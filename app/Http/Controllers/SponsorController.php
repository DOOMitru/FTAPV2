<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Sponsors shown on the home page.
 *
 * The about page sells "your logo goes on this site" as a deliverable, so this
 * wall is a promise the league makes to a paying business. It was a hardcoded
 * array until now, which meant honouring that promise required a deploy.
 */
class SponsorController extends Controller
{
    public function index(): View
    {
        // ordered(), the same scope the home page uses, so an administrator
        // arranging the list sees the sequence the page will actually render.
        return view('sponsors.index', ['sponsors' => Sponsor::ordered()->get()]);
    }

    public function create(): View
    {
        return view('sponsors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, logoRequired: true);

        $validated['logo_path'] = $request->file('logo')->store('sponsor-logos', 'public');
        unset($validated['logo']);

        Sponsor::create($validated);

        return redirect()->route('sponsors.index')->with('status', 'Sponsor added successfully!');
    }

    public function edit(Sponsor $sponsor): View
    {
        return view('sponsors.edit', compact('sponsor'));
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

        return redirect()->route('sponsors.index')->with('status', 'Sponsor updated successfully!');
    }

    public function destroy(Sponsor $sponsor): RedirectResponse
    {
        $path = $sponsor->logo_path;

        $sponsor->delete();

        // The file goes with the row. Nothing else references it, so leaving it
        // would accumulate artwork on disk that no page can reach and no screen
        // can list.
        Storage::disk('public')->delete($path);

        return redirect()->route('sponsors.index')->with('status', 'Sponsor deleted successfully!');
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
