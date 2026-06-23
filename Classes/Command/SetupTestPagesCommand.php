<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates test pages with all FAQ content element option variants for Playwright testing.
 *
 * @author  Niels Tiedt <niels.tiedt@gedankenfolger.de>
 * @company Gedankenfolger GmbH
 */
#[AsCommand(
    name: 'gedankenfolger-faq:setup-test-pages',
    description: 'Creates test pages with FAQ content element option variants for Playwright testing',
)]
final class SetupTestPagesCommand extends Command
{
    public const REGISTRY_NAMESPACE = 'gedankenfolger-faq:setup-test-pages';

    private const TEST_ROOT_TITLE = '__Test: FAQ';

    protected function configure(): void
    {
        $this
            ->addOption('pid', null, InputOption::VALUE_OPTIONAL, 'Parent page UID (auto-detected from site configuration if omitted)')
            ->addOption('site', null, InputOption::VALUE_OPTIONAL, 'Site identifier to use for root page detection (e.g. "main")')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Delete existing test pages before creating');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int Command::SUCCESS or Command::FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Bootstrap::initializeBackendAuthentication();

        $pidOption = $input->getOption('pid');
        $parentPid = $pidOption !== null ? (int)$pidOption : $this->detectRootPageId($input->getOption('site'), $output);
        $reset = (bool)$input->getOption('reset');

        $existingUid = $this->findExistingTestRoot($parentPid);

        if ($existingUid !== null) {
            if (!$reset) {
                $output->writeln('<comment>Test pages already exist (uid=' . $existingUid . '). Use --reset to recreate.</comment>');
                return Command::SUCCESS;
            }
            $this->deletePageTree($existingUid, $parentPid, $output);
        }

        $rootUid = $this->createPage($parentPid, self::TEST_ROOT_TITLE, hidden: 1, navHide: 1);
        $output->writeln('Page tree root: uid=' . $rootUid . ' (hidden, not in navigation)');

        $folderUid = $this->createPage($rootUid, 'FAQ Test Data', doktype: 254);
        $output->writeln('SysFolder for test data: uid=' . $folderUid);

        $variantPages = [
            'default'          => $this->createPage($rootUid, 'FAQ: Default'),
            'open-first'       => $this->createPage($rootUid, 'FAQ: Open First Item'),
            'open-single-only' => $this->createPage($rootUid, 'FAQ: Open Single Only'),
            'grouped'          => $this->createPage($rootUid, 'FAQ: Grouped by Category'),
            'grouped-titles'   => $this->createPage($rootUid, 'FAQ: Grouped with Category Titles'),
        ];

        $categories = $this->createCategories($folderUid);
        $this->createFaqItems($folderUid, $categories);

        $this->createContentElement($variantPages['default'], $folderUid, 'Default', []);
        $this->createContentElement($variantPages['open-first'], $folderUid, 'Open First Item', [
            'gedankenfolger_faq_openItem' => 'first',
        ]);
        $this->createContentElement($variantPages['open-single-only'], $folderUid, 'Open Single Only', [
            'gedankenfolger_faq_openSingleOnly' => 1,
        ]);
        $this->createContentElement($variantPages['grouped'], $folderUid, 'Grouped by Category', [
            'gedankenfolger_faq_groupByCategory' => 1,
        ]);
        $this->createContentElement($variantPages['grouped-titles'], $folderUid, 'Grouped with Category Titles', [
            'gedankenfolger_faq_groupByCategory'    => 1,
            'gedankenfolger_faq_showCategoryTitles' => 1,
        ]);

        GeneralUtility::makeInstance(Registry::class)->set(
            $this->registryNamespace(),
            'test_data_' . $parentPid,
            ['rootUid' => $rootUid, 'folderUid' => $folderUid, 'variants' => $variantPages]
        );

        $output->writeln('');
        $output->writeln('<info>Done. Test pages created:</info>');
        foreach ($variantPages as $key => $uid) {
            $output->writeln(sprintf('  uid=%-6d  %s', $uid, $key));
        }

        return Command::SUCCESS;
    }

    private function registryNamespace(): string
    {
        return self::REGISTRY_NAMESPACE;
    }

    private function detectRootPageId(?string $siteIdentifier, OutputInterface $output): int
    {
        $sites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();

        if ($sites === []) {
            $output->writeln('<comment>No site configuration found, falling back to pid=1. Use --pid to override.</comment>');
            return 1;
        }

        if ($siteIdentifier !== null) {
            foreach ($sites as $site) {
                if ($site->getIdentifier() === $siteIdentifier) {
                    $output->writeln('Using site "' . $siteIdentifier . '" (rootPageId=' . $site->getRootPageId() . ')');
                    return $site->getRootPageId();
                }
            }
            $output->writeln('<comment>Site "' . $siteIdentifier . '" not found, falling back to first site. Use --pid to override.</comment>');
        }

        $site = array_values($sites)[0];

        if (count($sites) > 1) {
            $output->writeln('<comment>Multiple sites found, using first: "' . $site->getIdentifier() . '". Use --site to select a different one.</comment>');
        } else {
            $output->writeln('Using site "' . $site->getIdentifier() . '" (rootPageId=' . $site->getRootPageId() . ')');
        }

        return $site->getRootPageId();
    }

