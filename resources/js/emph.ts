/**
 * The emphasis markers PHP writes, and how to read them.
 *
 * One definition, imported by both halves of the app: confirm.ts writes these
 * into the Blade dialog with text nodes, and Emph.vue renders them in Vue.
 * The balance rule below is subtle enough that two copies of it would be a
 * genuine liability.
 */

/**
 * Must match EMPH in app/Support/helpers.php -- U+2063 INVISIBLE SEPARATOR.
 *
 * Invisible on purpose: a message travels as a plain string through a data
 * attribute, a prop or a flash, and the markers ride along without ever being
 * parsed as markup.
 */
export const EMPH = '⁣';

export interface EmphPart {
    text: string;
    /** True for the runs PHP marked as an entity's name. */
    marked: boolean;
}

/**
 * Split a marked message into its parts.
 *
 * Odd-numbered segments are the marked ones, so balanced markers always split
 * into an odd number of parts. An even count means a marker lost its partner,
 * and emphasising everything from there to the end of the sentence is a louder
 * way to be wrong than emphasising nothing -- so an unbalanced message gets
 * none.
 */
export function emphParts(message: string): EmphPart[] {
    const parts = message.split(EMPH);

    if (parts.length % 2 === 0) {
        return [{ text: parts.join(''), marked: false }];
    }

    return parts
        .map((text, index) => ({ text, marked: index % 2 === 1 }))
        .filter((part) => part.text !== '');
}

/** The message with its markers removed, for anywhere that cannot emphasise. */
export function withoutEmph(message: string): string {
    return message.split(EMPH).join('');
}
