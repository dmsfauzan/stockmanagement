<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'active'])->group(function (): void {
    Route::get('/me', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'roles' => auth()->user()->roleSlugs(),
                'permissions' => auth()->user()->permissionSlugs(),
            ],
        ]);
    })->name('api.me');

    Route::middleware('permission:items.view')->group(function (): void {
        Route::get('/items', [ItemController::class, 'index'])->name('api.items.index');
        Route::get('/items/{item}', [ItemController::class, 'show'])->name('api.items.show');
        Route::get('/categories', [MasterDataController::class, 'categories'])->name('api.categories.index');
        Route::get('/units', [MasterDataController::class, 'units'])->name('api.units.index');
        Route::get('/suppliers', [MasterDataController::class, 'suppliers'])->name('api.suppliers.index');
        Route::get('/customers', [MasterDataController::class, 'customers'])->name('api.customers.index');
    });

    Route::middleware('permission:warehouse.view')->group(function (): void {
        Route::get('/warehouses', [MasterDataController::class, 'warehouses'])->name('api.warehouses.index');
    });

    Route::middleware('permission:location.view')->group(function (): void {
        Route::get('/locations', [MasterDataController::class, 'locs'])->name('api.locations.index');
    });

    Route::middleware('permission:stock.view')->group(function (): void {
        Route::get('/stock', [StockController::class, 'index'])->name('api.stock.index');
        Route::get('/stock/movements', [StockController::class, 'movements'])->name('api.stock.movements');
        Route::get('/stock/low', [StockController::class, 'low'])->name('api.stock.low');
    });

    Route::middleware('permission:goods_receipt.view')->group(function (): void {
        Route::get('/goods-receipts', [TransactionController::class, 'receipts'])->name('api.goods-receipts.index');
        Route::get('/goods-receipts/{receipt}', [TransactionController::class, 'receipt'])->name('api.goods-receipts.show');
    });

    Route::middleware('permission:goods_issue.view')->group(function (): void {
        Route::get('/goods-issues', [TransactionController::class, 'issues'])->name('api.goods-issues.index');
        Route::get('/goods-issues/{issue}', [TransactionController::class, 'issue'])->name('api.goods-issues.show');
    });

    Route::middleware('permission:stock.adjustment')->group(function (): void {
        Route::get('/stock-adjustments', [TransactionController::class, 'adjustments'])->name('api.stock-adjustments.index');
        Route::get('/stock-adjustments/{adjustment}', [TransactionController::class, 'adjustment'])->name('api.stock-adjustments.show');
    });

    Route::middleware('permission:stock_opname.view')->group(function (): void {
        Route::get('/stock-opnames', [TransactionController::class, 'opnames'])->name('api.stock-opnames.index');
        Route::get('/stock-opnames/{opname}', [TransactionController::class, 'opname'])->name('api.stock-opnames.show');
    });

    Route::middleware('permission:transfer.view')->group(function (): void {
        Route::get('/stock-transfers', [TransactionController::class, 'transfers'])->name('api.stock-transfers.index');
        Route::get('/stock-transfers/{transfer}', [TransactionController::class, 'transfer'])->name('api.stock-transfers.show');
    });

    Route::middleware('permission:purchase_order.view')->group(function (): void {
        Route::get('/purchase-orders', [TransactionController::class, 'purchaseOrders'])->name('api.purchase-orders.index');
        Route::get('/purchase-orders/{purchaseOrder}', [TransactionController::class, 'purchaseOrder'])->name('api.purchase-orders.show');
    });

    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/reports/stock', [ReportController::class, 'stock'])->name('api.reports.stock');
        Route::get('/reports/incoming', [ReportController::class, 'incoming'])->name('api.reports.incoming');
        Route::get('/reports/outgoing', [ReportController::class, 'outgoing'])->name('api.reports.outgoing');
        Route::get('/reports/movement', [ReportController::class, 'movement'])->name('api.reports.movement');
        Route::get('/reports/expiry', [ReportController::class, 'expiry'])->name('api.reports.expiry');
        Route::get('/reports/opname', [ReportController::class, 'opname'])->name('api.reports.opname');
        Route::get('/reports/adjustment', [ReportController::class, 'adjustment'])->name('api.reports.adjustment');
        Route::get('/reports/transfer', [ReportController::class, 'transfer'])->name('api.reports.transfer');
        Route::get('/reports/warehouse-comparison', [ReportController::class, 'warehouseComparison'])->name('api.reports.warehouse-comparison');
        Route::get('/reports/valuation', [ReportController::class, 'valuation'])->name('api.reports.valuation');
        Route::get('/reports/cogs', [ReportController::class, 'cogs'])->name('api.reports.cogs');
        Route::get('/reports/journal', [ReportController::class, 'journal'])->name('api.reports.journal');
        Route::get('/reports/replenishment', [ReportController::class, 'replenishment'])->name('api.reports.replenishment');
    });
});
