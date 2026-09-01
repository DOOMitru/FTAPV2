# Red and Black Aesthetic Refresh — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the whole application off its cyan/navy palette onto the logo's red-and-black, add a motif and semantic colour layer that carry meaning, and rewrite the public copy to lead on what the league actually is.

**Architecture:** Nothing structural changes. The two-register split (dashboard = flat/hairline, public = gradient/elevation), the five guard tests, the component vocabulary and the three shells all stay exactly as built. This plan changes token *values*, seven rules that fill with the wrong token, four component semantics, twelve gradient rules and eight pages of copy. Because there are zero hardcoded colours outside `_tokens.css`, Task 1 alone recolours the entire app.

**Tech Stack:** Laravel 12 · PHP 8.5 · Blade · plain CSS with custom properties · `postcss-import` + `autoprefixer` · Vite 7 · PHPUnit 11 · headless Chromium for visual verification

**Spec:** `docs/superpowers/specs/2026-08-31-red-black-redesign-design.md`

## Global Constraints

- **NEVER RUN GIT COMMANDS.** Not `commit`, not `add`, and not read-only `show`/`log`/`diff`. The repository owner runs every git operation by hand. Every "commit" step in this plan is a **hand-off**: state the files and the message, then stop and wait. To see what changed since the last commit use `find . -newer .git/COMMIT_EDITMSG -type f -not -path './node_modules/*' -not -path './vendor/*'`.
- **No inline CSS.** The only permitted `style` attribute sets custom properties only (`style="--meter-fill: 86%"`). Enforced by `InlineStyleGuardTest`, which also rejects `<style>` blocks.
- **No inline JavaScript.** There is none left in the app; do not reintroduce any. Values reach JS through `data-` attributes read via `dataset`.
- **All colour is tokens.** No hex literal may appear in any file under `resources/css/` except `1-base/_tokens.css`. Verify with `grep -rn "#[0-9A-Fa-f]\{6\}" resources/css --include='*.css' | grep -v "1-base/_tokens.css" | grep -v "\*"`.
- **Contrast floors.** Normal text 4.5:1. Large text (≥24px, or ≥18.66px bold) 3:1. Non-text (icons, borders, focus rings) 3:1. Hairlines are exempt from 3:1 but must clear **1.4:1** against every surface they divide — a hairline at 1.1:1 is mathematically present and optically absent, a trap this project has hit four times.
- **Gradients need two measurements**, never one: ink against each stop, **and** stop against stop. A pair less than 1.5:1 apart renders as a flat fill however well it scores against its ink.
- **Screenshot before declaring anything done.** Every colour defect this project has shipped passed every assertion that existed at the time. A green suite is necessary and not sufficient.
- **Do not rename any `--gradient-*` token.** `PublicRegisterTest` pins their names. Re-hueing values is free; renaming breaks the fence.
- **Both themes, every time.** Light is the harder one: the logo red fails on white, so light is the approximation.

## Verification commands

```bash
php artisan test                       # full suite — 108 passing at plan time
npm run build                          # must succeed; watch the CSS byte count
php artisan serve --port=8899          # then screenshot against 127.0.0.1:8899
```

**Screenshots.** Snap-confined Chromium cannot read or write `/tmp` or any
dot-directory. Use a plain directory under `$HOME`:

```bash
mkdir -p "$HOME/ftap-shots"
/snap/bin/chromium --headless --disable-gpu --no-sandbox --hide-scrollbars \
  --window-size=1440,1400 --virtual-time-budget=5000 \
  --screenshot="$HOME/ftap-shots/home.png" "http://127.0.0.1:8899/"
```

Headless Chromium renders the **dark** theme by default here and ignores
`--force-dark-mode`; the theme comes from `localStorage` via
`theme-script.blade.php`. To capture light, temporarily stamp
`data-theme="light"` on `<html>` in the layout, screenshot, then revert — and
revert before any hand-off.

## File structure

| File | Responsibility | Task |
|---|---|---|
| `resources/css/1-base/_tokens.css` | Every colour value in the app. The only file with hex literals. | 1, 3 |
| `tests/Feature/TokenContrastTest.php` | **New.** Parses the token file and asserts every AA pair, so the spec's numbers become enforced invariants rather than a one-off script. | 1 |
| `resources/css/3-components/_btn.css` | `.btn--primary` fill token; `.btn--danger` quiet-destructive states. | 1, 2 |
| `resources/css/3-components/_nav.css` | `.nav-link--current` fill token. | 1 |
| `resources/css/3-components/_pagination.css` | Current-page fill token. | 1 |
| `resources/css/3-components/_rows.css` | `.podium__place--1` fill token; podium medal colours. | 1, 2 |
| `resources/css/3-components/_chip-token.css` | Selected-chip fill token. | 1 |
| `resources/css/3-components/_section-badge.css` | Icon-disc fill token. | 1 |
| `resources/css/3-components/_rank.css` | Medal modifiers for 1st/2nd/3rd. | 2 |
| `resources/views/components/rank.blade.php` | Emits the per-place medal modifier. | 2 |
| `resources/css/3-components/_badge.css` | New `.badge--open` variant. | 2 |
| `resources/css/3-components/_alert.css`, `_form.css` | Keep red-as-alarm legible now red is also the brand. | 2 |
| `resources/css/5-public/_register.css` | Gradients, hero watermark, suit eyebrows, icon tile. | 3 |
| `resources/views/home.blade.php`, `events.blade.php`, `contact.blade.php`, `about/index.blade.php`, `rules/*.blade.php` | Public copy. | 4 |
| `tests/Feature/ContentPreservationTest.php` | Two pinned empty-state strings move with the copy. | 4 |

---

### Task 1: Palette

Rewrite every colour token, add the contrast test that locks them, and fix the
seven rules that fill with the wrong token.

