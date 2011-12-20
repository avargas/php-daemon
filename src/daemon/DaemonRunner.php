<?php

# partially based off http://www.php.net/manual/en/function.pcntl-wait.php#98710

namespace daemon;

use daemon\process\ParentProcess;
use daemon\process\ChildProcess;

use loggy\Logger;

declare(ticks = 1);

class DaemonRunner
{
	protected static $SLEEP = 1000000;
	protected static $MAX_BAD_COUNT = 5;
	protected static $MAX_INTERRUPTS = 3;

	protected $parallel;
	protected $callback;
	protected $parent;

	protected $id = 0;
	protected $total;
	protected $active = array();
	protected $signalQueue = array();
	
	protected $exit = false;
	protected $badcount = 0;

	protected $isDaemon = false;

	public $log;

	public function __construct ($parallel = null, $callback = null, $daemon = false)
	{
		if ($parallel) {
			$this->setParallel($parallel);
		}

		if ($callback) {
			$this->setCallback($callback);
		}

		$parent = $this->initializeParentProcess();

		# initialize logging
		$this->log = Logger::get(get_called_class() . '(pid:' . $parent->getPid() . ')');
		$this->log->info('DaemonRunner instance created, getmypid: %d', $parent->getPid());

		if ($daemon) {
			$this->daemonize();
		}
	}
	
	public function initializeParentProcess ()
	{
		$parent = new ParentProcess();
		$parent->registerDefaultSignals();
		
		$parent->on(SIGCHLD, array($this, 'handleChildExit'));
		$parent->on(SIGINT, array($this, 'handleInterrupt'));
		$parent->on(SIGHUP, array($this, 'handleHangup'));		

		$this->setParent($parent);

		return $parent;
	}

	public function daemonize ()
	{
		$this->isDaemon = true;
		
		$this->log->debug('creating parent daemon');

		# daemonize by forking ourselves
		$pid = pcntl_fork();

		$this->log->debug('pcntl_fork returned %d', $pid);

		# failed to fork
		if ($pid < 0) {
			throw new DaemonException('Forking main processed to daemonize failed');
		}

		# parent
		if ($pid > 0) {
			exit(0);
		}

		# we're inside the daemon, so start it up!
		$this->log->info('Daemon Process created');
		$this->loop();
	}

	public function isDaemon ()
	{
		return $this->isDaemon;
	}

	public function setParallel ($parallel)
	{
		$this->parallel = $parallel;
		return $this;
	}

	public function getParallel ()
	{
		return $this->parallel;
	}

	public function setCallback ($callback)
	{
		$this->callback = $callback;
		return $this;
	}

	public function getCallback ()
	{
		return $this->callback;
	}

	public function setParent ($parent)
	{
		$this->parent = $parent;
		return $this;
	}

	public function getParent ()
	{
		return $this->parent;
	}

	public function loop ()
	{
		$parallel = $this->getParallel();
		$callback = $this->getCallback();
		$parent = $this->getParent();

		$this->log->info('initializing run, parallel: %d', $parallel);

		# main loop
		while (true) {
			try {
				$running = count($parent->getChilds());

				$this->log->debug('running: %d, parallel: %d', $running, $parallel);
				
				# if one of the forks dies, try to respawn
				# if it fails more than MAX_BAD_COUNT, then an exit strategy begins
				# wait til all the other forks have finished.
				if (!$this->exit && $this->badcount >= static::$MAX_BAD_COUNT) {
					$this->exit = true;
				}

				# if in exit mode, print a message
				if ($this->exit) {
					# no more forks running
					if ($running < 1) {
						break;
					}

					$this->log->info('exiting, waiting for %d forks to finish', $running);
				}

				# only fork when not in exit mode
				if ($running < $parallel && !$this->exit) {
					$child = $parent->fork($callback);

					# finished quicker than we thought
					if (isset($this->signalQueue[$child->getPid()])) {
						$parent->handleSignal(SIGCHLD, $pid, $this->signalQueue[$pid]);
						unset($this->signalQueue);
					}

					# reset bad counter since no exception was thrown
					$this->badcount = 0;
				}

				usleep(static::$SLEEP);
			} catch (DaemonException $e) {
				$this->logger->warn($e);
				$this->bacount++;
			} catch (\Exception $e) {
				$this->logger->error($e);
				$this->exit = true;
			}
		}
		
		$this->log->debug('Daemon is ending');
	}

	public function handleInterrupt ($count)
	{
		$this->exit = true;

		if ($count >= static::$MAX_INTERRUPTS) {
			$this->log->error('Interrupt sent too many times, forcing close');

			foreach ($this->getParent()->getChilds() as $child) {
				$child->kill();
			}

			exit(1);
		}
	}

	public function handleHangup ($signo)
	{
		$this->log->debug('receiving hangup');

		if (!$this->isDaemon()) {
			$this->log->error("cannot restart, not in daemon mode");
		}

		return true;
	}

	public function handleChildExit ($count, $process, $pid = null, $status = null)
	{
		$this->log->info('received signal SIGCHLD, count: %d, pid: %d, status: %s', $count, $pid, $status);

		# If no pid is provided, that means we're getting the signal from the system.  Let's figure out 
		# which child process ended 
		if (!$pid) {
			$this->log->debug('no pid, trying pcntl_waitpid');
			$pid = pcntl_waitpid(-1, $status, WNOHANG);
			$this->log->debug('pcntl_waitpid pid:%d, status:%d', $pid, $status);
		}

		$parent = $this->getParent();

		# make sure we get all of the exited children 
		while ($pid > 0) {
			if ($pid && $parent->getChild($pid)) {
				$exitCode = pcntl_wexitstatus($status);

				if ($exitCode != 0) {
					$this->log->warn("$pid exited with status " . $exitCode);
					$this->badcount++;
				}

				$this->getParent()->removeChild($pid);

				continue;
			}

			if ($pid) {
				# Oh no, our job has finished before this parent process could even note that it had been launched! 
				# Let's make note of it and handle it when the parent process is ready for it 
				$this->log->debug('adding %d to the signal queue', $pid);
				$this->signalQueue[$pid] = $status;
			}

			$pid = pcntl_waitpid(-1, $status, WNOHANG);
		}

		$this->log->debug('handleChildExit completed');

		return true;
	}

	public static function run ($parallel, $callback)
	{
		$inst = new static($parallel, $callback);
		return $inst->loop();
	}
}