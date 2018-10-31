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

ini_set("memory_limit", -1);

use Swoole\Channel;
use Swoole\Lock;
/*
const TABLE_SESSION_LIMIT = 1024 * 128;

$ts = microtime(true);
$locks = [];
$lockQueue = new Channel(TABLE_SESSION_LIMIT * 24);

for($i = 0; $i < TABLE_SESSION_LIMIT; $i++){
	$locks[$i] = new Lock(SWOOLE_MUTEX);
	echo $i . PHP_EOL;
}
$tu = (microtime(true) - $ts) * 1000;
echo TABLE_SESSION_LIMIT . " locks in $tu ms" . PHP_EOL;

while(true);
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
		$response->write("Time used: " . $tu . " ms " . count($a));
		$response->end();
	});
	$s->start();
});
$proc->start();

class Something{
	private $buffer;

	public function __construct(){
		//global $ggg;
		$this->buffer = str_repeat("0", 1024 * 1024);
	}
}

while(true){
	$a = [];
	for($i = 0; $i < 1024; $i++){
		$a[] = new Something();
	}
	echo "done";
	$ts = microtime(true);
	$b = \Swoole\Serialize::pack($a);
	$tu = (microtime(true) - $ts) * 1000;
	echo "Generated in $tu ms" . PHP_EOL;
	$t->set(0, ["a" => $b]);
	sleep(1);
}