**Files:**
- Modify: `resources/css/1-base/_tokens.css` (the `:root` colour block and both dark mappings)
- Create: `tests/Feature/TokenContrastTest.php`
- Modify: `resources/css/3-components/_btn.css:38,43`
- Modify: `resources/css/3-components/_nav.css:33,43`
- Modify: `resources/css/3-components/_pagination.css:64`
- Modify: `resources/css/3-components/_rows.css:180`
- Modify: `resources/css/3-components/_chip-token.css:52`
- Modify: `resources/css/3-components/_section-badge.css:30`

**Interfaces:**
- Produces: tokens `--c-primary`, `--c-primary-hover`, `--c-primary-fill`, `--c-primary-fill-hover`, `--c-primary-ink`, `--c-open`, `--c-gold`, `--c-silver`, `--c-bronze`, `--c-medal-ink`, and their `--dark-*` twins. Tasks 2 and 3 consume these by name.
- **Retires** `--c-accent`, `--c-accent-strong`, `--c-accent-hover`, `--c-accent-ink`, `--c-accent-text` and `--gradient-accent`/`--gradient-accent-ink`. With red as the primary there is no second brand hue for them to carry. Every consumer is redirected in Tasks 1–3. `--gradient-accent` and `--gradient-accent-ink` are named in `PublicRegisterTest`, so they must keep **existing as tokens** — Task 3 redefines them as aliases of the panel ramp rather than deleting them.

- [ ] **Step 1: Write the failing contrast test**

This is the deliverable that makes the spec's numbers permanent. Create
`tests/Feature/TokenContrastTest.php`:

```php
<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The spec's contrast figures, as enforced invariants.
 *
 * Every colour defect this project has shipped passed every assertion that
 * existed at the time, because no assertion measured colour. This one does:
 * it reads the real token file, so a hand edit that breaks AA fails the suite
 * instead of reaching a screenshot -- or a user.
 */
class TokenContrastTest extends TestCase
{
    /** Relative luminance, WCAG 2.1 definition. */
    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];
        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Every token as a literal hex, resolved from the real file.
     *
     * Light values live on bare :root; dark values are declared once as
     * --dark-* and mapped twice. Reading the --dark-* declarations gives the
     * dark palette without having to model the cascade.
     */
    private static function tokens(): array
    {
        $css = file_get_contents(resource_path('css/1-base/_tokens.css'));

        // Strip comments first. The token file explains its own values, and
        // those explanations quote hex literals ("white on #EF4537 is only
        // 3.77:1") that must not be read as declarations.
        $css = preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/(--[a-z0-9-]+):\s*(#[0-9A-Fa-f]{6})\s*;/', $css, $m, PREG_SET_ORDER);

        $tokens = [];
        foreach ($m as $match) {
            // First declaration wins: the light palette on bare :root, and the
            // --dark-* block, both of which precede the mapping blocks.
            $tokens[$match[1]] ??= strtoupper($match[2]);
        }

        return $tokens;
    }

    public static function contrastPairs(): array
    {
        // [label, foreground token, background token, minimum ratio]
        return [
            // Light theme — body copy and muted copy on both grounds.
            'light text on bg' => ['--c-text', '--c-bg', 4.5],
            'light text on surface' => ['--c-text', '--c-surface', 4.5],
            'light muted on bg' => ['--c-text-muted', '--c-bg', 4.5],
            'light muted on surface' => ['--c-text-muted', '--c-surface', 4.5],

            // Dark theme.
            'dark text on bg' => ['--dark-text', '--dark-bg', 4.5],
            'dark text on surface' => ['--dark-text', '--dark-surface', 4.5],
            'dark muted on bg' => ['--dark-text-muted', '--dark-bg', 4.5],
            'dark muted on surface' => ['--dark-text-muted', '--dark-surface', 4.5],

            // Brand red as text.
            'light primary on surface' => ['--c-primary', '--c-surface', 4.5],
            'light primary on bg' => ['--c-primary', '--c-bg', 4.5],
            'dark primary on surface' => ['--dark-primary', '--dark-surface', 4.5],
            'dark primary on bg' => ['--dark-primary', '--dark-bg', 4.5],

            // Brand red as a fill carrying a small uppercase label. This is the
            // pair that fails if anyone "simplifies" the fill back to
            // --c-primary: white on #EF4537 is 3.77:1.
            'light fill carries ink' => ['--c-primary-ink', '--c-primary-fill', 4.5],
            'light fill hover carries ink' => ['--c-primary-ink', '--c-primary-fill-hover', 4.5],
            'dark fill carries ink' => ['--dark-primary-ink', '--dark-primary-fill', 4.5],
            'dark fill hover carries ink' => ['--dark-primary-ink', '--dark-primary-fill-hover', 4.5],

            // Felt green, the open/won semantic.
            'light open on surface' => ['--c-open', '--c-surface', 4.5],
            'light open on bg' => ['--c-open', '--c-bg', 4.5],
            'dark open on surface' => ['--dark-open', '--dark-surface', 4.5],
            'dark open on bg' => ['--dark-open', '--dark-bg', 4.5],

            // Medals are discs with ink, never text: no gold clears 4.5:1 on
            // white, and a medal is a disc anyway.
            'gold disc carries ink' => ['--c-medal-ink', '--c-gold', 4.5],
            'silver disc carries ink' => ['--c-medal-ink', '--c-silver', 4.5],
            'bronze disc carries ink' => ['--c-medal-ink', '--c-bronze', 4.5],
        ];
    }

    #[DataProvider('contrastPairs')]
    public function test_token_pair_meets_its_contrast_floor(string $fg, string $bg, float $min): void
    {
        $tokens = self::tokens();

        $this->assertArrayHasKey($fg, $tokens, "Token {$fg} is not defined as a hex literal.");
        $this->assertArrayHasKey($bg, $tokens, "Token {$bg} is not defined as a hex literal.");

        $ratio = self::ratio($tokens[$fg], $tokens[$bg]);

        $this->assertGreaterThanOrEqual(
            $min,
            round($ratio, 2),
            sprintf(
                '%s (%s) on %s (%s) is %.2f:1, below the %.1f:1 floor.',
                $fg, $tokens[$fg], $bg, $tokens[$bg], $ratio, $min
            )
        );
    }

    public static function hairlinePairs(): array
    {
        // A hairline is exempt from 3:1 but must be optically present. Below
        // ~1.4:1 it is mathematically there and invisible -- the exact failure
        // that shipped #1E293B and #E2E8F0 in Phase 1.
        return [
            'light hairline on surface' => ['--c-border', '--c-surface'],
            'light hairline on raised' => ['--c-border', '--c-surface-raised'],
            'dark hairline on surface' => ['--dark-border', '--dark-surface'],
            'dark hairline on raised' => ['--dark-border', '--dark-surface-raised'],
        ];
    }

    #[DataProvider('hairlinePairs')]
    public function test_hairline_is_optically_present(string $border, string $surface): void
    {
        $tokens = self::tokens();
        $ratio = self::ratio($tokens[$border], $tokens[$surface]);

        $this->assertGreaterThanOrEqual(
            1.4,
            round($ratio, 2),
            sprintf('%s on %s is %.2f:1 — present in the maths, absent to the eye.', $border, $surface, $ratio)
        );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=TokenContrastTest`

