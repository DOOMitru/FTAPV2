/**
 * A select whose options depend on another select.
 *
 * Recording a result asks which tournament, then offers only the players
 * registered for it who do not already have a result. The second list therefore
 * cannot be rendered server-side; it has to be derived when the first changes.
 *
 * The data arrives as JSON in a data-* attribute and is read through `dataset`.
 * The previous implementation built a JavaScript object literal inside a
 * <script> block by interpolating names directly:
 *
 *     firstName: "{{ $registrant->user->first_name }}",
 *
 * Inside a <script> element the HTML parser does not decode entities, so
 * Blade's escaping does not apply the way it does elsewhere -- and a player
 * named `</script><img src=x onerror=...>` closes the element and executes.
 * A data attribute is decoded exactly once, into a string, which JSON.parse
 * then reads as data. There is no code path.
 *
 * Markup contract:
 *
 *     <select data-dependent-source='{"<id>":{"options":[{...}],"exclude":[...]}}'
 *             data-dependent-target="#user_id"
 *             data-dependent-placeholder="Select…">
 */
type Option = { id: string; firstName: string; lastName: string; nickname: string; email: string };
type Source = Record<string, { options: Option[]; exclude: string[] }>;

function repopulate(source: HTMLSelectElement): void {
    const target = document.querySelector<HTMLSelectElement>(source.dataset.dependentTarget ?? '');

    if (! target) {
        return;
    }

    let data: Source;

    try {
        data = JSON.parse(source.dataset.dependentSource ?? '{}') as Source;
    } catch {
        return;
    }

    const previous = target.value || target.dataset.dependentPrevious || '';
    const group = data[source.value] ?? { options: [], exclude: [] };

    target.replaceChildren();

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = source.dataset.dependentPlaceholder ?? '';
    target.appendChild(placeholder);

    for (const option of group.options) {
        if (group.exclude.includes(option.id)) {
            continue;
        }

        const el = document.createElement('option');
        el.value = option.id;
        // textContent, not innerHTML: a name is text.
        el.textContent = `${option.firstName} ${option.lastName} (${option.email})`;
        el.dataset.firstName = option.firstName;
        el.dataset.lastName = option.lastName;
        el.dataset.nickname = option.nickname;
        target.appendChild(el);
    }

    // Restore the prior choice when validation bounced the form back.
    if (previous) {
        target.value = previous;
    }
}

export function initDependentSelects(): void {
    const sources = document.querySelectorAll<HTMLSelectElement>('select[data-dependent-source]');

    sources.forEach((source) => repopulate(source));

    document.addEventListener('change', (event) => {
        const source = (event.target as HTMLElement | null)?.closest<HTMLSelectElement>('select[data-dependent-source]');

        if (source) {
            repopulate(source);
        }
    });
}
