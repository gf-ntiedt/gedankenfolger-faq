<?php
declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\DataProcessing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

class GroupByCategoryProcessor implements DataProcessorInterface
{
    /**
     * Local cache for category uid => full row to avoid repeated DB lookups.
     * @var array<int,array<string,mixed>>
     */
    protected array $categoryCache = [];

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $source = (string)($processorConfiguration['source'] ?? 'faqs');
        $as = (string)($processorConfiguration['as'] ?? 'faqsGrouped');
        $categoryField = (string)($processorConfiguration['categoryField'] ?? 'categories');
        $replaceSource = !empty($processorConfiguration['replaceSource']);

        // Respect grouping flag from CE data
        $groupFlag = (int)($cObj->data['gedankenfolger_faq_groupByCategory'] ?? $cObj->data['gedankenfolger_faq_groupbycategory'] ?? 0);
        if (!$groupFlag) {
            return $processedData;
        }

        $items = $processedData[$source] ?? [];

        // If no items were provided by a previous processor, fetch them from DB.
        if (empty($items)) {
            if (!empty($processorConfiguration['table'])) {
                $table = (string)$processorConfiguration['table'];
                $orderBy = $this->resolveOrderBy($processorConfiguration, $cObj);
                $recursive = $this->resolveRecursive($processorConfiguration, $cObj);

                $pid = null;
                if (isset($processorConfiguration['pidInList.']['field'])) {
                    $pidFieldName = (string)$processorConfiguration['pidInList.']['field'];
                    $pid = $cObj->data[$pidFieldName] ?? null;
                } elseif (isset($processorConfiguration['pidInList.field'])) {
                    $pidFieldName = (string)$processorConfiguration['pidInList.field'];
                    $pid = $cObj->data[$pidFieldName] ?? null;
                } elseif (isset($processorConfiguration['pidInList'])) {
                    $pid = $processorConfiguration['pidInList'];
                }

                if ($pid !== null && $pid !== '') {
                    $items = $this->fetchRowsByTableAndPid($table, $pid, $orderBy, $recursive);
                }
            } else {
                $pid = $cObj->data['gedankenfolger_faq_storageFolder'] ?? null;
                if ($pid) {
                    $items = $this->fetchFaqsByPid($pid);
                }
            }
        }

        // Ensure categories are resolved (array of sys_category rows) even if TS did not enrich them.
        $tableForMm = (string)($processorConfiguration['table'] ?? 'tx_gedankenfolger_faq_item');
        $this->ensureCategoriesResolved($items, $tableForMm, $categoryField);

        $groups = [];

