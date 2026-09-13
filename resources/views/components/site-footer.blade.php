{{-- The site footer, shared by the public shell and the dashboard shell.
     Deliberately not part of either: a footer used by two shells is not
     furniture belonging to one of them. --}}
<footer class="site-footer">
    <div class="l-container site-footer__inner">
        {{-- Decorative: the signature beside it already says the name, so alt
             text would make a screen reader announce it twice. --}}
        <img class="site-footer__mark" src="{{ asset('images/header_logo.png') }}" alt="">

        {{-- Three spans rather than one string, so the line can break where it
             is written to break. On a phone the tagline does not fit beside
             the mark on one line, and letting it wrap on its own broke it
             mid-clause -- "Play hard. Play / smart. Be first to act." --}}
        <span class="site-footer__tag">
            <span>{{ __('Play hard.') }}</span>
            <span>{{ __('Play smart.') }}</span>
            <span>{{ __('Be first to act.') }}</span>
        </span>

        {{-- Two spans, for the same reason the tagline has three: at 375 this
             does not fit on one line and wrapped wherever it happened to run
             out -- "© 2026 First to / Act Poker", splitting the league's name
             across the break. Written as two parts it breaks between the year
             and the name, which is where a person would put it. --}}
        <span class="site-footer__sig">
            <span>&copy; {{ date('Y') }}</span>
            <span>{{ config('app.name') }}</span>
        </span>
    </div>
</footer>
