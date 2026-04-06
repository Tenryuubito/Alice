<?php

$EM_CONF['alice'] = [
    'title' => 'Alice Performance & Analytics',
    'description' => 'A powerful SITE performance auditor and asset bridge for TYPO3 v14.',
    'category' => 'module',
    'author' => 'Denis Root',
    'author_email' => 'denis.root.beruflich@gmail.com',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'license' => 'GPL-2.0-or-later',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
