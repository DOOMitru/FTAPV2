@props(['value'])

{{-- <x-field> supersedes this, but 23 views still call it directly, so it is
     restyled rather than deleted. Same class <x-field> renders. --}}
<label {{ $attributes->merge(['class' => 'field__label']) }}>
    {{ $value ?? $slot }}
</label>
