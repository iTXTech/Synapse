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
use iTXTech\Synapse\RakNet\RakNet;

class Synapse{
	/** @var RakNet */
	private $raknet;
	/** @var Kyrios */
	private $kyrios;

	public function __construct(Kyrios $kyrios, RakNet $raknet){
		$this->kyrios = $kyrios;
		$this->raknet = $raknet;
		return $this;
	}

	public function launch(){
		$this->raknet->launch();
	}

	public function shutdown(){
		$this->raknet->shutdown();
	}
}