<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    // POST (not PATCH) so the avatar can be sent as multipart form data.
    Route::post('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('settings/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    Route::post('settings/billing/{subscription}/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('settings/billing/{subscription}/resume', [BillingController::class, 'resume'])->name('billing.resume');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