Expected: **failures**, because `--c-primary-fill`, `--c-open`, `--c-gold`,
`--c-silver`, `--c-bronze` and `--c-medal-ink` do not exist yet. You should see
`Token --c-primary-fill is not defined as a hex literal.`

This is the point of running it now: the test must be proven able to fail before
it is worth anything.

- [ ] **Step 3: Rewrite the light palette in `_tokens.css`**

Replace the light colour declarations on bare `:root`. Keep the file's existing
comment style — it explains *why* a value is what it is, which is the reason the
project has survived this many colour decisions.

```css
    /* Light — the table by day. Warm-neutral, not blue-grey: #F4F7FC was a
       BLUE white, and it fought the logo's red on every page. */
    --c-bg: #F6F4F3;
    --c-surface: #FFFFFF;
    --c-surface-raised: #EFEBE9;
    /* 1.73:1 on white and 1.46:1 on the raised surface. The first draft used
       #D5CDCA, which measured 1.32:1 against raised -- the same optical
       absence this system has now designed out four times. */
    --c-border: #CDC3BF;
    --c-text: #16171B;
    --c-text-muted: #56595F;

    /* Brand. The logo red is #EF4537, sampled at 87.8% of the mark's chromatic
       mass (hue 4.6deg). It is used LITERALLY in dark, where it measures
       4.58:1 as text on the surface. It cannot be used literally here: on
       white it is 3.77:1 and fails AA. #D6291B is the light-theme stand-in at
       5.01:1. */
    --c-primary: #D6291B;
    --c-primary-hover: #B92417;
    /* A fill carrying a small uppercase label needs more than --c-primary can
       give on the dark side, so the fill is its own token. In LIGHT the two
       coincide -- white on #D6291B is 5.01:1, which beats the dark theme's
       fill -- but the token still exists in both themes so no consumer has to
       branch on theme. */
    --c-primary-fill: #D6291B;
    --c-primary-fill-hover: #B02718;
    --c-primary-ink: #FFFFFF;

    /* Felt green: registration open, registered, paid, won. The one semantic
       colour the domain hands us for free. */
    --c-open: #15803D;

    /* Medals. These are DISCS WITH INK, never text -- no gold clears 4.5:1 on
       white (#D4A017 manages 2.38:1), and a medal is a disc anyway. Shared by
       both themes, because the ink they carry is fixed too. */
    --c-gold: #D4A017;
    --c-silver: #A8AAB0;
    --c-bronze: #B87333;
    --c-medal-ink: #16171B;
```

- [ ] **Step 4: Rewrite the dark palette in `_tokens.css`**

Replace the `--dark-*` declarations:

```css
    /* Dark — the table at night. #0B0F19 was a blue black; #0E1014 is neutral,
       which is half the reason the logo red suddenly looks native. */
    --dark-bg: #0E1014;
    --dark-surface: #181B22;
    --dark-surface-raised: #212530;
    /* 1.66:1 on surface, 1.48:1 on raised. #333845 was the first draft and
       measured 1.31:1 on raised. */
    --dark-border: #3A4050;
    --dark-text: #ECEEF2;
    --dark-text-muted: #99A1AF;

    /* The logo red, used exactly: 4.58:1 as text on --dark-surface. */
    --dark-primary: #EF4537;
    --dark-primary-hover: #F4685C;
    /* White on #EF4537 is 3.77:1, so a fill carrying a 13px uppercase label
       must be deeper than the brand red itself. This is the same trap the
       retired --c-accent-strong existed for. */
    --dark-primary-fill: #D63A2C;
    --dark-primary-fill-hover: #B93225;
    --dark-primary-ink: #FFFFFF;

    --dark-open: #2FBF6B;
```

- [ ] **Step 5: Map the new dark tokens in BOTH dark blocks**

`_tokens.css` maps dark values twice — under `@media (prefers-color-scheme: dark)`
guarded as `:root:not([data-theme="light"])`, and under `:root[data-theme="dark"]`.
**Both must be updated identically**, or an explicit theme choice and a system
preference will disagree. Add to each block:

```css
        --c-primary-fill: var(--dark-primary-fill);
        --c-primary-fill-hover: var(--dark-primary-fill-hover);
        --c-open: var(--dark-open);
```

Remove the now-dead `--c-accent-text: var(--dark-accent-text);` line from both,
and delete `--dark-accent-text` from the `--dark-*` block.

- [ ] **Step 6: Run the contrast test until it passes**

Run: `php artisan test --filter=TokenContrastTest`
Expected: **PASS** — 23 contrast pairs and 4 hairline pairs.

If a pair fails, the value is wrong — not the test. Adjust the token.

- [ ] **Step 7: Fix the seven rules that fill with the wrong token**

These were authored against a cyan whose ink was near-black. With a red primary
they become white-on-#EF4537 at 3.77:1. Every one moves to the fill token.

