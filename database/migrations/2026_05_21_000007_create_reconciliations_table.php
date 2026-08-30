<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('group_no')->unique(); // REC-2026-000001
            $table->foreignId('provider_id')->constrained();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('expected_total', 18, 2)->default(0);
            $table->decimal('actual_total', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->enum('status', ['open', 'balanced', 'with_discrepancy', 'closed'])->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
            $table->index(['period_from', 'period_to']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_row_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // provider_report | bank_credit | bank_debit | internal_txn | adjustment
            $table->timestamps();

            $table->unique(['reconciliation_id', 'source_row_id']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliations');
    }
};
