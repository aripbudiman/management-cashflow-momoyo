<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRESETS = [
        'pcs', 'box', 'pack', 'karton', 'dus', 'lusin', 'kodi', 'botol', 'kaleng', 'sachet',
        'bungkus', 'kantong', 'toples', 'jerigen', 'galon', 'ikat', 'butir', 'tray', 'rak',
        'gram', 'kg', 'ons', 'ml', 'liter', 'sak', 'karung',
    ];

    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor', 12, 2); // jumlah satuan terkecil dalam 1 satuan ini
            $table->boolean('is_base')->default(false);
            $table->timestamps();

            $table->unique(['item_id', 'unit_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('price_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('default_in_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('default_out_unit_id')->nullable()->constrained('units')->nullOnDelete();
        });

        Schema::table('stock_report_items', function (Blueprint $table) {
            $table->json('inputs')->nullable(); // input asli staff per kolom: {in: {qty, unit_id}, ...}
        });

        $now = now();
        foreach (self::PRESETS as $name) {
            DB::table('units')->insertOrIgnore(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Kolom items.unit lama menjadi satuan terkecil (faktor 1)
        DB::table('items')->orderBy('id')->each(function ($item) use ($now) {
            $name = trim((string) $item->unit) ?: 'pcs';
            $unitId = DB::table('units')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->value('id')
                ?? DB::table('units')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);

            DB::table('item_units')->insert([
                'item_id' => $item->id, 'unit_id' => $unitId, 'factor' => 1, 'is_base' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('items')->where('id', $item->id)->update([
                'price_unit_id' => $unitId, 'default_in_unit_id' => $unitId, 'default_out_unit_id' => $unitId,
            ]);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('unit', 30)->default('pcs');
        });

        DB::table('items')->orderBy('id')->each(function ($item) {
            $name = DB::table('item_units')
                ->join('units', 'units.id', '=', 'item_units.unit_id')
                ->where('item_units.item_id', $item->id)
                ->where('item_units.is_base', true)
                ->value('units.name');
            DB::table('items')->where('id', $item->id)->update(['unit' => $name ?? 'pcs']);
        });

        Schema::table('stock_report_items', function (Blueprint $table) {
            $table->dropColumn('inputs');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_unit_id');
            $table->dropConstrainedForeignId('default_in_unit_id');
            $table->dropConstrainedForeignId('default_out_unit_id');
        });

        Schema::dropIfExists('item_units');
        Schema::dropIfExists('units');
    }
};
