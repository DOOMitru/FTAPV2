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
}
