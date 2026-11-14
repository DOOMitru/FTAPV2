# Inertia + Vue Conversion — Plan 2: The remaining shared components

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the eight Blade components the authenticated pages still need, so
Plan 3 can convert pages without stopping to build furniture.

**Architecture:** Each is a Vue twin of a Blade component that stays in place for
the public pages and for pages not yet converted. `ComponentParityTest`
discovers each pair from the filesystem and fails when the two stop agreeing
about the class hooks the stylesheet is keyed on.

**Tech Stack:** Vue 3.5 `<script setup lang="ts">`, TypeScript 5.9 strict,
Inertia v3, Vite 7, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-09-19-inertia-vue-conversion-design.md`

**Depends on:** Plan 1 (`docs/superpowers/plans/2026-09-19-inertia-vue-foundation.md`),
complete. `Btn`, `Card`, `Field`, `Alert`, `InputError`, `Modal`, `Monogram`,
`PageHeader`, `Emph`, `Dropdown`, `DropdownLink`, `Action`, `Topbar`, `Brand`,
`ThemeToggle`, `SiteFooter`, `ConfirmDialog` and `NotificationCard` already
exist in `resources/js/Components/`.

## Global Constraints

- **Never run git commands.** The owner commits by hand. Every "Commit" step is
  a hand-off: state the files and the message, then stop.
- **Run the suite with `bin/suite.sh`**, never a bare `php artisan test` — PHP
  8.5.4 segfaults on a full run on this machine. Do not pipe it; capture with
  `out=$(bin/suite.sh); status=$?`.
- **TypeScript throughout.** Every SFC is `<script setup lang="ts">`. No `.js`
  files. No `any` or `@ts-ignore` without a comment saying why.
- **Class hooks are copied verbatim.** The CSS is not being rewritten, and
  `ComponentParityTest` fails on any divergence.
- **Props carry display-ready values** (spec §5a). PHP decides a value, Vue
  places it: `place_label: "3rd"`, not `place: 3` to ordinalise here.
- **Semantic, not stylistic.** A prop says `tier: "gold"`, never
  `tier: "notification--gold"`.
- **No public page changes.** The Blade twins stay exactly as they are.

---

## Why `p-event` is not in this plan

`p-event.blade.php` is 308 lines and is used by `events.blade.php`,
`home.blade.php` and `poker/tournaments/show.blade.php`. Only the last is an app
page, and it is the 499-line tournament page that converts last in Plan 3. The
card is most of that page's work rather than furniture for other pages, so it
belongs to that task. Building it here would mean writing 308 lines nothing
consumes for several weeks.

---

## File Structure

**Created**, all in `resources/js/Components/`:

| File | Responsibility |
|---|---|
| `Badge.vue` | A pill. One variant modifier. |
| `Stat.vue` | A label over a value. |
| `Rank.vue` | A place, medalled for the top three. |
| `EmptyState.vue` | Title, optional body, optional action slot. |
| `PlayerLink.vue` | A name, linked only when there is an account behind it. |
| `Table.vue` | Scroll wrapper, optional caption, head and body slots, two layout modifiers. |
| `Meter.vue` | A labelled bar. |
| `SeasonPanel.vue` | One season's figures for one player. |

**Modified:** none. Every Blade twin stays.

---

## Task 1: The five small ones

Badge, Stat, Rank, EmptyState and PlayerLink are three to twenty-two lines each
and share no behaviour, so they are one task rather than five commits of a
dozen lines.

**Files:**
- Create: `resources/js/Components/{Badge,Stat,Rank,EmptyState,PlayerLink}.vue`

**Interfaces:**
- Produces:
  - `Badge` — `variant?: 'accent' | 'open' | 'primary' | null` (default `null`, meaning no modifier), default slot.
  - `Stat` — `label: string`, `value: string | number`.
  - `Rank` — `place: number`, `label?: string | null`.
  - `EmptyState` — `title: string`, default slot, `action` slot.
  - `PlayerLink` — `playerId?: string | null`, default slot.

- [ ] **Step 1: Write Badge.vue**

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    /**
     * No modifier by default, unlike the Blade twin.
     *
     * That component defaults to `neutral` and emits `badge--neutral`, which
     * the stylesheet does not declare -- a plain <x-badge> has been carrying a
     * phantom class. Nothing catches it: UnusedCssClassTest finds classes
     * DECLARED and unused, not classes USED and undeclared, which is the
     * opposite direction.
     *
     * The three below are every variant the CSS defines and every one any
     * caller passes.
     */
    variant?: 'accent' | 'open' | 'primary' | null;
}

const props = withDefaults(defineProps<Props>(), { variant: null });

// Written out rather than interpolated, so both guards can see the names.
const VARIANT: Record<string, string> = {
    accent: 'badge--accent',
    open: 'badge--open',
    primary: 'badge--primary',
};

const classes = computed(() =>
    ['badge', props.variant === null ? '' : VARIANT[props.variant]].filter(Boolean).join(' '));
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
```

