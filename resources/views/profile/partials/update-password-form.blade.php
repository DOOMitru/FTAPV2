<section class="l-stack">
    <header>
        <h2 class="card__title">{{ __('Update Password') }}</h2>

        <p class="field__hint">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="l-stack">
        @csrf
        @method('put')

        {{-- bag="updatePassword": this page renders three forms, and without the
             named bag a failure here would light up the fields of the others. --}}
        <x-field name="current_password" type="password" bag="updatePassword"
                 :label="__('Current Password')" autocomplete="current-password" />

        <x-field name="password" type="password" bag="updatePassword"
                 :label="__('New Password')" autocomplete="new-password" />

        <x-field name="password_confirmation" type="password" bag="updatePassword"
                 :label="__('Confirm Password')" autocomplete="new-password" />

        <div class="l-cluster">
            <x-btn variant="primary">{{ __('Save') }}</x-btn>

            @if (session('status') === 'password-updated')
                <p class="field__hint" x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2000)">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
