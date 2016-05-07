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

use synapse\network\protocol\mcpe\DisconnectPacket;
use synapse\network\protocol\mcpe\GenericPacket;
use synapse\network\protocol\spp\ConnectPacket;
use synapse\network\protocol\spp\DataPacket;
use synapse\network\protocol\spp\Info;
use synapse\network\protocol\spp\InformationPacket;
use synapse\network\protocol\spp\RedirectPacket;
use synapse\network\SynapseInterface;

class Client{
	/** @var Server */
	private $server;
	/** @var SynapseInterface */
	private $interface;
	private $ip;
	private $port;
	/** @var Player[] */
	private $players = [];
	private $isVerified = false;
	private $isMainServer = false;
	private $maxPlayers;
	private $lastUpdate;

	public function __construct(SynapseInterface $interface, $ip, int $port){
		$this->server = $interface->getServer();
		$this->interface = $interface;
		$this->ip = $ip;
		$this->port = $port;
		$this->lastUpdate = time();
	}

	public function setIpAndPort(string $ip, int $port){
		$this->ip = $ip;
		$this->port = $port;
	}

	public function isMainServer() : bool{
		return $this->isMainServer;
	}

	public function getMaxPlayers() : int{
		return $this->maxPlayers;
	}

	public function getId() : string{
		return str_replace(".", "", $this->getIp()) . $this->getPort();
	}

	public function handleDataPacket(DataPacket $packet){
		switch($packet::NETWORK_ID){
			case Info::HEARTBEAT_PACKET:
				if(!$this->isVerified()){
					$this->server->getLogger()->error("Client {$this->getIp()}:{$this->getPort()} is not verified");
					return;
				}
				$this->lastUpdate = time();
				$this->server->getLogger()->notice("Received Heartbeat Packet from {$this->getIp()}:{$this->getPort()}");
				break;
			case Info::CONNECT_PACKET:
				/** @var ConnectPacket $packet */
				if($packet->protocol != Info::CURRENT_PROTOCOL){
					$this->interface->removeClient($this);
				}
				$pk = new InformationPacket();
				if($this->server->comparePassword(base64_decode($packet->encodedPassword))){
					$this->setVerified();
					$pk->message = InformationPacket::INFO_LOGIN_SUCCESS;
					$this->isMainServer = $packet->isMainServer;
					$this->maxPlayers = $packet->maxPlayers;
					$this->server->addClient($this);
					$this->server->getLogger()->notice("Client {$this->getIp()}:{$this->getPort()} has connected successfully");
				}else{
					$pk->message = InformationPacket::INFO_LOGIN_FAILED;
					$this->server->getLogger()->emergency("Client {$this->getIp()}:{$this->getPort()} tried to connect with wrong password!");
				}
				$this->sendDataPacket($pk);
				break;
			case Info::DISCONNECT_PACKET:
				/** @var DisconnectPacket $packet */
				$this->server->removeClient($this);
				break;
			case Info::REDIRECT_PACKET:
				/** @var RedirectPacket $packet */
				if(isset($this->players[$uuid = $packet->uuid->toBinary()])){
					$pk = new GenericPacket();
					$pk->buffer = $packet->mcpeBuffer;
					$this->players[$uuid]->sendDataPacket($pk, $packet->direct);
				}else{
					$this->server->getLogger()->error("Error RedirectPacket");
				}
				break;
			default:
				$this->server->getLogger()->error("Client {$this->getIp()}:{$this->getPort()} send an unknown packet " . $packet::NETWORK_ID);
		}
	}

	public function sendDataPacket(DataPacket $pk){
		$this->interface->putPacket($this, $pk);
	}

	public function getIp(){
		return $this->ip;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function isVerified() : bool{
		return $this->isVerified;
	}

	public function setVerified(){
		$this->isVerified = true;
	}

	public function getPlayers(){
		return $this->players;
	}

	public function addPlayer(Player $player){
		$this->players[$player->getRawUUID()] = $player;
	}

	public function removePlayer(Player $player){
		unset($this->players[$player->getRawUUID()]);
	}

	public function close(){
		foreach($this->players as $player){
			$player->close("Server Closed");
		}
	}
}