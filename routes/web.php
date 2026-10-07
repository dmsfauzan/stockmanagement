<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\Admin\ApiTokensIndex;
use App\Livewire\Admin\AuditLogIndex;
use App\Livewire\Admin\RoleIndex;
use App\Livewire\Admin\SettingIndex;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Inventory\LowStockIndex;
use App\Livewire\Inventory\StockMovementIndex;
use App\Livewire\Inventory\StockOnHandIndex;
use App\Livewire\MasterData\CategoryIndex;
use App\Livewire\MasterData\CustomerIndex;
use App\Livewire\MasterData\ItemForm;
use App\Livewire\MasterData\ItemIndex;
use App\Livewire\MasterData\ItemShow;
use App\Livewire\MasterData\LocationIndex;
use App\Livewire\MasterData\SupplierIndex;
use App\Livewire\MasterData\SupplierShow;
use App\Livewire\MasterData\UnitIndex;
use App\Livewire\MasterData\WarehouseIndex;
use App\Livewire\NotificationsIndex;
use App\Livewire\Reports\ExpiryReport;
use App\Livewire\Reports\CogsReport;
use App\Livewire\Reports\IncomingReport;
use App\Livewire\Reports\JournalReport;
use App\Livewire\Reports\ValuationReport;
use App\Livewire\Reports\MovementReport;
use App\Livewire\Reports\AdjustmentReport;
use App\Livewire\Reports\OpnameReport;
use App\Livewire\Reports\OutgoingReport;
use App\Livewire\Reports\ReplenishmentReport;
use App\Livewire\Reports\TransferReport;
use App\Livewire\Reports\WarehouseComparisonReport;
use App\Http\Controllers\LabelController;
use App\Livewire\Reports\StockReport;
use App\Livewire\Scanning\ScanIndex;
use App\Livewire\Transactions\GoodsIssueForm;
use App\Livewire\Transactions\GoodsIssueIndex;
use App\Livewire\Transactions\GoodsIssueShow;
use App\Livewire\Transactions\GoodsReceiptForm;
use App\Livewire\Transactions\GoodsReceiptIndex;
use App\Livewire\Transactions\GoodsReceiptShow;
use App\Livewire\Transactions\StockAdjustmentForm;
use App\Livewire\Transactions\StockAdjustmentIndex;
use App\Livewire\Transactions\StockAdjustmentShow;
use App\Livewire\Transactions\StockOpnameCount;
use App\Livewire\Transactions\StockOpnameForm;
use App\Livewire\Transactions\StockOpnameIndex;
use App\Livewire\Transactions\StockOpnameShow;
use App\Livewire\Transactions\PurchaseOrderForm;
use App\Livewire\Transactions\PurchaseOrderIndex;
use App\Livewire\Transactions\PurchaseOrderShow;
use App\Livewire\Transactions\StockTransferForm;
use App\Livewire\Transactions\StockTransferIndex;
use App\Livewire\Transactions\StockTransferShow;
use Illuminate\Support\Facades\Route;

Route::get('/health', [\App\Http\Controllers\HealthController::class, 'public'])->name('health.public');
Route::view('/offline', 'offline')->name('offline');

