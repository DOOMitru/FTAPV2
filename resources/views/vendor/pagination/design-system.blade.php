{{--
    The design system's pagination. Registered as the default in
    AppServiceProvider, so every paginated view in the app -- the seven admin
    index pages as well as the public events list -- uses it without changing a
    single call site. This was deferred to Phase 5; the events list needed it
    first.

    Laravel's stock views are Tailwind-only, which is why this replaces rather
    than restyles them.
--}}
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
            @if ($paginator->onFirstPage())
                <li><span class="pager__link pager__link--disabled" aria-disabled="true">{{ __('Previous') }}</span></li>
            @else
                <li><a class="pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pager__gap" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span class="pager__link pager__link--current" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a class="pager__link" href="{{ $url }}"
                                   aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a></li>
            @else
                <li><span class="pager__link pager__link--disabled" aria-disabled="true">{{ __('Next') }}</span></li>
            @endif
        </ul>
    </nav>
@endif
