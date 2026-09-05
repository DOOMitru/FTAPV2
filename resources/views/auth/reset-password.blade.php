<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="l-stack">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field name="email" type="email" :label="__('Email')"
                 :value="old('email', $request->email)" required autofocus autocomplete="username" />

        <x-field name="password" type="password" :label="__('Password')" required autocomplete="new-password" />

        <x-field name="password_confirmation" type="password" :label="__('Confirm Password')"
                 required autocomplete="new-password" />

        <div class="l-cluster l-cluster--end">
            <x-btn variant="primary">{{ __('Reset Password') }}</x-btn>
        </div>
    </form>
</x-guest-layout>
