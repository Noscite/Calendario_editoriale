<?php

declare(strict_types=1);

/**
 * Pricing API esterne in USD per 1M token (input/output) o per chiamata.
 * Aggiornabile senza redeploy. Conversione EUR via 'usd_to_eur'.
 */
return [
    // Tasso conversione USD→EUR. Fissato qui per evitare chiamate FX ogni call.
    'usd_to_eur' => env('AI_PRICING_USD_TO_EUR', 0.93),

    // Anthropic Claude — pricing per 1M token (USD)
    'anthropic' => [
        'claude-sonnet-4-6' => [
            'input'           => 3.00,
            'output'          => 15.00,
            'cache_creation'  => 3.75,
            'cache_read'      => 0.30,
        ],
        'claude-sonnet-4-5' => [
            'input' => 3.00, 'output' => 15.00,
            'cache_creation' => 3.75, 'cache_read' => 0.30,
        ],
        'claude-opus-4-8' => [
            'input' => 5.00, 'output' => 25.00,
            'cache_creation' => 6.25, 'cache_read' => 0.50,
        ],
        'claude-opus-4-7' => [
            'input' => 5.00, 'output' => 25.00,
            'cache_creation' => 6.25, 'cache_read' => 0.50,
        ],
        'claude-haiku-4-5' => [
            'input' => 0.80, 'output' => 4.00,
            'cache_creation' => 1.00, 'cache_read' => 0.08,
        ],
        'default' => [
            'input' => 3.00, 'output' => 15.00,
            'cache_creation' => 3.75, 'cache_read' => 0.30,
        ],
    ],

    // Perplexity Sonar
    'perplexity' => [
        'sonar' => ['input' => 1.00, 'output' => 1.00],
    ],

    // OpenAI Images — pricing per chiamata (USD)
    'openai_images' => [
        'gpt-image-1' => [
            '1024x1024_standard' => 0.04,
            '1024x1024_hd'       => 0.08,
            '1792x1024_standard' => 0.08,
            '1024x1792_standard' => 0.08,
            '1792x1024_hd'       => 0.12,
            '1024x1792_hd'       => 0.12,
        ],
        'dall-e-3' => [
            '1024x1024_standard' => 0.04,
            '1024x1024_hd'       => 0.08,
            '1792x1024_standard' => 0.08,
            '1024x1792_standard' => 0.08,
        ],
        'default' => 0.04,
    ],

    'alert_brand_monthly_cost_eur' => env('AI_ALERT_BRAND_MONTHLY_EUR', 20.0),

    // Sotto questa soglia di post residui nel wallet, la dashboard segnala
    // in rosso l'organizzazione (non blocca nulla di per sé — il blocco vero
    // è in GenerationController::preflight quando il saldo è insufficiente
    // per la generazione richiesta).
    'low_post_credit_threshold' => env('AI_LOW_POST_CREDIT_THRESHOLD', 10),
];
