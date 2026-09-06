@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="masthead">
{{-- The mark, then the name, then the motto -- the light hero from the home
     page, in the order it reads there.

     The name is TEXT, not part of the image, and that is the point: most mail
     clients block remote images until the reader allows them, so a header that
     is one picture arrives as an empty box above a nameless message. Here the
     image is the ornament and the words carry the identity. --}}
<img src="{{ asset('images/hero_logo.png') }}" class="logo" alt="">
<div class="brand">First to Act <span class="brand-accent">Poker League</span></div>
<div class="motto">Play hard. Play smart. Be first to act.</div>
</a>
</td>
</tr>
