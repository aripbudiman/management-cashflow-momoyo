<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Item;
use App\Models\StockMovement;
use App\Repositories\StockRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StockMonitorController extends Controller
{
    public function __construct(private readonly StockRepository $stocks) {}

    public function index(Request $request): Response
    {
        $branches = $this->visibleBranches($request);
        $items = $this->stocks->stockMatrix($branches, $request->input('search'));

        return Inertia::render('stock-monitor/Index', [
            'branches' => $branches->map->only(['id', 'branch_name'])->values(),
            'items' => $items->map(fn (Item $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'price' => $i->price,
                'min_stock' => $i->min_stock,
                'stocks' => $i->stocks,
            ])->values(),
            'filters' => $request->only('search'),
        ]);
    }

    public function card(Request $request): Response
    {
        $branches = $this->visibleBranches($request);
        $branchId = (int) $request->input('branch_id', $branches->first()?->id);
        abort_unless($branches->contains('id', $branchId), 403);

        $itemId = (int) $request->input('item_id');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('from')) ? $request->input('from') : today()->startOfMonth()->toDateString();
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('to')) ? $request->input('to') : today()->toDateString();

        $card = $itemId ? $this->stocks->stockCard($branchId, $itemId, $from, $to) : null;

        return Inertia::render('stock-monitor/Card', [
            'branches' => $branches->map->only(['id', 'branch_name'])->values(),
            'items' => Item::orderBy('name')->get(['id', 'name', 'unit']),
            'filters' => ['branch_id' => $branchId, 'item_id' => $itemId ?: null, 'from' => $from, 'to' => $to],
            'types' => StockMovement::TYPES,
            'opening' => $card['opening'] ?? 0,
            'movements' => $card['movements'] ?? [],
        ]);
    }

    /**
     * Admin pusat melihat semua cabang aktif; staff hanya cabangnya sendiri.
     *
     * @return Collection<int, Branch>
     */
    private function visibleBranches(Request $request): Collection
    {
        $user = $request->user();

        if ($user->isAdminPusat()) {
            return Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']);
        }

        return Branch::where('id', $user->employee?->current_branch_id)->get(['id', 'branch_name']);
    }
}
