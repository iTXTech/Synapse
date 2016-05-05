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

use synapse\Thread;
use synapse\utils\Binary;

class SynapseSocket extends Thread{
	private $interface;
	private $ip;
	private $port;
	private $socket;
	private $stop = false;
	private $clients = [];

	public function __construct(SynapseInterface $interface, $ip, int $port){
		$this->interface = $interface;
		$this->ip = $ip;
		$this->port = $port;
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($this->socket === false or !socket_bind($this->socket, $ip, (int) $port) or !socket_listen($this->socket)){
			$this->interface->getServer()->getLogger()->critical("Synapse Server can't be started: " . socket_strerror(socket_last_error()));
			return;
		}
		socket_set_block($this->socket);

		socket_getsockname($this->socket, $addr, $port);
		$this->interface->getServer()->getLogger()->info("Synapse Server is listening on $addr:$port");
		$this->start();
	}

	public function getSocket(){
		return $this->socket;
	}

	public function close(){
		socket_close($this->socket);
	}

	public function getConnectionById(string $id){
		return $this->clients[$id];
	}

	public function writePacket($client, $buffer){
		return socket_write($client, Binary::writeLInt(strlen($buffer)) . $buffer);
	}

	public function readPacket($client, &$buffer){
		socket_set_nonblock($client);
		$d = @socket_read($client, 4);
		if($this->stop === true){
			return false;
		}elseif($d === false){
			return null;
		}elseif($d === "" or strlen($d) < 4){
			return false;
		}
		socket_set_block($client);
		$size = Binary::readLInt($d);
		if($size < 0 or $size > 65535){
			return false;
		}
		$buffer = rtrim(socket_read($client, $size + 2)); //Strip two null bytes
		return true;
	}

	public function disconnect($client){
		@socket_set_option($client, SOL_SOCKET, SO_LINGER, ["l_onoff" => 1, "l_linger" => 1]);
		@socket_shutdown($client, 2);
		@socket_set_block($client);
		@socket_read($client, 1);
		@socket_close($client);
		unset($this->clients[self::clientHash($client)]);
	}

	public static function clientHash($client){
		socket_getpeername($client, $addr, $port);
		$i = explode($addr, ".");
		return ($i[0]. $i[1]. $i[2]. $i[3]. $port);
	}

	public function run(){
		while(!$this->stop){
			$r = [$socket = $this->socket];
			$w = null;
			$e = null;
			if(socket_select($r, $w, $e, 0) === 1){
				if(($client = socket_accept($this->socket)) !== false){
					socket_set_block($client);
					socket_set_option($client, SOL_SOCKET, SO_KEEPALIVE, 1);
					socket_getpeername($client, $addr, $port);
					if(!isset($this->clients[$hash = self::clientHash($client)])){
						$this->clients[$hash] = [
							"client" => $client,
							"addr" => $addr,
							"port" => $port,
							"timeout" => microtime(true) + 5,
						];
					}else{
						$this->clients[$hash]["timeout"] = microtime(true) + 5;
					}
				}
			}

			foreach($this->clients as $cli){
				$client = &$cli["client"];
				if($client !== null and !$this->stop){
					if($cli["timeout"] < microtime(true)){ //Timeout
						$this->disconnect($client);
						continue;
					}
					$p = $this->readPacket($client, $buffer);
					if($p === false){
						$this->disconnect($client);
						continue;
					}elseif($p === null){
						continue;
					}
					$this->interface->handlePacket($client, $buffer);
				}
			}
		}
	}

	public function getThreadName(){
		return "SynapseServer";
	}
}