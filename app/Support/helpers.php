<?php

use Illuminate\Support\HtmlString;

/**
 * The marker that sets an entity's name apart inside a message.
 *
 * U+2063 INVISIBLE SEPARATOR, chosen because it is exactly that: a character
 * with no width and no meaning of its own, whose job in Unicode is to separate
 * things without printing. If a message ever reaches a reader unsplit -- a
 * native window.confirm, a log line, an email -- the marker costs nothing,
 * where a visible sentinel like ** or ⟪⟫ would be rubbish on the page.
 */
const EMPH = "\u{2063}";

if (! function_exists('emph')) {
    /**
     * Mark an entity's name so whatever renders the message can set it apart.
     *
     * Messages carrying a venue, player, season, sponsor or tournament name are
     * read for the name -- an administrator deleting the third of four seasons
     * wants to know WHICH one they are about to delete, and the sentence around
     * it is the same every time.
     *
     * A marker rather than markup, because the message is plain text all the way
     * through and must stay that way. It travels through the session, through
     * translation, and -- for a confirmation -- through a data attribute that
     * confirm.ts deliberately reads as a value rather than as source. Putting
     * <strong> in here would hand back the injection that arrangement exists to
     * prevent: see the docblock on confirm.ts, where a season named
     * `'); alert(document.cookie); //` is the worked example.
     */
    function emph(?string $name): string
    {
        return EMPH.trim((string) $name).EMPH;
    }
}

if (! function_exists('emph_split')) {
    /**
     * Bold the marked runs of an ALREADY-ESCAPED string.
     *
     * Safe because every segment arrives escaped and is put back untouched; the
     * only thing added is <strong>. Nothing here parses HTML, so nothing here
     * can be tricked into executing any.
     *
     * Odd-numbered segments are the marked ones: splitting "a<M>b<M>c" gives
     * [a, b, c], and the same holds when the string starts or ends with a mark.
     * Balanced markers therefore always split into an ODD number of parts.
     *
     * An even count means a marker lost its partner -- a name truncated
     * somewhere, a message assembled by hand -- and the naive reading would
     * bold everything from that marker to the end of the sentence. Bolding
     * nothing is the quieter way to be wrong, so that is what an unbalanced
     * message gets.
     */
    function emph_split(string $escaped): HtmlString
    {
        $parts = explode(EMPH, $escaped);

        if (count($parts) % 2 === 0) {
            return new HtmlString(implode('', $parts));
        }

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1 && $part !== '') {
                $parts[$i] = '<strong class="emph">'.$part.'</strong>';
            }
        }

        return new HtmlString(implode('', $parts));
    }
}

if (! function_exists('emph_html')) {
    /** The same, for a raw string: escape first, then bold the marked runs. */
    function emph_html(?string $raw): HtmlString
    {
        return emph_split(e((string) $raw));
    }
}

if (! function_exists('emph_strip')) {
    /** The message with its markers removed, for anywhere that cannot bold. */
    function emph_strip(?string $raw): string
    {
        return str_replace(EMPH, '', (string) $raw);
    }
}

if (! function_exists('initials')) {
    /**
     * A person's initials: the first letter of the first word and of the last.
     *
     * One definition, because there are two callers with no way to share code
     * otherwise: <x-monogram>, which renders server-side, and the register
     * dialog's player list, which Alpine renders in the browser from a payload
     * PHP builds. A second derivation would be free to disagree -- and would,
     * the first time somebody fixed one of them.
     *
     * Handles "Wanda Reeve" and "Jean-Luc Picard", and does not fall over on
     * one word or none. mb_* throughout: a name is the last place to assume
     * one byte per letter.
     */
    function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = match (count($words)) {
            0 => '?',
            1 => mb_substr($words[0], 0, 1),
            default => mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1),
        };

        return mb_strtoupper($letters);
    }
}
