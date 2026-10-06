<?php

namespace App\Providers;

use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\GoodsIssuePolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\ItemPolicy;
use App\Policies\LocationPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\StockOpnamePolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user && str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(GoodsIssue::class, GoodsIssuePolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(StockOpname::class, StockOpnamePolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);

        View::composer('layouts.app', function ($view): void {
            $lowStockCount = 0;
            $outOfStockCount = 0;

            try {
                $row = DB::table('stock_balances')
                    ->join('items', 'items.id', '=', 'stock_balances.item_id')
                    ->whereNull('items.deleted_at')
                    ->selectRaw(
                        'SUM(CASE WHEN stock_balances.quantity_on_hand <= items.minimum_stock THEN 1 ELSE 0 END) as low,'
                        .' SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END) as out'
                    )
                    ->first();

                $lowStockCount = (int) ($row->low ?? 0);
                $outOfStockCount = (int) ($row->out ?? 0);
            } catch (Throwable) {
                // Database not ready yet; render badges at zero.
            }

            $view->with('lowStockCount', $lowStockCount)
                ->with('outOfStockCount', $outOfStockCount);
        });
    }
}
