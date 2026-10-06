<?php

namespace App\Livewire;

use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notifikasi')]
class NotificationsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $readFilter = '';

    public int $perPage = 15;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedReadFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'readFilter']);
        $this->perPage = 15;
        $this->resetPage();
    }

    public function markAsRead(int $id): void
    {
        $notification = Notification::where('user_id', auth()->id())->find($id);

        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        Notification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function linkFor(Notification $notification): string
    {
        $id = $notification->reference_id;
        $ref = (string) ($notification->reference_type ?? '');

        $route = match ($ref) {
            Item::class, 'item' => 'items.show',
            GoodsReceipt::class => 'goods-receipts.show',
            GoodsIssue::class => 'goods-issues.show',
            PurchaseOrder::class, 'purchase_order' => 'purchase-orders.show',
            StockAdjustment::class => 'stock-adjustments.show',
            StockOpname::class => 'stock-opnames.show',
            StockTransfer::class => 'stock-transfers.show',
            'stock_movement' => 'stock.movements',
            default => null,
        };

        if ($route === null) {
            return route('dashboard');
        }

        try {
            if ($route === 'stock.movements') {
                return route($route);
            }

            if ($id === null) {
                return route('dashboard');
            }

            return route($route, $id);
        } catch (\Throwable $e) {
            return route('dashboard');
        }
    }

    public function badgeClasses(string $type): string
    {
        return match (true) {
            $type === 'stock.out' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800',
            $type === 'stock.low', $type === 'stock.expiring' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
            $type === 'approval.request' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
            $type === 'approval.result', $type === 'opname.completed' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-900/40 dark:text-blue-300 dark:ring-blue-800',
            str_starts_with($type, 'stock.') => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
            str_starts_with($type, 'approval.') => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-900/40 dark:text-blue-300 dark:ring-blue-800',
            default => 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600',
        };
    }

    protected function baseQuery(): Builder
    {
        return Notification::where('user_id', auth()->id())
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('message', 'like', $term);
                });
            })
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->readFilter === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
            ->when($this->readFilter === 'read', fn (Builder $query) => $query->whereNotNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function render()
    {
        $userId = auth()->id();

        return view('livewire.notifications-index', [
            'items' => $this->baseQuery()->paginate($this->perPage),
            'types' => Notification::where('user_id', $userId)->distinct()->pluck('type')->filter()->values(),
            'unreadCount' => Notification::where('user_id', $userId)->whereNull('read_at')->count(),
        ]);
    }
}
