<?php

namespace daemon;


spl_autoload_register(function ($class) {
	if (strpos($class, 'daemon') !== 0) {
		return;
	}

	$file = realpath(__DIR__ . '/../') . '/' . str_replace('\\', '/', $class) . '.php';

	if (file_exists($file)) {
		require_once $file;
	}
});