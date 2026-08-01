<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Language switch is available to guests too, so the login page can be read.
Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    // Password reset. Laravel's broker handles token issue and expiry.
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Own account. Available to every signed in user regardless of role, and
    // always scoped to the authenticated user inside the controller.
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('suppliers', SupplierController::class);

    // Selling. The till is its own screen rather than a create form, because a
    // basket is built up over many interactions before anything is saved.
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosController::class, 'store'])->name('pos.store');

    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');

    // Declared before sales/{sale} so the literal path wins, and constrained so
    // only the two known layouts can be requested.
    Route::get('sales/{sale}/print/{format?}', [SaleController::class, 'print'])
        ->whereIn('format', ['a5', 'receipt'])
        ->name('sales.print');

    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    // Reversing a sale is a POST of its own so it can be guarded separately
    // from viewing one. Restricted to managers inside the controller.
    Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');

    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');

    // Recycle bin actions. Declared before the resource-style wildcards would
    // otherwise swallow them, and guarded to admins inside the controllers.
    Route::post('products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
    Route::delete('products/{product}/force', [ProductController::class, 'forceDelete'])->name('products.force-delete');
    Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore');

    // Purchasing. Status transitions are POSTs of their own rather than a
    // generic update, so each one can be authorised and guarded separately.
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::get('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'storeReceipt'])->name('purchase-orders.receive.store');

    // Stock counts. The sheet is edited on the show page, so there is no edit
    // screen; update saves the counts and optionally posts them.
    Route::resource('stock-takes', StockTakeController::class)->except('edit');
    Route::post('stock-takes/{stock_take}/cancel', [StockTakeController::class, 'cancel'])->name('stock-takes.cancel');

    Route::get('movements', [StockMovementController::class, 'index'])->name('movements.index');
    Route::get('movements/create', [StockMovementController::class, 'create'])->name('movements.create');
    Route::post('movements', [StockMovementController::class, 'store'])->name('movements.store');

    Route::get('reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
    Route::get('reports/valuation', [ReportController::class, 'valuation'])->name('reports.valuation');

    // Administration. Access is enforced inside the controller.
    Route::resource('users', UserController::class)->except('show');
});
