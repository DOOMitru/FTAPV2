@props(['name', 'cards', 'index'])

{{--
    One hand from the hierarchy: five cards face down that turn over in order,
    once the hands above it have been dealt.

    Every card is drawn twice, back and face, one behind the other. The card
    itself rotates; the face starts turned away and comes round with it. That is
    the whole mechanism -- no script decides when anything happens, and a
    browser that ignores the animation still ends up showing the face.
--}}
{{-- A style attribute setting only a custom property: this hand's place in the
     hierarchy. The cascade needs it to know when this hand's turn comes, and
     the stylesheet cannot know which hand it is drawing. --}}
<figure class="p-hand" style="--hand: {{ $index - 1 }}">
    <div class="p-hand__cards">
        @foreach ($cards as $i => $card)
            {{-- A style attribute setting only a custom property: this card's
                 place in the deal, which the stagger in the stylesheet reads.
                 The delay cannot live in the CSS alone -- it needs to know
                 which card this is. --}}
            <div class="p-hand__card" style="--deal: {{ $i }}">
                {{-- Both images are decorative. The caption below names the
                     hand and lists the cards for a reader who cannot see them,
                     which is one announcement instead of ten per hand. --}}
                <img class="p-hand__side p-hand__side--back"
                     src="{{ asset('images/deck/back.svg') }}" alt="" aria-hidden="true">

                <img class="p-hand__side p-hand__side--face"
                     src="{{ asset('images/deck/'.$card['code'].'.svg') }}" alt="" aria-hidden="true">
            </div>
        @endforeach
    </div>

    <figcaption class="p-hand__caption">
        <span class="p-hand__rank">{{ $index }}</span>
        <span class="p-hand__name">{{ $name }}</span>
        <span class="u-visually-hidden">{{ implode(', ', array_column($cards, 'label')) }}</span>
    </figcaption>
</figure>
