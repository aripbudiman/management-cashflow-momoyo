<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\CashflowReportService;
use App\Services\CashflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashflowReportController extends Controller
{
    public function __construct(private readonly CashflowReportService $service) {}

    public function index(Request $request): Response
    {
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']);
        $mode = $request->input('mode') === 'monthly' ? 'monthly' : 'daily';
        $monthParam = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m-d', $monthParam.'-01')->startOfDay();
        $branchId = $branches->contains('id', (int) $request->input('branch_id')) ? (int) $request->input('branch_id') : 0; // 0 = semua cabang

        return Inertia::render('cashflow-reports/Index', [
            'mode' => $mode,
            'month' => $monthParam,
            'monthLabel' => $month->translatedFormat('F Y'),
            'branchId' => $branchId,
            'branches' => $branches,
            'wallets' => CashflowService::WALLETS,
            'report' => $mode === 'monthly' ? $this->service->monthly($month) : $this->service->daily($branchId, $month),
        ]);
    }
}
