# Inertia + Vue conversion

**Status:** approved design, not yet implemented.
**Date:** 2026-09-19.

The authenticated half of the app moves from Blade + Alpine to Inertia + Vue 3,
written in TypeScript throughout. The public half stays exactly as it is.

**The driver is a stated preference for working in Vue rather than Blade.** That
is worth writing down plainly, because it sets the bar for every trade-off
below: this conversion is not fixing a defect, relieving a bottleneck, or
unlocking a feature. Nothing in the app is waiting on it. So where a choice is
between "more Vue" and "less risk to a working, live application with 206
players", this document chooses less risk.

## The problem this design had to solve first

Inertia server-side rendering needs a Node process that stays alive.
`docs/DEPLOYMENT.md` records that DreamHost shared hosting cannot hold one open
-- the queue is already a cron running `queue:work --stop-when-empty` for that
reason. So a full single-page application on the current host means every page
is client-rendered.

That is survivable for screens behind a login. It is not survivable for `/`,
`/events`, `/about`, `/contact` and the seven `/rules/*` pages, which exist to
be found and read by people who have never signed in. Those pages are the
league's public face, and an empty shell that fills in after JavaScript runs is
a worse page for both a search engine and a phone on a slow connection.

**Decision: a hybrid.** Inertia and Blade coexist in one Laravel application.
The 29 routes behind `auth` become Vue pages; the 18 public routes keep the
server-rendered Blade they have now. No host change, no SSR, no SEO regression.

The alternatives were weighed and rejected. Converting everything and accepting
client rendering trades the public pages' first paint and indexability for
consistency nobody sees. Converting everything and moving to a VPS solves it
properly but turns a view-layer change into a hosting migration -- a new server,
a rewritten deploy pipeline, and an ongoing cost, in service of a preference.

## 1. The boundary

**COMPLETE, 2026-09-20.** All 29 authenticated routes are Vue; the 18 public
ones are Blade and are staying. What follows is the list as it was drawn, and
it held: nothing moved across the line in either direction.

One thing the list did not anticipate. `p-event` is rendered by two public
pages AND by the tournament details page, so it became a TWELFTH twin
alongside the eleven in §2 -- a component that exists twice for good rather
than for the length of the conversion. `ComponentParityTest` discovers it by
name like the others.

Exact, because "the app behind login" is a vibe and this needs to be a list.

**Becoming Vue pages (29):** `/dashboard`, `/profile`, `/players/{player}`,
`/seasons/{season}`, `/tournaments/{tournament}`, `/confirm-password`,
`/verify-email`, `/verify-email/{id}/{hash}`, `/users` and its `create`,
`{user}`, `{user}/edit`, `/sponsors` and its `create`, `{sponsor}/edit`, and all
of `/poker/*` -- seasons, tournaments, venues, points-structure and
venue-points/create, with their index, create, show and edit pages.

**Staying Blade (18):** `/`, `/events`, `/about`, `/about/mission`,
`/about/sponsors`, `/contact`, the seven `/rules/*` routes, `/login`,
`/register`, `/forgot-password`, `/reset-password/{token}`.

**The auth screens stay Blade deliberately, and not only for SEO.** `/register`
carries the reCAPTCHA v3 gate: `resources/js/recaptcha.ts` hooks a Blade form's
submit, fetches a token, and races a five-second timeout. That path is verified
end to end and `recaptcha:check` gates the deploy on its configuration. Leaving
it untouched means this project cannot break the one defence the public
registration form has. Converting the auth screens later is a small, separate
piece of work if it is ever wanted.

## 2. Eleven components have to exist twice -- eighteen, as it turned out

**AMENDED, 2026-09-20.** Eleven was the prediction, twelve was the count when
`p-event` joined them, and seventeen is the number after the dead Blade
dashboard shell was deleted -- not because more were duplicated, but because
deleting the eight components nothing called any more left only the genuinely
shared ones behind, and the guard then counted what was actually there.
`ComponentParityTest` DISCOVERS the pairs, so no list needed correcting; the
number in this heading is the only thing that ever went stale -- and it went
stale again on 2026-09-21, when `toast` became the eighteenth. Eleven was never
going to be the number; that a guard which discovers its own subjects never
needed touching is the part worth keeping.

This is the real cost of the hybrid, and the part most likely to cause trouble
later, so it is stated first among the consequences rather than buried.

`btn`, `card`, `field`, `alert`, `badge`, `table`, `rank`, `monogram`,
`empty-state` and `player-link` are used by both public Blade pages and
authenticated pages. So is the confirmation-dialog machinery:
`layouts/public.blade.php` loads `confirm.ts`, and `p-event` on the public
`/events` page carries `data-confirm`, because a signed-in visitor can register
for a tournament from a public page.

