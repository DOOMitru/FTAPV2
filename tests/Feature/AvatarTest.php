<?php

namespace Tests\Feature;

use App\Models\PokerSeason;
use App\Models\PokerTournament;
use App\Models\PokerTournamentRegistrant;
use App\Models\PokerTournamentResult;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A player is drawn as their photo, or as their initials.
 *
 * The fallback was one generic 1024x1024 PNG -- 1.9MB, drawn at 24px, the same
 * grey stranger for everybody. That was not a rare state: nobody in this league
 * has uploaded a photo, because the 205 accounts came from a CSV import that
 * sets none. An identical face beside every name is worse than no face at all,
 * because it takes the space where a difference belongs.
 *
 * So the no-photo state is a monogram, and it is the state nearly every one of
 * these tests exercises, because it is the state the app is actually in.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $blade, array $data = []): string
    {
        return Blade::render($blade, $data);
    }

    public function test_a_player_without_a_photo_gets_both_initials(): void
    {
        $user = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);

        $html = $this->render('<x-avatar :user="$user" />', ['user' => $user]);

        $this->assertStringContainsString('>WR<', $html);
        $this->assertStringContainsString('avatar--initials', $html);
    }

    public function test_the_generic_placeholder_is_never_served(): void
    {
        // The point of the whole change. 1.9MB, on the nav bar of every
        // authenticated page, identical for all 205 players.
        $user = User::factory()->create(['profile_image' => null]);

        $this->assertStringNotContainsString(
            'default_profile',
            $this->render('<x-avatar :user="$user" />', ['user' => $user])
        );
    }

    public function test_a_real_photo_is_used_when_there_is_one(): void
    {
        // The other half: this must not have become a component that only ever
        // draws letters.
        $user = User::factory()->create(['profile_image' => 'profile-images/wanda.jpg']);

        $html = $this->render('<x-avatar :user="$user" />', ['user' => $user]);

        $this->assertStringContainsString('profile-images/wanda.jpg', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringNotContainsString('avatar--initials', $html);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function names(): array
    {
        return [
            'ordinary' => ['Wanda Reeve', 'WR'],
            'hyphenated first name' => ['Jean-Luc Picard', 'JP'],
            'three parts takes the first and the last' => ['Ana Maria Silva', 'AS'],
            'one word' => ['Cher', 'C'],
            'already upper' => ['WANDA REEVE', 'WR'],
            'lower case' => ['wanda reeve', 'WR'],
            'ragged spacing' => ['  Wanda   Reeve  ', 'WR'],
            'accented' => ['Émile Zola', 'ÉZ'],
            'non-latin' => ['Дмитрий Кампан', 'ДК'],
        ];
    }

    #[DataProvider('names')]
    public function test_initials_are_taken_from_a_bare_name(string $name, string $expected): void
    {
        // The no-account case, which the schema allows: user_id is nullable with
        // nullOnDelete on results, registrants and venue points, so deleting a
        // player leaves their player_name behind with nothing attached.
        $html = $this->render('<x-avatar :name="$name" />', ['name' => $name]);

        $this->assertStringContainsString('>'.$expected.'<', $html);
    }

    public function test_nothing_at_all_still_renders(): void
    {
        // Rather than a blank circle or a crash in a leaderboard.
        $this->assertStringContainsString('>?<', $this->render('<x-avatar />'));
    }

    public function test_a_users_full_name_is_used_rather_than_their_display_name(): void
    {
        // display_name prefers a nickname and otherwise falls back to the first
        // name ALONE -- so initials taken from it would drop every surname, and
        // a player nicknamed "Ace" would monogram as "A".
        $user = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'nickname' => 'Ace',
        ]);

        $html = $this->render('<x-avatar :user="$user" />', ['user' => $user]);

        $this->assertStringContainsString('>WR<', $html);
    }

    public function test_the_users_own_name_wins_over_a_passed_one(): void
    {
        // Call sites pass both -- the account when there is one, and the
        // snapshotted player_name as the fallback. player_name is a copy taken
        // at registration, so it can be stale; the account is the live answer.
        $user = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);

        $html = $this->render('<x-avatar :user="$user" :name="$name" />',
            ['user' => $user, 'name' => 'Old Spelling']);

        $this->assertStringContainsString('>WR<', $html);
    }

    public function test_both_initials_survive_every_size(): void
    {
        // 24px is where two letters could stop fitting, and it is the size the
        // nav bar and both user tables use.
        $user = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);

        foreach (['sm', 'md', 'lg'] as $size) {
            $html = $this->render('<x-avatar :user="$user" :size="$size" />',
                ['user' => $user, 'size' => $size]);

            $this->assertStringContainsString('>WR<', $html, "Lost an initial at size {$size}.");
        }
    }

    public function test_a_decorative_monogram_is_hidden_from_a_screen_reader(): void
    {
        // It sits beside a visible name, so announcing it repeats the name --
        // and "W R" read as letters is worse than repetition.
        $user = User::factory()->create(['first_name' => 'Wanda', 'last_name' => 'Reeve']);

        $html = $this->render('<x-avatar :user="$user" decorative />', ['user' => $user]);

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('role="img"', $html);
    }

    public function test_a_standalone_monogram_carries_the_name(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Wanda', 'last_name' => 'Reeve', 'nickname' => 'Ace',
        ]);

        $html = $this->render('<x-avatar :user="$user" />', ['user' => $user]);

        $this->assertStringContainsString('role="img"', $html);
        // The name to SAY is the display name, even though the letters drawn
        // come from the full one.
        $this->assertStringContainsString('aria-label="Ace"', $html);
    }

    public function test_the_awaiting_approval_table_shows_a_photo_column(): void
    {
        // The inconsistency this fixes: the approved-users table on the same
        // page has had one all along, and this is the table where knowing who
        // you are admitting matters most.
        $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
        User::factory()->create([
            'first_name' => 'Priya', 'last_name' => 'Raman', 'approval_status' => 'pending',
        ]);

        $html = $this->actingAs($admin)->get(route('users.index'))->assertOk()->getContent();

        $awaiting = substr($html, (int) strpos($html, 'Awaiting approval'));
        $awaiting = substr($awaiting, 0, (int) strpos($awaiting, '</table>'));

        $this->assertStringContainsString('avatar', $awaiting);
        $this->assertStringContainsString('>PR<', $awaiting);
    }

    public function test_the_tournament_page_draws_players_with_the_component(): void
    {
        [$tournament, $player] = $this->tournamentWithAPlayer();

        $html = $this->actingAs(User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']))
            ->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $this->assertStringContainsString('>MI<', $html, 'The registered-players row lost its monogram.');
        $this->assertNotNull($player);
    }

    public function test_the_podium_seat_keeps_its_medal_class(): void
    {
        // On the podium .podium__seat is not a circle, it is the medal -- gold,
        // silver and bronze grounds with contrast ratios recorded in the
        // stylesheet. The avatar has to carry that class, or the podium stops
        // saying who came first.
        [$tournament, $player] = $this->tournamentWithAPlayer();

        PokerTournamentResult::create([
            'tournament_id' => $tournament->id, 'user_id' => $player->id,
            'player_name' => 'Marcus Ilic', 'place' => 1, 'points' => 100,
        ]);

        $html = $this->actingAs($player)->get(route('tournaments.show', $tournament))->assertOk()->getContent();

        $this->assertStringContainsString('podium__seat', $html);
        $this->assertStringContainsString('podium__place--1', $html);
        $this->assertStringContainsString('>MI<', $html);
    }

    public function test_no_hand_cut_initials_remain_in_any_view(): void
    {
        // Two views sliced their own first letter out of a name. That is the
        // component's job, and a copy of it is a copy that will not learn about
        // photos, or surnames, or multi-byte letters.
        $offenders = [];

        foreach (\Symfony\Component\Finder\Finder::create()->files()
            ->in(resource_path('views'))->name('*.blade.php') as $file) {
            if (preg_match('/substr\([^)]*(?:player_name|first_name|display_name|last_name)[^)]*,\s*0,\s*1\s*\)/', file_get_contents($file->getRealPath()))) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders, 'Use <x-avatar> rather than cutting an initial by hand.');
    }

    /** @return array{0: PokerTournament, 1: User} */
    private function tournamentWithAPlayer(): array
    {
        $season = PokerSeason::create(['name' => 'Season 40', 'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(), 'is_current' => true]);

        $tournament = PokerTournament::create([
            'name' => 'Autumn Showdown', 'start_time' => now()->subHour(),
            'venue_id' => Venue::create(['name' => 'Hall', 'address' => '1 St'])->id,
            'season_id' => $season->id,
        ]);

        $player = User::factory()->create([
            'first_name' => 'Marcus', 'last_name' => 'Ilic', 'approval_status' => 'approved',
        ]);

        PokerTournamentRegistrant::create([
            'tournament_id' => $tournament->id, 'user_id' => $player->id,
            'player_name' => 'Marcus Ilic', 'registered_at' => now(),
        ]);

        return [$tournament, $player];
    }
}
