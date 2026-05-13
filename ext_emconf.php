<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Gedankenfolger FAQ',
    'description' => 'FAQ extension using Content Blocks, Site Set, Schema, JS and SCSS for TYPO3 13.',
    'category' => 'fe',
    'author' => 'Niels Tiedt, Gedankenfolger GmbH',
    'author_email' => 'niels.tiedt@gedankenfolger.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '13.8.1',
    'autoload' => [
        'psr-4' => [
            'Gedankenfolger\\GedankenfolgerFaq\\' => 'Classes',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
            'content_blocks' => '1.3.0-1.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'ws_scss' => '',
            'schema' => ''
        ],
    ],
];