- [ ] **Step 2: Write Stat.vue**

```vue
<script setup lang="ts">
interface Props {
    label: string;
    /** Already formatted -- spec §5a. This places a value, it does not format one. */
    value: string | number;
}

defineProps<Props>();
</script>

<template>
    <div class="stat">
        <span class="stat__label">{{ label }}</span>
        <span class="stat__value">{{ value }}</span>
    </div>
</template>
```

- [ ] **Step 3: Write Rank.vue**

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    place: number;
    /**
     * What the badge READS as, when that is not the bare place. The season
     * standings in rank order show "#2" here, because in that order the
     * position is the figure rather than a row number beside one.
     */
    label?: string | null;
}

const props = withDefaults(defineProps<Props>(), { label: null });

/*
 * Places 1-3 carry a medal; everything below is the quiet default. The
 * modifier is per-place rather than one shared --podium, because a podium
 * whose three seats are the same colour states no order.
 *
 * Written out rather than interpolated as `rank--${place}`: a composed class
 * name is invisible to both UnusedCssClassTest and ComponentParityTest, which
 * is how four notification tier classes went missing in Plan 1.
 */
const MEDAL: Record<number, string> = {
    1: 'rank--1',
    2: 'rank--2',
    3: 'rank--3',
};

const classes = computed(() => ['rank', MEDAL[props.place] ?? ''].filter(Boolean).join(' '));
</script>

<template>
    <span :class="classes">{{ label ?? place }}</span>
</template>
```

- [ ] **Step 4: Write EmptyState.vue**

```vue
<script setup lang="ts">
import { useSlots } from 'vue';

interface Props {
    title: string;
}

defineProps<Props>();

const slots = useSlots();
</script>

<template>
    <div class="empty">
        <p class="empty__title">{{ title }}</p>

        <p v-if="slots.default" class="empty__body"><slot /></p>

        <div v-if="slots.action" class="empty__action"><slot name="action" /></div>
    </div>
</template>
```

- [ ] **Step 5: Write PlayerLink.vue**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    /**
     * The account's id, or null when there is none.
     *
     * user_id is nullable with nullOnDelete on results, registrants and venue
     * points, so a departed player leaves a player_name string and nothing
     * behind it -- and a link to a user that no longer exists is a 404 with
     * somebody's name on it. Every call site would otherwise have to remember
     * that itself, which is the sort of thing remembered in nine places out
     * of ten.
     *
     * An id rather than a user object: the page sends what it has, and the
     * only thing this needs is whether there is an account and where it lives.
     */
    playerId?: string | null;
}

withDefaults(defineProps<Props>(), { playerId: null });
</script>

<template>
    <!-- Literal path: Ziggy is not installed, so there is no route() here. -->
    <Link v-if="playerId" class="player-link" :href="`/players/${playerId}`"><slot /></Link>
    <span v-else><slot /></span>
</template>
```

- [ ] **Step 6: Run the parity guard**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: PASS, with five more pairs discovered. A failure names the hooks that
diverged — fix the component, never the test.

- [ ] **Step 7: Type-check and build**

```bash
npm run typecheck && npx vite build; echo "exit=$?"
```

Expected: exit 0.

- [ ] **Step 8: Commit (hand-off)**

Files: `resources/js/Components/{Badge,Stat,Rank,EmptyState,PlayerLink}.vue`

```
feat: port the five small shared components to Vue

Badge, Stat, Rank, EmptyState and PlayerLink. Each keeps its Blade twin for the
public pages, and ComponentParityTest compares the pair.

Rank writes its three medal classes out rather than interpolating rank--${place}:
a composed class name is invisible to both that guard and UnusedCssClassTest,
which is how four notification tier classes went missing in Plan 1.

PlayerLink takes an id rather than a user, and renders a span when there is
none -- user_id is nullable with nullOnDelete, so a departed player leaves a
name and nothing to point at.
```

---

## Task 2: Table

**Files:**
- Create: `resources/js/Components/Table.vue`

**Interfaces:**
- Produces: `Table` — `caption?: string | null`, `stacked?: boolean`,
  `cards?: boolean`; slots `head` and default.

- [ ] **Step 1: Write Table.vue**

