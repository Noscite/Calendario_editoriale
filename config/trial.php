<?php

return [
    'duration_days'    => 14,
    'token_budget'     => 30_000,
    'calendar_budget'  => 1,
    'grace_period_days' => 90, // giorni prima del soft-delete dati dopo scadenza

    'features_disabled_during_trial' => [
        'social_auto_publish',
        'social_account_connect',
        'multiple_calendars_per_month',
        'advanced_export',
    ],
];
