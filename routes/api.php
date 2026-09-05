<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\BorrowingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ActivityLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check endpoint (no auth required, no versioning)
Route::get('/health', [HealthController::class, 'check']);

// API Route Registrar (DRY: registered for both /v1 and unversioned backward compatibility)
$registerApiRoutes = function () {
    // Public routes with rate limiting
    Route::middleware(['throttle:10,1'])->prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes with rate limiting
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Items
        Route::delete('/items/bulk-delete', [ItemController::class, 'bulkDelete']);
        Route::get('/items/search-suggestions', [ItemController::class, 'searchSuggestions']);
        Route::post('/items/{item}', [ItemController::class, 'update']);
        Route::apiResource('items', ItemController::class);

        // Borrowings
        Route::apiResource('borrowings', BorrowingController::class);
        Route::post('/borrowings/{borrowing}/return', [BorrowingController::class, 'return']);
        Route::match(['post', 'put'], '/borrowings/{borrowing}/approve', [BorrowingController::class, 'approve']);
        Route::match(['post', 'put'], '/borrowings/{borrowing}/reject', [BorrowingController::class, 'reject']);
        Route::post('/borrowings/{borrowing}/extend', [BorrowingController::class, 'extend']);
        Route::get('/borrowings/my/list', [BorrowingController::class, 'myBorrowings']);

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/borrowings', [ReportController::class, 'borrowings']);
            Route::get('/items', [ReportController::class, 'items']);
            Route::get('/overdue', [ReportController::class, 'overdue']);
            Route::get('/monthly', [ReportController::class, 'monthly']);
            Route::get('/export/borrowings/pdf', [ReportController::class, 'exportBorrowingsPdf']);
            Route::get('/export/borrowings/excel', [ReportController::class, 'exportBorrowingsExcel']);
        });

        // Profile
        Route::prefix('profile')->group(function () {
            Route::put('/', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'updatePassword']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
        });

        // Activity Logs (Admin only)
        Route::prefix('activity-logs')->middleware('admin')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index']);
            Route::get('/recent', [ActivityLogController::class, 'recent']);
            Route::get('/{type}/{id}', [ActivityLogController::class, 'getForModel']);
        });

        // Users (Admin only)
        Route::middleware('admin')->group(function () {
            Route::apiResource('users', UserController::class);
        });
    });
};

// Versioned routes (/api/v1/...)
Route::prefix('v1')->group($registerApiRoutes);

// Unversioned routes (/api/...) for frontend and existing client backward compatibility
$registerApiRoutes();

