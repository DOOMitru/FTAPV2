{{-- Wired to the design system's .dropdown__item, which carries the same type
     treatment as .nav-link so a menu item and a top-level link read as one
     vocabulary. Previously this rendered stock Breeze Tailwind, which left the
     design-system rule dead and put text-gray-700 on the dark surface at
     1.59:1 — below AA and close to unreadable in dark mode. --}}
<a {{ $attributes->merge(['class' => 'dropdown__item']) }}>{{ $slot }}</a>
