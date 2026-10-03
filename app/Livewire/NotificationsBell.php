<?php

namespace App\Livewire;

use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Notification;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use Livewire\Component;

class NotificationsBell extends Component
{
    public int $unreadCount = 0;

    public bool $open = false;

    public array $items = [];

    public function mount(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $this->unreadCount = Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
    }

    public function openPanel(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->loadItems();
        }
    }

    public function markAsRead(int $id): void
    {
        $notification = Notification::where('user_id', auth()->id())->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        $this->loadItems();
    }

    public function markAllAsRead(): void
    {
        Notification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);

        $this->loadItems();
    }

    public function linkFor(Notification $notification): string
    {
        $id = $notification->reference_id;

        $route = match ($notification->reference_type) {
            GoodsReceipt::class => 'goods-receipts.show',
            GoodsIssue::class => 'goods-issues.show',
            StockAdjustment::class => 'stock-adjustments.show',
            StockOpname::class => 'stock-opnames.show',
            StockTransfer::class => 'stock-transfers.show',
            default => null,
        };

        if ($route === null || $id === null) {
            return '#';
        }

        try {
            return route($route, $id);
        } catch (\Throwable $e) {
            return '#';
        }
    }

    protected function loadItems(): void
    {
        $this->items = Notification::where('user_id', auth()->id())
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'read' => $n->read_at !== null,
                'time' => optional($n->created_at)->diffForHumans(),
                'link' => $this->linkFor($n),
            ])
            ->all();

        $this->refreshCount();
    }

    public function render()
    {
        return view('livewire.notifications-bell');
    }
}
