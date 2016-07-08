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

namespace synapse\event\player;

use synapse\Client;
use synapse\event\Cancellable;
use synapse\Player;

class PlayerTransferEvent extends PlayerEvent implements Cancellable{
	public static $handlerList = null;
	/** @var Client */
	private $targetClient;
	private $needDisconnect;

	public function __construct(Player $player, Client $client, bool $needDisconnect){
		parent::__construct($player);
		$this->targetClient = $client;
		$this->needDisconnect = $needDisconnect;
	}

	public function needDisconnect() : bool{
		return $this->needDisconnect;
	}

	public function getTargetClient() : Client{
		return $this->targetClient;
	}

	public function setTargetClient(Client $client){
		$this->targetClient = $client;
	}
}