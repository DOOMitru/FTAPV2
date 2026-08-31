# Phase 2 — the public pages

Spec: `docs/superpowers/specs/2026-08-30-design-system-design.md`, §4 (phase table) and
**§4.1 (two visual registers, 2026-08-31)**.
Prerequisite: Phase 1 complete and committed — see `docs/PHASE-1-EXIT-AUDIT.md`.
Baseline suite: **98 passed, 0 failed.** Any failure during this phase is real.

## What this phase converts

Eight views, ~1,240 lines, ~2,400 Tailwind utility instances.

| View | Lines | TW classes | Data it consumes |
|---|---|---|---|
| `home` | 264 | 523 | `$currentSeason`, `$nextTournament` |
| `events` | 152 | 335 | `$upcomingTournaments`, `$pastTournaments` |
| `rules/betting` | 153 | 318 | `$bettingRules`, `$conductRules`, `$penalties` |
| `rules/tournament` | 153 | 313 | `$generalRules`, `$seasonRules`, `$finalRules` |
| `about/index` | 123 | 247 | contact form |
| `rules/points-structure` | 141 | 243 | `$pointsStructure`, `$currentSeason`, `$topPerformers` |
| `contact` | 116 | 242 | contact form |
| `rules/texas-holdem` | 138 | 176 | none (all inline `@php`) |

Three of them post forms: `contact` and `about/index` both to `contact.store`, `events` to
`tournaments.register`. Phase 0 established that the contact form uses a **`topic`** field,
not `type` — the spec is the outlier there. Do not "fix" it.

## Global constraints

1. **Never run git commands.** The owner runs every git operation manually, including
   read-only `git show` / `git log` / `git diff`. Three agents breached this in Phase 0 and
   one in Phase 1. To see what a task changed, use `find resources -newer <file>`.
2. **No inline CSS.** `InlineStyleGuardTest` enforces both forms — the `style` attribute
   (custom properties only) and `<style>` blocks. It is not advisory; it fails the build.
3. **Gradients and elevation are public-only.** `PublicRegisterTest` fences
   `--gradient-*`, `--shadow-raised`, `--shadow-float` and `--radius-lg` to
   `resources/css/5-public/`, the public shell and `4-pages/`. New public CSS goes in
   `5-public/`, not `3-components/`, the moment it touches those tokens.
4. **`@import` before `@tailwind` in `app.css`.** `postcss-import` silently drops any
   `@import` that follows another statement. Green build, missing stylesheet, no error.
5. **Tailwind still loads *after* the design system.** On a selector both target at equal
   specificity, the Tailwind utility wins. So when you put a design-system class on an
   element, **remove the Tailwind classes from that same element in the same edit.**
   Leaving both is how you get a class that appears applied and does nothing.
6. **One accent.** The pages currently use indigo, amber, rose, emerald, cyan and slate as
   if they were a palette. They are not. Everything routes through `--c-primary` and
   `--c-accent`; the gradients in §4.1 are the only licence.

## The safety net

| Test | What it guards here |
|---|---|
| `RouteSmokeTest` | Every public route renders for guest, player and admin; no 5xx; no literal Blade artifact leaking into output. |
| `EmptyStateSmokeTest` | `events` and `rules/points-structure` have `@forelse` branches that only fire with an empty database. |
| `InlineStyleGuardTest` | No inline CSS creeps back in while eight pages are rewritten. |
| `PublicRegisterTest` | The gradient licence does not leak into the dashboard. |
| `ContentPreservationTest` | **Extended by Task 1** — the rules pages carry real league rules as content. A conversion must not drop a rule. |

---

## Task 1: The public vocabulary, and a content test that can catch a dropped rule

Eight pages share six blocks. Build them once, or eight pages invent eight heroes. Nothing
converts until this exists.

**Files:**
- Create: `resources/views/components/p-hero.blade.php`, `p-section-head.blade.php`,
  `p-item.blade.php`, `p-chip.blade.php`
- Modify: `resources/css/5-public/_register.css`, `resources/css/app.css`
- Modify: `tests/Feature/ContentPreservationTest.php`

**Interfaces:**
- Consumes: `.p-panel`, `.p-wash`, `.p-raised`, `.p-lift` and the §4.1 tokens
- Produces: the component set every later task in this phase consumes

- [ ] **Step 1: The six blocks, taken from what the pages actually repeat**

