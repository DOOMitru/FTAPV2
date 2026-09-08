<?php

namespace Tests\Feature;

use App\Models\PointsStructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * A result can be recorded without a nickname.
 *
 * player_nickname validates as nullable, so a request that omits it leaves the
 * key absent from $validated -- and both store() and update() read it
 * unguarded, raising an ErrorException and returning 500 rather than any kind
 * of validation failure. The two forms always post an empty string, which is
 * why nothing had ever hit it; found while testing the publish lock, because
 * the guard tests post the minimum a route will accept.
 */
class ResultNicknameOmittedTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    public function test_a_result_can_be_created_without_a_nickname(): void
    {
        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);

        $this->actingAs($this->admin())->post(route('poker.results.store'), [
            'tournament_id' => $tournament->id,
            'points_structure_id' => $structure->id,
            'user_id' => $player->id,
            'player_name' => 'Wanda Reeve',
        ])->assertRedirect(route('poker.results.index'));

        $this->assertSame(1, $tournament->results()->count());
        $this->assertNull($tournament->results()->first()->player_nickname);
    }

    public function test_a_result_can_be_updated_without_a_nickname(): void
    {
        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);
        $result = $this->score($tournament, $player, 3, 40);

        $this->actingAs($this->admin())->put(route('poker.results.update', $result), [
            'tournament_id' => $tournament->id,
            'points_structure_id' => $structure->id,
            'user_id' => $player->id,
            'player_name' => 'Wanda Reeve',
        ])->assertRedirect(route('poker.results.index'));

        $this->assertSame(1, $result->fresh()->place);
        $this->assertNull($result->fresh()->player_nickname);
    }

    public function test_a_nickname_is_still_stored_when_given(): void
    {
        // The other half: ?? null must not have turned the field off.
        $structure = PointsStructure::create(['place' => 1, 'points' => 100]);
        $tournament = $this->tournament();
        $player = User::factory()->create();
        $this->enter($tournament, $player);

        $this->actingAs($this->admin())->post(route('poker.results.store'), [
            'tournament_id' => $tournament->id,
            'points_structure_id' => $structure->id,
            'user_id' => $player->id,
            'player_name' => 'Wanda Reeve',
            'player_nickname' => 'Ace',
        ]);

        $this->assertSame('Ace', $tournament->results()->first()->player_nickname);
    }
}
