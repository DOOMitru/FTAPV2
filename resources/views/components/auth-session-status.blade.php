@props(['status'])

{{-- A session status is a success message; <x-alert> already renders one with
     the right role and token colours. The old markup was green-600 text with no
     dark variant, which sat at 3.4:1 on the dark auth panel. --}}
@if ($status)
    <x-alert variant="success" {{ $attributes }}>{{ $status }}</x-alert>
@endif
