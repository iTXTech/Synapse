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

namespace iTXTech\Synapse\Kyrios;

use iTXTech\Synapse\Raknet\MessageHandler;
use iTXTech\Synapse\Raknet\MessageProcessor;
use iTXTech\Synapse\Raknet\Protocol\EncapsulatedPacket;
use iTXTech\Synapse\Raknet\Raknet;

class RaknetMessageHandler implements MessageHandler{
	private $processor;

	public function __construct(Raknet $raknet){
		$this->processor = new MessageProcessor($raknet, $this);
	}

	public function tick(){

	}

	public function connect($fd){

	}

	public function task($data){

	}

	public function openSession(string $identifier, string $address, int $port, int $clientID) : void{
		// TODO: Implement openSession() method.
	}

	public function updatePing(string $identifier, int $pingMS) : void{
		// TODO: Implement updatePing() method.
	}

	public function handleRaw(string $address, int $port, string $payload) : void{
		// TODO: Implement handleRaw() method.
	}

	public function handleEncapsulated(string $identifier, EncapsulatedPacket $packet, int $flags) : void{
		// TODO: Implement handleEncapsulated() method.
	}

	public function handleOption(string $option, string $value) : void{
		// TODO: Implement handleOption() method.
	}

	public function closeSession(string $identifier, string $reason) : void{
		// TODO: Implement closeSession() method.
	}

	public function notifyACK(string $identifier, int $identifierACK) : void{
		// TODO: Implement notifyACK() method.
	}
}