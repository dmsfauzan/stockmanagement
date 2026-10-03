<?php

namespace App\Livewire\MasterData;

use App\Models\Unit;
use App\Services\Support\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Unit')]
class UnitIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

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

    public function render()
    {
        return view('livewire.master-data.unit-index', [
            'units' => Unit::query()
                ->withCount('items')
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
