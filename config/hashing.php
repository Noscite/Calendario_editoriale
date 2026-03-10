<?php

return [

    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds'           => env('BCRYPT_ROUNDS', 12),
        'verify_algorithm' => false,  // Necessario per compatibilità con hash $2b$ da Python/passlib
    ],

    'argon' => [
        'memory'    => 65536,
        'threads'   => 1,
        'time'      => 4,
        'verify'    => false,
    ],

];
