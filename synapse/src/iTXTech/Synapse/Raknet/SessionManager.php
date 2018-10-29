<?php

/*
 * RakLib network library
 *
 *
 * This project is not affiliated with Jenkins Software LLC nor RakNet.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace iTXTech\Synapse\Raknet;

use Co\Server;
use iTXTech\SimpleFramework\Console\Logger;
use iTXTech\Synapse\Util\Binary;
use iTXTech\Synapse\Util\InternetAddress;
use iTXTech\Synapse\Raknet\Protocol\ACK;
use iTXTech\Synapse\Raknet\Protocol\AdvertiseSystem;
use iTXTech\Synapse\Raknet\Protocol\Datagram;
use iTXTech\Synapse\Raknet\Protocol\EncapsulatedPacket;
use iTXTech\Synapse\Raknet\Protocol\NACK;
use iTXTech\Synapse\Raknet\Protocol\OfflineMessage;
use iTXTech\Synapse\Raknet\Protocol\OpenConnectionReply1;
use iTXTech\Synapse\Raknet\Protocol\OpenConnectionReply2;
use iTXTech\Synapse\Raknet\Protocol\OpenConnectionRequest1;
use iTXTech\Synapse\Raknet\Protocol\OpenConnectionRequest2;
use iTXTech\Synapse\Raknet\Protocol\Packet;
use iTXTech\Synapse\Raknet\Protocol\UnconnectedPing;
use iTXTech\Synapse\Raknet\Protocol\UnconnectedPingOpenConnections;
use iTXTech\Synapse\Raknet\Protocol\UnconnectedPong;
use Swoole\Channel;
use Swoole\Serialize;
use Swoole\Table;

class SessionManager{
	private const TABLE_BLOCK_TIMEOUT = "to";

	/** @var \SplFixedArray<Packet|null> */
	protected $packetPool;

	/** @var Session[] */
	protected $sessions = [];

	/** @var OfflineMessageHandler */
	protected $offlineMessageHandler;

	/** @var bool */
	protected $shutdown = false;

	/** @var int */
	protected $startTimeMS;

	/** @var int */
	protected $maxMtuSize;

	/** @var InternetAddress */
	protected $serverAddr;

	/** @var Channel */
	private $rChan;
	/** @var Channel */
	private $kChan;

	private $protocolVersion;
	/** @var Server */
	private $server;
	/** @var Table */
	private $table;
	/** @var Table */
	private $block;

	public function __construct(InternetAddress $address, Channel $rChan, Channel $kChan, Server $server,
	                            Table $table, int $maxMtuSize = 1492,
	                            int $protocolVersion = Properties::DEFAULT_PROTOCOL_VERSION){
		$this->rChan = $rChan;
		$this->kChan = $kChan;
		$this->startTimeMS = (int) (microtime(true) * 1000);
		$this->maxMtuSize = $maxMtuSize;
		$this->offlineMessageHandler = new OfflineMessageHandler($this);
		$this->serverAddr = $address;

		$this->protocolVersion = $protocolVersion;
		$this->server = $server;
		$this->table = $table;
		$this->block = new Table(2048);//2048 Blocked address
		$this->block->column(self::TABLE_BLOCK_TIMEOUT, Table::TYPE_INT);
		$this->block->create();

		$this->registerPackets();
	}

	private function getFromTable(string $key){
		return $this->table->get(Raknet::TABLE_MAIN_KEY, $key);
	}

	private function setToTable(array $arr){
		$this->table->set(Raknet::TABLE_MAIN_KEY, $arr);
	}

	private function getArrFromTable(string $key) : array {
		return Serialize::unpack($this->getFromTable($key));
	}

	private function setArrToTable(string $key, array $arr){
		$this->setToTable([$key => Serialize::pack($arr)]);
	}

	public function isPortChecking() : bool {
		return $this->getFromTable(Raknet::TABLE_PORT_CHECKING) === 0 ? false : true;
	}

	/**
	 * Returns the time in milliseconds since server start.
	 *
	 * @return int
	 */
	public function getRakNetTimeMS() : int{
		return ((int) (microtime(true) * 1000)) - $this->startTimeMS;
	}

	public function getPort() : int{
		return $this->serverAddr->port;
	}

	public function getMaxMtuSize() : int{
		return $this->maxMtuSize;
	}

	public function getProtocolVersion() : int{
		return $this->protocolVersion;
	}

	public function tick() : void{
		while($this->receiveStream()) ;

		$time = microtime(true);
		foreach($this->sessions as $session){
			$session->update($time);
		}

		$this->setArrToTable(Raknet::TABLE_IP_SEC, []);

		if($this->getFromTable(Raknet::TABLE_SEND_BYTES) > 0 or
			$this->getFromTable(Raknet::TABLE_RECEIVE_BYTES) > 0){
			$diff = max(0.005, $time - $this->getFromTable(Raknet::TABLE_LAST_MEASURE));
			$this->streamOption("bandwidth", serialize([
				"up" => $this->getFromTable(Raknet::TABLE_SEND_BYTES) / $diff,
				"down" => $this->getFromTable(Raknet::TABLE_RECEIVE_BYTES) / $diff
			]));
			$this->setToTable([Raknet::TABLE_SEND_BYTES => 0, Raknet::TABLE_RECEIVE_BYTES => 0]);
		}
		$this->setToTable([Raknet::TABLE_LAST_MEASURE => $time]);

		if($this->block->count() > 0){
			$now = time();
			foreach($this->block as $address => $value){
				if($value[self::TABLE_BLOCK_TIMEOUT] <= $now){
					$this->block->del($address);
				}
			}
		}
	}


	public function receivePacket(string $addr, int $port, string $buffer) : bool{
		$address = new InternetAddress($addr, $port, 4);

		$this->table->incr(Raknet::TABLE_MAIN_KEY, Raknet::TABLE_RECEIVE_BYTES, strlen($buffer));
		if(isset($this->block[$address->ip])){
			return true;
		}

		$ipSec = $this->getArrFromTable(Raknet::TABLE_IP_SEC);
		if(isset($ipSec[$address->ip])){
			if(++$ipSec[$address->ip] >= $this->getFromTable(Raknet::TABLE_PACKET_LIMIT)){
				$this->blockAddress($address->ip);
				return true;
			}
		}else{
			$ipSec[$address->ip] = 1;
		}
		$this->setArrToTable(Raknet::TABLE_IP_SEC, $ipSec);

		try{
			$pid = ord($buffer{0});

			$session = $this->getSession($address);
			if($session !== null){
				if(($pid & Datagram::BITFLAG_VALID) !== 0){
					if($pid & Datagram::BITFLAG_ACK){
						$session->handlePacket(new ACK($buffer));
					}elseif($pid & Datagram::BITFLAG_NAK){
						$session->handlePacket(new NACK($buffer));
					}else{
						$session->handlePacket(new Datagram($buffer));
					}
				}else{
					Logger::debug("Ignored unconnected packet from $address due to session already opened (0x" . dechex($pid) . ")");
				}
			}elseif(($pk = $this->getPacketFromPool($pid, $buffer)) instanceof OfflineMessage){
				/** @var OfflineMessage $pk */

				do{
					try{
						$pk->decode();
						if(!$pk->isValid()){
							throw new \InvalidArgumentException("Packet magic is invalid");
						}
					}catch(\Throwable $e){
						Logger::debug("Received garbage message from $address (" . $e->getMessage() . "): " . bin2hex($pk->buffer));
						Logger::logException($e);
						$this->blockAddress($address->ip, 5);
						break;
					}

					if(!$this->offlineMessageHandler->handle($pk, $address)){
						Logger::debug("Unhandled unconnected packet " . get_class($pk) . " received from $address");
					}
				}while(false);
			}elseif(($pid & Datagram::BITFLAG_VALID) !== 0 and ($pid & 0x03) === 0){
				// Loose datagram, don't relay it as a raw packet
				// RakNet does not currently use the 0x02 or 0x01 bitflags on any datagram header, so we can use
				// this to identify the difference between loose datagrams and packets like Query.
				Logger::debug("Ignored connected packet from $address due to no session opened (0x" . dechex($pid) . ")");
			}else{
				$this->streamRaw($address, $buffer);
			}
		}catch(\Throwable $e){
			Logger::debug("Packet from $address (" . strlen($buffer) . " bytes): 0x" . bin2hex($buffer));
			Logger::logException($e);
			$this->blockAddress($address->ip, 5);
		}

		return true;
	}

	public function sendPacket(Packet $packet, InternetAddress $address) : void{
		$packet->encode();
		$this->server->sendto($address->ip, $address->port, $packet->buffer);
	}

	public function streamEncapsulated(Session $session, EncapsulatedPacket $packet, int $flags = Properties::PRIORITY_NORMAL) : void{
		$id = $session->getAddress()->toString();
		$buffer = chr(Properties::PACKET_ENCAPSULATED) . chr(strlen($id)) . $id . chr($flags) . $packet->toInternalBinary();
		$this->rChan->push($buffer);
	}

	public function streamRaw(InternetAddress $source, string $payload) : void{
		$buffer = chr(Properties::PACKET_RAW) . chr(strlen($source->ip)) . $source->ip . Binary::writeShort($source->port) . $payload;
		$this->rChan->push($buffer);
	}

	protected function streamClose(string $identifier, string $reason) : void{
		$buffer = chr(Properties::PACKET_CLOSE_SESSION) . chr(strlen($identifier)) . $identifier . chr(strlen($reason)) . $reason;
		$this->rChan->push($buffer);
	}

	protected function streamInvalid(string $identifier) : void{
		$buffer = chr(Properties::PACKET_INVALID_SESSION) . chr(strlen($identifier)) . $identifier;
		$this->rChan->push($buffer);
	}

	protected function streamOpen(Session $session) : void{
		$address = $session->getAddress();
		$identifier = $address->toString();
		$buffer = chr(Properties::PACKET_OPEN_SESSION) . chr(strlen($identifier)) . $identifier . chr(strlen($address->ip)) . $address->ip . Binary::writeShort($address->port) . Binary::writeLong($session->getID());
		$this->rChan->push($buffer);
	}

	protected function streamACK(string $identifier, int $identifierACK) : void{
		$buffer = chr(Properties::PACKET_ACK_NOTIFICATION) . chr(strlen($identifier)) . $identifier . Binary::writeInt($identifierACK);
		$this->rChan->push($buffer);
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	protected function streamOption(string $name, $value) : void{
		$buffer = chr(Properties::PACKET_SET_OPTION) . chr(strlen($name)) . $name . $value;
		$this->rChan->push($buffer);
	}

	public function streamPingMeasure(Session $session, int $pingMS) : void{
		$identifier = $session->getAddress()->toString();
		$buffer = chr(Properties::PACKET_REPORT_PING) . chr(strlen($identifier)) . $identifier . Binary::writeInt($pingMS);
		$this->rChan->push($buffer);
	}

	public function receiveStream() : bool{
		if(($packet = $this->kChan->pop()) !== false){
			$id = ord($packet{0});
			$offset = 1;
			if($id === Properties::PACKET_ENCAPSULATED){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				$offset += $len;
				$session = $this->sessions[$identifier] ?? null;
				if($session !== null and $session->isConnected()){
					$flags = ord($packet{$offset++});
					$buffer = substr($packet, $offset);
					$session->addEncapsulatedToQueue(EncapsulatedPacket::fromInternalBinary($buffer), $flags);
				}else{
					$this->streamInvalid($identifier);
				}
			}elseif($id === Properties::PACKET_RAW){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$port = Binary::readShort(substr($packet, $offset, 2));
				$offset += 2;
				$payload = substr($packet, $offset);
				$this->server->sendto($address, $port, $payload);
			}elseif($id === Properties::PACKET_CLOSE_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				if(isset($this->sessions[$identifier])){
					$this->sessions[$identifier]->flagForDisconnection();
				}else{
					$this->streamInvalid($identifier);
				}
			}elseif($id === Properties::PACKET_INVALID_SESSION){
				$len = ord($packet{$offset++});
				$identifier = substr($packet, $offset, $len);
				if(isset($this->sessions[$identifier])){
					$this->removeSession($this->sessions[$identifier]);
				}
			}elseif($id === Properties::PACKET_SET_OPTION){
				$len = ord($packet{$offset++});
				$name = substr($packet, $offset, $len);
				$offset += $len;
				$value = substr($packet, $offset);
				switch($name){
					case "name":
						$this->setToTable([Raknet::TABLE_SERVER_NAME => $name]);
						break;
					case "portChecking":
						$this->setToTable([Raknet::TABLE_PORT_CHECKING => ((bool) $value) ? 1 : 0]);
						break;
					case "packetLimit":
						$this->setToTable([Raknet::TABLE_PACKET_LIMIT => (int) $value]);
						break;
				}
			}elseif($id === Properties::PACKET_BLOCK_ADDRESS){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$offset += $len;
				$timeout = Binary::readInt(substr($packet, $offset, 4));
				$this->blockAddress($address, $timeout);
			}elseif($id === Properties::PACKET_UNBLOCK_ADDRESS){
				$len = ord($packet{$offset++});
				$address = substr($packet, $offset, $len);
				$this->unblockAddress($address);
			}elseif($id === Properties::PACKET_SHUTDOWN){
				foreach($this->sessions as $session){
					$this->removeSession($session);
				}

				//$this->socket->close();
				$this->shutdown = true;
			}elseif($id === Properties::PACKET_EMERGENCY_SHUTDOWN){
				$this->shutdown = true;
			}else{
				Logger::debug("Unknown RakLib internal packet (ID 0x" . dechex($id) . ") received from main thread");
			}

			return true;
		}

		return false;
	}

	public function blockAddress(string $address, int $timeout = 300) : void{
		$final = time() + $timeout;
		if(!$this->block->exist($address) or $timeout === -1){
			if($timeout === -1){
				$final = PHP_INT_MAX;
			}else{
				Logger::notice("Blocked $address for $timeout seconds");
			}
			$this->block->set($address, [self::TABLE_BLOCK_TIMEOUT => $final]);
		}elseif($this->block->get($address, self::TABLE_BLOCK_TIMEOUT) < $final){
			$this->block->set($address, [self::TABLE_BLOCK_TIMEOUT => $final]);
		}
	}

	public function unblockAddress(string $address) : void{
		$this->block->del($address);
		Logger::debug("Unblocked $address");
	}

	/**
	 * @param InternetAddress $address
	 *
	 * @return Session|null
	 */
	public function getSession(InternetAddress $address) : ?Session{
		return $this->sessions[$address->toString()] ?? null;
	}

	public function sessionExists(InternetAddress $address) : bool{
		return isset($this->sessions[$address->toString()]);
	}

	public function createSession(InternetAddress $address, int $clientId, int $mtuSize) : Session{
		$this->checkSessions();

		$this->sessions[$address->toString()] = $session = new Session($this, clone $address, $clientId, $mtuSize);
		Logger::debug("Created session for $address with MTU size $mtuSize");

		return $session;
	}

	public function removeSession(Session $session, string $reason = "unknown") : void{
		$id = $session->getAddress()->toString();
		if(isset($this->sessions[$id])){
			$this->sessions[$id]->close();
			$this->removeSessionInternal($session);
			$this->streamClose($id, $reason);
		}
	}

	public function removeSessionInternal(Session $session) : void{
		unset($this->sessions[$session->getAddress()->toString()]);
	}

	public function openSession(Session $session) : void{
		$this->streamOpen($session);
	}

	private function checkSessions() : void{
		if(count($this->sessions) > 4096){
			foreach($this->sessions as $i => $s){
				if($s->isTemporal()){
					unset($this->sessions[$i]);
					if(count($this->sessions) <= 4096){
						break;
					}
				}
			}
		}
	}

	public function notifyACK(Session $session, int $identifierACK) : void{
		$this->streamACK($session->getAddress()->toString(), $identifierACK);
	}

	public function getName() : string{
		return $this->table->get(Raknet::TABLE_MAIN_KEY, Raknet::TABLE_SERVER_NAME);
	}

	public function getId() : int{
		return $this->table->get(Raknet::TABLE_MAIN_KEY, Raknet::TABLE_SERVER_ID);
	}

	/**
	 * @param int $id
	 * @param string $class
	 */
	private function registerPacket(int $id, string $class) : void{
		$this->packetPool[$id] = new $class;
	}

	/**
	 * @param int $id
	 * @param string $buffer
	 *
	 * @return Packet|null
	 */
	public function getPacketFromPool(int $id, string $buffer = "") : ?Packet{
		$pk = $this->packetPool[$id];
		if($pk !== null){
			$pk = clone $pk;
			$pk->buffer = $buffer;
			return $pk;
		}

		return null;
	}

	private function registerPackets() : void{
		$this->packetPool = new \SplFixedArray(256);

		$this->registerPacket(UnconnectedPing::$ID, UnconnectedPing::class);
		$this->registerPacket(UnconnectedPingOpenConnections::$ID, UnconnectedPingOpenConnections::class);
		$this->registerPacket(OpenConnectionRequest1::$ID, OpenConnectionRequest1::class);
		$this->registerPacket(OpenConnectionReply1::$ID, OpenConnectionReply1::class);
		$this->registerPacket(OpenConnectionRequest2::$ID, OpenConnectionRequest2::class);
		$this->registerPacket(OpenConnectionReply2::$ID, OpenConnectionReply2::class);
		$this->registerPacket(UnconnectedPong::$ID, UnconnectedPong::class);
		$this->registerPacket(AdvertiseSystem::$ID, AdvertiseSystem::class);
	}
}
