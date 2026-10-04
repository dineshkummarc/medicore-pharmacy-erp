<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
| Every route checks a permission server-side via the `can:` middleware or a
| policy/Gate call in the controller. Hiding links in the UI is cosmetic only.
*/
Route::middleware(['auth', 'auth.session', 'active'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('can:dashboard.view')->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // Medicines & inventory
    Route::get('medicines/lookup', [MedicineController::class, 'lookup'])->middleware('can:medicines.view')->name('medicines.lookup');
    Route::post('medicines/bulk', [MedicineController::class, 'bulk'])->name('medicines.bulk');
    Route::get('medicines/export', [MedicineController::class, 'export'])->middleware('can:medicines.view')->name('medicines.export');
    Route::post('medicines/{medicine}/restore', [MedicineController::class, 'restore'])
        ->withTrashed()->middleware('can:medicines.delete')->name('medicines.restore');
    Route::get('medicines/{medicine}/barcode', [MedicineController::class, 'barcode'])->middleware('can:medicines.view')->name('medicines.barcode');
    Route::resource('medicines', MedicineController::class);

    Route::middleware('can:inventory.view')->group(function () {
        Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
        Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    });
    Route::middleware('can:inventory.adjust')->group(function () {
        Route::get('medicines/{medicine}/opening-stock', [BatchController::class, 'create'])->name('batches.create');
        Route::post('medicines/{medicine}/opening-stock', [BatchController::class, 'store'])->name('batches.store');
        Route::post('batches/{batch}/adjust', [BatchController::class, 'adjust'])->name('batches.adjust');
        Route::post('batches/{batch}/write-off', [BatchController::class, 'writeOff'])->name('batches.write-off');
    });

    Route::middleware('can:catalog.manage')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit']);
        Route::resource('manufacturers', ManufacturerController::class)->except(['show', 'create', 'edit']);
    });

    // Customers
    Route::get('customers/lookup', [CustomerController::class, 'lookup'])->middleware('can:sales.create')->name('customers.lookup');
    Route::post('customers/quick', [CustomerController::class, 'quickStore'])->middleware('can:customers.manage')->name('customers.quick');
    Route::post('customers/{customer}/payments', [CustomerController::class, 'collect'])
        ->middleware('can:customers.payments')->name('customers.payments');
    Route::resource('customers', CustomerController::class);

    // Suppliers
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'pay'])
        ->middleware('can:suppliers.payments')->name('suppliers.payments');
    Route::resource('suppliers', SupplierController::class);

    // Purchases
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
        ->middleware('can:purchases.cancel')->name('purchases.cancel');
    Route::resource('purchases', PurchaseController::class)->except(['destroy']);

    // POS & invoices
    Route::middleware('can:sales.create')->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('pos/search', [PosController::class, 'search'])->name('pos.search');
        Route::post('pos', [PosController::class, 'store'])->middleware('throttle:60,1')->name('pos.store');
    });
    Route::middleware('can:sales.view')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
        Route::get('sales/{sale}/pdf', [SaleController::class, 'pdf'])->name('sales.pdf');
    });

    // Returns
    Route::get('returns', [SaleReturnController::class, 'index'])->middleware('can:returns.view')->name('returns.index');
    Route::middleware('can:returns.create')->group(function () {
        Route::get('returns/create', [SaleReturnController::class, 'create'])->name('returns.create');
        Route::post('sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('returns.store');
    });
    Route::get('returns/{saleReturn}', [SaleReturnController::class, 'show'])->middleware('can:returns.view')->name('returns.show');

    // Expenses
    Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->middleware('can:expenses.view')->name('expenses.receipt');
    Route::resource('expenses', ExpenseController::class)->except(['show']);
    Route::resource('expense-categories', ExpenseCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy'])->middleware('can:expenses.manage');

    // Reports
    Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('medicines', [ReportController::class, 'medicines'])->name('medicines');
        Route::get('categories', [ReportController::class, 'categories'])->name('categories');
        Route::get('staff', [ReportController::class, 'staff'])->name('staff');
        Route::get('purchases', [ReportController::class, 'purchases'])->name('purchases');
        Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('low-stock', [ReportController::class, 'lowStock'])->name('low-stock');
        Route::get('expiry', [ReportController::class, 'expiry'])->name('expiry');
        Route::get('customers', [ReportController::class, 'customers'])->name('customers');
        Route::get('suppliers', [ReportController::class, 'suppliers'])->name('suppliers');
        Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('payments', [ReportController::class, 'payments'])->name('payments');
        Route::get('profit', [ReportController::class, 'profit'])->middleware('can:reports.financial')->name('profit');
    });

    // Administration
    Route::middleware('can:staff.manage')->group(function () {
        Route::post('staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::put('staff/{staff}/password', [StaffController::class, 'password'])->name('staff.password');
        Route::resource('staff', StaffController::class)->except(['show', 'destroy'])->parameters(['staff' => 'staff']);
    });
    Route::resource('roles', RoleController::class)->except(['show'])->middleware('can:roles.manage');

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::resource('payment-methods', PaymentMethodController::class)->only(['store', 'update', 'destroy']);
    });

    Route::get('activity-log', [ActivityLogController::class, 'index'])->middleware('can:audit.view')->name('activity-log.index');
});
