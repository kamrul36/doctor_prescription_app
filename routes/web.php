<?php

use App\Http\Controllers\Api\V1\Catalog\AdviceTemplateController as AdviceTemplateLookup;
use App\Http\Controllers\Api\V1\Catalog\LabTestController as LabTestLookup;
use App\Http\Controllers\Api\V1\Catalog\ProcedureController as ProcedureLookup;
use App\Http\Controllers\Api\V1\Patient\PatientController as PatientLookup;
use App\Http\Controllers\Web\Admin\ChamberController;
use App\Http\Controllers\Web\Admin\DoctorController;
use App\Http\Controllers\Web\Admin\RoleController;
use App\Http\Controllers\Web\Admin\SpecialtyController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Catalog\AdviceTemplateController;
use App\Http\Controllers\Web\Catalog\LabTestController;
use App\Http\Controllers\Web\Catalog\ProcedureController;
use App\Http\Controllers\Web\Clinical\PrescriptionController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\Patient\PatientController;
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

    Route::get('/', HomeController::class)->name('home');

    Route::middleware('throttle:120,1')->group(function () {
        Route::resource('patients', PatientController::class);
    });

    // The prescription pad (P1.5). A visit is a case_histories row; drafts are edited, finalized ones are read-only.
    Route::prefix('prescriptions')->name('prescriptions.')->controller(PrescriptionController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{case}', 'show')->name('show');
        Route::get('{case}/edit', 'edit')->name('edit');
        Route::put('{case}', 'update')->name('update');
        Route::post('{case}/finalize', 'finalize')->name('finalize');
        Route::post('{case}/cancel', 'cancel')->name('cancel');
    });

    // Typeahead for the Blade pad: the same API controllers (search, ranking, cache), on the session.
    Route::prefix('lookup')->name('lookup.')->middleware('throttle:240,1')->group(function () {
        Route::get('lab-tests', [LabTestLookup::class, 'index'])->name('lab-tests');
        Route::get('procedures', [ProcedureLookup::class, 'index'])->name('procedures');
        Route::get('advice-templates', [AdviceTemplateLookup::class, 'index'])->name('advice-templates');
        Route::get('patients', [PatientLookup::class, 'index'])->name('patients');
    });

    Route::prefix('catalog')->name('catalog.')->middleware('throttle:120,1')->group(function () {
        Route::resource('lab-tests', LabTestController::class)->except(['show']);
        Route::resource('procedures', ProcedureController::class)->except(['show']);
        Route::resource('advice-templates', AdviceTemplateController::class)->except(['show']);
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('doctor', [DoctorProfileController::class, 'edit'])->name('doctor.edit');
        Route::put('doctor', [DoctorProfileController::class, 'update'])->name('doctor.update');
        Route::resource('templates', TemplateController::class)->except(['show', 'destroy']);
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('roles', RoleController::class)->except(['show']);
        // Practice setup (practice.manage): chambers, the specialty list, and chamber assignment.
        Route::resource('chambers', ChamberController::class)->except(['show', 'destroy']);
        Route::resource('specialties', SpecialtyController::class)->except(['show', 'destroy']);
        Route::get('doctors', [DoctorController::class, 'index'])->name('doctors.index');
        Route::get('doctors/{user}/chambers', [DoctorController::class, 'edit'])->name('doctors.edit');
        Route::put('doctors/{user}/chambers', [DoctorController::class, 'update'])->name('doctors.update');
    });
});
