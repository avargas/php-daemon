<?php

namespace daemon;

use daemon\process\AbstractProcess;

abstract class AbstractTask
{
	protected $process;
	protected $log;
	protected $hangup;

	public function __construct (AbstractProcess $process)
	{
		if ($process) {
			$this->setProcess($process);
		}

		$this->log = Loggy::get(get_called_class() . '(pid: ' . $process->getPid() . ')');
	}

	public function setProcess (AbstractProcess $process)
	{
		$this->process = $process;
	}

	public function getProcess ()
	{
		return $this->process;
	}

	public abstract function execute ()
}