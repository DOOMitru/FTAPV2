<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTournaments;
use Tests\TestCase;

/**
 * Asking to publish, the moment the field is settled.
 *
 * Publishing is the end of a tournament -- it tells every player who scored
 * where they came and locks the record -- but its button lives in the page
 * header, and an administrator who has just worked down a field of twenty is
 * looking at the bottom of a list. It was possible to finish a night and simply
 * not notice that anything was left to do.
 *
 * So the last elimination asks. Once: the offer is flashed, so it is gone on
 * the next request whichever way it is answered, and declining records nothing
 * -- there is nothing to record, because the next request will not ask again.
 */
class PublishOfferTest extends TestCase
{
    use BuildsTournaments, RefreshDatabase;

    private function player(string $first): User
    {
        return User::factory()->create([
            'first_name' => $first, 'last_name' => 'Player', 'approval_status' => 'approved',
        ]);
    }

    private function eliminate(\App\Models\PokerTournament $tournament, User $player, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin())
            ->from(route('tournaments.show', $tournament))
            ->post(route('poker.tournaments.eliminate', $tournament), ['user_id' => $player->id]);
    }

    public function test_the_last_elimination_offers_to_publish(): void
    {
        $tournament = $this->tournament();
        $a = $this->player('Ann');
        $b = $this->player('Bob');

        foreach ([$a, $b] as $p) {
            $this->enter($tournament, $p);
        }

        // Not yet: one player is still in, so there is nothing to publish.
        $this->eliminate($tournament, $a)->assertSessionMissing('offer_publish');

        // The last one settles the field.
        $this->eliminate($tournament, $b)->assertSessionHas('offer_publish', true);
    }

    public function test_the_offer_opens_the_dialog_on_the_page_it_lands_on(): void
    {
        $tournament = $this->tournament();
        $player = $this->player('Solo');
        $this->enter($tournament, $player);

        $html = $this->followRedirects($this->eliminate($tournament, $player))->getContent();

        $this->assertStringContainsString('publish-offer', $html);
        $this->assertMatchesRegularExpression(
            '/class="confirm publish-offer"\s*\n?\s*x-init="\$el\.showModal\(\)"/',
            $html,
            'The dialog must open itself on the request that flashed the offer.'
        );
    }

    public function test_it_asks_once_and_not_again_on_a_reload(): void
    {
        // Flashed, so the next request does not ask. An administrator who
        // declines and keeps working is not asked the same question on every
        // page load until they give in.
        $tournament = $this->tournament();
        $player = $this->player('Solo');
        $this->enter($tournament, $player);

        $this->followRedirects($this->eliminate($tournament, $player));

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent();

        // The dialog is still in the document -- the field is still settled --
        // but nothing opens it.
        $this->assertStringContainsString('publish-offer', $html);
        $this->assertStringNotContainsString('x-init="$el.showModal()"', $html);
    }

    public function test_the_dialog_publishes_without_asking_a_second_time(): void
    {
        // It IS the confirmation. A data-confirm here would put a dialog on top
        // of a dialog to ask the question just asked.
        $tournament = $this->tournament();
        $player = $this->player('Solo');
        $this->enter($tournament, $player);

        $html = $this->followRedirects($this->eliminate($tournament, $player))->getContent();

        $at = strpos($html, 'publish-offer');
        $dialog = substr($html, $at, (int) strpos($html, '</dialog>', $at) - $at);

        $this->assertStringContainsString(route('poker.tournaments.publish', $tournament), $dialog);
        $this->assertStringNotContainsString('data-confirm', $dialog);
    }

    public function test_declining_records_nothing_and_leaves_the_field_settled(): void
    {
        // "Not yet" is a form method="dialog": it closes and posts nothing.
        $tournament = $this->tournament();
        $player = $this->player('Solo');
        $this->enter($tournament, $player);

        $this->followRedirects($this->eliminate($tournament, $player));

        $this->assertFalse($tournament->fresh()->isPublished());
        $this->assertTrue($tournament->fresh()->isComplete());
    }

    public function test_a_published_tournament_offers_nothing(): void
    {
        $tournament = $this->tournament();
        $player = $this->player('Solo');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);
        $tournament->forceFill(['published_at' => now()])->save();

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString('publish-offer', $html);
    }

    public function test_a_player_is_never_asked(): void
    {
        // Publishing is an administrator's act, and the route refuses anyone
        // else. Offering it would be offering a button that fails.
        $tournament = $this->tournament();
        $player = $this->player('Solo');
        $this->enter($tournament, $player);
        $this->score($tournament, $player, 1, 100);

        $html = $this->actingAs($player)
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString('publish-offer', $html);
    }

    public function test_an_incomplete_field_is_not_asked_about(): void
    {
        $tournament = $this->tournament();
        $a = $this->player('Ann');
        $b = $this->player('Bob');

        foreach ([$a, $b] as $p) {
            $this->enter($tournament, $p);
        }

        $this->score($tournament, $a, 2, 85);

        $html = $this->actingAs($this->admin())
            ->get(route('tournaments.show', $tournament->fresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString('publish-offer', $html);
    }

    public function test_the_offer_says_what_publishing_does(): void
    {
        // Before the button, not after it: it notifies every player who scored
        // and locks the tournament.
        $tournament = $this->tournament('Autumn Showdown');
        $player = $this->player('Solo');
        $this->enter($tournament, $player);

        $this->followRedirects($this->eliminate($tournament, $player))
            ->assertSee('That is the whole field for Autumn Showdown. Publish the results now?')
            ->assertSee('Every player who scored points is notified, and the tournament is locked.');
    }
}
