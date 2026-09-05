<?php

/*
|--------------------------------------------------------------------------
| Texas Hold'em game rules
|--------------------------------------------------------------------------
|
| The league's rules of play, as data rather than markup, so the page can
| render them and a test can check them without either one restating the
| other. Rules are cited by number -- "21.8" -- so the numbering is produced
| from the nesting and never typed, and cannot drift when one is inserted.
|
| Each entry is:
|   text     the rule itself
|   note     an aside that qualifies it, set apart when rendered
|   children sub-clauses, nested to any depth
|   after    a sentence belonging to the rule but following its sub-clauses
|
| Transcribed from the league's own document. Wording is deliberately left
| alone, including where it is not how anyone would write it fresh.
|
*/

return [

    'rules' => [
        ['text' => 'The dealer shall shuffle the cards 3 to 7 times.'],

        ['text' => 'Prior to the deal, the deck will be cut using a Cut Card. The Cut Card will be placed at the bottom of the deck concealing the face of the bottom card.'],

        ['text' => 'The player immediately to the left of the dealer shall post the required Small Blind before the beginning of the Round of Play.'],

        [
            'text' => 'The player immediately to the left of the Small Blind position shall post the required Big Blind before the beginning of the Round of Play.',
            'note' => 'When all but two players have been eliminated from the table, the player with the Dealer Button shall post the Small Blind and the other play shall post the Big Blind.',
        ],

        ['text' => 'All cards shall be dealt in a clockwise direction beginning with the player immediately to the left of the Dealer Button.'],

        [
            'text' => 'The Dealer shall commence the Round of Play by dealing one card to each player face down, then a second card to each player face down.',
            'children' => [
                [
                    'text' => 'If the dealer exposes the face of a card during a deal:',
                    'children' => [
                        ['text' => 'To a blind: it is a misdeal.'],
                        ['text' => "To a player other than a blind: the exposed card becomes the first burn card and the final card of the deal will be that player's second card."],
                    ],
                ],
                ['text' => 'If more than one card is exposed during the deal then it is a misdeal and the cards will be collected to be re-dealt.'],
            ],
            'after' => 'The first betting round will commence once players have received both Hold Cards.',
        ],

        ['text' => 'Each player may examine their Hold Cards at any time. It is a player’s responsibility to protect their hand from other players. The Hold Cards may be protected by keeping the cards in your hand, or by placing a small object on top of the cards.'],

        ['text' => 'Each player must keep their Hold Cards in full view of the Dealer at all times and must ensure that they are examined in a manner that does not disclose to other players their value. Any cards that fall off the table are dead. Players may not exchange information concerning their cards.'],

        [
            'text' => 'The first betting round shall proceed as follows:',
            'children' => [
                ['text' => 'The player immediately to the left of the Big Blind position is the first to act and must Call, Raise or Fold.'],
                ['text' => 'Betting continues in a clockwise direction until each player has an opportunity to Call, Raise or Fold. The player in the Big Blind position may Check if no other player has Raised or has the option to Raise.'],
                ['text' => 'If a player opts to Raise, he/she must verbalize Raise and place the proper amount of chips on the table. A Raise is minimum double the previous bet. A player may not place chips as if calling then go back to their chips to indicate a Raise; it must all be done in one motion, and it is considered a String Bet and is not allowed.'],
            ],
        ],

        ['text' => 'The dealer shall commence the second betting round by Burning a Card and then turning three Community Cards face up in the middle of the table; these three cards are commonly referred to as the Flop.'],

        ['text' => 'The dealer shall commence the third betting round by Burning a Card then placing the fourth community card on the table; this fourth card is commonly referred to as the Turn.'],

        ['text' => 'The dealer shall commence the fourth betting round by Burning a card then placing the fifth Community Card on the table; this fifth card is commonly referred to as the River.'],

        ['text' => 'Betting for the second, third and fourth betting rounds shall begin with the player immediately to the left of the Dealer and shall otherwise proceed in the same manner as betting round one.'],

        [
            'text' => 'Upon completion of four betting rounds:',
            'children' => [
                ['text' => 'If only one player remains in the Round of Play (i.e., all but one player has folded), the player is not obligated to show their hand. If the player does show their hand it must be available for all of the players at the table to see (Show one must show all).'],
                [
                    'text' => 'If two or more players remain in the round of play:',
                    'children' => [
                        ['text' => 'It shall be the obligation of the player who made the last bet to show their hand when called by another player or players.'],
                        ['text' => 'If no player has placed a bet, it shall be the obligation of all players to show their hands.'],
                    ],
                ],
            ],
        ],

        ['text' => 'Any combination of a player’s hold cards and/or community cards may be used to construct a standard five-card poker hand.'],

        [
            'text' => 'The dealer shall:',
            'children' => [
                [
                    'text' => 'Declare the last remaining player the winner or determine the winning hand among the remaining players in accordance with the following ranking of poker combinations:',
                    'children' => [
                        ['text' => '“ROYAL FLUSH” is a hand containing an ace, king, queen, jack and 10 of the same suit.'],
                        ['text' => '“STRAIGHT FLUSH” is a hand containing five cards of the same suit in consecutive ranking. An ace may count high or low.'],
                        ['text' => '“FOUR OF A KIND” is a hand containing four cards of the same rank.'],
                        ['text' => '“FULL HOUSE” is a hand containing three of a kind and one pair.'],
                        ['text' => '“FLUSH” is a hand containing five cards of the same suit, but not in consecutive ranking.'],
                        ['text' => '“STRAIGHT” is a hand containing five cards of consecutive rank, regardless of suit. An ace may count high or low.'],
                        ['text' => '“THREE OF A KIND” is a hand containing three cards of the same rank.'],
                        ['text' => '“TWO PAIR” is a hand containing two pairs.'],
                        ['text' => '“ONE PAIR” is a hand containing two cards of the same rank.'],
                        ['text' => '“HIGH CARD” is a hand that does not contain one pair or better.'],
                    ],
                ],
                [
                    'text' => 'Resolve ties in the following manner:',
                    'children' => [
                        [
                            'text' => 'In the event of equal ranking poker combinations of four of a kind, three of a kind, two pair or one pair, the high card not used in the poker combination, commonly known as the kicker, shall break the tie.',
                            'children' => [
                                ['text' => 'If the tie cannot be broken, the pot shall be split or chopped equally.'],
                                ['text' => 'In the event there is an extra chip that cannot be split, the player to the left of the dealer receives the extra chip.'],
                            ],
                        ],
                    ],
                ],
                ['text' => 'At the end of the final betting round (referred to as the “showdown” when all players show/turn their cards face up to see who the winner is), the dealer will muck all losing hands and then award the pot to the winning hand.'],
            ],
        ],

        ['text' => 'The dealer shall ensure that blinds are posted.'],

        ['text' => 'If only two players remain in a round of play and one player does not have enough chips to call the bet made by the other player, the player with the fewest chips may move All-In; the player with the most chips will receive chips back exceeding the chips of the All-In player. The cards are then exposed to everyone before the dealer continues to deal.'],

        [
            'text' => 'If two or more players wish to bet more than the bet of another player moving All-In, the dealer shall establish a side pot(s). Players may raise All-In:',
            'children' => [
                ['text' => 'The player moving All-In is eligible to win the main pot, consisting of the Blinds, all previous bets, the bet of the player moving All-In and the bets of the other players matching the All-In bet.'],
                ['text' => 'The players with chips remaining may continue placing bets into the side.'],
                ['text' => 'Additional side pots may be created if other players are All-In. There is no limit as to how many side pots can be in play.'],
            ],
        ],

        [
            'text' => 'A player is eliminated from the tournament when the following occurs:',
            'children' => [
                ['text' => 'The player has lost all of their chips.'],
                ['text' => 'The player is absent and has been blinded out.'],
                ['text' => 'The player has been removed by the tournament director for an infraction as stated under the Behaviour Rules.'],
            ],
        ],

        [
            'text' => 'In the event of a misdeal the dealer will retrieve all cards, re-shuffle and deal a new hand. A misdeal may NOT be called after substantial action has occurred. The following would be cause for a misdeal:',
            'children' => [
                ['text' => 'Dealing to the wrong person first.'],
                ['text' => 'Dealing too few or too many cards to a player.'],
                ['text' => 'Finding more than one Boxed Card (that is, a card that was mixed face up) in the deck.'],
                ['text' => 'Finding the deck to be defective.'],
                ['text' => 'Finding a joker in the deck.'],
                ['text' => 'Dealing a player out that has requested a hand or has chips invested in the hand.'],
                ['text' => 'Failure to shuffle and/or cut the deck before dealing.'],
                ['text' => 'Dealing one of the blind’s first card face up.'],
                ['text' => 'Dealing two or more cards face up.'],
            ],
        ],
    ],

];
