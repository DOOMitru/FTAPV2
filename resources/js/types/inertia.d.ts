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
     * Shared on every page, rendered by none of them.
     *
     * Three controllers flash sentinel strings ('profile-updated',
     * 'password-updated', 'verification-link-sent') that a page MATCHES on to
     * decide whether to show an inline saved indicator. A component that
     * printed flash.status would show a reader the sentinel itself.
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
