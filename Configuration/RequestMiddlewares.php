<?php

/**
 * This file is part of the "Alice" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * (c) 2026 Tenryuubito
 */


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
