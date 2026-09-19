<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    value: number;
    max: number;
    /**
     * Required, unlike the Blade twin.
     *
     * role="meter" needs an accessible name (axe-core: aria-meter-name), and
     * the Blade default is a generic fallback its own docblock calls "not a
     * substitute for a real, specific label at each call site". Describe what
     * THIS meter shows: "Points for Mara Vasquez".
     */
    label: string;
    /**
     * What the meter READS as. Always a formatted string, never a number to
     * format here.
     *
     * The Blade twin takes `decimals` and calls number_format when no
     * valueText is given. Both are gone: formatting is PHP's under spec §5a,
     * and with the label arriving ready-made there is nothing for `decimals`
     * to control. The rule it encoded still matters and now lives at the call
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
// Clamped, because a value above max would otherwise draw past its own track.
const percentage = computed(() => (props.max > 0
    ? Math.min(100, Math.round((props.value / props.max) * 10000) / 100)
    : 0));
</script>

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
