<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pojedynczy rachunek wirtualny (mikro rachunek) wydany konsumentowi.
 *
 * `(tenant_id, owner_type, owner_ref)` jest unikalne - to czyni alokację
 * idempotentną. Konsument, który powtórzy żądanie po timeoucie, dostanie
 * ten sam numer zamiast wypalić drugi i rozjechać sobie rozliczenie.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('iban', 34)->unique();
            $table->string('nrb', 26)->unique();

            $table->foreignId('masscollect_domain_id')->constrained();
            $table->unsignedBigInteger('sequence');
            $table->foreignId('tenant_id')->constrained();

            // Referencja po stronie konsumenta: id transakcji, sesji
            // weryfikacyjnej, lokalizacji. Dla nas nieprzezroczysta.
            $table->string('owner_type');
            $table->string('owner_ref');
            $table->string('label')->nullable();

            $table->string('lifecycle');   // persistent | one_time
            // allocated - wydany, brak wpłat
            // active    - wpłynęła co najmniej jedna wpłata
            // expired   - minął termin, dalsze wpłaty trafiają do wyjaśnienia
            // released  - zamknięty przez konsumenta
            $table->string('status')->default('allocated');

            $table->bigInteger('expected_amount_minor')->nullable();
            $table->string('currency', 3)->default('PLN');

            $table->bigInteger('received_amount_minor')->default(0);
            $table->unsignedInteger('payments_count')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('first_payment_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['masscollect_domain_id', 'sequence']);
            $table->unique(['tenant_id', 'owner_type', 'owner_ref']);
            $table->index(['status', 'expires_at']);
            $table->index('owner_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_accounts');
    }
};
