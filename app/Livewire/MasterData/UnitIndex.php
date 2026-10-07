<?php

namespace App\Livewire\MasterData;

use App\Models\Unit;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Unit')]
class UnitIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $trashedFilter = '';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

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

        $this->selectedIds = Unit::query()
            ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('name')
            ->paginate($this->perPage)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->hasPermission('items.create'), 403);

        $this->resetValidation();
        $this->reset(['editingId', 'code', 'name']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $unit = Unit::findOrFail($id);
        $this->resetValidation();

        $this->editingId = $unit->id;
        $this->code = (string) $unit->code;
        $this->name = (string) $unit->name;
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

        if (! $this->editingId && $this->trashedConflict('units', $this->code)) {
            $this->dispatch('toast', type: 'error', message: 'Kode sudah dipakai data terhapus. Pulihkan dari filter Terhapus.');

            return;
        }

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('units', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingId) {
            $unit = Unit::findOrFail($this->editingId);
            $old = $unit->toArray();
            $unit->update($data);
            AuditLogger::logModel('update', $unit, $old, $unit->fresh()->toArray());
        } else {
            $unit = Unit::create($data);
            AuditLogger::logModel('create', $unit, null, $unit->toArray());
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Tersimpan');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        $unit = Unit::findOrFail($id);

        if ($unit->items()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Unit masih digunakan barang.');

            return;
        }

        $old = $unit->toArray();
        $unit->delete();

        AuditLogger::logModel('delete', $unit, $old);

        $this->dispatch('toast', type: 'success', message: 'Unit dihapus.');
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasPermission('items.delete'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $deleted = 0;
        $skipped = 0;

        foreach (Unit::whereIn('id', $this->selectedIds)->get() as $unit) {
            if ($unit->items()->exists()) {
                $skipped++;

                continue;
            }

            $old = $unit->toArray();
            $unit->delete();
            AuditLogger::logModel('delete', $unit, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: $deleted > 0 ? 'success' : 'error', message: "Hapus {$deleted} unit, {$skipped} dilewati.");
    }

    public function restore(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $unit = Unit::withTrashed()->findOrFail($id);
        $unit->restore();

        AuditLogger::logModel('restore', $unit, null, $unit->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: 'Unit dipulihkan.');
    }

    public function bulkRestore(): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $count = 0;

        foreach (Unit::withTrashed()->whereIn('id', $this->selectedIds)->get() as $unit) {
            $unit->restore();
            AuditLogger::logModel('restore', $unit, null, $unit->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "Pulihkan {$count} data.");
    }

    private function trashedConflict(string $table, string $code): bool
    {
        return DB::table($table)->where('code', trim($code))->whereNotNull('deleted_at')->exists();
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);

        $rows = Unit::query()
            ->withCount('items')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->name,
                ]);
            }

            fclose($handle);
        }, 'units-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.master-data.unit-index', [
            'units' => Unit::query()
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
