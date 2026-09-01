# Red and black — an aesthetic refresh of the whole app

**2026-08-31.** The design-system conversion is finished: 86 of 86 views run on
hand-built CSS, Tailwind is gone, and the token discipline is total — **zero**
hardcoded colours exist outside `1-base/_tokens.css`. That last fact is what makes
this project affordable.

This spec covers a change of *identity*, not of architecture. The two-register
split, the guard tests, the component vocabulary and the shells all stay. What
changes is the palette, four motif decisions, one button semantic, and the public
copy.

## Why

**The site does not look like its own logo.** The mark is red `#EF4537`, charcoal
`#303030` and white — and it is literally the four card suits. The app is cyan on
blue-navy. Measured across the rendered public pages:

| | red-family hues | blue-family hues |
|---|---|---|
| home | 0.9% | 36.4% |
| events | 0.0% | 52.0% |
| rules | 0.0% | 65.3% |
| about | 6.4% | 27.5% |
| contact | 0.1% | 24.1% |

The only red on the home page is inside the logo image. That is why the mark reads
as pasted on rather than as the page's own colour.

The blue came from the original brief, not from the brand, and it has no
relationship to poker. That is the real source of "bland": the app is wearing a
generic SaaS skin. Colour alone will not fix "flat" — surface and motif do that —
so both are in scope.

## Decisions taken

Four, all made by the owner on 2026-08-31:

1. **Red-and-black, all in.** Red becomes the primary action colour across the
   whole app; grounds go to warm-neutral ink. Cyan is retired.
2. **Motif that does a job.** Suits, felt green and medal colours appear only
   where they carry meaning. No felt textures, no card backs, no chip graphics.
3. **Plain and specific copy.** Facts replace superlatives; "free to play" is
   promoted to the home headline.
4. **Quiet destructive.** Destructive buttons rest neutral and commit to red only
   on hover/focus and in the confirm dialog.

## 1 · The palette

Warm-neutral grounds are half the effect. `#0B0F19` is a *blue* black and it was
fighting the logo; `#0E1014` is not.

### Neutrals

| Token | Dark | Light |
|---|---|---|
| `--c-bg` | `#0E1014` | `#F6F4F3` |
| `--c-surface` | `#181B22` | `#FFFFFF` |
| `--c-surface-raised` | `#212530` | `#EFEBE9` |
| `--c-border` | `#3A4050` | `#CDC3BF` |
| `--c-text` | `#ECEEF2` | `#16171B` |
| `--c-text-muted` | `#99A1AF` | `#56595F` |

### Brand

| Token | Dark | Light | Job |
|---|---|---|---|
| `--c-primary` | `#EF4537` | `#D6291B` | links, active nav, focus rings, eyebrows |
| `--c-primary-hover` | `#F4685C` | `#B92417` | |
| `--c-primary-fill` | `#D63A2C` | `#D6291B` | button grounds carrying small white labels |
| `--c-primary-ink` | `#FFFFFF` | `#FFFFFF` | |

In the light theme `--c-primary-fill` is the same value as `--c-primary`: white on
`#D6291B` measures 5.01:1, which beats the dark theme's fill and removes a second
near-identical red that would have earned nothing. The token still exists in both
themes so consumers need no theme-aware branching.

`--c-primary` in dark is the logo red **exactly**, sampled at 87.8% of the mark's
chromatic mass (hue 4.6°). It clears AA as text on the dark surface at 4.58:1, so
the brand colour can be used literally rather than approximated.

### Semantic

| Token | Dark | Light | Job |
|---|---|---|---|
| `--c-open` | `#2FBF6B` | `#15803D` | registration open, paid, won — felt green |
| `--c-gold` | `#D4A017` | shared | 1st |
| `--c-silver` | `#A8AAB0` | shared | 2nd |
| `--c-bronze` | `#B87333` | shared | 3rd |

### Contrast — measured, not asserted

Every value below was computed against the WCAG relative-luminance formula before
being written down. Text targets 4.5:1; hairlines and borders target their own
visibility, not 3:1, because a hairline that hits 3:1 is a rule, not a hairline.

