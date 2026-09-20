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
        <div class="guest">
            <div class="guest__panel l-stack">
                <div class="guest__brand"><x-brand /></div>
                {{ $slot }}
            </div>
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
