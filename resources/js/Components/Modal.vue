<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Props {
    show?: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    /** Move focus to the first focusable element when this opens. */
    focusable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    show: false,
    maxWidth: '2xl',
    focusable: false,
});

const emit = defineEmits<{ close: [] }>();

const root = ref<HTMLElement | null>(null);

/*
 * A faithful port of x-modal, focus trap and all -- and worth saying why, since
 * confirm-dialog.blade.php is right that a native <dialog> gets all of this
 * free and this spends thirty lines doing it by hand.
 *
 * The blocker is the stylesheet, not the markup. This modal's CSS is keyed on
 * .modal__overlay, .modal__backdrop, .modal__panel and six motion classes, and
 * rewriting CSS is out of scope for the conversion. Moving to <dialog> is a
 * worthwhile follow-up once it is not.
 */
const focusables = (): HTMLElement[] => {
    const element = root.value;

    if (element === null) {
        return [];
    }

    const selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';

    // Array.from rather than a spread: tsconfig targets esnext but does not
    // set downlevelIteration, and a NodeListOf is only spreadable with it.
    return Array.from(element.querySelectorAll<HTMLElement>(selector))
        .filter((node) => ! node.hasAttribute('disabled'));
};

const onKeydown = (event: KeyboardEvent): void => {
    if (! props.show) {
        return;
    }

    if (event.key === 'Escape') {
        emit('close');

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const list = focusables();

    if (list.length === 0) {
        return;
    }

    event.preventDefault();

    const index = list.indexOf(document.activeElement as HTMLElement);
    const next = event.shiftKey
        ? (index <= 0 ? list.length - 1 : index - 1)
        : (index === -1 || index === list.length - 1 ? 0 : index + 1);

    list[next]?.focus();
};

/*
 * watch with immediate, not onMounted alone.
 *
 * This is the fault Phase 1 Task 12 fixed in the Alpine version: x-init never
 * fired on a modal that STARTED open -- which is the wrong-password path, where
 * the modal reopens carrying the error -- so neither the focus trap nor the
 * scroll lock engaged. Alpine's answer was x-effect; Vue's is immediate.
 */
watch(() => props.show, (show) => {
    if (! show) {
        document.body.classList.remove('is-modal-open');

        return;
    }

    document.body.classList.add('is-modal-open');

    if (props.focusable) {
        void nextTick(() => focusables()[0]?.focus());
    }
}, { immediate: true });

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('is-modal-open');
});
</script>

<template>
    <div v-show="show" ref="root" class="modal">
        <Transition
            enter-active-class="modal__motion"
            enter-from-class="modal__fade-from"
            enter-to-class="modal__fade-to"
            leave-active-class="modal__motion modal__motion--leaving"
            leave-from-class="modal__fade-to"
            leave-to-class="modal__fade-from"
        >
            <div v-show="show" class="modal__overlay" @click="emit('close')">
                <div class="modal__backdrop"></div>
            </div>
        </Transition>

        <Transition
            enter-active-class="modal__motion"
            enter-from-class="modal__rise-from"
            enter-to-class="modal__rise-to"
            leave-active-class="modal__motion modal__motion--leaving"
            leave-from-class="modal__rise-to"
            leave-to-class="modal__rise-from"
        >
            <div v-show="show" class="modal__panel" :class="`modal__panel--${maxWidth}`">
                <slot />
            </div>
        </Transition>
    </div>
</template>