In `_btn.css:37-44`:

```css
/* --c-primary-fill, not --c-primary: a 13px uppercase label is normal-size
   text, and white on the brand red #EF4537 is only 3.77:1. The fill token is
   the deeper red that carries it at 4.67:1. */
.btn--primary {
    background-color: var(--c-primary-fill);
    color: var(--c-primary-ink);
}

.btn--primary:hover {
    background-color: var(--c-primary-fill-hover);
}
```

In `_nav.css:32-45`, keep the existing specificity comment and swap both fills:

```css
.nav-link--current {
    background-color: var(--c-primary-fill);
    color: var(--c-primary-ink);
}

.nav-link--current:hover {
    background-color: var(--c-primary-fill-hover);
    color: var(--c-primary-ink);
}
```

In `_pagination.css:64`, `_chip-token.css:52` and `_section-badge.css:30`, change
`background-color: var(--c-primary);` to `background-color: var(--c-primary-fill);`.

In `_rows.css:180`:

```css
.podium__place--1 .podium__step { height: 3rem; background-color: var(--c-primary-fill); color: var(--c-primary-ink); }
```

- [ ] **Step 8: Redirect the retired accent tokens**

`--c-accent*` no longer exists. Find every consumer and point it at the new
tokens:

Run: `grep -rn "c-accent" resources/css`

Expected consumers and their replacements:
- `_btn.css` `.btn--danger` — leave for now; Task 2 rewrites it wholesale.
- `_badge.css` `.badge--accent` — becomes `border-color: var(--c-primary); color: var(--c-primary);`
- `_alert.css:28` `border-inline-start-color: var(--c-accent);` — becomes `var(--c-primary)`
- `_form.css:46` `color: var(--c-accent-text);` — becomes `var(--c-primary)`

Then delete the `--c-accent`, `--c-accent-strong`, `--c-accent-hover`,
`--c-accent-ink` and `--c-accent-text` declarations from `_tokens.css`.

Leave `--gradient-accent` and `--gradient-accent-ink` **in place and unchanged**
for now — `PublicRegisterTest` pins their names and Task 3 redefines them.

- [ ] **Step 9: Verify nothing references a dead token**

```bash
grep -rn "c-accent" resources/css resources/views && echo "STILL REFERENCED — fix before continuing" || echo "clean"
grep -rn "#[0-9A-Fa-f]\{6\}" resources/css --include='*.css' | grep -v "1-base/_tokens.css" | grep -v "^\s*/\*" | grep -v "\*"
```

Expected: `clean`, and no hex outside the token file.

- [ ] **Step 10: Build and run the whole suite**

```bash
npm run build
php artisan test
```

Expected: build succeeds; **108 tests pass, plus the new TokenContrastTest**.

- [ ] **Step 11: Screenshot the sweep — this is where the real bugs are**

A green suite means nothing here. Start the server and capture, at minimum:
`/`, `/events`, `/rules/tournament`, `/about`, `/contact`, and after logging in,
`/dashboard` and one admin index. **Both themes.**

Look specifically for: focus rings that were tuned to cyan, hover states that now
read as errors, the `theme-toggle` icon, `.meter` fills, and any place a red now
sits beside a validation error.

Record what you find. Fix anything that is wrong *because of* the swap; leave
anything that was already wrong for its own task.

- [ ] **Step 12: HAND-OFF — do not run git**

State the changed files and this message, then stop:

```
feat(design): move the palette to the logo's red and black

The app has been wearing a cyan/navy palette that came from the original
brief rather than the brand. The logo is red #EF4537, charcoal and white;
across the public pages the red-family hues measured 0.0-6.4% of what was
painted against 24-65% blue.

Grounds move to warm-neutral (#0E1014 / #F6F4F3) -- #0B0F19 was a blue
black and it fought the mark. Dark-theme primary is now the logo red
exactly, which clears AA as text at 4.58:1; light uses #D6291B because
#EF4537 on white is 3.77:1 and fails.

Adds --c-primary-fill, because seven rules filled with --c-primary and
inked with --c-primary-ink -- fine at 10.6:1 with cyan, 3.77:1 with red.
Adds felt green for open states and gold/silver/bronze for placements.
Retires the --c-accent family: with red as the primary there is no second
brand hue for it to carry.

TokenContrastTest is new and makes all of this permanent: it parses the
real token file and asserts every pair, so a hand edit that breaks AA
fails the suite instead of reaching a user.
```

---

### Task 2: Component semantics

> **Executed 2026-08-31.** Step 1 (`.btn--danger`) was completed inside Task 1:
> leaving it referencing the retired accent tokens would have handed over a
> commit whose delete button had no background at all. `.link--danger` was also
> restyled there — the plan missed it entirely, and since `.link` is itself
> `--c-primary` now, a red Delete would have rendered identically to the Edit
> link beside it. The remaining steps (medals, `.badge--open`, alarm red) stand.

Four changes that give the new colours their jobs.

**Files:**
- Modify: `resources/css/3-components/_btn.css` (`.btn--danger`)
- Modify: `resources/css/3-components/_rank.css`
- Modify: `resources/views/components/rank.blade.php`
- Modify: `resources/css/3-components/_rows.css` (podium medal colours)
- Modify: `resources/css/3-components/_badge.css` (new `.badge--open`)
- Modify: `resources/views/events.blade.php`, `dashboard.blade.php`, `poker/tournaments/show.blade.php`, `poker/seasons/index.blade.php` (badge call sites)
- Modify: `resources/css/3-components/_alert.css`, `_form.css`

**Interfaces:**
- Consumes: `--c-primary-fill`, `--c-primary-fill-hover`, `--c-open`, `--c-gold`, `--c-silver`, `--c-bronze`, `--c-medal-ink` from Task 1.
- Produces: CSS classes `.btn--danger` (restyled), `.rank--1`, `.rank--2`, `.rank--3`, `.badge--open`. Task 5 screenshots all of them.

- [ ] **Step 1: Restyle `.btn--danger` as quiet-destructive**

