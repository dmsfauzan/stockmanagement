<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Roles & Permissions')]
class RoleIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    /** @var array<int> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('roles.manage'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Role::class);

        $this->resetValidation();
        $this->reset(['editingId', 'name', 'selectedPermissions']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);

        $this->authorize('update', $role);

        $this->resetValidation();
        $this->editingId = $role->id;
        $this->name = (string) $role->name;
        $this->selectedPermissions = $role->permissions->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function togglePermission(int $permissionId): void
    {
        if ($this->isAdminRole()) {
            return;
        }

        if (in_array($permissionId, $this->selectedPermissions, true)) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, [$permissionId]));
        } else {
            $this->selectedPermissions[] = $permissionId;
        }
    }

    public function save(): void
    {
        $isEdit = $this->editingId !== null;
        $roleModel = $isEdit ? Role::findOrFail($this->editingId) : new Role();

        $this->authorize($isEdit ? 'update' : 'create', $isEdit ? $roleModel : Role::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->editingId)],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        if ($isEdit && $roleModel->slug === 'admin') {
            $data['selectedPermissions'] = Permission::pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        if (! $isEdit) {
            $slug = Str::slug($data['name'], '_');
            $base = $slug !== '' ? $slug : 'role';
            $slug = $base;
            $i = 1;
            while (Role::where('slug', $slug)->exists()) {
                $slug = $base.'_'.$i++;
            }
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $slug,
                'is_system' => false,
            ]);
            $role->permissions()->sync($data['selectedPermissions']);
            AuditLogger::logModel('create', $role, null, $role->toArray());
            $this->dispatch('toast', type: 'success', message: 'Role dibuat.');
        } else {
            $old = $roleModel->toArray();
            $old['permission_ids'] = $roleModel->permissions->pluck('id')->all();
            $roleModel->update(['name' => $data['name']]);
            $roleModel->permissions()->sync($data['selectedPermissions']);
            $new = $roleModel->fresh()->toArray();
            $new['permission_ids'] = $roleModel->fresh()->permissions->pluck('id')->all();
            AuditLogger::logModel('update', $roleModel, $old, $new);
            $this->dispatch('toast', type: 'success', message: 'Role diperbarui.');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $role = Role::withCount('users')->findOrFail($id);

        $this->authorize('delete', $role);

        if ($role->is_system) {
            $this->dispatch('toast', type: 'error', message: 'Role sistem tidak dapat dihapus.');

            return;
        }

        if ($role->users_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Role masih digunakan oleh user.');

            return;
        }

        $old = $role->toArray();
        $old['permission_ids'] = $role->permissions->pluck('id')->all();
        $role->permissions()->detach();
        $role->delete();

        AuditLogger::logModel('delete', $role, $old);

        $this->dispatch('toast', type: 'success', message: 'Role dihapus.');
    }

    protected function isAdminRole(): bool
    {
        if ($this->editingId !== null) {
            return Role::whereKey($this->editingId)->where('slug', 'admin')->exists();
        }

        return false;
    }

    public function render()
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.admin.role-index', [
            'roles' => $roles,
            'permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'isAdmin' => $this->isAdminRole(),
        ]);
    }
}
