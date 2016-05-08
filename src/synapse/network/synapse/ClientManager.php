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

namespace synapse\network\synapse;

use synapse\Client;
use synapse\ClientConnection;

class ClientManager
{

    protected $packetLimit = 1000;
    protected $shutdown = false;
    
    /** @var SynapseServer */
    protected $server;
    /** @var SynapseSocket */
    protected $socket;
    /** @var int */
    private $serverId;
    /** @var Client[] */
    private $client = [];

    public function __construct(SynapseServer $server, SynapseSocket $socket)
    {
        $this->server = $server;
        $this->socket = $socket;

        $this->serverId = mt_rand(0, PHP_INT_MAX);
        $this->run();
    }

    public function run(){
        $this->tickProcessor();
    }

    private function tickProcessor(){
        while(!$this->shutdown){
            $start = microtime(true);
            $this->tick();
            $time = microtime(true);
            if($time - $start < 0.01){
                @time_sleep_until($time + 0.01 - ($time - $start));
            }
        }
    }
    
    public function getClients()
    {
        return $this->client;
    }
    
    private function tick()
    {
        while(($socket = $this->socket->getClient())){
            $this->client[] = new ClientConnection($this, $socket);
        }
        
        foreach($this->client as $client){
            $client->update();
            if(($data = $client->readData()) !== null){
                socket_getpeername($client->getSocket(), $address, $port);
                $this->server->pushThreadToMainPacket($address.':'.$port.'-->'.$data);
                //TODO
            }
        }
    }
}