Each of those needs a Blade version and a Vue version, rendering the same class
hooks, because the CSS is keyed on those hooks and the CSS is not being
rewritten. Two definitions of one thing is the defect class this project has
been bitten by repeatedly -- `PokerSeason::current()`, `VenuePoints::readableBy()`
and `initials()` all exist because a second definition drifted or was about to.

**Mitigation: a parity guard, written before the second component is converted,
not after the eleventh.** It extracts the CSS class literals from each Blade
component and from its Vue twin and fails when the two sets diverge. It does not
prove the components behave alike; it proves they agree about the hooks the
stylesheet depends on, which is the drift that silently breaks a page's
appearance.

**Accepted limitation:** behavioural drift between a Blade component and its Vue
twin is not caught by anything. The public surface is small and changes rarely,
which is what makes this acceptable rather than reckless.

## 3. Architecture

**Middleware.** `HandleInertiaRequests` shares the authenticated user, flash
messages and validation errors on every Inertia response.

**AMENDED, 2026-09-20.** The spec left open who RENDERS the shared flash, and
for the length of the conversion each page did. `AppLayout` owns it now. What
had prevented that was three controllers flashing sentinel strings -- values a
page matched on rather than displayed -- and none of them does any more: two
flash nothing (`useForm`'s `recentlySuccessful` tells a form it saved, which is
all the sentinel ever said) and the third flashes the sentence. A page may opt
out with `:show-flash`, which exactly one does.

**AMENDED AGAIN, 2026-09-21.** The flash is a toast at the bottom of the
viewport, and `layouts/public.blade.php` is no longer "untouched": it renders
the same thing server-side for the public half, so neither half has a page that
draws its own. Each shell keeps two live regions permanently in the document --
one polite, one assertive -- and only the message is inserted, because a region
created together with its content is unreliably announced.

**Layouts.** `AppLayout.vue` is a persistent layout carrying the navigation,
topbar and notifications; it does not remount between page visits, so the nav
state survives navigation. `layouts/public.blade.php` keeps serving the Blade
side -- untouched at the time of writing, though see the 2026-09-21 amendment
above. A visitor crossing the boundary -- signing in, or
following a link from `/events` to a tournament -- gets one ordinary full page
load. That is correct behaviour, not a defect to engineer around.

**Emphasis markers port as-is.** `emph()` wraps a name in U+2063 markers and
returns a plain string; 52 call sites do this. Because the markers travel as
data rather than as HTML, a flash prop crosses to Vue unchanged and a small
`<Emph>` component renders the marked runs in bold. Nothing about the PHP side
changes.

**Confirmation dialogs.** `confirm.ts` is 194 lines driving 29 dialogs. The Vue
side gets a composable equivalent; the TypeScript module stays in place for the
public pages that still use it. This is one of the eleven duplications and is
the largest of them.

**Theme.** `theme.ts` runs before Vue mounts, writes `data-theme` and reads
`localStorage`. It is untouched and keeps working on both sides. The inline
theme script in the layout head stays: it exists to prevent a flash of the wrong
theme, and moving it into Vue would reintroduce exactly that flash.

**Pagination.** `resources/views/vendor/pagination/design-system.blade.php`
gets a Vue twin driven by Laravel's paginator props.

**CSS.** Imported globally in `app.ts`, unchanged. The five-layer cascade
(`1-base` through `5-public`) and its import order survive intact -- the order
is load-bearing and is documented throughout the stylesheets. Scoped styles per
component are explicitly rejected: they would dismantle that cascade and the
specificity reasoning recorded in it.

## 4. TypeScript

Every Vue single-file component is `<script setup lang="ts">`. No JavaScript
files are added to `resources/js`.

`tsconfig.json` already has `strict: true` and already includes
`resources/js/**/*.vue`, so no configuration change is needed to start.

**Nothing type-checks today, and that has to change first.** `package.json`
defines only `build` and `dev`; Vite strips types with esbuild without checking
them, and CI runs `npm run build` alone. A type error ships silently right now.
So `npm run typecheck` (`vue-tsc --noEmit`) is added and wired into CI beside
the suite. Until that gate exists, "TypeScript throughout" is a style convention
rather than a guarantee -- and this project spent a week learning what a check
that cannot fail is worth.

Props are declared with `defineProps<Props>()` and emits with
`defineEmits<...>()`. `any` and `@ts-ignore` require a comment saying why.

`resources/js/types/global.d.ts` loses its `Alpine` declaration when Alpine goes
and keeps `grecaptcha`, which is still needed by the Blade `/register` page.

## 5. Page props are defined twice, so they get a drift guard

A `DashboardProps` interface in TypeScript describes a shape a PHP controller
produces. That is the same two-definitions problem as section 2, in a different
place.