```
body text on bg            dark 16.39:1   light 16.34:1
body text on surface       dark 14.83:1   light 17.91:1
muted on surface           dark  6.62:1   light  7.02:1
hairline vs surface        dark  1.66:1   light  1.73:1
hairline vs raised         dark  1.48:1   light  1.46:1
--c-primary as text        dark  4.58:1   light  5.01:1 (white) / 4.57:1 (bg)
white on --c-primary-fill        4.67:1   both themes
--c-open as text           dark  7.21:1   light  5.02:1
gold / silver / bronze disc with ink  7.54:1 / 7.71:1 / 4.72:1
```

**Three failures were found and fixed during derivation.** They are recorded
because the same traps will recur if anyone re-derives this:

- `#EF4537` as text on white is **3.77:1** — the logo red cannot be used literally
  in the light theme. `#D6291B` replaces it at 5.01:1.
- `#333845` against `--c-surface-raised` is **1.31:1** — present in the maths,
  absent to the eye. Raised to `#3A4050`.
- `#D5CDCA` against the light raised surface is **1.32:1**, same failure mirrored.
  Raised to `#CDC3BF`.

**Gold cannot be text.** No gold clears 4.5:1 on white — `#D4A017` manages 2.38:1.
This is why the medals are specified as **discs with dark ink**, which is what a
medal is anyway: `#16171B` on `#D4A017` is 7.54:1 and works unchanged in both
themes.

### Gradients

The 12 gradient rules live in one file and are re-hued from blue to red. Both
constraints from the existing system carry forward, because both were learned the
hard way:

- **Ink versus each stop**, and **stop versus stop.** A pair 1.14:1 apart renders
  as a flat fill however well it scores against its ink.
- `--gradient-primary` is background-clipped onto hero words, so it must stay
  bright; `--gradient-panel` is a large fill and wants depth. They are separate
  tokens for that reason and stay separate.

`PublicRegisterTest` pins gradient token *names*, not values, so re-hueing is free.
Do not rename them.

## 2 · Motif

Four uses, each carrying meaning or atmosphere and nothing in between:

1. **Suit eyebrows.** Section markers cycle ♠♥♦♣, replacing the current coloured
   dot. Decorative but honest — it marks a section without claiming a category.
2. **Medal discs.** `.rank--podium` currently paints 1st, 2nd and 3rd in one
   identical `--c-primary`, so the podium announces no hierarchy at all. It gains
   `--rank-1/2/3` carrying gold, silver and bronze.
3. **Hero watermark.** One large suit glyph at 3.5% opacity. A glyph, not an
   image: no texture files, no bytes added to the page.
4. **Felt green** as the open/won semantic, above.

### What is deliberately not done

**Suit glyphs beside table rows.** There is no tournament *type* column in the
schema — tournaments carry `name`, `description`, `start_time`, `season_id`,
`venue_id` — so a suit there would imply a category that does not exist.
Decoration wearing meaning's clothes is worse than either.

If tournament types are ever added (regular / championship / charity / finale) the
four suits map onto them exactly, and this is the first place to spend them.

## 3 · Quiet destructive

`.btn--danger` already exists, so this is a restyle rather than a new variant.

| State | Treatment |
|---|---|
| rest | neutral ground, `--c-border` hairline, `--c-text` label — visually a secondary button |
| hover / focus | `--c-primary-fill` ground, white label, red focus ring |
| inside the confirm dialog | the confirm button is a full red fill |

The rule this buys: **a filled red button always means "go", with no exceptions.**
Exactly one red button rests on any view. Destruction is never the loudest thing on
a page, and the moment of commitment is where the colour arrives.

The rejected alternative was outlined-red versus filled-red. It is domain-honest —
it echoes the deck's two colours — but two red buttons side by side are
distinguishable only on inspection, and peripheral vision is exactly where a
misclick on Delete happens.

## 4 · Copy

The home page currently sells a casino the league is not, while the actual
proposition sits unread on the about page.

