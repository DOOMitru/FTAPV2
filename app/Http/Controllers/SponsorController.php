<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sponsors shown on the home page.
 *
 * The about page sells "your logo goes on this site" as a deliverable, so this
 * wall is a promise the league makes to a paying business. It was a hardcoded
 * array until now, which meant honouring that promise required a deploy.
 */
class SponsorController extends Controller
{
    public function index(): \Inertia\Response
    {
        // ordered(), the same scope the home page uses, so an administrator
        // arranging the list sees the sequence the page will actually render.
        //
        // Not paginated, and no Paginate::props: this is a short, hand-kept
        // list, and the home page renders all of it at once anyway.
        return \Inertia\Inertia::render('Sponsors/Index', [
            'sponsors' => Sponsor::ordered()->get()->map(fn (Sponsor $sponsor) => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                // The stored value, not a prettied host: this is the screen an
                // administrator checks the setting on, so it shows what was
                // actually saved.
                'website_url' => $sponsor->website_url,
                'logo_url' => $sponsor->logoUrl(),
                'is_premium' => $sponsor->isPremium(),
                'tier_label' => $sponsor->isPremium() ? __('Premium') : __('Regular'),
                'delete_confirm' => __('Delete :name? Their uploaded logo is deleted too, and that cannot be undone.', [
                    'name' => emph($sponsor->name),
                ]),
            ])->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sponsors/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, logoRequired: true);

        $validated['logo_path'] = $request->file('logo')->store('sponsor-logos', 'public');
        unset($validated['logo']);

        $sponsor = Sponsor::create($validated);

        return redirect()->route('sponsors.index')->with('status', __('Sponsor :name added.', [
            'name' => emph($sponsor->name),
        ]));
    }

    public function edit(Sponsor $sponsor): Response
    {
        return Inertia::render('Sponsors/Edit', [
            'sponsor' => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'website_url' => $sponsor->website_url,
                'tier' => $sponsor->tier,
                'sort_order' => $sponsor->sort_order,

                // The URL, not the path. logoUrl() is where this app turns one
                // into the other, and a page rebuilding it from logo_path
                // would be a second definition of the storage layout.
                'logo_url' => $sponsor->logoUrl(),
            ],
        ]);
    }

    public function update(Request $request, Sponsor $sponsor): RedirectResponse
    {
        // The logo is optional here. An edit that only changes a name must not
        // demand the artwork again -- and must not silently lose it either.
        $validated = $this->validated($request, logoRequired: false);
        unset($validated['logo']);

        if ($request->hasFile('logo')) {
            $previous = $sponsor->logo_path;
            $validated['logo_path'] = $request->file('logo')->store('sponsor-logos', 'public');

            // Deleted AFTER the replacement is stored: doing it first would
            // leave the sponsor with no logo if the upload then failed. Every
            // edit would otherwise leave an orphan nothing ever references.
            Storage::disk('public')->delete($previous);
        }

        $sponsor->update($validated);

        return redirect()->route('sponsors.index')->with('status', __('Sponsor :name updated.', [
            'name' => emph($sponsor->name),
        ]));
    }

    public function destroy(Sponsor $sponsor): RedirectResponse
    {
        $path = $sponsor->logo_path;
        // Read before the row goes: after delete() the model still holds its
        // attributes in memory, but relying on that makes the message depend
        // on an implementation detail rather than on a value we took.
        $name = $sponsor->name;

        $sponsor->delete();

        // The file goes with the row. Nothing else references it, so leaving it
        // would accumulate artwork on disk that no page can reach and no screen
        // can list.
        Storage::disk('public')->delete($path);

        return redirect()->route('sponsors.index')->with('status', __('Sponsor :name deleted.', [
            'name' => emph($name),
        ]));
    }

    /**
     * One rule set for both writes, so create and edit cannot drift apart.
     *
     * SVG is accepted. It is a document rather than a raster and can carry
     * script, and it is served from this app's own origin -- but only
     * administrators can upload here, and they can already do far more through
     * the rest of the admin area. If sponsor uploads are ever opened to
     * non-administrators, drop svg from this list or serve the files from a
     * separate domain.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $logoRequired): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'tier' => ['required', 'in:premium,regular'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => [
                $logoRequired ? 'required' : 'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:2048',
            ],
        ]);
    }
}
