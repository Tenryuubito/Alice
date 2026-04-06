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

namespace Tenryuubito\Alice\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend Controller for Alice Performance Module
 */
#[AsController]
class BackendController extends ActionController
{
    protected $extensionName = 'Alice';

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly ConnectionPool $connectionPool,
        protected readonly RequestFactory $requestFactory,
        protected readonly SiteFinder $siteFinder,
        protected readonly Registry $registry,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function indexAction(): ResponseInterface
    {
        $pageId = (int)($this->request->getQueryParams()['id'] ?? 0);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $analysis = null;
        // Also allow pageId = 0 for the absolute root node
        if ($pageId >= 0) {
            if ($pageId > 0) {
                // Fetch latest analysis
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_alice_analysis');
                $analysis = $queryBuilder
                    ->select('*')
                    ->from('tx_alice_analysis')
                    ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, \Doctrine\DBAL\ParameterType::INTEGER)))
                    ->orderBy('tstamp', 'DESC')
                    ->setMaxResults(1)
                    ->executeQuery()
                    ->fetchAssociative();

                if ($analysis) {
                    $analysis['results'] = json_decode((string)$analysis['results'], true);
                }

                try {
                    $site = $this->siteFinder->getSiteByPageId($pageId);
                    $moduleTemplate->assign('siteFound', true);
                    $isSiteRoot = (bool)$this->connectionPool->getQueryBuilderForTable('pages')
                        ->select('is_siteroot')
                        ->from('pages')
                        ->where('uid = ' . (int)$pageId)
                        ->executeQuery()
                        ->fetchOne();
                    $moduleTemplate->assign('isSiteRoot', $isSiteRoot);
                } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
                    $moduleTemplate->assign('siteFound', false);
                    $moduleTemplate->assign('isSiteRoot', false);
                }
            } else {
                // Page ID is 0 (Absolute Root)
                $moduleTemplate->assign('siteFound', false);
                $moduleTemplate->assign('isSiteRoot', true); // Treat root node as site root for config
            }

            // Always load and assign settings from Global Extension Configuration
            $settings = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('alice');
            $settings['thresholds']['lcp'] = (float)($settings['lcp'] ?? 2.5);
            $settings['thresholds']['cls'] = (float)($settings['cls'] ?? 0.1);
            $settings['thresholds']['inp'] = (float)($settings['inp'] ?? 200);
            $settings['auto_lazyloading'] = (bool)($settings['auto_lazyloading'] ?? false);
            $moduleTemplate->assign('settings', $settings);
        }

        $uriBuilder = $this->uriBuilder->reset();
        $analyzeUri = (string)$uriBuilder
            ->setTargetPageType(0)
            ->uriFor('analyze');
        
        $saveSettingsUri = (string)$uriBuilder
            ->setTargetPageType(0)
            ->uriFor('saveSettings');

        $moduleTemplate->assign('pageId', $pageId);
        $moduleTemplate->assign('analysis', $analysis);
        $moduleTemplate->assign('analyzeUri', $analyzeUri);
        $moduleTemplate->assign('saveSettingsUri', $saveSettingsUri);

        return $moduleTemplate->renderResponse('Index');
    }

    /**
     * Action to save site-level settings
     *
     * @param int $id The page ID (0 for root)
     * @param array $settings The settings array from the form
     */
    public function saveSettingsAction(int $id = 0, array $settings = []): ResponseInterface
    {
        // Allow id >= 0 to support root node (global settings)
        if ($id < 0) {
            return $this->redirect('index');
        }

        $config = [
            'lcp' => (string)($settings['thresholds']['lcp'] ?? '2.5'),
            'cls' => (string)($settings['thresholds']['cls'] ?? '0.1'),
            'inp' => (string)($settings['thresholds']['inp'] ?? '200'),
            'auto_lazyloading' => ($settings['auto_lazyloading'] ?? '0') === '1' ? '1' : '0'
        ];

        // Save to Global Extension Configuration
        GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->set('alice', $config);
        
        $this->addFlashMessage('Global settings saved successfully.');

        return $this->redirect('index', null, null, ['id' => $id]);
    }

    /**
     * AJAX Action to analyze a page and store results
     */
    public function analyzeAction(): ResponseInterface
    {
        try {
            $pageId = (int)($this->request->getQueryParams()['id'] ?? 0);
            if ($pageId <= 0) {
                throw new \InvalidArgumentException('Invalid Page ID');
            }

            // 1. Fetch Page Content (using shared BE cookie for restricted pages)
            try {
                $site = $this->siteFinder->getSiteByPageId($pageId);
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
                throw new \RuntimeException('The selected page (ID: ' . $pageId . ') is not part of a valid Site Configuration. Please ensure this page or its parent has a "Site Root" defined.');
            }

            $url = (string)$site->getRouter()->generateUri($pageId, ['no_cache' => '1']);
            
            // Get the current backend session cookie
            $beCookie = $_COOKIE['be_typo_user'] ?? '';
            
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => [
                    'Cookie' => 'be_typo_user=' . $beCookie,
                    'User-Agent' => 'TYPO3 Alice Auditor'
                ]
            ]);

            $html = (string)$response->getBody();

            // 2. Perform SEO & Image Audit
            $results = $this->performHtmlAudit($html);

            // 3. Collect Web Vitals from POST data
            $postData = $this->request->getParsedBody()['vitals'] ?? [];
            $lcp = (float)($postData['lcp'] ?? 0);
            $cls = (float)($postData['cls'] ?? 0);
            $inp = (float)($postData['inp'] ?? 0);

            // 4. Persistence
            $connection = $this->connectionPool->getConnectionForTable('tx_alice_analysis');
            
            // Safety check for table existence
            $schemaManager = $connection->createSchemaManager();
            if (!$schemaManager->tablesExist(['tx_alice_analysis'])) {
                throw new \RuntimeException('Database table "tx_alice_analysis" is missing. Please run the TYPO3 Database Update in the Maintenance module.');
            }

            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_alice_analysis');

            // Check if record exists
            $existing = $queryBuilder
                ->select('uid')
                ->from('tx_alice_analysis')
                ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, \Doctrine\DBAL\ParameterType::INTEGER)))
                ->executeQuery()
                ->fetchOne();

            $data = [
                'pid' => $pageId,
                'tstamp' => time(),
                'results' => json_encode($results),
                'lcp' => $lcp,
                'cls' => $cls,
                'inp' => $inp
            ];

            if ($existing) {
                $connection->update('tx_alice_analysis', $data, ['uid' => (int)$existing]);
            } else {
                $data['crdate'] = time();
                $connection->insert('tx_alice_analysis', $data);
            }

            $jsonResponse = json_encode([
                'success' => true,
                'analysis' => [
                    'pid' => $pageId,
                    'tstamp' => time(),
                    'results' => $results, // Use raw array here
                    'lcp' => $lcp,
                    'cls' => $cls,
                    'inp' => $inp
                ]
            ]);

            return $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream($jsonResponse));

        } catch (\Throwable $e) {
            $errorResponse = json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseFactory->createResponse(500)
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream($errorResponse));
        }
    }

    protected function performHtmlAudit(string $html): array
    {
        $results = [
            'images' => [],
            'seo' => []
        ];

        if (empty($html)) {
            return $results;
        }

        // Handle UTF-8 for DOMDocument
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);

        // Images: Support <img> and <picture> patterns
        foreach ($dom->getElementsByTagName('img') as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }
            
            // Extract technical attributes
            $src = $img->getAttribute('src');
            if (empty($src)) {
                $src = $img->getAttribute('data-src') ?: $img->getAttribute('srcset') ?: '';
            }

            if (empty($src)) {
                continue;
            }

            $alt = $img->getAttribute('alt');
            $title = $img->getAttribute('title');
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            $loading = $img->getAttribute('loading');

            $filename = basename(parse_url($src, PHP_URL_PATH) ?: $src);

            $issue = null;
            if (empty($alt)) {
                $issue = 'Missing alt tag';
            }

            $results['images'][] = [
                'src' => $src,
                'filename' => $filename,
                'alt' => $alt,
                'title' => $title,
                'width' => $width,
                'height' => $height,
                'loading' => $loading ?: 'eager',
                'issue' => $issue
            ];
        }

        // Advanced SEO Check
        $results['seo'] = [
            'issues' => [],
            'inventory' => []
        ];

        $title = $dom->getElementsByTagName('title');
        if ($title->length === 0) {
            $results['seo']['issues'][] = 'error.missing_title';
        }

        // Mandatory Meta Checks
        $mandatory = ['description', 'keywords', 'robots'];
        foreach ($mandatory as $name) {
            $tag = $xpath->query('//meta[@name="' . $name . '"]/@content');
            if ($tag->length === 0) {
                $results['seo']['issues'][] = 'error.missing_' . $name;
            }
        }

        // Discovery: Inventory of all other meta tags
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (!$meta instanceof \DOMElement) continue;
            
            $name = $meta->getAttribute('name') ?: $meta->getAttribute('property') ?: $meta->getAttribute('http-equiv');
            $content = $meta->getAttribute('content');
            $charset = $meta->getAttribute('charset');

            if ($charset) {
                $results['seo']['inventory'][] = ['name' => 'charset', 'content' => $charset];
            } elseif ($name && $content) {
                $results['seo']['inventory'][] = ['name' => $name, 'content' => $content];
            }
        }

        return $results;
    }
}
