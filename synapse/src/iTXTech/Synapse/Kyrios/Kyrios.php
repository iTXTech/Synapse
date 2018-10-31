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

namespace iTXTech\Synapse\Kyrios;

use Co\Server;
use iTXTech\SimpleFramework\Console\Logger;
use iTXTech\SimpleFramework\Console\TextFormat;
use iTXTech\Synapse\Raknet\MessageProcessor;
use iTXTech\Synapse\Raknet\Raknet;
use Swoole\Channel;
use Swoole\Process;

class Kyrios{
	/** @var Process */
	private $proc;

	private $host;
	private $port;
	private $swOpts;

	/** @var Raknet */
	private $raknet;

	/** @var Channel */
	private $rChan;
	/** @var Channel */
	private $kChan;

	/** @var RaknetMessageHandler */
	private $handler;

	public function __construct(string $host, int $port, array $swOpts){
		$this->host = $host;
		$this->port = $port;
		$this->swOpts = $swOpts;
	}

	public function init(Channel $rChan, Channel $kChan, Raknet $raknet){
		$this->rChan = $rChan;
		$this->kChan = $kChan;
		$this->raknet = $raknet;
		$this->handler = new RaknetMessageHandler($this->raknet);
	}

	public function launch(){
		$host = $this->host;
		$port = $this->port;
		$swOpts = $this->swOpts;
		$rChan = $this->rChan;
		$kChan = $this->kChan;
		$handler = $this->handler;

		$this->proc = new Process(function(Process $process) use ($host, $port, $swOpts, $rChan, $kChan, $handler){
			$server = new Server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
			$server->on("start", function(Server $server) use ($handler){
				Logger::info(TextFormat::GREEN . "iTXTech Synapse Kyrios Server is listening on " . $server->host . ":" . $server->port);
				$server->tick(10, function() use ($handler){
					$handler->tick();
				});
			});
			$server->on("connect", function(Server $server, $fd, $fromId){

			});
			$server->on("receive", function(Server $server, $fd, $fromId, $data){

			});
			$server->on("task", function(Server $server, $taskId, $fromId, $data) use ($handler){
				$handler->task($data);
			});
			$server->on("finish", function(Server $server, $taskId, $data){
			});

			$server->start();
		});
		$this->proc->start();
	}

	public function shutdown(){
		$this->proc->close();
	}
}