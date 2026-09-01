@props(['sponsor' => null])

{{-- enctype is load-bearing. Without it the browser sends the field names but
     no file, so validation reports a missing logo and the form gives no clue
     why -- the input looked filled in. --}}
<form method="POST"
      action="{{ $sponsor ? route('sponsors.update', $sponsor) : route('sponsors.store') }}"
      enctype="multipart/form-data"
      class="l-stack">
    @csrf
    @if ($sponsor)
        @method('PUT')
    @endif

    <x-field name="name" :label="__('Sponsor Name')" :value="old('name', $sponsor?->name)" required autofocus />

    <x-field name="logo" type="file" :label="$sponsor ? __('Replace Logo') : __('Logo')" :required="! $sponsor" />

    @if ($sponsor)
        <p class="field__hint">{{ __('Leave this empty to keep the current logo.') }}</p>
    @endif

    <x-field name="website_url" type="url" :label="__('Website (optional)')"
             :value="old('website_url', $sponsor?->website_url)"
             placeholder="https://example.com" />

    <x-field name="tier" :label="__('Tier')">
        <select class="field__control" name="tier" id="tier" required>
            <option value="regular" @selected(old('tier', $sponsor?->tier ?? 'regular') === 'regular')>{{ __('Regular Sponsor') }}</option>
            <option value="premium" @selected(old('tier', $sponsor?->tier) === 'premium')>{{ __('Premium Sponsor') }}</option>
        </select>
    </x-field>

    <x-field name="sort_order" type="number" :label="__('Sort Order')"
             :value="old('sort_order', $sponsor?->sort_order ?? 0)" />

    <p class="field__hint">{{ __('Lower numbers appear first, within a tier. Premium sponsors always come before regular ones.') }}</p>

    <div class="l-cluster">
        <x-btn variant="primary">{{ $sponsor ? __('Save') : __('Add Sponsor') }}</x-btn>

        <a class="link" href="{{ route('sponsors.index') }}">{{ __('Cancel') }}</a>
    </div>
</form>
