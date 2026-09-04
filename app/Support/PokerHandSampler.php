<?php

namespace App\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deals one example of each poker hand, for the hand hierarchy on the rules
 * page.
 *
 * A fresh example every time the page is loaded, which is the point: a fixed
 * picture of the same royal flush teaches the suit as well as the shape, and
 * the shape is the only part that matters.
 *
 * Every hand here must be an example of ITS OWN ranking and of nothing better.
 * That is easy to get wrong by accident -- five random cards of one suit are a
 * flush most of the time and a straight flush the rest of it -- so the builders
 * that could collide with a higher hand exclude it explicitly rather than
 * trusting the odds.
 *
 * Cards are strings of "SUIT-RANK", matching the file names in
 * public/images/deck. Each hand comes back sorted by rank, high to low, so the
 * card that names the hand leads the row.
 */
class PokerHandSampler
{
    public const SUITS = ['C', 'D', 'H', 'S'];

    /** Low to high. The index IS the rank's value; nothing else orders them. */
    public const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    /** The hierarchy, strongest first, as the page lists it. */
    public const HIERARCHY = [
        'Royal Flush',
        'Straight Flush',
        'Four of a Kind',
        'Full House',
        'Flush',
        'Straight',
        'Three of a Kind',
        'Two Pair',
        'One Pair',
        'High Card',
    ];

    private Randomizer $rng;

    /** Seedable, so the tests can deal a known shuffle instead of hoping. */
    public function __construct(?Randomizer $rng = null)
    {
        $this->rng = $rng ?? new Randomizer(new Mt19937());
    }

    /**
     * One example of every hand, strongest first.
     *
     * Each card arrives with the words for it as well as the file name, so the
     * view has nothing to work out.
     *
     * @return array<int, array{name: string, cards: array<int, array{code: string, label: string}>}>
     */
    public function hierarchy(): array
    {
        return array_map(fn (string $name) => [
            'name' => $name,
            'cards' => array_map(
                fn (string $card) => ['code' => $card, 'label' => $this->describe($card)],
                $this->hand($name)
            ),
        ], self::HIERARCHY);
    }

    /**
     * One example of the named hand, sorted by rank, highest first.
     *
     * @return array<int, string>
     */
    public function hand(string $name): array
    {
        $cards = match ($name) {
            'Royal Flush' => $this->sameSuitRun(count(self::RANKS) - 5),
            'Straight Flush' => $this->sameSuitRun($this->rng->getInt(0, count(self::RANKS) - 6)),
            'Four of a Kind' => $this->ofAKind(4, [1]),
            'Full House' => $this->ofAKind(3, [2]),
            'Flush' => $this->flush(),
            'Straight' => $this->straight(),
            'Three of a Kind' => $this->ofAKind(3, [1, 1]),
            'Two Pair' => $this->ofAKind(2, [2, 1]),
            'One Pair' => $this->ofAKind(2, [1, 1, 1]),
            'High Card' => $this->highCard(),
            default => throw new \InvalidArgumentException("Unknown hand: {$name}"),
        };

        return $this->byRank($cards);
    }

    /** "Ten of hearts", for the reader who cannot see the picture. */
    public function describe(string $card): string
    {
        [$suit, $rank] = explode('-', $card);

        $ranks = [
            'J' => 'Jack', 'Q' => 'Queen', 'K' => 'King', 'A' => 'Ace',
        ];

        $suits = ['C' => 'clubs', 'D' => 'diamonds', 'H' => 'hearts', 'S' => 'spades'];

        return __(':rank of :suit', [
            'rank' => __($ranks[$rank] ?? $rank),
            'suit' => __($suits[$suit]),
        ]);
    }

    /**
     * Five cards of one suit running up from the rank at $start.
     *
     * Both flush-runs come through here, and the only thing separating them is
     * where they may begin. The royal flush is the one run that ends on the
     * ace, so it is the highest start there is; a straight flush is any of the
     * others, which is why its top card is drawn from everything below that.
     * Two entries in a hierarchy must never be able to draw the same picture.
     *
     * @return array<int, string>
     */
    private function sameSuitRun(int $start): array
    {
        $suit = $this->pick(self::SUITS);

        return array_map(
            fn (int $i) => $suit.'-'.self::RANKS[$i],
            range($start, $start + 4)
        );
    }

