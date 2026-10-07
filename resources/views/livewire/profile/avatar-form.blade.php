<div>
    <x-ui.page-header title="Avatar" subtitle="Foto profil Anda">
        <x-slot:actions>
            <a href="{{ route('profile.edit') }}" class="app-btn app-btn-secondary">Kembali ke Profil</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-xl">
        <div class="flex items-center gap-4">
            @php $avatar = auth()->user()->avatar_path; @endphp
            @if (app(\App\Services\Support\ImageService::class)->thumbUrl($avatar))
                <img src="{{ app(\App\Services\Support\ImageService::class)->thumbUrl($avatar) }}" alt="Avatar" class="h-16 w-16 rounded-full object-cover">
            @else
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-800 text-xl font-semibold text-white">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
            @endif
            <div>
                <p class="text-sm font-medium text-app-text">{{ auth()->user()->name }}</p>
                <p class="text-xs text-app-muted">{{ auth()->user()->email }}</p>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            <div>
                <label class="app-label">Foto baru (JPG/PNG/WebP/GIF, maks 2MB)</label>
                <input type="file" wire:model="avatar" accept="image/*" class="app-input mt-1 text-sm">
                @error('avatar') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                <div wire:loading wire:target="avatar" class="mt-1 text-xs text-app-muted">Mengunggah…</div>
            </div>
            <label class="flex items-center gap-2 text-sm text-app-muted">
                <input type="checkbox" wire:model.live="removeAvatar" class="rounded">
                Hapus avatar saat ini
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="remove" class="app-btn app-btn-ghost text-rose-600 dark:text-rose-400">Hapus</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" class="app-btn app-btn-primary">Simpan</button>
            </div>
        </div>
    </x-ui.card>
</div>
