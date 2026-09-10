<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Gedankenfolger FAQ',
    'description' => 'FAQ extension using Content Blocks, Site Set, Schema, JS and SCSS for TYPO3 14.',
    'category' => 'fe',
    'author' => 'Niels Tiedt, Gedankenfolger GmbH',
    'author_email' => 'niels.tiedt@gedankenfolger.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '14.4.1',
    'autoload' => [
        'psr-4' => [
            'Gedankenfolger\\GedankenfolgerFaq\\' => 'Classes',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'content_blocks' => '2.0.0-2.99.99',
            'ws_scss' => '14.0.0-14.99.99',
            'schema' => '4.0.0-4.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
