<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout']);
Route::get('/dashboard', [UserController::class, 'dashboard']);
Route::post('/create', [ProductController::class, 'create']);
Route::post('/edit/{id}', [ProductController::class, 'edit']);
Route::post('/delete/{id}', [ProductController::class, 'delete']);
Route::get('/show/{id}', [ProductController::class, 'show']);
Route::post('/profil', [UserController::class, 'profil']);
Route::middleware('auth')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});
