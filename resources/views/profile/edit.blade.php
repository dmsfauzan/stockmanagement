<x-app-layout>
    <x-ui.page-header title="{{ __('Profile') }}" :subtitle="__('Manage your account settings and preferences.')" />

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