    /**
     * A group of $size cards of one rank, plus groups of the given sizes at
     * other ranks. Every rank used is distinct, which is what keeps two pair
     * from dealing a full house and three of a kind from dealing quads.
     *
     * @param  array<int, int>  $others
     * @return array<int, string>
     */
    private function ofAKind(int $size, array $others): array
    {
        $sizes = [$size, ...$others];
        $ranks = $this->pickMany(self::RANKS, count($sizes));
        $cards = [];

        foreach ($sizes as $i => $count) {
            // Distinct suits within a rank: only four cards of a rank exist,
            // and two of them cannot share a suit.
            foreach ($this->pickMany(self::SUITS, $count) as $suit) {
                $cards[] = $suit.'-'.$ranks[$i];
            }
        }

        return $cards;
    }

    /**
     * Five of one suit that do not also make a straight -- which would be a
     * straight flush, one of the two hands above this one.
     *
     * @return array<int, string>
     */
    private function flush(): array
    {
        $suit = $this->pick(self::SUITS);

        do {
            $ranks = $this->pickMany(self::RANKS, 5);
        } while ($this->isRun($ranks));

        return array_map(fn (string $rank) => $suit.'-'.$rank, $ranks);
    }

    /**
     * Five in a row, and not all of one suit -- that would be a straight flush.
     *
     * The ace plays high only. A five-high straight is a real hand, but drawn
     * in rank order its ace leads the row and it reads as an ace-high straight,
     * so it is left out of a picture whose whole job is to be read at a glance.
     *
     * @return array<int, string>
     */
    private function straight(): array
    {
        $start = $this->rng->getInt(0, count(self::RANKS) - 5);
        $ranks = array_slice(self::RANKS, $start, 5);

        do {
            $suits = array_map(fn () => $this->pick(self::SUITS), $ranks);
        } while (count(array_unique($suits)) === 1);

        return array_map(
            fn (int $i) => $suits[$i].'-'.$ranks[$i],
            array_keys($ranks)
        );
    }

    /**
     * Five distinct ranks that are neither a run nor all one suit.
     *
     * The brief asked for five different suits, which no five cards can have:
     * there are four. What it means in practice is "not a flush", so that is
     * what this enforces, alongside "not a straight" and "not a pair".
     *
     * @return array<int, string>
     */
    private function highCard(): array
    {
        do {
            $ranks = $this->pickMany(self::RANKS, 5);
            $suits = array_map(fn () => $this->pick(self::SUITS), $ranks);
        } while ($this->isRun($ranks) || count(array_unique($suits)) === 1);

        return array_map(fn (int $i) => $suits[$i].'-'.$ranks[$i], array_keys($ranks));
    }

    /**
     * Do these five ranks make a straight?
     *
     * Both directions of it: five consecutive values, and the wheel, where the
     * ace plays below the two.
     *
     * @param  array<int, string>  $ranks
     */
    private function isRun(array $ranks): bool
    {
        $values = array_map(fn (string $r) => array_search($r, self::RANKS, true), $ranks);
        sort($values);

        if ($values === [0, 1, 2, 3, 12]) {
            return true;
        }

        foreach (array_slice($values, 1) as $i => $value) {
            if ($value !== $values[$i] + 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Highest first. The card that gives a hand its name -- the ace of a royal
     * flush, the higher of two pairs -- is then the one the eye reaches first.
     *
     * @param  array<int, string>  $cards
     * @return array<int, string>
     */
    private function byRank(array $cards): array
    {
        usort($cards, function (string $a, string $b) {
            $rank = fn (string $card) => array_search(explode('-', $card)[1], self::RANKS, true);

            return $rank($b) <=> $rank($a);
        });

        return $cards;
    }

    /** @param array<int, string> $from */
    private function pick(array $from): string
    {
        return $from[$this->rng->getInt(0, count($from) - 1)];
    }

    /** @param array<int, string> $from @return array<int, string> */
    private function pickMany(array $from, int $count): array
    {
        return array_slice($this->rng->shuffleArray($from), 0, $count);
    }
}
