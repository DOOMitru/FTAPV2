@props(['user' => null])

{{--
    A player's name, as a way to their figures.

    An <a> only when there is an account to point at. user_id is nullable with
    nullOnDelete on results, registrants and venue points, so a departed player
    leaves a player_name string and nothing behind it -- and a link to
    players.show for a user that no longer exists is a 404 with somebody's name
    on it. Every call site would otherwise have to remember that itself, which
    is the sort of thing that gets remembered in nine places out of ten.

    The slot rather than a name prop, because the text differs at the call
    sites: a nickname carrying the full name in a title, a snapshotted
    player_name, a first and last name read off the model.
--}}
@if ($user)
    <a href="{{ route('players.show', $user) }}"
       {{ $attributes->merge(['class' => 'player-link']) }}>{{ $slot }}</a>
@else
    <span {{ $attributes }}>{{ $slot }}</span>
@endif
