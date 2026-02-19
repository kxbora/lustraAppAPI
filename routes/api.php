<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;

Route::middleware('throttle:10,1')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json([
        'message' => 'Use POST /api/login with email and password.',
    ], 200);
});
Route::middleware('throttle:10,1')->post('/social-login', [AuthController::class, 'socialLogin']);
Route::middleware('throttle:3,1')->post('/forgot-password/send-otp', [AuthController::class, 'sendResetOtp']);
Route::middleware('throttle:10,1')->post('/forgot-password/verify-otp', [AuthController::class, 'verifyResetOtp']);
Route::middleware('throttle:5,1')->post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithOtp']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);

    Route::prefix('/favorites')->group(function() {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::get('/user/{userId}', [FavoriteController::class, 'index']);
        Route::post('/', [FavoriteController::class, 'store']);
        Route::post('/toggle', [FavoriteController::class, 'toggle']);
        Route::delete('/{id}', [FavoriteController::class, 'destroy']);
    });

    Route::post('/favorite', [FavoriteController::class, 'toggle']);

    Route::prefix('/carts')->group(function() {
        Route::get('/user/{userId}', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'destroy']);
        Route::delete('/user/{userId}/clear', [CartController::class, 'clear']);
    });

    Route::prefix('/orders')->group(function() {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/', [OrderController::class, 'store']);
        Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
    });

    Route::prefix('/payments')->group(function() {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/{id}', [PaymentController::class, 'show']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::put('/{id}', [PaymentController::class, 'update']);
        Route::delete('/{id}', [PaymentController::class, 'destroy']);
    });

    Route::prefix('/notifications')->group(function() {
        Route::get('/user/{userId}', [NotificationController::class, 'index']);
        Route::post('/', [NotificationController::class, 'store']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});

Route::prefix('/products')->group(function() {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });
});

Route::prefix('/categories')->group(function() {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });
});