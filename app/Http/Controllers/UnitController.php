<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('units/Index', [
            'units' => Unit::withCount('itemUnits')->orderBy('name')->get(),
        ]);
    }

    public function store(SaveUnitRequest $request): RedirectResponse
    {
        Unit::create($request->validated());

        return back()->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function update(SaveUnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return back()->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->itemUnits()->exists()) {
            return back()->withErrors(['unit' => 'Satuan masih dipakai barang, tidak bisa dihapus.']);
        }

        $unit->delete();

        return back()->with('success', 'Satuan berhasil dihapus.');
    }
}
