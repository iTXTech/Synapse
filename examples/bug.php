<?php

$chan = new \Swoole\Channel(1024);

$proc = new \Swoole\Process(function(\Swoole\Process $process) use ($chan){
	$store = new class{
		public $sth = 234;
	};
	$server = new \Swoole\Http\Server("0.0.0.0", 2333);
	$server->on("start", function(\Swoole\Http\Server $server) use ($chan, $store){
		echo "Server on" . PHP_EOL;
		$server->tick(10, function() use ($chan, $store){
			if(($s = $chan->pop()) !== false){
				echo "Got from chan: " . $s . PHP_EOL;
				$store->sth = $s;
			}
		});
	});
	$server->on("request", function(\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($store){
		$response->end($store->sth);
	});
	$server->start();
});
$proc->start();

while(true){
	$chan->push(mt_rand(0, PHP_INT_MAX));
	sleep(1);
}