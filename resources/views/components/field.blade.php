@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'hintInline' => false,
    'bag' => 'default',
    'id' => null,
])

@php
    // The id follows the name unless a caller overrides it, and a caller has
    // to when the same field name appears twice on one page. profile/edit
    // renders three forms, two of which have a `password` -- both inputs took
    // id="password", and a label points at the FIRST match in the document.
    // The delete-account dialog's Password label therefore focused the New
    // Password box in the form above it. The named error bags on that page
    // exist for the same collision; the ids had not caught up.
    $controlId = $id ?? $name;

    // A note beside the label is still the control's description, so it is
    // pointed at rather than merely placed next to it -- otherwise it is read
    // by eye only and a screen reader announces the field with no mention of
    // the constraint it is about to reject the entry for.
    $noted = $hint && $hintInline;
    $noteId = $noted ? $controlId.'-note' : null;
@endphp

<div>
    @if ($noted)
        {{-- The note shares the label's line: a constraint short enough to read
             in passing belongs where the eye already is, above the control,
             rather than under one it is meant to be read before. --}}
        <div class="field__label-row">
            <label class="field__label" for="{{ $controlId }}">{{ $label }}</label>

            <span class="field__note" id="{{ $noteId }}">{{ $hint }}</span>
        </div>
    @else
        <label class="field__label" for="{{ $controlId }}">{{ $label }}</label>
    @endif

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
            id="{{ $controlId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @required($required)
            @if ($noteId) aria-describedby="{{ $noteId }}" @endif
            {{ $attributes->merge(['class' => 'field__control']) }}
        >
    @endif

    {{-- Under the control unless it was asked for beside the label; drawing
         it in both places would say the same thing twice. --}}
    @if ($hint && ! $hintInline)
        <p class="field__hint">{{ $hint }}</p>
    @endif

    {{-- The bag matters. profile/edit renders three forms on one page and two
         of them use named bags, so that a failure in one does not light up the
         fields of another. @error's second argument is how that reaches here. --}}
    @error($name, $bag)
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>
