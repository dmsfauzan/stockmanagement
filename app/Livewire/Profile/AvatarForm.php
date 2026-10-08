<?php

namespace App\Livewire\Profile;

use App\Services\Support\ImageService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Avatar')]
class AvatarForm extends Component
{
    use WithFileUploads;

    public $avatar = null;

    public bool $removeAvatar = false;

    public function save(): void
    {
        $this->validate([
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $user = auth()->user();
        $images = app(ImageService::class);

        if ($this->avatar) {
            $images->delete($user->avatar_path);

            $user->update([
                'avatar_path' => $images->store($this->avatar, 'avatars'),
            ]);
        } elseif ($this->removeAvatar) {
            $images->delete($user->avatar_path);

            $user->update(['avatar_path' => null]);
        }

        $this->reset('avatar', 'removeAvatar');

        $this->dispatch('toast', type: 'success', message: __('Avatar disimpan.'));
    }

    public function remove(): void
    {
        $images = app(ImageService::class);
        $images->delete(auth()->user()->avatar_path);
        auth()->user()->update(['avatar_path' => null]);
        $this->reset('avatar', 'removeAvatar');
        $this->dispatch('toast', type: 'success', message: __('Avatar dihapus.'));
    }

    public function render()
    {
        return view('livewire.profile.avatar-form');
    }
}
