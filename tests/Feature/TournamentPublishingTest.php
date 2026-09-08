<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * When a tournament is finished, and what saying so means.
 *
 * "Every result is in" is not a stable state in this application: registering a
 * late player shifts every recorded finish down, and results can be edited
 * through the admin CRUD. So completeness is a question an administrator asks
 * before publishing, and publishing is what freezes the answer.
 */
class TournamentPublishingTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    public function test_an_empty_tournament_is_not_complete(): void
    {
        // Nobody entered, so there is nothing to be finished. Without this
        // guard "every registrant has a result" is trivially true of zero
        // registrants and an empty tournament could be published.
        $this->assertFalse($this->tournament()->isComplete());
    }

    public function test_a_tournament_with_an_unscored_registrant_is_not_complete(): void
    {
        $tournament = $this->tournament();
        $players = User::factory()->count(2)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        $this->score($tournament, $players[0], 2, 50);

        $this->assertFalse($tournament->fresh()->isComplete());
    }

    public function test_a_tournament_where_everyone_has_finished_is_complete(): void
    {
        $tournament = $this->tournament();
        $players = User::factory()->count(2)->create();

        foreach ($players as $player) {
            $this->enter($tournament, $player);
        }

        $this->score($tournament, $players[0], 2, 50);
        $this->score($tournament, $players[1], 1, 100);

        $this->assertTrue($tournament->fresh()->isComplete());
    }

    public function test_a_result_for_someone_who_never_entered_does_not_complete_it(): void
    {
        // Counting results against registrants would pass here. The admin
        // results form validates that a user EXISTS, not that they registered,
        // so a stray result can outnumber the field without covering it.
        $tournament = $this->tournament();
        $entered = User::factory()->create();
        $stranger = User::factory()->create();

        $this->enter($tournament, $entered);
        $this->score($tournament, $stranger, 1, 100);

        $this->assertFalse($tournament->fresh()->isComplete());
    }

    public function test_a_tournament_starts_unpublished(): void
    {
        $this->assertFalse($this->tournament()->isPublished());
        $this->assertNull($this->tournament()->published_at);
    }

    public function test_publishing_is_recorded_as_a_time(): void
    {
        $tournament = $this->tournament();

        $tournament->forceFill(['published_at' => now()])->save();

        $this->assertTrue($tournament->fresh()->isPublished());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $tournament->fresh()->published_at);
    }

    public function test_an_unpublished_tournament_refuses_nothing(): void
    {
        $this->assertNull($this->tournament()->publishedRefusal());
    }

    public function test_the_refusal_names_the_tournament_and_says_what_to_do(): void
    {
        // Six call sites show this message. It has to say which tournament --
        // an administrator may have several open -- and what the way out is.
        $tournament = $this->tournament();
        $tournament->forceFill(['published_at' => now()])->save();

        $refusal = $tournament->publishedRefusal();

        $this->assertStringContainsString('Autumn Showdown', $refusal);
        $this->assertStringContainsString('published', $refusal);
        $this->assertStringContainsString('Unpublish', $refusal);
    }

    public function test_published_at_is_not_mass_assignable(): void
    {
        // Publishing sends messages to players. It is not something a tournament
        // edit form should be able to do by posting a field.
        $tournament = $this->tournament();

        $tournament->update(['published_at' => now()]);

        $this->assertFalse($tournament->fresh()->isPublished());
    }
}
