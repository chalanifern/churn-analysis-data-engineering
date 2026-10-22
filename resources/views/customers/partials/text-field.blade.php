@php
    $value = old($name, $customer->{$name} ?? '');
    $hasError = $errors->has($name);
    $baseClasses = 'w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none';
    $stateClasses = $hasError
        ? 'border-rose-400 bg-rose-50 focus:ring-rose-200'
        : 'border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-200';
@endphp

<div class="space-y-1">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}
        @if ($required ?? false)
            <span class="text-rose-600" aria-hidden="true">*</span>
        @endif
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type ?? 'text' }}"
        value="{{ $value }}"
        @if ($required ?? false) required @endif
        @if (isset($extra)) {{ $extra }} @endif
        class="{{ $baseClasses }} {{ $stateClasses }}"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    >

    @if ($hasError)
        <p id="{{ $name }}-error" class="text-xs text-rose-700">{{ $errors->first($name) }}</p>
    @endif
</div>
