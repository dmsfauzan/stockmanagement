<?php

namespace App\Livewire\Transactions;

use App\Models\StockTransfer;
use App\Services\Workflow\DocumentWorkflow;
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
        try {
            DocumentWorkflow::requestTransfer($this->transferId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil diajukan.'));
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
        try {
            DocumentWorkflow::approveTransfer($this->transferId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil disetujui.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);
        $this->authorize('reject', $transfer);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectTransfer($this->transferId, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: __('Ditolak.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function dispatchTransfer(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);
        $this->authorize('dispatch', $transfer);
        try {
            DocumentWorkflow::dispatchTransfer($this->transferId);
            $this->dispatch('toast', type: 'success', message: __('Transfer keluar dari lokasi asal.'));
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'Insufficient stock') ? 'Insufficient stock: stok tidak mencukupi di lokasi asal.' : $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function receive(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);
        $this->authorize('receive', $transfer);
        try {
            DocumentWorkflow::receiveTransfer($this->transferId);
            $this->dispatch('toast', type: 'success', message: __('Barang diterima di lokasi tujuan.'));
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'Insufficient stock') ? 'Insufficient stock' : $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function complete(): void
    {
        $transfer = StockTransfer::findOrFail($this->transferId);
        $this->authorize('complete', $transfer);
        try {
            DocumentWorkflow::completeTransfer($this->transferId);
            $this->dispatch('toast', type: 'success', message: __('Transfer selesai.'));
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