    private function findExistingTestRoot(int $parentPid): ?int
    {
        $data = GeneralUtility::makeInstance(Registry::class)->get($this->registryNamespace(), 'test_data_' . $parentPid);
        return isset($data['rootUid']) ? (int)$data['rootUid'] : null;
    }

    private function deletePageTree(int $rootUid, int $parentPid, OutputInterface $output): void
    {
        $allUids = array_merge([$rootUid], $this->collectChildPageUids($rootUid));

        $cmdMap = [];
        foreach ($allUids as $uid) {
            $cmdMap['pages'][$uid] = ['delete' => 1];
        }

        $dataHandler = $this->makeDataHandler();
        $dataHandler->start([], $cmdMap);
        $dataHandler->process_cmdmap();

        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException('DataHandler delete errors: ' . implode('; ', $dataHandler->errorLog));
        }

        GeneralUtility::makeInstance(Registry::class)->remove($this->registryNamespace(), 'test_data_' . $parentPid);
        $output->writeln('Deleted existing test pages (root uid=' . $rootUid . ')');
    }

    /**
     * @return int[]
     */
    private function collectChildPageUids(int $parentUid): array
    {
        $rows = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->select(['uid'], 'pages', ['pid' => $parentUid, 'deleted' => 0])
            ->fetchAllAssociative();

        $uids = [];
        foreach (array_column($rows, 'uid') as $uid) {
            $uids[] = (int)$uid;
            foreach ($this->collectChildPageUids((int)$uid) as $childUid) {
                $uids[] = $childUid;
            }
        }

        return $uids;
    }

    private function createPage(
        int $pid,
        string $title,
        int $hidden = 0,
        int $navHide = 0,
        int $doktype = 1
    ): int {
        $newId = 'NEW_' . uniqid('', true);

        $dataHandler = $this->makeDataHandler();
        $dataHandler->start([
            'pages' => [
                $newId => [
                    'pid'      => $pid,
                    'title'    => $title,
                    'hidden'   => $hidden,
                    'nav_hide' => $navHide,
                    'doktype'  => $doktype,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException('DataHandler errors: ' . implode('; ', $dataHandler->errorLog));
        }

        return (int)($dataHandler->substNEWwithIDs[$newId] ?? 0);
    }

    /**
     * @return array<string, int> Keys: 'cms', 'development'
     */
    private function createCategories(int $folderUid): array
    {
        $newCms = 'NEW_' . uniqid('', true);
        $newDev = 'NEW_' . uniqid('', true);

        $dataHandler = $this->makeDataHandler();
        $dataHandler->start([
            'sys_category' => [
                $newCms => ['pid' => $folderUid, 'title' => 'CMS'],
                $newDev => ['pid' => $folderUid, 'title' => 'Development'],
            ],
        ], []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException('DataHandler errors: ' . implode('; ', $dataHandler->errorLog));
        }

        return [
            'cms'         => (int)($dataHandler->substNEWwithIDs[$newCms] ?? 0),
            'development' => (int)($dataHandler->substNEWwithIDs[$newDev] ?? 0),
        ];
    }

    /**
     * @param array<string, int> $categories
     */
    private function createFaqItems(int $folderUid, array $categories): void
    {
        $items = [
            ['question' => 'What is TYPO3?', 'answer' => 'TYPO3 is an open-source content management system.', 'category' => $categories['cms']],
            ['question' => 'What is Composer?', 'answer' => 'Composer is a dependency manager for PHP.', 'category' => $categories['development']],
            ['question' => 'What is a Content Block?', 'answer' => 'A Content Block is a structured content type definition.', 'category' => $categories['cms']],
            ['question' => 'What is PHP?', 'answer' => 'PHP is a server-side scripting language.', 'category' => $categories['development']],
            ['question' => 'What is this uncategorized item?', 'answer' => 'This item has no category assigned.', 'category' => 0],
        ];

        $dataMap = ['tx_gedankenfolger_faq_item' => []];
        foreach ($items as $item) {
            $newId = 'NEW_' . uniqid('', true);
            $record = [
                'pid'      => $folderUid,
                'question' => $item['question'],
                'answer'   => $item['answer'],
            ];
            if ($item['category'] > 0) {
                $record['categories'] = $item['category'];
            }
            $dataMap['tx_gedankenfolger_faq_item'][$newId] = $record;
        }

        $dataHandler = $this->makeDataHandler();
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException('DataHandler errors: ' . implode('; ', $dataHandler->errorLog));
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createContentElement(int $pid, int $folderUid, string $variantLabel, array $options): void
    {
        $newId = 'NEW_' . uniqid('', true);
        $record = array_merge([
            'pid'                              => $pid,
            'CType'                            => 'gedankenfolger_faq',
            'header'                           => 'FAQ Variant: ' . $variantLabel,
            'gedankenfolger_faq_storageFolder' => (string)$folderUid,
        ], $options);

        $dataHandler = $this->makeDataHandler();
        $dataHandler->start(['tt_content' => [$newId => $record]], []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException('DataHandler errors: ' . implode('; ', $dataHandler->errorLog));
        }
    }

    private function makeDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }
}
