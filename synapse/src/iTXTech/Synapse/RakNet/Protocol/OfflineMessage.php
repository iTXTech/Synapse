<?php

/*
 * RakLib network library
 *
 *
 * This project is not affiliated with Jenkins Software LLC nor RakNet.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace iTXTech\Synapse\Raknet\Protocol;

use iTXTech\Synapse\Raknet\Properties;

abstract class OfflineMessage extends Packet{
	/** @var string */
	protected $magic;

	protected function readMagic(){
		$this->magic = $this->get(16);
	}

	protected function writeMagic(){
		$this->put(Properties::MAGIC);
	}

	public function isValid() : bool{
		return $this->magic === Properties::MAGIC;
	}

}
