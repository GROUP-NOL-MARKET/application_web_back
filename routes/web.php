<?php

use App\Imports\ProductsImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/import-products', function () {
    Excel::import(new ProductsImport, storage_path('app/public/Produits.csv'));
    return 'Produits importés avec succès !';
});

Route::get('/run-migrations', function () {
    Artisan::call('migrate', ['--force' => true]);
    return 'Migrations run successfully!';
});
Route::get('/test-env', function () {
    return env('TEST_ENV_VAR');
});

