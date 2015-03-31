Caterpillar
===========

Caterpillar is a queuing mechanism based on beanstalkd. It handles enqueuing and processing tasks, and managing multiple concurrent workers that can process the same queue.

You can use Caterpillar to quickly parallelize background tasks simply by running many workers.


Installation
------------

Download from source or use Composer.

`git clone git@github.com:aaronpk/caterpillar.git`

Add this to your composer.json file in the "require" section: 

`"p3k/caterpillar": "0.1.*"`


Usage
-----

### Queuing Tasks

To queue a task, first create a new `Caterpillar` object and pass in the beanstalkd tube name you want to use, as well as the beanstalkd server and a path to write log files.

```php
$c = new Caterpillar('caterpillar-test', '127.0.0.1', 11300, $logdir);
```

You can queue any static method of any class to run. For illustration purposes, we'll use this test task below. All it does is says it's running, waits a random amount of time, and then finishes.

```php
class TestTask {
  public static function run($val) {
    echo "Running task $val ...\n";
    usleep(rand(750000,2000000));
    echo "finished!\n";
  }
}
```

Now we'll queue up 10 tasks and each one is identified by a random number just so they show up better in the logs.

```
for($i=0; $i<10; $i++) {
  $c->queue('TestTask', 'run', [rand(1000,9999)]);
}
```

### Running Workers

To run the workers, you'll first need to make a Caterpillar object the same way you did to queue jobs. Then just run the `run_workers` method with the number of concurrent processes you want to run as the first argument. The script will fork this many children and they will all run in parallel, managed by the parent process.

```php
require_once(__DIR__.'/vendor/autoload.php');

// Make sure you load your environment and any of the classes that are being used as workers

class TestTask {
  public static function run($val) {
    echo "Running task $val ... ";
    usleep(rand(750000,2000000));
    echo "finished!\n";
  }
}

$logdir = __DIR__.'/log/';

$c = new Caterpillar('caterpillar-test', '127.0.0.1', 11300, $logdir);
$c->run_workers(2); // Runs two workers in the foreground
```

When you run this from the console, the parent process stays in the foreground. You can quit all the workers by pressing CTRL+C and waiting a couple seconds for them to finish.

If you want to run the parent process in the background, such as when using some system init methods, you can pass `true` as the second argument, e.g. `$c->run_workers(2, true)`.


API Documentation
-----------------

### `new Caterpillar($tube, $server, $port, $log_path)`

Creates the Caterpillar object and configures it to use a specific tube, beanstalkd server and log path.

* `$tube`: (default is the "default" tube) The name of the beanstalkd tube to use
* `$server`: (default 127.0.0.1) The IP address or hostname of the beanstalkd server
* `$port`: (default 11300) The port of the beanstalkd server
* `$log_path`: (default "./log/") The path to use for all log files

### `queue($class, $method, $args, $opts)`

Enqueues a new job onto the tube.

* `$class`: (string) The class name of the task to run
* `$method`: (string) The name of the static method in the class to run
* `$args`: (array) This array is passed in as arguments to the static function. If you include 3 items in the array, your function must take 3 arguments.
* `$opts`: (array)
** `delay`: (default 0) The number of seconds to wait before the job will be available to workers
** `ttr`: (default 300) The "time to run" of the job in seconds. If the job is not completed before the time is up, it will be put back onto the queue by beanstalkd.
** `priority`: (default 1024) The beanstalkd priority number to set for the job.

### `run_workers($num, $background)`

Runs the number of workers specified as separate child processes.

* `$num`: (integer) The number of children to run
* `$background`: (true, false) Whether to run the parent process in the background or foreground


### `print_stats()`

Outputs info about the number of jobs ready, delayed, buried, and the number of processes watching beanstalkd tubes.

Sample output:

```
              tube  urgent  ready delayed buried  using watching
           default  0       0     0       0       1     1
  caterpillar-test  0       2     0       0       0     0
```

See the beanstalkd documentation for more details on what each of these mean.


TODO
----

* Fix log rotation. Figure out why the children are unable to catch the HUP signal the parent sends.



License
-------

Copyright 2015 by Aaron Parecki

Available under the Apache 2.0 License. See LICENSE.txt

