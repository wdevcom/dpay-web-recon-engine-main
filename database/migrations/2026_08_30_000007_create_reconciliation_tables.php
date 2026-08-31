<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile: zestawienie naszej księgi z danymi konsumenta (docelowo
 * dpay-web-manager) za okres, plus rejestr rozjazdów i ścieżka akceptacji.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('group_no')->unique();       // REC-2026-000001
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('masscollect_domain_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('currency', 3)->default('PLN');

            // Nasza strona (bank + księga) kontra strona konsumenta.
            $table->bigInteger('bank_total_minor')->default(0);
            $table->bigInteger('counterparty_total_minor')->default(0);
            $table->bigInteger('difference_minor')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);

            $table->string('status')->default('open');  // open | balanced | with_discrepancy | closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_from']);
        });

        Schema::create('discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('statement_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('virtual_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // unidentified_payment | amount_mismatch | missing_in_bank
            // | missing_at_consumer | duplicate | payment_after_expiry | other
            $table->string('type');
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->text('description')->nullable();
            $table->text('proposed_resolution')->nullable();
            $table->string('status')->default('pending');   // pending | under_review | approved | rejected | written_off
            $table->string('severity')->default('warning'); // info | warning | critical
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resolution_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['type', 'status']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->string('action');   // submitted | approved | rejected | reverted
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('discrepancies');
        Schema::dropIfExists('reconciliations');
    }
};
