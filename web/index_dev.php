<?php

require __DIR__. '/../app/app.php';

$kernel = new Kernel('dev', true);
$kernel->createApplication()->run();
