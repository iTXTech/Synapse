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

namespace iTXTech\Synapse;

use iTXTech\Synapse\Kyrios\Kyrios;
use iTXTech\Synapse\Raknet\Raknet;

class Launcher{
	private $kHost;
	private $kPort;
	private $kSwOpts = [
		"worker_num" => 8,
		"task_worker_num" => 16
	];

	private $rHost;
	private $rPort;
	private $rMaxMtuSize = 1492;
	private $rServerName;
	private $rServerId;
	private $rSwOpts = [
		"worker_num" => 8,
		"task_worker_num" => 16
	];


	public function getRServerId(): int{
		return $this->rServerId;
	}

	public function __construct(){
		$this->rServerId = mt_rand(0, PHP_INT_MAX);
	}

	//settings for Kyrios

	public function kListen(string $host, int $port){
		$this->kHost = $host;
		$this->kPort = $port;
		return $this;
	}

	public function kSwOpts(array $opts){
		$this->kSwOpts = array_merge($this->kSwOpts, $opts);
		return $this;
	}

	//settings for Raknet

	public function rListen(string $host, int $port){
		$this->rHost = $host;
		$this->rPort = $port;
		return $this;
	}

	public function rMaxMtuSize(int $size){
		$this->rMaxMtuSize = $size;
		return $this;
	}

	public function rSwOpts(array $opts){
		$this->rSwOpts = array_merge($this->rSwOpts, $opts);
		return $this;
	}

	public function rServerName(string $serverName){
		$this->rServerName = $serverName;
		return $this;
	}

	public function rServerId(string $id){
		$this->rServerId = $id;
		return $this;
	}

	public function build(): Synapse{
		$kyrios = new Kyrios($this->kHost, $this->kPort, $this->kSwOpts);
		$raknet = new Raknet($this->rHost, $this->rPort, $this->rSwOpts, $this->rMaxMtuSize,
			$this->rServerName, $this->rServerId);
		return new Synapse($kyrios, $raknet);
	}

	public function launch(): Synapse{
		$synapse = $this->build();
		$synapse->launch();
		return $synapse;
	}
}