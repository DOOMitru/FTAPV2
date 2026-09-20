<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'First to Act Poker') }}</title>

        <link rel="preload" href="{{ asset('fonts/archivo.woff2') }}" as="font" type="font/woff2" crossorigin>

        <x-theme-script />
        <script>(function(){var m=location.search.match(/_t=(light|dark)/);if(m){document.documentElement.setAttribute('data-theme',m[1]);}})();</script>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png"/>
    </head>
    <body>
        <div class="public">
            <x-topbar>
                <x-slot name="links">
                    <a class="nav-link {{ request()->routeIs('home') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('home')) aria-current="page" @endif
                       href="{{ route('home') }}">{{ __('Home') }}</a>

                    <a class="nav-link {{ request()->routeIs('events') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('events')) aria-current="page" @endif
                       href="{{ route('events') }}">{{ __('Events') }}</a>

                    <x-dropdown align="left" :inline-mobile="true">
                        <x-slot name="trigger">
                            <button type="button"
                                    class="nav-link {{ request()->routeIs('rules.*') ? 'nav-link--current' : '' }}"
                                    @if (request()->routeIs('rules.*')) aria-current="page" @endif>
                                {{ __('Rules') }}
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            {{-- Named for what each page is titled, so the menu
                                 and the page a reader lands on agree. --}}
                            <x-dropdown-link :href="route('rules.tournament')">{{ __('Rules & Regulations') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('rules.betting')">{{ __('Betting & Conduct') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('rules.texas-holdem')">{{ __('How to play') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('rules.points-structure')">{{ __('Points') }}</x-dropdown-link>
                        </x-slot>
                    </x-dropdown>

                    <a class="nav-link {{ request()->routeIs('about.*') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('about.*')) aria-current="page" @endif
                       href="{{ route('about.index') }}">{{ __('About') }}</a>

                    <a class="nav-link {{ request()->routeIs('contact') ? 'nav-link--current' : '' }}"
                       @if (request()->routeIs('contact')) aria-current="page" @endif
                       href="{{ route('contact') }}">{{ __('Contact') }}</a>
                </x-slot>

                <x-slot name="actions">
                    @auth
                        {{-- Signed in, the public bar carries the same two structures as
                             the dashboard's own bar and reuses its classes: a plain row on
                             desktop, a flat left/right row on mobile. Dashboard is a nav
                             link, not a button -- it is another place to go, the same as
                             Events or About. --}}
                        <div class="topbar__actions-desktop">
                            <x-theme-toggle />
                            <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                        </div>

                        {{-- Mobile: log out alone on the left where a destructive action is
                             hard to hit by accident; theme and Dashboard group on the right,
                             matching layouts/navigation.blade.php exactly. --}}
                        <div class="topbar__actions-mobile">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-btn variant="danger" size="sm">{{ __('Log out') }}</x-btn>
                            </form>

                            <div class="topbar__identity">
                                <x-theme-toggle />
                                <a class="nav-link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                            </div>
                        </div>
                    @else
                        <x-theme-toggle />
                        <a class="nav-link" href="{{ route('login') }}">{{ __('Log in') }}</a>
                        {{-- "Join" stays a button: it is the one call to action on the page. --}}
                        <x-btn variant="primary" size="sm" :href="route('register')">{{ __('Join') }}</x-btn>
                    @endauth
                </x-slot>
            </x-topbar>

            <main class="public__main">
                <div class="l-container l-stack">{{ $slot }}</div>
            </main>

            <x-site-footer />

            {{-- One per page. Every form carrying data-confirm is asked about
                 here rather than through the browser's own dialog. --}}
            <x-confirm-dialog />
        </div>
        <script>window.addEventListener('load',function(){
  var act=(location.search.match(/_act=(\w+)/)||[])[1];
  if(!act) return;
  var ping=function(m){fetch('/__report',{method:'POST',headers:{'Content-Type':'text/plain','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:m})};
  window.onerror=function(m){ping('PAGE ERROR '+m)};
  var q=function(s){return document.querySelector(s)};
  var state=function(tag){
    var o=[];
    o.push('path='+location.pathname);
    o.push('title='+((q('.page-header__title')||q('h1')||{}).textContent||'-').trim());
    ['first_name','last_name','nickname','email'].forEach(function(n){
      var e=q('#'+n);
      o.push(n+'='+(e?JSON.stringify(e.value)+' ac='+JSON.stringify(e.getAttribute('autocomplete')||'-'):'MISSING'));
    });
    var c=q('#is_admin');
    o.push('is_admin='+(c?c.type+':'+c.checked:'MISSING'));
    o.push('hiddenAdmin='+document.querySelectorAll('input[type=hidden][name=is_admin]').length);
    o.push('strayAc='+document.querySelectorAll('div[autocomplete]').length);
    o.push('grid='+document.querySelectorAll('.l-grid').length+' check='+document.querySelectorAll('.field__check').length);
    o.push('intro='+JSON.stringify(((q('.card .field__hint')||{}).textContent||'-').trim().slice(0,40)));
    o.push('buttons='+[].map.call(document.querySelectorAll('.l-container .btn'),function(b){return b.textContent.trim()}).join(','));
    o.push('errors='+[].map.call(document.querySelectorAll('.field__error'),function(e){return e.textContent.trim().slice(0,50)}).join(' | '));
    o.push('alert='+((q('.alert')||{}).textContent||'-').trim());
    o.push('invite='+!!document.querySelector('.card input[readonly], .invite, code'));
    o.push('scrollW='+document.documentElement.scrollWidth+'/'+window.innerWidth);
    ping(tag+' :: '+o.join(' ~~ '));
  };
  var set=function(el,v){var s=Object.getOwnPropertyDescriptor(el.constructor.prototype,'value').set;s.call(el,v);el.dispatchEvent(new Event('input',{bubbles:true}))};
  setTimeout(function(){
    state('loaded-'+act);
    if(act==='look') return;
    if(act==='register'){ set(q('#first_name'),'Probe'); set(q('#last_name'),'Registered');
                          set(q('#email'),'probe.registered@example.test'); }
    if(act==='dupe'){ set(q('#first_name'),'Probe'); set(q('#last_name'),'Dupe');
                      set(q('#email'),'probe.admin@example.test'); }
    if(act==='untick'){ var c=q('#is_admin'); ping('before :: adminChecked='+c.checked); if(c.checked) c.click(); }
    setTimeout(function(){
      q('#first_name').form.requestSubmit();
      setTimeout(function(){state('after-'+act)},2600);
    },400);
  },900);
});</script>
    </body>
</html>
