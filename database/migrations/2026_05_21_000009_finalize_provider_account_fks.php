<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->foreign('clearing_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('commission_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['clearing_account_id']);
            $table->dropForeign(['commission_account_id']);
            $table->dropForeign(['bank_account_id']);
        });
    }
};
