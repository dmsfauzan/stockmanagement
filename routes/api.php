<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WriteTransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'active', 'idempotent'])->group(function (): void {
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

    Route::middleware('permission:sales_order.view')->group(function (): void {
        Route::get('/sales-orders', [TransactionController::class, 'salesOrders'])->name('api.sales-orders.index');
        Route::get('/sales-orders/{salesOrder}', [TransactionController::class, 'salesOrder'])->name('api.sales-orders.show');
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
        Route::get('/accounting/journal', [ReportController::class, 'journal'])->name('api.accounting.journal');
        Route::get('/accounting/summary', [ReportController::class, 'accountingSummary'])->name('api.accounting.summary');
    });

    // ---------------------------------------------------------------------
    // WRITE (create + workflow). Permission middleware mirrors the policy.
    // ---------------------------------------------------------------------
    Route::prefix('goods-receipts')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storeReceipt'])->middleware('permission:goods_receipt.create')->name('api.goods-receipts.store');
        Route::post('/{id}/submit', [WriteTransactionController::class, 'submitReceipt'])->middleware('permission:goods_receipt.submit')->name('api.goods-receipts.submit');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approveReceipt'])->middleware('permission:goods_receipt.approve')->name('api.goods-receipts.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectReceipt'])->middleware('permission:goods_receipt.approve')->name('api.goods-receipts.reject');
        Route::post('/{id}/post', [WriteTransactionController::class, 'postReceipt'])->middleware('permission:goods_receipt.post')->name('api.goods-receipts.post');
    });

    Route::prefix('goods-issues')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storeIssue'])->middleware('permission:goods_issue.create')->name('api.goods-issues.store');
        Route::post('/{id}/submit', [WriteTransactionController::class, 'submitIssue'])->middleware('permission:goods_issue.submit')->name('api.goods-issues.submit');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approveIssue'])->middleware('permission:goods_issue.approve')->name('api.goods-issues.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectIssue'])->middleware('permission:goods_issue.approve')->name('api.goods-issues.reject');
        Route::post('/{id}/post', [WriteTransactionController::class, 'postIssue'])->middleware('permission:goods_issue.post')->name('api.goods-issues.post');
    });

    Route::prefix('stock-adjustments')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storeAdjustment'])->middleware('permission:stock.adjustment')->name('api.stock-adjustments.store');
        Route::post('/{id}/submit', [WriteTransactionController::class, 'submitAdjustment'])->middleware('permission:stock.adjustment')->name('api.stock-adjustments.submit');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approveAdjustment'])->middleware('permission:stock.adjustment.approve')->name('api.stock-adjustments.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectAdjustment'])->middleware('permission:stock.adjustment.approve')->name('api.stock-adjustments.reject');
        Route::post('/{id}/post', [WriteTransactionController::class, 'postAdjustment'])->middleware('permission:stock.adjustment.approve')->name('api.stock-adjustments.post');
    });

    Route::prefix('stock-opnames')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storeOpname'])->middleware('permission:stock_opname.create')->name('api.stock-opnames.store');
        Route::post('/{id}/start', [WriteTransactionController::class, 'startOpname'])->middleware('permission:stock_opname.create')->name('api.stock-opnames.start');
        Route::post('/{id}/submit', [WriteTransactionController::class, 'submitOpname'])->middleware('permission:stock_opname.submit')->name('api.stock-opnames.submit');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approveOpname'])->middleware('permission:stock_opname.approve')->name('api.stock-opnames.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectOpname'])->middleware('permission:stock_opname.approve')->name('api.stock-opnames.reject');
    });

    Route::prefix('stock-transfers')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storeTransfer'])->middleware('permission:transfer.create')->name('api.stock-transfers.store');
        Route::post('/{id}/request', [WriteTransactionController::class, 'requestTransfer'])->middleware('permission:transfer.create')->name('api.stock-transfers.request');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approveTransfer'])->middleware('permission:transfer.approve')->name('api.stock-transfers.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectTransfer'])->middleware('permission:transfer.approve')->name('api.stock-transfers.reject');
        Route::post('/{id}/dispatch', [WriteTransactionController::class, 'dispatchTransfer'])->middleware('permission:transfer.approve')->name('api.stock-transfers.dispatch');
        Route::post('/{id}/receive', [WriteTransactionController::class, 'receiveTransfer'])->middleware('permission:transfer.receive')->name('api.stock-transfers.receive');
        Route::post('/{id}/complete', [WriteTransactionController::class, 'completeTransfer'])->middleware('permission:transfer.receive')->name('api.stock-transfers.complete');
    });

    Route::prefix('purchase-orders')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storePurchaseOrder'])->middleware('permission:purchase_order.create')->name('api.purchase-orders.store');
        Route::post('/{id}/submit', [WriteTransactionController::class, 'submitPurchaseOrder'])->middleware('permission:purchase_order.submit')->name('api.purchase-orders.submit');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approvePurchaseOrder'])->middleware('permission:purchase_order.approve')->name('api.purchase-orders.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectPurchaseOrder'])->middleware('permission:purchase_order.approve')->name('api.purchase-orders.reject');
        Route::post('/{id}/close', [WriteTransactionController::class, 'closePurchaseOrder'])->middleware('permission:purchase_order.approve')->name('api.purchase-orders.close');
    });

    Route::prefix('sales-orders')->group(function (): void {
        Route::post('/', [WriteTransactionController::class, 'storeSalesOrder'])->middleware('permission:sales_order.create')->name('api.sales-orders.store');
        Route::post('/{id}/submit', [WriteTransactionController::class, 'submitSalesOrder'])->middleware('permission:sales_order.submit')->name('api.sales-orders.submit');
        Route::post('/{id}/approve', [WriteTransactionController::class, 'approveSalesOrder'])->middleware('permission:sales_order.approve')->name('api.sales-orders.approve');
        Route::post('/{id}/reject', [WriteTransactionController::class, 'rejectSalesOrder'])->middleware('permission:sales_order.approve')->name('api.sales-orders.reject');
        Route::post('/{id}/close', [WriteTransactionController::class, 'closeSalesOrder'])->middleware('permission:sales_order.approve')->name('api.sales-orders.close');
    });
});
