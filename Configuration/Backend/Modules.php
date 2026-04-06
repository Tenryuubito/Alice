<?php

return [
    'alice' => [
        'labels' => [
            'title' => 'LLL:EXT:alice/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab',
        ],
        'iconIdentifier' => 'module-reports',
    ],
    'alice_performance' => [
        'parent' => 'alice',
        'extensionName' => 'Alice',
        'path' => '/module/alice/performance',
        'labels' => [
            'title' => 'LLL:EXT:alice/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tablabel',
            'description' => 'LLL:EXT:alice/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tabdescr',
        ],
        'iconIdentifier' => 'module-reports',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'controllerActions' => [
            \Tenryuubito\Alice\Controller\BackendController::class => [
                'index',
                'analyze',
                'saveSettings',
            ],
        ],
    ],
];
