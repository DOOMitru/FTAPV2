@props(['disabled' => false])

{{-- Same class <x-field> renders, so a hand-built form and a <x-field> one are
     visually identical. --}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'field__control']) }}>
