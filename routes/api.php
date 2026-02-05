<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\IAMController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\InactivePatientController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

// Branch Management
Route::prefix('branches')->group(function () {
    Route::get('/', [BranchController::class, 'index']);
    Route::post('/', [BranchController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/{id}', [BranchController::class, 'show']);
    Route::put('/{id}', [BranchController::class, 'update'])->middleware('auth:sanctum');
    
    // Room Management
    Route::prefix('{branchId}/rooms')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [RoomController::class, 'index']);
        Route::post('/', [RoomController::class, 'store']);
    });
    
    // Room Treatment Assignments
    Route::post('/{branchId}/room-treatment-assignments', [RoomController::class, 'syncAssignments'])->middleware('auth:sanctum');
    
    // Staff Management
    Route::prefix('{branchId}/staff')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [StaffController::class, 'index']);
        Route::post('/', [StaffController::class, 'store']);
    });
});

// Room Management (standalone)
Route::prefix('rooms')->middleware('auth:sanctum')->group(function () {
    Route::put('/{id}', [RoomController::class, 'update']);
    Route::delete('/{id}', [RoomController::class, 'destroy']);
});

// Staff Management (standalone)
Route::prefix('staff')->middleware('auth:sanctum')->group(function () {
    Route::put('/{id}/availability', [StaffController::class, 'updateAvailability']);
    Route::put('/{id}/working-hours', [StaffController::class, 'updateWorkingHours']);
    Route::put('/{id}/treatments', [StaffController::class, 'updateTreatments']);
});

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

// Patient Management
Route::prefix('patients')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PatientController::class, 'index']);
    Route::get('/search', [PatientController::class, 'search']);
    Route::post('/', [PatientController::class, 'store']);
    Route::get('/{id}', [PatientController::class, 'show']);
    Route::put('/{id}', [PatientController::class, 'update']);
});

// Treatment Management
Route::prefix('treatments')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [TreatmentController::class, 'index']);
});

// Booking Management
Route::prefix('bookings')->middleware('auth:sanctum')->group(function () {
    Route::get('/available-staff', [BookingController::class, 'getAvailableStaff']);
    Route::post('/find-available-slot', [BookingController::class, 'findAvailableSlot']);
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'store']);
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::put('/{id}', [BookingController::class, 'update']);
    Route::put('/{id}/cancel', [BookingController::class, 'cancel']);
});

// Inactive Patients Management
Route::prefix('inactive-patients')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [InactivePatientController::class, 'index']);
    Route::post('/sync', [InactivePatientController::class, 'sync']);
    Route::put('/{id}/status', [InactivePatientController::class, 'updateStatus']);
    Route::put('/{id}/action', [InactivePatientController::class, 'updateAction']);
    Route::post('/{id}/send-reminder', [InactivePatientController::class, 'sendReminder']);
});

// Data Viewer (Simple endpoint to view database tables)
Route::prefix('data')->middleware('auth:sanctum')->group(function () {
    Route::get('/{table}', function (string $table) {
        $allowedTables = [
            'branches',
            'branch_rooms',
            'patients',
            'staff',
            'treatments',
            'therapy_sessions',
            'room_treatment_assignments',
            'staff_treatment_assignments',
            'room_availability_slots',
            'inactive_patients',
        ];

        if (!in_array($table, $allowedTables)) {
            return response()->json([
                'success' => false,
                'message' => 'Table not allowed',
            ], 403);
        }

        try {
            $data = \Illuminate\Support\Facades\DB::table($table)
                ->limit(100) // Limit to 100 rows for performance
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data: ' . $e->getMessage(),
            ], 500);
        }
    });
});
