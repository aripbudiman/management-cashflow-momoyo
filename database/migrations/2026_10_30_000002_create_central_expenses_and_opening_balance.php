<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('radius_meters');
        });

        Schema::create('central_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            // null = dibayar langsung dari uang bos / kas pusat (tidak memotong saldo cabang mana pun)
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('category', 40);
            $table->decimal('nominal', 15, 2);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_expenses');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
