<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rachunki rzeczywiste w BNP Paribas.
 *
 * GOconnect Biznes NIE MA operacji zwracającej listę rachunków - kanał
 * pozwala tylko odpytać rachunek, który już znamy. Dlatego to jest rejestr
 * po naszej stronie, a `bnp:sync-accounts` jedynie odświeża z banku salda,
 * walutę i dostępność (GetAccountBalance per rachunek).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('iban', 34)->unique();
            $table->string('name');
            $table->string('currency', 3)->default('PLN');
            // operational  - rachunek bieżący
            // masscollect  - rachunek zbiorczy, na który spływają wpłaty na rachunki wirtualne
            // settlement   - rachunek rozliczeniowy providera (BLIK, Visa/MC, PayU)
            $table->string('purpose')->default('operational');
            $table->boolean('is_active')->default(true);

            // Migawka z ostatniej synchronizacji. Grosze jako bigint - tak samo
            // jak w bibliotece bankowej, żeby nie było miejsca na zaokrąglenie.
            $table->bigInteger('available_balance_minor')->nullable();
            $table->bigInteger('booked_balance_minor')->nullable();
            $table->timestamp('balance_synced_at')->nullable();
            $table->string('sync_error')->nullable();

            // Pobieranie przyrostowe idzie po numerze porządkowym operacji
            // W DANYM DNIU, więc numer ma sens tylko razem z datą.
            $table->unsignedInteger('last_transaction_number')->nullable();
            $table->date('last_transaction_date')->nullable();
            $table->timestamp('history_synced_at')->nullable();

            $table->timestamps();

            $table->index(['purpose', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
