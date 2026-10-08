<?php

use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Catalog\AdviceTemplateController;
use App\Http\Controllers\Api\V1\Catalog\LabTestController;
use App\Http\Controllers\Api\V1\Catalog\ProcedureController;
use App\Http\Controllers\Api\V1\Patient\PatientController;
use App\Http\Controllers\Api\V1\Practice\ChamberController;
use App\Http\Controllers\Api\V1\Practice\DoctorProfileController;
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
        Route::get('chamber', [ChamberController::class, 'show'])->name('chamber.show');
        Route::put('chamber', [ChamberController::class, 'update'])->name('chamber.update');
        Route::get('doctors/me', [DoctorProfileController::class, 'show'])->name('doctors.me.show');
        Route::put('doctors/me', [DoctorProfileController::class, 'update'])->name('doctors.me.update');
        Route::apiResource('prescription-templates', TemplateController::class)
            ->parameters(['prescription-templates' => 'template'])
            ->except(['destroy']);
        Route::middleware('throttle:120,1')->group(function () {
            Route::apiResource('patients', PatientController::class);
            Route::get('patients/{patient}/summary', [PatientController::class, 'summary'])->name('patients.summary');
        });
        Route::middleware('throttle:120,1')->group(function () {
            Route::apiResource('lab-tests', LabTestController::class);
            Route::apiResource('procedures', ProcedureController::class);
            Route::apiResource('advice-templates', AdviceTemplateController::class);
        });
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });
});
