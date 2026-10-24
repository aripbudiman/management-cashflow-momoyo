<?php

use App\Http\Controllers\Settings\ApplicationController;
use App\Http\Controllers\Settings\PlacementTemplateController;
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

        // Template Surat Penempatan Kerja (editor + variabel)
        Route::get('settings/placement-template', [PlacementTemplateController::class, 'edit'])->name('placement-template.edit');
        Route::put('settings/placement-template', [PlacementTemplateController::class, 'update'])->name('placement-template.update');
        Route::delete('settings/placement-template', [PlacementTemplateController::class, 'reset'])->name('placement-template.reset');
        Route::post('settings/placement-template/preview', [PlacementTemplateController::class, 'preview'])->name('placement-template.preview');
    });
});
