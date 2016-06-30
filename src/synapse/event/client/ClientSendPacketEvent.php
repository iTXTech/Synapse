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
use synapse\event\Cancellable;
use synapse\network\protocol\spp\DataPacket;

class ClientSendPacketEvent extends ClientEvent implements Cancellable{
	public static $handlerList = null;

	/** @var DataPacket */
	private $packet;

	public function __construct(Client $client, DataPacket $packet){
		parent::__construct($client);
		$this->packet = $packet;
	}

	public function getPacket() : DataPacket{
		return $this->packet;
	}
}