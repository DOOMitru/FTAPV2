{{-- Delegates to <x-btn>. Kept only so the 23 forms still calling it keep
     working until Phases 2-5 convert them; there is one button now, not four.
     <x-btn variant="primary"> already defaults to type="submit", matching this
     component's old behaviour. --}}
<x-btn variant="primary" {{ $attributes }}>{{ $slot }}</x-btn>
