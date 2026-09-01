<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sponsors on the home page, managed rather than hardcoded.
 *
 * The about page sells "your logo goes on this site" as a deliverable, so the
 * wall is a promise the league makes to a paying business. It has to be
 * editable without a deploy, and its order has to be one thing rather than a
 * property of whichever page is rendering it.
 */
class SponsorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sponsor_defaults_to_the_regular_tier(): void
    {
        $sponsor = Sponsor::create(['name' => 'Ace High', 'logo_path' => 'sponsor-logos/a.png']);

        $this->assertSame('regular', $sponsor->fresh()->tier);
        $this->assertFalse($sponsor->isPremium());
        // The unsaved instance too: a schema default is applied on INSERT and
        // never hydrated back, so without a model default this would be null
        // until the row was reloaded.
        $this->assertSame('regular', $sponsor->tier);
    }

    public function test_ordered_puts_premium_first_then_sort_order_then_name(): void
    {
        // The premium sponsor deliberately carries the HIGHEST sort_order. Tier
        // has to outrank order, and a fixture where premium also happened to
        // sort first would prove nothing.
        Sponsor::create(['name' => 'Zed Regular', 'logo_path' => 'x.png', 'sort_order' => 1]);
        Sponsor::create(['name' => 'Abe Regular', 'logo_path' => 'x.png', 'sort_order' => 1]);
        Sponsor::create(['name' => 'Early Regular', 'logo_path' => 'x.png', 'sort_order' => 0]);
        Sponsor::create(['name' => 'Prem', 'logo_path' => 'x.png', 'tier' => 'premium', 'sort_order' => 9]);

        $this->assertSame(
            ['Prem', 'Early Regular', 'Abe Regular', 'Zed Regular'],
            Sponsor::ordered()->pluck('name')->all()
        );
    }

    public function test_logo_url_points_at_the_public_disk(): void
    {
        $sponsor = Sponsor::create(['name' => 'Ace High', 'logo_path' => 'sponsor-logos/a.png']);

        $this->assertStringContainsString('/storage/sponsor-logos/a.png', $sponsor->logoUrl());
    }

    public function test_the_factory_produces_a_regular_sponsor_and_can_produce_a_premium_one(): void
    {
        $this->assertFalse(Sponsor::factory()->create()->isPremium());
        $this->assertTrue(Sponsor::factory()->premium()->create()->isPremium());
    }

    public function test_only_premium_is_promoted_and_every_other_tier_sorts_together(): void
    {
        // This is what the CASE expression buys over a plain orderBy('tier').
        // Alphabetically 'premium' happens to precede 'regular', so the simpler
        // version passes the test above by luck -- but it would order an
        // unrecognised tier by its NAME rather than grouping it with the
        // non-premium sponsors. 'aaa-unknown' sorts before 'premium'
        // alphabetically and must still render after it.
        Sponsor::create(['name' => 'Unknown Tier', 'logo_path' => 'x.png', 'tier' => 'aaa-unknown']);
        Sponsor::create(['name' => 'Prem', 'logo_path' => 'x.png', 'tier' => 'premium']);

        $this->assertSame(['Prem', 'Unknown Tier'], Sponsor::ordered()->pluck('name')->all());
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_creating_a_sponsor_stores_the_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('ace.png'),
            'tier' => 'premium',
        ])->assertRedirect();

        $sponsor = Sponsor::first();

        $this->assertNotNull($sponsor);
        $this->assertTrue($sponsor->isPremium());
        Storage::disk('public')->assertExists($sponsor->logo_path);
    }

    public function test_a_sponsor_cannot_be_created_without_a_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'tier' => 'regular',
        ])->assertSessionHasErrors('logo');

        $this->assertSame(0, Sponsor::count());
    }

    public function test_a_non_image_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->create('accounts.pdf', 100, 'application/pdf'),
            'tier' => 'regular',
        ])->assertSessionHasErrors('logo');

        $this->assertSame(0, Sponsor::count());
    }

    public function test_replacing_a_logo_deletes_the_old_file(): void
    {
        // Otherwise every edit leaves an orphan on disk that nothing will ever
        // reference, notice, or clean up.
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('first.png'),
            'tier' => 'regular',
        ]);

        $sponsor = Sponsor::first();
        $original = $sponsor->logo_path;

        $this->actingAs($admin)->put(route('sponsors.update', $sponsor), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('second.png'),
            'tier' => 'regular',
        ]);

        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($sponsor->fresh()->logo_path);
    }

    public function test_editing_without_a_new_logo_keeps_the_existing_one(): void
    {
        // An edit that only changes the name must not demand the artwork again,
        // and must not lose it.
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('only.png'),
            'tier' => 'regular',
        ]);

        $sponsor = Sponsor::first();
        $original = $sponsor->logo_path;

        $this->actingAs($admin)->put(route('sponsors.update', $sponsor), [
            'name' => 'Ace High Beverages',
            'tier' => 'regular',
        ])->assertRedirect();

        $this->assertSame('Ace High Beverages', $sponsor->fresh()->name);
        $this->assertSame($original, $sponsor->fresh()->logo_path);
        Storage::disk('public')->assertExists($original);
    }

    public function test_deleting_a_sponsor_deletes_its_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('sponsors.store'), [
            'name' => 'Ace High',
            'logo' => UploadedFile::fake()->image('gone.png'),
            'tier' => 'regular',
        ]);

        $sponsor = Sponsor::first();
        $path = $sponsor->logo_path;

        $this->actingAs($admin)->delete(route('sponsors.destroy', $sponsor));

        $this->assertSame(0, Sponsor::count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_player_cannot_reach_sponsor_administration(): void
    {
        $player = User::factory()->create(['is_admin' => false]);

        $this->actingAs($player)->get(route('sponsors.index'))->assertForbidden();
        $this->actingAs($player)->get(route('sponsors.create'))->assertForbidden();
        $this->actingAs($player)->post(route('sponsors.store'), [
            'name' => 'Sneaky', 'tier' => 'regular',
        ])->assertForbidden();

        $this->assertSame(0, Sponsor::count());
    }

    public function test_the_home_page_renders_sponsors_from_the_database(): void
    {
        Sponsor::factory()->create(['name' => 'Regularly']);
        Sponsor::factory()->premium()->create(['name' => 'Premiumly']);

        $response = $this->get('/');
        $response->assertOk()->assertSee('Premiumly')->assertSee('Regularly');

        // Premium first in the RENDERED MARKUP, not merely in a collection.
        // Nothing else pins this wall -- the hardcoded array it replaces was
        // never covered -- so if the view stopped calling ordered() no other
        // test would notice.
        $body = $response->getContent();
        $this->assertLessThan(strpos($body, 'Regularly'), strpos($body, 'Premiumly'));
    }

    public function test_a_premium_sponsor_is_marked_so_in_the_markup(): void
    {
        Sponsor::factory()->premium()->create(['name' => 'Premiumly']);

        $this->get('/')->assertOk()->assertSee('p-sponsor--premium', false);
    }

    public function test_the_sponsor_section_is_absent_when_there_are_none(): void
    {
        // A "Proudly Supported By" heading over an empty grid advertises that
        // nobody sponsors the league.
        //
        // Asserted on the grid class, not the heading text. The heading is
        // rendered through x-p-hero's `highlight` prop, which wraps part of it
        // in a span -- so the literal words never appear contiguously in the
        // markup and assertDontSee on them passes whether the section renders
        // or not.
        $this->get('/')->assertOk()->assertDontSee('p-sponsors', false);
    }

    public function test_a_sponsor_with_a_website_links_out_safely(): void
    {
        Sponsor::factory()->create(['name' => 'Linked', 'website_url' => 'https://example.com']);

        $this->get('/')->assertOk()
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('https://example.com', false);
    }

    public function test_a_sponsor_without_a_website_renders_no_link(): void
    {
        Sponsor::factory()->create(['name' => 'Unlinked']);

        $this->get('/')->assertOk()
            ->assertSee('Unlinked')
            ->assertDontSee('rel="noopener noreferrer"', false);
    }

    public function test_the_logo_carries_the_sponsor_name_as_alt_text(): void
    {
        // The logo IS the content here, not decoration, so empty alt would
        // leave a screen reader with nothing where a sponsor should be.
        Sponsor::factory()->create(['name' => 'Ace High']);

        $this->get('/')->assertOk()->assertSee('alt="Ace High"', false);
    }

    public function test_the_admin_table_links_the_logo_and_drops_the_website_column(): void
    {
        // The website reached the row twice before -- a "Visit" link in its own
        // column, and a sort_order column nobody reads. It is now the logo's
        // href, with the address itself under the name.
        Sponsor::create([
            'name' => 'Linked Sponsor',
            'logo_path' => 'sponsor-logos/a.png',
            'website_url' => 'https://linked.example',
        ]);

        $response = $this->actingAs($this->admin())->get(route('sponsors.index'))->assertOk();

        $response->assertSee('sponsor-thumb-link', false);
        $response->assertSee('href="https://linked.example"', false);
        // The address, under the name, as stored.
        $response->assertSee('https://linked.example');

        // Both columns are gone: their headers and the old link's text.
        $response->assertDontSee('>Website<', false);
        $response->assertDontSee('>Order<', false);
        $response->assertDontSee('>Visit<', false);
    }

    public function test_the_admin_table_leaves_a_logo_unlinked_without_a_website(): void
    {
        Sponsor::create(['name' => 'No Site Sponsor', 'logo_path' => 'sponsor-logos/b.png']);

        $response = $this->actingAs($this->admin())->get(route('sponsors.index'))->assertOk();

        $response->assertSee('No Site Sponsor');
        $response->assertDontSee('sponsor-thumb-link', false);
    }

    public function test_the_linked_logo_carries_a_name_and_the_unlinked_one_does_not(): void
    {
        // An image is the accessible NAME of a link that contains nothing else,
        // so the linked logo's alt has to be the sponsor. Unlinked it is
        // decoration -- the name is in the very next cell -- and repeating it
        // there would have a screen reader say it twice.
        Sponsor::create([
            'name' => 'Named Alt',
            'logo_path' => 'sponsor-logos/a.png',
            'website_url' => 'https://named.example',
        ]);
        Sponsor::create(['name' => 'Empty Alt', 'logo_path' => 'sponsor-logos/b.png']);

        $response = $this->actingAs($this->admin())->get(route('sponsors.index'))->assertOk();

        $response->assertSee('alt="Named Alt"', false);
        $response->assertDontSee('alt="Empty Alt"', false);
        $response->assertSee('alt=""', false);
    }
}
