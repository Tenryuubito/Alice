<?php

declare(strict_types=1);

namespace Tenryuubito\Alice\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for performing performance and SEO audits on HTML content.
 */
class AuditService
{
    /**
     * @param RequestFactory $requestFactory
     */
    public function __construct(
        protected readonly RequestFactory $requestFactory
    ) {}

    /**
     * Performs a comprehensive audit of the provided HTML content.
     * Analyzes images, links, and SEO-relevant meta tags.
     *
     * @param string $html The HTML content to audit
     * @param string $baseUrl The base URL for relative link resolution
     * @return array The audit results categorized by type
     */
    public function performHtmlAudit(string $html, string $baseUrl = ''): array
    {
        $results = [
            'images' => [],
            'seo' => ['issues' => [], 'inventory' => []],
            'links' => []
        ];

        if (empty($html)) {
            return $results;
        }

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);

        foreach ($dom->getElementsByTagName('img') as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: $img->getAttribute('srcset');
            if (empty($src)) {
                continue;
            }

            $alt = $img->getAttribute('alt');
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            $loading = $img->getAttribute('loading');
            $filename = basename(parse_url($src, PHP_URL_PATH) ?: $src);

            $issue = null;
            if (empty($alt)) {
                $issue = 'Missing alt tag';
            } elseif (empty($width) || empty($height)) {
                $issue = 'Missing dimensions';
            }

            $results['images'][] = [
                'src' => $src,
                'filename' => $filename,
                'alt' => $alt,
                'width' => $width,
                'height' => $height,
                'loading' => $loading ?: 'eager',
                'issue' => $issue
            ];
        }

        $links = $dom->getElementsByTagName('a');
        $baseHost = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        $count = 0;
        foreach ($links as $link) {
            if (!$link instanceof \DOMElement || $count >= 50) {
                continue;
            }
            $href = $link->getAttribute('href');
            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            $fullUrl = $href;
            if (!str_starts_with($href, 'http')) {
                $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
            }
            $linkHost = parse_url($fullUrl, PHP_URL_HOST) ?: '';
            $isExternal = !empty($linkHost) && $linkHost !== $baseHost;

            $start = microtime(true);
            $statusCode = 0;
            $accessible = false;
            try {
                $response = $this->requestFactory->request($fullUrl, 'HEAD', [
                    'headers' => ['User-Agent' => 'TYPO3 Alice LinkAuditor'],
                    'connect_timeout' => 3.0,
                    'timeout' => 3.0,
                    'allow_redirects' => true,
                    'http_errors' => false
                ]);
                $statusCode = $response->getStatusCode();
                $accessible = ($statusCode >= 200 && $statusCode < 400);
            } catch (\Throwable $e) {
                // Silently handle connection errors during link check
            }
            $duration = round((microtime(true) - $start) * 1000);

            $results['links'][] = [
                'url' => $fullUrl,
                'text' => trim($link->textContent) ?: '---',
                'status' => $statusCode,
                'accessible' => $accessible,
                'time' => $duration,
                'isExternal' => $isExternal
            ];
            $count++;
        }

        if ($dom->getElementsByTagName('title')->length === 0) {
            $results['seo']['issues'][] = 'error.missing_title';
        }
        foreach (['description', 'keywords', 'robots'] as $name) {
            if ($xpath->query('//meta[@name="' . $name . '"]/@content')->length === 0) {
                $results['seo']['issues'][] = 'error.missing_' . $name;
            }
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (!$meta instanceof \DOMElement) {
                continue;
            }
            $name = $meta->getAttribute('name') ?: $meta->getAttribute('property') ?: $meta->getAttribute('http-equiv');
            $content = $meta->getAttribute('content');
            if ($name && $content) {
                $results['seo']['inventory'][] = ['name' => $name, 'content' => $content];
            }
        }

        return $results;
    }

    /**
     * Calculates an overall audit summary including status, errors, and warnings count.
     * Takes Core Web Vitals into account.
     *
     * @param array $results Reference to the audit results array to be updated with status flags
     * @param float $lcp Largest Contentful Paint value
     * @param float $cls Cumulative Layout Shift value
     * @param float $inp Interaction to Next Paint value
     * @return array Summary data containing status code, error count, and warning count
     */
    public function calculateSummary(array &$results, float $lcp, float $cls, float $inp): array
    {
        $errors = 0;
        $warnings = 0;
        
        $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('alice');
        $lcpTarget = (float)($settings['lcp'] ?? 2.5);
        $clsTarget = (float)($settings['cls'] ?? 0.1);
        $inpTarget = (float)($settings['inp'] ?? 200);

        if ($lcp > 4.0 || $cls > 0.25 || $inp > 500) {
            $errors++;
        } elseif ($lcp > $lcpTarget || $cls > $clsTarget || $inp > $inpTarget) {
            $warnings++;
        }

        foreach ($results['images'] as &$img) {
            $img['status'] = 0;
            if (empty($img['alt']) || empty($img['width']) || empty($img['height'])) {
                $img['status'] = 1;
                $warnings++;
            }
        }

        foreach ($results['seo']['issues'] as $issue) {
            if (str_contains($issue, 'title') || str_contains($issue, 'description')) {
                $errors++;
            } else {
                $warnings++;
            }
        }

        foreach ($results['links'] as &$link) {
            $link['status'] = $link['accessible'] ? 0 : 2;
            if ($link['status'] === 2) {
                $errors++;
            } elseif ($link['time'] > 1000) {
                 $link['status'] = 1;
                 $warnings++;
            }
        }

        $status = 0;
        if ($errors > 0) {
            $status = 2;
        } elseif ($warnings > 0) {
            $status = 1;
        }

        return ['status' => $status, 'errors' => $errors, 'warnings' => $warnings];
    }
}
