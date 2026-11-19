@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'min' => null,
    'max' => null,
    'step' => null,
    'rows' => 3,
])

@php
    $id = $attributes->get('id', 'field-'.$name);
    $wrapperClass = $attributes->get('class');
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $current = old($name, $value);
    $current = is_scalar($current) ? (string) $current : '';
@endphp

<div @if ($wrapperClass) class="{{ $wrapperClass }}" @endif>
    <label for="{{ $id }}" class="field-label">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            class="field-input resize-y"
        >{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            class="field-input appearance-none bg-[length:0.7rem] bg-[right_1rem_center] bg-no-repeat pr-10"
            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 12 8%22 fill=%22none%22 stroke=%22%235d6a64%22 stroke-width=%221.5%22%3E%3Cpath d=%22m1 1.5 5 5 5-5%22/%3E%3C/svg%3E')"
        >
            {{ $slot }}
        </select>
    @else
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $current }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($step !== null) step="{{ $step }}" @endif
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            class="field-input"
        >
    @endif

    @if ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="field-error" role="alert">{{ $error }}</p>
    @endif
</div>
