<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\Upgrades;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * Replaces translated categories stored in the "Filter by category" field with their
 * default language category.
 *
 * The category tree of the FAQ content element lists only default language categories
 * (and categories for all languages) now. A stored translated category is not visible
 * in the tree anymore and never matches an FAQ item, because FAQ items only reference
 * default language categories. Translated categories without a default language parent
 * are left untouched.
 *
 * @author  Niels Tiedt <niels.tiedt@gedankenfolger.de>
 * @company Gedankenfolger GmbH
 */
#[UpgradeWizard('gedankenfolgerFaqFilterCategoryTranslationMigration')]
final class FilterCategoryTranslationMigrationWizard implements UpgradeWizardInterface
{
    private const TABLE = 'tt_content';
    private const CATEGORY_TABLE = 'sys_category';
    private const CONTENT_TYPE = 'gedankenfolger_faq';
    private const FILTER_FIELD = 'gedankenfolger_faq_filterByCategory';

    /**
     * @param ConnectionPool $connectionPool
     */
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Returns the speaking name of this wizard.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'EXT:gedankenfolger_faq: Replace translated categories in the FAQ category filter with their default language category';
    }

    /**
     * Returns the description of this wizard.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'The category tree of the FAQ filter lists only default language categories now. '
            . 'Translated categories stored in the "Filter by category" field are replaced by their '
            . 'default language category. Translated categories without a default language parent are not changed.';
    }

    /**
     * Returns true if at least one FAQ content element stores a translated category in the filter.
     *
     * @return bool
     */
    public function updateNecessary(): bool
    {
        return $this->collectUpdates() !== [];
    }

    /**
     * Replaces the stored translated categories with their default language category.
     *
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function executeUpdate(): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        foreach ($this->collectUpdates() as $uid => $value) {
            $connection->update(self::TABLE, [self::FILTER_FIELD => $value], ['uid' => $uid]);
        }

        return true;
    }

    /**
     * Returns the wizards that must have run before this one.
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * Collects the new filter value per tt_content uid.
     *
     * @return array<int, string>
     * @throws \Doctrine\DBAL\Exception
     */
    private function collectUpdates(): array
    {
        if (!$this->requiredColumnsExist()) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', self::FILTER_FIELD)
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter(self::CONTENT_TYPE, Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->neq(
                    self::FILTER_FIELD,
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $storedUids = [];
        foreach ($rows as $row) {
            $storedUids[] = $this->splitUids((string)$row[self::FILTER_FIELD]);
        }
        $parentByTranslatedUid = $this->fetchParentsOfTranslatedCategories(array_merge([], ...$storedUids));
        if ($parentByTranslatedUid === []) {
            return [];
        }

        $updates = [];
        foreach ($rows as $row) {
            $oldUids = $this->splitUids((string)$row[self::FILTER_FIELD]);
            $newUids = array_values(array_unique(array_map(
                static fn(int $uid): int => $parentByTranslatedUid[$uid] ?? $uid,
                $oldUids
            )));
            if ($newUids !== $oldUids) {
                $updates[(int)$row['uid']] = implode(',', $newUids);
            }
        }

        return $updates;
    }

    /**
     * Returns the default language parent uid for each given translated category that has one.
     *
     * @param int[] $categoryUids
     * @return array<int, int> Translated category uid => default language category uid
     * @throws \Doctrine\DBAL\Exception
     */
    private function fetchParentsOfTranslatedCategories(array $categoryUids): array
    {
        if ($categoryUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'l10n_parent')
            ->from(self::CATEGORY_TABLE)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter(array_values(array_unique($categoryUids)), ArrayParameterType::INTEGER)
                ),
                $queryBuilder->expr()->gt(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->gt(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $parents = [];
        foreach ($rows as $row) {
            $parents[(int)$row['uid']] = (int)$row['l10n_parent'];
        }

        return $parents;
    }

    /**
     * Splits a comma separated uid list into integers.
     *
     * @param string $value
     * @return int[]
     */
    private function splitUids(string $value): array
    {
        $uids = [];
        foreach (explode(',', $value) as $part) {
            if (ctype_digit(trim($part))) {
                $uids[] = (int)$part;
            }
        }

        return $uids;
    }

    /**
     * Checks that the required tables and columns exist, so the wizard list does not fail
     * with an SQL error before the database schema has been updated.
     *
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    private function requiredColumnsExist(): bool
    {
        $required = [
            self::TABLE => ['uid', 'CType', self::FILTER_FIELD],
            self::CATEGORY_TABLE => ['uid', 'sys_language_uid', 'l10n_parent'],
        ];
        foreach ($required as $table => $columns) {
            $schemaManager = $this->connectionPool->getConnectionForTable($table)->createSchemaManager();
            if (!$schemaManager->tablesExist([$table])) {
                return false;
            }
            $tableSchema = $schemaManager->introspectTable($table);
            foreach ($columns as $column) {
                if (!$tableSchema->hasColumn($column)) {
                    return false;
                }
            }
        }

        return true;
    }
}
