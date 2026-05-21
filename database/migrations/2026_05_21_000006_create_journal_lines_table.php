<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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

            $table->index(['account_id', 'created_at']);
            $table->index('external_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
