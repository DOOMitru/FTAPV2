@props(['variant' => 'info'])

{{--
    The flash, pinned to the bottom of the viewport.

    A toast is an ALERT IN A DIFFERENT PLACE, so x-alert stays in charge of the
    variant colours, the live-region role and the emphasis markers; this adds
    the elevation and the way out. The twin is Toast.vue, and
    ComponentParityTest holds the two to the same class hooks.

    Rendered SERVER-SIDE, like the confirmation dialog: emph_split runs in PHP
    where it already does, the role is in the markup rather than assigned after
    mount, and the message is on the page with JavaScript switched off.
    toast.ts only starts the timer and wires the button.

    data-toast-persist rather than a look at the variant class: what the
    stylesheet calls a colour is not JavaScript's business, and "this one waits
    to be read" is the actual rule.
--}}
<div class="toast-host" data-toast>
    {{-- Bound rather than wrapped in an @if: Blade cannot compile a directive
         inside a component's attribute list, and the attribute bag already
         omits a false value and renders a true one bare. --}}
    <x-alert :variant="$variant" class="toast"
             :data-toast-persist="$variant === 'danger'">
        {{ $slot }}

        <x-slot:control>
            <button type="button" class="toast__dismiss" data-toast-dismiss>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>

                {{-- From content rather than aria-label, so it is translated
                     with the rest of the page. --}}
                <span class="u-visually-hidden">{{ __('Dismiss') }}</span>
            </button>
        </x-slot:control>
    </x-alert>
</div>
