<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Unit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemService
{
    /**
     * Simpan barang beserta satuan & konversinya. Satuan terkecil (faktor 1) adalah dasar penyimpanan stok.
     *
     * @param  array{name: string, price: mixed, min_stock: mixed, is_active: bool, price_unit_id: int, default_in_unit_id?: ?int, default_out_unit_id?: ?int, units: array<int, array{unit_id: int, factor: mixed}>}  $data
     */
    public function save(?Item $item, array $data): Item
    {
        return DB::transaction(function () use ($item, $data) {
            $units = collect($data['units'])
                ->map(fn (array $u) => ['unit_id' => (int) $u['unit_id'], 'factor' => round((float) $u['factor'], 2)])
                ->sortBy('factor')
                ->values();

            $baseId = $units->first()['unit_id'];

            if ($item && $item->baseUnitId() !== $baseId && ($item->movements()->exists() || $item->branchStocks()->exists())) {
                throw ValidationException::withMessages(['units' => 'Satuan terkecil tidak bisa diganti karena barang sudah punya riwayat stok.']);
            }

            $attrs = Arr::only($data, ['name', 'price', 'min_stock', 'is_active']) + [
                'price_unit_id' => $data['price_unit_id'] ?? $baseId,
                'default_in_unit_id' => $data['default_in_unit_id'] ?? $units->last()['unit_id'],
                'default_out_unit_id' => $data['default_out_unit_id'] ?? $baseId,
            ];

            if ($item) {
                $item->update($attrs);
            } else {
                $item = Item::create($attrs);
            }

            $item->itemUnits()->whereNotIn('unit_id', $units->pluck('unit_id'))->delete();
            foreach ($units as $index => $unit) {
                ItemUnit::updateOrCreate(
                    ['item_id' => $item->id, 'unit_id' => $unit['unit_id']],
                    ['factor' => $unit['factor'], 'is_base' => $index === 0],
                );
            }

            return $item->unsetRelation('itemUnits');
        });
    }

    /**
     * Impor massal: barang baru dibuat dengan satu satuan (terkecil). Barang yang sudah ada hanya diperbarui
     * harganya jika satuan baris impor sama dengan satuan harga barang tersebut.
     *
     * @param  array<int, array{name: string, unit: string, price: mixed}>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $rows): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, &$result) {
            foreach ($rows as $row) {
                $unitName = trim($row['unit']);
                $unit = Unit::whereRaw('lower(name) = ?', [mb_strtolower($unitName)])->first() ?? Unit::create(['name' => $unitName]);
                $item = Item::whereRaw('lower(name) = ?', [mb_strtolower(trim($row['name']))])->first();

                if (! $item) {
                    $this->save(null, [
                        'name' => trim($row['name']),
                        'price' => $row['price'],
                        'min_stock' => 0,
                        'is_active' => true,
                        'price_unit_id' => $unit->id,
                        'units' => [['unit_id' => $unit->id, 'factor' => 1]],
                    ]);
                    $result['created']++;
                } elseif ($item->price_unit_id === $unit->id) {
                    $item->update(['price' => $row['price']]);
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            }
        });

        return $result;
    }
}
