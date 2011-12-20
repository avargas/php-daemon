<?php

namespace daemon\process;

class ParentProcess extends AbstractProcess
{
	public function __construct ($pid = null)
	{
		$this->isParent(true);

		parent::__construct($pid);
	}
}