<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setoran_transfer_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('setoran_transfers')->cascadeOnDelete();
            $table->text('photo_url');
            $table->timestamps();

            $table->index('transfer_id');
        });

        // Satu setoran = satu nominal + banyak foto; rekening tujuan tidak lagi dipilih staff
        Schema::table('setoran_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_account_id')->nullable()->change();
            $table->text('proof_receipt_url')->nullable()->change();
        });

        // Data lama: gabungkan beberapa baris transfer per closing menjadi satu, foto pindah ke tabel proofs
        $now = now();
        foreach (DB::table('setoran_transfers')->orderBy('id')->get()->groupBy('cashflow_id') as $rows) {
            $main = $rows->first();
            foreach ($rows as $row) {
                if ($row->proof_receipt_url) {
                    DB::table('setoran_transfer_proofs')->insert([
                        'transfer_id' => $main->id,
                        'photo_url' => $row->proof_receipt_url,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            if ($rows->count() > 1) {
                DB::table('setoran_transfers')->where('id', $main->id)->update(['transfer_amount' => $rows->sum('transfer_amount')]);
                DB::table('setoran_transfers')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setoran_transfer_proofs');

        Schema::table('setoran_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_account_id')->nullable(false)->change();
            $table->text('proof_receipt_url')->nullable(false)->change();
        });
    }
};