Replace `_btn.css:55-64` entirely:

```css
/* Quiet destructive.
 *
 * When red is the BRAND colour it can no longer also mean "danger" by itself:
 * a red Delete beside a red Save is distinguishable only on inspection, and
 * peripheral vision is exactly where a misclick on Delete happens.
 *
 * So a destructive button rests neutral -- visually a ghost -- and commits to
 * red only on hover and focus, at the moment the pointer is actually on it.
 * The rule this buys: a FILLED RED BUTTON ALWAYS MEANS GO, with no exceptions,
 * and exactly one of them rests on any view.
 */
.btn--danger {
    border-color: var(--c-border);
    color: var(--c-text);
}

.btn--danger:hover,
.btn--danger:focus-visible {
    background-color: var(--c-primary-fill);
    border-color: var(--c-primary-fill);
    color: var(--c-primary-ink);
}
```

- [ ] **Step 2: Verify the danger button by eye, both themes**

`.btn--danger` appears on the season, venue, tournament and user edit pages, and
in the delete-account form on `/profile`. Screenshot one, hover it, and confirm
it reads as secondary at rest and unmistakably red on hover.

`.btn:focus-visible` already draws a 2px `--c-primary` outline, so the focus
state gets both the ring and the fill. That is intentional.

- [ ] **Step 3: Give the podium its medals**

`.rank--podium` currently paints 1st, 2nd and 3rd in one identical colour, so a
podium announces no hierarchy at all. Replace `_rank.css:19-22`:

```css
/* A medal is a disc with ink, not coloured text: no gold clears 4.5:1 on white
   (#D4A017 manages 2.38:1), so the colour goes behind the numeral rather than
   into it. That also happens to be what a medal actually is, and it means one
   set of values works in both themes. */
.rank--1,
.rank--2,
.rank--3 {
    border-color: transparent;
    color: var(--c-medal-ink);
    font-weight: 700;
}

.rank--1 { background-color: var(--c-gold); }
.rank--2 { background-color: var(--c-silver); }
.rank--3 { background-color: var(--c-bronze); }
```

- [ ] **Step 4: Emit the per-place modifier**

Replace `resources/views/components/rank.blade.php`:

```blade
@props(['place'])

@php
    // Places 1-3 carry a medal; everything below is the quiet default.
    $medal = $place <= 3 ? ' rank--'.$place : '';
@endphp

<span {{ $attributes->merge(['class' => 'rank'.$medal]) }}>{{ $place }}</span>
```

- [ ] **Step 5: Check for stale `.rank--podium` references**

```bash
grep -rn "rank--podium" resources/
```

Expected: **no results.** If any remain, they are now dead styling — remove them.
`ModifierClassGuardTest` will fail on an `l-*` modifier without its base, but it
does not cover `.rank`, so this grep is the check.

- [ ] **Step 6: Run the suite**

Run: `php artisan test`
Expected: PASS. `ContentPreservationTest` asserts on place numbers as data, not
on markup, so a class change cannot break it.

- [ ] **Step 7: Add the `.badge--open` variant**

Append to `_badge.css`:

```css
/* Felt green, for a state that is OPEN or WON -- registration open, you're
   registered, the current season. Distinct from .badge--primary, which marks
   identity (Admin, Podium Level) rather than state. */
.badge--open {
    border-color: var(--c-open);
    color: var(--c-open);
}
```

- [ ] **Step 8: Move exactly the state badges to green**

`badge--primary` is currently doing two different jobs. Change **only** these
five call sites to `variant="open"`:

- `resources/views/events.blade.php:52` — `Registration Open`
- `resources/views/events.blade.php:97` — `You're registered`
- `resources/views/dashboard.blade.php:48` — `Registered`
- `resources/views/poker/tournaments/show.blade.php:65` — `Registered`
- `resources/views/poker/seasons/index.blade.php:31` — `Current`

**Leave these on `variant="primary"`** — they mark identity, not state:
`users/index.blade.php:37` and `users/show.blade.php:56` (`Admin`), and
`rules/points-structure.blade.php:68` (`Podium Level`).

- [ ] **Step 9: Keep red-as-alarm legible**

Task 1 pointed `_alert.css` and `_form.css` at `--c-primary`. That is now the
brand colour, so an inline validation error reads as emphasis rather than as a
problem. Check both by eye:

- Submit an empty form at `/login` and at `/poker/seasons/create`.
- Trigger an alert (any successful save redirects with one).

If an error no longer reads as an error, the fix is **not** another red. Give the
error state an icon and keep the colour — the form already renders
`x-input-error` beside the field, so the distinguishing signal should be
position and iconography, not hue. Record what you changed.

- [ ] **Step 10: Build, test, screenshot**

```bash
npm run build && php artisan test
```

Then screenshot `/events` (both themes) and confirm: green open badges, medal
discs on the archived-event podiums, and the danger button quiet at rest.

- [ ] **Step 11: HAND-OFF — do not run git**

```
feat(design): give the new colours their jobs

Quiet destructive: .btn--danger rests neutral and commits to red only on
hover and focus. When red is the brand colour it cannot also mean danger by
itself -- a red Delete beside a red Save is distinguishable only on
inspection, and peripheral vision is where the misclick happens. The rule
this buys is that a filled red button always means go.

Medals: .rank--podium painted 1st, 2nd and 3rd in one identical colour, so
a podium announced no hierarchy. Split into gold, silver and bronze discs.
The colour goes behind the numeral because no gold clears 4.5:1 as text on
white -- which is also what a medal actually is.

Adds .badge--open in felt green for state, applied to exactly the five
call sites that mark a state. badge--primary was carrying both state
(Registration Open, Registered) and identity (Admin, Podium Level); the
identity ones keep it.
```

---

### Task 3: Public register

