<?php

namespace Tests\Feature;

use App\Support\PokerHandSampler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hand hierarchy as the rules page draws it.
 *
 * What the hands CONTAIN is settled in PokerHandSamplerTest against the rules of
 * poker. What is checked here is that the page puts them on screen: ten hands,
 * five cards each, face down over face up, named underneath.
 */
class HandHierarchyPageTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get(route('rules.texas-holdem'))->assertOk()->getContent();
    }

    public function test_every_ranking_is_dealt_a_hand(): void
    {
        $html = $this->page();

        $this->assertSame(10, substr_count($html, 'class="p-hand"'));

        foreach (PokerHandSampler::HIERARCHY as $name) {
            $this->assertStringContainsString($name, $html, "{$name} is missing from the page.");
        }
    }

    public function test_each_hand_is_five_backs_over_five_faces(): void
    {
        $html = $this->page();

        // Ten hands of five: the backs are what is seen first, and there is one
        // behind every card rather than one shared image.
        $this->assertSame(50, substr_count($html, 'deck/back.svg'));
        $this->assertSame(50, substr_count($html, 'p-hand__side--face'));
        // The closing quote matters: the row that holds them is
        // "p-hand__cards", which contains this class name as a substring.
        $this->assertSame(50, substr_count($html, 'class="p-hand__card"'));
    }

    public function test_every_card_is_dealt_a_position(): void
    {
        // The stagger reads --deal, so a card without one turns with the first.
        $html = $this->page();

        foreach (range(0, 4) as $i) {
            $this->assertSame(
                10,
                substr_count($html, '--deal: '.$i.'"'),
                "Not every hand has a card in position {$i}."
            );
        }
    }

    public function test_the_name_sits_below_the_cards(): void
    {
        $html = $this->page();

        // The caption follows the row of cards in the markup, which is what
        // puts it under them.
        $this->assertMatchesRegularExpression(
            '/p-hand__cards.*?<\/div>\s*<figcaption class="p-hand__caption">.*?Royal Flush/s',
            $html
        );
    }

    public function test_every_card_it_asks_for_actually_exists(): void
    {
        // A card code with no file behind it is a hole in the picture, and the
        // sampler builds those codes rather than listing them.
        preg_match_all('#deck/([^"\']+\.svg)#', $this->page(), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach (array_unique($matches[1]) as $file) {
            $this->assertFileExists(public_path('images/deck/'.$file));
        }
    }

    public function test_a_reader_who_cannot_see_the_cards_is_told_what_they_are(): void
    {
        // The images are decorative -- fifty of them, announced one by one,
        // would bury the page. The caption carries the words instead.
        $html = $this->page();

        $this->assertStringContainsString('u-visually-hidden', $html);
        $this->assertMatchesRegularExpression('/Royal Flush<\/span>\s*<span class="u-visually-hidden">[^<]* of [^<]*</s', $html);

        // And no image announces itself.
        $this->assertSame(0, substr_count($html, 'deck/back.svg" alt="Card'));
    }

    public function test_the_hands_are_dealt_afresh_on_each_visit(): void
    {
        // The reason the examples are generated rather than written down. Ten
        // hands agreeing across two visits would be a fixed deck.
        $first = $this->page();

        for ($i = 0; $i < 5; $i++) {
            if ($this->page() !== $first) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail('Six visits dealt the same ten hands every time.');
    }

    public function test_the_hands_are_dealt_one_after_another(): void
    {
        // The cascade is built from two numbers, and this is the one the
        // stylesheet cannot work out for itself: which hand it is drawing. Ten
        // hands, each with its own place, strongest first.
        $html = $this->page();

        foreach (range(0, 9) as $i) {
            $this->assertSame(
                1,
                substr_count($html, '--hand: '.$i.'"'),
                "Expected exactly one hand in position {$i}."
            );
        }

        // In order: the royal flush is dealt before the high card, and the
        // markup order is what says so.
        $this->assertLessThan(
            strpos($html, '--hand: 9"'),
            strpos($html, '--hand: 0"'),
            'The hierarchy is dealt out of order.'
        );

        $this->assertLessThan(
            strpos($html, 'High Card'),
            strpos($html, 'Royal Flush'),
            'The strongest hand should come first.'
        );
    }
}
