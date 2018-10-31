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

namespace iTXTech\Synapse\Raknet;

use iTXTech\Synapse\Raknet\Protocol\EncapsulatedPacket;

interface MessageHandler{

	/**
	 * @param string $identifier
	 * @param string $address
	 * @param int    $port
	 * @param int    $clientID
	 */
	public function openSession(string $identifier, string $address, int $port, int $clientID) : void;

	/**
	 * @param string $identifier
	 * @param string $reason
	 */
	public function closeSession(string $identifier, string $reason) : void;

	/**
	 * @param string             $identifier
	 * @param EncapsulatedPacket $packet
	 * @param int                $flags
	 */
	public function handleEncapsulated(string $identifier, EncapsulatedPacket $packet, int $flags) : void;

	/**
	 * @param string $address
	 * @param int    $port
	 * @param string $payload
	 */
	public function handleRaw(string $address, int $port, string $payload) : void;

	/**
	 * @param string $identifier
	 * @param int    $identifierACK
	 */
	public function notifyACK(string $identifier, int $identifierACK) : void;

	/**
	 * @param string $option
	 * @param string $value
	 */
	public function handleOption(string $option, string $value) : void;

	/**
	 * @param string $identifier
	 * @param int    $pingMS
	 */
	public function updatePing(string $identifier, int $pingMS) : void;
}
