<?php
require_once(__DIR__.'/../vendor/autoload.php');

$c = new Caterpillar();
$c->print_stats();

/*
 This will output information about the beanstalk queues, for example:

              tube  urgent  ready delayed buried  using watching
           default  0       0     0       0       1     1
  caterpillar-test  0       2     0       0       0     0
*/
