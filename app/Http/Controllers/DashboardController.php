<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CompanyBankAccount;
use App\Models\DailyCashflow;
use App\Models\Employee;
use App\Models\SetoranTransfer;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Tampilkan halaman Beranda / Dashboard sesuai dengan role pengguna.
     */
    public function index(Request $request): Response
    {
        $user = $request->user() ?: auth()->user();

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        $user->loadMissing(['role', 'employee.currentBranch']);

        $today = Carbon::today()->toDateString();
        $roleName = $user->role?->name ?? 'staff_cabang';

        // 1. Data Cabang Pengguna (jika Staff Cabang)
        $userBranch = $user->employee?->currentBranch;

        // 2. Data Absensi Hari Ini untuk Staff Cabang
        $userAttendance = null;
        $currentShift = null;
        $branchShifts = collect();
        if ($user->employee) {
            $userAttendance = Attendance::with(['shift'])
                ->where('employee_id', $user->employee->id)
                ->where('date', $today)
                ->first();

            $branchShifts = Shift::where('branch_id', $user->employee->current_branch_id)->get();
            $currentShift = $branchShifts->first();
        }

        // 3. Ringkasan KPI Seluruh Jaringan (Admin Pusat)
        $totalBranches = Branch::where('is_active', true)->count();
        $totalEmployees = Employee::where('status', 'active')->count();

        // Cashflow Hari Ini
        $todayCashflows = DailyCashflow::with(['branch', 'details', 'expenses', 'setoranTransfers'])
            ->where('report_date', $today)
            ->get();

        $totalOmsetToday = $todayCashflows->sum('total_omset');
        $totalExpenseToday = $todayCashflows->sum('total_expense');
        $totalNetCashToday = $todayCashflows->sum('total_net_cash');
        $closedBranchesCount = $todayCashflows->count();

        // Setoran Pending Verifikasi Finance
        $pendingTransfersCount = SetoranTransfer::where('status', 'pending_verification')->count();

        // Absensi Hari Ini Se-Jaringan
        $todayAttendances = Attendance::with(['employee', 'branch', 'shift'])
            ->where('date', $today)
            ->latest()
            ->take(8)
            ->get();

        $attendancesSummary = [
            'total_present' => Attendance::where('date', $today)->whereIn('status', ['hadir_tepat_waktu', 'terlambat'])->count(),
            'on_time' => Attendance::where('date', $today)->where('status', 'hadir_tepat_waktu')->count(),
            'late' => Attendance::where('date', $today)->where('status', 'terlambat')->count(),
        ];

        // Daftar Cabang untuk monitoring (termasuk koordinat GPS & radius)
        $branches = Branch::where('is_active', true)
            ->withCount(['employees as active_employees_count' => function ($query) {
                $query->where('status', 'active');
            }])
            ->get();

        // Bank Akun Resmi Momoyo
        $bankAccounts = CompanyBankAccount::where('is_active', true)->get();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_branches' => $totalBranches,
                'total_employees' => $totalEmployees,
                'closed_branches_count' => $closedBranchesCount,
                'total_omset_today' => (float) $totalOmsetToday,
                'total_expense_today' => (float) $totalExpenseToday,
                'total_net_cash_today' => (float) $totalNetCashToday,
                'pending_transfers_count' => $pendingTransfersCount,
                'attendances_summary' => $attendancesSummary,
            ],
            'userBranch' => $userBranch,
            'userEmployee' => $user->employee,
            'userAttendance' => $userAttendance,
            'currentShift' => $currentShift,
            'branchShifts' => $branchShifts,
            'branches' => $branches,
            'todayCashflows' => $todayCashflows,
            'recentAttendances' => $todayAttendances,
            'bankAccounts' => $bankAccounts,
            'todayDateFormatted' => Carbon::now()->translatedFormat('l, d F Y'),
        ]);
    }
}
