<x-app-layout>
    <x-ui.page-header title="{{ __('Profile') }}" :subtitle="__('Manage your account settings and preferences.')">
        <x-slot:actions>
            <a href="{{ route('profile.avatar') }}" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                Ubah Avatar
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-ui.card padding="p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-ui.card>

        <x-ui.card padding="p-6 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-ui.card>

        <x-ui.card padding="p-6 sm:p-8" class="border-rose-200 dark:border-rose-900/50">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
