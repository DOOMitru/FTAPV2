import { onBeforeUnmount, onMounted, type Ref } from 'vue';

/**
 * Run a callback when a click lands outside an element.
 *
 * Alpine gave this for free as `@click.outside`; Vue does not, and the trap it
 * hides is worth stating. The listener must be bound to an element that
 * CONTAINS the trigger. topbar.blade.php records what happens otherwise: with
 * the handler on the panel alone, the burger sits outside it, so the click that
 * opens the menu is itself an outside click -- the button sets open, the
 * document listener immediately unsets it, and the menu can never stay open.
 */
export function onClickOutside(root: Ref<HTMLElement | null>, handler: () => void): void {
    const listener = (event: MouseEvent): void => {
        const element = root.value;

        if (element !== null && ! element.contains(event.target as Node)) {
            handler();
        }
    };

    // Capture phase: a handler inside the panel may remove the clicked node
    // from the DOM, and contains() on a detached node is false, which would
    // read as an outside click.
    onMounted(() => document.addEventListener('click', listener, true));
    onBeforeUnmount(() => document.removeEventListener('click', listener, true));
}
