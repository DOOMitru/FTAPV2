{{-- Delegates to <x-btn>. ghost is the Cancel/dismiss variant and, like this
     component before it, defaults to type="button" so it cannot submit a form
     by accident. --}}
<x-btn variant="ghost" {{ $attributes }}>{{ $slot }}</x-btn>
