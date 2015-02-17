<?php

use Monolog\Logger;

return [
    'session.storage.save_path' => realpath(__DIR__ . '/../../data/sessions'),
    'session.storage.options' => [
        'name' => 'filelibio',
    ],

    'db.options' => [
        'driver'   => 'pdo_pgsql',
        'dbname'   => '',
        'user'     => '',
        'password' => '',
        'host'     => 'localhost',
    ],
    'filelib.options' => [
        'enableAcceleration' => false,
        'queue' => [
            'token' => '',
            'projectId' => '',
            'queueName' => 'dev_gaylord', // Your own nick here, not Pekkis. One developer. One queue.
        ]
    ],
    'logLevel' => Logger::DEBUG,
];
