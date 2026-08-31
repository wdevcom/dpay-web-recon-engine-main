<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsumenci tego mikroserwisu - dpay-web-manager, dpay-web-eid,
 * dpay-web-esim. Klucze API trzymamy w osobnej tabeli, żeby dało się je
 * rotować bez utraty tożsamości konsumenta i bez przestoju.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();   // manager | eid | esim | partners
            $table->string('name');
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable(); // encrypted
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Trzymamy wyłącznie hash - klucz pokazujemy raz, przy nadaniu.
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 12)->index(); // do rozpoznania klucza w UI
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_keys');
        Schema::dropIfExists('tenants');
    }
};
