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
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Middleware to rewrite asset URLs to Vite Dev Server (HMR) during development.
 */
class ViteAssetMiddleware implements MiddlewareInterface
{
    private const VITE_DEV_SERVER = 'http://localhost:5173';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Only process in Development Context and for HTML responses
        if (!Environment::getContext()->isDevelopment() || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        // Check if Vite Dev Server is actually reachable
        if (!$this->isViteDevServerRunning()) {
            return $response;
        }

        $body = (string)$response->getBody();
        if (empty($body)) {
            return $response;
        }

        // 1. Inject Vite Client
        if (!str_contains($body, '@vite/client')) {
            $viteClient = '<script type="module" src="' . self::VITE_DEV_SERVER . '/@vite/client"></script>';
            $body = str_replace('</head>', $viteClient . PHP_EOL . '</head>', $body);
        }

        // 2. Load Entry Mapping
        $entriesPath = GeneralUtility::getFileAbsFileName('EXT:alice/vite.entries.json');
        if (!file_exists($entriesPath)) {
            return $response;
        }

        $manifest = json_decode((string)file_get_contents($entriesPath), true);
        if (empty($manifest['entries'])) {
            return $response;
        }

        $projectPath = str_replace('\\', '/', Environment::getProjectPath());

        // 3. Rewrite Build URLs to Dev Server Sources
        foreach ($manifest['entries'] as $outputKey => $absSourcePath) {
            // Convert absolute source path to a relative path from project root for Vite
            $viteSourcePath = str_replace($projectPath . '/', '', $absSourcePath);
            
            // Build the patterns to find (CSS and JS)
            // Example outputKey: packages/nelius/Resources/Public/Build/Css/main
            $searchPatterns = [
                '/_assets/' . $outputKey . '.css',
                '/_assets/' . $outputKey . '.js',
                '/_assets/' . $outputKey, // Fallback
            ];

            foreach ($searchPatterns as $search) {
                if (str_contains($body, $search)) {
                    // Replace with Vite Dev Server URL
                    $replacement = self::VITE_DEV_SERVER . '/' . $viteSourcePath;
                    
                    // If it was a CSS link, we need to use a script[type=module] for Vite HMR to work correctly with SCSS
                    if (str_ends_with($search, '.css')) {
                         $body = preg_replace(
                             '/<link[^>]+href=["\']' . preg_quote($search, '/') . '["\'][^>]*>/i',
                             '<script type="module" src="' . $replacement . '"></script>',
                             $body
                         );
                    } else {
                        $body = str_replace($search, $replacement, $body);
                    }
                }
            }
        }

        $streamFactory = GeneralUtility::makeInstance(StreamFactory::class);
        return $response->withBody($streamFactory->createStream($body));
    }

    private function isViteDevServerRunning(): bool
    {
        $connection = @fsockopen('localhost', 5173, $errno, $errstr, 0.05);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }
}
