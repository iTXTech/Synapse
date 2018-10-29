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

$t = new \Swoole\Table(65536);
$t->column("a", \Swoole\Table::TYPE_STRING, PHP_INT_MAX);
$t->create();

$proc = new \Swoole\Process(function(\Swoole\Process $process) use ($t){
	$s = new \Swoole\Http\Server("0.0.0.0", 2333);
	$s->on("start", function(\Swoole\Http\Server $server){
		echo "started." . PHP_EOL;
	});
	$s->on("request", function(\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($t){
		$ts = microtime(true);
		$a = \Swoole\Serialize::unpack($t->get(0, "a"));
		$tu = (microtime(true) - $ts) * 1000;
		$response->end("Time used: " . $tu . " ms");
	});
	$s->start();
});
$proc->start();

class Something{
	public $buffer;

	public function __construct(){
		$this->buffer = str_repeat(mt_rand(0, 9), 1000);
	}
}

while(true){
	$a = [];
	for($i = 0; $i < 100; $i++){
		//$a[] = new Something();
		$a[] = str_repeat(mt_rand(0, 9), 1000);
	}
	$ts = microtime(true);
	$a = \Swoole\Serialize::pack($a);
	$tu = (microtime(true) - $ts) * 1000;
	$t->set(0, ["a" => $a]);
	sleep(1);
	echo "Generated in $tu ms" . PHP_EOL;
}