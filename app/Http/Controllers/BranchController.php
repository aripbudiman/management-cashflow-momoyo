<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Tampilkan daftar master cabang dan konfigurasi koordinat GPS/radius.
     * Hanya dapat diakses oleh Admin Pusat.
     */
    public function index(Request $request): Response
    {
        $query = Branch::withCount(['employees as active_employees_count' => function ($q) {
            $q->where('status', 'active');
        }]);

        // 1. Pencarian
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('branch_name', 'ilike', "%{$search}%")
                    ->orWhere('branch_code', 'ilike', "%{$search}%")
                    ->orWhere('address', 'ilike', "%{$search}%");
            });
        }

        // 2. Filter Status Aktif
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $branches = $query->orderBy('branch_code')->paginate(12)->withQueryString();

        // Ringkasan KPI Cabang
        $stats = [
            'total' => Branch::count(),
            'active' => Branch::where('is_active', true)->count(),
            'inactive' => Branch::where('is_active', false)->count(),
            'total_coverage_employees' => DB::table('employees')->where('status', 'active')->count(),
        ];

        return Inertia::render('branches/Index', [
            'branches' => $branches,
            'filters' => $request->only(['search', 'is_active']),
            'stats' => $stats,
        ]);
    }

    /**
     * Tambah cabang baru beserta titik koordinat GPS dan auto-generate shift standar.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_code' => ['required', 'string', 'max:30', 'unique:branches,branch_code'],
            'branch_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:500'],
            'is_active' => ['boolean'],
        ], [
            'branch_code.unique' => 'Kode cabang ini sudah digunakan oleh cabang lain.',
            'latitude.between' => 'Titik koordinat Latitude tidak valid.',
            'longitude.between' => 'Titik koordinat Longitude tidak valid.',
            'radius_meters.min' => 'Radius toleransi geofencing minimal 10 meter.',
            'radius_meters.max' => 'Radius toleransi geofencing maksimal 500 meter.',
        ]);

        $validated['branch_code'] = strtoupper(trim($validated['branch_code']));
        $validated['branch_name'] = trim($validated['branch_name']);
        $validated['is_active'] = $request->boolean('is_active', true);

        DB::transaction(function () use ($validated) {
            $branch = Branch::create($validated);

            // Buat shift standar (Opening, Closing, Middle) otomatis untuk cabang baru
            $defaultShifts = [
                [
                    'branch_id' => $branch->id,
                    'shift_name' => 'Shift Pagi (Opening)',
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'late_tolerance_minutes' => 15,
                ],
                [
                    'branch_id' => $branch->id,
                    'shift_name' => 'Shift Sore (Closing)',
                    'start_time' => '15:00:00',
                    'end_time' => '23:00:00',
                    'late_tolerance_minutes' => 15,
                ],
                [
                    'branch_id' => $branch->id,
                    'shift_name' => 'Shift Middle',
                    'start_time' => '12:00:00',
                    'end_time' => '20:00:00',
                    'late_tolerance_minutes' => 15,
                ],
            ];

            foreach ($defaultShifts as $shift) {
                Shift::create($shift);
            }
        });

        return redirect()->back()->with('success', "Gerai cabang {$validated['branch_name']} ({$validated['branch_code']}) berhasil ditambahkan dan siap digunakan!");
    }

    /**
     * Perbarui informasi cabang, koordinat GPS, atau radius geofencing.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'branch_code' => ['required', 'string', 'max:30', Rule::unique('branches', 'branch_code')->ignore($branch->id)],
            'branch_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:500'],
            'is_active' => ['boolean'],
        ], [
            'branch_code.unique' => 'Kode cabang ini sudah digunakan oleh cabang lain.',
        ]);

        $validated['branch_code'] = strtoupper(trim($validated['branch_code']));
        $validated['branch_name'] = trim($validated['branch_name']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $branch->update($validated);

        return redirect()->back()->with('success', "Informasi gerai cabang {$branch->branch_name} berhasil diperbarui.");
    }

    /**
     * Nonaktifkan atau hapus cabang.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $hasEmployees = $branch->employees()->exists();
        $hasCashflow = $branch->dailyCashflows()->exists();

        if ($hasEmployees || $hasCashflow) {
            // Jika ada riwayat transaksi/karyawan, nonaktifkan agar integritas data terjaga
            $branch->update(['is_active' => ! $branch->is_active]);
            $statusText = $branch->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

            return redirect()->back()->with('success', "Status gerai {$branch->branch_name} berhasil {$statusText}.");
        }

        $branch->delete();

        return redirect()->back()->with('success', "Gerai cabang {$branch->branch_name} berhasil dihapus.");
    }
}
