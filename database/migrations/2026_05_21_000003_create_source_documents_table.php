<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('format'); // mt940, csv_pko, sibs_csv, blik_psp_txt, payu_csv, paymentero_csv, internal
            $table->string('filename');
            $table->string('storage_path')->nullable();
            $table->string('file_hash', 64)->unique(); // sha256, dedup
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('received_via')->default('upload'); // upload | sftp | ftp | internal_pull
            $table->timestamp('received_at')->useCurrent();
            $table->enum('parse_status', ['pending', 'parsing', 'parsed', 'failed'])->default('pending');
            $table->json('parse_errors')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->timestamps();

            $table->index(['provider_id', 'parse_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_documents');
    }
};
