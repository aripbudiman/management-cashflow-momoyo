<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncScheduleRequest;
use App\Models\Branch;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $service) {}

    public function index(Request $request): Response
    {
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_code', 'branch_name']);
        $branchId = (int) $request->input('branch_id', $branches->first()?->id);
        $monthParam = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $month = Carbon::createFromFormat('Y-m-d', $monthParam.'-01')->startOfDay();

        $branch = $branches->firstWhere('id', $branchId);

        return Inertia::render('schedules/Index', [
            'branches' => $branches,
            'branchId' => $branchId,
            'month' => $monthParam,
            'monthLabel' => $month->translatedFormat('F Y'),
            'grid' => $branch ? $this->service->grid(Branch::findOrFail($branchId), $month) : null,
        ]);
    }

    public function store(SyncScheduleRequest $request): RedirectResponse
    {
        $this->service->save(
            Branch::findOrFail($request->integer('branch_id')),
            $request->validated('entries'),
            $request->user()->id,
        );

        return back()->with('success', 'Jadwal shift & hari libur berhasil disimpan.');
    }
}
