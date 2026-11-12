<?php

use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Audit\Controllers\AuditLogController;
use App\Modules\Authentication\Controllers\AuthenticationController;
use App\Modules\Department\Controllers\DepartmentController;
use App\Modules\Designation\Controllers\DesignationController;
use App\Modules\DeviceManagement\Controllers\DeviceController;
use App\Modules\Employee\Controllers\EmployeeController;
use App\Modules\Leave\Controllers\LeaveController;
use App\Modules\Leave\Controllers\LeaveTypeController;
use App\Modules\Project\Controllers\InvoiceController;
use App\Modules\Project\Controllers\ProjectController;
use App\Modules\Project\Controllers\QuotationController;
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
        Route::post('/{user}/archive', [UserController::class, 'archive']);
        Route::post('/{user}/unarchive', [UserController::class, 'unarchive']);
    });

    // Roles & Permissions Management
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });
    Route::get('/permissions', [RoleController::class, 'permissions']);

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
        Route::post('/{employee}/avatar', [EmployeeController::class, 'updateAvatar']);
        Route::post('/{employee}/status', [EmployeeController::class, 'updateStatus']);
        Route::get('/{employee}/bank', [EmployeeController::class, 'getBankDetails']);
        Route::put('/{employee}/bank', [EmployeeController::class, 'updateBankDetails']);
    });

    // Leave Management & Approvals
    Route::prefix('leaves')->group(function () {
        Route::get('/', [LeaveController::class, 'index']);
        Route::get('/balances', [LeaveController::class, 'balances']);
        Route::post('/', [LeaveController::class, 'store']);
        Route::post('/{id}/approve', [LeaveController::class, 'approve']);
        Route::post('/{id}/reject', [LeaveController::class, 'reject']);
        Route::post('/{id}/cancel', [LeaveController::class, 'cancel']);
    });

    // Leave Structure & Categories (Super Admin Dynamic Configuration)
    Route::prefix('leave-types')->group(function () {
        Route::get('/', [LeaveTypeController::class, 'index']);
        Route::post('/', [LeaveTypeController::class, 'store']);
        Route::post('/reset-defaults', [LeaveTypeController::class, 'resetDefaults']);
        Route::get('/{leaveType}', [LeaveTypeController::class, 'show']);
        Route::put('/{leaveType}', [LeaveTypeController::class, 'update']);
        Route::delete('/{leaveType}', [LeaveTypeController::class, 'destroy']);
    });

    // Attendance & Idle/Active Time Accounting
    Route::prefix('attendance')->group(function () {
        Route::get('/today', [AttendanceController::class, 'today']);
        Route::post('/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/clock-out', [AttendanceController::class, 'clockOut']);
        Route::post('/break/start', [AttendanceController::class, 'startBreak']);
        Route::post('/break/end', [AttendanceController::class, 'endBreak']);
        Route::post('/telemetry', [AttendanceController::class, 'recordTelemetry']);
        Route::get('/roster', [AttendanceController::class, 'roster']);
        Route::get('/stats', [AttendanceController::class, 'stats']);
        Route::post('/manual-punch', [AttendanceController::class, 'manualPunch']);
        Route::put('/{attendance}', [AttendanceController::class, 'adjustRecord']);
        Route::get('/policy', [AttendanceController::class, 'getPolicy']);
        Route::put('/policy', [AttendanceController::class, 'updatePolicy']);
    });

    // Advanced Projects Management
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);
        Route::post('/{project}/milestones', [ProjectController::class, 'addMilestone']);
        Route::post('/{project}/tasks', [ProjectController::class, 'addTask']);
        Route::put('/tasks/{task}/status', [ProjectController::class, 'updateTaskStatus']);
    });

    // Quotation / Estimate Builder
    Route::prefix('quotations')->group(function () {
        Route::get('/', [QuotationController::class, 'index']);
        Route::post('/', [QuotationController::class, 'store']);
        Route::get('/{quotation}', [QuotationController::class, 'show']);
        Route::put('/{quotation}', [QuotationController::class, 'update']);
        Route::delete('/{quotation}', [QuotationController::class, 'destroy']);
        Route::post('/{quotation}/convert-to-project', [QuotationController::class, 'convertToProject']);
        Route::post('/{quotation}/convert-to-invoice', [QuotationController::class, 'convertToInvoice']);
    });

    // Invoicing & Tax Invoices (GST & Non-GST)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/{invoice}', [InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);
        Route::post('/{invoice}/payment', [InvoiceController::class, 'recordPayment']);
    });

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });
});
