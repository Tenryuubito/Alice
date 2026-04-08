<?php

declare(strict_types=1);

namespace Tenryuubito\Alice\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to automatically add loading="lazy" to images if enabled in extension configuration.
 */
class LazyLoadingMiddleware implements MiddlewareInterface
{
    /**
     * @param Registry $registry
     */
    public function __construct(
        protected readonly Registry $registry
    ) {}

    /**
     * Processes the request and adds loading="lazy" to images in the HTML response body.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!($request->getAttribute('site') instanceof Site) || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('alice');
        
        if (empty($settings['auto_lazyloading']) || $settings['auto_lazyloading'] === '0') {
            return $response;
        }

        $body = (string)$response->getBody();
        if (empty($body)) {
            return $response;
        }

        $modifiedBody = preg_replace_callback(
            '/<img\s+([^>]+)>/i',
            function ($matches) {
                $attributes = $matches[1];
                
                if (preg_match('/\bloading\s*=/i', $attributes)) {
                    return $matches[0];
                }

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
