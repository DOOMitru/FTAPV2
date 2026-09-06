{{--
    A player, as a picture: their photo when they have uploaded one, and their
    initials when they have not.

    The fallback used to be images/default_profile.png -- one generic 1024x1024
    face, shipped at 1.9MB and drawn at 24px, identical for everybody. Nobody in
    this league has uploaded a photo (the 205 accounts came from a CSV import
    that sets none), so in practice that was every avatar in the app: a column
    of the same grey stranger, which is worse than no avatar at all because it
    occupies the space where a difference should be. Initials at least tell
    Marcus from Priya, and they do it before anyone uploads anything.

    `user` is optional, and so is `name`. user_id is nullable with nullOnDelete
    on results, registrants and venue points, so a deleted player leaves a
    player_name string and no account behind it -- and every call site would
    otherwise have to handle that itself.

    `decorative` should be passed true whenever this sits next to an
    already-visible name (a leaderboard row, a table with a Name column) so the
    name is not announced twice. Left false, it carries the name itself, since a
    standalone avatar does need one.
--}}
@props(['user' => null, 'name' => null, 'size' => 'md', 'decorative' => false])

@php
    $dimension = match ($size) {
        'lg' => 64,
        'sm' => 24,
        default => 40,
    };

    $sizeClass = match ($size) {
        'lg' => ' avatar--lg',
        'sm' => ' avatar--sm',
        default => '',
    };

    // The photo, only when there is a real one. profile_image_url is not asked
    // here on purpose: it answers with the placeholder rather than with
    // nothing, so it cannot be used to tell the two states apart.
    $photo = filled($user?->profile_image) ? $user->profile_image_url : null;

    // What to announce. display_name prefers a nickname and falls back to the
    // first name alone, which is the right thing to say out loud.
    $label = $user?->display_name ?: ($name ?: __('Player'));

    // What to draw. The full name, not display_name -- initials want a surname,
    // and display_name drops it.
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

@if ($photo)
    <img
        {{ $attributes->merge([
            'class' => 'avatar'.$sizeClass,
            'src' => $photo,
            'alt' => $decorative ? '' : $label,
            'width' => $dimension,
            'height' => $dimension,
        ]) }}
    >
@else
    {{-- A span, not an img with a generated source: the initials are text, so
         they scale with the page, restyle with the theme and cost no request.

         role/aria-label rather than alt, and aria-hidden when decorative --
         without one of the two this reads out as a stray letter pair beside
         every name. --}}
    <span
        {{ $attributes->merge(['class' => 'avatar avatar--initials'.$sizeClass]) }}
        @if ($decorative) aria-hidden="true" @else role="img" aria-label="{{ $label }}" @endif
    >{{ $initials }}</span>
@endif
