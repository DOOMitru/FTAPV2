@props(['messages'])

{{-- Wired to .field__error rather than Tailwind's text-red-600. That colour sits
     at 3.40:1 on the dark surface used by the guest panel — below AA — and it is
     rendered for every validation error on confirm-password and reset-password.
     --c-primary is AA on every surface in both themes.

     It stays red now that red is also the brand colour. What distinguishes an
     error here is not hue but position and conditionality: this is bold small
     text sitting directly beneath the field it belongs to, rendered only when
     that field failed. The brand red never appears in that shape. --}}
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'field__error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
