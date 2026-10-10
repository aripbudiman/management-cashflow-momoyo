<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRecapController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashflowReportController;
use App\Http\Controllers\CashflowVerificationController;
use App\Http\Controllers\DailyCashflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

// Quick Login switcher untuk pengujian role RBAC (Admin Pusat vs Staff Cabang)
Route::post('/dev-quick-login', function (Request $request) {
    $role = $request->input('role');
    $user = User::whereHas('role', function ($q) use ($role) {
        $q->where('name', $role);
    })->first();

    if (! $user) {
        $emailMap = [
            'admin_pusat' => 'admin@momoyo.id',
            'staff_cabang' => 'aripbudiman@gmail.com',
            'owner' => 'owner@momoyo.id',
            'superadmin' => 'superadmin@momoyo.id',
        ];
        $email = $emailMap[$role] ?? 'admin@momoyo.id';
        $user = User::where('email', $email)->first();
    }

    if ($user) {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    return back()->withErrors(['email' => 'Akun tidak ditemukan di database.']);
})->name('dev.quick-login');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Modul Absensi (Face ID & Clock In / Clock Out GPS)
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/register-face', [AttendanceController::class, 'registerFace'])->name('attendance.register-face');
    Route::post('/attendance/reset-face', [AttendanceController::class, 'resetFace'])->name('attendance.reset-face');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');

    // Input Cashflow Harian (Staff Cabang)
    Route::middleware(['role:staff_cabang'])->group(function () {
        Route::get('/cashflows', [DailyCashflowController::class, 'index'])->name('cashflows.index');
        Route::post('/cashflows', [DailyCashflowController::class, 'store'])->name('cashflows.store');
    });

    // Pengajuan Izin, Sakit, Cuti (karyawan mengajukan, admin pusat memutuskan)
    Route::get('/leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::post('/leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
    Route::delete('/leaves/{leave}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');
    Route::middleware(['role:admin_pusat'])->group(function () {
        Route::post('/leaves/{leave}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
        Route::post('/leaves/{leave}/reject', [LeaveRequestController::class, 'reject'])->name('leaves.reject');
    });

    // Menu Master Karyawan & Master Cabang (Khusus Admin Pusat)
    Route::middleware(['role:admin_pusat'])->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);

        // Verifikasi setoran & laporan cashflow
        Route::get('/cashflow-verifications', [CashflowVerificationController::class, 'index'])->name('cashflow-verifications.index');
        Route::post('/cashflow-verifications/{cashflow}/verify', [CashflowVerificationController::class, 'verify'])->name('cashflow-verifications.verify');
        Route::post('/cashflow-verifications/{cashflow}/revise', [CashflowVerificationController::class, 'revise'])->name('cashflow-verifications.revise');
        Route::get('/expense-reports', [ExpenseReportController::class, 'index'])->name('expense-reports.index');
        Route::get('/cashflow-reports', [CashflowReportController::class, 'index'])->name('cashflow-reports.index');

        // Shift, jadwal & hari libur karyawan, rekap kehadiran
        Route::resource('shifts', ShiftController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('/attendance-recap', [AttendanceRecapController::class, 'index'])->name('attendance-recap.index');
    });
});

require __DIR__.'/settings.php';
