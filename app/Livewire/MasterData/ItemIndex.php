<?php

namespace App\Livewire\MasterData;

use App\Imports\ItemsImport;
use App\Models\Category;
use App\Models\Item;
use App\Services\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Barang')]
class ItemIndex extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $statusFilter = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public $importFile = null;

    public bool $showImportModal = false;

    /** @var array<int, string> */
    public array $importErrors = [];

    public int $importedCount = 0;

    public int $updatedCount = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', Item::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
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

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['sku', 'name', 'created_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function deleteItem(int $id): void
    {
        $item = Item::findOrFail($id);

        $this->authorize('delete', $item);

        $old = $item->toArray();
        $item->delete();

        AuditLogger::logModel('delete', $item, $old);

        $this->dispatch('toast', type: 'success', message: 'Barang dihapus.');
    }

    public function openImportModal(): void
    {
        $this->authorize('create', Item::class);

        $this->reset('importFile', 'importErrors', 'importedCount', 'updatedCount');
        $this->showImportModal = true;
    }

    public function import(): void
    {
        $this->authorize('create', Item::class);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $import = new ItemsImport;

        Excel::import($import, $this->importFile);

        $this->importedCount = $import->imported;
        $this->updatedCount = $import->updated;
        $this->importErrors = $import->errors;

        AuditLogger::log('IMPORT', 'items', null, null, [
            'imported' => $import->imported,
            'updated' => $import->updated,
            'failed' => count($import->errors),
        ]);

        $this->reset('importFile');

        $this->dispatch(
            'toast',
            type: count($import->errors) > 0 ? 'warning' : 'success',
            message: "Import selesai: {$import->imported} dibuat, {$import->updated} diperbarui, ".count($import->errors).' gagal.'
        );
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $this->authorize('viewAny', Item::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ItemsImport::headings());
            fputcsv($handle, ['BRG-001', '8991234567890', 'Wireless Mouse', 'ELEC', 'PCS', 'Logitech', '30', '300', 'SUP001', 'active']);
            fclose($handle);
        }, 'items-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Item::class);

        $rows = $this->baseQuery()->orderBy($this->sortField, $this->sortDirection)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Barcode', 'Nama', 'Kategori', 'Satuan', 'Min', 'Max', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->sku,
                    $row->barcode,
                    $row->name,
                    $row->category?->name,
                    $row->unit?->name,
                    $row->minimum_stock,
                    $row->maximum_stock,
                    $row->status,
                ]);
            }

            fclose($handle);
        }, 'items-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function baseQuery(): Builder
    {
        return Item::query()
            ->with(['category', 'unit', 'primarySupplier'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('sku', 'like', $term)
                        ->orWhere('barcode', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter));
    }

    public function render()
    {
        return view('livewire.master-data.item-index', [
            'items' => $this->baseQuery()->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
