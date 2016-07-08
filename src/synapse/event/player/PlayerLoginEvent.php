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

use synapse\event\Cancellable;
use synapse\Player;

class PlayerLoginEvent extends PlayerEvent implements Cancellable{
	public static $handlerList = null;
	private $kickMessage;
	private $clientHash;

	public function __construct(Player $player, string $kickMessage, string $clientHash){
		parent::__construct($player);
		$this->clientHash = $clientHash;
	}

	public function setClientHash(string $clientHash){
		$this->clientHash = $clientHash;
	}

	public function getClientHash() : string{
		return $this->clientHash;
	}

	public function setKickMessage(string $kickMessage){
		$this->kickMessage = $kickMessage;
	}

	public function getKickMessage() : string{
		return $this->kickMessage;
	}
}