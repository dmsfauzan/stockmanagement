@props(['model' => null])
@if ($model)
    @php
        $progress = method_exists($model, 'approvalProgress') ? $model->approvalProgress() : ['current' => 0, 'required' => 1];
        $histories = method_exists($model, 'approvalHistories') ? $model->approvalHistories : collect();
    @endphp
    <x-ui.card>
        <div class="flex items-center justify-between gap-2">
            <h2 class="app-card-title">Approval</h2>
            <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-900/30 dark:text-primary-300">
                {{ $progress['current'] }}/{{ $progress['required'] }}
            </span>
        </div>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($histories as $h)
                <li class="flex items-start gap-2">
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $h->action === 'approved' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    <div class="min-w-0">
                        <p class="text-app-text">
                            Level {{ $h->level }} ·
                            <span class="{{ $h->action === 'approved' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ ucfirst($h->action) }}</span>
                            — {{ $h->user?->name ?? '-' }}
                        </p>
                        <p class="text-xs text-app-muted">{{ to_display_tz($h->created_at)?->format('d M Y H:i') }}@if($h->notes) · {{ $h->notes }}@endif</p>
                    </div>
                </li>
            @empty
                <li class="text-xs text-app-muted">{{ __('Belum ada riwayat approval.') }}</li>
            @endforelse
        </ul>
    </x-ui.card>
@endif
