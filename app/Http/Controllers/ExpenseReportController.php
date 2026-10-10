<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\ExpenseReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseReportController extends Controller
{
    public function __construct(private readonly ExpenseReportService $service) {}

    public function index(Request $request): Response
    {
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_name']);
        $monthParam = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m-d', $monthParam.'-01')->startOfDay();
        $branchId = $branches->contains('id', (int) $request->input('branch_id')) ? (int) $request->input('branch_id') : 0; // 0 = semua cabang

        return Inertia::render('expense-reports/Index', [
            'branches' => $branches,
            'branchId' => $branchId,
            'month' => $monthParam,
            'monthLabel' => $month->translatedFormat('F Y'),
            'report' => $this->service->report($month, $branchId),
        ]);
    }
}
