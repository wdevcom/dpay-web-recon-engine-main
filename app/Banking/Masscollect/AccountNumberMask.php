<?php

namespace App\Banking\Masscollect;

use InvalidArgumentException;

/**
 * Maska numeru rachunku wirtualnego masscollect.
 *
 * Polski NRB to 26 cyfr: [2 kontrolne][8 numeru rozliczeniowego][16 rachunku].
 * Bank nadaje prefiks w części 16-cyfrowej, my dokładamy cyfrę domeny i
 * sekwencję:
 *
 *   NRB = CC | 16001462 | 0022 | 1 | 00000000001
 *              ^bank      ^bank  ^my  ^my
 *
 * Cyfry kontrolne liczymy sami (ISO 7064 mod 97-10) - dzięki temu numer da
 * się wygenerować bez odpytywania banku, a błędny numer nie ma prawa
 * opuścić tego procesu.
 */
final class AccountNumberMask
{
    public const NRB_LENGTH = 26;
    public const BANK_CODE_LENGTH = 8;
    public const ACCOUNT_PART_LENGTH = 16;

    public function __construct(
        public readonly string $bankCode,
        public readonly string $clientPrefix,
        public readonly int $domainDigits,
    ) {
        if (preg_match('/^\d{'.self::BANK_CODE_LENGTH.'}$/', $bankCode) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Numer rozliczeniowy banku musi mieć dokładnie %d cyfr, otrzymano "%s".', self::BANK_CODE_LENGTH, $bankCode)
            );
        }
        if (preg_match('/^\d*$/', $clientPrefix) !== 1) {
            throw new InvalidArgumentException(sprintf('Prefiks klienta musi być ciągiem cyfr, otrzymano "%s".', $clientPrefix));
        }
        if ($domainDigits < 1) {
            throw new InvalidArgumentException('Domena musi zajmować co najmniej jedną cyfrę.');
        }
        if (strlen($clientPrefix) + $domainDigits >= self::ACCOUNT_PART_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Prefiks (%d) i cyfry domeny (%d) nie zostawiają miejsca na sekwencję w %d-cyfrowej części rachunku.',
                    strlen($clientPrefix),
                    $domainDigits,
                    self::ACCOUNT_PART_LENGTH
                )
            );
        }
    }

    public static function fromConfig(?array $config = null): self
    {
        $config ??= config('bnp.masscollect');

        return new self(
            (string) ($config['bank_code'] ?? ''),
            (string) ($config['client_prefix'] ?? ''),
            (int) ($config['domain_digits'] ?? 1),
        );
    }

    /** Ile cyfr zostaje na numer porządkowy w obrębie domeny. */
    public function sequenceLength(): int
    {
        return self::ACCOUNT_PART_LENGTH - strlen($this->clientPrefix) - $this->domainDigits;
    }

    /** Pojemność jednej domeny - tyle numerów da się wydać, zanim zabraknie. */
    public function capacityPerDomain(): int
    {
        return 10 ** $this->sequenceLength();
    }

    /** Ile domen mieści się w przyjętej liczbie cyfr domeny. */
    public function domainCount(): int
    {
        return 10 ** $this->domainDigits;
    }

    public function assertDomainDigits(string $digits): void
    {
        if (strlen($digits) !== $this->domainDigits || preg_match('/^\d+$/', $digits) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Cyfry domeny muszą być %d-cyfrową liczbą, otrzymano "%s".', $this->domainDigits, $digits)
            );
        }
    }

    /**
     * Buduje NRB dla wskazanej domeny i numeru porządkowego.
     */
    public function build(string $domainDigits, int $sequence): string
    {
        $this->assertDomainDigits($domainDigits);

        if ($sequence < 0 || $sequence >= $this->capacityPerDomain()) {
            throw new SequenceExhaustedException(
                sprintf(
                    'Numer porządkowy %d wykracza poza pojemność domeny "%s" (%d numerów).',
                    $sequence,
                    $domainDigits,
                    $this->capacityPerDomain()
                )
            );
        }

        $accountPart = $this->clientPrefix
            . $domainDigits
            . str_pad((string) $sequence, $this->sequenceLength(), '0', STR_PAD_LEFT);

        $bban = $this->bankCode . $accountPart;

        return $this->checkDigits($bban) . $bban;
    }

    /**
     * Czy numer w ogóle należy do naszej maski. Wołane przy dopasowywaniu
     * pozycji z wyciągu - odsiewa rachunki obce, zanim pójdzie zapytanie
     * do bazy.
     */
    public function matches(string $nrb): bool
    {
        $normalized = self::normalize($nrb);

        return preg_match('/^\d{'.self::NRB_LENGTH.'}$/', $normalized) === 1
            && str_starts_with(substr($normalized, 2), $this->bankCode . $this->clientPrefix);
    }

    /** Cyfry domeny odczytane z numeru - routing bez zapytania do bazy. */
    public function domainDigitsOf(string $nrb): ?string
    {
        if (! $this->matches($nrb)) {
            return null;
        }

        $offset = 2 + self::BANK_CODE_LENGTH + strlen($this->clientPrefix);

        return substr(self::normalize($nrb), $offset, $this->domainDigits);
    }

    /** Numer porządkowy odczytany z numeru rachunku. */
    public function sequenceOf(string $nrb): ?int
    {
        if (! $this->matches($nrb)) {
            return null;
        }

        $offset = 2 + self::BANK_CODE_LENGTH + strlen($this->clientPrefix) + $this->domainDigits;

        return (int) substr(self::normalize($nrb), $offset, $this->sequenceLength());
    }

    /**
     * Cyfry kontrolne wg ISO 7064 mod 97-10, liczone blokami po 7 znaków,
     * żeby nie przekroczyć zakresu int na 30-cyfrowej liczbie bez bcmath.
     * "PL" to 25 i 21, stąd stała "2521".
     */
    public function checkDigits(string $bban24): string
    {
        if (preg_match('/^\d{24}$/', $bban24) !== 1) {
            throw new InvalidArgumentException('Część BBAN musi mieć 24 cyfry.');
        }

        $numeric = $bban24 . '2521' . '00';

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder . $chunk) % 97;
        }

        return str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT);
    }

    public static function normalize(string $accountNumber): string
    {
        $value = strtoupper((string) preg_replace('/[\s-]/', '', $accountNumber));

        return str_starts_with($value, 'PL') ? substr($value, 2) : $value;
    }
}
