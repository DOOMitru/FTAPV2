<?php

/*
|--------------------------------------------------------------------------
| Betting and conduct rules
|--------------------------------------------------------------------------
|
| The same shape as config/holdem.php, and rendered by the same component, so
| the league's two rule pages read alike and are cited alike -- "behaviour 5.4"
| addresses a clause because the numbering comes from the nesting rather than
| from anything typed into the text.
|
| Transcribed from the league's own document; wording is left alone.
|
*/

return [

    'betting' => [
        ['text' => 'Once a chip has been placed in play it becomes part of the pot.'],

        ['text' => 'Players must announce their action. A higher denomination chip placed in play without a verbal bet being made will be accepted as the minimum bet.'],

        ['text' => 'Players shall keep all of their cards and chips on the table and visible at all times.'],

        // "Straddle", corrected from "Strattle" in the source.
        ['text' => 'Straddle betting is not allowed.'],
    ],

    'behaviour' => [
        ['text' => 'The dealer is in control of the table. No one else at the table is allowed to touch the chips in play without the dealer’s permission.'],

        ['text' => 'Cell phone use is not allowed at the tables.'],

        ['text' => 'Players who are not at the table when their hands are dealt will have their hand pushed into the muck and their blind will be advanced into the pot.'],

        ['text' => 'Persons who appear to be intoxicated may not be allowed to participate in the tournament.'],

        [
            'text' => 'The tournament director may remove disruptive players from the tournament. The following system of warnings and penalties may be used:',
            'children' => [
                ['text' => 'The tournament director will inform the player of their infraction and if necessary make them aware of further consequences.'],
                // "has passed", corrected from "has past" in the source.
                ['text' => 'After the first verbal warning the player will be withdrawn from play until the blind has passed their position at the table.'],
                ['text' => 'The player will be ejected from the tournament.'],
                ['text' => 'The player will be banned from playing in any “First to Act” event for the period of one month.'],
                ['text' => 'The player will not be invited to play at any “First to Act” event for the remainder of the season.'],
            ],
        ],

        ['text' => 'The tournament director may call the clock on a player who has not made a decision on the next action. The player shall have one minute, including a ten-second countdown, to act. If the time expires without action, the player’s hand is dead.'],
    ],

];
