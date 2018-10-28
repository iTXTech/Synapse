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

namespace iTXTech\Synapse\RakNet\Protocol;

use iTXTech\RakNet\Properties;
use iTXTech\Synapse\Util\InternetAddress;

class NewIncomingConnection extends Packet{
	public static $ID = MessageIdentifiers::ID_NEW_INCOMING_CONNECTION;

	/** @var InternetAddress */
	public $address;

	/** @var InternetAddress[] */
	public $systemAddresses = [];

	/** @var int */
	public $sendPingTime;
	/** @var int */
	public $sendPongTime;

	protected function encodePayload() : void{
		$this->putAddress($this->address);
		foreach($this->systemAddresses as $address){
			$this->putAddress($address);
		}
		$this->putLong($this->sendPingTime);
		$this->putLong($this->sendPongTime);
	}

	protected function decodePayload() : void{
		$this->address = $this->getAddress();

		//TODO: HACK!
		$stopOffset = strlen($this->buffer) - 16; //buffer length - sizeof(sendPingTime) - sizeof(sendPongTime)
		$dummy = new InternetAddress("0.0.0.0", 0, 4);
		for($i = 0; $i < Properties::$SYSTEM_ADDRESS_COUNT; ++$i){
			if($this->offset >= $stopOffset){
				$this->systemAddresses[$i] = clone $dummy;
			}else{
				$this->systemAddresses[$i] = $this->getAddress();
			}
		}

		$this->sendPingTime = $this->getLong();
		$this->sendPongTime = $this->getLong();
	}
}
