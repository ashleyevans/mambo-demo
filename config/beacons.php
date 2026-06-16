<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Beacon Stores
    |--------------------------------------------------------------------------
    |
    | Maps an iBeacon's "major:minor" identifier to the physical store it sits
    | in. Used to label beacon visits and events with a recognisable location
    | and logo. The logo path is resolved relative to the public directory.
    |
    */

    'stores' => [
        '1:1' => [
            'name' => 'Starbucks · Great Portland Street, London',
            'logo' => 'images/shops/starbucks-mark.svg',
        ],
    ],

];
