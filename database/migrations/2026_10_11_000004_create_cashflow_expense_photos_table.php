<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_expense_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('cashflow_expenses')->cascadeOnDelete();
            $table->text('photo_url');
            $table->timestamps();

            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_expense_photos');
    }
};
