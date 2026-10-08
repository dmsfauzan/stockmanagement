<?php

namespace App\Providers;

use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesOrder;
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
use App\Policies\SalesOrderPolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\StockOpnamePolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use App\Services\Support\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Password::defaults(fn () => app()->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()
            : Password::min(8)->letters()->numbers());

        Event::listen(Login::class, function (Login $event): void {
            AuditLogger::log('LOGIN', 'auth', $event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            AuditLogger::log('LOGOUT', 'auth', $event->user);
        });

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user && str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        Gate::define('viewApiDocs', function (?User $user): bool {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            return $user !== null && $user->hasPermission('settings.manage');
        });

        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
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
                $activeWarehouseId = null;
                try {
                    $activeWarehouseId = session()->get('active_warehouse_id');
                    if ($activeWarehouseId === '' || $activeWarehouseId === 0 || $activeWarehouseId === '0') {
                        $activeWarehouseId = null;
                    }
                } catch (Throwable) {
                }

                $q = DB::table('stock_balances')
                    ->join('items', 'items.id', '=', 'stock_balances.item_id')
                    ->whereNull('items.deleted_at');

                if ($activeWarehouseId !== null) {
                    $q->where('stock_balances.warehouse_id', (int) $activeWarehouseId);
                }

                $row = $q->selectRaw(
                    'SUM(CASE WHEN stock_balances.quantity_on_hand <= items.minimum_stock THEN 1 ELSE 0 END) as low,'
                    .' SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END) as out'
                )->first();

                $lowStockCount = (int) ($row->low ?? 0);
                $outOfStockCount = (int) ($row->out ?? 0);
            } catch (Throwable) {
            }

            $view->with('lowStockCount', $lowStockCount)
                ->with('outOfStockCount', $outOfStockCount);
        });
    }
}
