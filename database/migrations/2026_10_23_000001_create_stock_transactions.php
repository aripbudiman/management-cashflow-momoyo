<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('type', 10); // in, out
            $table->date('moved_at');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->string('status', 20)->default('active'); // active, cancelled
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'moved_at']);
        });

        Schema::create('stock_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('stock_transactions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('type', 10); // in, out, waste
            $table->decimal('input_qty', 12, 2); // jumlah sesuai input staff
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('base_qty', 12, 2); // jumlah dalam satuan terkecil
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['transaction_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->constrained('stock_transactions')->cascadeOnDelete();
        });

        Schema::table('stock_report_items', function (Blueprint $table) {
            $table->decimal('transfer_qty', 12, 2)->default(0); // net transfer masuk/keluar hari itu
        });
    }

    public function down(): void
    {
        Schema::table('stock_report_items', function (Blueprint $table) {
            $table->dropColumn('transfer_qty');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });

        Schema::dropIfExists('stock_transaction_items');
        Schema::dropIfExists('stock_transactions');
    }
};
