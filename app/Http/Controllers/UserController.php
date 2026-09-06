<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PlayerApproved;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Columns a search looks at. Interpolated into SQL below, so this list is
     * the only thing that may ever go in it -- never anything from a request.
     */
    private const SEARCHABLE = ['first_name', 'last_name', 'nickname', 'email'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->orderBy('first_name')
            ->paginate(15)
            // Without this, page 2 of a search drops the term and silently
            // shows page 2 of everybody -- which looks like the search broke.
            ->withQueryString();

        // Not paginated. A queue that needs paging is a queue nobody is
        // working; if it ever grows that large that is the signal, not a
        // pagination bug. Oldest first, because the person who has waited
        // longest is the one to deal with.
        $pending = User::awaitingApproval()->orderBy('created_at')->get();

        return view('users.index', compact('users', 'pending', 'search'));
    }

    /**
     * Narrow a user query to those matching every word of a search.
     *
     * Every term has to match something, but not the same something: that is
     * what lets "ada lovelace" find the row whose first_name is Ada and whose
     * last_name is Lovelace, which no single LIKE over either column can.
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/', $search) as $term) {
            // lower() on both sides rather than trusting LIKE. SQLite and a
            // ci-collated MySQL fold ASCII case; Postgres does not, and a
            // search that quietly turns case-sensitive is not something anyone
            // reports -- they just stop using it.
            $like = '%'.mb_strtolower($term).'%';

            $query->where(function (Builder $q) use ($like) {
                foreach (self::SEARCHABLE as $column) {
                    $q->orWhereRaw("lower({$column}) like ?", [$like]);
                }
            });
        }

        return $query;
    }

    public function create(): View
    {
        return view('users.create');
    }

    /**
     * Register a player directly.
     *
     * Approved on the spot: an administrator creating the account IS the
     * decision, so recording anything else would be a fiction.
     *
     * There is no password field. The account gets an unusable random one and
     * the player sets their own through a password-reset link -- a signed,
     * expiring, single-use token the framework already issues, with the whole
     * flow already in the app. A parallel invite-token system would mean a
     * second table, a second expiry policy and a second set of security
     * assumptions to do the same job.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'] ?? null,
            'email' => $validated['email'],
            // Nobody chooses this and nobody is told it. The reset link below
            // is the only way in.
            'password' => Hash::make(Str::random(40)),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ]);

        $user->forceFill([
            'approval_status' => 'approved',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ])->save();

        // Verification is NOT granted here. An administrator vouches for the
        // person, not for the address.
        event(new Registered($user));

        // ONE token, used twice. Password::createToken deletes any existing
        // token for the user, so calling sendResetLink() and then createToken()
        // would email a link that the second call had already invalidated.
        $token = Password::createToken($user);
        $user->sendPasswordResetNotification($token);

        return redirect()->route('users.index')
            ->with('status', $user->first_name.' '.$user->last_name.' was registered and approved.')
            // Surfaced as well as sent: MAIL_MAILER is log, so a link that is
            // only emailed reaches nobody. It stays useful once a mailer exists,
            // for when a player says the mail never arrived.
            ->with('invite_url', route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]));
    }

    /**
     * Re-issue the password link for a player.
     *
     * Offered unconditionally: whether an account has ever set a password is
     * not knowable from the schema -- every account has a hash, and the random
     * one a new player starts with is indistinguishable from a chosen one.
     */
    public function sendInvite(User $user): RedirectResponse
    {
        // One token, used twice: createToken() deletes any existing token, so
        // issuing a second to surface a copy would invalidate the one sent.
        $token = Password::createToken($user);
        $user->sendPasswordResetNotification($token);

        return back()
            ->with('status', 'A password link was sent to '.$user->email.'.')
            ->with('invite_url', route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]));
    }

    /**
     * Re-issue the email verification link for a player.
     *
     * EmailVerificationNotificationController cannot serve this: it acts on
     * $request->user(), the authenticated actor, and an administrator acting on
     * someone else's account is a different operation.
     *
     * Unlike the password token, a verification link is a stateless signed URL,
     * so the sent one and the surfaced one are independently valid. The expiry
     * below is read from the same config the notification uses, so the copy an
     * administrator hands over cannot outlive the one in the email.
     */
    public function sendVerification(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('error', $user->email.' is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return back()
            ->with('status', 'A verification link was sent to '.$user->email.'.')
            ->with('verification_url', URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
            ));
    }

    /**
     * Admit a player to the league.
     *
     * Also the route back for a REJECTED account. Once rejected it has left the
     * pending queue, so without this the reversibility that justified keeping
     * the row would be a claim the interface does not support.
     */
    public function approve(User $user): RedirectResponse
    {
        // Captured before the write: this method is also the route back for a
        // rejected account, so it can be reached for someone already approved.
        // Notifying on every visit to that control would be noise, and the
        // player has learned nothing new.
        $wasAlreadyApproved = $user->isApproved();

        $user->forceFill([
            'approval_status' => 'approved',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ])->save();

        if (! $wasAlreadyApproved) {
            $user->notify(new PlayerApproved());
        }

        return back()->with('status', $user->first_name.' '.$user->last_name.' can now enter tournaments.');
    }

    /**
     * Refuse a player, keeping the account.
     *
     * Deleting would make the decision unrecoverable, lose any record that it
     * was made, and let the same person re-register into a clean slate the next
     * minute.
     */
    public function reject(User $user): RedirectResponse
    {
        $user->forceFill([
            'approval_status' => 'rejected',
            'approval_decided_at' => now(),
            'approval_decided_by' => auth()->id(),
        ])->save();

        return back()->with('status', $user->first_name.' '.$user->last_name.' was not approved.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): View
    {
        // Resolved here rather than via a relation: approval_decided_by is a
        // plain string column, not a foreign key, because users.id is a ULID
        // and the row it points at may since have been deleted. Null is the
        // normal case for accounts the migration grandfathered.
        $decidedBy = $user->approval_decided_by
            ? User::find($user->approval_decided_by)
            : null;

        return view('users.show', compact('user', 'decidedBy'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'is_admin' => 'boolean',
        ]);

        $user->update($validated);

        return redirect()->route('users.index')->with('status', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully!');
    }
}
