# Sponsor management — design

**2026-09-01.** Sponsors become a managed resource: an administrator adds them
under Setup, uploads a logo, optionally links a website, and picks a tier.
Premium sponsors render larger and first on the home page.

## Why

The home page's sponsor wall is a hardcoded PHP array in `home.blade.php:113`
with five invented businesses, three emoji and two card marks standing in for
logos. Adding a real sponsor currently means editing a Blade template and
deploying.

That matters more than it would for most placeholder content, because the
sponsorship pitch on the about page makes the wall a *deliverable*: "your logo
goes on the posters in every partner venue, and on this site." The site cannot
currently honour the second half of that without a code change.

## Decisions taken

By the owner on 2026-09-01:

1. **Premium sponsors get a bigger card in the same wall**, sorted first — not a
   separate labelled section. The hierarchy reads as importance rather than as
   two lists, and it collapses to one column on a phone with no special case.
2. **The sector line is dropped.** Name and logo only.
3. **The logo is required.** A wall of logos with one text card among them looks
   broken, and consistency is the point of a sponsor wall.

## 1 · The model

A new `sponsors` table:

| column | type | notes |
|---|---|---|
| `id` | ULID | matching every other table in this app |
| `name` | string | required |
| `logo_path` | string | required; relative path on the `public` disk |
| `website_url` | string, nullable | when present, the card links out |
| `tier` | string | `'premium'` or `'regular'`, default `'regular'`, indexed |
| `sort_order` | integer, default 0 | ordering *within* a tier |
| timestamps | | |

**`sort_order` is not in the brief, and is included deliberately.** Without it
the wall is ordered by insertion, so the only way to move a sponsor is to delete
and re-add it — which for a required-logo resource means re-uploading the
artwork. One integer avoids that. It is not exposed as drag-and-drop; it is a
number on the form.

**`tier` is a string, not a boolean `is_premium`.** The brief names two tiers,
but a third ("Founding", "Venue partner") is the kind of thing a league adds,
and a boolean cannot grow. A string column with two known values can.

### Model surface

```php
public function scopeOrdered($query);   // premium first, then sort_order, then name
public function isPremium(): bool;
public function logoUrl(): string;      // Storage::disk('public')->url($this->logo_path)
```

`scopeOrdered()` is the single expression of the display order, so the home page
and the admin index cannot disagree about it.

## 2 · The blocker this feature has to fix first

**`public/storage` does not exist.** The symlink was never created, so anything
written to the `public` disk is unreachable over HTTP — every uploaded logo
would 404.

This is not new. `ProfileController` has stored avatars to that disk since
before this work, and the only reason nobody has hit it is that no user has ever
uploaded one. Sponsor logos would hit it immediately.

Two things follow:

- `php artisan storage:link` must run as part of setup, and belongs in the
  documented setup steps rather than in someone's memory.
- A test must assert the link exists, because its absence is invisible until a
  user uploads something and then sees a broken image rather than an error.

## 3 · Uploads

Following `ProfileController`'s established pattern: `store('sponsor-logos',
'public')`, replacing and **deleting the previous file** on update, and deleting
the file when the sponsor is deleted.

Validation: `image`, `mimes:png,jpg,jpeg,webp,svg`, `max:2048` (2MB).

**SVG is accepted with a caveat worth writing down.** An SVG is a document, not
a raster, and can carry script. These are uploaded only by administrators, who
can already do far more damage through the rest of the admin area, so the risk
is not meaningfully increased — but the file is served from the app's own origin,
so a hostile SVG would run there. If sponsor uploads are ever opened to
non-administrators, SVG must be dropped from that list or the files served from
a separate domain.

## 4 · The public wall

`.p-sponsors` is already an auto-fit grid at `minmax(min(11rem, 100%), 1fr)`.
Premium cards take `grid-column: span 2` above the point where two columns
exist, and a larger logo. Below that breakpoint everything is one column and the
span is a no-op, which is why this option needed no mobile special-casing.

- Order comes from `scopeOrdered()`.
- A sponsor with a `website_url` wraps its logo and name in an external link:
  `rel="noopener noreferrer"`, and an accessible name that says the link leaves
  the site.
- The logo carries the sponsor's name as its `alt`, not empty alt — the logo *is*
  the content here, not decoration.
- **If there are no sponsors, the section does not render.** A "Proudly
  Supported By" heading above an empty grid advertises that nobody sponsors the
  league.

## 5 · Admin

A seventh resource under Setup, following the six that exist: index with the
standardised header action, create, edit, delete. `EnsureUserIsAdmin` via the
existing admin route group.

The nav gains **Sponsors** under Setup, beside Points structure and Players.

## 6 · What proves it

- the storage symlink exists
- a logo uploads, is stored on the public disk, and is reachable at its URL
- updating with a new logo deletes the old file
- deleting a sponsor deletes its file
- a sponsor cannot be created without a logo
- an oversized or non-image file is rejected
- `scopeOrdered()` puts premium first, then `sort_order`, then name
- the home page renders sponsors from the database, premium ones first
- a sponsor with a website renders an external link; one without renders no link
- the section is absent entirely when there are no sponsors
- a player gets 403 from every sponsor admin route

## 7 · Risks

- **The hardcoded array disappears.** The five invented sponsors on the home page
  vanish the moment this ships, and the wall will be empty until real ones are
  added. That is correct — inventing sponsors on a live site is worse than
  showing none — but it is a visible change and should not be a surprise.
- **No test pins the sponsor names**, checked: `ContentPreservationTest`'s only
  match on "Full House" is in the poker hand-ranking list on the rules page, not
  the sponsor wall. So nothing will catch the wall silently emptying — which
  makes the test for "renders sponsors from the database" load-bearing rather
  than decorative.
- **Deleting a sponsor deletes an uploaded file.** Irreversible, and the
  confirmation must say so.

## 8 · Out of scope

- Drag-and-drop reordering. `sort_order` is a number on the form.
- Sponsor logos anywhere but the home page.
- Tracking clicks on sponsor links.
- Tiers beyond premium and regular.
