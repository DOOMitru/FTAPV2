/**
 * The props HandleInertiaRequests::share() puts on every page.
 *
 * This is a second definition of something PHP already decides, which is the
 * shape of problem this project keeps meeting. InertiaPropContractTest holds
 * the per-page half of it; these shared keys are held by
 * InertiaSharedPropsTest on the PHP side.
 */

/** The five fields the middleware narrows the signed-in user to. */
export interface AuthUser {
    id: string;
    first_name: string;
    last_name: string;
    nickname: string | null;
    /** The accessor's answer: the nickname, or the first name. */
    display_name: string;
    /** The letters, from the FULL name -- see spec §5a. */
    initials: string;
    is_admin: boolean;
}

export interface PageLink {
    /**
     * What this link IS, rather than something for the template to infer from
     * its label. Laravel's own labels carry HTML entities -- "&laquo;
     * Previous" -- and a template deciding "is this the previous link?" by
     * looking for the word would break the first time it was translated.
     */
    kind: 'previous' | 'next' | 'page' | 'gap';
    /** Null when there is nowhere to go: the current page, a gap, or an edge. */
    url: string | null;
    label: string;
    active: boolean;
    aria_label: string | null;
}

/** A page of results, as Laravel's paginator crosses into Vue. */
export interface Paginated<T> {
    data: T[];
    links: PageLink[];
    /** "Showing 1 to 10 of 43", or null when there is only one page. */
    status_label: string | null;
}

/**
 * One notification, ready to render.
 *
 * Every field is decided by PHP -- spec §5a. There is no `place: number` here
 * on purpose: the ordinal, the date format and the points label are PHP rules,
 * and a second copy in TypeScript would be free to disagree with the Blade
 * card still serving unconverted pages.
 */
export interface Notification {
    id: string;
    is_read: boolean;
    /** Semantic, not a class name: the template maps it to one. */
    tier: 'gold' | 'silver' | 'bronze' | 'scored';
    place_label: string;
    title: string;
    meta: string;
    /** Carries U+2063 emphasis markers; render through <Emph>. */
    delete_confirm: string;
}

/**
 * Where a tournament stands, from PokerTournament::status(). Rendered by
 * TournamentStatus.vue on the tournaments list and a venue's page.
 */
export type TournamentStatus = 'upcoming' | 'awaiting' | 'published';

/**
 * One season's figures for one player, as App\Support\SeasonFigures builds
 * them.
 *
 * Here rather than inside SeasonPanel.vue because three files share it: the
 * component renders it, and the dashboard and a player's profile each declare
 * it among their props. A copy in each would be three definitions of one
 * shape.
 */
export interface SeasonFigures {
    /** "#3" -- the place, shared on a tie -- or "—" when unranked. */
    rank_label: string;
    /** On the board: at least one point this season. Picks the panel's branch. */
    is_ranked: boolean;
    /** "218 pts over 26 events" -- pluralised in PHP. */
    totals_label: string;
    /** "of 41 ranked players", or null when unranked. Pluralised in PHP. */
    ranked_label: string | null;
    first: string;
    second: string;
    third: string;
    events_label: string;
    points_label: string;
    /**
     * Null means the viewer may not read them -- VenuePoints::readableBy(),
     * applied by the controller. The row is absent rather than blank or zero:
     * a zero is a fact about the player, and this is a fact about the reader.
     *
     * Spec §8 requires the figure withheld from the PROPS, not hidden here.
     * This component only renders what it is given.
     */
    venue_points_label: string | null;
}

/**
 * The four props a page spreads in for the panel.
 *
 * snake_case, unlike the component's own props: these are what the controller
 * sends, and the template bridges the two -- :season-name="season_name".
 */
export interface SeasonPanelProps {
    /** The card's title: the season's name, or a generic heading. */
    season_name: string;
    /** Where "Full Season Stats" goes, or null when no season is running. */
    season_href: string | null;
    /** Null when no season is running. */
    season: SeasonFigures | null;
    /** Whether the viewer is the player. Changes the COPY only. */
    self: boolean;
}

export interface PageProps {
    /**
     * Inertia's usePage<T>() constrains T to its own PageProps, which carries
     * an index signature -- a page may be handed props nobody declared here.
     * Without this the constraint is unsatisfied and every usePage call fails
     * to compile. It does NOT weaken the declared keys below: those keep their
     * types, and InertiaPropContractTest still checks a page against what its
     * controller actually sends.
     */
    [key: string]: unknown;

    /** `user` is null when signed out, never absent -- see the middleware. */
    auth: { user: AuthUser | null };

    /**
     * Shared on every page and drawn by AppLayout.
     *
     * Always a sentence, never a sentinel. Three controllers used to flash
     * strings a page matched on rather than displayed, which is why nothing
     * could render this generically; they no longer do, so the shell shows it
     * and a page inside the shell need not mention it at all.
     */
    flash: { status: string | null; error: string | null };

    /**
     * The shell's badge and panel. Shared on every page, because the
     * navigation is on every page.
     */
    notifications: {
        unread_count: number;
        recent: Notification[];
    };
}