> **Partly executed 2026-08-31.** Steps 2-5 (the four gradient values and the
> `.p-icon-tile` move) were completed inside Task 1. Task 1 alone would have
> shipped red chrome against a still-cyan hero and finale panel — the gradient
> values live in the same file, so keeping them back made the commit incoherent
> rather than smaller. The motif steps (suit eyebrow, hero watermark) stand.
>
> One thing the plan did not anticipate: `PublicRegisterTest` matches raw text
> per line, so defining `--gradient-accent: var(--gradient-panel)` counts as a
> USE of a public token outside `5-public/` — and so does merely naming that
> reference in a comment. The aliases are written as literal values instead.

Re-hue the twelve gradient rules onto red and add the two motif elements.

**Files:**
- Modify: `resources/css/1-base/_tokens.css` (the gradient block)
- Modify: `resources/css/5-public/_register.css` (icon tile, hero watermark, eyebrow)

**Interfaces:**
- Consumes: everything from Tasks 1 and 2.
- Produces: re-hued `--gradient-primary`, `--gradient-panel`, `--gradient-accent` (now an alias), `--gradient-raised`, `--gradient-surface`; and `.p-hero__watermark`, `.p-hero__eyebrow` suit marks.

- [ ] **Step 1: Understand the constraint before touching a value**

`--gradient-primary` currently has **two incompatible jobs**: it is
background-clipped onto the hero title, *and* it fills `.p-icon-tile`
(`_register.css:148`) which carries a white SVG. On the dark side no single ramp
does both — the bright stop a clipped title needs (`#FF7A6E`) carries a white
icon at only 2.54:1.

**Resolution: `.p-icon-tile` moves to `--gradient-panel`.** That frees
`--gradient-primary` to be purely the bright text ramp and removes the conflict
permanently.

- [ ] **Step 2: Rewrite the light gradients**

In `_tokens.css`, replace the light gradient block:

```css
    /* ── The public register ────────────────────────────────────────────────
       The dashboard is borders-not-shadows. The public site is the shop
       window and is allowed gradients and elevation. Every stop derives from
       --c-primary so the two registers read as one product.

       TWO measurements, always: ink against each stop, AND stop against stop.
       A pair less than 1.5:1 apart renders as a flat fill however well it
       scores against its ink -- the exact failure that shipped a 1.14:1 pair
       in Phase 2. */

    /* Clipped onto the hero title only, since .p-icon-tile moved to the panel
       ramp. The title is 40-56px display type, so it needs 3:1 against the
       PAGE GROUND rather than 4.5:1: 3.97:1 and 7.46:1 here, 1.88:1 apart. */
    --gradient-primary: linear-gradient(135deg, #E03B2B 0%, #9C1A0F 100%);

    /* Large fills, and anything carrying white ink -- panels, icon tiles.
       White measures 6.67:1 and 12.14:1; stops sit 1.82:1 apart. */
    --gradient-panel: linear-gradient(135deg, #B02718 0%, #6B140C 100%);
    --gradient-panel-ink: #FFFFFF;
    --gradient-panel-ink-inverse: #0E1014;

    /* Retained as an ALIAS, not deleted: PublicRegisterTest pins these names,
       and with red as the primary there is no second brand hue left for a
       separate accent ramp to carry. */
    --gradient-accent: var(--gradient-panel);
    --gradient-accent-ink: var(--gradient-panel-ink);

    /* Card-scale and quiet on purpose: the panel ramp's separation is right
       for one moment and unbearable across 21 cards. Composed from the
       surface tokens, so it needs no dark twin. */
    --gradient-raised: linear-gradient(160deg, var(--c-surface-raised) 0%, var(--c-surface) 100%);
    --gradient-surface: linear-gradient(180deg, var(--c-surface) 0%, var(--c-bg) 100%);
```

- [ ] **Step 3: Rewrite the dark gradients**

```css
    /* Dark. The clipped title ramp goes bright-to-brand rather than
       brand-to-deep: on a near-black page a deep stop disappears into the
       ground. 7.49:1 and 4.74:1 against --dark-bg, 1.58:1 apart. */
    --dark-gradient-primary: linear-gradient(135deg, #FF7A6E 0%, #E8412F 100%);

    /* The panel's deep stop sits 1.40:1 from the page ground, which would be
       an invisible EDGE if the edge were made of luminance. It is not: every
       panel carries a hairline, exactly as the dashboard does. The 135deg
       direction puts the brighter stop at the top-left where the panel meets
       the page, so its leading edge still reads. White measures 7.61:1 and
       13.64:1; stops sit 1.79:1 apart. */
    --dark-gradient-panel: linear-gradient(135deg, #A02418 0%, #5C110A 100%);
```

- [ ] **Step 4: Verify every ramp with the two measurements**

Do not skip this and do not eyeball it. Save as `$HOME/ftap-shots/ramps.py`:

```python
def lin(c):
    c /= 255
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

def L(h):
    h = h.lstrip('#')
    r, g, b = (int(h[i:i+2], 16) for i in (0, 2, 4))
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)

def cr(a, b):
    la, lb = L(a), L(b)
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)

CHECKS = [
    # label, stop A, stop B, ink, ink floor, ground, ground floor
    ("primary light (clipped title)", "#E03B2B", "#9C1A0F", None, 0,   "#F6F4F3", 3.0),
    ("primary dark  (clipped title)", "#FF7A6E", "#E8412F", None, 0,   "#0E1014", 3.0),
    ("panel light",                   "#B02718", "#6B140C", "#FFFFFF", 4.5, None, 0),
    ("panel dark",                    "#A02418", "#5C110A", "#FFFFFF", 4.5, None, 0),
]

for label, a, b, ink, ink_min, ground, ground_min in CHECKS:
    print(f"\n{label}")
    if ink:
        for stop in (a, b):
            v = cr(ink, stop)
            print(f"  {'OK ' if v >= ink_min else 'FAIL'} {v:5.2f}:1  ink on {stop}")
    if ground:
        for stop in (a, b):
            v = cr(stop, ground)
            print(f"  {'OK ' if v >= ground_min else 'FAIL'} {v:5.2f}:1  {stop} vs ground")
    v = cr(a, b)
    print(f"  {'OK ' if v >= 1.5 else 'FLAT'} {v:5.2f}:1  stop vs stop  <- the check that is always forgotten")
```

