<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // over | short | duplicate | missing | wrong_amount | unexpected_fee | refund_outside_system | commission_settlement | other
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('PLN');
            $table->text('description')->nullable();
            $table->text('proposed_resolution')->nullable();
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'written_off'])->default('pending');
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resolution_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['provider_id', 'status']);
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable'); // discrepancy or journal_entry (manual_adjustment)
            $table->string('action'); // submitted | approved | rejected | reverted
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
    }
};
