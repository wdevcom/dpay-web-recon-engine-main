<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Księga podwójnego zapisu prowadzona przez ten serwis.
 *
 * To jest nasz niezależny obraz pieniądza w banku - druga księga wobec
 * dpay-web-manager. Reconcile polega na zestawieniu tych dwóch, więc księgi
 * muszą powstawać niezależnie; kopiowanie sald z managera zniszczyłoby
 * sens kontroli.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // bank        - rachunek rzeczywisty w BNP
            // masscollect - zbiorcze konto domeny mikro rachunków
            // payable     - zobowiązania wobec konsumenta/merchanta
            // suspense    - wpłaty niezidentyfikowane
            // discrepancy - rozjazdy in-plus / in-minus
            // fee, revenue, expense, equity
            $table->string('type');
            $table->string('currency', 3)->default('PLN');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('masscollect_domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no')->unique();   // JE-2026-000001
            $table->date('posted_at');
            $table->date('value_date')->nullable();
            $table->text('description');
            // bank_statement | masscollect_payment | payout | fee
            // | settlement | manual_adjustment | reversal
            $table->string('source_type');
            $table->foreignId('statement_entry_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('posted');   // posted | reversed
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('total', 18, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->timestamps();

            $table->index(['source_type', 'posted_at']);
            $table->index(['status', 'posted_at']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->string('memo')->nullable();
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
            $table->index('external_ref');
        });

        /*
         * Numeracja księgi. Osobna tabela zamiast MAX(entry_no)+1, bo przy
         * dwóch workerach kolejki MAX+1 wyprodukuje ten sam numer i wpis
         * padnie na unikalnym indeksie w środku przetwarzania wyciągu.
         */
        Schema::create('ledger_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique();  // np. "JE-2026"
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_sequences');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
