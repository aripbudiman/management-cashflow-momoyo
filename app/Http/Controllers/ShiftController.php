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
    public function index(Request $request): Response
    {
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_code', 'branch_name']);
        $branchId = (int) $request->input('branch_id', $branches->first()?->id);

        return Inertia::render('shifts/Index', [
            'branches' => $branches,
            'branchId' => $branchId,
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

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return back()->with('success', 'Shift berhasil dihapus. Riwayat jadwal tetap tersimpan.');
    }
}
