<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Users')]
class UserIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $roleFilter = '';

    public int $perPage = 10;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $status = 'active';

    /** @var array<int> */
    public array $selectedRoles = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('users.manage'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);

        $this->resetValidation();
        $this->reset(['editingId', 'name', 'email', 'password', 'selectedRoles']);
        $this->status = 'active';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);

        $this->authorize('update', $user);

        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->status = (string) $user->status;
        $this->selectedRoles = $user->roles->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $isEdit = $this->editingId !== null;
        $userModel = $isEdit ? User::findOrFail($this->editingId) : new User;

        $this->authorize($isEdit ? 'update' : 'create', $isEdit ? $userModel : User::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'status' => ['required', 'in:active,inactive'],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['integer', Rule::exists('roles', 'id')],
        ];

        if ($isEdit) {
            $rules['password'] = ['nullable', 'string', 'min:8', 'max:72'];
        } else {
            $rules['password'] = ['required', 'string', 'min:8', 'max:72'];
        }

        $data = $this->validate($rules);

        if ($isEdit && auth()->id() === $userModel->id && strtolower($data['status']) === 'inactive') {
            $this->dispatch('toast', type: 'error', message: __('Tidak dapat menonaktifkan akun sendiri.'));

            return;
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ];

        if ($data['password'] !== null && $data['password'] !== '') {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($isEdit) {
            $old = $userModel->toArray();
            $old['role_ids'] = $userModel->roles->pluck('id')->all();
            $userModel->update($payload);
            $userModel->roles()->sync($this->selectedRoles);
            $new = $userModel->fresh()->toArray();
            $new['role_ids'] = $userModel->fresh()->roles->pluck('id')->all();
            AuditLogger::logModel('update', $userModel, $old, $new);
            $this->dispatch('toast', type: 'success', message: __('User diperbarui.'));
        } else {
            $user = User::create($payload);
            $user->roles()->sync($this->selectedRoles);
            AuditLogger::logModel('create', $user, null, $user->toArray());
            $this->dispatch('toast', type: 'success', message: __('User dibuat.'));
        }

        $this->showModal = false;
    }

    public function toggleStatus(int $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('update', $user);

        if (auth()->id() === $user->id) {
            $this->dispatch('toast', type: 'error', message: __('Tidak dapat mengubah status akun sendiri.'));

            return;
        }

        $old = $user->toArray();
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        AuditLogger::logModel('update', $user, $old, $user->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: __('Status user diperbarui.'));
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('delete', $user);

        if (auth()->id() === $user->id) {
            $this->dispatch('toast', type: 'error', message: __('Tidak dapat menghapus akun sendiri.'));

            return;
        }

        $old = $user->toArray();
        $user->roles()->detach();
        $user->delete();

        AuditLogger::logModel('delete', $user, $old);

        $this->dispatch('toast', type: 'success', message: __('User dihapus.'));
    }

    protected function baseQuery()
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->roleFilter !== '', fn ($query) => $query->whereHas('roles', fn ($r) => $r->where('roles.id', $this->roleFilter)))
            ->orderBy('name');
    }

    public function render()
    {
        return view('livewire.admin.user-index', [
            'users' => $this->baseQuery()->paginate($this->perPage),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
