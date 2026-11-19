@props(['name', 'label', 'checked' => false, 'hint' => null])

@php
    $id = 'toggle-'.$name;
    $isChecked = (bool) old($name, $checked);
@endphp

<label for="{{ $id }}" class="flex cursor-pointer items-center justify-between gap-4 rounded-xl px-1 py-2">
    <span class="min-w-0">
        <span class="block text-sm font-medium text-ink">{{ $label }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs leading-relaxed text-ink-soft">{{ $hint }}</span>
        @endif
    </span>

    <span class="relative inline-flex shrink-0">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="1"
            @checked($isChecked)
            class="peer sr-only"
        >
        <span class="h-6 w-10 rounded-full bg-line-strong transition-colors duration-150 peer-checked:bg-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent" aria-hidden="true"></span>
        <span class="pointer-events-none absolute left-1 top-1 size-4 rounded-full bg-white shadow-sm transition-transform duration-150 ease-[var(--ease-out-quart)] peer-checked:translate-x-4" aria-hidden="true"></span>
    </span>
</label>
