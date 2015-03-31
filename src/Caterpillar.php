<?php

class Caterpillar {

  private $_tube;
  private $_bs;
  private $_server;
  private $_port;
  private $_log_path;

  // workers
  private $_pids;
  private $_child_id;
  public static $PCNTL_CONTINUE;

  private static $FILE = 1;
  private static $ERR = 2;

  public function __construct($tube=false, $server='127.0.0.1', $port=11300, $log_path=false) {
    if($tube == false)
      $tube = 'default';

    if($log_path == false)
      $log_path = './log/';

    $this->_bs = new Pheanstalk\Pheanstalk($server, $port);
    $this->_tube = $tube;
    $this->_server = $server;
    $this->_port = $port;
    $this->_log_path = $log_path;
  }

  public function queue($class, $method, $args=[], $opts=[]) {
    $defaults = [
      'priority' => 1024,
      'delay' => 0,
      'ttr' => 300
    ];
    $opts = array_merge($defaults, $opts);

    if(!is_array($args))
      $args = [$args];

    $this->_bs->putInTube($this->_tube,
      json_encode(array('class'=>$class, 'method'=>$method, 'args'=>$args)),
      $opts['priority'],
      $opts['delay'],
      $opts['ttr']);
  }

  private function _process(&$job) {
    $data = json_decode($job->getData());

    if(!is_object($data) || !property_exists($data, 'class')) {
      echo "Found bad job:\n";
      print_r($data);
      echo "\n";
      $this->_bs->delete($job);
      return;
    }

    echo "===============================================\n";
    echo "# Beginning job: " . $data->class . '::' . $data->method . "\n";

    call_user_func_array([$data->class, $data->method], $data->args);
    
    echo "\n# Job Complete\n-----------------------------------------------\n\n";
    $this->_bs->delete($job);
  }

  public function print_stats() {
    $allTubes = $this->_bs->listTubes();

    $fields = array(
      'current-jobs-urgent' => 'urgent',
      'current-jobs-ready' => 'ready',
      'current-jobs-delayed' => 'delayed',
      'current-jobs-buried' => 'buried',
      'current-using' => 'using',
      'current-watching' => 'watching'
    );

    echo sprintf('%30s', 'tube');
    foreach($fields as $k=>$v) {
      echo "\t" . $v;
    }
    echo "\n";
    foreach($allTubes as $tube) {
      echo sprintf('%30s', $tube) . "\t";
      $stats = $this->_bs->statsTube($tube);
      foreach($fields as $k=>$v) {
        echo $stats->{$k} . "\t";
      }
      echo "\n";
    }

    echo "\n";
  }

  private function _run_worker() {
    $this->_bounce_log();

    $this->_log("watching tube: " . $this->_tube);

    $this->_bs->watch($this->_tube)->ignore('default');

    while(self::$PCNTL_CONTINUE)
    {
      if(($job=$this->_bs->reserve(2)) == FALSE)
        continue;

      $this->_log("processing job");
      $this->_process($job);
    } // while true

    $this->_log("worker finished!");
  }

