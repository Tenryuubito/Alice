<?php

declare(strict_types=1);

namespace Tenryuubito\Alice\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Service for aggregating and retrieving performance/SEO issues across multiple pages.
 */
class IssueService
{
    /**
     * @param ConnectionPool $connectionPool
     */
    public function __construct(
        protected readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Retrieves a list of all identified issues for a given root page context.
     * Aggregates SEO, image, and link issues from stored analysis records.
     *
     * @param int $rootPageId The root page UID to filter by (0 for global)
     * @return array List of issues with metadata
     */
    public function getGlobalIssues(int $rootPageId = 0): array
    {
        $issues = [];
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_alice_analysis');
        
        $results = $queryBuilder
            ->select('a.*', 'p.title as page_title')
            ->from('tx_alice_analysis', 'a')
            ->join('a', 'pages', 'p', 'a.pid = p.uid')
            ->orderBy('a.tstamp', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $row) {
            $data = json_decode((string)$row['results'], true);
            if (!$data) {
                continue;
            }

            $pid = (int)$row['pid'];
            $pageTitle = $row['page_title'];

            foreach ($data['seo']['issues'] ?? [] as $issue) {
                $isError = str_contains($issue, 'title') || str_contains($issue, 'description');
                $issues[] = [
                    'pid' => $pid,
                    'page' => $pageTitle,
                    'type' => 'SEO',
                    'status' => $isError ? 2 : 1,
                    'source' => 'Meta Tag',
                    'problem' => $issue
                ];
            }

            foreach ($data['images'] ?? [] as $img) {
                if (!empty($img['issue'])) {
                    $issues[] = [
                        'pid' => $pid,
                        'page' => $pageTitle,
                        'type' => 'Image',
                        'status' => 1,
                        'source' => $img['filename'],
                        'problem' => $img['issue']
                    ];
                }
            }

            foreach ($data['links'] ?? [] as $link) {
                if (!$link['accessible']) {
                    $issues[] = [
                        'pid' => $pid,
                        'page' => $pageTitle,
                        'type' => 'Link',
                        'status' => 2,
                        'source' => $link['url'],
                        'problem' => 'Link is broken (Status ' . $link['status'] . ')'
                    ];
                }
            }
        }

        return $issues;
    }
}
