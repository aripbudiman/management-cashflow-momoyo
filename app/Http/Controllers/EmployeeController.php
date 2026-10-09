<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePlacement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Tampilkan daftar master karyawan dan akun login.
     * Hanya dapat diakses oleh Admin Pusat.
     */
    public function index(Request $request): Response
    {
        $query = Employee::with(['user.role', 'currentBranch']);

        // 1. Pencarian (Nama, NIK, Email, No HP)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('nik', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'ilike', "%{$search}%")
                            ->orWhere('username', 'ilike', "%{$search}%");
                    });
            });
        }

        // 2. Filter Cabang
        if ($branchId = $request->input('branch_id')) {
            $query->where('current_branch_id', $branchId);
        }

        // 3. Filter Status (active, inactive, probation)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // 4. Filter Role
        if ($roleId = $request->input('role_id')) {
            $query->whereHas('user', function ($uq) use ($roleId) {
                $uq->where('role_id', $roleId);
            });
        }

        $employees = $query->latest('id')->paginate(12)->withQueryString();

        // Data pendukung form & filter
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get(['id', 'branch_code', 'branch_name']);
        $roles = Role::orderBy('id')->get(['id', 'name', 'display_name']);

        // Ringkasan KPI
        $stats = [
            'total' => Employee::count(),
            'active' => Employee::where('status', 'active')->count(),
            'probation' => Employee::where('status', 'probation')->count(),
            'inactive' => Employee::where('status', 'inactive')->count(),
            'has_login' => Employee::whereNotNull('user_id')->count(),
        ];

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'branches' => $branches,
            'roles' => $roles,
            'filters' => $request->only(['search', 'branch_id', 'status', 'role_id']),
            'stats' => $stats,
        ]);
    }

    /**
     * Simpan data karyawan baru beserta pembuatan akun login pengguna.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:30', 'unique:employees,nik'],
            'full_name' => ['required', 'string', 'max:150'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'current_branch_id' => ['required', 'exists:branches,id'],
            'status' => ['required', 'in:active,inactive,probation'],

            // Akun Login Pengguna
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,id'],
        ], [
            'nik.unique' => 'NIK ini sudah terdaftar untuk karyawan lain.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'username.unique' => 'Username ini sudah digunakan.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        DB::transaction(function () use ($validated, $request) {
            // 1. Buat User Login
            $username = $validated['username'] ?: strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['full_name'])).rand(10, 99);

            $user = User::create([
                'name' => $validated['full_name'],
                'username' => $username,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => $validated['role_id'],
                'is_active' => $validated['status'] === 'active',
                'email_verified_at' => now(),
            ]);

            // 2. Buat Record Karyawan
            $employee = Employee::create([
                'user_id' => $user->id,
                'nik' => strtoupper(trim($validated['nik'])),
                'full_name' => trim($validated['full_name']),
                'phone_number' => $validated['phone_number'],
                'current_branch_id' => $validated['current_branch_id'],
                'status' => $validated['status'],
            ]);

            // 3. Catat Surat Tugas Penempatan Perdana
            $suratTugasNumber = 'ST/MMY/'.date('Ym').'/'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);
            EmployeePlacement::create([
                'employee_id' => $employee->id,
                'from_branch_id' => null,
                'to_branch_id' => $employee->current_branch_id,
                'effective_date' => now()->toDateString(),
                'surat_tugas_number' => $suratTugasNumber,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->back()->with('success', "Karyawan {$validated['full_name']} dan akun login berhasil didaftarkan!");
    }

    /**
     * Perbarui data karyawan dan akun login.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:30', Rule::unique('employees', 'nik')->ignore($employee->id)],
            'full_name' => ['required', 'string', 'max:150'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'current_branch_id' => ['required', 'exists:branches,id'],
            'status' => ['required', 'in:active,inactive,probation'],

            // Akun Login Pengguna
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,id'],
        ], [
            'nik.unique' => 'NIK ini sudah digunakan oleh karyawan lain.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
        ]);

        DB::transaction(function () use ($validated, $employee, $user, $request) {
            $oldBranchId = $employee->current_branch_id;

            // 1. Update atau Buat User
            $userData = [
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'role_id' => $validated['role_id'],
                'is_active' => $validated['status'] === 'active',
            ];

            if (! empty($validated['username'])) {
                $userData['username'] = $validated['username'];
            }

            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            if ($user) {
                $user->update($userData);
            } else {
                $userData['password'] = Hash::make($validated['password'] ?: 'password');
                $userData['email_verified_at'] = now();
                $newUser = User::create($userData);
                $employee->user_id = $newUser->id;
            }

            // 2. Update Employee
            $employee->update([
                'nik' => strtoupper(trim($validated['nik'])),
                'full_name' => trim($validated['full_name']),
                'phone_number' => $validated['phone_number'],
                'current_branch_id' => $validated['current_branch_id'],
                'status' => $validated['status'],
                'user_id' => $employee->user_id,
            ]);

            // 3. Jika terjadi rotasi / perpindahan cabang, catat surat tugas mutasi
            if ($oldBranchId != $validated['current_branch_id']) {
                $suratTugasNumber = 'ST/MUT/'.date('Ym').'/'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);
                EmployeePlacement::create([
                    'employee_id' => $employee->id,
                    'from_branch_id' => $oldBranchId,
                    'to_branch_id' => $validated['current_branch_id'],
                    'effective_date' => now()->toDateString(),
                    'surat_tugas_number' => $suratTugasNumber,
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->back()->with('success', "Data karyawan {$validated['full_name']} berhasil diperbarui!");
    }

    /**
     * Nonaktifkan atau hapus data karyawan.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        DB::transaction(function () use ($employee) {
            // Nonaktifkan user login
            if ($employee->user) {
                $employee->user->update(['is_active' => false]);
            }

            // Set status karyawan menjadi inactive
            $employee->update(['status' => 'inactive']);
        });

        return redirect()->back()->with('success', "Karyawan {$employee->full_name} dan akses loginnya berhasil dinonaktifkan.");
    }
}
