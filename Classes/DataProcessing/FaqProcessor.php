<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\DataProcessing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Fetches FAQ records, resolves their sys_category relations (many-to-many),
 * optionally filters by selected categories, and optionally groups the result by categories.
 *
 * Output variables (defaults):
 * - "faqs": flat list of FAQ items (each item is an array with at least "data" and "categories")
 * - "faqsByCategory": grouped list (each group contains "category" and "items")
 *
 * TypoScript configuration (processorConfiguration):
 * - table: FAQ table name (default: tx_gedankenfolger_faq_item)
 * - pidInList / pidInList.field / pidInList.{field}: storage PID(s), comma-separated supported
 * - recursive / recursive.field / recursive.{field}: page recursion depth (default: 0)
 * - orderBy / orderBy.field / orderBy.{field,ifEmpty}: FAQ ordering (default: sorting ASC)
 *
 * - categoryField: relation field name in sys_category_record_mm.fieldname (default: categories)
 * - categoryOrderBy / categoryOrderBy.field / categoryOrderBy.{field,ifEmpty}: category ordering
 *   (default: sorting ASC; refers to sys_category.<column>)
 *
 * - asFlat: target key for flat list (default: faqs)
 * - asGrouped: target key for grouped list (default: faqsByCategory)
 *
 * - groupByCategoryField: content element field name controlling grouping
 *   (default: gedankenfolger_faq_groupByCategory)
 * - filterByCategoryField: content element field name providing selected category uids
 *   (default: gedankenfolger_faq_filterByCategory)
 *
 * - resolveToRecordObjects: if true, additionally attaches Record objects:
 *   - each FAQ item gets "record"
 *   - each category in FAQ item gets "record"
 *   - each group "category" gets "record"
 *
 * Notes on Record objects:
 * - This mimics what the record-transformation data processor does via RecordFactory.
 * - Record objects are available in TYPO3 v13+ and are primarily intended for Fluid usage.
 */
final class FaqProcessor implements DataProcessorInterface
{
    private const DEFAULT_FAQ_TABLE = 'tx_gedankenfolger_faq_item';
    private const CATEGORY_TABLE = 'sys_category';
    private const CATEGORY_MM_TABLE = 'sys_category_record_mm';

    private ConnectionPool $connectionPool;
    private ?RecordFactory $recordFactory;

    public function __construct(
        ?ConnectionPool $connectionPool = null,
        ?RecordFactory $recordFactory = null,
    ) {
        // Keep DI-friendly, but do not require service registration.
        $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);

