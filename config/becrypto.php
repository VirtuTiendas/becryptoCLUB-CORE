<?php

return [
    'locales' => explode(',', env('SUPPORTED_LOCALES', 'en,es,de')),
    'default_locale' => env('DEFAULT_LOCALE', 'en'),
    'cache' => [
        'market_ttl' => env('CACHE_TTL_MARKET', 120),
        'mining_ttl' => env('CACHE_TTL_MINING', 60),
    ],
    'rate_limits' => [
        'public' => env('API_RATE_LIMIT', 60),
        'private' => env('PRIVATE_API_RATE_LIMIT', 30),
    ],
];
