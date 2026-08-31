<?php

namespace Tests\Feature\Masscollect;

use App\Banking\Masscollect\AccountNumberMask;
use App\Banking\Masscollect\SequenceExhaustedException;
use App\Banking\Masscollect\VirtualAccountNumber;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\BankAccountNumber;
use PHPUnit\Framework\TestCase;

class AccountNumberMaskTest extends TestCase
{
    private function mask(): AccountNumberMask
    {
        return new AccountNumberMask('16001462', '0022', 1);
    }

    public function test_generated_numbers_pass_the_bank_libraries_own_checksum(): void
    {
        $mask = $this->mask();

        // Krzyżowa weryfikacja: sumę kontrolną liczymy sami, ale sprawdza ją
        // walidator z biblioteki bankowej - ten sam, który odrzuci numer
        // przed wysłaniem czegokolwiek do BNP.
        foreach ([0, 1, 7, 12345, 999999999] as $sequence) {
            $number = VirtualAccountNumber::generate($mask, '1', $sequence);

            $validated = BankAccountNumber::fromString($number->iban());

            $this->assertSame($number->nrb, $validated->nrb());
            $this->assertSame(26, strlen($number->nrb));
        }
    }

    public function test_domain_digit_and_sequence_are_readable_back_from_the_number(): void
    {
        $mask = $this->mask();
        $number = VirtualAccountNumber::generate($mask, '3', 4242);

        // To jest sedno podziału domenowego: właściciela wpłaty da się
        // ustalić z samego numeru na wyciągu, bez zapytania do bazy.
        $this->assertSame('3', $mask->domainDigitsOf($number->nrb));
        $this->assertSame(4242, $mask->sequenceOf($number->nrb));
    }

    public function test_recognises_only_numbers_from_our_mask(): void
    {
        $mask = $this->mask();

        $ours = VirtualAccountNumber::generate($mask, '2', 99);

        $this->assertTrue($mask->matches($ours->nrb));
        $this->assertTrue($mask->matches($ours->iban()));
        $this->assertTrue($mask->matches(chunk_split($ours->nrb, 4, ' ')));

        // Inny bank i inny prefiks tego samego banku - obce.
        $this->assertFalse($mask->matches('56114020040000000000000000'));
        $this->assertFalse($mask->matches('69160014629999000000000000'));
        $this->assertNull($mask->domainDigitsOf('56114020040000000000000000'));
    }

    public function test_capacity_matches_the_digits_left_for_the_sequence(): void
    {
        $mask = $this->mask();

        // 16 cyfr rachunku - 4 prefiksu - 1 domeny = 11 cyfr sekwencji.
        $this->assertSame(11, $mask->sequenceLength());
        $this->assertSame(100_000_000_000, $mask->capacityPerDomain());
        $this->assertSame(10, $mask->domainCount());
    }

    public function test_refuses_to_build_a_number_beyond_the_pool(): void
    {
        $mask = $this->mask();

        $this->expectException(SequenceExhaustedException::class);

        $mask->build('1', $mask->capacityPerDomain());
    }

    public function test_rejects_a_mask_that_leaves_no_room_for_a_sequence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AccountNumberMask('16001462', '0022222222222222', 1);
    }
}
