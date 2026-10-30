<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashflow_expenses', function (Blueprint $table) {
            $table->foreignId('stock_transaction_id')->nullable()->after('cashflow_id')->constrained('stock_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cashflow_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_transaction_id');
        });
    }
};
