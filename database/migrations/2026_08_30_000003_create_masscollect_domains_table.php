<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podział domenowy przestrzeni numerów masscollect.
 *
 * Cyfra domeny siedzi wprost w numerze rachunku, tuż za prefiksem nadanym
 * przez bank. Dzięki temu z samego numeru na wyciągu wiadomo, czyja jest
 * wpłata - bez zapytania do bazy i nawet wtedy, gdy alokacja została
 * skasowana.
 *
 * `next_sequence` jest licznikiem wypalanym w transakcji z blokadą wiersza;
 * numery nie wracają do puli, bo recykling to najczęstsze źródło wpłat
 * zaksięgowanych na nowego właściciela.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('masscollect_domains', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // manager | eid | esim | partners
            $table->string('digits', 4)->unique();     // cyfra (lub cyfry) domeny w numerze
            $table->string('name');

            // persistent - numer jest tożsamością (lokalizacja, partner)
            // one_time   - numer jest referencją operacji (transakcja, weryfikacja)
            $table->string('lifecycle')->default('one_time');

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->constrained();

            $table->unsignedBigInteger('next_sequence')->default(1);
            $table->unsignedBigInteger('allocated_count')->default(0);

            // Domyślny czas życia alokacji jednorazowej; null = bezterminowo.
            $table->unsignedInteger('default_ttl_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masscollect_domains');
    }
};
