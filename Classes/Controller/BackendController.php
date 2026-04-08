<?php

declare(strict_types=1);

namespace Tenryuubito\Alice\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Tenryuubito\Alice\Service\AuditService;
use Tenryuubito\Alice\Service\IssueService;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend controller for the Alice Performance Module.
 *
 * Handles the main dashboard view, single and batch audit triggers,
 * and configuration management.
 */
#[AsController]
class BackendController extends ActionController
{
    /**
     * @var string
     */
    protected $extensionName = 'Alice';

    /**
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param ConnectionPool $connectionPool
     * @param RequestFactory $requestFactory
     * @param SiteFinder $siteFinder
     * @param Registry $registry
     * @param AuditService $auditService
     * @param IssueService $issueService
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     */
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly ConnectionPool $connectionPool,
        protected readonly RequestFactory $requestFactory,
        protected readonly SiteFinder $siteFinder,
        protected readonly Registry $registry,
        protected readonly AuditService $auditService,
        protected readonly IssueService $issueService,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Main dashboard entry point.
     * Renders either the universal overview (UID 0) or the single-page audit view.
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $pageId = (int)($this->request->getQueryParams()['id'] ?? 0);
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $globalIssues = [];
        $analysis = null;
        $isSiteRoot = false;
        $siteFound = false;
        $sitePages = [];

