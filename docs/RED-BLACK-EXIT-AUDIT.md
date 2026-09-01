# Red and black — exit audit

**2026-08-31.** The aesthetic refresh is complete. The app runs on the logo's
palette, the motif carries meaning, and the public copy says what the league
actually is.

## The measured result

| | before | after |
|---|---|---|
| Suite | 108 passed | **135 passed** (0 failed) |
| CSS bundle | 46,823 B (7,614 gz) | 48,331 B (7,931 gz) — **+3.2%** |
| Contrast pairs enforced by a test | 0 | **27** |
| Renders verified | — | **112** (28 pages × 2 widths × 2 themes) |
| Horizontal overflow | — | none; widest scrollWidth at 375px is **375** |
| Body font | — | Archivo × 112 |

### The number that started this

Red-family versus blue-family hues, as a share of painted pixels on the five
main public pages. Same measurement method before and after.

| page | red before | red after | blue before | blue after |
|---|---|---|---|---|
| home | 0.9% | 7.5% | 36.4% | 0.1% |
| events | 0.0% | 1.4% | 52.0% | 0.1% |
| rules | 0.0% | 1.7% | 65.3% | 0.0% |
| about | 6.4% | 8.1% | 27.5% | 0.1% |
| contact | 0.1% | 1.6% | 24.1% | 0.2% |
| **mean** | **1.5%** | **4.1%** | **41.1%** | **0.1%** |

The site was 27 times more blue than red while wearing a red logo. It is now
neither: red where red means something, and a neutral charcoal everywhere else,
which is exactly what the mark is made of.

## What the audit caught that the tasks did not

Three findings, all from measurement rather than inspection.

### 1. The dark theme was still blue-grey, and I had written otherwise

Task 1 replaced `#0B0F19` with `#0E1014` and the token comment called it
neutral. It was not. The whole dark family sat at **hue 222° and 16–19%
saturation** — less blue than before, but the same hue. The colour sweep found
**7% of painted pixels still classifying blue, 20% on the rules pages**, which
are dense with raised cards.

The logo's charcoal is `#303030`: **exactly 0% saturation**. The dark palette
now sits within one point of neutral and a hair warm, matching the direction
the light theme already leaned. Blue fell from 7% to 0.1%.

This is the same failure this project keeps finding: a value that is *directionally*
right, asserted as *actually* right, and never measured.

### 2. The 375px pass had never actually run at 375px

Headless Chromium enforces a **500px minimum window width** in both `--headless`
and `--headless=new`. Every "375px" render was silently 500px wide. Mobile had
not been verified at all, and would have been reported as passing.

The fix was to load each page in a **same-origin iframe** sized to 375 and read
the inner document from the wrapper. `scrollWidth` then reports honestly: 375 in
a 375 viewport, on all 56 mobile renders.

### 3. Two sweep "failures" that were the harness, not the app

Guest pages swept inside an authenticated browser profile redirect to
`/dashboard`, and auth-gated pages swept logged-out redirect to `/login` — both
dropping the query string that selects the theme. Twelve failures on the first
count, four on the second. Re-run in the correct profile, all sixteen are clean.

Worth recording because the failure *looked* exactly like a theming bug.

## The guards, and proof they bite

| test | guards |
|---|---|
| `TokenContrastTest` | **new** — parses the real token file and asserts 23 contrast pairs plus 4 hairline floors |
| `ConvertedViewsTest` | Tailwind returning to any view; its allowlist is empty |
| `ModifierClassGuardTest` | a layout modifier used without its base class |
| `PublicRegisterTest` | gradient and elevation tokens outside the public register |
| `InlineStyleGuardTest` | inline CSS, both `style` attributes and `<style>` blocks |
| `ContentPreservationTest` | that a rewrite has not dropped content — asserted on data, never markup |

`TokenContrastTest` was proven to fail twice: once before the tokens existed,
and once at the end by substituting the logo red `#EF4537` into the light theme,
which failed exactly the two pairs it should (3.77:1 on white). **A guard nobody
has watched fail is an assumption.**

## The rules this refresh established

- **A filled red button always means "go."** Destructive controls rest neutral
  and commit to red on hover — `.btn--danger` and `.link--danger` alike. Verified
  by eye: on `/profile`, SAVE is filled and DELETE ACCOUNT is not.
- **Red is the brand; green is "open"; medals are discs.** `badge--primary` had
  been carrying both state and identity; now `--c-open` carries state. Gold,
  silver and bronze replaced a podium painted one colour.
- **Motif must do a job.** Suits mark hero eyebrows and watermark hero bands.
  They do **not** appear beside table rows: there is no tournament-type column,
  so one there would imply a category that does not exist.
- **Claim nothing the page cannot show.** The events intro states no
  registration window, because `scheduled_at` runs −120 to +120 minutes of
  `start_time`; and no location, because the seeded venue cards say Houston.

## Still open — unchanged by this work

1. **The delete-account modal's focus trap has never been driven by a human.**
   Headless Chromium cannot drive Alpine's `x-show`. On `/profile`: Delete
   Account → wrong password → submit; check focus, Escape, and the scroll lock.
2. **Email verification is half-wired and predates all of this.** `User` does
   not implement `MustVerifyEmail`; the import is commented at
   `app/Models/User.php:5`, so no verification mail is ever sent.
3. **`components/tournament-badge` still has no callers.**
4. **`steward@firsttoact.com`** survives on the contact page. It matched the
   jargon sweep but it is an address, not copy — changing it is the owner's call.
