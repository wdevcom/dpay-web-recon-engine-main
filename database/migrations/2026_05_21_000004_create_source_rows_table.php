<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_no');
            $table->json('raw_payload');     // original columns as parsed
            $table->json('normalized');      // canonical fields (see Recon\Parsers\ParsedRow)
            $table->string('external_id')->nullable();   // provider txn id, bank ref, etc.
            $table->date('value_date')->nullable();
            $table->date('posted_at')->nullable();
            $table->decimal('gross_amount', 18, 2)->nullable();
            $table->decimal('net_amount', 18, 2)->nullable();
            $table->decimal('commission_amount', 18, 2)->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->string('row_type')->nullable(); // sale | refund | payout | fee | bank_credit | bank_debit | internal_txn | ...
            $table->enum('match_status', ['unmatched', 'matched', 'partial', 'manual', 'ignored'])->default('unmatched');
            $table->foreignId('journal_entry_id')->nullable();
            $table->timestamps();

            $table->index(['external_id']);
            $table->index(['value_date', 'row_type']);
            $table->index(['match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_rows');
    }
};
