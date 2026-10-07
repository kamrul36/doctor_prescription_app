<?php

use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Settings\ChamberController;
use App\Http\Controllers\Web\Settings\DoctorProfileController;
use App\Http\Controllers\Web\Settings\TemplateController;
use Illuminate\Support\Facades\Route;

// Blade UI (session auth). API equivalents live in routes/api.php and share
// the same Actions, FormRequests and Policies.

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::view('/', 'home')->name('home');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('chamber', [ChamberController::class, 'edit'])->name('chamber.edit');
        Route::put('chamber', [ChamberController::class, 'update'])->name('chamber.update');
        Route::get('doctor', [DoctorProfileController::class, 'edit'])->name('doctor.edit');
        Route::put('doctor', [DoctorProfileController::class, 'update'])->name('doctor.update');
        Route::resource('templates', TemplateController::class)->except(['show', 'destroy']);
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('roles', RoleController::class)->except(['show']);
    });
});
