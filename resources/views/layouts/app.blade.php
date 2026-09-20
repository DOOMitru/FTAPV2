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
        <div class="shell">
            @include('layouts.navigation')

            {{-- Flash messages deliberately not rendered here yet: eight views already
                 render session('status')/session('error') themselves (poker/{seasons,
                 tournaments,points-structure,venues,registrants,venue-points,results}/index
                 and poker/tournaments/show), so a layout-level block would duplicate them.
                 Worse, ProfileController, Auth/PasswordController and
                 Auth/EmailVerificationNotificationController flash sentinel strings
                 ('profile-updated', 'password-updated', 'verification-link-sent') that the
                 profile partials match on but never display — a naive block here would
                 render those literally. The layout takes ownership of flash messages once
                 the views that duplicate them convert and the sentinel-flashing controllers
                 are updated to flash real copy; until then this is the same YAGNI call
                 already made for pagination in this phase. --}}
            @isset($header)
                <header class="shell__header">
                    <div class="shell__header-inner">{{ $header }}</div>
                </header>
            @endisset

            {{-- No .l-container here. Every view supplies its own, and this
                 one nested inside it -- .l-container sets padding-inline, so
                 two of them meant the gutter was applied twice: 32px a side on
                 a phone, where the content had the least room to give. --}}
            <main class="shell__content">{{ $slot }}</main>

            {{-- .shell is a flex column with min-height: 100vh and
                 .shell__content takes flex: 1, so this sits at the bottom of
                 the viewport on a short page rather than floating mid-screen. --}}
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
