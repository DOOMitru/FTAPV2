{{--
    The design system's pagination. Registered as the default in
    AppServiceProvider, so every paginated view in the app -- the seven admin
    index pages as well as the public events list -- uses it without changing a
    single call site. This was deferred to Phase 5; the events list needed it
    first.

    Laravel's stock views are Tailwind-only, which is why this replaces rather
    than restyles them.

    The page numbers are windowed here rather than taken from $elements.
    Laravel's own window cannot produce the shape this app wants: under eight
    pages it renders every one of them, and over that its slider widens around
    the current page. $elements is still passed in by links() and deliberately
    unused.
--}}
@php
    // Declared once rather than inlined four times: each of Previous and Next
    // has an enabled and a disabled arm, and four copies of an svg is four
    // places for one to drift.
    $prevArrow = new \Illuminate\Support\HtmlString('<svg class="pager__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7"/></svg>');
    $nextArrow = new \Illuminate\Support\HtmlString('<svg class="pager__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>');
@endphp

@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <span class="pager__status">
            {!! __('Showing :first to :last of :total', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) !!}
        </span>

        <ul class="pager__list">
            {{-- Word and arrow both, always in the DOM. On a phone the words
                 do not fit -- PREVIOUS, five page numbers and NEXT need about
                 400px against 343 of usable width, so Next wrapped onto a line
                 of its own and sat there orphaned, the two controls people
                 actually use split across two rows.

                 Below 48rem the label is clipped rather than removed, so the
                 link is an arrow to look at and still reads as "Previous" to a
                 screen reader. display:none would have taken the name with
                 it. --}}
            @if ($paginator->onFirstPage())
                <li><span class="pager__link pager__link--edge pager__link--disabled" aria-disabled="true">{{ $prevArrow }}<span class="pager__label">{{ __('Previous') }}</span></span></li>
            @else
                <li><a class="pager__link pager__link--edge" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ $prevArrow }}<span class="pager__label">{{ __('Previous') }}</span></a></li>
            @endif

            @php
                $lastPage = $paginator->lastPage();
                $currentPage = $paginator->currentPage();

                // The first two, the last two, and the page you are on.
                //
                // The current page earns its place: without it nothing in the
                // row is marked, and on page 5 of 10 the control would show
                // "1 2 ... 9 10" with no indication of where you are. Its
                // neighbours are deliberately left out -- Previous and Next
                // reach them, and the point of this window is to stay short.
                //
                // unique() matters at the small end: on three pages the set is
                // 1, 2, 2, 3 before it is deduped, and on two pages
                // $lastPage - 1 is 1.
                $pages = collect([1, 2, $currentPage, $lastPage - 1, $lastPage])
                    ->filter(fn ($page) => $page >= 1 && $page <= $lastPage)
                    ->unique()
                    ->sort()
                    ->values();
            @endphp

            @foreach ($pages as $index => $page)
                {{-- A gap only where one was actually skipped, so a run of
                     consecutive pages never grows a separator. --}}
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <li><span class="pager__gap" aria-hidden="true">&hellip;</span></li>
                @endif

                @if ($page === $currentPage)
                    <li>
                        <span class="pager__link pager__link--current" aria-current="page">{{ $page }}</span>
                    </li>
                @else
                    <li>
                        <a class="pager__link" href="{{ $paginator->url($page) }}"
                           aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                    </li>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="pager__link pager__link--edge" href="{{ $paginator->nextPageUrl() }}" rel="next"><span class="pager__label">{{ __('Next') }}</span>{{ $nextArrow }}</a></li>
            @else
                <li><span class="pager__link pager__link--edge pager__link--disabled" aria-disabled="true"><span class="pager__label">{{ __('Next') }}</span>{{ $nextArrow }}</span></li>
            @endif
        </ul>
    </nav>
@endif
