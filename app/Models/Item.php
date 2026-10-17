<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Item extends Model
{
    protected $fillable = [
        'name', 'price', 'min_stock', 'is_active',
        'price_unit_id', 'default_in_unit_id', 'default_out_unit_id',
    ];

    protected $hidden = ['itemUnits'];

    protected $appends = ['unit', 'unit_options'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function itemUnits(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Satuan barang, urut dari terkecil ke terbesar.
     *
     * @return Collection<int, ItemUnit>
     */
    public function sortedUnits(): Collection
    {
        $this->loadMissing('itemUnits.unit');

        return $this->itemUnits->sortBy(fn (ItemUnit $u) => (float) $u->factor)->values();
    }

    /** Nama satuan terkecil (dasar penyimpanan stok). */
    public function getUnitAttribute(): ?string
    {
        return $this->sortedUnits()->first()?->unit->name;
    }

    /**
     * @return array<int, array{unit_id: int, name: string, factor: float}>
     */
    public function getUnitOptionsAttribute(): array
    {
        return $this->sortedUnits()
            ->map(fn (ItemUnit $u) => ['unit_id' => $u->unit_id, 'name' => $u->unit->name, 'factor' => (float) $u->factor])
            ->all();
    }

    public function baseUnitId(): ?int
    {
        return $this->sortedUnits()->first()?->unit_id;
    }

    public function defaultInUnitId(): ?int
    {
        return $this->default_in_unit_id ?? $this->sortedUnits()->last()?->unit_id;
    }

    public function defaultOutUnitId(): ?int
    {
        return $this->default_out_unit_id ?? $this->baseUnitId();
    }

    /** Pengali satuan ke satuan terkecil; null jika satuan bukan milik barang ini. */
    public function factorFor(?int $unitId): ?float
    {
        if ($unitId === null) {
            return 1.0;
        }

        $unit = $this->sortedUnits()->firstWhere('unit_id', $unitId);

        return $unit ? (float) $unit->factor : null;
    }

    /** Harga per satuan terkecil (harga master diinput per `price_unit_id`). */
    public function basePrice(): float
    {
        $factor = $this->price_unit_id ? ($this->factorFor($this->price_unit_id) ?: 1.0) : 1.0;

        return round((float) $this->price / $factor, 2);
    }
}
