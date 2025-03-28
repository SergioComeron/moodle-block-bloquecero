<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/zoom_udima:addinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ],
    'block/zoom_udima:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks'
    ],
];