```vue
<script setup lang="ts">
import { computed, useSlots } from 'vue';

interface Props {
    caption?: string | null;
    /** Below 48rem the rows reflow into label/value blocks. */
    stacked?: boolean;
    /**
     * Below 48rem each row becomes a card whose cells are PLACED on a grid the
     * page defines, rather than stacked into labelled lines. Use it where a row
     * has a clear primary identifier and a handful of supporting facts --
     * stacking a five-column admin list prints five labelled lines per record
     * and buries the thing you came to find.
     */
    cards?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    caption: null,
    stacked: false,
    cards: false,
});

const slots = useSlots();

// Both modifiers belong on the <table>, not on the scroll wrapper, which is
// where a caller's own class lands.
const classes = computed(() => [
    'table',
    props.stacked ? 'table--stacked' : '',
    props.cards ? 'table--cards' : '',
].filter(Boolean).join(' '));
</script>

<template>
    <div class="table-scroll">
        <table :class="classes">
            <caption v-if="caption" class="table__caption">{{ caption }}</caption>

            <thead v-if="slots.head"><tr><slot name="head" /></tr></thead>

            <tbody><slot /></tbody>
        </table>
    </div>
</template>
```

- [ ] **Step 2: Run the parity guard**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: PASS.

- [ ] **Step 3: Type-check and build**

```bash
npm run typecheck && npx vite build; echo "exit=$?"
```

- [ ] **Step 4: Commit (hand-off)**

Files: `resources/js/Components/Table.vue`

```
feat: port the table component to Vue

Scroll wrapper, optional caption, head and body slots, and the two layout
modifiers. Both modifiers go on the <table> rather than the wrapper, as in the
Blade twin: a caller's own class lands on the wrapper, so neither can arrive as
a plain class.
```

---

## Task 3: Meter

**Files:**
- Create: `resources/js/Components/Meter.vue`

**Interfaces:**
- Produces: `Meter` — `value: number`, `max: number`, `label: string`,
  `valueLabel: string`, `showValue?: boolean`, `valueTitle?: string | null`.

**One deliberate difference from the Blade twin: `label` is required here.**
The Blade component defaults it to a generated string and its own docblock says
that default "is not a substitute for a real, specific label at each call site"
— `role="meter"` requires an accessible name per the ARIA spec (axe-core's
`aria-meter-name`). Making it required enforces what the comment asks for, and
removes a `number_format` call that would otherwise be a second definition of a
formatting rule in TypeScript.

- [ ] **Step 1: Write Meter.vue**

Read `resources/views/components/meter.blade.php` in full first and copy every
class hook from it.

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    value: number;
    max: number;
    /**
     * Required, unlike the Blade twin. role="meter" needs an accessible name
     * (axe-core: aria-meter-name), and the Blade default is a generic fallback
     * its own docblock calls "not a substitute for a real, specific label at
     * each call site". Describe what THIS meter shows: "Points for Mara
     * Vasquez".
     */
    label: string;
    /**
     * What the meter READS as. Always a formatted string, never a number to
     * format here.
     *
     * The Blade twin takes `decimals` and calls number_format when no
     * valueText is given. Both go: formatting is PHP's under spec §5a, and
     * with the label arriving ready-made there is nothing for `decimals` to
     * control. The rule it encoded still matters and now lives at the call
     * site -- a meter showing an AVERAGE formats to one decimal, because
     * rounding 218.3 and 218.4 to the same 218 hides the difference the
     * standings are ordered on.
     *
     * In rank order the standings show a position -- "#2" -- over a bar whose
     * length is still the average behind it, with valueTitle carrying the
     * figure itself.
     */
    valueLabel: string;
    showValue?: boolean;
    valueTitle?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    showValue: true,
    valueTitle: null,
});

// Arithmetic, not formatting: a ratio of two numbers the caller already has.
// Clamped, because a value above max would otherwise draw a bar past its track.
const percentage = computed(() => (props.max > 0
    ? Math.min(100, Math.round((props.value / props.max) * 100 * 100) / 100)
    : 0));
</script>
```

```vue
<template>
    <!--
        style stays a literal attribute: it carries the one approved
        custom-property escape hatch (--meter-fill) for the no-inline-CSS rule.
    -->
    <div
        class="meter"
        role="meter"
        :aria-valuenow="value"
        :aria-valuemin="0"
        :aria-valuemax="max"
        :aria-label="label"
        :style="`--meter-fill: ${percentage}%`"
    >
        <div class="meter__track"><div class="meter__fill"></div></div>

        <span v-if="showValue" class="meter__value" :title="valueTitle ?? undefined">
            {{ valueLabel }}
        </span>
    </div>
</template>
```

- [ ] **Step 2: Run the parity guard**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: PASS.

- [ ] **Step 3: Type-check and build**

```bash
npm run typecheck && npx vite build; echo "exit=$?"
```

- [ ] **Step 4: Commit (hand-off)**

Files: `resources/js/Components/Meter.vue`

```
feat: port the meter component to Vue

