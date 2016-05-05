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

class Client{
	/** @var Server */
	private $server;
	private $ip;
	private $port;
	/** @var Player[] */
	private $players = [];
	private $isVerified = false;
	
	public function __construct(Server $server, $ip, int $port){
		$this->server = $server;
		$this->ip = $ip;
		$this->port = $port;
	}

	public function getIp(){
		return $this->ip;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function isVerified() : bool {
		return $this->isVerified;
	}

	public function setVerified(){
		$this->isVerified = true;
	}

	public function addPlayer(Player $player){
		$this->players[$player->getUUID()->toBinary()] = $player;
	}

	public function removePlayer(Player $player){
		unset($this->players[$player->getUUID()->toBinary()]);
	}

	public function close(){
		foreach($this->players as $player){
			$player->close("Server Closed");
		}
	}
}