Route::get('/manifest.webmanifest', function () {
    return response(file_get_contents(public_path('manifest.webmanifest')), 200, [
        'Content-Type' => 'application/manifest+json; charset=UTF-8',
    ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response(file_get_contents(public_path('sw.js')), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('pwa.sw');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'active', 'permission:dashboard.view'])->get('/dashboard', DashboardIndex::class)->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/notifications', \App\Livewire\Profile\NotificationPreferences::class)->name('profile.notifications');
    Route::get('/profile/avatar', \App\Livewire\Profile\AvatarForm::class)->name('profile.avatar');

    Route::middleware('permission:items.view')->get('/items', ItemIndex::class)->name('items.index');
    Route::middleware('permission:items.create')->get('/items/create', ItemForm::class)->name('items.create');
    Route::middleware('permission:items.view')->get('/items/{item}', ItemShow::class)->name('items.show');
    Route::middleware('permission:items.update')->get('/items/{item}/edit', ItemForm::class)->name('items.edit');

    Route::middleware('permission:items.view')->get('/categories', CategoryIndex::class)->name('categories.index');
    Route::middleware('permission:items.view')->get('/units', UnitIndex::class)->name('units.index');
    Route::middleware('permission:items.view')->get('/suppliers', SupplierIndex::class)->name('suppliers.index');
    Route::middleware('permission:items.view')->get('/suppliers/{supplier}', SupplierShow::class)->name('suppliers.show');
    Route::middleware('permission:items.view')->get('/customers', CustomerIndex::class)->name('customers.index');

    Route::get('/notifications', NotificationsIndex::class)->name('notifications.index');

    Route::middleware('permission:warehouse.view')->get('/warehouses', WarehouseIndex::class)->name('warehouses.index');
    Route::middleware('permission:location.view')->get('/locations', LocationIndex::class)->name('locations.index');

    Route::middleware('permission:purchase_order.view')->get('/purchase-orders', PurchaseOrderIndex::class)->name('purchase-orders.index');
    Route::middleware('permission:purchase_order.create')->get('/purchase-orders/create', PurchaseOrderForm::class)->name('purchase-orders.create');
    Route::middleware('permission:purchase_order.view')->get('/purchase-orders/{purchaseOrder}', PurchaseOrderShow::class)->name('purchase-orders.show');
    Route::middleware('permission:purchase_order.update')->get('/purchase-orders/{purchaseOrder}/edit', PurchaseOrderForm::class)->name('purchase-orders.edit');

    Route::middleware('permission:goods_receipt.view')->get('/goods-receipts', GoodsReceiptIndex::class)->name('goods-receipts.index');
    Route::middleware('permission:goods_receipt.create')->get('/goods-receipts/create', GoodsReceiptForm::class)->name('goods-receipts.create');
    Route::middleware('permission:goods_receipt.view')->get('/goods-receipts/{receipt}', GoodsReceiptShow::class)->name('goods-receipts.show');
    Route::middleware('permission:goods_receipt.update')->get('/goods-receipts/{receipt}/edit', GoodsReceiptForm::class)->name('goods-receipts.edit');

    Route::middleware('permission:goods_issue.view')->get('/goods-issues', GoodsIssueIndex::class)->name('goods-issues.index');
    Route::middleware('permission:goods_issue.create')->get('/goods-issues/create', GoodsIssueForm::class)->name('goods-issues.create');
    Route::middleware('permission:goods_issue.view')->get('/goods-issues/{issue}', GoodsIssueShow::class)->name('goods-issues.show');
    Route::middleware('permission:goods_issue.update')->get('/goods-issues/{issue}/edit', GoodsIssueForm::class)->name('goods-issues.edit');

    Route::middleware('permission:stock.adjustment')->get('/stock-adjustments', StockAdjustmentIndex::class)->name('stock-adjustments.index');
    Route::middleware('permission:stock.adjustment')->get('/stock-adjustments/create', StockAdjustmentForm::class)->name('stock-adjustments.create');
    Route::middleware('permission:stock.adjustment')->get('/stock-adjustments/{adjustment}', StockAdjustmentShow::class)->name('stock-adjustments.show');
    Route::middleware('permission:stock.adjustment')->get('/stock-adjustments/{adjustment}/edit', StockAdjustmentForm::class)->name('stock-adjustments.edit');

    Route::middleware('permission:stock_opname.view')->get('/stock-opnames', StockOpnameIndex::class)->name('stock-opnames.index');
    Route::middleware('permission:stock_opname.create')->get('/stock-opnames/create', StockOpnameForm::class)->name('stock-opnames.create');
    Route::middleware('permission:stock_opname.view')->get('/stock-opnames/{opname}', StockOpnameShow::class)->name('stock-opnames.show');
    Route::middleware('permission:stock_opname.create')->get('/stock-opnames/{opname}/edit', StockOpnameForm::class)->name('stock-opnames.edit');
    Route::middleware('permission:stock_opname.view')->get('/stock-opnames/{opname}/count', StockOpnameCount::class)->name('stock-opnames.count');

    Route::middleware('permission:transfer.view')->get('/stock-transfers', StockTransferIndex::class)->name('stock-transfers.index');
    Route::middleware('permission:transfer.create')->get('/stock-transfers/create', StockTransferForm::class)->name('stock-transfers.create');
    Route::middleware('permission:transfer.view')->get('/stock-transfers/{transfer}', StockTransferShow::class)->name('stock-transfers.show');
    Route::middleware('permission:transfer.create')->get('/stock-transfers/{transfer}/edit', StockTransferForm::class)->name('stock-transfers.edit');

    Route::middleware('permission:stock.view')->get('/stock', StockOnHandIndex::class)->name('stock.index');
    Route::middleware('permission:stock.movement')->get('/stock/movements', StockMovementIndex::class)->name('stock.movements');
    Route::middleware('permission:stock.view')->get('/stock/low-stock', LowStockIndex::class)->name('stock.low');

    Route::middleware('permission:reports.view')->get('/reports/stock', StockReport::class)->name('reports.stock');
    Route::middleware('permission:reports.view')->get('/reports/incoming', IncomingReport::class)->name('reports.incoming');
    Route::middleware('permission:reports.view')->get('/reports/outgoing', OutgoingReport::class)->name('reports.outgoing');
    Route::middleware('permission:reports.view')->get('/reports/movement', MovementReport::class)->name('reports.movement');
    Route::middleware('permission:reports.view')->get('/reports/expiry', ExpiryReport::class)->name('reports.expiry');
    Route::middleware('permission:reports.view')->get('/reports/opname', OpnameReport::class)->name('reports.opname');
    Route::middleware('permission:reports.view')->get('/reports/adjustment', AdjustmentReport::class)->name('reports.adjustment');
    Route::middleware('permission:reports.view')->get('/reports/transfer', TransferReport::class)->name('reports.transfer');
    Route::middleware('permission:reports.view')->get('/reports/warehouse-comparison', WarehouseComparisonReport::class)->name('reports.warehouse-comparison');
    Route::middleware('permission:reports.view')->get('/reports/valuation', ValuationReport::class)->name('reports.valuation');
    Route::middleware('permission:reports.view')->get('/reports/cogs', CogsReport::class)->name('reports.cogs');
    Route::middleware('permission:reports.view')->get('/reports/journal', JournalReport::class)->name('reports.journal');
    Route::middleware('permission:reports.view')->get('/reports/replenishment', ReplenishmentReport::class)->name('reports.replenishment');

    Route::middleware('permission:users.manage')->get('/admin/users', UserIndex::class)->name('admin.users');
    Route::middleware('permission:roles.manage')->get('/admin/roles', RoleIndex::class)->name('admin.roles');
    Route::middleware('permission:audit_logs.view')->get('/admin/audit-logs', AuditLogIndex::class)->name('admin.audit-logs');
    Route::middleware('permission:settings.manage')->get('/admin/api-tokens', ApiTokensIndex::class)->name('admin.api-tokens');
    Route::middleware('permission:settings.manage')->get('/admin/settings', SettingIndex::class)->name('admin.settings');
    Route::middleware('permission:settings.manage')->get('/admin/health', [\App\Http\Controllers\HealthController::class, 'check'])->name('admin.health');

    Route::middleware('permission:items.view')->get('/scan', ScanIndex::class)->name('scan');
    Route::get('/labels/bulk', [LabelController::class, 'bulk'])->name('labels.bulk');
    Route::get('/labels/print', [LabelController::class, 'print'])->name('labels.print');
    Route::get('/labels/items/{item}', [LabelController::class, 'item'])->name('labels.item');
    Route::get('/labels/locations/{location}', [LabelController::class, 'location'])->name('labels.location');
});

require __DIR__.'/auth.php';
