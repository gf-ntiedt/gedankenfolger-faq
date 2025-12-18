<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Gedankenfolger FAQ',
    'description' => 'FAQ extension using Content Blocks, Site Set, Schema, JS and SCSS for TYPO3 13.',
    'category' => 'fe',
    'author' => 'Gedankenfolger',
    'author_email' => 'dev@example.com',
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'version' => '13.5.2',
    'autoload' => [
        'psr-4' => [
            'Gedankenfolger\\GedankenfolgerFaq\\' => 'Classes',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
            'fluid' => '13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'ws_scss' => '',
            'schema' => ''
        ],
    ],
];
