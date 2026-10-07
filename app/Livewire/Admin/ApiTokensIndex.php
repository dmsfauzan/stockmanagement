<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('API Tokens')]
class ApiTokensIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public int $selectedUserId = 0;

    public string $tokenName = 'api-token';

    /** @var array<int, string> */
    public array $abilities = [];

    public ?string $expiresAt = null;

    public ?string $plainToken = null;

    public ?int $plainTokenUserId = null;

    /** @var array<int, string> */
    public array $confirmDelete = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->reset(['selectedUserId', 'plainToken', 'plainTokenUserId']);
        $this->tokenName = 'api-token';
        $this->abilities = [];
        $this->expiresAt = null;
        $this->showCreateModal = true;
    }

    public function closeModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createToken(): void
    {
        $this->validate([
            'selectedUserId' => ['required', 'exists:users,id'],
            'tokenName' => ['required', 'string', 'min:2', 'max:80'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'exists:permissions,slug'],
            'expiresAt' => ['nullable', 'date'],
        ]);

        $user = User::findOrFail($this->selectedUserId);

        $abilities = $this->abilities === [] ? $user->permissionSlugs() : $this->abilities;
        $expiresAt = $this->expiresAt ? \Illuminate\Support\Carbon::parse($this->expiresAt) : null;

        $token = $user->createToken($this->tokenName, $abilities, $expiresAt);

        $this->plainToken = $token->plainTextToken;
        $this->plainTokenUserId = $user->id;
        $this->showCreateModal = false;
        $this->dispatch('toast', type: 'success', message: 'Token dibuat — salin sekarang, tidak bisa dilihat lagi.');
    }

    public function deleteToken(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);

        \Laravel\Sanctum\PersonalAccessToken::whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Token dihapus.');
    }

    public function render()
    {
        $tokens = \Laravel\Sanctum\PersonalAccessToken::query()
            ->with('tokenable')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('token', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.admin.api-tokens-index', [
            'tokens' => $tokens,
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'permissions' => Permission::orderBy('group')->orderBy('slug')->get(['id', 'slug', 'group']),
        ]);
    }
}
