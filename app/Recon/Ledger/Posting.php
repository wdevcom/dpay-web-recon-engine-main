<?php

namespace App\Recon\Ledger;

use App\Models\Account;
use App\Recon\Support\Money;
use Brick\Math\BigDecimal;
use DateTimeInterface;

/**
 * Fluent builder for a single journal entry. Hand it to JournalPoster::post().
 *
 *   Posting::for(JournalEntry::SRC_BANK, 'BLIK payout 2026-05-21', $today)
 *       ->debit($bankAcc,  '99.83')
 *       ->credit($clearing,'99.83')
 *       ->valueDate($valueDate)
 *       ->memo('Nest Bank S.A. PSP-000002919')
 *       ->externalRef('PSP-000002919');
 */
final class Posting
{
    /** @var PostingLine[] */
    public array $lines = [];

    public ?DateTimeInterface $valueDate = null;
    public ?int $sourceRowId = null;
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

    public function sourceRow(?int $sourceRowId): self
    {
        $this->sourceRowId = $sourceRowId;
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

    public function debit(Account $account, string|float|int|BigDecimal $amount, ?string $memo = null, ?string $externalRef = null): self
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

    public function credit(Account $account, string|float|int|BigDecimal $amount, ?string $memo = null, ?string $externalRef = null): self
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
