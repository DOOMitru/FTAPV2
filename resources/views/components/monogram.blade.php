{{--
    A player, as their initials.

    This was <x-avatar>, and it drew a photo when one had been uploaded. Nobody
    ever uploaded one -- the 205 accounts came from a CSV import that sets none
    -- so the picture half was a column, an accessor, an upload control on two
    forms and file handling in two controllers, all maintained for a state the
    app was never in. It is gone, and so is the word avatar: a component named
    for a picture it cannot hold is a component that lies about itself.

    `user` is optional, and so is `name`. user_id is nullable with nullOnDelete
    on results, registrants and venue points, so a deleted player leaves a
    player_name string and no account behind it -- and every call site would
    otherwise have to handle that itself.

    `decorative` should be passed true whenever this sits next to an
    already-visible name (a leaderboard row, a table with a Name column) so the
    name is not announced twice. Left false, it carries the name itself.
--}}
@props(['user' => null, 'name' => null, 'size' => 'md', 'decorative' => false])

@php
    $sizeClass = match ($size) {
        'lg' => ' monogram--lg',
        'sm' => ' monogram--sm',
        default => '',
    };

    // What to announce. display_name prefers a nickname and falls back to the
    // first name alone, which is the right thing to say out loud.
    $label = $user?->display_name ?: ($name ?: __('Player'));

    // What to draw. The full name, not display_name -- initials want a surname,
    // and display_name drops it, so a player nicknamed "Ace" would be a lone A.
    $fullName = $user ? trim($user->first_name.' '.$user->last_name) : (string) $name;

    // First letter of the first word and of the last, which handles "Wanda
    // Reeve" and "Jean-Luc Picard" and does not fall over on one word or none.
    // mb_* throughout: a name is the last place to assume one byte per letter.
    $words = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $initials = match (count($words)) {
        0 => '?',
        1 => mb_substr($words[0], 0, 1),
        default => mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1),
    };

    $initials = mb_strtoupper($initials);
@endphp

{{-- A span, not an image: the initials are text, so they scale with the page,
     restyle with the theme and cost no request.

     role/aria-label rather than alt, and aria-hidden when decorative -- without
     one of the two this reads out as a stray letter pair beside every name. --}}
<span
    {{ $attributes->merge(['class' => 'monogram'.$sizeClass]) }}
    @if ($decorative) aria-hidden="true" @else role="img" aria-label="{{ $label }}" @endif
>{{ $initials }}</span>
