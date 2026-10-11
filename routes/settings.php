<?php

use App\Http\Controllers\Settings\ApplicationController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // Nama & icon aplikasi (khusus Admin Pusat)
    Route::middleware('role:admin_pusat')->group(function () {
        Route::get('settings/application', [ApplicationController::class, 'edit'])->name('application.edit');
        Route::post('settings/application', [ApplicationController::class, 'update'])->name('application.update');
    });
});
