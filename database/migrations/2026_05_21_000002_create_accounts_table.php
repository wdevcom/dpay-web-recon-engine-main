<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();   // BANK.96253..., CLEARING.SIBS, MERCHANT.dpay
            $table->string('name');
            // type drives normal balance side and reporting bucket
            $table->enum('type', [
                'bank',
                'clearing',
                'merchant',
                'commission',
                'suspense',
                'discrepancy',
                'revenue',
                'expense',
                'equity',
            ]);
            $table->string('currency', 3)->default('PLN');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('external_ref')->nullable(); // e.g. bank account number for type=bank
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
