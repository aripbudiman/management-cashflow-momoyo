<?php

use App\Http\Controllers\DashboardController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

// Quick Demo Login switcher untuk pengujian multi-role RBAC instan
Route::post('/dev-quick-login', function (Request $request) {
    $role = $request->input('role');
    $emailMap = [
        'owner' => 'owner@momoyo.id',
        'superadmin' => 'superadmin@momoyo.id',
        'finance_pusat' => 'finance@momoyo.id',
        'store_manager' => 'manager.tebet@momoyo.id',
        'crew_kasir' => 'kasir.tebet@momoyo.id',
    ];

    $email = $emailMap[$role] ?? 'owner@momoyo.id';
    $user = User::where('email', $email)->first();

    if ($user) {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    return back()->withErrors(['email' => 'Akun demo tidak ditemukan di database.']);
})->name('dev.quick-login');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
