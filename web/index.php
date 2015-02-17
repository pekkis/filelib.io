<?php

require __DIR__. '/../app/app.php';

$kernel = new Kernel('prod');
$kernel->createApplication()->run();
