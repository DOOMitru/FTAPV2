/**
 * Fill text fields from the option a <select> has selected.
 *
 * The admin forms let you pick a registered user and then carry that user's
 * name into free-text fields, so a result can be recorded for someone who is
 * not in the system while still linking most entries to a real account.
 *
 * Values travel on the option's data-* attributes and are read through
 * `dataset`, never interpolated into an inline handler — the same rule as
 * confirm.ts, and for the same reason: an attribute is HTML-decoded before its
 * contents reach the JS parser, so a name containing an apostrophe would break
 * out of a string literal built that way.
 *
 * Markup contract:
 *
 *     <select data-autofill='{"first":"#player_name","nick":"#player_nickname"}'>
 *       <option data-first-name="Ada" data-last-name="Lovelace" data-nickname="Countess">
 *
 * `first` receives "first last" trimmed; `nick` receives the nickname or "".
 * A target that is not on the page is skipped rather than throwing.
 */
type Targets = { first?: string; nick?: string };

function apply(select: HTMLSelectElement): void {
    let targets: Targets;

    try {
        targets = JSON.parse(select.dataset.autofill ?? '{}') as Targets;
    } catch {
        return;
    }

    const option = select.options[select.selectedIndex];

    if (! option?.value) {
        return;
    }

    if (targets.first) {
        const field = document.querySelector<HTMLInputElement>(targets.first);

        if (field) {
            field.value = `${option.dataset.firstName ?? ''} ${option.dataset.lastName ?? ''}`.trim();
        }
    }

    if (targets.nick) {
        const field = document.querySelector<HTMLInputElement>(targets.nick);

        if (field) {
            field.value = option.dataset.nickname ?? '';
        }
    }
}

export function initAutofill(): void {
    document.addEventListener('change', (event) => {
        const select = (event.target as HTMLElement | null)?.closest<HTMLSelectElement>('select[data-autofill]');

        if (select) {
            apply(select);
        }
    });
}
