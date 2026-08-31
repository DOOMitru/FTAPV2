{{-- The real brand mark. This previously rendered Laravel's own SVG, left over
     from Breeze scaffolding. Decorative: the brand link's name comes from the
     adjacent wordmark. --}}
<img src="{{ asset('images/header_logo.png') }}" alt="" {{ $attributes->merge(['class' => 'brand__logo']) }}>
