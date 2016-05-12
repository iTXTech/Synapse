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

namespace synapse\network\synlib;


class ClientManager{
	protected $shutdown = false;

	/** @var SynapseServer */
	protected $server;
	/** @var SynapseSocket */
	protected $socket;
	/** @var int */
	private $serverId;
	/** @var ClientConnection[] */
	private $client = [];

	public function __construct(SynapseServer $server, SynapseSocket $socket){
		$this->server = $server;
		$this->socket = $socket;

		$this->serverId = mt_rand(0, PHP_INT_MAX);
		$this->run();
	}

	public function run(){
		$this->tickProcessor();
	}

	private function tickProcessor(){
		while(!$this->shutdown){
			$start = microtime(true);
			$this->tick();
			$time = microtime(true);
			if($time - $start < 0.01){
				@time_sleep_until($time + 0.01 - ($time - $start));
			}
		}
	}

	public function getClients(){
		return $this->client;
	}

	private function tick(){
		while(($socket = $this->socket->getClient())){
			$client = new ClientConnection($this, $socket);
			$this->client[$client->getHash()] = $client;
			$this->server->addClientOpenRequest($client->getHash());
		}

		foreach($this->client as $client){
			$client->update();
			while(($data = $client->readPacket()) !== null){
				$this->server->pushThreadToMainPacket($client->getHash() . "|" . $data);
			}
		}

		while(strlen($data = $this->server->readMainToThreadPacket()) > 0){
			$tmp = explode("|", $data, 2);
			if(count($tmp) == 2){
				$this->client[$tmp[0]]->writePacket($tmp[1]);
			}
		}
	}
}