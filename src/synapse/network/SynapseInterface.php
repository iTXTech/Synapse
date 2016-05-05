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
 
namespace synapse\network;

use synapse\Client;
use synapse\network\protocol\mcpe\DisconnectPacket;
use synapse\network\protocol\spp\ConnectPacket;
use synapse\network\protocol\spp\DataPacket;
use synapse\network\protocol\spp\HeartbeatPacket;
use synapse\network\protocol\spp\Info;
use synapse\network\protocol\spp\PlayerLoginPacket;
use synapse\network\protocol\spp\RedirectPacket;
use synapse\Server;

class SynapseInterface{
	private $server;
	private $ip;
	private $port;
	/** @var SynapseSocket */
	private $socket;
	/** @var Client[] */
	private $clients = [];
	/** @var DataPacket[] */
	private $packetPool = [];
	
	public function __construct(Server $server, $ip, int $port){
		$this->server = $server;
		$this->ip = $ip;
		$this->port = $port;
		$this->registerPackets();
		$this->socket = new SynapseSocket($this, $ip, $port);
	}

	public function getServer(){
		return $this->server;
	}

	public function addClient($client, $ip, $port){
		$this->clients[SynapseSocket::clientHash($client)] = new Client($this->server, $ip, $port);
		$this->server->addClient($this->clients[SynapseSocket::clientHash($client)]);
	}

	public function removeClient($client){
		$this->server->removeClient($this->clients[SynapseSocket::clientHash($client)]);
		unset($this->clients[SynapseSocket::clientHash($client)]);
	}

	public function putPacket(Client $client, DataPacket $pk){
		$id = str_replace(".", "", $client->getIp()) . $client->getPort();
		$client = $this->clients[$id];
		if(!$pk->isEncoded){
			$pk->encode();
		}
		$this->socket->writePacket($client, $pk->buffer);
	}

	public function process(){

	}

	/**
	 * @param $buffer
	 *
	 * @return DataPacket
	 */
	public function getPacket($buffer) {
		$pid = ord($buffer{0});
		/** @var DataPacket $class */
		$class = $this->packetPool[$pid];
		if ($class !== null) {
			$pk = clone $class;
			$pk->setBuffer($buffer, 1);
			return $pk;
		}
		return null;
	}

	public function handlePacket($client, $buffer){
		if(!isset($this->clients[$hash = SynapseSocket::clientHash($client)])){
			throw new \Exception("Invalid Client");
		}

		$client = $this->clients[$client];

		if(($pk = $this->getPacket($buffer)) != null){
			switch($pk::NETWORK_ID){
				case Info::HEARTBEAT_PACKET:
					if(!$client->isVerified()){
						$this->server->getLogger()->error("Client {$client->getIp()}:{$client->getPort()} is not verified");
						return;
					}
					break;
				case Info::CONNECT_PACKET:
					/** @var ConnectPacket $pk */
					if($this->server->comparePassword($pk->encodedPassword)){
						$client->setVerified();
					}
					break;
			}
		}
	}

	/**
	 * @param int        $id 0-255
	 * @param DataPacket $class
	 */
	public function registerPacket($id, $class) {
		$this->packetPool[$id] = new $class;
	}


	private function registerPackets() {
		$this->packetPool = new \SplFixedArray(256);

		$this->registerPacket(Info::HEARTBEAT_PACKET, HeartbeatPacket::class);
		$this->registerPacket(Info::CONNECT_PACKET, ConnectPacket::class);
		$this->registerPacket(Info::DISCONNECT_PACKET, DisconnectPacket::class);
		$this->registerPacket(Info::REDIRECT_PACKET, RedirectPacket::class);
		$this->registerPacket(Info::PLAYER_LOGIN_PACKET, PlayerLoginPacket::class);
	}
}