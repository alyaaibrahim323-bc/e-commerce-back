<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileapiController;

///////////////////////////////////////////////////////
//  Auth Routes
///////////////////////////////////////////////////////
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::delete('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Reset Password
    Route::post('/reset-password', [AuthController::class, 'sendResetLink']);
    Route::post('/update-password', [AuthController::class, 'resetPassword']);
});

//////////////////////////////////////////////////////
//  Product Routes (public & admin)
///////////////////////////////////////////////////////
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{product}', [ProductController::class, 'update']);
Route::delete('/products/{product}', [ProductController::class, 'destroy']);
Route::get('categories/{category:slug}/products', [ProductController::class, 'productsByCategory']);

///////////////////////////////////////////////////////
//  Categories
///////////////////////////////////////////////////////
Route::apiResource('categories', CategoryController::class);

///////////////////////////////////////////////////////
//  Favorites
///////////////////////////////////////////////////////
// للزوار والمستخدمين
Route::post('/favorites/{product}', [FavoriteController::class, 'toggleFavorite']);
Route::get('/favorites', [FavoriteController::class, 'getFavorites']);
Route::get('/favorite', [FavoriteController::class, 'getFavoriteProducts']);

///////////////////////////////////////////////////////
//  Profile (authenticated only)
///////////////////////////////////////////////////////
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileapiController::class, 'show']);
    Route::put('/profile', [ProfileapiController::class, 'update']);
});

///////////////////////////////////////////////////////
//  Cart (for users and guests)
///////////////////////////////////////////////////////
// للمستخدمين
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/{product}', [CartController::class, 'updateCart']);
    Route::put('/cart/{product}/quantity', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/{product}', [CartController::class, 'removeItem']);
});

// للزوار
Route::middleware('guest')->group(function () {
    Route::get('/cart', [CartController::class, 'getCart']);
    Route::post('/cart/{product}', [CartController::class, 'updateCart']);
    Route::put('/cart/{product}/quantity', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/{product}', [CartController::class, 'removeItem']);
});

///////////////////////////////////////////////////////
//  Orders
///////////////////////////////////////////////////////
Route::post('/checkout', [OrderController::class, 'checkout'])->middleware('auth:sanctum');
Route::get('/order/confirmation/{order}', [OrderController::class, 'confirmation'])->name('order.confirmation');
Route::get('/orders/{order}/track', [OrderController::class, 'trackOrder'])->middleware('auth:sanctum');

///////////////////////////////////////////////////////
// Payments
///////////////////////////////////////////////////////
Route::post('/orders/{order}/pay', [PaymentController::class, 'initiatePayment'])->middleware('auth:sanctum');
Route::post('/paymob/webhook', [PaymentController::class, 'handleWebhook'])
    ->name('paymob.webhook')
    ->withoutMiddleware(['csrf', 'auth:sanctum']);

///////////////////////////////////////////////////////
// Admin-only Routes
///////////////////////////////////////////////////////
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
});

///////////////////////////////////////////////////////
//  Authenticated User Info
///////////////////////////////////////////////////////
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
