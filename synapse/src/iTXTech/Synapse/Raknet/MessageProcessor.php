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
use iTXTech\Synapse\Util\Binary;

class MessageProcessor{

	/** @var Raknet */
	private $raknet;

	/** @var MessageHandler */
	protected $instance;

	public function __construct(Raknet $raknet, MessageHandler $instance){
		$this->raknet = $raknet;
		$this->instance = $instance;
	}

	public function sendEncapsulated(string $identifier, EncapsulatedPacket $packet, int $flags = Properties::PRIORITY_NORMAL) : void{
		$buffer = chr(Properties::PACKET_ENCAPSULATED) . chr(strlen($identifier)) . $identifier . chr($flags) . $packet->toInternalBinary();
		$this->raknet->getKChan()->push($buffer);
	}

	public function sendRaw(string $address, int $port, string $payload) : void{
		$buffer = chr(Properties::PACKET_RAW) . chr(strlen($address)) . $address . Binary::writeShort($port) . $payload;
		$this->raknet->getKChan()->push($buffer);
	}

	public function closeSession(string $identifier, string $reason) : void{
		$buffer = chr(Properties::PACKET_CLOSE_SESSION) . chr(strlen($identifier)) . $identifier . chr(strlen($reason)) . $reason;
		$this->raknet->getKChan()->push($buffer);
	}

	/**
	 * @param string $name
	 * @param mixed $value Must be castable to string
	 */
	public function sendOption(string $name, $value) : void{
		$buffer = chr(Properties::PACKET_SET_OPTION) . chr(strlen($name)) . $name . $value;
		$this->raknet->getKChan()->push($buffer);
	}

	public function blockAddress(string $address, int $timeout) : void{
		$buffer = chr(Properties::PACKET_BLOCK_ADDRESS) . chr(strlen($address)) . $address . Binary::writeInt($timeout);
		$this->raknet->getKChan()->push($buffer);
	}

	public function unblockAddress(string $address) : void{
		$buffer = chr(Properties::PACKET_UNBLOCK_ADDRESS) . chr(strlen($address)) . $address;
		$this->raknet->getKChan()->push($buffer);
	}

	public function shutdown() : void{
		$buffer = chr(Properties::PACKET_SHUTDOWN);
		$this->raknet->getKChan()->push($buffer);
		$this->raknet->shutdown();
	}

	/**
	 * @return bool
	 */
	public function handlePacket() : bool{
		if(($packet = $this->raknet->getRChan()->pop()) !== false){
			$id = ord($packet{0});
			$offset = 1;
			if($id === Properties::PACKET_ENCAPSULATED){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$flags = ord($packet{$offset++});
				$buffer = substr($packet, $offset);
				$this->instance->handleEncapsulated($identifier, EncapsulatedPacket::fromInternalBinary($buffer), $flags);
			}elseif($id === Properties::PACKET_RAW){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$payload = substr($packet, $offset);
				$this->instance->handleRaw($address, $port, $payload);
			}elseif($id === Properties::PACKET_SET_OPTION){
				$len = ord($packet{$offset++});
				$name = substr($packet, $offset, $len);
				$offset += $len;
				$value = substr($packet, $offset);
				$this->instance->handleOption($name, $value);
			}elseif($id === Properties::PACKET_OPEN_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$clientID = Binary::readLong(substr($packet, $offset, 8));
				$this->instance->openSession($identifier, $address, $port, $clientID);
			}elseif($id === Properties::PACKET_CLOSE_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$len = ord($packet{$offset++});
				$reason = substr($packet, $offset, $len);
				$this->instance->closeSession($identifier, $reason);
			}elseif($id === Properties::PACKET_INVALID_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$this->instance->closeSession($identifier, "Invalid session");
			}elseif($id === Properties::PACKET_ACK_NOTIFICATION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$identifierACK = Binary::readInt(substr($packet, $offset, 4));
				$this->instance->notifyACK($identifier, $identifierACK);
			}elseif($id === Properties::PACKET_REPORT_PING){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$pingMS = Binary::readInt(substr($packet, $offset, 4));
				$this->instance->updatePing($identifier, $pingMS);
			}

			return true;
		}

		return false;
	}
}
