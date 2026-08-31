<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dziennik wywołań GOconnect.
 *
 * `message_id` musi tu wylądować ZANIM komunikat pójdzie do banku - przy
 * zleceniach to jedyny klucz, którym da się potem ustalić, czy bank przyjął
 * dyspozycję, gdy odpowiedź nie dotarła. Dla operacji odczytowych to zwykła
 * diagnostyka, ale schemat jest ten sam, żeby ścieżka zapisu była jedna.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bnp_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation');
            $table->string('message_id')->nullable()->index();
            $table->string('account_iban', 34)->nullable();
            $table->string('status')->default('sent'); // sent | ok | failed | uncertain
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['operation', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bnp_operations');
    }
};
