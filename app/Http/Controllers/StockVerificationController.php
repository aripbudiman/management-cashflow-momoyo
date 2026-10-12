<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideStockReportRequest;
use App\Models\Branch;
use App\Models\StockReport;
use App\Repositories\StockRepository;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockVerificationController extends Controller
{
    public function __construct(
        private readonly StockService $service,
        private readonly StockRepository $stocks,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('stock-verifications/Index', [
            'reports' => $this->stocks->paginateReports($request->only(['branch_id', 'status', 'month'])),
            'branches' => Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']),
            'filters' => $request->only(['branch_id', 'status', 'month']),
        ]);
    }

    public function verify(DecideStockReportRequest $request, StockReport $report): RedirectResponse
    {
        $this->service->verify($report, $request->user());

        return back()->with('success', 'Laporan stok diverifikasi.');
    }

    public function revise(DecideStockReportRequest $request, StockReport $report): RedirectResponse
    {
        $this->service->requestRevision($report, $request->user(), $request->validated('revision_notes'));

        return back()->with('success', 'Permintaan revisi dikirim ke cabang.');
    }
}
