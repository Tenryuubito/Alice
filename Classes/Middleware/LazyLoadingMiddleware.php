<?php

/**
 * This file is part of the "Alice" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * (c) 2026 Tenryuubito
 */


declare(strict_types=1);

namespace Tenryuubito\Alice\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to automatically add loading="lazy" to images if enabled in Site Configuration
 */
class LazyLoadingMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected readonly Registry $registry
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Only process frontend requests with HTML response
        if (!($request->getAttribute('site') instanceof Site) || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $site = $request->getAttribute('site');
        
        // Use Global Extension Configuration instead of per-site Registry
        $settings = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('alice');
        
        if (empty($settings['auto_lazyloading']) || $settings['auto_lazyloading'] === '0') {
            return $response;
        }

        $body = (string)$response->getBody();
        if (empty($body)) {
            return $response;
        }

        // Post-process HTML: Add loading="lazy" to <img> tags that don't have a loading attribute
        $modifiedBody = preg_replace_callback(
            '/<img\s+([^>]+)>/i',
            function ($matches) {
                $attributes = $matches[1];
                
                // If loading attribute already exists (eager or lazy), don't touch it
                if (preg_match('/\bloading\s*=/i', $attributes)) {
                    return $matches[0];
                }

                // Add loading="lazy"
                return '<img ' . $attributes . ' loading="lazy">';
            },
            $body
        );

        if ($modifiedBody !== null && $modifiedBody !== $body) {
            $streamFactory = GeneralUtility::makeInstance(StreamFactory::class);
            $newBody = $streamFactory->createStream($modifiedBody);
            return $response->withBody($newBody);
        }

        return $response;
    }
}
