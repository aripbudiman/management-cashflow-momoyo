<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\AttendanceRecapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRecapController extends Controller
{
    public function __construct(private readonly AttendanceRecapService $service) {}

    public function index(Request $request): Response
    {
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_code', 'branch_name']);
        $branchId = (int) $request->input('branch_id', $branches->first()?->id);
        $monthParam = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m-d', $monthParam.'-01')->startOfDay();

        return Inertia::render('attendance-recap/Index', [
            'branches' => $branches,
            'branchId' => $branchId,
            'month' => $monthParam,
            'monthLabel' => $month->translatedFormat('F Y'),
            'recap' => $branchId ? $this->service->recap($branchId, $month) : null,
        ]);
    }
}
