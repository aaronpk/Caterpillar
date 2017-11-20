<?php

/*
 * This is an example job class. The worker will run a static function on a class,
 * so this class defines one method "run" that will be called with a single argument.
 */

class TestTask {
  public static function run($val) {
    echo "Running task $val ... ";
    usleep(rand(750000,2000000));
    echo "finished!\n";
  }
}

