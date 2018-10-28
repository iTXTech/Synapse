<?php

/*
 *
 * iTXTech Synapse
 *
 * Copyright (C) 2018 iTX Technologies
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace iTXTech\Synapse\Raknet;

use Co\Server;
use iTXTech\SimpleFramework\Console\Logger;
use iTXTech\SimpleFramework\Console\TextFormat;
use iTXTech\Synapse\Util\InternetAddress;
use Swoole\Channel;
use Swoole\Process;

class Raknet{
	/** @var Process */
	private $proc;

	private $host;
	private $port;
	private $swOpts;
	private $maxMtuSize;

	/** @var Channel */
	private $rChan;
	/** @var Channel */
	private $kChan;

	private $serverName;
	private $serverId;

	public function __construct(string $host, int $port, array $swOpts, int $maxMtuSize, string $serverName, int $serverId){
		$this->host = $host;
		$this->port = $port;
		$this->swOpts = $swOpts;
		$this->maxMtuSize = $maxMtuSize;
		$this->serverName = $serverName;
		$this->serverId = $serverId;
	}

	public function channel(Channel $rChan, Channel $kChan){
		$this->rChan = $rChan;
		$this->kChan = $kChan;
	}

	public function launch(){
		$host = $this->host;
		$port = $this->port;
		$swOpts = $this->swOpts;
		$maxMtuSize = $this->maxMtuSize;
		$rChan = $this->rChan;
		$kChan = $this->kChan;
		$serverName = $this->serverName;
		$serverId = $this->serverId;

		$this->proc = new Process(function(Process $process) use ($host, $port, $swOpts, $maxMtuSize, $rChan, $kChan, $serverName, $serverId){
			$server = new Server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
			$server->set($swOpts);

			$sessionManager = new SessionManager(new InternetAddress($host, $port, 4),
				$rChan, $kChan,
				$server, $serverName, $serverId);//TODO: 6

			$server->on("start", function(Server $server) use ($sessionManager){
				Logger::info(TextFormat::GREEN . "iTXTech Synapse RakNet is listening on " . $server->host . ":" . $server->port);
				$server->tick(10, function() use ($sessionManager){
					$sessionManager->tick();
				});
			});
			$server->on("packet", function(Server $server, $data, $clientInfo) use ($sessionManager){
				$sessionManager->receivePacket($clientInfo["address"], $clientInfo["port"], $data);
			});

			$server->start();
		});
		$this->proc->start();
	}

	public function shutdown(){
		$this->proc->close();
	}

	public function setServerName(string $serverName): void{
		$this->serverName = $serverName;
		$this->kChan->push(chr(Properties::PACKET_SET_OPTION) . chr(strlen("name")) . "name" . $serverName);
	}
}