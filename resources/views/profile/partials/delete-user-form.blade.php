<section class="l-stack">
    <header>
        <h2 class="card__title">{{ __('Delete Account') }}</h2>

        <p class="field__hint">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <div>
        <x-btn variant="danger" type="button"
               x-data=""
               x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete Account') }}
        </x-btn>
    </div>

    {{-- :show is true on the wrong-password path, so the modal reopens with the
         error. That is the case Phase 1 Task 12 fixed: $watch skips its first
         evaluation, so x-init never fired on a modal that started open and
         neither the focus trap nor the scroll lock engaged. It is x-effect now. --}}
    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <div class="modal__body l-stack">
                <h2 class="card__title">{{ __('Are you sure you want to delete your account?') }}</h2>

                <p class="field__hint">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>

                <x-field name="password" type="password" bag="userDeletion"
                         :label="__('Password')" :placeholder="__('Password')" />
            </div>

            <div class="modal__footer">
                <x-btn variant="ghost" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-btn>

                <x-btn variant="danger">{{ __('Delete Account') }}</x-btn>
            </div>
        </form>
    </x-modal>
</section>
