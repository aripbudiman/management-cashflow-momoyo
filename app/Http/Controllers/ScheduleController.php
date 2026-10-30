<?php

namespace App\Http\Controllers;

use App\Http\Requests\CopyScheduleWeekRequest;
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

    /**
     * Jadwal mingguan. Kepala Toko menyusun jadwal cabangnya; Admin Pusat hanya memantau semua cabang.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isKepalaToko = $user->isKepalaToko();

        $branches = Branch::where('is_active', true)
            ->when($isKepalaToko, fn ($q) => $q->where('id', $user->employee?->current_branch_id))
            ->orderBy('branch_name')
            ->get(['id', 'branch_code', 'branch_name']);

        $branchId = $isKepalaToko ? (int) $branches->first()?->id : (int) $request->input('branch_id', $branches->first()?->id);
        $branch = $branches->firstWhere('id', $branchId);

        $weekStart = $this->weekStart((string) $request->input('week'));

        return Inertia::render('schedules/Index', [
            'branches' => $branches,
            'branchId' => $branchId,
            'canEdit' => $isKepalaToko && $branch !== null,
            'weekStart' => $weekStart->toDateString(),
            'weekLabel' => $weekStart->translatedFormat('d M').' – '.$weekStart->copy()->addDays(6)->translatedFormat('d M Y'),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'grid' => $branch ? $this->service->grid(Branch::findOrFail($branchId), $weekStart) : null,
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

    public function copyWeek(CopyScheduleWeekRequest $request): RedirectResponse
    {
        $count = $this->service->copyPreviousWeek(
            Branch::findOrFail($request->integer('branch_id')),
            $request->weekStart(),
            $request->user()->id,
        );

        return back()->with($count ? 'success' : 'warning', $count
            ? "{$count} jadwal disalin dari minggu sebelumnya (hanya sel kosong yang diisi)."
            : 'Tidak ada jadwal yang bisa disalin: minggu sebelumnya kosong atau semua sel sudah terisi.');
    }

    private function weekStart(string $date): Carbon
    {
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? Carbon::parse($date) : Carbon::today();

        return $parsed->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
