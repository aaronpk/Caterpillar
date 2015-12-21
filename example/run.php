<?php
require_once(__DIR__.'/../vendor/autoload.php');

class TestTask {
  public static function run($val) {
    echo "Running task $val ... ";
    usleep(rand(750000,2000000));
    echo "finished!\n";
  }
}

$logdir = __DIR__.'/log/';

$c = new Caterpillar('caterpillar-test', '127.0.0.1', 11300, $logdir);
$c->run_workers(2);
