<?php

namespace Tests\Feature;

use App\Models\Sponsor;
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
}
