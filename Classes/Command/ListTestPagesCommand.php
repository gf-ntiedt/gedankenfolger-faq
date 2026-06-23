<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Outputs FAQ test page UIDs from sys_registry as JSON for Playwright.
 *
 * @author  Niels Tiedt <niels.tiedt@gedankenfolger.de>
 * @company Gedankenfolger GmbH
 */
#[AsCommand(
    name: 'gedankenfolger-faq:test-pages',
    description: 'Outputs FAQ test page UIDs as JSON (run setup-test-pages first)',
)]
final class ListTestPagesCommand extends Command
{
    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int Command::SUCCESS or Command::FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
        $rootPageId = $sites !== [] ? array_values($sites)[0]->getRootPageId() : 1;

        $data = GeneralUtility::makeInstance(Registry::class)->get(
            SetupTestPagesCommand::REGISTRY_NAMESPACE,
            'test_data_' . $rootPageId
        );

        if ($data === null) {
            $output->writeln('{}');
            return Command::FAILURE;
        }

        $output->writeln((string)json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
