<?php
require_once(__DIR__.'/../vendor/autoload.php');

$c = new Caterpillar('caterpillar-test');

for($i=0; $i<2; $i++) {
  $c->queue('TestTask', 'run', [rand(1000,9999)]);
}
