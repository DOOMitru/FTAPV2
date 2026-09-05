<?php

namespace Tests\Unit;

use App\Support\PokerHandSampler;
use Tests\TestCase;

/**
 * Every dealt example must be an example of its own ranking and of nothing
 * better.
 *
 * The classifier below is written from the rules of poker rather than from the
 * sampler, on purpose: a test that reuses the code under test only proves the
 * code agrees with itself. Each hand is dealt many times, because these faults
 * are probabilistic -- five random cards of one suit are a straight flush about
 * once in every two hundred deals, and a single run would miss it.
 *
 * Laravel's base case rather than PHPUnit's, because describe() reads the
 * translator and the translator needs the application booted. No database is
 * touched.
 */
class PokerHandSamplerTest extends TestCase
{
    private const DEALS = 300;

    private function sampler(): PokerHandSampler
    {
        return new PokerHandSampler();
    }

    /** @param array<int, string> $cards */
    private function ranksOf(array $cards): array
    {
        return array_map(fn ($c) => explode('-', $c)[1], $cards);
    }

    /** @param array<int, string> $cards */
    private function suitsOf(array $cards): array
    {
        return array_map(fn ($c) => explode('-', $c)[0], $cards);
    }

    /** @param array<int, string> $cards */
    private function valuesOf(array $cards): array
    {
        $v = array_map(fn ($r) => array_search($r, PokerHandSampler::RANKS, true), $this->ranksOf($cards));
        sort($v);

        return $v;
    }

    /** @param array<int, string> $cards */
    private function isStraight(array $cards): bool
    {
        $v = $this->valuesOf($cards);

        if (count(array_unique($v)) !== 5) {
            return false;
        }

        return $v === [0, 1, 2, 3, 12] || $v[4] - $v[0] === 4;
    }

    /** @param array<int, string> $cards */
    private function isFlush(array $cards): bool
    {
        return count(array_unique($this->suitsOf($cards))) === 1;
    }

    /** The rank-group sizes, largest first: [4,1], [3,2], [2,2,1] and so on. */
    private function shape(array $cards): array
    {
        $counts = array_count_values($this->ranksOf($cards));
        rsort($counts);

        return $counts;
    }

    /** What this hand actually IS, by the rules rather than by what was asked for. */
    private function classify(array $cards): string
    {
        $straight = $this->isStraight($cards);
        $flush = $this->isFlush($cards);
        $shape = $this->shape($cards);
        $high = max($this->valuesOf($cards));

        return match (true) {
            $straight && $flush && $high === 12 && $this->valuesOf($cards)[0] === 8 => 'Royal Flush',
            $straight && $flush => 'Straight Flush',
            $shape === [4, 1] => 'Four of a Kind',
            $shape === [3, 2] => 'Full House',
            $flush => 'Flush',
            $straight => 'Straight',
            $shape === [3, 1, 1] => 'Three of a Kind',
            $shape === [2, 2, 1] => 'Two Pair',
            $shape === [2, 1, 1, 1] => 'One Pair',
            default => 'High Card',
        };
    }

    public function test_every_hand_is_an_example_of_itself(): void
    {
        $sampler = $this->sampler();

        foreach (PokerHandSampler::HIERARCHY as $name) {
            for ($i = 0; $i < self::DEALS; $i++) {
                $cards = $sampler->hand($name);

                $this->assertSame(
                    $name,
                    $this->classify($cards),
                    "Asked for {$name}, dealt ".implode(' ', $cards)
                );
            }
        }
    }

    public function test_every_hand_is_five_distinct_cards(): void
    {
        $sampler = $this->sampler();

        foreach (PokerHandSampler::HIERARCHY as $name) {
            for ($i = 0; $i < 50; $i++) {
                $cards = $sampler->hand($name);

                $this->assertCount(5, $cards, $name);
                $this->assertSame($cards, array_unique($cards), "Duplicate card in {$name}");
            }
        }
    }

    public function test_every_hand_is_dealt_highest_card_first(): void
    {
        $sampler = $this->sampler();

        foreach (PokerHandSampler::HIERARCHY as $name) {
            for ($i = 0; $i < 50; $i++) {
                $cards = $sampler->hand($name);

                $values = array_map(
                    fn ($c) => array_search(explode('-', $c)[1], PokerHandSampler::RANKS, true),
                    $cards
                );

                $sorted = $values;
                rsort($sorted);

                $this->assertSame($sorted, $values, 'Out of order: '.implode(' ', $cards));
            }
        }
    }

