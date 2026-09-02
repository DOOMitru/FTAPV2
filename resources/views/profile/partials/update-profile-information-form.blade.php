<section class="l-stack">
    <header>
        <h2 class="card__title">{{ __('Profile Information') }}</h2>

        <p class="field__hint">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    {{-- A separate form, targeted by the button below via form="send-verification".
         It must stay outside the profile form: nesting forms is invalid HTML and
         the button would post the wrong action. --}}
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="l-stack"
          enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div>
            <span class="field__label">{{ __('Profile photo') }}</span>

            <div class="field__media">
                <x-avatar :user="$user" size="lg" decorative />

                <label class="field__file">
                    <span class="u-visually-hidden">{{ __('Choose profile photo') }}</span>
                    <input class="field__file" type="file" name="profile_image" accept="image/*">
                </label>
            </div>

            <x-input-error :messages="$errors->get('profile_image')" />
        </div>

        <div class="l-grid">
            <x-field name="first_name" :label="__('First Name')"
                     :value="old('first_name', $user->first_name)"
                     required autofocus autocomplete="given-name" />

            <x-field name="last_name" :label="__('Last Name')"
                     :value="old('last_name', $user->last_name)"
                     required autocomplete="family-name" />
        </div>

        {{-- Not decoration: the nickname drives the season standings naming rule
             and the top bar's display_name. --}}
        <x-field name="nickname" :label="__('Nickname')"
                 :value="old('nickname', $user->nickname)" autocomplete="nickname" />

        <div>
            <x-field name="email" type="email" :label="__('Email')"
                     :value="old('email', $user->email)" required autocomplete="username" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="field__hint">
                    {{ __('Your email address is unverified.') }}

                    <button class="link" form="send-verification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <x-alert variant="success">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </x-alert>
                @endif
            @endif
        </div>

        @if ($user->is_admin)
            <div>
                <label class="field__check" for="is_admin">
                    <input id="is_admin" type="checkbox" name="is_admin" value="1"
                           {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                    <span>{{ __('Is Admin') }}</span>
                </label>

                <x-input-error :messages="$errors->get('is_admin')" />
            </div>
        @endif

        <div class="l-cluster l-cluster--end">
            <x-btn variant="primary">{{ __('Save') }}</x-btn>

            @if (session('status') === 'profile-updated')
                <p class="field__hint" x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2000)">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
