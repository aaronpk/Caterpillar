<?php
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/lib.php');

$c = new Caterpillar('caterpillar-test');

// Queue three jobs that will run the TestTask::run function from the worker script
for($i=0; $i<2; $i++) {
  $c->queue('TestTask', 'run', [rand(1000,9999)]);
}
