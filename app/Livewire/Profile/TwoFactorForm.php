<?php

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\Barcode\LabelService;
use App\Services\Support\NotificationService;
use App\Services\Support\TwoFactorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Two-Factor Authentication')]
class TwoFactorForm extends Component
{
    public bool $enabled = false;

    public bool $needsConfirm = false;

    public ?string $secret = null;

    public ?string $qrSvg = null;

    public string $code = '';

    /** @var array<int, string> */
    public array $recoveryCodes = [];

    public bool $showRecovery = false;

    public string $disablePassword = '';

    public function mount(TwoFactorService $twoFactor): void
    {
        $user = auth()->user();

        $this->enabled = $user->hasTwoFactorEnabled();

        if (! $user->hasTwoFactorSetup()) {
            $secret = $twoFactor->generateSecret();
            $user->forceFill(['two_factor_secret' => $secret])->save();
        }
    }

    public function enableQr(TwoFactorService $twoFactor): void
    {
        $user = auth()->user()->fresh();

        if (! $user->hasTwoFactorSetup()) {
            $user->forceFill(['two_factor_secret' => $twoFactor->generateSecret()])->save();
            $user = $user->fresh();
        }

        $this->needsConfirm = true;
        $this->secret = (string) $user->two_factor_secret;
        $this->showRecovery = false;

        $url = $twoFactor->provisioningUrl(
            (string) config('app.name', 'Warehouse Stock Management'),
            (string) $user->email,
            $this->secret,
        );

        $this->qrSvg = app(LabelService::class)->qrSvg($url, 200);
    }

    public function confirmEnable(TwoFactorService $twoFactor): void
    {
        $user = auth()->user()->fresh();

        $this->validate([
            'code' => ['required', 'string', 'min:4', 'max:64'],
        ]);

        if (! $user->hasTwoFactorSetup() || ! $twoFactor->verify((string) $user->two_factor_secret, $this->code)) {
            $this->addError('code', __('Kode tidak valid.'));

            return;
        }

        $codes = TwoFactorService::generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->enabled = true;
        $this->needsConfirm = false;
        $this->recoveryCodes = $codes;
        $this->showRecovery = true;
        $this->reset('code');

        NotificationService::notify(
            (int) $user->getKey(),
            'security.2fa',
            'Two-factor diaktifkan',
            'Two-factor authentication diaktifkan untuk akun Anda.',
            User::class,
            (int) $user->getKey(),
        );

        $this->dispatch('toast', type: 'success', message: __('Two-factor diaktifkan. Simpan recovery codes Anda.'));
    }

    public function regenerateCodes(): void
    {
        $this->validate([
            'disablePassword' => ['required', 'current_password'],
        ]);

        $codes = TwoFactorService::generateRecoveryCodes();

        auth()->user()->forceFill(['two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($codes)])->save();

        $this->recoveryCodes = $codes;
        $this->showRecovery = true;
        $this->reset('disablePassword');

        $this->dispatch('toast', type: 'success', message: __('Recovery codes baru dibuat.'));
    }

    public function disable(): void
    {
        $this->validate([
            'disablePassword' => ['required', 'current_password'],
        ]);

        auth()->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->enabled = false;
        $this->needsConfirm = false;
        $this->showRecovery = false;
        $this->reset('code', 'disablePassword', 'secret', 'qrSvg', 'recoveryCodes');

        NotificationService::notify(
            (int) auth()->id(),
            'security.2fa',
            'Two-factor dimatikan',
            'Two-factor authentication dimatikan untuk akun Anda.',
            User::class,
            (int) auth()->id(),
        );

        $this->dispatch('toast', type: 'success', message: __('Two-factor dimatikan.'));
    }

    public function render()
    {
        return view('livewire.profile.two-factor-form');
    }
}
