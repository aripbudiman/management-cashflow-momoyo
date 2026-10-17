<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockReport;
use App\Models\StockTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StockRepository
{
    private const REPORT_RELATIONS = ['branch:id,branch_name,branch_code', 'submittedBy:id,name', 'verifiedBy:id,name', 'items.item.itemUnits.unit'];

    /**
     * Barang aktif beserta stok saat ini di satu cabang.
     *
     * @return Collection<int, Item>
     */
    public function activeItemsWithStock(int $branchId): Collection
    {
        return Item::query()
            ->where('is_active', true)
            ->with(['itemUnits.unit', 'branchStocks' => fn ($q) => $q->where('branch_id', $branchId)])
            ->orderBy('name')
            ->get()
            ->each(fn (Item $item) => $item->setAttribute('stock_qty', $item->branchStocks->first()?->qty ?? '0.00'));
    }

    public function findReport(int $branchId, string $date): ?StockReport
    {
        return StockReport::with(self::REPORT_RELATIONS)
            ->where('branch_id', $branchId)
            ->where('report_date', $date)
            ->first();
    }

    /**
     * @return Collection<int, StockReport>
     */
    public function historyForBranch(int $branchId, int $limit = 14): Collection
    {
        return StockReport::where('branch_id', $branchId)
            ->latest('report_date')
            ->limit($limit)
            ->get(['id', 'report_date', 'status', 'revision_notes']);
    }

    /**
     * @param  array{branch_id?: mixed, status?: mixed, month?: mixed}  $filters
     */
    public function paginateReports(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return StockReport::with(self::REPORT_RELATIONS)
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->whereBetween('report_date', [$v.'-01', date('Y-m-t', strtotime($v.'-01'))]))
            ->orderByRaw("case when status = 'submitted' then 0 else 1 end")
            ->latest('report_date')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Matriks stok barang x cabang untuk pantauan pusat.
     *
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, Item>
     */
    public function stockMatrix(Collection $branches, ?string $search = null): Collection
    {
        return Item::query()
            ->where('is_active', true)
            ->when($search, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->with(['itemUnits.unit', 'branchStocks' => fn ($q) => $q->whereIn('branch_id', $branches->pluck('id'))])
            ->orderBy('name')
            ->get()
            ->each(fn (Item $item) => $item->setAttribute('stocks', $item->branchStocks->pluck('qty', 'branch_id')));
    }

    /**
     * Kartu stok: saldo awal periode + daftar mutasi.
     *
     * @return array{opening: float, movements: Collection<int, StockMovement>}
     */
    public function stockCard(int $branchId, int $itemId, string $from, string $to): array
    {
        $opening = (float) StockMovement::where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->where('moved_at', '<', $from)
            ->sum('qty');

        $movements = StockMovement::with('user:id,name')
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->whereBetween('moved_at', [$from, $to])
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        return ['opening' => $opening, 'movements' => $movements];
    }

    /**
     * @return Collection<int, StockTransfer>
     */
    public function transfers(?int $branchId, int $limit = 30): Collection
    {
        return StockTransfer::with(['fromBranch:id,branch_name', 'toBranch:id,branch_name', 'item.itemUnits.unit', 'sentBy:id,name', 'receivedBy:id,name'])
            ->when($branchId, fn ($q, $v) => $q->where(fn ($w) => $w->where('from_branch_id', $v)->orWhere('to_branch_id', $v)))
            ->latest('sent_at')
            ->limit($limit)
            ->get();
    }
}
