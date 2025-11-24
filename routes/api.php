<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MomoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FedapayController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\BanniereController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\CoverImageController;
use App\Http\Controllers\RecentViewController;
use App\Http\Controllers\ClientsStatsController;

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout']);
Route::post('/profil', [UserController::class, 'profil']);
Route::get('/products/search', [SearchController::class, 'search']);
Route::get('/banniere', [BanniereController::class, 'getBanner']);
Route::get('/bannieres', [BanniereController::class, 'index']);
Route::get('/banniere/{id}', [BanniereController::class, 'show']);
Route::post('/contact', [ContactController::class, 'store']);


Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login']);

    Route::middleware('jwt.auth')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::get('/clients/stats', [ClientsStatsController::class, 'index']);
        Route::post('/banniere', [BanniereController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'dashboard']);
        Route::get('/messages', [ContactController::class, 'index']);
        Route::get('/avis', [ReviewController::class, 'show']);
        Route::post('/promos', [PromoController::class, 'store']);
        Route::delete('/promos/{id}', [PromoController::class, 'destroy']);
        Route::patch('/promos/{id}', [PromoController::class, 'update']);
        Route::get('/cover-images', [CoverImageController::class, 'index']);
        Route::post('/cover-images', [CoverImageController::class, 'store']);
        Route::patch('/cover-images/{id}/toggle-active', [CoverImageController::class, 'toggleActive']);
        Route::delete('/cover-images/{id}', [CoverImageController::class, 'destroy']);
    });
});


Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/promos', [PromoController::class, 'index']);


Route::middleware('jwt.auth')->group(function () {

    // Route::post('/momo/create', [MomoController::class, 'createPayment']);
    // Infos utilisateur
    Route::get('/user', [UserController::class, 'show']);   // récupérer infos user
    Route::put('/user/update', [UserController::class, 'update']); // mettre à jour infos user
    Route::put('/user/update-address', [UserController::class, 'updateAddress']);
    Route::put('/user/update-phone', [UserController::class, 'updatePhone']);
    Route::post('/user/request-otp', [UserController::class, 'requestOtp']);
    Route::post('/user/verify-otp', [UserController::class, 'verifyOtp']);
    Route::post('/user/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/user/delete', [UserController::class, 'deleteUser']);
    Route::post('/upload-profile', [ProfileController::class, 'uploadProfile']);



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



    // Avis
    Route::apiResource('reviews', ReviewController::class)->only(['index', 'store']);

    // Messages
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{id}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);


    Route::post('/payments/fedapay', [FedapayController::class, 'createTransaction']);
    Route::get('/payments/status/{transactionId}', [FedapayController::class, 'checkStatus']);
});


// Route::get('/momo/status/{reference}', [MomoController::class, 'getStatus']);
// Route::post('/momo/webhook', [MomoController::class, 'webhook']);
Route::get('/cover-images', [CoverImageController::class, 'index']);

Route::post('/fedapay/webhook', [FedapayController::class, 'webhook'])->name('fedapay.webhook');
