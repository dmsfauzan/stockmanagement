@props(['status' => 'normal', 'label' => null])
@php
    $value = is_object($status) && property_exists($status, 'value') ? $status->value : (string) $status;
    $map = [
        'normal' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800',
        'low' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
        'low_stock' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
        'out' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800',
        'out_of_stock' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800',
        'over' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-800',
        'overstock' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-800',
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800',
        'inactive' => 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600',
        'draft' => 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600',
        'submitted' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
        'requested' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800',
        'in_transit' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-800',
        'received' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-900/40 dark:text-teal-300 dark:ring-teal-800',
        'approved' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-900/40 dark:text-blue-300 dark:ring-blue-800',
        'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800',
        'posted' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800',
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800',
        'opening' => 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600',
        'incoming' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800',
        'outgoing' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800',
        'transfer_in' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-800',
        'transfer_out' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-900/40 dark:text-indigo-300 dark:ring-indigo-800',
        'adjustment_in' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-900/40 dark:text-teal-300 dark:ring-teal-800',
        'adjustment_out' => 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-900/40 dark:text-orange-300 dark:ring-orange-800',
        'reversed' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600',
    ];
    $classes = $map[$value] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600';
    $translationKey = 'status.'.$value;
    $text = $label ?? (\Illuminate\Support\Facades\Lang::has($translationKey) ? __($translationKey) : ucwords(str_replace('_', ' ', $value)));
@endphp
<span {{ $attributes->merge(['class' => "app-badge $classes"]) }}>
    {{ $text }}
</span>
