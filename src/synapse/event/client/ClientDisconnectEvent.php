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

namespace synapse\event\client;

use synapse\Client;

class ClientDisconnectEvent extends ClientEvent{
	public static $handlerList = null;

	private $reason;
	private $type;

	public function __construct(Client $client, string $reason, int $type){
		parent::__construct($client);
		$this->reason = $reason;
		$this->type = $type;
	}

	public function getReason() : string{
		return $this->reason;
	}

	public function setReason(string $reason){
		$this->reason = $reason;
	}

	public function getType() : int{
		return $this->type;
	}
}