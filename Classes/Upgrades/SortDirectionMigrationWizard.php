<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\Upgrades;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * Splits a sort direction stored in the "Sort by" text fields (for example "question DESC")
 * into the column name and the separate sort direction select fields.
 *
 * Affects the FAQ content element fields "orderBy" and "categoryOrderBy". Values without a
 * trailing ASC/DESC are left untouched.
 *
 * @author  Niels Tiedt <niels.tiedt@gedankenfolger.de>
 * @company Gedankenfolger GmbH
 */
#[UpgradeWizard('gedankenfolgerFaqSortDirectionMigration')]
final class SortDirectionMigrationWizard implements UpgradeWizardInterface
{
    private const TABLE = 'tt_content';
    private const CONTENT_TYPE = 'gedankenfolger_faq';

    /**
     * Maps each "Sort by" column to the column that receives the extracted direction.
     */
    private const FIELD_PAIRS = [
        'gedankenfolger_faq_orderBy' => 'gedankenfolger_faq_orderDirection',
        'gedankenfolger_faq_categoryOrderBy' => 'gedankenfolger_faq_categoryOrderDirection',
    ];

    private const PATTERN = '/^([a-zA-Z0-9_-]+)\s+(ASC|DESC)$/i';

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
        return 'EXT:gedankenfolger_faq: Move sort direction from the Sort by fields into the sort direction fields';
    }

    /**
     * Returns the description of this wizard.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'The FAQ fields "Sort by" and "Category sort by" accept only a column name now. '
            . 'A direction stored there (for example "question DESC") is split into the column name '
            . 'and the new sort direction fields. Values without a direction are not changed.';
    }

    /**
     * Returns true if at least one FAQ content element stores a direction in a "Sort by" field.
     *
     * @return bool
     */
    public function updateNecessary(): bool
    {
        return $this->collectUpdates() !== [];
    }

    /**
     * Splits the stored values into column name and direction.
     *
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function executeUpdate(): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        foreach ($this->collectUpdates() as $uid => $data) {
            $connection->update(self::TABLE, $data, ['uid' => $uid]);
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
     * Collects the column updates per tt_content uid.
     *
     * @return array<int, array<string, string>>
     */
    private function collectUpdates(): array
    {
        if (!$this->sortByColumnsExist()) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $likeConditions = [];
        foreach (array_keys(self::FIELD_PAIRS) as $orderByField) {
            $likeConditions[] = $queryBuilder->expr()->like(
                $orderByField,
                $queryBuilder->createNamedParameter('% %')
            );
        }

        $rows = $queryBuilder
            ->select('uid', ...array_keys(self::FIELD_PAIRS))
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter(self::CONTENT_TYPE, Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->or(...$likeConditions)
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $updates = [];
        foreach ($rows as $row) {
            $data = [];
            foreach (self::FIELD_PAIRS as $orderByField => $directionField) {
                if (preg_match(self::PATTERN, trim((string)$row[$orderByField]), $matches) === 1) {
                    $data[$orderByField] = $matches[1];
                    $data[$directionField] = strtoupper($matches[2]);
                }
            }
            if ($data !== []) {
                $updates[(int)$row['uid']] = $data;
            }
        }

        return $updates;
    }

    /**
     * Checks that tt_content and both "Sort by" columns exist, so the wizard list does not fail
     * with an SQL error before the database schema has been updated.
     *
     * @return bool
     */
    private function sortByColumnsExist(): bool
    {
        $schemaManager = $this->connectionPool->getConnectionForTable(self::TABLE)->createSchemaManager();
        if (!$schemaManager->tablesExist([self::TABLE])) {
            return false;
        }

        $tableSchema = $schemaManager->introspectTable(self::TABLE);
        foreach (array_keys(self::FIELD_PAIRS) as $orderByField) {
            if (!$tableSchema->hasColumn($orderByField)) {
                return false;
            }
        }

        return true;
    }
}
