<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bukti foto opsional untuk pengeluaran pusat & pengeluaran proyek
        Schema::create('expense_proofs', function (Blueprint $table) {
            $table->id();
            $table->morphs('proofable');
            $table->text('photo_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_proofs');
    }
};
