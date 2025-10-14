<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\RecentViewController;

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout']);
Route::post('/profil', [UserController::class, 'profil']);


Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login']);

    Route::middleware('jwt.auth')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
    });
});


// Produits (publique sauf si tu veux protéger create/edit/delete)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);


Route::middleware('jwt.auth')->group(function () {
    // Infos utilisateur
    Route::get('/user', [UserController::class, 'show']);   // récupérer infos user
    Route::put('/user/update', [UserController::class, 'update']); // mettre à jour infos user
    Route::put('/user/update-address', [UserController::class, 'updateAddress']);
    Route::put('/user/update-phone', [UserController::class, 'updatePhone']);
    Route::post('/user/request-otp', [UserController::class, 'requestOtp']);
    Route::post('/user/verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('/user/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/user/delete', [UserController::class, 'deleteUser']);


    Route::post('/products', [ProductController::class, 'create']);
    Route::put('/products/{id}', [ProductController::class, 'edit']);
    Route::delete('/products/{id}', [ProductController::class, 'delete']);

    // Favoris
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

    // Vues récentes
    Route::get('/recent-views', [RecentViewController::class, 'index']);
    Route::post('/recent-views', [RecentViewController::class, 'store']);

    // Bons de réduction
    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::post('/vouchers', [VoucherController::class, 'store']);
    Route::get('/vouchers/{id}', [VoucherController::class, 'show']);
    Route::delete('/vouchers/{id}', [VoucherController::class, 'destroy']);

    // Commandes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/order/create', [OrderController::class, 'create']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/status/{status}', [OrderController::class, 'filterByStatus']);

    Route::post('/fedapay/webhook', [PaymentController::class, 'webhook']);

    // Avis
    Route::apiResource('reviews', ReviewController::class)->only(['index', 'store']);

    // Messages
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{id}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
});
