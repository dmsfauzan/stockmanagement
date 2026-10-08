<?php

namespace App\Livewire\Profile;

use App\Services\Support\NotificationPreferenceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Preferensi Notifikasi')]
class NotificationPreferences extends Component
{
    /** @var array<string, bool> */
    public array $email = [];

    public bool $digest = false;

    public function mount(): void
    {
        $user = auth()->user();

        foreach (array_keys(NotificationPreferenceService::types()) as $type) {
            $this->email[$type] = NotificationPreferenceService::isEmailEnabled($user, $type);
        }

        $this->digest = NotificationPreferenceService::isDigestEnabled($user);
    }

    public function save(): void
    {
        $user = auth()->user();

        foreach (array_keys(NotificationPreferenceService::types()) as $type) {
            NotificationPreferenceService::setEmailEnabled($user, $type, (bool) ($this->email[$type] ?? false));
        }

        NotificationPreferenceService::setEmailEnabled($user, NotificationPreferenceService::digestType(), $this->digest);

        $this->dispatch('toast', type: 'success', message: __('Preferensi notifikasi disimpan.'));
    }

    public function render()
    {
        return view('livewire.profile.notification-preferences', [
            'types' => NotificationPreferenceService::types(),
            'globalEmailEnabled' => NotificationPreferenceService::emailGloballyEnabled(),
            'globalDigestEnabled' => NotificationPreferenceService::digestGloballyEnabled(),
        ]);
    }
}
