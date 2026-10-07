<div>
    <x-ui.page-header title="Two-Factor Authentication" subtitle="Amankan akun dengan kode TOTP dari aplikasi authenticator">
        <x-slot:actions>
            <a href="{{ route('profile.edit') }}" class="app-btn app-btn-secondary">Kembali ke Profil</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        @if (! $enabled && ! $needsConfirm)
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-app-text">Two-factor belum aktif</p>
                    <p class="mt-1 text-sm text-app-muted">Aktifkan untuk meminta kode dari aplikasi authenticator (Google Authenticator, Authy, dsb.) saat login.</p>
                    <button type="button" wire:click="enableQr" class="app-btn app-btn-primary mt-3">Aktifkan Two-Factor</button>
                </div>
            </div>
        @endif

        @if ($needsConfirm)
            <div>
                <p class="text-sm font-semibold text-app-text">1. Scan QR ini dengan aplikasi authenticator</p>
                <div class="mt-3 flex flex-col items-start gap-4 sm:flex-row">
                    <div class="rounded-xl border border-app-border bg-white p-2">
                        <div class="h-[200px] w-[200px] [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg !!}</div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-app-muted">Atau masukkan kode manual:</p>
                        <p class="mt-1 break-all rounded-lg bg-app-surface-2 px-3 py-2 font-mono text-sm text-app-text">{{ $secret }}</p>
                    </div>
                </div>

                <div class="mt-5 max-w-sm">
                    <label class="app-label">2. Masukkan 6 digit dari aplikasi</label>
                    <input type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" class="app-input mt-1 font-mono tracking-widest" placeholder="000000">
                    @error('code') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    <button type="button" wire:click="confirmEnable" class="app-btn app-btn-primary mt-3">Konfirmasi & Aktifkan</button>
                </div>
            </div>
        @endif

        @if ($enabled)
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Two-factor aktif
                    </span>
                </div>

                @if ($showRecovery && count($recoveryCodes))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/20">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Recovery codes (simpan di tempat aman)</p>
                        <div class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm text-amber-900 dark:text-amber-100">
                            @foreach ($recoveryCodes as $rc)
                                <span>{{ $rc }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-lg border border-app-border p-4">
                    <label class="app-label">Masukkan password untuk mengelola (regenerate codes / nonaktifkan)</label>
                    <input type="password" wire:model="disablePassword" class="app-input mt-1 max-w-sm" autocomplete="current-password">
                    @error('disablePassword') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="regenerateCodes" class="app-btn app-btn-secondary">Regenerate Recovery Codes</button>
                        <x-ui.confirm action="disable" title="Nonaktifkan 2FA" message="Nonaktifkan two-factor authentication?" confirm-label="Nonaktifkan" variant="danger" class="app-btn app-btn-danger">Nonaktifkan 2FA</x-ui.confirm>
                    </div>
                </div>
            </div>
        @endif
    </x-ui.card>
</div>
