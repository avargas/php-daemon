<?php

namespace daemon\process;

use daemon\DaemonException;

use loggy\Logger;

const MAX_SIGHUP = 2;

abstract class AbstractProcess
{
	protected $isParent = false;
	protected $isChild = false;
	protected $childs = array();
	protected $pid = 0;
	protected $parent;
	protected $signalCallbacks = array();
	protected $signalCount = array();

	public $log;

	public function __construct ($pid = null)
	{
		$this->setPID($pid ? : getmypid());
		$this->initializeLogging();
	}

	public function isParent ($is = null)
	{
		if ($is !== null) {
			$this->isParent = $is;
		}

		return $this->isParent;
	}

	public function isChild ($is = null)
	{
		if ($is !== null) {
			$this->isChild = $is;
		}

		return $this->isChild;
	}

	public function setPid ($pid)
	{
		$this->pid = $pid;

		return $this;
	}

	public function getPid ()
	{
		return $this->pid;
	}

	public function setParent (AbstractProcess $parent)
	{
		$this->parent = $parent;

		return $this;
	}

	public function getParent ()
	{
		return $this->parent;
	}

	public function getParentPid ()
	{
		return $this->parent ? $this->parent->getPid() : null;
	}

	public function fork ($callback)
	{
		$pid = pcntl_fork();

		# fork failed
		if ($pid === -1) {
			throw new DaemonException('pcntl_fork failed');
		}

		$child = new ChildProcess($pid, $this);

		# parent process
		if ($pid) {
			# kept for meta info only
			$this->addChild($child);
			
			return $child;
		}

		# we're the fork
		$child->registerDefaultSignals();
		$callback($child);
		exit(0);
	}

	public function createChild ($pid)
	{
		$child = new ChildProcess($pid, $this);
		return $this->addChild($child);
	}

	public function addChild (AbstractProcess $child)
	{
		$this->childs[$child->getPid()] = $child;
	}

	public function removeChild ($child)
	{
		$pid = $child instanceof self ? $child->getPid() : $child;
		$this->log->debug('removing child ' . $pid);

		unset($this->childs[$pid]);
	}

	public function getChilds ()
	{
		return $this->childs;
	}

	public function getChild ($pid)
	{
		return isset($this->childs[$pid]) ? $this->childs[$pid] : 0;
	}

	public function initializeLogging ()
	{

		if ($this->isChild()) {
			$facility = sprintf('%s (pid:%d, ppid:%d)', get_called_class(), $this->getPid(), $this->getParentPid());
		} else {
			$facility = sprintf('%s (pid:%d)', get_called_class(), $this->getPid());
		}

		$this->log = Logger::get($facility);
		$this->log->debug('logging initialized');
	}

	public function getLog ()
	{
		return $this->log;
	}

	public function getRestartCount ()
	{
		return $this->getSignalCount(SIGHUP);
	}

	public function getInterruptCount ()
	{
		return $this->getSignalCount(SIGINT);
	}

	public function getTerminateCount ()
	{
		return $this->getSignalCount(SIGTERM);
	}

	public function getEndCount ()
	{
		return $this->getInterruptCount() + $this->getTerminateCount();
	}

	public function getSignalCount ($signal)
	{
		return isset($this->signalCount[$signal]) ? $this->signalCount[$signal] : 0;
	}

	public function on ($signal, $callback = null)
	{
		$this->log->debug('registering pcntl signal %d', $signal);

		if (!isset($this->signalCallbacks[$signal])) {
			$this->signalCallbacks[$signal] = array();
			pcntl_signal($signal, array($this, 'handleSignal'));
		}

		if ($callback !== null) {
			$this->signalCallbacks[$signal][] = $callback;
		}

		return $this;
	}

	public function kill ()
	{
		$this->sendSignal(SIGKILL);
	}

	public function sendSignal ($signal)
	{
		$this->log->debug('Sending %d to %d', $signal, $this->getPid());
		posix_kill($this->getPid(), $signal);
	}

	public function handleSignal ($signal, $pid = null, $status = null)
	{
		# keep a log of all the received signals
		if (!isset($this->signalCount[$signal])) {
			$this->signalCount[$signal] = 1;
		} else {
			$this->signalCount[$signal]++;
		}

		$this->log->debug('handling signal: %d, count: %d', $signal, $this->signalCount[$signal]);

		foreach ($this->signalCallbacks[$signal] as $callback) {
			call_user_func_array($callback, array($this->signalCount[$signal], $this, $pid, $status));
		}
	}

	public function registerDefaultSignals ()
	{
		$this->on(SIGHUP); # when sending the reload command
		$this->on(SIGINT); # when sending CTRL + C
		$this->on(SIGTERM); # when sending `kill`
		$this->on(SIGCHLD); # when a child process dies
	}
}