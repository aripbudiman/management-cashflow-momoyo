<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockReportRequest;
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
        $branch = $request->user()->employee?->currentBranch;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date')) ? $request->input('date') : today()->toDateString();

        return Inertia::render('stock-reports/Index', [
            'branch' => $branch?->only(['id', 'branch_name', 'branch_code']),
            'date' => $date,
            'today' => today()->toDateString(),
            'items' => $branch ? $this->stocks->activeItemsWithStock($branch->id)->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'unit_options' => $i->unit_options,
                'default_in_unit_id' => $i->defaultInUnitId(),
                'default_out_unit_id' => $i->defaultOutUnitId(),
                'stock_qty' => $i->stock_qty,
            ])->values() : [],
            'report' => $branch ? $this->stocks->findReport($branch->id, $date) : null,
            'history' => $branch ? $this->stocks->historyForBranch($branch->id) : [],
        ]);
    }

    public function store(StoreStockReportRequest $request): RedirectResponse
    {
        $branch = $request->user()->employee->currentBranch;

        $this->service->saveReport($request->user(), $branch, $request->validated());

        return redirect()->route('stock-reports.index', ['date' => $request->input('report_date')])
            ->with('success', 'Laporan stok harian berhasil dikirim ke Admin Pusat.');
    }
}
