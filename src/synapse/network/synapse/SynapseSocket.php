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


class SynapseSocket{

    public function __construct(\ThreadedLogger $logger, $port = 19132, $interface = "0.0.0.0")
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(@socket_bind($this->socket, $interface, $port) !== true){
            $logger->critical("**** FAILED TO BIND TO " . $interface . ":" . $port . "!");
            $logger->critical("Perhaps a server is already running on that port?");
            exit(1);
        }
        socket_listen($this->socket);
        socket_set_nonblock($this->socket);
    }

    public function getClient()
    {
        return socket_accept($this->socket);
    }

    public function getSocket(){
        return $this->socket;
    }

    public function close(){
        socket_close($this->socket);
    }

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setSendBuffer($size){
        @socket_set_option($this->socket, SOL_SOCKET, SO_SNDBUF, $size);
        return $this;
    }

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setRecvBuffer($size){
        @socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, $size);
        return $this;
    }

}