        // RecordFactory exists in TYPO3 v13+. Keep optional to avoid hard failures in edge setups.
        $this->recordFactory = $recordFactory ?? (class_exists(RecordFactory::class) ? GeneralUtility::makeInstance(RecordFactory::class) : null);
    }

    /**
     * @param array<string|int, mixed> $contentObjectConfiguration
     * @param array<string|int, mixed> $processorConfiguration
     * @param array<string|int, mixed> $processedData
     * @return array<string|int, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $table = $this->sanitizeTableName((string)($processorConfiguration['table'] ?? self::DEFAULT_FAQ_TABLE));
        if ($table === '') {
            return $processedData;
        }

        $categoryField = (string)($processorConfiguration['categoryField'] ?? 'categories');

        $asFlat = (string)($processorConfiguration['asFlat'] ?? 'faqs');
        $asGrouped = (string)($processorConfiguration['asGrouped'] ?? ($processorConfiguration['as'] ?? 'faqsByCategory'));

        $orderByFaq = $this->resolveOrderBy($processorConfiguration, $cObj, 'orderBy', 'sorting');
        $recursive = $this->resolveIntFromConfig($processorConfiguration, $cObj, 'recursive', 0);

        $categoryOrderBy = $this->resolveOrderBy($processorConfiguration, $cObj, 'categoryOrderBy', 'sorting');

        $groupByCategoryField = (string)($processorConfiguration['groupByCategoryField'] ?? 'gedankenfolger_faq_groupByCategory');
        $groupEnabled = (int)($cObj->data[$groupByCategoryField] ?? 0) === 1;

        $filterByCategoryField = (string)($processorConfiguration['filterByCategoryField'] ?? 'gedankenfolger_faq_filterByCategory');
        $filterCategoryUids = $this->normalizeIntegerList($cObj->data[$filterByCategoryField] ?? null);
        $filterActive = $filterCategoryUids !== [];

        $resolveToRecordObjects = !empty($processorConfiguration['resolveToRecordObjects']) && $this->recordFactory !== null;

        $pidInList = $this->resolvePidInList($processorConfiguration, $cObj);
        $pidsToQuery = $this->normalizePidList($pidInList, $recursive);

        if ($pidsToQuery === []) {
            $processedData[$asFlat] = [];
            $processedData[$asGrouped] = [];
            return $processedData;
        }

        $faqRows = $this->fetchFaqRows(
            $table,
            $pidsToQuery,
            $orderByFaq,
            $categoryField,
            $filterCategoryUids
        );

        if ($faqRows === []) {
            $processedData[$asFlat] = [];
            $processedData[$asGrouped] = [];
            return $processedData;
        }

        $faqUids = array_values(array_unique(array_map(
            static fn(array $row): int => (int)($row['uid'] ?? 0),
            $faqRows
        )));
        $faqUids = array_values(array_filter($faqUids, static fn(int $uid): bool => $uid > 0));

        // Fetch mm relations first (uid_foreign => [uid_local...]) in one query.
        [$categoryUidsByFaqUid, $allCategoryUids] = $this->fetchCategoryRelations(
            $table,
            $categoryField,
            $faqUids
        );

        // If filtering is active, keep only selected categories for output and grouping
        // to avoid showing unrelated categories/groups.
        if ($filterActive) {
            $filterSet = array_fill_keys($filterCategoryUids, true);

            foreach ($categoryUidsByFaqUid as $faqUid => $catUids) {
                $categoryUidsByFaqUid[$faqUid] = array_values(array_filter(
                    $catUids,
                    static fn(int $catUid): bool => isset($filterSet[$catUid])
                ));
            }

            $allCategoryUids = array_values(array_filter(
                $allCategoryUids,
                static fn(int $catUid) => isset($filterSet[$catUid])
            ));
        }

        // Fetch category rows once, ordered.
        $categoryRows = $this->fetchCategoryRows($allCategoryUids, $categoryOrderBy);
        $categoryRowByUid = [];
        foreach ($categoryRows as $catRow) {
            $uid = (int)($catRow['uid'] ?? 0);
            if ($uid > 0) {
                $categoryRowByUid[$uid] = $catRow;
            }
        }

        // Build category order index for stable per-item category ordering.
        $categoryOrderIndex = [];
        foreach ($categoryRows as $idx => $catRow) {
            $uid = (int)($catRow['uid'] ?? 0);
            if ($uid > 0) {
                $categoryOrderIndex[$uid] = $idx;
            }
        }

        // Build flat items (DataProcessor-style arrays).
        $items = [];
        foreach ($faqRows as $faqRow) {
            $faqUid = (int)($faqRow['uid'] ?? 0);
            $catUids = $categoryUidsByFaqUid[$faqUid] ?? [];

            // Sort categories for this FAQ by the global category ordering.
            usort($catUids, static function (int $a, int $b) use ($categoryOrderIndex): int {
                return ($categoryOrderIndex[$a] ?? PHP_INT_MAX) <=> ($categoryOrderIndex[$b] ?? PHP_INT_MAX);
            });

            $categories = [];
            foreach ($catUids as $catUid) {
                if (!isset($categoryRowByUid[$catUid])) {
                    continue;
                }
                $catRow = $categoryRowByUid[$catUid];

                $categoryEntry = ['data' => $catRow];
                if ($resolveToRecordObjects) {
                    $categoryEntry['record'] = $this->safeCreateResolvedRecord(self::CATEGORY_TABLE, $catRow);
                }
                $categories[] = $categoryEntry;
            }

            $item = [
                'data' => $faqRow,
                'categories' => $categories,
            ];

            if ($resolveToRecordObjects) {
                $item['record'] = $this->safeCreateResolvedRecord($table, $faqRow);
            }

            $items[] = $item;
        }

        $processedData[$asFlat] = $items;

        // Grouped output
        $groups = [];
        if ($groupEnabled) {
            // Create groups in category order.
            foreach ($categoryRows as $catRow) {
                $catUid = (int)($catRow['uid'] ?? 0);
                if ($catUid <= 0) {
                    continue;
                }

                $categoryGroup = [
                    'data' => $catRow,
                ];
                if ($resolveToRecordObjects) {
                    $categoryGroup['record'] = $this->safeCreateResolvedRecord(self::CATEGORY_TABLE, $catRow);
                }

                $groups[$catUid] = [
                    'category' => $categoryGroup,
                    'items' => [],
                ];
            }

            $uncategorizedKey = 0;
            $groups[$uncategorizedKey] = [
                'category' => [
                    'data' => [
                        'uid' => 0,
                        'title' => 'Uncategorized',
                    ],
                ],
                'items' => [],
            ];

            // Put each FAQ into every matching category group.
            foreach ($items as $item) {
                $faqUid = (int)($item['data']['uid'] ?? 0);
                $catUids = $categoryUidsByFaqUid[$faqUid] ?? [];

                if ($catUids === []) {
                    $groups[$uncategorizedKey]['items'][] = $item;
                    continue;
                }

                foreach ($catUids as $catUid) {
                    if (!isset($groups[$catUid])) {
                        continue;
                    }
                    $groups[$catUid]['items'][] = $item;
                }
            }

            // Drop empty "Uncategorized" group if not used (common when filtering is active).
            if ($groups[$uncategorizedKey]['items'] === []) {
                unset($groups[$uncategorizedKey]);
            }
        }

        $processedData[$asGrouped] = array_values($groups);

        return $processedData;
    }

    /**
     * Fetch FAQ records from $table for the given PIDs, with optional category filter.
     *
     * @param int[] $pidsToQuery
     * @param int[] $filterCategoryUids
     * @return array<int, array<string|int, mixed>>
     */
    private function fetchFaqRows(
        string $table,
        array $pidsToQuery,
        string $orderBy,
        string $categoryField,
        array $filterCategoryUids
    ): array {
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        [$orderColumn, $orderDirection] = $this->sanitizeOrderBy($orderBy, 'sorting');
        $orderByExpression = 'i.' . $orderColumn;

        $qb->select('i.*')
            ->from($table, 'i')
            ->where(
                $qb->expr()->in('i.pid', $qb->createNamedParameter($pidsToQuery, ArrayParameterType::INTEGER))
            )
            ->orderBy($orderByExpression, $orderDirection);

        // Optional filter by selected categories (OR semantics).
        if ($filterCategoryUids !== []) {
            $qb->innerJoin(
                'i',
                self::CATEGORY_MM_TABLE,
                'mm',
                'mm.uid_foreign = i.uid'
            );

            $qb->andWhere(
                $qb->expr()->eq('mm.tablenames', $qb->createNamedParameter($table, ParameterType::STRING)),
                $qb->expr()->eq('mm.fieldname', $qb->createNamedParameter($categoryField, ParameterType::STRING)),
                $qb->expr()->in('mm.uid_local', $qb->createNamedParameter($filterCategoryUids, ArrayParameterType::INTEGER))
            );

            // Avoid duplicates caused by multiple matching relations.
            $qb->groupBy('i.uid');
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Fetch sys_category_record_mm relations for the given foreign uids.
     *
     * @param int[] $foreignUids
     * @return array{0: array<int, int[]>, 1: int[]} [uidsByForeignUid, allCategoryUids]
     */
    private function fetchCategoryRelations(string $table, string $categoryField, array $foreignUids): array
    {
        if ($foreignUids === []) {
            return [[], []];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);

        $rows = $qb->select('uid_foreign', 'uid_local')
            ->from(self::CATEGORY_MM_TABLE)
            ->where(
                $qb->expr()->eq('tablenames', $qb->createNamedParameter($table, ParameterType::STRING)),
                $qb->expr()->eq('fieldname', $qb->createNamedParameter($categoryField, ParameterType::STRING)),
                $qb->expr()->in('uid_foreign', $qb->createNamedParameter($foreignUids, ArrayParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $uidsByForeignUid = [];
        $allCategoryUids = [];

        foreach ($rows as $row) {
            $foreignUid = (int)($row['uid_foreign'] ?? 0);
            $categoryUid = (int)($row['uid_local'] ?? 0);

            if ($foreignUid <= 0 || $categoryUid <= 0) {
                continue;
            }

            $uidsByForeignUid[$foreignUid][] = $categoryUid;
            $allCategoryUids[] = $categoryUid;
        }

        // Deduplicate lists.
        foreach ($uidsByForeignUid as $foreignUid => $catUids) {
            $uidsByForeignUid[$foreignUid] = array_values(array_unique($catUids));
        }

        $allCategoryUids = array_values(array_unique($allCategoryUids));

        return [$uidsByForeignUid, $allCategoryUids];
    }

    /**
     * Fetch sys_category rows for the given uids, ordered by $orderBy.
     *
     * @param int[] $categoryUids
     * @return array<int, array<string|int, mixed>>
     */
    private function fetchCategoryRows(array $categoryUids, string $orderBy): array
    {
        if ($categoryUids === []) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

        [$orderColumn, $orderDirection] = $this->sanitizeOrderBy($orderBy, 'sorting');
        $orderByExpression = 'c.' . $orderColumn;

        return $qb->select('c.*')
            ->from(self::CATEGORY_TABLE, 'c')
            ->where(
                $qb->expr()->in('c.uid', $qb->createNamedParameter($categoryUids, ArrayParameterType::INTEGER))
            )
            ->orderBy($orderByExpression, $orderDirection)
            ->addOrderBy('c.uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Resolve pidInList (direct value or via pidInList.field).
     *
     * @param array<string|int, mixed> $processorConfiguration
     * @return mixed
     */
    private function resolvePidInList(array $processorConfiguration, ContentObjectRenderer $cObj)
    {
        // pidInList.{ field = foo }
        if (isset($processorConfiguration['pidInList.']['field'])) {
            $pidFieldName = (string)$processorConfiguration['pidInList.']['field'];
            return $cObj->data[$pidFieldName] ?? null;
        }

        // pidInList.field = foo (flattened)
        if (isset($processorConfiguration['pidInList.field'])) {
            $pidFieldName = (string)$processorConfiguration['pidInList.field'];
            return $cObj->data[$pidFieldName] ?? null;
        }

        // pidInList = 123 or "123,456"
        return $processorConfiguration['pidInList'] ?? null;
    }

    /**
     * Normalize PID list and apply recursion expansion.
     *
     * @param mixed $pidInList
     * @return int[]
     */
    private function normalizePidList($pidInList, int $recursive): array
    {
        $pids = $this->normalizeIntegerList($pidInList);
        if ($pids === []) {
            return [];
        }

        if ($recursive <= 0) {
            return $pids;
        }

        return $this->expandPidsRecursive($pids, $recursive);
    }

    /**
     * Expand a list of PIDs to include child pages up to $recursive levels.
     *
     * @param int[] $pids
     * @return int[]
     */
    private function expandPidsRecursive(array $pids, int $recursive): array
    {
        $expanded = $pids;
        $currentLevel = $pids;

        for ($level = 0; $level < $recursive; $level++) {
            if ($currentLevel === []) {
                break;
            }

            $qb = $this->connectionPool->getQueryBuilderForTable('pages');
            $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));

            $rows = $qb->select('uid')
                ->from('pages')
                ->where(
                    $qb->expr()->in('pid', $qb->createNamedParameter($currentLevel, ArrayParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAllAssociative();

            $children = [];
            foreach ($rows as $row) {
                $uid = (int)($row['uid'] ?? 0);
                if ($uid > 0) {
                    $children[] = $uid;
                }
            }

            $children = array_values(array_unique($children));
            if ($children === []) {
                break;
            }

            $expanded = array_values(array_unique(array_merge($expanded, $children)));
            $currentLevel = $children;
        }

        return $expanded;
    }

    /**
     * Resolve a string orderBy config with optional ".field" and ".ifEmpty" semantics.
     *
     * @param array<string|int, mixed> $processorConfiguration
     */
    private function resolveOrderBy(
        array $processorConfiguration,
        ContentObjectRenderer $cObj,
        string $key,
        string $default
    ): string {
        // {key}.{ field = foo, ifEmpty = bar }
        if (isset($processorConfiguration[$key . '.']['field'])) {
            $fieldName = (string)$processorConfiguration[$key . '.']['field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
            if (isset($processorConfiguration[$key . '.']['ifEmpty'])) {
                return (string)$processorConfiguration[$key . '.']['ifEmpty'];
            }
            return $default;
        }

        // flattened {key}.field
        if (isset($processorConfiguration[$key . '.field'])) {
            $fieldName = (string)$processorConfiguration[$key . '.field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        if (isset($processorConfiguration[$key])) {
            $value = (string)$processorConfiguration[$key];
            return $value !== '' ? $value : $default;
        }

        return $default;
    }

    /**
     * Resolve an integer config with optional ".field" semantics.
     *
     * @param array<string|int, mixed> $processorConfiguration
     */
    private function resolveIntFromConfig(
        array $processorConfiguration,
        ContentObjectRenderer $cObj,
        string $key,
        int $default
    ): int {
        if (isset($processorConfiguration[$key . '.']['field'])) {
            $fieldName = (string)$processorConfiguration[$key . '.']['field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (int)$value;
            }
            return $default;
        }

        if (isset($processorConfiguration[$key . '.field'])) {
            $fieldName = (string)$processorConfiguration[$key . '.field'];
            $value = $cObj->data[$fieldName] ?? null;
            if ($value !== null && $value !== '') {
                return (int)$value;
            }
        }

        if (isset($processorConfiguration[$key])) {
            return (int)$processorConfiguration[$key];
        }

        return $default;
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    private function normalizeIntegerList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_int($value)) {
            return $value > 0 ? [$value] : [];
        }

        if (is_string($value)) {
            $parts = array_map('trim', explode(',', $value));
            $ints = array_map('intval', array_filter($parts, static fn(string $v): bool => $v !== ''));
            $ints = array_values(array_filter($ints, static fn(int $v): bool => $v > 0));
            return array_values(array_unique($ints));
        }

        if (is_array($value)) {
            $ints = array_map('intval', $value);
            $ints = array_values(array_filter($ints, static fn(int $v): bool => $v > 0));
            return array_values(array_unique($ints));
        }

        return [];
    }

    /**
     * @return string Empty string if invalid.
     */
    private function sanitizeTableName(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return '';
        }
        return preg_match('/^[a-zA-Z0-9_]+$/', $table) ? $table : '';
    }

    /**
     * Sanitize an orderBy string into [column, direction].
     * Only allows column names consisting of [a-zA-Z0-9_].
     *
     * Accepted formats:
     * - "sorting"
     * - "sorting ASC"
     * - "sorting DESC"
     *
     * @return array{0: string, 1: 'ASC'|'DESC'}
     */
    private function sanitizeOrderBy(string $orderBy, string $defaultColumn): array
    {
        $orderBy = trim($orderBy);
        $column = $defaultColumn;
        $direction = 'ASC';

        if ($orderBy !== '') {
            $candidate = $orderBy;
            $dir = 'ASC';

            if (preg_match('/^([a-zA-Z0-9_]+)\s+(ASC|DESC)$/i', $orderBy, $m)) {
                $candidate = $m[1];
                $dir = strtoupper($m[2]);
            }

            if (preg_match('/^[a-zA-Z0-9_]+$/', $candidate)) {
                $column = $candidate;
            }

            if ($dir === 'ASC' || $dir === 'DESC') {
                $direction = $dir;
            }
        }

        return [$column, $direction];
    }

    /**
     * Safely create a resolved Record object from a database row.
     * If RecordFactory is unavailable or throws, returns null.
     */
    private function safeCreateResolvedRecord(string $table, array $row): ?RecordInterface
    {
        if ($this->recordFactory === null) {
            return null;
        }

        try {
            return $this->recordFactory->createResolvedRecordFromDatabaseRow($table, $row);
        } catch (\Throwable) {
            return null;
        }
    }
}