| The site says | The league is |
|---|---|
| "**high-stakes** tournaments" | **free to play** |
| "our **premier** venues" | local Regina bars and lounges |
| "the **most exciting** amateur league" | weekly social hold'em |
| *never mentioned on the home page* | 100% of sponsor fees fund the finale prize |
| *never mentioned* | charity tournaments |

Three rules for the rewrite:

1. **Lead with free.** It is the strongest fact the league has and it is currently
   contradicted on the front page.
2. **Counts, not adjectives.** "our amazing sponsors" → "Five businesses pay for
   the finale prize." "premier venues" → the venue names.
3. **Claim nothing the page cannot show.** Every superlative either becomes a
   number the app already renders, or goes.

The approved home hero:

> **REGINA, SASKATCHEWAN**
> **First to Act Poker League**
> Free Texas Hold'em every week across Regina. Play the season, earn points at
> every table, and the top 20 play the finale — for a prize pool funded entirely
> by local sponsors.
> `[ Join the league ]` `[ See the schedule ]`

Also retire the invented in-group language: "our **stewards** are ready to connect"
and "Join the First to Act **Circle**" both read as a small league costuming itself.

**Two strings are pinned by tests** and need coordinated edits in the same commit:
`'Check back soon for our next seasonal announcement.'` and `'Standard league
structure is being finalized.'`, both in `ContentPreservationTest`. `'Current
Season Leaders'` and `'No Scheduled Events'` are also pinned but are keepers — the
owner specified the first by name.

## 5 · Blast radius

| | reach |
|---|---|
| `var(--c-primary)` | 53 rules across 16 files |
| `var(--c-border)` | 38 rules across 24 files |
| `var(--gradient-*)` | 12 rules in 1 file |
| `var(--shadow-*)` | 6 rules across 4 files |
| hardcoded colours outside `_tokens.css` | **0** (the one hex found by grep is inside a comment) |

Because there are no stray hexes, step A below changes the entire application's
colour by editing one file. Everything after it is correction and elaboration, not
search-and-replace.

## 6 · Sequencing

Five steps, each independently committable, each ending at a hand-off:

**A · Palette.** Rewrite the neutral, brand and semantic tokens in
`1-base/_tokens.css`. The whole app changes at once. Screenshot every page in both
themes and fix the fallout — this step is where cyan-assuming rules surface.

**B · Component semantics.** Four specific changes:
`.btn--danger` restyled to the quiet-destructive states above;
`.rank--podium` split into per-place medal modifiers;
the registration-open badge in `_badge.css` and the events page's status pill moved
from primary to `--c-open`, leaving every other badge neutral;
and the topbar's active-link underline moved from cyan to `--c-primary`.
This step also re-examines the alert and form-error components, which currently use
red as *alarm* — see Risks.

**C · Public register.** Re-hue the 12 gradient rules onto red, apply the hero
watermark and suit eyebrows, verify stop-versus-stop separation on every ramp.

**D · Copy.** Rewrite the 8 public pages against the three rules above, updating
the two pinned test strings in the same commit.

**E · Audit.** Full contrast sweep, every page at 2 widths × 2 themes, the whole
suite, and every guard test.

## 7 · Risks

- **Cyan-assuming rules.** 53 `--c-primary` consumers were authored against a
  cyan/blue that is about to become red. Some will have been tuned to that hue —
  focus rings and hover states most likely. Step A's screenshot sweep is what
  catches them; a green suite will not.
- **Contrast regressions are silent.** Every colour bug this project has shipped
  passed every assertion that existed at the time. Step E re-measures rather than
  re-asserts.
- **Red carries meaning already.** Error text, validation states and the alert
  component all currently use red as *alarm*. When red is also the brand, those
  need re-examining so an inline error still reads as an error and not as
  emphasis. This lands in step B.
- **The light theme is the harder one.** The logo red fails on white, so the light
  theme is the approximation and will need the most screenshot iteration.

## 8 · Out of scope

The three items in `docs/PHASE-5-EXIT-AUDIT.md` — the unverified modal focus trap,
the half-wired email verification, and `tournament-badge` having no callers — are
untouched by this work and stay open.
