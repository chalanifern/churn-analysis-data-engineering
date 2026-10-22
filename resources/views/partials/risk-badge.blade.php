@php
    $tone = match ($level ?? 'Low') {
        'High' => 'bg-rose-100 text-rose-700 ring-rose-200',
        'Medium' => 'bg-amber-100 text-amber-700 ring-amber-200',
        default => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    };
@endphp

<span @class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset',
    $tone,
])>{{ $level ?? 'Low' }}</span>