  public function run_workers($num, $background=false) {

    if($background) {
      $this->_daemonize();
    }

    $this->_write_pidfile();

    $this->_pids = array();
    for($child_id = 0; $child_id < $num; $child_id++) {
      // Fork now
      $pid = pcntl_fork();
      if($pid === 0) {
        // This is the child process
        self::$PCNTL_CONTINUE = TRUE;

        // Set up the child signal handler to catch SIGTERM and prevent executing the next while() loop
        // pcntl_signal(SIGTERM, function($sig){
        //   #$this->_log("Child caught SIGTERM");
        //   #self::$PCNTL_CONTINUE = FALSE;
        // });
        // pcntl_signal(SIGHUP, function($sig){
        //   echo "CHILD CAUGHT SIGHUP\n";
        //   $this->_bounce_log();
        // });

        $this->_log("Child process started");
        
        $c = new Caterpillar($this->_tube, $this->_server, $this->_port, $this->_log_path);
        $c->_child_id = $child_id;
        $c->_run_worker();
        
        $this->_log("Child finished gracefully");
        die();
      } else {
        // This is the parent process
        $this->_pids[] = $pid;
      }
    }

    ////////////////////////////////////////////////////////////////////////////////////////
    // Everything below this line is only run in the parent process

    $this->_child_id = FALSE;
    $this->_bounce_log();

    $this->_log("Parent started " . count($this->_pids) . " child processes", self::$FILE|self::$ERR);

    // pcntl_signal(SIGHUP, function($sig){
    //   $this->_bounce_log();
    //   $this->_log("Parent caught SIGHUP");
    //   foreach($this->_pids as $p) {
    //     posix_kill($p, SIGHUP);
    //   }
    //   $this->_log('Sent SIGHUP to ' . count($this->_pids) . " children");
    // });

    // Pass off USR1 signals to the children
    // pcntl_signal(SIGUSR1, function($sig){
    //   $this->_log("Parent caught SIGUSR1! Sending to children...");
    //   foreach($this->_pids as $p) {
    //     posix_kill($p, SIGUSR1);
    //   }
    //   $this->_log('Done sending USR1 to ' . count($this->_pids) . " children");
    // });

    pcntl_signal(SIGTERM, function($sig){
      $this->_log("Parent caught SIGTERM");
      foreach($this->_pids as $p) {
        posix_kill($p, SIGTERM);
      }
      $this->_log('Done killing ' . count($this->_pids) . " children");
      // After the children die, execution will finally reach "parent finished" below
    });

    // Trap CTRL+C signals in case the script was run from a terminal
    pcntl_signal(SIGINT, function($sig){
      $this->_log("CTRL+C! Killing children...", self::$FILE+self::$ERR);
      foreach($this->_pids as $p) {
        posix_kill($p, SIGTERM);
      }
      $this->_log('Done killing ' . count($this->_pids) . " children", self::$FILE+self::$ERR);
    });

    // Keep waiting for the children until they all exit
    foreach($this->_pids as $p) {
      while(0 == pcntl_waitpid($p, $status, WNOHANG)) {
        pcntl_signal_dispatch();
        sleep(1);
      }
    }

    $this->_log("Parent finished", self::$FILE+self::$ERR);
    $this->_remove_pidfile();
  }

  private function _daemonize() {
    // Fork the current process
    $pid = pcntl_fork();
   
    // Check to make sure it forked okay
    if($pid == -1) {
      echo "\n Error: The process failed to fork.\n";
    } else if($pid) {
      echo "Daemon started with pid: $pid\n";
      //This is the parent process.
      exit;
    } else {
      //We're now in the child process.
    }

    $this->_bounce_log();

    // Detach from the terminal window, so that we stay alive when it is closed
    if(posix_setsid() == -1) {
      echo "\n Error: Unable to detach from the terminal window.\n";
    }
  }

  private function _log($msg, $dst=1) {
    if($dst & self::$FILE) {
      echo '[' . posix_getpid() . "] $msg\n";
    }
    if($dst & self::$ERR) {
      fwrite(STDERR, '[' . posix_getpid() . "] $msg\n");
    }
  }

  private function _write_pidfile() {
    $pidFile = fopen($this->_log_path . $this->_tube . '.pid', 'w');
    fwrite($pidFile, posix_getpid());
    fclose($pidFile);
  }
  private function _remove_pidfile() {
    $pidFile = $this->_log_path . $this->_tube . '.pid';
    unlink($pidFile);
  }

  private function _bounce_log() {
    global $STDOUT;

    if(isset($STDOUT))
      fclose($STDOUT);
    else
      fclose(STDOUT);

    if(isset($this->_child_id) && $this->_child_id !== FALSE)
      $filename = $this->_log_path . $this->_tube . '-' . $this->_child_id . '.log';
    else
      $filename = $this->_log_path . $this->_tube . '.log';

    $STDOUT = fopen($filename, 'a');
  }

}