**Decision: hand-written interfaces beside each page, plus a route-walking
guard.** The guard requests every authenticated route as an administrator,
reads the real Inertia props off the response, and compares the top-level key
set against the keys declared in that page's `Props` interface. A controller
that renames or drops a prop fails the suite instead of quietly leaving the
interface describing something the server no longer sends.

Generating the types from PHP with `spatie/laravel-typescript-transformer` was
considered and rejected for scope: it works properly only with dedicated data
objects, which would pull `spatie/laravel-data` and a DTO layer across 23
controllers into a project that is already large enough.

The guard is deliberately shallow -- top-level keys, not nested shapes. It
catches the common failure (a prop renamed, added or removed) without
reimplementing a type checker in PHP.

## 5a. Props carry display-ready values

Decided on 2026-09-19, while porting the app shell, after four components in a
row turned out to render values PHP computes: `initials()`, `display_name`,
`Number::ordinal()`, `Carbon::parse()->format()`, `number_format()`, `emph()`
and `__()`.

**The rule: PHP decides a value, Vue places it.** A prop carries
`place_label: "3rd"`, not `place: 3` for the template to ordinalise. It carries
`initials: "WR"`, not a first and last name for the template to reduce.

**Why, and it is not a preference.** This project has one definition of a rule
and a test for each: `PokerSeason::current()`, `VenuePoints::readableBy()`,
`initials()`. The last of those already answered this exact question -- the note
at its call site in `monogram.blade.php` reads: "initials() rather than the
derivation inline: the register dialog builds a player list in the browser and
needs the same letters from PHP, and two copies of this would be free to
disagree." A player list is already rendered in the browser from letters PHP
computed. Vue is the same situation with a different renderer.

`__()` deserves its own note: there is no `lang/` directory, so it returns its
key and translation is a convention rather than a live feature. That makes
hardcoding English in Vue harmless today and wrong later -- a `lang/` file added
in a year would translate the Blade half and not the Vue half, and nothing would
say so. User-facing strings therefore come from PHP too.

**What this costs.** Props get chattier, and a value cannot be reformatted
client-side without a round trip. Neither matters at this scale: the largest
list any page sends is ten notifications.

**Where the line sits.** Vue owns what is genuinely presentational and has no
PHP rule behind it: which element gets a class, whether a panel is open, how a
grid wraps. It does not own what a value reads as.

**Semantic, not stylistic.** A prop says `tier: "gold"`, not
`tier: "notification--gold"`. The rule -- first three places take medal tiers,
a lower scoring finish takes the accent -- lives once in PHP; mapping a tier to
a class name is each template's own trivial business, and the middleware has no
opinion about the stylesheet.

## 6. Tests

Of 112 test files:

- **49 assert on authenticated HTML** and are ported to
  `assertInertia(fn (Assert $page) => ...)`, asserting the component name and
  props rather than rendered markup.
- **8 touch both sides** and are ported in part.
- **5 cover public pages only** and do not change.
- **34 never touch HTML** -- model, command and unit tests -- and do not change.
- **16 scan `.blade.php` files directly** and are re-homed, as below.

**Guards that retire (2) -- NEITHER DID, 2026-09-20.** `MarkupBalanceTest` and
`AlpineAttributeGuardTest` were expected to go once their last target
converted. Their last targets are `components/{modal,topbar,dropdown}.blade.php`
and the public pages that render them, none of which convert under the boundary
in §1 -- so both stay, and so does Alpine. The prediction was made before that
boundary was drawn; this is what it looks like from the other side.

The reasoning behind each is unchanged and still applies to the Blade half: Vue
compiles its templates, so an unbalanced tag there is a build failure, and an
Alpine expression can still be truncated by a quote inside its own attribute.

**Guards that re-point at `.vue`, keeping `.blade.php` for the public side
(12).** `UnusedCssClassTest`, `ModifierClassGuardTest`, `InlineStyleGuardTest`,
`PlayerLinkCoverageTest`, `MonogramCoverageTest`, `MonogramTest`,
`LinkedRowTest`, `DialogDisplayGuardTest`, `DashboardGutterTest`,
`FormActionAlignmentTest`, `VenueStatRowsTest`, `ConvertedViewsTest`.

**Done 2026-09-20, and earlier than this section imagined.** Seven of them
sweep a directory and now read both halves through
`Tests\Concerns\ScansTemplates`; the rest name screens, and an entry moved
from `views/` to `js/Pages/` as each page converted. Doing it while pages were
still converting mattered: a guard pointed at the shrinking half passes more
easily every week, and these went un-re-pointed for three plans.

