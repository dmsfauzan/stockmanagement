<?php

namespace App\Livewire\MasterData;

use App\Imports\CategoryImport;
use App\Livewire\Concerns\ImportsMasterData;
use App\Models\Category;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Kategori')]
class CategoryIndex extends Component
{
    use ImportsMasterData, WithPagination;

    public string $search = '';

    public string $trashedFilter = '';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $status = 'active';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTrashedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedIds(): void
    {
        if ($this->selectedIds === []) {
            $this->selectAll = false;
        }
    }

    public function updatedSelectAll(): void
    {
        $this->toggleSelectAll();
    }

    public function toggleSelectAll(): void
    {
        if (! $this->selectAll) {
            $this->selectedIds = [];

            return;
        }

        $this->selectedIds = Category::query()
            ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($this->perPage)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->hasPermission('items.create'), 403);

        $this->resetValidation();
        $this->reset(['editingId', 'code', 'name', 'description']);
        $this->status = 'active';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $category = Category::findOrFail($id);
        $this->resetValidation();

        $this->editingId = $category->id;
        $this->code = (string) $category->code;
        $this->name = (string) $category->name;
        $this->description = (string) ($category->description ?? '');
        $this->status = (string) $category->status;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $permission = $this->editingId ? 'items.update' : 'items.create';
        abort_unless(auth()->user()->hasPermission($permission), 403);

        if (! $this->editingId && $this->trashedConflict('categories', $this->code)) {
            $this->dispatch('toast', type: 'error', message: __('Kode sudah dipakai data terhapus. Pulihkan dari filter Terhapus.'));

            return;
        }

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('categories', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['description'] = $data['description'] !== '' ? $data['description'] : null;

        if ($this->editingId) {
            $category = Category::findOrFail($this->editingId);
            $old = $category->toArray();
            $category->update($data);
            AuditLogger::logModel('update', $category, $old, $category->fresh()->toArray());
        } else {
            $category = Category::create($data);
            AuditLogger::logModel('create', $category, null, $category->toArray());
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: __('Tersimpan'));
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        $category = Category::findOrFail($id);

        if ($category->items()->exists()) {
            $this->dispatch('toast', type: 'error', message: __('Kategori masih memiliki barang.'));

            return;
        }

        $old = $category->toArray();
        $category->delete();

        AuditLogger::logModel('delete', $category, $old);

        $this->dispatch('toast', type: 'success', message: __('Kategori dihapus.'));
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: __('Tidak ada data terpilih.'));

            return;
        }

        $deleted = 0;
        $skipped = 0;

        foreach (Category::whereIn('id', $this->selectedIds)->get() as $category) {
            if ($category->items()->exists()) {
                $skipped++;

                continue;
            }

            $old = $category->toArray();
            $category->delete();
            AuditLogger::logModel('delete', $category, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: $deleted > 0 ? 'success' : 'error', message: "Hapus {$deleted} kategori, {$skipped} dilewati.");
    }

    public function restore(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $category = Category::withTrashed()->findOrFail($id);
        $category->restore();

        AuditLogger::logModel('restore', $category, null, $category->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: __('Kategori dipulihkan.'));
    }

    public function bulkRestore(): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: __('Tidak ada data terpilih.'));

            return;
        }

        $count = 0;

        foreach (Category::withTrashed()->whereIn('id', $this->selectedIds)->get() as $category) {
            $category->restore();
            AuditLogger::logModel('restore', $category, null, $category->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "Pulihkan {$count} data.");
    }

    private function trashedConflict(string $table, string $code): bool
    {
        return DB::table($table)->where('code', trim($code))->whereNotNull('deleted_at')->exists();
    }

    public function bulkActivate(): void
    {
        $this->bulkSetStatus('active');
    }

    public function bulkDeactivate(): void
    {
        $this->bulkSetStatus('inactive');
    }

    protected function bulkSetStatus(string $status): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: __('Tidak ada data terpilih.'));

            return;
        }

        $count = 0;

        foreach (Category::whereIn('id', $this->selectedIds)->get() as $category) {
            $old = $category->toArray();
            $category->update(['status' => $status]);
            AuditLogger::logModel('update', $category, $old, $category->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "{$count} kategori diperbarui menjadi {$status}.");
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);

        $rows = Category::query()
            ->withCount('items')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Description', 'Status', 'Items Count']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->name,
                    $row->description,
                    $row->status,
                    $row->items_count,
                ]);
            }

            fclose($handle);
        }, 'categories-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function importClass(): string
    {
        return CategoryImport::class;
    }

    protected function importLabelText(): string
    {
        return 'Kategori';
    }

    protected function importModule(): string
    {
        return 'categories';
    }

    protected function importPermission(): string
    {
        return 'items.create';
    }

    protected function importSampleRow(): array
    {
        return ['ELEC', 'Elektronik', 'Barang elektronik', 'active'];
    }

    public function render()
    {
        return view('livewire.master-data.category-index', [
            'categories' => Category::query()
                ->withCount('items')
                ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
                ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
