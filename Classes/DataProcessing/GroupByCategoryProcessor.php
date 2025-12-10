<?php
declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\DataProcessing;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\ArrayParameterType;

class GroupByCategoryProcessor implements DataProcessorInterface
{
    /**
     * Local cache for category uid => title to avoid repeated DB lookups.
     * @var array<int,string>
     */
    protected array $categoryTitleCache = [];

    /**
     * Process method called by TYPO3 DataProcessing pipeline.
     *
     * Configuration options (processorConfiguration):
     * - source: name of input array in $processedData (default: 'faqs')
     * - as: name to store grouped result (default: 'faqsGrouped')
     * - categoryField: field in each item to group by (default: 'categories')
     * - replaceSource: if true, overwrite the original source with grouped result
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData): array
    {
        $source = (string)($processorConfiguration['source'] ?? 'faqs');
        $as = (string)($processorConfiguration['as'] ?? 'faqsGrouped');
        $categoryField = (string)($processorConfiguration['categoryField'] ?? 'categories');
        $replaceSource = !empty($processorConfiguration['replaceSource']);

        // Check if grouping should be enabled (respect if.isTrue.field = gedankenfolger_faq_groupByCategory)
        $groupFlag = (int)($cObj->data['gedankenfolger_faq_groupByCategory'] ?? $cObj->data['gedankenfolger_faq_groupbycategory'] ?? 0);
        if (!$groupFlag) {
            // Grouping not requested — return processedData unchanged
            return $processedData;
        }

        $items = $processedData[$source] ?? [];

        // If no items were provided by a previous processor, fetch them from DB.
        // Processor configuration may provide a `table` and `pidInList` to control fetching.
        if (empty($items)) {
            if (!empty($processorConfiguration['table'])) {
                $table = (string)$processorConfiguration['table'];
                $orderBy = (string)($processorConfiguration['orderBy'] ?? 'sorting');
                $pid = null;
                // Support TypoScript dot-notation: pidInList.{ field = foo }
                if (isset($processorConfiguration['pidInList.']['field'])) {
                    $pidFieldName = (string)$processorConfiguration['pidInList.']['field'];
                    $pid = $cObj->data[$pidFieldName] ?? null;
                } elseif (isset($processorConfiguration['pidInList.field'])) {
                    // Fallback if configuration used a flattened key
                    $pidFieldName = (string)$processorConfiguration['pidInList.field'];
                    $pid = $cObj->data[$pidFieldName] ?? null;
                } elseif (isset($processorConfiguration['pidInList'])) {
                    // Direct numeric or comma-separated pid list
                    $pid = $processorConfiguration['pidInList'];
                }

                if ($pid !== null && $pid !== '') {
                    $items = $this->fetchRowsByTableAndPid($table, $pid, $orderBy);
                }
            } else {
                $pid = $cObj->data['gedankenfolger_faq_storageFolder'] ?? null;
                if ($pid) {
                    $items = $this->fetchFaqsByPid($pid);
                }
            }
        }

        $groups = [];
        foreach ($items as $item) {
            $row = $item['data'] ?? $item;

            $catValue = $row[$categoryField] ?? null;
            $label = 'Uncategorized';

            if (is_array($catValue)) {
                $first = reset($catValue);
                $label = $this->resolveCategoryLabel($first);
            } elseif (is_numeric($catValue)) {
                $label = $this->resolveCategoryLabel((int)$catValue);
            } elseif (is_string($catValue) && $catValue !== '') {
                $label = $catValue;
            }

            if (!isset($groups[$label])) {
                $groups[$label] = [
                    'category' => $label,
                    'items' => [],
                ];
            }
            $groups[$label]['items'][] = $item;
        }

        // Preserve ordering by converting to indexed array
        $result = array_values($groups);

        $processedData[$as] = $result;
        if ($replaceSource) {
            $processedData[$source] = $result;
        }

        return $processedData;
    }

    /**
     * Convenience wrapper to load FAQ items by pid using default table and order.
     */
    protected function fetchFaqsByPid($pid): array
    {
        return $this->fetchRowsByTableAndPid('tx_gedankenfolger_faq_item', $pid, 'sorting');
    }

    /**
     * Fetch rows from a table for one or multiple PIDs (comma-separated list supported).
     * Applies FrontendRestrictionContainer for visibility rules.
     * The result is normalized to the typical DataProcessor row format: ['data' => <row>].
     */
    protected function fetchRowsByTableAndPid(string $table, $pid, string $orderBy = 'sorting'): array
    {
        $result = [];

        // Basic table name validation to reduce risk of malformed configuration
        if ($table === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [];
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        // Apply standard FE restrictions (enable fields, etc.)
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        // support numeric pid or comma-separated list
        if (is_string($pid) && strpos($pid, ',') !== false) {
            $pids = array_map('intval', array_filter(array_map('trim', explode(',', $pid))));
            if (empty($pids)) {
                return [];
            }
            $expr = $qb->expr()->in('pid', $qb->createNamedParameter($pids, ArrayParameterType::INTEGER));
            [$orderColumn, $orderDirection] = $this->sanitizeOrderBy($orderBy);
            $qb->select('*')->from($table)->where($expr)->orderBy($orderColumn, $orderDirection);
        } else {
            $pidInt = (int)$pid;
            if ($pidInt <= 0) {
                return [];
            }
            [$orderColumn, $orderDirection] = $this->sanitizeOrderBy($orderBy);
            $qb->select('*')
                ->from($table)
                ->where($qb->expr()->eq('pid', $qb->createNamedParameter($pidInt, ParameterType::INTEGER)))
                ->orderBy($orderColumn, $orderDirection);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $result[] = ['data' => $row];
        }

        return $result;
    }

    /**
     * Resolve a category label from a category uid or plain string. Caches results per request.
     */
    protected function resolveCategoryLabel($uidOrString): string
    {
        if ($uidOrString === null || $uidOrString === '') {
            return 'Uncategorized';
        }

        if (!is_numeric($uidOrString)) {
            return (string)$uidOrString;
        }

        $uid = (int)$uidOrString;
        if ($uid <= 0) {
            return 'Uncategorized';
        }

        if (isset($this->categoryTitleCache[$uid])) {
            return $this->categoryTitleCache[$uid];
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
        $row = $qb
            ->select('title')
            ->from('sys_category')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        $title = $row['title'] ?? ('Category ' . $uid);
        $this->categoryTitleCache[$uid] = $title;
        return $title;
    }

    /**
     * Sanitize orderBy configuration into [column, direction]. Only allows [a-zA-Z0-9_].
     * Defaults to ['sorting', 'ASC'] if invalid.
     *
     * @return array{0:string,1:string}
     */
    protected function sanitizeOrderBy(string $orderBy): array
    {
        $orderBy = trim($orderBy);
        $column = 'sorting';
        $direction = 'ASC';

        if ($orderBy !== '') {
            // Parse optional direction
            if (preg_match('/^([a-zA-Z0-9_]+)\s+(ASC|DESC)$/i', $orderBy, $m)) {
                $candidate = $m[1];
                $dir = strtoupper($m[2]);
            } else {
                $candidate = $orderBy;
                $dir = 'ASC';
            }
            if (preg_match('/^[a-zA-Z0-9_]+$/', $candidate)) {
                $column = $candidate;
            }
            if (in_array($dir, ['ASC', 'DESC'], true)) {
                $direction = $dir;
            }
        }

        return [$column, $direction];
    }
}
