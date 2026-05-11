<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default platforms quando il brand non ha social attivi
    |--------------------------------------------------------------------------
    | Usato in fase di onboarding: i post territoriali vengono generati
    | comunque come draft, su queste piattaforme. L'utente li ri-programma
    | (o cambia piattaforme) dopo aver collegato i social reali.
    */
    'default_platforms' => env('TERRITORIAL_DEFAULT_PLATFORMS')
        ? array_map('trim', explode(',', env('TERRITORIAL_DEFAULT_PLATFORMS')))
        : ['linkedin', 'instagram', 'facebook'],
];
