@props(['variant' => 'info'])

{{--
    The slot arrives ESCAPED -- every caller writes {{ session('status') }} --
    so emph_split() only has to put <strong> around the runs an entity name was
    marked with. It never parses what is already there, which is what keeps a
    venue called `<script>` a venue called `<script>`.

    Done here rather than at the call sites so that every alert on the site gets
    it, including ones written after this: twenty-seven of them say exactly
    `<x-alert variant="success">{{ session('status') }}</x-alert>`, and a rule
    that has to be remembered at each of them is a rule that will be missed.
--}}
<div {{ $attributes->merge(['class' => 'alert alert--'.$variant]) }} role="{{ $variant === 'danger' ? 'alert' : 'status' }}">
    {{ emph_split($slot->toHtml()) }}

    {{-- An optional trailing control, which only x-toast passes: a dismiss
         button has to sit INSIDE the alert to share its surface. NAMED,
         because the default slot here is the message -- and not run through
         emph_split, because it is markup rather than a sentence. --}}
    {{ $control ?? '' }}
</div>
