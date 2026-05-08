<?php

use Illuminate\Support\Facades\Route;

// Auth
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

// Admin
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\RecommendationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\ReportController;

// Customer
use App\Http\Controllers\Customer\ShopController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\ChatController as CustomerChatController;
use App\Http\Controllers\Customer\ProfileController;

/*
|--------------------------------------------------------------------------
| Public / Storefront
|--------------------------------------------------------------------------
*/
Route::get('/', [ShopController::class, 'landing'])->name('home');

Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('index');
    Route::get('/product/{product}', [ShopController::class, 'show'])->name('show');
});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Customer Area (role: pembeli)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:pembeli'])->group(function () {
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/add', [CartController::class, 'add'])->name('add');
        Route::patch('/update/{item}', [CartController::class, 'update'])->name('update');
        Route::delete('/remove/{item}', [CartController::class, 'remove'])->name('remove');
    });

    Route::prefix('checkout')->name('checkout.')->group(function () {
        Route::get('/', [CheckoutController::class, 'index'])->name('index');
        Route::post('/', [CheckoutController::class, 'store'])->name('store');
        Route::get('/success/{order}', [CheckoutController::class, 'success'])->name('success');
        Route::post('/payment-proof/{order}', [CheckoutController::class, 'uploadProof'])->name('proof');
    });

    Route::prefix('my-orders')->name('customer.orders.')->group(function () {
        Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [CustomerOrderController::class, 'show'])->name('show');
    });

    Route::prefix('chat')->name('customer.chat.')->group(function () {
        Route::get('/', [CustomerChatController::class, 'index'])->name('index');
        Route::post('/send', [CustomerChatController::class, 'send'])->name('send');
        Route::get('/poll', [CustomerChatController::class, 'poll'])->name('poll');
    });
});

/*
|--------------------------------------------------------------------------
| Admin Area (role: admin OR karyawan)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,karyawan'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Master Data
    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('materials', MaterialController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('users', UserController::class);

    // DataTables AJAX endpoints
    Route::prefix('datatables')->name('datatables.')->group(function () {
        Route::get('products', [ProductController::class, 'data'])->name('products');
        Route::get('materials', [MaterialController::class, 'data'])->name('materials');
        Route::get('orders', [AdminOrderController::class, 'data'])->name('orders');
        Route::get('productions', [ProductionController::class, 'data'])->name('productions');
        Route::get('purchases', [PurchaseController::class, 'data'])->name('purchases');
    });

    // Operations
    Route::resource('purchases', PurchaseController::class);
    Route::resource('productions', ProductionController::class);
    Route::patch('productions/{production}/stage/{stage}', [ProductionController::class, 'updateStage'])->name('productions.stage');

    // Fuzzy Mamdani recommendation
    Route::prefix('recommendations')->name('recommendations.')->group(function () {
        Route::get('/', [RecommendationController::class, 'index'])->name('index');
        Route::get('/create', [RecommendationController::class, 'create'])->name('create');
        Route::post('/calculate', [RecommendationController::class, 'calculate'])->name('calculate');
        Route::get('/{product}/history', [RecommendationController::class, 'history'])->name('history');
    });

    // Sales
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index'])->name('index');
        Route::get('/{order}', [AdminOrderController::class, 'show'])->name('show');
        Route::patch('/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('status');
        Route::patch('/{order}/verify-payment', [AdminOrderController::class, 'verifyPayment'])->name('verify');
    });

    // Chat
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [AdminChatController::class, 'index'])->name('index');
        Route::get('/{thread}', [AdminChatController::class, 'show'])->name('show');
        Route::post('/{thread}/send', [AdminChatController::class, 'send'])->name('send');
        Route::get('/{thread}/poll', [AdminChatController::class, 'poll'])->name('poll');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/production', [ReportController::class, 'production'])->name('production');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/{type}/export', [ReportController::class, 'export'])->name('export');
    });
});
