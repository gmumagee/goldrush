<?php

use App\Http\Controllers\AccountSelectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineBinController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TransactionController;
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

        Route::resource('warehouses', WarehouseController::class);
        Route::resource('vendors', VendorController::class);
        Route::resource('products', ProductController::class);
        Route::resource('routes', VendingRouteController::class);
        Route::resource('locations', LocationController::class);
        Route::resource('machines', MachineController::class);
        Route::resource('bins', BinController::class);
        Route::resource('services', ServiceController::class)->except(['show']);
        Route::resource('transactions', TransactionController::class);

        Route::get('/machines/{machine}/bins/create', [MachineBinController::class, 'create'])
            ->name('machines.bins.create');
        Route::post('/machines/{machine}/bins', [MachineBinController::class, 'store'])
            ->name('machines.bins.store');
        Route::get('/machines/{machine}/bins/edit', [MachineBinController::class, 'edit'])
            ->name('machines.bins.edit');
        Route::patch('/machines/{machine}/bins', [MachineBinController::class, 'update'])
            ->name('machines.bins.update');

        Route::get('/services/{service}', [ServiceController::class, 'show'])
            ->name('services.show');
        Route::post('/services/{service}/open', [ServiceController::class, 'open'])
            ->name('services.open');
        Route::post('/services/{service}/close', [ServiceController::class, 'close'])
            ->name('services.close');
        Route::get('/services/{service}/machines/{machine}/count', [ServiceController::class, 'countMachine'])
            ->name('services.machines.count');
        Route::post('/services/{service}/machines/{machine}/count', [ServiceController::class, 'storeCount'])
            ->name('services.machines.count.store');
        Route::get('/services/{service}/machines/{machine}/fill', [ServiceController::class, 'fillMachine'])
            ->name('services.machines.fill');
        Route::post('/services/{service}/machines/{machine}/fill', [ServiceController::class, 'storeFill'])
            ->name('services.machines.fill.store');
    });
});
