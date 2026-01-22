<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerController;
use App\Http\Controllers\Api\CustomFieldConfigController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ProcurementItemController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check endpoint (public)
Route::get('/health', function () {
    try {
        // Check database connection
        \DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'environment' => config('app.env'),
        'database' => $dbStatus,
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/password', [AuthController::class, 'changePassword']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });

    // Master Data - Departments
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    });

    // Master Data - Buyers
    Route::get('/buyers', [BuyerController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/buyers', [BuyerController::class, 'store']);
        Route::put('/buyers/{buyer}', [BuyerController::class, 'update']);
        Route::delete('/buyers/{buyer}', [BuyerController::class, 'destroy']);
    });

    // Master Data - Statuses
    Route::get('/statuses', [StatusController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/statuses', [StatusController::class, 'store']);
        Route::put('/statuses/{status}', [StatusController::class, 'update']);
        Route::delete('/statuses/{status}', [StatusController::class, 'destroy']);
    });

    // Custom Field Configs
    Route::get('/custom-field-configs/active', [CustomFieldConfigController::class, 'getActive']);
    Route::middleware('role:admin')->group(function () {
        Route::get('/custom-field-configs', [CustomFieldConfigController::class, 'index']);
        Route::put('/custom-field-configs', [CustomFieldConfigController::class, 'update']);
    });

    // Procurement Items
    Route::get('/procurement-items', [ProcurementItemController::class, 'index']);
    Route::get('/procurement-items/export', [ProcurementItemController::class, 'export']);
    Route::get('/procurement-items/user-requesters', [ProcurementItemController::class, 'getUserRequesters']);
    Route::get('/procurement-items/{procurementItem}', [ProcurementItemController::class, 'show']);
    Route::post('/procurement-items', [ProcurementItemController::class, 'store']);
    Route::put('/procurement-items/{procurementItem}', [ProcurementItemController::class, 'update']);
    Route::patch('/procurement-items/{procurementItem}/status', [ProcurementItemController::class, 'updateStatus']);
    Route::patch('/procurement-items/{procurementItem}/buyer', [ProcurementItemController::class, 'updateBuyer']);
    Route::middleware('role:admin,avp')->group(function () {
        Route::delete('/procurement-items/{procurementItem}', [ProcurementItemController::class, 'destroy']);
    });

    // User Management (admin only)
    Route::middleware('role:admin')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    // Import Data (admin only)
    Route::middleware('role:admin')->prefix('import')->group(function () {
        Route::post('/upload', [ImportController::class, 'upload']);
        Route::get('/session/{id}', [ImportController::class, 'getSession']);
        Route::put('/session/{id}/mapping', [ImportController::class, 'updateMapping']);
        Route::get('/session/{id}/preview', [ImportController::class, 'preview']);
        Route::post('/session/{id}/execute', [ImportController::class, 'execute']);
        Route::get('/template', [ImportController::class, 'downloadTemplate']);
    });

    // Import Users (admin only)
    Route::middleware('role:admin')->prefix('import-users')->group(function () {
        Route::post('/upload', [UserImportController::class, 'uploadUsers']);
        Route::get('/session/{id}', [UserImportController::class, 'getSession']);
        Route::put('/session/{id}/mapping', [UserImportController::class, 'updateMapping']);
        Route::get('/session/{id}/preview', [UserImportController::class, 'preview']);
        Route::post('/session/{id}/execute', [UserImportController::class, 'execute']);
        Route::get('/template', [UserImportController::class, 'downloadTemplate']);
    });

    // Activity Logs
    Route::middleware('role:admin,avp,staff')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });
    Route::get('/activity-logs/my', [ActivityLogController::class, 'myLogs']);
    Route::get('/activity-logs/item/{itemId}', [ActivityLogController::class, 'forItem']);

    // Settings (admin only)
    Route::middleware('role:admin')->prefix('settings')->group(function () {
        Route::delete('/purge-data', [SettingsController::class, 'purgeData']);
    });

    // Field Permissions (admin only)
    Route::middleware('role:admin')->prefix('field-permissions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\FieldPermissionController::class, 'index']);
        Route::get('/fields', [\App\Http\Controllers\Api\FieldPermissionController::class, 'fields']);
        Route::put('/{permission}', [\App\Http\Controllers\Api\FieldPermissionController::class, 'update']);
        Route::post('/bulk-update', [\App\Http\Controllers\Api\FieldPermissionController::class, 'bulkUpdate']);
        Route::post('/reset', [\App\Http\Controllers\Api\FieldPermissionController::class, 'reset']);
    });
});
