<?php

/*
 *
 * iTXTech Synapse
 *
 * Copyright (C) 2018 iTX Technologies
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace iTXTech\Synapse;

use iTXTech\Synapse\Kyrios\Kyrios;
use iTXTech\Synapse\Raknet\Raknet;
use Swoole\Channel;

class Synapse{
	/** @var Raknet */
	private $raknet;
	/** @var Kyrios */
	private $kyrios;
	/** @var Channel */
	private $rChan;
	/** @var Channel */
	private $kChan;

	public function __construct(Kyrios $kyrios, Raknet $raknet){
		$this->kyrios = $kyrios;
		$this->raknet = $raknet;

		$this->rChan = new Channel(1024 * 1024 * 1024);//1GB
		$this->kChan = new Channel(1024 * 1024 * 1024);

		$this->raknet->channel($this->rChan, $this->kChan);
		$this->kyrios->channel($this->rChan, $this->kChan);

		return $this;
	}

	public function launch(){
		$this->raknet->launch();
	}

	public function shutdown(){
		$this->raknet->shutdown();
	}

	public function getRaknet() : Raknet{
		return $this->raknet;
	}

	public function getKyrios() : Kyrios{
		return $this->kyrios;
	}
}