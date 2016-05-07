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
use synapse\utils\MainLogger;

class SynapseSocket extends Thread{
	private $ip;
	private $port;
	private $socket;
	private $stop;
	public $waitIp = "";
	public $waitPort = 0;
	private $waiting;
	/** @var \Threaded */
	protected $buffer;

	public function isWaiting(){
		return $this->waiting === true;
	}

	public function getPBuffer(){
		if($this->buffer->count() !== 0){
			return $this->buffer->shift();
		}
		return null;
	}

	public function __construct(string $ip, int $port){
		$this->ip = $ip;
		$this->port = $port;
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($this->socket === false or !socket_bind($this->socket, $ip, (int) $port) or !socket_listen($this->socket)){
			MainLogger::getLogger()->critical("Synapse Server can't be started: " . socket_strerror(socket_last_error()));
			return;
		}
		socket_set_block($this->socket);

		socket_getsockname($this->socket, $addr, $port);
		MainLogger::getLogger()->info("Synapse Server is listening on $addr:$port");
		$this->stop = false;
		$this->buffer = new \Threaded;
		$this->start();
	}

	public function getSocket(){
		return $this->socket;
	}

	public function close(){
		socket_close($this->socket);
	}

	public function getConnectionById(string $id){
		return $this->{"client_" . $id};
	}

	public function writePacket($client, $buffer){
		return @socket_write($client, Binary::writeLInt(strlen($buffer)) . $buffer);
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
		if(is_resource($client)){
			$hash = self::clientHash($client);
			@socket_set_option($client, SOL_SOCKET, SO_LINGER, ["l_onoff" => 1, "l_linger" => 1]);
			@socket_shutdown($client, 2);
			@socket_set_block($client);
			@socket_read($client, 1);
			@socket_close($client);
			unset($this->{"client_" . ($hash)});
			//unset($this->{"timeout_" . $hash});
		}
	}

	public static function clientHash($client){
		if(!is_resource($client)){
			throw new \Exception("Invalid Client");
		}
		socket_getpeername($client, $addr, $port);
		return (str_replace(".", "", $addr) . $port);
	}

	public function run(){
		while(!$this->stop){
			$this->synchronized(function(){
				$this->wait(100);
			});
			$r = [$socket = $this->socket];
			$w = null;
			$e = null;
			if(socket_select($r, $w, $e, 0) === 1){
				if(($client = socket_accept($this->socket)) !== false){
					socket_set_block($client);
					socket_set_option($client, SOL_SOCKET, SO_KEEPALIVE, 1);
					socket_getpeername($client, $addr, $port);
					if(!isset($this->{"client_" . ($hash = self::clientHash($client))})){
						$this->{"client_" . $hash} = $client;
						$this->waitIp = $addr;
						$this->waitPort = $port;
						$this->synchronized(function(){
							$this->waiting = true;
							$this->wait();
						});
						$this->waiting = false;
						$this->waitIp = "";
						$this->waitPort = 0;
					}
					//$this->{"timeout_" . $hash} = microtime(true) + 5;
				}
			}

			foreach($this as $p => $v){
				if(strstr($p, "client_")){
					$client = &$v;
					$hash = explode("_", $p);
					$hash = $hash[1];
					if($client !== null and !$this->stop){
						/*if($this->{"timeout_" . $hash} < microtime(true)){ //Timeout
							$this->disconnect($client);
							continue;
						}*/
						$p = $this->readPacket($client, $buffer);
						if($p === false){
							$this->disconnect($client);
							unset($this->{$p});
							continue;
						}elseif($p === null){
							continue;
						}
						$this->buffer[] = $hash. "|" . $buffer;
					}
				}
			}
		}
	}

	public function getThreadName(){
		return "SynapseServer";
	}
}