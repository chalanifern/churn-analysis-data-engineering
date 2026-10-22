@php
    /** @var list<string> $options */
    $value = old($name, $customer->{$name} ?? '');
    $hasError = $errors->has($name);
@endphp

<div class="space-y-1">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}
        <span class="text-rose-600" aria-hidden="true">*</span>
    </label>

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        required
        @class([
            'w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:outline-none',
            'border-rose-400 bg-rose-50 focus:ring-rose-200' => $hasError,
            'border-slate-300 bg-white focus:border-indigo-500 focus:ring-indigo-200' => ! $hasError,
        ])
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
    >
        <option value="">Select…</option>
        @foreach ($options as $option)
            <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
        @endforeach
    </select>

    @error($name)
        <p id="{{ $name }}-error" class="text-xs text-rose-700">{{ $message }}</p>
    @enderror
</div>
