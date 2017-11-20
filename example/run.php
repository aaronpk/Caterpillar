<?php
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/lib.php');

// Make sure the log folder exists and is writable by the user running this script
$logdir = __DIR__.'/log/';

$c = new Caterpillar('caterpillar-test', '127.0.0.1', 11300, $logdir);
$c->run_workers(2);