| Block | Appears in | Shape |
|---|---|---|
| `<x-p-hero>` | all 8 | eyebrow, title (with an accent-coloured span), lede; centred |
| `<x-p-section-head>` | rules ×4, home, events | icon tile + uppercase heading |
| `<x-p-item>` | rules ×4 | a number, a title, a body — 21 of them on `texas-holdem` alone |
| `<x-p-chip>` | `texas-holdem`, `points-structure` | small centred tile in a grid |
| `.p-panel__glow` | 5 pages | the offset blurred disc inside a gradient panel |
| `.p-rule` | rules ×4 | the fading divider that closes each page |

`<x-p-hero>` takes `eyebrow`, `title`, and an optional `highlight` prop for the accented
word, so the accent is a system decision rather than a `<span class="text-indigo-600">`
copied eight times.

- [ ] **Step 2: Prove them on a scratch page before any real view depends on them**

Render all six in both themes at 375px and 1280px. Check the accent panel's ink is
`--gradient-accent-ink` and not white — that is the AA trap §4.1 documents.

- [ ] **Step 3: Extend `ContentPreservationTest` to cover the rules pages**

This is the real risk of the phase. `texas-holdem` holds 21 numbered rules in an inline
`@php` array; `betting` and `tournament` pull theirs from the controller. A rewrite that
drops one, or drops the ordering, breaks nothing a smoke test can see.

Assert on **data, never markup**, as the existing methods do: rule numbers `01`–`21` and a
sample of rule titles on `texas-holdem`; counts of `$bettingRules` / `$conductRules` /
`$penalties` on `betting`; the season name and the top three point values on
`points-structure`; the upcoming and past tournament names on `events`.

Add the empty-state assertions too, for the reason recorded under Task 4: with an empty
database, `events` and `rules/points-structure` must still render their `@empty` copy, and
that copy must be asserted on by text. Today nothing checks it.

Run it against the current, unconverted pages first — **it must pass before anything is
rewritten**, or it is testing the rewrite rather than guarding it.

- [ ] **Step 4: Checkpoint — hand off for commit**

Stage: `resources/views/components/p-*.blade.php resources/css/ tests/`
Message: `feat(design): public page vocabulary, and content tests for the rules pages`

---

## Task 2: `rules/texas-holdem` — the gate

The smallest page (176 utility instances) and the one that exercises the most vocabulary:
hero, five section heads, 21 numbered items, an accent panel with a glow, a 10-cell chip
grid, and the closing rule. If the public register works here it works everywhere.

**Files:**
- Modify: `resources/views/rules/texas-holdem.blade.php`
- Modify: `resources/css/5-public/_register.css` (only if a block is genuinely missing)

- [ ] **Step 1: Convert**

Keep the `@php` rule arrays exactly as they are — they are content. Replace only markup.
The nested double-card at lines 83–84 (a bordered box inside a bordered box) collapses to
one `.p-raised`; that nesting existed to fake elevation with borders, which is no longer
necessary in this register.

- [ ] **Step 2: Verify**

`php artisan test` — all green, `ContentPreservationTest` included.
Both themes, 375px and 1280px, no horizontal scroll.
`grep -cE 'bg-|text-gray|dark:|rounded-|px-[0-9]' resources/views/rules/texas-holdem.blade.php`
— expected 0.

- [ ] **Step 3: STOP. Owner review.**

**This is a gate, not a checkpoint.** Present the page in both themes before the other
seven follow its pattern. Rejecting the register here costs one page; rejecting it at Task 7
costs eight. The Phase 1 equivalent (Task 10) earned its cost.

---

## Task 3: `rules/tournament` and `rules/betting`

Same shape as Task 2 — hero, section heads, numbered items — but the rules come from the
controller rather than an inline array, and `betting` adds a penalties table.

**Files:** both views. No new CSS expected; if a block is missing, that is a signal Task 1
under-built, so add it to `5-public/` rather than inline to one page.

`ContentPreservationTest` must still pass on the controller-supplied counts.

---

## Task 4: `rules/points-structure`

The only rules page with live data: `$pointsStructure`, `$currentSeason`, `$topPerformers`.

**Watch for:** this is the page Phase 0's `is_active` → `is_current` fix unblocked. The
points table itself should use `<x-table>`, which already exists and is currently used by
exactly one view.