Run: `python3 "$HOME/ftap-shots/ramps.py"`
Expected: **no FAIL and no FLAT.**

- [ ] **Step 5: Move the icon tile onto the panel ramp**

In `_register.css:148-150`:

```css
    /* --gradient-panel, not --gradient-primary: the primary ramp is clipped
       onto the hero title and has to stay bright, and its bright stop carries
       a white icon at only 2.54:1. The panel ramp carries it at 7.61:1. */
    background-image: var(--gradient-panel);
    color: var(--gradient-panel-ink);
```

- [ ] **Step 6: Add the suit eyebrow**

Append to `_register.css`. The suit is a text glyph, so it costs no bytes and
inherits colour and size automatically:

```css
/* The eyebrow's suit mark. A glyph, not an image -- no request, no bytes, and
   it scales with the type. It marks a section; it does not claim a category,
   which is why suits are NOT used on table rows (there is no tournament-type
   column for them to encode). */
.p-hero__eyebrow::before,
.p-section-head__eyebrow::before {
    content: "\2660";       /* ♠ */
    margin-inline-end: var(--space-2);
}

.p-hero__eyebrow--heart::before { content: "\2665"; }   /* ♥ */
.p-hero__eyebrow--diamond::before { content: "\2666"; } /* ♦ */
.p-hero__eyebrow--club::before { content: "\2663"; }    /* ♣ */
```

Check the real class name for the section eyebrow first:
`grep -n "eyebrow" resources/css/5-public/_register.css` — if
`.p-section-head__eyebrow` does not exist, use whatever the section heading
component actually emits and correct the selector above.

- [ ] **Step 7: Add the hero watermark**

```css
/* One large suit behind the hero, at 3.5% -- atmosphere, not information, so
   it is aria-hidden and carries no contrast requirement. A glyph again: the
   whole motif costs zero image bytes. */
.p-hero {
    position: relative;
    overflow: hidden;
}

.p-hero__watermark {
    position: absolute;
    inset-block-start: -0.25em;
    inset-inline-end: -0.1em;
    font-size: 18rem;
    line-height: 1;
    color: var(--c-text);
    opacity: 0.035;
    pointer-events: none;
    user-select: none;
}
```

Add the element to the hero component, marked decorative:

```blade
<span class="p-hero__watermark" aria-hidden="true">&spades;</span>
```

Find the hero component with `grep -rln "p-hero" resources/views/components`.

- [ ] **Step 8: Confirm `.p-hero`'s `overflow: hidden` breaks nothing**

`.p-hero` may already have positioning or an existing `::after`. Check before
adding, and confirm no focus ring or dropdown inside the hero is now clipped —
a hero containing the topbar's user menu would be a real regression.

- [ ] **Step 9: Build, test, screenshot**

```bash
npm run build && php artisan test
```

`PublicRegisterTest` must still pass — it checks that gradient tokens are used
only inside `5-public/`, `2-layout/_shell-public.css` and `4-pages/`, and that
no raw `box-shadow` appears outside them. Aliasing `--gradient-accent` keeps its
name alive, which is what that test pins.

Screenshot `/`, `/about`, `/events` in both themes.

- [ ] **Step 10: HAND-OFF — do not run git**

```
feat(design): re-hue the public register onto red

The twelve gradient rules move from blue to red, keeping both constraints
the earlier work established: ink against each stop, and stop against stop.
A pair less than 1.5:1 apart renders as a flat fill however well it scores
against its ink.

Resolves a conflict that predates this change: --gradient-primary was both
clipped onto the hero title and used as the fill of .p-icon-tile, and on
the dark side no single ramp does both -- the bright stop a clipped title
needs carries a white icon at 2.54:1. The icon tile moves to the panel
ramp, which carries it at 7.61:1.

--gradient-accent becomes an alias of the panel ramp rather than being
deleted: PublicRegisterTest pins its name, and with red as the primary
there is no second brand hue for a separate accent ramp to carry.

Adds the suit eyebrow and the hero watermark, both as text glyphs -- the
motif costs zero image bytes.
```

---

### Task 4: Copy

Rewrite the public pages to lead on what the league is.

**Files:**
- Modify: `resources/views/home.blade.php`, `events.blade.php`, `contact.blade.php`, `about/index.blade.php`
- Modify: `tests/Feature/ContentPreservationTest.php` (two pinned strings)

**Interfaces:** none — this task touches copy and one test.

- [ ] **Step 1: Rewrite the home hero**

The page currently sells a casino the league is not. Replace the hero strings in
`home.blade.php`:

| Current | Replacement |
|---|---|
| `Join the most exciting amateur poker league. Compete in tournaments, climb the leaderboard, and become the champion.` | `Free Texas Hold'em every week across Regina. Play the season, earn points at every table, and the top 20 play the finale — for a prize pool funded entirely by local sponsors.` |
| `Track the latest seasonal progress, upcoming high-stakes tournaments, and the race to the championship finale.` | `Where the season stands, what is scheduled next, and who is in front.` |
| `Thank you to our amazing sponsors who make this league possible.` | `These businesses pay for the finale prize pool. That is what keeps the games free.` |

Keep the em dash as a literal character in the PHP string. **Do not write
`&amp;` or any HTML entity inside a `__()` string** — Blade escapes it again and
it renders literally, a bug this project has already shipped once.

- [ ] **Step 2: Rewrite the events intro**

In `events.blade.php`:

| Current | Replacement |
|---|---|
| `Join us at our premier venues for high-stakes competition. Register early to secure your seat at the table.` | `Every league night, across all of our venues. Registration opens ahead of each event and closes two hours before cards are in the air.` |

Verify the two-hour claim against the data before shipping it — compare
`registration_closes` and `start_time` on a real tournament. **If it is not
two hours, write what it actually is.** The whole point of this task is that the
page stops claiming things it cannot back.

- [ ] **Step 3: Retire the invented in-group language**

