# Sponsor management — exit audit

**2026-09-01.** Sponsors are a managed resource: added under Setup with an
uploaded logo, an optional website link and a tier. Premium sponsors render
larger and first on the home page.

**Spec:** `docs/superpowers/specs/2026-09-01-sponsor-management-design.md`
**Plan:** `docs/superpowers/plans/2026-09-01-sponsor-management.md`

## Result

| | |
|---|---|
| Suite | **196 passed** (was 178 when this started) |
| New tests | 20 |
| CSS bundle | 48.96 kB (8.03 kB gzipped) |

## Verified by hand, not only by assertion

- A logo uploaded through the real form is stored on the public disk and
  **returns HTTP 200** at its URL — the thing the storage-link fix was for
- Deleting a sponsor through the UI removes its file: three uploads, three
  deletions, **zero orphans left on disk**
- The external link renders as
  `<a class="p-sponsor p-raised p-lift p-sponsor--premium" href="https://…">`
- With no sponsors the wall is **absent** from the home page, and the admin
  screen explains the consequence rather than just saying "empty"
- Premium spans two columns with a taller logo, in both themes

## Three defects found by looking rather than by testing

### 1. `Storage::disk('public')->url()` hardcodes a host

It builds URLs from `APP_URL`, so an in-page image carries a fixed host. Any
mismatch — a dev port, a staging domain, a proxy, http versus https — gives a
**broken image and no error**. The tests passed throughout, because they only
asserted the path portion; a screenshot caught it.

`asset()` resolves against the actual request host instead. That is why avatars
have always worked, and `User::profileImageUrl()` already did it that way, so
this is now consistent rather than novel.

### 2. `public/storage` never existed

Anything on the public disk was unreachable over HTTP. It predates this feature:
`ProfileController` has written avatars there all along, and the gap went
unnoticed only because no user has ever uploaded one.

The link is gitignored, so it cannot be committed — and `composer setup` did not
create it, meaning **every fresh clone had this bug**. The setup script now runs
`storage:link`, and a test covers a *stale* link as well as a missing one, since
one pointing elsewhere fails identically and looks fine in a directory listing.

### 3. Two tests that passed for the wrong reason

Both were written by me, and both looked like protection they were not:

- **Ordering.** `orderBy('tier')` passes the main ordering test, because
  `'premium'` precedes `'regular'` alphabetically by luck. Verified by swapping
  the implementation. A second test now pins the behaviour that actually
  differs: an unrecognised tier groups with the non-premium sponsors instead of
  sorting by its own name — which matters precisely because `tier` is a string
  so a third one can be added.
- **The empty wall.** `assertDontSee('Proudly Supported By')` could never fail:
  `x-p-hero`'s `highlight` prop wraps part of the heading in a span, so the
  literal words never appear contiguously in the markup. It asserts on the
  `p-sponsors` grid class now.

## Decisions worth not re-litigating

- **`tier` is a string, not `is_premium`.** A boolean cannot grow into a third
  tier, and a league adding "Founding" or "Venue partner" is not far-fetched.
- **`sort_order` exists** although the brief did not ask for it. Without it the
  wall is ordered by insertion, so moving a sponsor means deleting and re-adding
  — and since the logo is required, re-uploading the artwork.
- **`ordered()` is the only expression of display order.** The admin list and
  the public wall both call it, so what an administrator arranges is what the
  page renders.
- **The old logo is deleted *after* the replacement is stored.** The other order
  leaves a sponsor with no logo if the upload then fails.
- **`object-fit: contain`, never `cover`.** Cropping a paying sponsor's mark to
  fit a box is not a thing to do to them.
- **The logo plate is a fixed white, not themed.** Logos are usually transparent
  PNGs in the business's own dark ink, and a dark transparent logo on a dark
  surface is an empty rectangle — the one case where the theme must not win.
- **The card is an anchor only when there is a website.** An anchor with no
  `href` is not a link, and a card that looks clickable and is not is worse than
  a plain one.
- **`alt` carries the sponsor name.** The logo is the content here, not
  decoration; empty alt would leave a screen reader with nothing where a
  sponsor should be.

## Open

1. **The wall is empty.** The five invented sponsors are gone and the three used
   to test this were synthetic, so they were removed along with their files. Add
   real ones under Setup → Sponsors.
2. **Review the logo plate against real artwork.** In dark mode the white plate
   is prominent; the test images were thin outlines, so a real logo will fill
   more of it. Easy to soften if it still reads heavy.
3. **SVG uploads are accepted.** An SVG is a document that can carry script, and
   it is served from this app's own origin. Only administrators can upload, and
   they can already do more damage elsewhere — but if sponsor uploads ever open
   to non-administrators, drop the format or serve the files from another domain.
   The reason is recorded in `SponsorController`.
4. The items in `docs/PLAYER-APPROVAL-AUDIT.md` and
   `docs/RED-BLACK-EXIT-AUDIT.md`, unchanged — most consequentially, **`MAIL_MAILER`
   is still `log`**.
