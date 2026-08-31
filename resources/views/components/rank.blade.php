@props(['place'])

<span {{ $attributes->merge(['class' => 'rank'.($place <= 3 ? ' rank--podium' : '')]) }}>{{ $place }}</span>
