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
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDocumentController;
use App\Http\Controllers\ProjectPlacementController;
use App\Http\Controllers\ProjectProgressController;
use App\Http\Controllers\ProjectRabController;
use App\Http\Controllers\ProjectTimelineController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffPortalController;
use App\Http\Controllers\StockMonitorController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StockVerificationController;
use App\Http\Controllers\UnitController;
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
    Route::middleware(['role:staff_cabang,kepala_toko'])->group(function () {
        // Portal mobile staff: jadwal & profil
        Route::get('/my-schedule', [StaffPortalController::class, 'schedule'])->name('staff.schedule');

        Route::get('/cashflows', [DailyCashflowController::class, 'index'])->name('cashflows.index');
        Route::post('/cashflows', [DailyCashflowController::class, 'store'])->name('cashflows.store');

        // Input stok harian & kirim transfer antar cabang (Staff Cabang)
        Route::post('/stock-reports', [StockReportController::class, 'store'])->name('stock-reports.store');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
        Route::post('/stock-transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
    });

    // Pantauan stok, kartu stok & daftar transfer (Staff = cabang sendiri, Admin Pusat = semua cabang)
    Route::middleware(['role:staff_cabang,kepala_toko,admin_pusat'])->group(function () {
        // Stok harian: staff untuk cabangnya, admin pusat memilih cabang lalu input masuk/keluar
        Route::get('/stock-reports', [StockReportController::class, 'index'])->name('stock-reports.index');
        Route::post('/stock-transactions', [StockTransactionController::class, 'store'])->name('stock-transactions.store');
        Route::post('/stock-transactions/{transaction}/cancel', [StockTransactionController::class, 'cancel'])->name('stock-transactions.cancel');
        Route::get('/stock-monitor', [StockMonitorController::class, 'index'])->name('stock-monitor.index');
        Route::get('/stock-monitor/export', [StockMonitorController::class, 'export'])->name('stock-monitor.export');
        Route::get('/stock-monitor/card', [StockMonitorController::class, 'card'])->name('stock-monitor.card');
        Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::post('/stock-transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
    });

    // Monitoring Pekerjaan Lapangan (proyek renovasi/pembangunan outlet)
    Route::middleware(['role:staff_cabang,kepala_toko,admin_pusat'])->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
        Route::get('/projects/{project}/export', [ProjectController::class, 'export'])->whereNumber('project')->name('projects.export');
        Route::post('/work-items/{workItem}/progress', [ProjectProgressController::class, 'update'])->name('work-items.progress');
        Route::get('/placements/{placement}/pdf', [ProjectPlacementController::class, 'pdf'])->name('placements.pdf');
    });
    Route::post('/progress-logs/{progressLog}/verify', [ProjectProgressController::class, 'verify'])
        ->middleware('role:kepala_toko,admin_pusat')
        ->name('progress-logs.verify');
    Route::middleware(['role:admin_pusat'])->group(function () {
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::post('/projects/{project}/cancel', [ProjectController::class, 'cancel'])->name('projects.cancel');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/rab/template', [ProjectRabController::class, 'template'])->name('rab.template');
        Route::post('/projects/{project}/rab/import', [ProjectRabController::class, 'import'])->name('rab.import');
        Route::post('/projects/{project}/rab/send', [ProjectRabController::class, 'send'])->name('rab.send');
        Route::post('/projects/{project}/rab/revise', [ProjectRabController::class, 'revise'])->name('rab.revise');
        Route::post('/projects/{project}/sections', [ProjectRabController::class, 'storeSection'])->name('sections.store');
        Route::put('/sections/{section}', [ProjectRabController::class, 'updateSection'])->name('sections.update');
        Route::delete('/sections/{section}', [ProjectRabController::class, 'destroySection'])->name('sections.destroy');
        Route::post('/projects/{project}/items', [ProjectRabController::class, 'storeItem'])->name('work-items.store');
        Route::put('/work-items/{workItem}', [ProjectRabController::class, 'updateItem'])->name('work-items.update');
        Route::delete('/work-items/{workItem}', [ProjectRabController::class, 'destroyItem'])->name('work-items.destroy');

        Route::post('/projects/{project}/placements', [ProjectPlacementController::class, 'store'])->name('placements.store');
        Route::put('/projects/{project}/placements/{placement}', [ProjectPlacementController::class, 'update'])->name('placements.update');
        Route::post('/placements/{placement}/issue', [ProjectPlacementController::class, 'issue'])->name('placements.issue');
        Route::post('/placements/{placement}/cancel', [ProjectPlacementController::class, 'cancel'])->name('placements.cancel');
        Route::post('/placements/{placement}/complete', [ProjectPlacementController::class, 'complete'])->name('placements.complete');
        Route::delete('/placements/{placement}', [ProjectPlacementController::class, 'destroy'])->name('placements.destroy');

        Route::post('/projects/{project}/timeline/generate', [ProjectTimelineController::class, 'generate'])->name('timeline.generate');
        Route::put('/work-items/{workItem}/schedule', [ProjectTimelineController::class, 'updateItem'])->name('work-items.schedule');

        Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'store'])->name('project-documents.store');
        Route::delete('/project-documents/{document}', [ProjectDocumentController::class, 'destroy'])->name('project-documents.destroy');
    });

    // Profil & logout versi mobile (staff cabang & admin pusat)
    Route::get('/my-profile', [StaffPortalController::class, 'profile'])
        ->middleware('role:staff_cabang,kepala_toko,admin_pusat')
        ->name('staff.profile');

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

        // Master barang (SSOT harga) & verifikasi stok harian
        Route::post('/items/import', [ItemController::class, 'import'])->name('items.import');
        Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('units', UnitController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/stock-verifications', [StockVerificationController::class, 'index'])->name('stock-verifications.index');
        Route::post('/stock-verifications/{report}/verify', [StockVerificationController::class, 'verify'])->name('stock-verifications.verify');
        Route::post('/stock-verifications/{report}/revise', [StockVerificationController::class, 'revise'])->name('stock-verifications.revise');

        // Shift, jadwal & hari libur karyawan, rekap kehadiran
        Route::resource('shifts', ShiftController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('/attendance-recap', [AttendanceRecapController::class, 'index'])->name('attendance-recap.index');
    });
});

require __DIR__.'/settings.php';
