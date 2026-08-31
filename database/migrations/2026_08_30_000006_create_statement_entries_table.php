<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pozycje z banku - camt.052 (historia, także przyrostowa) i camt.053
 * (wyciąg dzienny, w tym wyciąg MBR z rachunków wirtualnych).
 *
 * Pobieranie zawsze zachodzi na siebie w czasie, więc dedup jest
 * obowiązkowy: `fingerprint` liczymy z pól, które bank powtarza
 * deterministycznie dla tej samej operacji.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained();
            $table->string('source');            // history | incremental | statement | mbr_statement
            $table->string('message_id')->nullable();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('entries_count')->default(0);
            $table->unsignedInteger('new_entries_count')->default(0);
            $table->string('document_namespace')->nullable();
            $table->timestamp('fetched_at')->useCurrent();
            $table->timestamps();

            $table->index(['bank_account_id', 'period_from', 'period_to']);
        });

        Schema::create('statement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained();
            $table->foreignId('bank_statement_id')->nullable()->constrained()->nullOnDelete();

            $table->string('direction', 4);              // CRDT | DBIT
            $table->bigInteger('amount_minor');          // zawsze dodatnia, znak niesie direction
            $table->string('currency', 3)->default('PLN');

            $table->date('booking_date')->nullable();
            $table->date('value_date')->nullable();

            // Refs z camt - trzy różne identyfikatory, mylone nagminnie.
            $table->unsignedInteger('transaction_number')->nullable(); // Refs/MsgId, porządkowy w dniu
            $table->string('instruction_id')->nullable();              // Refs/InstrId, referencja banku
            $table->string('end_to_end_id')->nullable();               // Refs/EndToEnd, nasza referencja
            $table->string('transaction_id')->nullable();              // Refs/TxId

            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account', 34)->nullable();
            $table->text('remittance_text')->nullable();

            // Rachunek wirtualny odczytany z pozycji - dla wpłat masscollect
            // bank podaje go w drugim Ustrd albo jako rachunek uznany.
            $table->string('detected_virtual_account', 34)->nullable();
            $table->foreignId('virtual_account_id')->nullable()->constrained()->nullOnDelete();

            // unmatched | matched | suspense | ignored
            $table->string('match_status')->default('unmatched');
            $table->string('match_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();

            $table->string('fingerprint', 64)->unique();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'booking_date']);
            $table->index(['match_status', 'booking_date']);
            $table->index('detected_virtual_account');
            $table->index('end_to_end_id');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('statement_entry_id')->references('id')->on('statement_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['statement_entry_id']);
        });
        Schema::dropIfExists('statement_entries');
        Schema::dropIfExists('bank_statements');
    }
};
