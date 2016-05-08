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

use synapse\network\synapse\ClientManager;

class ClientConnection{

	private $getData = '';
	private $sendData = [];
	/** @var resource */
	private $socket;

	public function __construct(ClientManager $clientManager, $socket){
		$this->clientManager = $clientManager;
		$this->socket = $socket;
	}

	public function update()
	{
		if(count($this->sendData) > 0){
			$data = implode('\r\n', $this->sendData);
			socket_write($this->socket, $data);
		}
		$data = socket_read($this->socket, 2048, PHP_BINARY_READ);
		$this->getData .= $data;
	}

	public function getSocket()
	{
		return $this->socket;
	}

	public function readData()
	{
		$buff = "\r\n";
		$end = explode($buff, $this->getData, 2);
		if(count($end) == 2){
			$this->getData = $end[1];
			if($end[0] == ''){
				return null;
			}
			return $end[0];
		}
		return null;
	}

	public function sendData(string $data)
	{
		$this->sendData[] = $data;
	}

}