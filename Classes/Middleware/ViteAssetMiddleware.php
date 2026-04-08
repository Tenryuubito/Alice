<?php

declare(strict_types=1);

namespace Tenryuubito\Alice\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to rewrite asset URLs to Vite Dev Server (HMR) during development.
 * 
 * Facilitates Hot Module Replacement by intercepting frontend responses and 
 * pointing production asset paths to the local Vite development server.
 */
class ViteAssetMiddleware implements MiddlewareInterface
{
    private const VITE_DEV_SERVER = 'http://localhost:5173';

    /**
     * Intercepts the response and performs asset URL rewriting if the Vite server is detected.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!Environment::getContext()->isDevelopment() || !str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        if (!$this->isViteDevServerRunning()) {
            return $response;
        }

        $body = (string)$response->getBody();
        if (empty($body)) {
            return $response;
        }

        if (!str_contains($body, '@vite/client')) {
            $viteClient = '<script type="module" src="' . self::VITE_DEV_SERVER . '/@vite/client"></script>';
            $body = str_replace('</head>', $viteClient . PHP_EOL . '</head>', $body);
        }

        $entriesPath = GeneralUtility::getFileAbsFileName('EXT:alice/vite.entries.json');
        if (!file_exists($entriesPath)) {
            return $response;
        }

        $manifest = json_decode((string)file_get_contents($entriesPath), true);
        if (empty($manifest['entries'])) {
            return $response;
        }

        $projectPath = str_replace('\\', '/', Environment::getProjectPath());

        foreach ($manifest['entries'] as $outputKey => $absSourcePath) {
            $viteSourcePath = str_replace($projectPath . '/', '', $absSourcePath);
            $searchPatterns = [
                '/_assets/' . $outputKey . '.css',
                '/_assets/' . $outputKey . '.js',
                '/_assets/' . $outputKey,
            ];

            foreach ($searchPatterns as $search) {
                if (str_contains($body, $search)) {
                    $replacement = self::VITE_DEV_SERVER . '/' . $viteSourcePath;
                    
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

    /**
     * Checks if the Vite development server is currently reachable on the default port.
     *
     * @return bool
     */
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
