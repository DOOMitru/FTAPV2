@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'bag' => 'default',
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

    {{-- The bag matters. profile/edit renders three forms on one page and two
         of them use named bags, so that a failure in one does not light up the
         fields of another. @error's second argument is how that reaches here. --}}
    @error($name, $bag)
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>
