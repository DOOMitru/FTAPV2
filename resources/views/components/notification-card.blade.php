@props(['notification'])

@php
    $data = $notification->data;
    $place = (int) ($data['place'] ?? 0);
    $read = $notification->read_at !== null;

    // The tier. 1-3 reuse the podium's medal tokens so the message matches the
    // podium the player just saw; a scoring finish below that gets the accent
    // instead, because it is worth telling someone about without claiming a
    // medal it did not win.
    //
    // The default arm also catches a row this card was not written for. The
    // store is general -- a placement is one type of notification, not the only
    // one it can hold -- and an unfamiliar row should render plainly rather
    // than take a page down.
    $tier = match ($place) {
        1 => 'notification--gold',
        2 => 'notification--silver',
        3 => 'notification--bronze',
        default => 'notification--scored',
    };
@endphp

<article class="notification {{ $tier }}{{ $read ? '' : ' notification--unread' }}">
    <span class="notification__place">{{ \Illuminate\Support\Number::ordinal($place) }}</span>

    <div class="notification__body">
        <p class="notification__title">{{ $data['tournament_name'] ?? __('A tournament') }}</p>

        <p class="notification__meta">
            {{ number_format((int) ($data['points'] ?? 0)) }} {{ __('pts') }}
            @isset ($data['played_on'])
                &middot; {{ \Illuminate\Support\Carbon::parse($data['played_on'])->format('M j, Y') }}
            @endisset
        </p>
    </div>

    <div class="notification__actions">
        {{-- One route for both directions; the label says which way this click
             goes, because the row already knows where it stands. --}}
        <form action="{{ route('notifications.update', $notification->id) }}" method="POST">
            @csrf
            @method('PATCH')

            <x-action :icon="$read ? 'undo' : 'approve'"
                      :label="$read ? __('Mark as unread') : __('Mark as read')" />
        </form>

        {{-- Only once read. Deleting something you have not looked at is how a
             player loses a result they never saw, and the controller refuses it
             too -- this hides a control that would fail rather than inventing a
             second rule. --}}
        @if ($read)
            <form action="{{ route('notifications.destroy', $notification->id) }}" method="POST"
                  data-confirm="{{ __('Delete this notification about :tournament?', [
                      'tournament' => $data['tournament_name'] ?? __('a tournament'),
                  ]) }}">
                @csrf
                @method('DELETE')

                <x-action icon="delete" :label="__('Delete notification')" danger />
            </form>
        @endif
    </div>
</article>
