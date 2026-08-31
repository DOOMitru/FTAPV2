@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

<div>
    <label class="field__label" for="{{ $name }}">{{ $label }}</label>

    {{-- Non-text controls (checkbox, radio, select, etc.) go through the
         slot, styled by the caller — this component only renders a text
         <input>. --}}
    @isset($slot)
        @if (trim($slot) !== '')
            {{ $slot }}
        @endif
    @endisset

    @if (! isset($slot) || trim($slot) === '')
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @required($required)
            {{ $attributes->merge(['class' => 'field__control']) }}
        >
    @endif

    @if ($hint)
        <p class="field__hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>
