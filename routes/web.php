<?php

use App\Http\Controllers\AccountSelectionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AccountUserPasswordController;
use App\Http\Controllers\AccountUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BinController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CalendarReminderController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DataDictionaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachineBinController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationContactController;
use App\Http\Controllers\LocationDocumentController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RouteLocationController;
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

    Route::middleware(['account.selected', 'account.member', 'technician.services'])->group(function () {
        Route::get('/dashboard', DashboardController::class)
            ->name('dashboard');

        Route::get('/account/password', [PasswordController::class, 'edit'])
            ->name('password.edit');
        Route::put('/account/password', [PasswordController::class, 'update'])
            ->name('password.update');

        Route::get('/account/users', [AccountUserController::class, 'index'])
            ->name('account-users.index');
        Route::get('/account/users/create', [AccountUserController::class, 'create'])
            ->name('account-users.create');
        Route::post('/account/users', [AccountUserController::class, 'store'])
            ->name('account-users.store');
        Route::get('/account/users/{accountUser}/password', [AccountUserPasswordController::class, 'edit'])
            ->name('account-users.password.edit');
        Route::put('/account/users/{accountUser}/password', [AccountUserPasswordController::class, 'update'])
            ->name('account-users.password.update');
        Route::get('/account/users/{accountUser}/edit', [AccountUserController::class, 'edit'])
            ->name('account-users.edit');
        Route::put('/account/users/{accountUser}', [AccountUserController::class, 'update'])
            ->name('account-users.update');
        Route::patch('/account/users/{accountUser}/deactivate', [AccountUserController::class, 'deactivate'])
            ->name('account-users.deactivate');
        Route::delete('/account/users/{accountUser}', [AccountUserController::class, 'destroy'])
            ->name('account-users.destroy');

        Route::get('/data-dictionary', [DataDictionaryController::class, 'index'])
            ->name('data-dictionary.index');
        Route::get('/data-dictionary/create', [DataDictionaryController::class, 'create'])
            ->name('data-dictionary.create');
        Route::post('/data-dictionary', [DataDictionaryController::class, 'store'])
            ->name('data-dictionary.store');
        Route::get('/data-dictionary/{dataDictionary}/edit', [DataDictionaryController::class, 'edit'])
            ->name('data-dictionary.edit');
        Route::put('/data-dictionary/{dataDictionary}', [DataDictionaryController::class, 'update'])
            ->name('data-dictionary.update');
        Route::post('/data-dictionary/{dataDictionary}/deactivate', [DataDictionaryController::class, 'deactivate'])
            ->name('data-dictionary.deactivate');
        Route::post('/data-dictionary/{dataDictionary}/activate', [DataDictionaryController::class, 'activate'])
            ->name('data-dictionary.activate');

        Route::get('/audit-log', [AuditLogController::class, 'index'])
            ->name('audit-log.index');

        Route::resource('warehouses', WarehouseController::class);
        Route::resource('vendors', VendorController::class);
        Route::resource('products', ProductController::class);
        Route::resource('contacts', ContactController::class);
        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('calendar-events', CalendarEventController::class);
        Route::post('/calendar-events/{calendarEvent}/complete', [CalendarEventController::class, 'complete'])
            ->name('calendar-events.complete');
        Route::post('/calendar-events/{calendarEvent}/cancel', [CalendarEventController::class, 'cancel'])
            ->name('calendar-events.cancel');
        Route::post('/calendar-reminders/{calendarReminder}/dismiss', [CalendarReminderController::class, 'dismiss'])
            ->name('calendar-reminders.dismiss');
        Route::resource('routes', VendingRouteController::class);
        Route::post('/routes/{route}/locations', [RouteLocationController::class, 'store'])
            ->name('routes.locations.store');
        Route::delete('/routes/{route}/locations/{routeLocation}', [RouteLocationController::class, 'destroy'])
            ->name('routes.locations.destroy');
        Route::post('/routes/{route}/locations/{routeLocation}/move-up', [RouteLocationController::class, 'moveUp'])
            ->name('routes.locations.move-up');
        Route::post('/routes/{route}/locations/{routeLocation}/move-down', [RouteLocationController::class, 'moveDown'])
            ->name('routes.locations.move-down');
        Route::resource('locations', LocationController::class);
        Route::get('/locations/{location}/contacts/create', [LocationContactController::class, 'create'])
            ->name('locations.contacts.create');
        Route::post('/locations/{location}/contacts', [LocationContactController::class, 'store'])
            ->name('locations.contacts.store');
        Route::get('/locations/{location}/contacts/{locationContact}/edit', [LocationContactController::class, 'edit'])
            ->name('locations.contacts.edit');
        Route::put('/locations/{location}/contacts/{locationContact}', [LocationContactController::class, 'update'])
            ->name('locations.contacts.update');
        Route::delete('/locations/{location}/contacts/{locationContact}', [LocationContactController::class, 'destroy'])
            ->name('locations.contacts.destroy');
        Route::get('/locations/{location}/documents/create', [LocationDocumentController::class, 'create'])
            ->name('locations.documents.create');
        Route::post('/locations/{location}/documents', [LocationDocumentController::class, 'store'])
            ->name('locations.documents.store');
        Route::get('/locations/{location}/documents/{document}', [LocationDocumentController::class, 'show'])
            ->name('locations.documents.show');
        Route::get('/locations/{location}/documents/{document}/download', [LocationDocumentController::class, 'download'])
            ->name('locations.documents.download');
        Route::delete('/locations/{location}/documents/{document}', [LocationDocumentController::class, 'destroy'])
            ->name('locations.documents.destroy');
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
        Route::post('/services/{service}/maintenance/open', [ServiceController::class, 'openMaintenance'])
            ->name('services.maintenance.open');
        Route::post('/purchases/{purchase}/void', [PurchaseController::class, 'void'])
            ->name('purchases.void');
        Route::post('/services/{service}/complete', [ServiceController::class, 'complete'])
            ->name('services.complete');
        Route::put('/services/{service}/maintenance/close', [ServiceController::class, 'closeMaintenance'])
            ->name('services.maintenance.close');
        Route::get('/services/{service}/amount-collected/edit', [ServiceController::class, 'editAmountCollected'])
            ->name('services.amount-collected.edit');
        Route::post('/services/{service}/amount-collected', [ServiceController::class, 'updateAmountCollected'])
            ->name('services.amount-collected.update');
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
