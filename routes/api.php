<?php

use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Catalog\AdviceTemplateController;
use App\Http\Controllers\Api\V1\Catalog\LabTestController;
use App\Http\Controllers\Api\V1\Catalog\ProcedureController;
use App\Http\Controllers\Api\V1\Clinical\CaseHistoryController;
use App\Http\Controllers\Api\V1\Patient\PatientController;
use App\Http\Controllers\Api\V1\Practice\ChamberController;
use App\Http\Controllers\Api\V1\Practice\DoctorController;
use App\Http\Controllers\Api\V1\Practice\DoctorProfileController;
use App\Http\Controllers\Api\V1\Practice\SpecialtyController;
use App\Http\Controllers\Api\V1\Practice\TemplateController;
use Illuminate\Support\Facades\Route;

// Served under /api (framework prefix) + /v1.
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware(['auth:api', 'active'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::apiResource('users', UserController::class)->except(['destroy']);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('chambers', ChamberController::class)->except(['destroy']);
        Route::apiResource('specialties', SpecialtyController::class)->except(['destroy']);
        Route::get('doctors/me', [DoctorProfileController::class, 'show'])->name('doctors.me.show');
        Route::put('doctors/me', [DoctorProfileController::class, 'update'])->name('doctors.me.update');
        Route::get('doctors', [DoctorController::class, 'index'])->name('doctors.index');
        Route::put('doctors/{user}/chambers', [DoctorController::class, 'assignChambers'])->name('doctors.chambers.update');
        Route::apiResource('prescription-templates', TemplateController::class)
            ->parameters(['prescription-templates' => 'template'])
            ->except(['destroy']);
        Route::middleware('throttle:120,1')->group(function () {
            Route::apiResource('patients', PatientController::class);
            Route::get('patients/{patient}/summary', [PatientController::class, 'summary'])->name('patients.summary');
            Route::get('patients/{patient}/case-histories', [CaseHistoryController::class, 'forPatient'])->name('patients.case-histories');
            Route::apiResource('case-histories', CaseHistoryController::class)->parameters(['case-histories' => 'case'])->except(['destroy']);
            Route::post('case-histories/{case}/finalize', [CaseHistoryController::class, 'finalize'])->name('case-histories.finalize');
            Route::post('case-histories/{case}/cancel', [CaseHistoryController::class, 'cancel'])->name('case-histories.cancel');
        });
        Route::middleware('throttle:120,1')->group(function () {
            Route::apiResource('lab-tests', LabTestController::class);
            Route::apiResource('procedures', ProcedureController::class);
            Route::apiResource('advice-templates', AdviceTemplateController::class);
        });
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });
});
