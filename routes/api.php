<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IAMController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

Route::get('/branches', [\App\Http\Controllers\BranchController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::prefix('iam')->middleware('auth:sanctum')->group(function () {
    Route::get('/users', [IAMController::class, 'getUsers']);
    Route::post('/users', [IAMController::class, 'createUser']);
    Route::put('/users/{id}', [IAMController::class, 'updateUser']);
    Route::delete('/users/{id}', [IAMController::class, 'deleteUser']);
    
    Route::get('/page-permissions', [IAMController::class, 'getPagePermissions']);
    Route::get('/role-permissions/{role}', [IAMController::class, 'getRolePermissions']);
    Route::post('/role-permissions', [IAMController::class, 'setRolePermission']);
});
