<?php

namespace daemon\process;

class ChildProcess extends AbstractProcess
{
	public function __construct ($pid = null, $parent = null)
	{
		$this->isChild(true);

		if ($parent) {
			$this->setParent($parent);
		}

		parent::__construct($pid);
	}
}