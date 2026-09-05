<?php

/*
|--------------------------------------------------------------------------
| Tournament and championship rules
|--------------------------------------------------------------------------
|
| The third of the league's rule sets, in the same shape as config/holdem.php
| and config/conduct.php and rendered by the same component, so every rules
| page reads alike and every clause is cited the same way.
|
| Transcribed from the league's own document; wording is left alone.
|
*/

return [

    'tournament' => [
        ['text' => 'The tournament play will comply with all municipal, provincial and federal laws.'],

        ['text' => 'The games shall be played at tables of sufficient size, but not exceeding seating for a maximum of 10 players.'],

        ['text' => 'The number of players at each table will be kept equal or as equal as possible.'],

        ['text' => 'The seating at tables shall be determined on a random basis.'],

        ['text' => 'Players must be seated at the start time of the tournament. Late arrivals may enter the tournament before the first break, however double the big blind will be removed from their chip stack.'],

        ['text' => 'The player in seat one will be the dealer to start the tournament.'],
    ],

    // Not a clause: it introduces the list rather than being the first item in
    // it, and numbering it would push every championship rule down by one.
    'championship_lead' => 'The season finale game will be the same format as regular season games with the following exceptions:',

    'championship' => [
        ['text' => 'There will be two tournaments running at the same time. There will not be a second game after the completion of the first game.'],

        ['text' => 'The blinds will be for 20 minutes rather than 15.'],

        ['text' => 'The blinds will be 100/200, 200/400, 300/600, 400/500, 500/1000, break, 1000/2000, 1500/3000, 2000/4000, 3000/6000, 4000/8000, 5000/10000 and 10000/20000.'],

        ['text' => 'Players will be placed into one of the two tournaments based on their point accumulation.'],

        ['text' => 'If a player is knocked out of the Championship game they are eligible to play in the alternate game as long as it is before the first break and the alternate game is still in progress. A player starting late will receive a reduced chip stack in the same manner as in our regular season games.'],

        ['text' => 'If players in the alternate game are knocked-out they may re-enter once if it is prior to the break. Their re-entry chip count will be reduced by double the current blind.'],
    ],

];
