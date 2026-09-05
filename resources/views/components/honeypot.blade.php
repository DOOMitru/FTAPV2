{{--
    A spam trap, not a form field. ContactController::store returns the ordinary
    success message when this is filled, so a bot learns nothing from being
    caught.

    It exists as a component for one reason: as bare markup it is an unlabelled,
    hidden text input that looks exactly like something to delete during a
    redesign — and nothing in the test suite fails when it goes. The only
    symptom is spam, weeks later. Named and commented, it is much harder to
    remove by accident.

    Must stay: hidden, out of the tab order, and named `company`, which is what
    the controller checks. `id` is a prop only because two forms can appear on
    one page and ids must stay unique.
--}}
@props(['id' => 'company'])

<input type="text" name="company" id="{{ $id }}" tabindex="-1" autocomplete="off" hidden>
