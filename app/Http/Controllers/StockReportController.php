<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockReportRequest;
use App\Models\Branch;
use App\Repositories\StockRepository;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    public function __construct(
        private readonly StockService $service,
        private readonly StockRepository $stocks,
    ) {}

    public function index(Request $request): Response
    {
        $branch = $this->resolveBranch($request);
        $isAdmin = $request->user()->isAdminPusat();
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date')) ? $request->input('date') : today()->toDateString();
        $report = $branch ? $this->stocks->findReport($branch->id, $date) : null;

        // Rekap hari itu: laporan yang sudah dikirim memakai snapshot-nya; selain itu dihitung langsung dari transaksi.
        $open = $branch ? $this->service->dateIsOpen($branch->id, $date) : false;
        $moved = $branch ? $this->stocks->dayMovements($branch->id, $date) : collect();
        $snapshot = $report && ! $open ? $report->items->keyBy('item_id') : collect();
        $adjustments = $report && $open ? $report->items->keyBy('item_id') : collect();

        $items = $branch ? $this->stocks->activeItemsWithStock($branch->id)->map(function ($i) use ($moved, $snapshot, $adjustments) {
            $m = $moved->get($i->id);
            $snap = $snapshot->get($i->id);

            return [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'unit_options' => $i->unit_options,
                'default_in_unit_id' => $i->defaultInUnitId(),
                'default_out_unit_id' => $i->defaultOutUnitId(),
                'stock_qty' => $i->stock_qty,
                'moved' => $snap ? true : $m !== null,
                'opening_qty' => $snap ? (float) $snap->opening_qty : null,
                'in_qty' => $snap ? (float) $snap->in_qty : (float) ($m->in_qty ?? 0),
                'out_qty' => $snap ? (float) $snap->out_qty : (float) ($m->out_qty ?? 0),
                'waste_qty' => $snap ? (float) $snap->waste_qty : (float) ($m->waste_qty ?? 0),
                'transfer_qty' => $snap ? (float) $snap->transfer_qty : (float) ($m->transfer_qty ?? 0),
                'expected_qty' => $snap
                    ? (float) $snap->expected_qty
                    : round((float) $i->stock_qty - (float) ($adjustments->get($i->id)?->diff_qty ?? 0), 2),
            ];
        })->sortBy(fn (array $i) => $i['moved'] ? 0 : 1)->values() : [];

        return Inertia::render('stock-reports/Index', [
            'branch' => $branch?->only(['id', 'branch_name', 'branch_code']),
            'date' => $date,
            'today' => today()->toDateString(),
            'open' => $open,
            'isAdmin' => $isAdmin,
            'branches' => $isAdmin ? Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']) : [],
            'items' => $items,
            'transactions' => $branch ? $this->stocks->transactionsForDay($branch->id, $date) : [],
            'report' => $report,
            'history' => $branch ? $this->stocks->historyForBranch($branch->id) : [],
        ]);
    }

    /**
     * Staff memakai cabang penempatannya; admin pusat memilih cabang lewat query `branch_id`.
     */
    private function resolveBranch(Request $request): ?Branch
    {
        $user = $request->user();

        if ($user->isAdminPusat()) {
            return Branch::where('is_active', true)->find($request->integer('branch_id') ?: null);
        }

        return $user->employee?->currentBranch;
    }

    public function store(StoreStockReportRequest $request): RedirectResponse
    {
        $branch = $request->user()->employee->currentBranch;

        $this->service->submitOpname($request->user(), $branch, $request->validated());

        return redirect()->route('stock-reports.index', ['date' => $request->input('report_date')])
            ->with('success', 'Rekap opname harian berhasil dikirim ke Admin Pusat.');
    }
}
