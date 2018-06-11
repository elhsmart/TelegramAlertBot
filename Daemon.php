<?php


define("APP_ROOT", dirname(__FILE__));

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Addons.php';

include __DIR__ . '/src/Eugenia/Config.php';

declare(ticks = 1); // needed for signal handling

Eugenia\Bot::getInstance()
  ->setDaemonize(false) 
  ->setVerbose(true)
  ->setDebug(true)
  ->setDebugLevel(3)
  ->setLogFile('/tmp/quick_start_daemon.log')
  ->setLoopInterval(1)  
  ->run();