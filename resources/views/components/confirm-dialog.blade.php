{{--
    The application's confirmation dialog. One per page, rendered by the
    layouts, driven by the `data-confirm` attribute already on every form that
    needs asking about -- so no call site changes to adopt it.

    A native <dialog> opened with showModal(), which is what buys the things a
    hand-rolled overlay has to reimplement and usually gets subtly wrong: focus
    moves into the dialog and is trapped there, Escape closes it, the rest of
    the page goes inert to the mouse and to assistive technology, and it renders
    in the top layer so nothing on the page can stack above it. The Alpine
    x-modal beside it spends about thirty lines of an attribute doing that by
    hand; this spends none.

    method="dialog" is the other half. A submit button inside such a form closes
    the dialog and records which button did it in returnValue, so neither button
    needs a handler of its own -- and Escape lands in the same place with an
    empty returnValue, which reads as "not confirmed" without a special case.

    The message is written into the paragraph as text by confirm.ts. It is never
    interpolated into markup here: that is the whole reason it lives in a data
    attribute rather than an inline onsubmit, and rendering it as HTML would
    give back exactly the injection that arrangement exists to prevent.
--}}
<dialog class="confirm" data-confirm-dialog aria-labelledby="confirm-dialog-message">
    <form method="dialog">
        <p class="confirm__message" id="confirm-dialog-message" data-confirm-message></p>

        <div class="confirm__actions">
            {{-- Explicit type="submit": x-btn gives ghost a type="button" so a
                 dismiss cannot post a form by accident, and inside a dialog
                 form that is precisely the button that would fail to close
                 anything. --}}
            <x-btn variant="ghost" type="submit" value="cancel">{{ __('Cancel') }}</x-btn>

            {{-- The variant is swapped at open time: most of these actions are
                 destructive, but not all, and the browser's own dialog had no
                 colour to get wrong where a styled one does. --}}
            <x-btn variant="danger" type="submit" value="confirm" data-confirm-accept>{{ __('Confirm') }}</x-btn>
        </div>
    </form>
</dialog>
