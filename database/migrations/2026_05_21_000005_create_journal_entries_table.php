<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no')->unique(); // JE-2026-000001
            $table->date('posted_at');
            $table->date('value_date')->nullable();
            $table->text('description');
            $table->string('source_type'); // bank_statement | sibs_report | blik_psp | payu_report | paymentero_report | internal_txn | internal_payout | manual_adjustment
            $table->foreignId('source_row_id')->nullable()->constrained('source_rows')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('posted');
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('total', 18, 2)->default(0); // sum of DR (= sum of CR)
            $table->string('currency', 3)->default('PLN');
            $table->timestamps();

            $table->index(['source_type', 'posted_at']);
            $table->index(['status', 'posted_at']);
        });

        // Add the FK from source_rows -> journal_entries now that journal_entries exists.
        Schema::table('source_rows', function (Blueprint $table) {
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('source_rows', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
        });
        Schema::dropIfExists('journal_entries');
    }
};
