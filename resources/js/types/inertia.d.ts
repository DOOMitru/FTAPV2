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
    is_admin: boolean;
}

export interface PageProps {
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
}