        foreach ($items as $item) {
            $row = $item['data'] ?? $item;

            $categoryRecords = $this->extractCategoryRecords($item, $row, $categoryField);

            // Uncategorized
            if (empty($categoryRecords)) {
                $key = '__uncategorized__';
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'data' => null,
                        'label' => 'Uncategorized',
                        'items' => [],
                    ];
                }
                $groups[$key]['items'][] = $item;
                continue;
            }

            // manyToMany: put item into every category group
            foreach ($categoryRecords as $cat) {
                $uid = (int)($cat['uid'] ?? 0);
                $key = $uid > 0 ? ('uid_' . $uid) : ('title_' . (string)($cat['title'] ?? ''));
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'data' => $cat,                      // full sys_category row
                        'label' => (string)($cat['title'] ?? ''), // convenience for Fluid
                        'items' => [],
                    ];
                }
                $groups[$key]['items'][] = $item;
            }
        }

        $result = array_values($groups);

        $processedData[$as] = $result;
        if ($replaceSource) {
            $processedData[$source] = $result;
        }

        return $processedData;
    }

    protected function fetchFaqsByPid($pid): array
    {
        return $this->fetchRowsByTableAndPid('tx_gedankenfolger_faq_item', $pid, 'sorting', 0);
    }

    protected function resolveOrderBy(array $processorConfiguration, ContentObjectRenderer $cObj): string
    {
        if (isset($processorConfiguration['orderBy.']['field'])) {
            $fieldName = (string)$processorConfiguration['orderBy.']['field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
            if (isset($processorConfiguration['orderBy.']['ifEmpty'])) {
                return (string)$processorConfiguration['orderBy.']['ifEmpty'];
            }
            return 'sorting';
        }

        if (isset($processorConfiguration['orderBy.field'])) {
            $fieldName = (string)$processorConfiguration['orderBy.field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        if (isset($processorConfiguration['orderBy'])) {
            return (string)$processorConfiguration['orderBy'];
        }

        return 'sorting';
    }

    protected function resolveRecursive(array $processorConfiguration, ContentObjectRenderer $cObj): int
    {
        if (isset($processorConfiguration['recursive.']['field'])) {
            $fieldName = (string)$processorConfiguration['recursive.']['field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (int)$value;
            }
        }

        if (isset($processorConfiguration['recursive.field'])) {
            $fieldName = (string)$processorConfiguration['recursive.field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (int)$value;
            }
        }

        if (isset($processorConfiguration['recursive'])) {
            return (int)$processorConfiguration['recursive'];
        }

        return 0;
    }

    protected function fetchRowsByTableAndPid(string $table, $pid, string $orderBy = 'sorting', int $recursive = 0): array
    {
        $result = [];

        if ($table === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [];
        }

        $pidsToQuery = [];
        if (is_string($pid) && strpos($pid, ',') !== false) {
            $pidsToQuery = array_map('intval', array_filter(array_map('trim', explode(',', $pid))));
        } else {
            $pidInt = (int)$pid;
            if ($pidInt > 0) {
                $pidsToQuery[] = $pidInt;
            }
        }

        if (empty($pidsToQuery)) {
            return [];
        }

        if ($recursive > 0) {
            $pidsToQuery = $this->expandPidsRecursive($pidsToQuery, $recursive);
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        [$orderColumn, $orderDirection] = $this->sanitizeOrderBy($orderBy);

        if (count($pidsToQuery) === 1) {
            $qb->select('*')
                ->from($table)
                ->where($qb->expr()->eq('pid', $qb->createNamedParameter($pidsToQuery[0], ParameterType::INTEGER)))
                ->orderBy($orderColumn, $orderDirection);
        } else {
            $expr = $qb->expr()->in('pid', $qb->createNamedParameter($pidsToQuery, ArrayParameterType::INTEGER));
            $qb->select('*')->from($table)->where($expr)->orderBy($orderColumn, $orderDirection);
        }

        $rows = $qb->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $result[] = ['data' => $row];
        }

        return $result;
    }

    protected function expandPidsRecursive(array $pids, int $recursive): array
    {
        $expandedPids = $pids;

        for ($level = 0; $level < $recursive; $level++) {
            $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
            $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

            $childPids = [];
            $expr = $qb->expr()->in('pid', $qb->createNamedParameter($pids, ArrayParameterType::INTEGER));
            $rows = $qb->select('uid')
                ->from('pages')
                ->where($expr)
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $childPids[] = (int)$row['uid'];
            }

            if (empty($childPids)) {
                break;
            }

            $expandedPids = array_merge($expandedPids, $childPids);
            $pids = $childPids;
        }

        return array_unique($expandedPids);
    }

    /**
     * Returns the full sys_category row for a given uid (or null).
     */
    protected function resolveCategory(int $uid): ?array
    {
        if ($uid <= 0) {
            return null;
        }
        if (isset($this->categoryCache[$uid])) {
            return $this->categoryCache[$uid];
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $row = $qb
            ->select('*')
            ->from('sys_category')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        $this->categoryCache[$uid] = $row;
        return $row;
    }

    /**
     * If categories are not already attached as full sys_category rows, resolve them via sys_category_record_mm (bulk).
     */
    protected function ensureCategoriesResolved(array &$items, string $tableNameForMm, string $categoryField): void
    {
        $recordUids = [];
        $needsResolution = false;

        foreach ($items as $item) {
            $row = $item['data'] ?? $item;
            $uid = (int)($row['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            // If TS already enriched, categories are typically an array of arrays with 'uid'
            $existing = $item[$categoryField] ?? ($row[$categoryField] ?? null);
            if (is_array($existing) && $existing !== []) {
                $first = reset($existing);
                if (is_array($first) && array_key_exists('uid', $first)) {
                    continue;
                }
            }

            $recordUids[] = $uid;
            $needsResolution = true;
        }

        if (!$needsResolution || $recordUids === []) {
            return;
        }

        // 1) MM rows: record uid_foreign => category uid_local
        $qbMm = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
        $qbMm->getRestrictions()->removeAll();

        $mmRows = $qbMm
            ->select('uid_foreign', 'uid_local')
            ->from('sys_category_record_mm')
            ->where(
                $qbMm->expr()->eq('tablenames', $qbMm->createNamedParameter($tableNameForMm, ParameterType::STRING)),
                $qbMm->expr()->eq('fieldname', $qbMm->createNamedParameter($categoryField, ParameterType::STRING)),
                $qbMm->expr()->in('uid_foreign', $qbMm->createNamedParameter(array_values(array_unique($recordUids)), ArrayParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $recordToCategoryUids = [];
        $allCategoryUids = [];

        foreach ($mmRows as $mmRow) {
            $foreignUid = (int)($mmRow['uid_foreign'] ?? 0);
            $localUid = (int)($mmRow['uid_local'] ?? 0);
            if ($foreignUid <= 0 || $localUid <= 0) {
                continue;
            }
            $recordToCategoryUids[$foreignUid][] = $localUid;
            $allCategoryUids[$localUid] = true;
        }

        if ($allCategoryUids === []) {
            // nothing to attach
            foreach ($items as &$item) {
                $row = $item['data'] ?? $item;
                $uid = (int)($row['uid'] ?? 0);
                if ($uid > 0 && !isset($item[$categoryField])) {
                    $item[$categoryField] = [];
                }
            }
            unset($item);
            return;
        }

        // 2) Load category rows (respect FE enable fields)
        $qbCat = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category');
        $qbCat->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        $catRows = $qbCat
            ->select('*')
            ->from('sys_category')
            ->where(
                $qbCat->expr()->in('uid', $qbCat->createNamedParameter(array_keys($allCategoryUids), ArrayParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $catByUid = [];
        foreach ($catRows as $catRow) {
            $uid = (int)($catRow['uid'] ?? 0);
            if ($uid > 0) {
                $catByUid[$uid] = $catRow;
                $this->categoryCache[$uid] = $catRow;
            }
        }

        // 3) Attach to items
        foreach ($items as &$item) {
            $row = $item['data'] ?? $item;
            $uid = (int)($row['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            $uids = $recordToCategoryUids[$uid] ?? [];
            $resolved = [];
            foreach ($uids as $catUid) {
                if (isset($catByUid[$catUid])) {
                    $resolved[] = $catByUid[$catUid];
                }
            }

            $item[$categoryField] = $resolved;
        }
        unset($item);
    }

    /**
     * Extract list of category records from either enriched item or raw row.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function extractCategoryRecords(array $item, array $row, string $categoryField): array
    {
        $value = $item[$categoryField] ?? ($row[$categoryField] ?? null);

        if ($value === null || $value === '' || $value === 0) {
            return [];
        }

        // already a list of category rows
        if (is_array($value)) {
            if ($value === []) {
                return [];
            }
            $first = reset($value);

            // list of rows
            if (is_array($first) && array_key_exists('uid', $first)) {
                /** @var array<int,array<string,mixed>> $value */
                return $value;
            }

            // single row as assoc array
            if (isset($value['uid'])) {
                return [$value];
            }

            // list of uids (fallback)
            $resolved = [];
            foreach ($value as $maybeUid) {
                if (is_numeric($maybeUid)) {
                    $cat = $this->resolveCategory((int)$maybeUid);
                    if ($cat !== null) {
                        $resolved[] = $cat;
                    }
                }
            }
            return $resolved;
        }

        // numeric uid (legacy / fallback)
        if (is_numeric($value)) {
            $cat = $this->resolveCategory((int)$value);
            return $cat !== null ? [$cat] : [];
        }

        return [];
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function sanitizeOrderBy(string $orderBy): array
    {
        $orderBy = trim($orderBy);
        $column = 'sorting';
        $direction = 'ASC';

        if ($orderBy !== '') {
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