    public function test_the_royal_flush_is_ace_down_to_ten_of_one_suit(): void
    {
        $sampler = $this->sampler();
        $suitsSeen = [];

        for ($i = 0; $i < 100; $i++) {
            $cards = $sampler->hand('Royal Flush');

            $this->assertSame(['A', 'K', 'Q', 'J', '10'], $this->ranksOf($cards));
            $this->assertCount(1, array_unique($this->suitsOf($cards)));

            $suitsSeen[] = $this->suitsOf($cards)[0];
        }

        // The suit is the only thing that varies, so it had better vary.
        $this->assertGreaterThan(1, count(array_unique($suitsSeen)));
    }

    public function test_the_straight_flush_never_reaches_an_ace(): void
    {
        // A run to the ace is the hand above it, and two entries in a
        // hierarchy drawing the same picture is the one thing it must not do.
        // Everything below that is fair game, and it had better vary: this was
        // pinned to nine-through-king and dealt the same ranks every time.
        $sampler = $this->sampler();
        $tops = [];

        for ($i = 0; $i < self::DEALS; $i++) {
            $cards = $sampler->hand('Straight Flush');
            $values = $this->valuesOf($cards);

            $this->assertCount(1, array_unique($this->suitsOf($cards)), 'Not one suit: '.implode(' ', $cards));
            $this->assertSame(4, $values[4] - $values[0], 'Not a run: '.implode(' ', $cards));
            // By value, not by position: which end of the row the highest
            // card sits at is the sort order's business, not this test's.
            $this->assertNotSame(
                'A',
                PokerHandSampler::RANKS[$values[4]],
                'Ran to the ace: '.implode(' ', $cards)
            );

            $tops[] = $values[4];
        }

        // Every top card from a six up to a king, and nothing above it.
        $this->assertGreaterThan(5, count(array_unique($tops)), 'The run barely moved.');
        $this->assertSame(11, max($tops), 'The highest run should top out at a king.');
        $this->assertSame(4, min($tops), 'The lowest run should be two to six.');
    }

    public function test_the_straight_is_never_all_one_suit(): void
    {
        $sampler = $this->sampler();

        for ($i = 0; $i < self::DEALS; $i++) {
            $cards = $sampler->hand('Straight');

            $this->assertGreaterThan(
                1,
                count(array_unique($this->suitsOf($cards))),
                'A straight in one suit is a straight flush: '.implode(' ', $cards)
            );
        }
    }

    public function test_the_examples_change_from_deal_to_deal(): void
    {
        // The reason this class exists rather than a fixed picture in the view.
        $sampler = $this->sampler();

        foreach (['Flush', 'Straight', 'Four of a Kind', 'Two Pair', 'High Card'] as $name) {
            $seen = [];

            for ($i = 0; $i < 40; $i++) {
                $seen[] = implode(' ', $sampler->hand($name));
            }

            $this->assertGreaterThan(1, count(array_unique($seen)), "{$name} never changed");
        }
    }

    public function test_the_hierarchy_deals_all_ten_in_order(): void
    {
        $hierarchy = $this->sampler()->hierarchy();

        $this->assertSame(PokerHandSampler::HIERARCHY, array_column($hierarchy, 'name'));

        foreach ($hierarchy as $entry) {
            $this->assertCount(5, $entry['cards']);

            foreach ($entry['cards'] as $card) {
                // The file name and the words for it travel together, so the
                // view never has to derive one from the other.
                $this->assertMatchesRegularExpression('/^[CDHS]-(?:[2-9]|10|[JQKA])$/', $card['code']);
                $this->assertNotSame('', $card['label']);
                $this->assertStringContainsString(' of ', $card['label']);
            }
        }
    }

    public function test_a_card_can_say_what_it_is(): void
    {
        $sampler = $this->sampler();

        $this->assertSame('Ace of spades', $sampler->describe('S-A'));
        $this->assertSame('10 of hearts', $sampler->describe('H-10'));
        $this->assertSame('Queen of diamonds', $sampler->describe('D-Q'));
    }

    public function test_an_unknown_hand_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sampler()->hand('Six of a Kind');
    }
}
