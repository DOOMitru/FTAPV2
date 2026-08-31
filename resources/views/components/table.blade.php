@props(['caption' => null])

<div {{ $attributes->merge(['class' => 'table-scroll']) }}>
    <table class="table">
        @if ($caption)
            <caption class="table__caption">{{ $caption }}</caption>
        @endif

        @isset($head)
            <thead><tr>{{ $head }}</tr></thead>
        @endisset

        <tbody>{{ $slot }}</tbody>
    </table>
</div>
