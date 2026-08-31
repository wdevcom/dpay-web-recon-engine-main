<?php

namespace App\Ledger;

use App\Models\Account;
use App\Support\Money;
use Brick\Math\BigDecimal;
use DateTimeInterface;

/**
 * Builder pojedynczego zapisu księgowego. Gotowy obiekt idzie do
 * JournalPoster::post().
 *
 *   Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Wpłata na 12...', $dzis)
 *       ->debit($rachunekZbiorczy, '99.83')
 *       ->credit($zobowiazanieDomeny, '99.83')
 *       ->statementEntry($entry->id);
 */
final class Posting
{
    /** @var PostingLine[] */
    public array $lines = [];

    public ?DateTimeInterface $valueDate = null;
    public ?int $statementEntryId = null;
    public ?int $createdBy = null;
    public string $defaultCurrency = 'PLN';

    private function __construct(
        public readonly string $sourceType,
        public readonly string $description,
        public readonly DateTimeInterface $postedAt,
    ) {
    }

    public static function for(string $sourceType, string $description, DateTimeInterface $postedAt): self
    {
        return new self($sourceType, $description, $postedAt);
    }

    public function valueDate(?DateTimeInterface $date): self
    {
        $this->valueDate = $date;
        return $this;
    }

    public function statementEntry(?int $statementEntryId): self
    {
        $this->statementEntryId = $statementEntryId;
        return $this;
    }

    public function createdBy(?int $userId): self
    {
        $this->createdBy = $userId;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->defaultCurrency = $currency;
        return $this;
    }

    public function debit(Account $account, string|int|BigDecimal $amount, ?string $memo = null, ?string $externalRef = null): self
    {
        $this->lines[] = new PostingLine(
            account: $account,
            debit: Money::of($amount),
            credit: BigDecimal::zero()->toScale(2),
            currency: $account->currency ?? $this->defaultCurrency,
            memo: $memo,
            externalRef: $externalRef,
        );
        return $this;
    }

    public function credit(Account $account, string|int|BigDecimal $amount, ?string $memo = null, ?string $externalRef = null): self
    {
        $this->lines[] = new PostingLine(
            account: $account,
            debit: BigDecimal::zero()->toScale(2),
            credit: Money::of($amount),
            currency: $account->currency ?? $this->defaultCurrency,
            memo: $memo,
            externalRef: $externalRef,
        );
        return $this;
    }
}
