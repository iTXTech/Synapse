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

use synapse\utils\Binary;

class ClientConnection{

	const MAGIC_BYTES = "\x35\xac\x66\xbf";

	private $receiveBuffer = "";
	private $sendBuffer = "";
	/** @var resource */
	private $socket;
	private $ip;
	private $port;

	public function __construct(ClientManager $clientManager, $socket){
		$this->clientManager = $clientManager;
		$this->socket = $socket;
		socket_getpeername($this->socket, $address, $port);
		$this->ip = $address;
		$this->port = $port;
	}

	public function getHash(){
		return $this->ip . ':' . $this->port;
	}

	public function getIp() : string {
		return $this->ip;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function update(){
		$err = socket_last_error($this->socket);
		if($err == 10057 or $err == 10054){
			$this->clientManager->getServer()->getLogger()->error("Synapse client [$this->ip:$this->port] has disconnected unexpectedly");
			return false;
		}else{
			$data = @socket_read($this->socket, 65535, PHP_BINARY_READ);
			$this->receiveBuffer .= $data;
			if($this->sendBuffer != ""){
				socket_write($this->socket, $this->sendBuffer);
				$this->sendBuffer = "";
			}
			return true;
		}
	}

	public function getSocket(){
		return $this->socket;
	}

	public function close(){
		@socket_close($this->socket);
	}

	public function readPacket(){
		$end = explode(self::MAGIC_BYTES, $this->receiveBuffer, 2);
		if(count($end) <= 2){
			if(count($end) == 1){
				if(strstr($end[0], self::MAGIC_BYTES)){
					$this->receiveBuffer = "";
				}else{
					return null;
				}
			}else{
				$this->receiveBuffer = $end[1];
			}
			$buffer = $end[0];
			if(strlen($buffer) < 4){
				return null;
			}
			$len = Binary::readLInt(substr($buffer, 0, 4));
			$buffer = substr($buffer, 4);
			if($len != strlen($buffer)){
				throw new \Exception("Wrong packet 0x" . ord($buffer{0}) . ": $buffer");
			}
			return $buffer;
		}
		return null;
	}

	public function writePacket($data){
		$this->sendBuffer .= Binary::writeLInt(strlen($data)) . $data . self::MAGIC_BYTES;
	}
}