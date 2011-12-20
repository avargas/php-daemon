<?php

error_reporting(-1);

require_once __DIR__ . '/../src/daemon/Autoloader.php';
require_once __DIR__ . '/../../php-loggy/src/loggy/Autoloader.php';
require_once __DIR__ . '/../../getopt-php/src/Getopt.php';

# initialize logging
$writer = new loggy\writer\CommandLineWriter;
$writer
	->setFormatter(new loggy\formatter\CommandLineFormatter)
	->registerErrorHandler()
	->registerExceptionHandler();
loggy\Logger::setWriter($writer);

# initialize getopt and check if --debug is passed for debug mode
$getopt = new Getopt(array(
	array(null, 'debug', Getopt::OPTIONAL_ARGUMENT)
));

$getopt->parse();

if (!$getopt->getOption('debug')) {
	$writer->setConfig('only', loggy\ALL ^ loggy\DEBUG);
}

$log = loggy\Logger::get('boostrap');