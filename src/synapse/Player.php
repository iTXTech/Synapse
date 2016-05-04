<?php

/*
 *
 *  _____   _____   __   _   _   _____  __    __  _____
 * /  ___| | ____| |  \ | | | | /  ___/ \ \  / / /  ___/
 * | |     | |__   |   \| | | | | |___   \ \/ /  | |___
 * | |  _  |  __|  | |\   | | | \___  \   \  /   \___  \
 * | |_| | | |___  | | \  | | |  ___| |   / /     ___| |
 * \_____/ |_____| |_|  \_| |_| /_____/  /_/     /_____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */
 
namespace synapse;

use synapse\network\protocol\mcpe\DataPacket;
use synapse\network\protocol\mcpe\Info;
use synapse\utils\UUID;

class Player{
	/** @var DataPacket */
	private $cachedLoginPacket;
	private $name;
	private $address;
	private $port;
	private $currentServerIp;
	private $currentServerPort;
	/** @var UUID */
	private $uuid;

	public function __construct(){
	}
	
	public function handleDataPacket(DataPacket $pk){
		switch($pk::NETWORK_ID){
			case Info::LOGIN_PACKET:
				$this->cachedLoginPacket = $pk;
				break;
		}
	}

	public function getUUID(){
		return $this->uuid;
	}

	public function getName() : string{

	}
	
	public function transfer(Client $client){

	}

	public function sendPacket(DataPacket $pk, $direct = false){
		
	}
}