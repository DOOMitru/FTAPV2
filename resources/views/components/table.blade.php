@props(['caption' => null, 'stacked' => false])

<div {{ $attributes->merge(['class' => 'table-scroll']) }}>
    {{-- stacked: below 48rem the rows reflow into label/value blocks. The
         modifier belongs on the table, and $attributes lands on the scroll
         wrapper, so it cannot come in as a plain class. --}}
    <table class="table{{ $stacked ? ' table--stacked' : '' }}">
        @if ($caption)
            <caption class="table__caption">{{ $caption }}</caption>
        @endif

        @isset($head)
            <thead><tr>{{ $head }}</tr></thead>
        @endisset

        <tbody>{{ $slot }}</tbody>
    </table>
</div>