In `contact.blade.php`:

| Current | Replacement |
|---|---|
| `Join the First to Act Circle` | `Get in touch` |
| `Whether you're looking to join the league, discuss partnership opportunities, or need technical assistance, our stewards are ready to connect.` | `Questions about joining, sponsoring, or something not working on the site — this reaches us either way.` |

- [ ] **Step 4: Update the two pinned strings — in the same commit**

`ContentPreservationTest` asserts these two verbatim. If you change the view
without the test, the suite fails; if you change neither, the empty states keep
the old voice.

- `Check back soon for our next seasonal announcement.` → `No events are scheduled yet. The next season's dates go up here first.`
- `Standard league structure is being finalized.` → `The points structure for this season is not published yet.`

Update `resources/views/events.blade.php`, the rules view that carries the second
string, **and** the matching `assertSee` calls in
`tests/Feature/ContentPreservationTest.php`.

Leave `Current Season Leaders` and `No Scheduled Events` exactly as they are —
the owner specified the first by name.

- [ ] **Step 5: Sweep for surviving superlatives**

```bash
grep -rniE "most exciting|high-stakes|premier|amazing|world-class|cutting-edge|state of the art|steward|elite" resources/views/*.blade.php resources/views/about resources/views/rules
```

Expected: no marketing hits. Tournament *names* from the database (`Elite
Faceoff`) are seeded data, not copy — leave them.

- [ ] **Step 6: Run the suite**

Run: `php artisan test`
Expected: PASS. If `ContentPreservationTest` fails, step 4 is incomplete.

- [ ] **Step 7: Read the pages as a stranger**

Screenshot `/`, `/events`, `/contact`, `/about` and read them start to finish.
The test for every remaining sentence: **does the page show what it claims?** If
it claims a number, the number should be on the page.

- [ ] **Step 8: HAND-OFF — do not run git**

```
feat(content): say what the league actually is

The home page sold "high-stakes tournaments" at "premier venues" while the
about page said free-to-play social poker in Regina bars. The site
contradicted itself, and the genuinely good story -- free to play, a real
season, a prize pool funded entirely by local sponsors -- was buried two
clicks in.

Promotes free-to-play to the hero, replaces superlatives with counts the
app already renders, and retires the invented in-group language ("our
stewards", "the First to Act Circle") that read as a small league
costuming itself.

Two empty-state strings are pinned by ContentPreservationTest and move
with the views in this commit.
```

---

### Task 5: Audit

Prove the refresh across every page, both themes, both widths.

**Files:** none modified unless the audit finds a defect.

- [ ] **Step 1: Full suite and build**

```bash
php artisan test
npm run build
```

Record the pass count and the CSS byte count. It was 46,823 bytes (7,614
gzipped) before this work.

- [ ] **Step 2: Sweep every route, both themes, both widths**

29 pages × 2 widths (375px and 1440px) × 2 themes. For each render assert:
no horizontal overflow, `data-theme` stamped, body font Archivo, and the body
background equal to the expected ground for that theme (`#0E1014` / `#F6F4F3`).

Horizontal overflow is checked in the page, not from the screenshot:

```js
document.documentElement.scrollWidth > window.innerWidth
```

- [ ] **Step 3: Re-measure the colour balance**

The premise of this whole project was that red was 0.0–6.4% of the painted
pixels and blue was 24–65%. Re-measure the same five public pages and confirm
the ratio has inverted. This is the one number that says whether the refresh
actually happened.

- [ ] **Step 4: Confirm the motif rule held**

```bash
grep -rn "♠\|♥\|♦\|♣\|2660\|2665\|2666\|2663" resources/css resources/views
```

Every hit must be an eyebrow, the hero watermark, or an empty-state icon. **A
suit beside a table row is a defect** — there is no tournament-type column, so
it would imply a category that does not exist.

- [ ] **Step 5: Verify the destructive rule by eye**

On each of `/poker/seasons/{id}/edit`, `/poker/venues/{id}/edit` and `/profile`:
count the filled red buttons at rest. **The answer must be one.** If Delete
renders red at rest, `.btn--danger` did not take.

- [ ] **Step 6: Check the guards still guard**

```bash
php artisan test --filter="ConvertedViewsTest|ModifierClassGuardTest|PublicRegisterTest|InlineStyleGuardTest|TokenContrastTest"
```

All must pass. Then prove `TokenContrastTest` still bites: temporarily set
`--c-primary` to `#EF4537` in the light block, confirm the suite fails with the
3.77:1 message, and revert. **A guard nobody has seen fail is an assumption.**

- [ ] **Step 7: Write the exit audit**

Create `docs/RED-BLACK-EXIT-AUDIT.md` recording: the suite result, the CSS byte
delta, the before/after colour balance, every contrast pair with its measured
ratio, what the screenshot sweep found that the suite did not, and anything left
open. Update `docs/RESUME-HERE.md` to point at it.

- [ ] **Step 8: HAND-OFF — do not run git**

```
docs: record the red-and-black exit audit

Suite, bundle size, and the before/after colour balance across the public
pages -- the measurement that motivated the work in the first place.

Records what the screenshot sweep caught that the test suite did not,
which on this project has been every colour defect that ever shipped.
```

---

## What is deliberately not in this plan

- **Suit glyphs on table rows.** There is no tournament-type column, so a suit
  there would imply a category that does not exist. If types are ever added
  (regular / championship / charity / finale) the four suits map exactly, and
  that is the first place to spend them.
- **Felt textures, card backs, chip graphics.** Ruled out with the owner: the
  motif carries meaning or it does not appear.
- **The nav's active-link treatment.** It stays a filled pill and simply inherits
  the red. Changing it to an underline was in the mockup but not in the spec, and
  the owner previously asked for the dashboard link to *match* the other menu
  items — do not undo that.
- **The three items in `docs/PHASE-5-EXIT-AUDIT.md`** — the unverified modal
  focus trap, the half-wired email verification, and `tournament-badge` having no
  callers. Untouched, still open.
