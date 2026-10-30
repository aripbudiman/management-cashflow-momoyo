<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    /**
     * Master shift. Kepala Toko mengelola shift cabangnya; Admin Pusat hanya memantau semua cabang.
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

        return Inertia::render('shifts/Index', [
            'branches' => $branches,
            'branchId' => $branchId,
            'canEdit' => $isKepalaToko && $branchId > 0,
            'shifts' => Shift::where('branch_id', $branchId)->orderBy('start_time')->get(),
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        Shift::create($request->validated());

        return back()->with('success', 'Shift berhasil ditambahkan.');
    }

    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return back()->with('success', 'Shift berhasil diperbarui. Jadwal yang sudah dibuat tidak berubah (snapshot).');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        abort_unless($request->user()->managesBranch($shift->branch_id), 403);

        $shift->delete();

        return back()->with('success', 'Shift berhasil dihapus. Riwayat jadwal tetap tersimpan.');
    }
}