label is required here, unlike the Blade twin whose default its own docblock
calls "not a substitute for a real, specific label at each call site".
role="meter" needs an accessible name.

valueLabel is required and decimals is gone. Formatting is PHP's under spec
§5a, and with the label arriving ready-made there is nothing left for decimals
to control. The rule it encoded moves to the call site: an average formats to
one decimal, because rounding 218.3 and 218.4 to the same 218 hides the
difference the standings are ordered on.
```

---

## Task 4: SeasonPanel

The largest in this plan at 135 lines, and the one with a rule inside it.

**Files:**
- Create: `resources/js/Components/SeasonPanel.vue`

**Interfaces:**
- Consumes: `Card`, `Btn`, `EmptyState` (Task 1), `Stat` (Task 1).
- Produces: `SeasonPanel` — props below.

**Read `resources/views/components/season-panel.blade.php` in full first.** Two
things in it decide the shape of this port:

**It is shared by the dashboard and a player's profile**, which show the same
panel about different people. Its docblock says why that matters: "as a copy it
would have been a hundred lines free to drift, and the venue-points rule below
is not something to enforce twice."

**`self` changes the copy only.** A person reading their own figures is told
what to DO about an empty one; a person reading somebody else's is told what is
true. It is not a permission flag — it must not be used to decide what data to
show.

**The venue-points rule is NOT enforced here.** A player's venue points are
readable only by that player and by admins (`VenuePoints::readableBy()`), and
spec §8 requires the figure to be withheld from the PROPS, not hidden in the
template. This component renders a venue-points figure when it is given one and
omits the row when it is not. `VenuePointsPrivacyTest` walks every route and
will catch a page that sends it to the wrong person.

- [ ] **Step 1: Write SeasonPanel.vue**

```vue
<script setup lang="ts">
import Card from './Card.vue';
import Btn from './Btn.vue';
import EmptyState from './EmptyState.vue';
import Stat from './Stat.vue';

interface Props {
    season: {
        name: string;
        /** Display-ready, per spec §5a. */
        points_label: string;
        tournaments_label: string;
        wins_label: string;
        rank_label: string | null;
        /**
         * Null when the viewer may not see it. The decision is made in PHP by
         * VenuePoints::readableBy(); this only renders what it is given, and
         * omits the row when given nothing. Spec §8.
         */
        venue_points_label: string | null;
    } | null;
    /**
     * Whether the viewer is the player. Changes the COPY only -- a person
     * reading their own figures is told what to do about an empty one, a
     * person reading somebody else's is told what is true. Never a permission
     * flag: what may be seen is decided in PHP before it reaches here.
     */
    self?: boolean;
}

withDefaults(defineProps<Props>(), { self: true });
</script>
```

**The template is a direct port of
`resources/views/components/season-panel.blade.php`, copying every class hook
and keeping both copy branches keyed on `self`.** It is not reproduced here:
at 135 lines it would be a verbatim second copy of a file in this same repo,
and the risk of the two disagreeing is worse than the inconvenience of opening
one of them. Every other code step in this plan carries its code inline.

- [ ] **Step 2: Run the parity guard**

```bash
php artisan test tests/Feature/ComponentParityTest.php
```

Expected: PASS.

- [ ] **Step 3: Type-check and build**

```bash
npm run typecheck && npx vite build; echo "exit=$?"
```

- [ ] **Step 4: Run the full suite**

```bash
out=$(bin/suite.sh); status=$?; echo "$out" | tail -1; echo "exit=$status"
```

Expected: `0 failing`, exit 0. `VenuePointsPrivacyTest` in particular must stay
green.

- [ ] **Step 5: Commit (hand-off)**

Files: `resources/js/Components/SeasonPanel.vue`

```
feat: port the season panel to Vue

Shared by the dashboard and a player's profile, which show the same panel about
different people. As a copy it would be a hundred lines free to drift.

self changes the copy only -- what to do about an empty season when it is
yours, what is true when it is somebody else's. It is not a permission flag:
the venue-points figure arrives null when the viewer may not see it, decided in
PHP by VenuePoints::readableBy(), because spec §8 requires it withheld from the
props rather than hidden in the template.
```

---

## What this plan does not cover

- `p-event` — see the note above; it belongs to Plan 3's tournament page.
- The `p-*` family otherwise (`p-fact`, `p-hand`, `p-hero`, `p-rules`,
  `p-section-head`, `p-sponsor`). Public pages only; they never need a twin.
- `theme-script`, `honeypot`, `auth-session-status`. Blade's permanently: the
  first runs before Vue mounts, the other two belong to public forms and auth
  screens, which are staying Blade.
