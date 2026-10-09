<?php

namespace App\Livewire\Transactions;

use App\Models\CustomerReturn;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Retur Penjualan')]
class CustomerReturnIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', CustomerReturn::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', CustomerReturn::class);

        $rows = $this->baseQuery()->orderByDesc('transaction_date')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Date', 'Customer', 'Warehouse', 'Items', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->number,
                    $row->transaction_date?->format('Y-m-d'),
                    $row->customer?->name,
                    $row->warehouse?->name,
                    $row->items->sum('quantity'),
                    $row->status,
                ]);
            }

            fclose($handle);
        }, 'customer-returns-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function baseQuery(): Builder
    {
        return CustomerReturn::query()
            ->with(['customer', 'warehouse'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter));
    }

    public function render()
    {
        return view('livewire.transactions.customer-return-index', [
            'returns' => $this->baseQuery()->orderByDesc('transaction_date')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
