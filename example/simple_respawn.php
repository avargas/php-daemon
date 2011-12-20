<?php

require_once __DIR__ . '/bootstrap.php';


$callback = function ($process) {
	$log = $process->log;

	$rand = 10;//mt_rand(1, 10);

	while (true) {
		if (($count = $process->getEndCount())) {
			$log->info('We were told to end %d times, so loop is ending now', $count);
			exit(0);
		}

		$log->info('Sleeping for %d - %d', $rand, $count = $process->getEndCount());
		
		usleep(50000);		
	}
};

$obj = new daemon\DaemonRunner(10, $callback);
$obj->loop();