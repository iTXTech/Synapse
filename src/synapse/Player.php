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
use synapse\network\protocol\mcpe\DisconnectPacket;
use synapse\network\protocol\mcpe\Info;
use synapse\network\SourceInterface;
use synapse\utils\UUID;

class Player{
	/** @var DataPacket */
	private $cachedLoginPacket;
	private $name;
	private $ip;
	private $port;
	private $clientId;
	private $currentServerIp;
	private $currentServerPort;
	/** @var UUID */
	private $uuid;
	/** @var SourceInterface */
	private $interface;
	/** @var Client */
	private $client;

	public function __construct(SourceInterface $interface, $clientId, $ip, int $port){
		$this->interface = $interface;
		$this->clientId = $clientId;
		$this->ip = $ip;
		$this->port = $port;
	}
	
	public function handleDataPacket(DataPacket $pk){
		var_dump($pk);
		switch($pk::NETWORK_ID){
			case Info::LOGIN_PACKET:
				$this->cachedLoginPacket = $pk->buffer;
				$this->name = $pk->username;
				$this->uuid = $pk->clientUUID;
				break;
		}
	}

	public function getIp(){
		return $this->ip;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function getUUID(){
		return $this->uuid;
	}

	public function getName() : string{

	}
	
	public function transfer(Client $client){

	}

	public function sendPacket(DataPacket $pk, $direct = false, $needACK = false){
		$identifier = $this->interface->putPacket($this, $pk, $needACK, $direct);
	}

	public function close(string $reason = "Generic reason"){
		$pk = new DisconnectPacket();
		$pk->message = $reason;
		$this->sendPacket($pk, true);
	}
}