<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerFaq\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class AfterBackendPageRenderEventListener
{
    public function __construct(
        private PageRenderer $pageRenderer,
        private ViewFactoryInterface $viewFactory,
    ) {}

    #[AsEventListener(event: AfterBackendPageRenderEvent::class)]
    public function __invoke(AfterBackendPageRenderEvent $event): void
    {
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:gedankenfolger_faq/Resources/Private/Language/locallang_tutorial_ui.xlf'
        );
        $this->pageRenderer->addCssFile('EXT:gedankenfolger_faq/Resources/Public/Css/BackendTutorial.css');
        $this->pageRenderer->addJsFile(
            'EXT:gedankenfolger_faq/Resources/Public/JavaScript/BackendTutorial.js',
            'text/javascript',
            false,
            false,
            '',
            false,
            '|',
            false,
            '',
            true
        );

        try {
            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
            $view = $this->viewFactory->create(new ViewFactoryData(
                partialRootPaths: [
                    GeneralUtility::getFileAbsFileName('EXT:gedankenfolger_faq/Resources/Private/Partials'),
                ],
                templatePathAndFilename: GeneralUtility::getFileAbsFileName(
                    'EXT:gedankenfolger_faq/Resources/Private/Templates/Backend/TourPanel.html'
                ),
                request: $request,
            ));
            $templateHtml = $view->render();
            if ($templateHtml) {
                $event->setContent($event->getContent() . $templateHtml);
            }
        } catch (\Throwable $e) {
            error_log('[GF_FAQ_TOUR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