Two faults surfaced from the move itself, neither predicted here. A class named
in a COMMENT counted as used, so the dead-CSS guard went quiet about the class
whose last real use had just been deleted. And `ComponentParityTest` read
`route('tournaments.register')` as a class list, because it splits quoted
strings on `.` and `register` is a declared class.

`ConvertedViewsTest` deserves a note. It is the Tailwind ledger: a view not on
its list must contain no Tailwind, and a view on the list must still contain
some, so the list cannot rot. Extending its haystack to `.vue` carries that rule
into the new templates -- a Vue component reaching for a utility class fails the
suite exactly as a Blade view would.

**Guards that need no change at all (2 of the sixteen, plus the route-walkers).**
`EmptyStateSmokeTest` walks routes rather than files, despite naming views in
its comments. `LinkUnderlineTest` reads the stylesheets and
`views/auth/login.blade.php`, which stays Blade.

Beyond those sixteen, the guards that read routes or stylesheets rather than
templates are unaffected: `RouteSmokeTest`, `NestedAnchorTest`,
`DuplicateElementIdTest`, `VenuePointsPrivacyTest`, `AdminAccessTest`,
`TournamentShowQueryShapeTest`, and `TokenContrastTest`, which parses the token
file itself.

`NestedAnchorTest` and `DuplicateElementIdTest` keep working against Inertia
pages only if those pages return HTML to a normal request. They do -- an Inertia
response to a non-XHR request is a full HTML document -- but this must be
verified on the first converted page rather than assumed.

## 7. Sequencing

Incremental and deployable throughout. Inertia routes and Blade routes coexist
for the whole project, so the branch merges often and the application is never
broken for longer than one page's conversion.

1. **Foundation.** Inertia server and client adapters, `HandleInertiaRequests`,
   `AppLayout.vue`, the `typecheck` script and its CI gate, and the parity guard
   from section 2 and the prop-drift guard from section 5 -- both written before
   there is anything to guard, so they are never retrofitted. `/profile`
   converts last in this step, as the thinnest real page that proves the stack
   end to end.
2. **Shared components.** The eleven twins from section 2, plus the app-only
   components the pages need.
3. **Pages, simplest first.** The index pages, then create and edit forms, then
   detail pages. `poker/tournaments/show` is 499 lines of interlocking state and
   converts last.
4. **Guard re-homing**, as each rule loses its last Blade target.
5. **Removal.** Alpine and the app side's `confirm.ts` usage go once no Vue page
   depends on them.

## 8. Constraints this must honour

- **The venue-points privacy rule.** A player's venue points are readable only
  by that player and by admins. Inertia props are the view data, so the rule
  applies exactly as it does today: the figure is withheld from the props, not
  hidden in the template. `VenuePointsPrivacyTest` walks routes and will catch a
  breach, but the rule is the reason, not the test.
- **The publish lock**, the registration window, and every other server-side
  rule are untouched. No controller logic changes in this project; only what a
  controller returns.
- **Every page keeps working at 375, 960 and 1440**, verified by measurement in
  a browser, which is this project's standard and is a real part of the cost.
- **`recaptcha:check`, `mail:check` and `bin/audit.sh` keep gating the deploy.**

## 9. Effort

Six to eight weeks for one developer working to this project's verification
standard.

The saving against a full conversion (seven to ten weeks) is smaller than the
route count suggests. Pages drop from 47 to 29, but the component work, the
cross-cutting machinery and the test porting do not shrink proportionally --
and the hybrid adds the eleven duplicated components, which a full conversion
would not have.

## 10. Risks

- **The dual component set drifting.** Mitigated for class hooks by the parity
  guard, unmitigated for behaviour. This is the risk most likely to produce a
  confusing bug months from now.
- **`poker/tournaments/show`.** 499 lines, the register-players picker's
  `{ q, chosen }` state, the standings panel, the publish flow and 29-dialog
  confirmation machinery all meet here. It is the page most likely to overrun.
- **Verification cost.** Twenty-nine pages, each measured at three widths in two
  themes, is a large share of the estimate and the easiest thing to quietly skip
  when the project is running long.
- **Bundle size.** The app currently ships 49 kB of JavaScript for every page.
  Vue plus Inertia plus 29 page components will be several times that. It will
  not matter on a desktop and may matter on a phone at a bar.

## 11. Deliberately out of scope

- Converting the public pages or the auth screens.
- Moving off DreamHost, and therefore SSR.
- Replacing the CSS design system with a Vue component library.
- Introducing `spatie/laravel-data` or generated TypeScript types.
- Any change to controller logic, models, migrations or the deploy pipeline
  beyond adding the `typecheck` gate.
- Vitest or any second test runner. Component behaviour is asserted through the
  existing PHPUnit suite and through browser measurement, as it is today.
