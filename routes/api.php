<?php

use App\Modules\Audit\Controllers\AuditLogController;
use App\Modules\Authentication\Controllers\AuthenticationController;
use App\Modules\Department\Controllers\DepartmentController;
use App\Modules\Designation\Controllers\DesignationController;
use App\Modules\DeviceManagement\Controllers\DeviceController;
use App\Modules\Employee\Controllers\EmployeeController;
use App\Modules\UserManagement\Controllers\RoleController;
use App\Modules\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes in this file are implicitly prefixed with /api/v1/ via bootstrap/app.php
|
*/

// Public Authentication Endpoints
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthenticationController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthenticationController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthenticationController::class, 'resetPassword'])->middleware('throttle:5,1');
});

// Device Registration / Heartbeat
Route::post('/devices/register', [DeviceController::class, 'register']);

// Authenticated API Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth Session Lifecycle
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthenticationController::class, 'logout']);
        Route::get('/me', [AuthenticationController::class, 'me']);
        Route::post('/change-password', [AuthenticationController::class, 'changePassword']);
    });

    // Workstation & Device Management
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceController::class, 'index']);
        Route::get('/{device}', [DeviceController::class, 'show']);
        Route::post('/{device}/revoke', [DeviceController::class, 'revoke']);
        Route::post('/{device}/activate', [DeviceController::class, 'activate']);
    });

    // System User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/roles', [UserController::class, 'assignRoles']);
        Route::post('/{user}/deactivate', [UserController::class, 'deactivate']);
    });

    // Roles & Permissions Inspection
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{role}', [RoleController::class, 'show']);
    });

    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Designations
    Route::apiResource('designations', DesignationController::class);

    // Employee Profiles & Self-Service
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/{employee}', [EmployeeController::class, 'show']);
        Route::put('/{employee}', [EmployeeController::class, 'update']);
        Route::post('/{employee}/status', [EmployeeController::class, 'updateStatus']);
        Route::get('/{employee}/bank', [EmployeeController::class, 'getBankDetails']);
        Route::put('/{employee}/bank', [EmployeeController::class, 'updateBankDetails']);
    });

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });
});