        if ($pageId > 0) {
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
                $this->siteFinder->getSiteByPageId($pageId);
                $siteFound = true;
                $isSiteRoot = false;
            } catch (\Throwable $e) {
                $siteFound = false;
            }
        } else {
            $siteFound = true;
            $isSiteRoot = true;

            try {
                $qbPages = $this->connectionPool->getQueryBuilderForTable('pages');
                $rows = $qbPages
                    ->select('p.uid', 'p.title', 'a.lcp', 'a.cls', 'a.inp', 'a.tstamp')
                    ->from('pages', 'p')
                    ->leftJoin(
                        'p',
                        'tx_alice_analysis',
                        'a',
                        $qbPages->expr()->eq('p.uid', $qbPages->quoteIdentifier('a.pid'))
                    )
                    ->where(
                        $qbPages->expr()->eq('p.sys_language_uid', 0),
                        $qbPages->expr()->eq('p.deleted', 0),
                        $qbPages->expr()->eq('p.hidden', 0),
                        $qbPages->expr()->neq('p.uid', 0)
                    )
                    ->orderBy('p.uid', 'ASC')
                    ->executeQuery()
                    ->fetchAllAssociative();

                foreach ($rows as $row) {
                    $sitePages[] = [
                        'uid' => (int)$row['uid'],
                        'title' => $row['title'],
                        'lcp' => $row['lcp'] !== null ? (float)$row['lcp'] : null,
                        'cls' => $row['cls'] !== null ? (float)$row['cls'] : null,
                        'inp' => $row['inp'] !== null ? (float)$row['inp'] : null,
                        'lastAudit' => $row['tstamp'] ? (int)$row['tstamp'] : null
                    ];
                }
            } catch (\Throwable $e) {
                // Silently handle database errors in dashboard overview
            }
            
            $globalIssues = $this->issueService->getGlobalIssues(0);
        }

        $moduleTemplate->assign('siteFound', $siteFound);
        $moduleTemplate->assign('isSiteRoot', $isSiteRoot);
        $moduleTemplate->assign('sitePages', $sitePages);

        $settings = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('alice');
        $settings['thresholds']['lcp'] = (float)($settings['lcp'] ?? 2.5);
        $settings['thresholds']['cls'] = (float)($settings['cls'] ?? 0.1);
        $settings['thresholds']['inp'] = (float)($settings['inp'] ?? 200);
        $settings['autoLazyLoading'] = (bool)($settings['auto_lazyloading'] ?? false);
        $settings['openaiApiKey'] = (string)($settings['openai_api_key'] ?? '');
        $moduleTemplate->assign('settings', $settings);

        $uriBuilder = $this->uriBuilder->reset();
        $moduleTemplate->assign('pageId', $pageId);
        $moduleTemplate->assign('analysis', $analysis);
        $moduleTemplate->assign('analyzeUri', (string)$uriBuilder->setTargetPageType(0)->uriFor('analyze', ['id' => $pageId]));
        $moduleTemplate->assign('saveSettingsUri', (string)$uriBuilder->setTargetPageType(0)->uriFor('saveSettings', ['id' => $pageId]));
        $moduleTemplate->assign('getPagesUri', (string)$uriBuilder->setTargetPageType(0)->uriFor('getSitePages', ['id' => $pageId]));
        $moduleTemplate->assign('globalIssues', $globalIssues);

        return $moduleTemplate->renderResponse('Index');
    }

    /**
     * AJAX endpoint for performing a single page audit.
     * Expects Web Vitals data in the POST body.
     *
     * @return ResponseInterface
     */
    public function analyzeAction(): ResponseInterface
    {
        try {
            $data = $this->request->getParsedBody();
            if (empty($data)) {
                $data = json_decode((string)$this->request->getBody(), true);
            }

            $pageId = (int)($data['id'] ?? $this->request->getQueryParams()['id'] ?? 0);
            if ($pageId <= 0) {
                throw new \InvalidArgumentException('Invalid Page ID');
            }

            $site = $this->siteFinder->getSiteByPageId($pageId);
            $url = (string)$site->getRouter()->generateUri($pageId, ['no_cache' => '1']);
            $beCookie = $_COOKIE['be_typo_user'] ?? '';
            
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => [
                    'Cookie' => 'be_typo_user=' . $beCookie,
                    'User-Agent' => 'TYPO3 Alice Auditor'
                ]
            ]);

            $html = (string)$response->getBody();
            $results = $this->auditService->performHtmlAudit($html, $url);

            $postData = $data['vitals'] ?? [];
            $lcp = (float)($postData['lcp'] ?? 0);
            $cls = (float)($postData['cls'] ?? 0);
            $inp = (float)($postData['inp'] ?? 0);

            $summary = $this->auditService->calculateSummary($results, $lcp, $cls, $inp);
            
            $connection = $this->connectionPool->getConnectionForTable('tx_alice_analysis');
            $dbData = [
                'pid' => $pageId,
                'tstamp' => time(),
                'results' => json_encode($results),
                'lcp' => $lcp,
                'cls' => $cls,
                'inp' => $inp,
                'status' => $summary['status'],
                'errors' => $summary['errors'],
                'warnings' => $summary['warnings']
            ];

            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_alice_analysis');
            $existing = $queryBuilder->select('uid')->from('tx_alice_analysis')
                ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, \Doctrine\DBAL\ParameterType::INTEGER)))
                ->executeQuery()->fetchOne();

            if ($existing) {
                $connection->update('tx_alice_analysis', $dbData, ['uid' => (int)$existing]);
            } else {
                $dbData['crdate'] = time();
                $connection->insert('tx_alice_analysis', $dbData);
            }

            return $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream(json_encode(['success' => true])));

        } catch (\Throwable $e) {
            return $this->responseFactory->createResponse(500)
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream(json_encode(['success' => false, 'error' => $e->getMessage()])));
        }
    }

    /**
     * Persists global extension settings.
     *
     * @param array $settings
     * @return ResponseInterface
     */
    public function saveSettingsAction(array $settings): ResponseInterface
    {
        $config = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class);
        $current = $config->get('alice');

        $current['lcp'] = (float)$settings['lcp'];
        $current['cls'] = (float)$settings['cls'];
        $current['inp'] = (int)$settings['inp'];
        $current['auto_lazyloading'] = (bool)($settings['autoLazyLoading'] ?? false);
        $current['openai_api_key'] = (string)($settings['openaiApiKey'] ?? '');

        $config->set('alice', $current);

        $this->addFlashMessage('Configuration saved successfully.');
        return $this->redirect('index');
    }

    /**
     * Placeholder for global analysis triggering.
     *
     * @return ResponseInterface
     */
    public function analyzeAllAction(): ResponseInterface
    {
        $this->addFlashMessage('Global Analysis triggered (Simulation for now).');
        return $this->redirect('index');
    }

    /**
     * AJAX endpoint to retrieve all page IDs for the current site context.
     *
     * @return ResponseInterface
     */
    public function getSitePagesAction(): ResponseInterface
    {
        $data = $this->request->getParsedBody();
        if (empty($data)) {
            $data = json_decode((string)$this->request->getBody(), true);
        }
        $pageId = (int)($this->request->getQueryParams()['id'] ?? $data['id'] ?? 0);
        $pids = [];

        try {
            if ($pageId > 0) {
                $site = $this->siteFinder->getSiteByPageId($pageId);
                $pids = $this->getPageUidsForRoot($site->getRootPageId());
            } else {
                foreach ($this->siteFinder->getAllSites() as $site) {
                    $pids = array_merge($pids, $this->getPageUidsForRoot($site->getRootPageId()));
                }
            }
            
            return $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream(json_encode(['pages' => array_unique($pids), 'success' => true])));

        } catch (\Throwable $e) {
            return $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream(json_encode([
                    'pages' => [],
                    'success' => false,
                    'error' => $e->getMessage()
                ])));
        }
    }

    /**
     * Fetches page data including latest analysis results for a specific root.
     *
     * @param int $rootPageId
     * @return array
     */
    protected function getPagesForRoot(int $rootPageId): array
    {
        $sitePages = [];
        try {
            $qbPages = $this->connectionPool->getQueryBuilderForTable('pages');
            $rows = $qbPages
                ->select('p.uid', 'p.title', 'a.lcp', 'a.cls', 'a.inp', 'a.tstamp')
                ->from('pages', 'p')
                ->leftJoin(
                    'p',
                    'tx_alice_analysis',
                    'a',
                    $qbPages->expr()->eq('p.uid', $qbPages->quoteIdentifier('a.pid'))
                )
                ->where(
                    $qbPages->expr()->eq('p.sys_language_uid', 0),
                    $qbPages->expr()->eq('p.deleted', 0),
                    $qbPages->expr()->eq('p.hidden', 0),
                    $qbPages->expr()->or(
                        $qbPages->expr()->eq('p.uid', $qbPages->createNamedParameter($rootPageId, \Doctrine\DBAL\ParameterType::INTEGER)),
                        $qbPages->expr()->eq('p.pid', $qbPages->createNamedParameter($rootPageId, \Doctrine\DBAL\ParameterType::INTEGER))
                    )
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $sitePages[] = [
                    'uid' => (int)$row['uid'],
                    'title' => $row['title'],
                    'lcp' => $row['lcp'] !== null ? (float)$row['lcp'] : null,
                    'cls' => $row['cls'] !== null ? (float)$row['cls'] : null,
                    'inp' => $row['inp'] !== null ? (float)$row['inp'] : null,
                    'lastAudit' => $row['tstamp'] ? (int)$row['tstamp'] : null
                ];
            }
        } catch (\Throwable $e) {
            // Silently handle database errors
        }
        return $sitePages;
    }

    /**
     * Retrieves all page UIDs recursively for a given root page.
     *
     * @param int $rootPageId
     * @return array
     */
    protected function getPageUidsForRoot(int $rootPageId): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $rows = $qb->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('sys_language_uid', 0),
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->eq('hidden', 0),
                $qb->expr()->or(
                    $qb->expr()->eq('uid', $qb->createNamedParameter($rootPageId, \Doctrine\DBAL\ParameterType::INTEGER)),
                    $qb->expr()->eq('pid', $qb->createNamedParameter($rootPageId, \Doctrine\DBAL\ParameterType::INTEGER))
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
            
        return array_column($rows, 'uid');
    }
}
