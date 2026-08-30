<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // sibs, blik, payu, paymentero, bank_pko, internal
            $table->string('name');
            $table->string('default_currency', 3)->default('PLN');
            $table->unsignedSmallInteger('match_window_days')->default(3);
            $table->decimal('amount_tolerance', 18, 2)->default(0);
            $table->foreignId('clearing_account_id')->nullable();    // FK assigned after accounts exist
            $table->foreignId('commission_account_id')->nullable();
            $table->foreignId('bank_account_id')->nullable();
            $table->json('sftp_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
