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
use synapse\network\protocol\spp\DataPacket;
use synapse\Server;

class SynapseInterface{
	private $server;
	private $ip;
	private $port;
	/** @var SynapseSocket */
	private $socket;
	/** @var Client[] */
	private $clients = [];
	
	public function __construct(Server $server, $ip, int $port){
		$this->server = $server;
		$this->ip = $ip;
		$this->port = $port;
		$this->socket = new SynapseSocket($server, $ip, $port);
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

	public function handlePacket(DataPacket $pk){

	}
}