> **Pre-flight correction.** An earlier draft of this plan said `EmptyStateSmokeTest`
> covers this page's `@empty` arm. It does not. `events` and `rules/points-structure` are
> only reached by the *parameterless sweep*, which asserts no 5xx and no leaked Blade
> artifact on an empty database. A conversion that deleted the `@empty` arm entirely would
> render an empty page, return 200, and pass. **Task 1 must add an assertion on the
> empty-state copy itself** for both pages, or these two `@empty` arms are unguarded for
> the whole phase.

---

## Task 5: `about/index` and `contact`

Both already call `<x-section-badge>`, and both post to `contact.store`.

**The forms are the work here.** Convert them to `<x-field>`, which brings the label,
control, hint and error markup with it. Keep the **`topic`** field name exactly as it is —
Phase 0 verified that the controller, mailable, both views and the tests agree on `topic`,
and the spec is the outlier.

> **The honeypot is the field named `company`** (`contact.blade.php:71`), and
> `ContactController:16` silently drops any submission where it is filled. It is an
> unlabelled text input that looks exactly like dead markup, and `<x-field>` would want to
> give it a label. **Do not convert it and do not remove it.** It must stay unlabelled,
> visually hidden and outside the `<x-field>` treatment. Nothing in the suite fails if it
> disappears; the only symptom is spam, weeks later.

Validation errors must be checked on a real failed submission in both themes: this is the
path where `input-error` was found at 3.4:1 during Phase 1.

---

## Task 6: `events`

Data-driven: `$upcomingTournaments`, `$pastTournaments`, and an inline registration form
posting to `tournaments.register`.

**Watch for:** Phase 0 established that `registration_open` is an accessor and is **not**
the same as "play has started" (`$isPast`, derived from `start_time`). There is a real
window where registration is closed but play has not begun. Whatever the current view does
with those two conditions, preserve it exactly — this is behaviour, not styling.

Both `@forelse` empty branches need the assertion described in Task 4 — the existing
sweep would not notice if they vanished.

---

## Task 7: `home`

Last, and largest — 523 utility instances across six distinct sections: a split hero with
`images/hero_logo.png`, a two-item feature list, three season cards (one of which is a dark
inverted panel), a five-logo sponsor grid, and a sponsor CTA.

By this point every block should already exist. **If `home` needs something new, that is
worth pausing on** — it means the vocabulary was drawn from the rules pages alone and the
marketing page has different needs.

`$currentSeason` and `$nextTournament` can both be null. The current page handles that;
check the null arms still render after conversion.

---

## Task 8: Sweep and lock

- [ ] **Step 1: Assert the eight are clean**

```bash
for f in home events contact about/index rules/texas-holdem rules/tournament \
         rules/betting rules/points-structure; do
  printf '%-26s %s\n' "$f" "$(grep -cE 'class="[^"]*(bg-(white|gray|indigo|amber|rose|emerald|cyan|slate)|text-(gray|indigo|xs|sm|lg|xl)|dark:|rounded-(md|lg|xl|2xl|3xl)|px-[0-9]|shadow-)' resources/views/$f.blade.php)"
done
```
Expected: all zero.

- [ ] **Step 2: Add that sweep as a test**

A grep in a checklist gets skipped; Phase 1 Task 11 made that argument and it held. Extend
`InlineStyleGuardTest` or add `ConvertedViewsTest` with an explicit allowlist of views that
are permitted to still contain Tailwind — Phase 3 shrinks the list, Phase 4 shrinks it
again, and Phase 5 empties it. That turns "50 views left" from a number in a doc into a
number the suite enforces.

- [ ] **Step 3: Full audit**

`php artisan test`, `npm run build`, all eight pages at 375px and 1280px in both themes,
reduced motion, and a theme-toggle pass on a public page.

- [ ] **Step 4: Checkpoint — hand off for commit, then update `docs/RESUME-HERE.md`**

## Phase 2 exit criteria

- [ ] `php artisan test` all green, including the extended `ContentPreservationTest`.
- [ ] `npm run build` succeeds.
- [ ] None of the eight views contains a Tailwind utility class.
- [ ] `ConvertedViewsTest`'s allowlist no longer names any Phase 2 view.
- [ ] No page scrolls horizontally at 375px.
- [ ] Both themes verified on all eight.
- [ ] `PublicRegisterTest` still passes — no gradient token has leaked into the dashboard.
- [ ] The owner has seen and accepted the public register at the Task 2 gate.
