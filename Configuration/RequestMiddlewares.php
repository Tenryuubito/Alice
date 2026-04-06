<?php

return [
    'frontend' => [
        'tenryuubito/alice/vite-bridge' => [
            'target' => \Tenryuubito\Alice\Middleware\ViteAssetMiddleware::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
            'before' => [
                'tenryuubito/alice/lazy-loading',
            ],
        ],
        'tenryuubito/alice/lazy-loading' => [
            'target' => \Tenryuubito\Alice\Middleware\LazyLoadingMiddleware::class,
            'after' => [
                'tenryuubito/alice/vite-bridge',
            ],
            'before' => [
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
    ],
];
