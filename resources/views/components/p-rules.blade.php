@props(['items'])

{{--
    The rules of play, nested to any depth.

    Recursive: a clause renders its own sub-clauses through this same component,
    so the depth is whatever the data has rather than a number chosen here.

    Nothing carries its own number. The numbering comes from CSS counters, so
    "16.2.1" is produced by where the clause sits -- rules get cited by number,
    and a number typed into the content is one that silently stops matching the
    moment a clause is inserted above it.
--}}
<ol class="p-rules">
    @foreach ($items as $item)
        <li class="p-clause">
            <div class="p-clause__body">
                <p class="p-clause__text">{{ $item['text'] }}</p>

                @isset($item['note'])
                    <p class="p-clause__note">
                        <span class="p-clause__note-label">{{ __('Note') }}</span>
                        {{ $item['note'] }}
                    </p>
                @endisset

                @isset($item['children'])
                    <x-p-rules :items="$item['children']" />
                @endisset

                {{-- A sentence that belongs to the rule but follows its
                     sub-clauses: rule 6's deal is described, then its
                     exceptions, and only then what happens next. --}}
                @isset($item['after'])
                    <p class="p-clause__text">{{ $item['after'] }}</p>
                @endisset
            </div>
        </li>
    @endforeach
</ol>
