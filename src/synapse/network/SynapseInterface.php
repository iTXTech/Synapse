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
use synapse\network\protocol\spp\DisconnectPacket;
use synapse\network\protocol\spp\ConnectPacket;
use synapse\network\protocol\spp\DataPacket;
use synapse\network\protocol\spp\HeartbeatPacket;
use synapse\network\protocol\spp\Info;
use synapse\network\protocol\spp\InformationPacket;
use synapse\network\protocol\spp\PlayerLoginPacket;
use synapse\network\protocol\spp\PlayerLogoutPacket;
use synapse\network\protocol\spp\RedirectPacket;
use synapse\network\protocol\spp\TransferPacket;
use synapse\network\synlib\SynapseServer;
use synapse\Server;

class SynapseInterface{
	private $server;
	private $ip;
	private $port;
	/** @var Client[] */
	private $clients;
	/** @var DataPacket[] */
	private $packetPool = [];
	/** @var SynapseServer */
	private $interface;

	public function __construct(Server $server, $ip, int $port){
		$this->server = $server;
		$this->ip = $ip;
		$this->port = $port;
		$this->registerPackets();
		$this->interface = new SynapseServer($server->getLogger(), $this, $server->getLoader(), $port, $ip);
	}

	public function getServer(){
		return $this->server;
	}

	public function addClient($ip, $port){
		$this->clients[$ip . ":" . $port] = new Client($this, $ip, $port);
	}

	public function removeClient(Client $client){
		$this->interface->addExternalClientCloseRequest($client->getHash());
		unset($this->clients[$client->getHash()]);
	}

	public function putPacket(Client $client, DataPacket $pk){
		if(!$pk->isEncoded){
			$pk->encode();
		}
		$this->interface->pushMainToThreadPacket($client->getHash() . "|" . $pk->buffer);
	}

	public function process(){
		while(strlen($data = $this->interface->getClientOpenRequest()) > 0){
			$tmp = explode(":", $data);
			$this->addClient($tmp[0], $tmp[1]);
		}
		while(strlen($data = $this->interface->readThreadToMainPacket()) > 0){
			$tmp = explode("|", $data, 2);
			if(count($tmp) == 2){
				$this->handlePacket($tmp[0], $tmp[1]);
			}
		}
		while(strlen($data = $this->interface->getInternalClientCloseRequest()) > 0){
			$this->clients[$data]->closeAllPlayers();
			$this->server->removeClient($this->clients[$data]);
			unset($this->clients[$data]);
		}
	}

	/**
	 * @param $buffer
	 *
	 * @return DataPacket
	 */
	public function getPacket($buffer){
		$pid = ord($buffer{0});
		/** @var DataPacket $class */
		$class = $this->packetPool[$pid];
		if($class !== null){
			$pk = clone $class;
			$pk->setBuffer($buffer, 1);
			return $pk;
		}
		return null;
	}

	public function handlePacket($hash, $buffer){
		if(!isset($this->clients[$hash])){
			throw new \Exception("Invalid Client");
		}

		$client = $this->clients[$hash];

		if(($pk = $this->getPacket($buffer)) != null){
			$pk->decode();
			$client->handleDataPacket($pk);
		}else{
			$this->server->getLogger()->critical("Error packet: 0x" . dechex(ord($buffer{0})) . " $buffer");
		}
	}

	/**
	 * @param int        $id 0-255
	 * @param DataPacket $class
	 */
	public function registerPacket($id, $class){
		$this->packetPool[$id] = new $class;
	}


	private function registerPackets(){
		$this->packetPool = new \SplFixedArray(256);

		$this->registerPacket(Info::HEARTBEAT_PACKET, HeartbeatPacket::class);
		$this->registerPacket(Info::CONNECT_PACKET, ConnectPacket::class);
		$this->registerPacket(Info::DISCONNECT_PACKET, DisconnectPacket::class);
		$this->registerPacket(Info::REDIRECT_PACKET, RedirectPacket::class);
		$this->registerPacket(Info::PLAYER_LOGIN_PACKET, PlayerLoginPacket::class);
		$this->registerPacket(Info::PLAYER_LOGOUT_PACKET, PlayerLogoutPacket::class);
		$this->registerPacket(Info::INFORMATION_PACKET, InformationPacket::class);
		$this->registerPacket(Info::TRANSFER_PACKET, TransferPacket::class);
	}
}