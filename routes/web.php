<?php

use App\Http\Controllers\AccountSelectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineBinController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VendingRouteController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/accounts/select', [AccountSelectionController::class, 'edit'])->name('accounts.select');
    Route::post('/accounts/select', [AccountSelectionController::class, 'update']);

    Route::middleware(['account.selected', 'account.member'])->group(function () {
        Route::get('/dashboard', DashboardController::class)
            ->name('dashboard');

        Route::get('/machines', [MachineController::class, 'index'])
            ->name('machines.index');
        Route::get('/machines/create', [MachineController::class, 'create'])
            ->name('machines.create');
        Route::post('/machines', [MachineController::class, 'store'])
            ->name('machines.store');
        Route::get('/machines/{machine}', [MachineController::class, 'show'])
            ->name('machines.show');

        Route::get('/machines/{machine}/bins/create', [MachineBinController::class, 'create'])
            ->name('machines.bins.create');
        Route::post('/machines/{machine}/bins', [MachineBinController::class, 'store'])
            ->name('machines.bins.store');

        Route::get('/products', [ProductController::class, 'index'])
            ->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])
            ->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::get('/locations', [LocationController::class, 'index'])
            ->name('locations.index');
        Route::get('/locations/create', [LocationController::class, 'create'])
            ->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])
            ->name('locations.store');

        Route::get('/warehouses', [WarehouseController::class, 'index'])
            ->name('warehouses.index');
        Route::get('/warehouses/create', [WarehouseController::class, 'create'])
            ->name('warehouses.create');
        Route::post('/warehouses', [WarehouseController::class, 'store'])
            ->name('warehouses.store');

        Route::get('/vendors', [VendorController::class, 'index'])
            ->name('vendors.index');
        Route::get('/vendors/create', [VendorController::class, 'create'])
            ->name('vendors.create');
        Route::post('/vendors', [VendorController::class, 'store'])
            ->name('vendors.store');

        Route::get('/routes', [VendingRouteController::class, 'index'])
            ->name('routes.index');
        Route::get('/routes/create', [VendingRouteController::class, 'create'])
            ->name('routes.create');
        Route::post('/routes', [VendingRouteController::class, 'store'])
            ->name('routes.store');
    });
});
