<?php

namespace App\Livewire\Transactions;

use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservationService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Transfer Barang')]
class StockTransferShow extends Component
{
    public int $transferId;

    public string $rejectionReason = '';

    public function mount($transfer): void
    {
        $model = $transfer instanceof StockTransfer ? $transfer : StockTransfer::findOrFail($transfer);

        $this->authorize('view', $model);

        $this->transferId = $model->id;
    }

    public function request(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);

        $this->authorize('request', $transfer);

        if (! $transfer->statusEnum()->canTransitionTo(TransferStatus::Requested)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            ReservationService::reserve(StockTransfer::class, $transfer->id, $transfer->items->map(fn ($item) => [
                'item_id' => $item->item_id,
                'warehouse_id' => $transfer->from_warehouse_id,
                'location_id' => $transfer->from_location_id,
                'quantity' => $item->quantity,
            ])->all());

            $transfer->update([
                'status' => TransferStatus::Requested->value,
                'requested_by' => auth()->id(),
                'requested_at' => now(),
            ]);

            AuditLogger::log('REQUEST', 'stock_transfer', $transfer);

            try {
                NotificationService::notifyApprovers('approval.request', 'Approval Transfer', $transfer->number.' menunggu persetujuan', StockTransfer::class, $transfer->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'reserve')
                ? 'Stok tersedia tidak mencukupi untuk direservasi.'
                : $e->getMessage();

            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function approve(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);

        $this->authorize('approve', $transfer);

        if (! $transfer->statusEnum()->canTransitionTo(TransferStatus::Approved)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $transfer->update([
                'status' => TransferStatus::Approved->value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            AuditLogger::log('APPROVE', 'stock_transfer', $transfer);

            try {
                NotificationService::notify($transfer->created_by, 'approval.result', 'Transfer disetujui', $transfer->number.' telah disetujui', StockTransfer::class, $transfer->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);

        $this->authorize('reject', $transfer);

        if (! $transfer->statusEnum()->canTransitionTo(TransferStatus::Rejected)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $transfer->update([
                'status' => TransferStatus::Rejected->value,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            AuditLogger::log('REJECT', 'stock_transfer', $transfer);

            try {
                ReservationService::release(StockTransfer::class, $transfer->id);
            } catch (\Throwable $e) {
            }

            try {
                NotificationService::notify($transfer->created_by, 'approval.result', 'Transfer ditolak', $transfer->number.' ditolak: '.$this->rejectionReason, StockTransfer::class, $transfer->id);
            } catch (\Throwable $e) {
            }

            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: 'Ditolak.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function dispatchTransfer(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);

        $this->authorize('dispatch', $transfer);

        if ($transfer->statusEnum() !== TransferStatus::Approved) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            InventoryService::dispatchStockTransfer($transfer);
            $this->dispatch('toast', type: 'success', message: 'Transfer keluar dari lokasi asal.');
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'Insufficient stock') ? 'Insufficient stock: stok tidak mencukupi di lokasi asal.' : $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function receive(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);

        $this->authorize('receive', $transfer);

        if ($transfer->statusEnum() !== TransferStatus::InTransit) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            InventoryService::receiveStockTransfer($transfer);
            $this->dispatch('toast', type: 'success', message: 'Barang diterima di lokasi tujuan.');
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'Insufficient stock') ? 'Insufficient stock' : $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function complete(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);

        $this->authorize('complete', $transfer);

        if (! $transfer->statusEnum()->canTransitionTo(TransferStatus::Completed)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $transfer->update([
                'status' => TransferStatus::Completed->value,
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ]);

            AuditLogger::log('COMPLETE', 'stock_transfer', $transfer);
            $this->dispatch('toast', type: 'success', message: 'Transfer selesai.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $transfer = StockTransfer::with([
            'fromWarehouse', 'toWarehouse',
            'fromLocation.rack.zone.warehouse', 'toLocation.rack.zone.warehouse',
            'items.item', 'items.unit',
            'creator', 'requester', 'approver', 'shipper', 'receiver', 'completer', 'rejecter',
        ])->findOrFail($this->transferId);

        return view('livewire.transactions.stock-transfer-show', [
            'transfer' => $transfer,
        ]);
    }
}
