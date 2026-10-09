<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
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

    // Menu Master Karyawan & Master Cabang (Khusus Admin Pusat)
    Route::middleware(['role:admin_pusat'])->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';
