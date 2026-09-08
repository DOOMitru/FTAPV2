@props(['caption' => null, 'stacked' => false, 'cards' => false])

<div {{ $attributes->merge(['class' => 'table-scroll']) }}>
    {{-- stacked: below 48rem the rows reflow into label/value blocks.
         cards: below 48rem each row becomes a card whose cells are PLACED on a
         grid the page defines, rather than stacked into labelled lines. Use it
         where a row has a clear primary identifier and a handful of supporting
         facts -- stacking a five-column admin list prints five labelled lines
         per record and buries the thing you came to find.

         Both modifiers belong on the table, and $attributes lands on the scroll
         wrapper, so neither can come in as a plain class. --}}
    <table class="table{{ $stacked ? ' table--stacked' : '' }}{{ $cards ? ' table--cards' : '' }}">
        @if ($caption)
            <caption class="table__caption">{{ $caption }}</caption>
        @endif

        @isset($head)
            <thead><tr>{{ $head }}</tr></thead>
        @endisset

        <tbody>{{ $slot }}</tbody>
    </table>
</div>
