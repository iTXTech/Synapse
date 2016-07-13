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


class SessionManager{
	protected $shutdown = false;

	/** @var SynapseServer */
	protected $server;
	/** @var SynapseSocket */
	protected $socket;
	/** @var Session[] */
	private $sessions = [];

	public function __construct(SynapseServer $server, SynapseSocket $socket){
		$this->server = $server;
		$this->socket = $socket;
		$this->run();
	}

	public function run(){
		$this->tickProcessor();
	}

	private function tickProcessor(){
		while(!$this->server->isShutdown()){
			$start = microtime(true);
			$this->tick();
			$time = microtime(true);
			if($time - $start < 0.01){
				@time_sleep_until($time + 0.01 - ($time - $start));
			}
		}
		$this->tick();
		foreach($this->sessions as $client){
			$client->close();
		}
		$this->socket->close();
	}

	public function getClients(){
		return $this->sessions;
	}

	public function getServer(){
		return $this->server;
	}

	private function tick(){
		try{
			while(($socket = $this->socket->getClient())){
				$session = new Session($this, $socket);
				$this->sessions[$session->getHash()] = $session;
				$this->server->addClientOpenRequest($session->getHash());
			}

			while(strlen($data = $this->server->readMainToThreadPacket()) > 0){
				$tmp = explode("|", $data, 2);
				if(count($tmp) == 2){
					if(isset($this->sessions[$tmp[0]])){
						$this->sessions[$tmp[0]]->writePacket($tmp[1]);
					}
				}
			}

			foreach($this->sessions as $session){
				if($session->update()){
					while(($data = $session->readPacket()) !== null){
						$this->server->pushThreadToMainPacket($session->getHash() . "|" . $data);
					}
				}else{
					$this->server->addInternalClientCloseRequest($session->getHash());
					unset($this->sessions[$session->getHash()]);
				}
			}
			
			while(strlen($data = $this->server->getExternalClientCloseRequest()) > 0){
				$this->sessions[$data]->close();
				unset($this->sessions[$data]);
			}
		}catch(\Throwable $e){
			$this->server->getLogger()->logException($e);
		}
	}
}