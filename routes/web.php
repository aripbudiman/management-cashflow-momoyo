<?php

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
    $emailMap = [
        'admin_pusat' => 'admin@momoyo.id',
        'staff_cabang' => 'kasir.tebet@momoyo.id',
        'owner' => 'owner@momoyo.id',
        'superadmin' => 'superadmin@momoyo.id',
    ];

    $email = $emailMap[$role] ?? 'admin@momoyo.id';
    $user = User::where('email', $email)->first();

    if ($user) {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    return back()->withErrors(['email' => 'Akun tidak ditemukan di database.']);
})->name('dev.quick-login');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Menu Master Karyawan & Master Cabang (Khusus Admin Pusat)
    Route::middleware(['role:admin_pusat'])->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';
