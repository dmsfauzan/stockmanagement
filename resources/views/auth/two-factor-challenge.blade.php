<x-guest-layout>
    <div x-data="{ recovery: false }">
        <div class="mb-4 text-center">
            <p class="text-sm font-semibold text-app-text">Verifikasi Two-Factor</p>
            <p class="mt-1 text-xs text-app-muted" x-show="! recovery">Masukkan kode 6 digit dari aplikasi authenticator Anda.</p>
            <p class="mt-1 text-xs text-app-muted" x-show="recovery">Masukkan salah satu recovery code Anda.</p>
        </div>

        <form method="POST" action="{{ route('two-factor.verify') }}">
            @csrf

            <div x-show="! recovery">
                <x-input-label for="code" :value="__('Kode')" />
                <x-text-input id="code" class="mt-1 block w-full font-mono tracking-widest" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus x-ref="code" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div x-show="recovery" x-cloak>
                <x-input-label for="recovery_code" :value="__('Recovery Code')" />
                <x-text-input id="recovery_code" class="mt-1 block w-full font-mono" type="text" name="recovery_code" autocomplete="one-time-code" x-ref="recovery_code" />
                <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
            </div>

            <div class="mt-4 flex items-center justify-between">
                <button type="button" class="text-xs text-app-muted underline hover:text-app-text"
                    @click="recovery = ! recovery; $nextTick(() => { recovery ? $refs.recovery_code.focus() : $refs.code.focus() })">
                    <span x-show="! recovery">Gunakan recovery code</span>
                    <span x-show="recovery">Gunakan kode authenticator</span>
                </button>

                <x-primary-button>
                    {{ __('Verifikasi') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-xs text-app-muted underline hover:text-app-text">Batalkan & keluar</button>
        </form>
    </div>
</x-guest-layout>
