@props(['messages'])

{{-- Wired to .field__error rather than Tailwind's text-red-600. That colour sits
     at 3.40:1 on the dark surface used by the guest panel — below AA — and it is
     rendered for every validation error on confirm-password and reset-password.
     --c-accent-text is AA on every surface in both themes. --}}
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'field__error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
