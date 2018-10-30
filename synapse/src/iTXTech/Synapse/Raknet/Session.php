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

use iTXTech\Synapse\Raknet\Properties;
use iTXTech\SimpleFramework\Console\Logger;
use iTXTech\Synapse\Raknet\Protocol\ACK;
use iTXTech\Synapse\Raknet\Protocol\ConnectedPing;
use iTXTech\Synapse\Raknet\Protocol\ConnectedPong;
use iTXTech\Synapse\Raknet\Protocol\ConnectionRequest;
use iTXTech\Synapse\Raknet\Protocol\ConnectionRequestAccepted;
use iTXTech\Synapse\Raknet\Protocol\Datagram;
use iTXTech\Synapse\Raknet\Protocol\DisconnectionNotification;
use iTXTech\Synapse\Raknet\Protocol\EncapsulatedPacket;
use iTXTech\Synapse\Raknet\Protocol\MessageIdentifiers;
use iTXTech\Synapse\Raknet\Protocol\NACK;
use iTXTech\Synapse\Raknet\Protocol\NewIncomingConnection;
use iTXTech\Synapse\Raknet\Protocol\Packet;
use iTXTech\Synapse\Raknet\Protocol\PacketReliability;
use iTXTech\Synapse\Util\InternetAddress;
use iTXTech\Synapse\Util\TableHelper;
use Swoole\Lock;
use Swoole\Table;

class Session{
	public const STATE_CONNECTING = 0;
	public const STATE_CONNECTED = 1;
	public const STATE_DISCONNECTING = 2;
	public const STATE_DISCONNECTED = 3;

	private const MAX_SPLIT_SIZE = 128;
	private const MAX_SPLIT_COUNT = 4;

	private const CHANNEL_COUNT = 32;

	public const WINDOW_SIZE = 2048;

	public const TABLE_SESSION_OBJECT = "obj";
	public const TABLE_SESSION_USING = "su";
	public const TABLE_ADDRESS = "a";
	public const TABLE_STATE = "s";
	public const TABLE_MTU_SIZE = "ms";
	public const TABLE_ID = "i";
	public const TABLE_IS_ACTIVE = "ia";
	public const TABLE_IS_TEMPORAL = "it";
	public const TABLE_LOCK_ID = "lid";
	public const TABLE_LAST_UPDATE = "lu";

	/** @var int */
	private $messageIndex = 0;

	/** @var int[] */
	private $sendOrderedIndex;
	/** @var int[] */
	private $sendSequencedIndex;
	/** @var int[] */
	private $receiveOrderedIndex;
	/** @var int[] */
	private $receiveSequencedHighestIndex;
	/** @var EncapsulatedPacket[][] */
	private $receiveOrderedPackets;

	/** @var InternetAddress */
	private $address;

	/** @var int */
	private $state = self::STATE_CONNECTING;
	/** @var int */
	private $mtuSize;
	/** @var int */
	private $splitID = 0;

	/** @var int */
	private $sendSeqNumber = 0;

	/** @var float */
	private $lastReceive;
	/** @var float|null */
	private $disconnectionTime;

	/** @var Datagram[] */
	private $packetToSend = [];

	/** @var int[] */
	private $ACKQueue = [];
	/** @var int[] */
	private $NACKQueue = [];

	/** @var Datagram[] */
	private $recoveryQueue = [];

	/** @var Datagram[][] */
	private $splitPackets = [];

	/** @var int[][] */
	private $needACK = [];

	/** @var Datagram */
	private $sendQueue;

	/** @var int */
	private $windowStart;
	/** @var int */
	private $windowEnd;
	/** @var int */
	private $highestSeqNumberThisTick = -1;

	/** @var int */
	private $reliableWindowStart;
	/** @var int */
	private $reliableWindowEnd;
	/** @var bool[] */
	private $reliableWindow = [];

	/** @var float */
	private $lastPingTime = -1;
	/** @var int */
	private $lastPingMeasure = 1;

	public static function getStructure(int $clientId = 0){
		return [
			self::TABLE_ADDRESS => [Table::TYPE_STRING, "", 128],
			self::TABLE_STATE => [Table::TYPE_INT, self::STATE_CONNECTING],
			self::TABLE_ID => [Table::TYPE_INT, $clientId],
			self::TABLE_IS_TEMPORAL => [Table::TYPE_INT, 1],//true
			self::TABLE_IS_ACTIVE => [Table::TYPE_INT, 0],//false
			self::TABLE_SESSION_OBJECT => [Table::TYPE_STRING, "", PHP_INT_MAX],
			self::TABLE_SESSION_USING => [Table::TYPE_INT, 0],//false
			self::TABLE_LOCK_ID => [Table::TYPE_INT, 0],
			self::TABLE_LAST_UPDATE => [Table::TYPE_FLOAT, microtime(true)],
		];
	}

	public static function createObject(SessionManager $manager, InternetAddress $address, int $mtuSize){
		$manager->assignLock($address->toString());
		TableHelper::putObject($manager->sessions, $address->toString(),
			self::TABLE_SESSION_OBJECT, new Session($address, $mtuSize));
	}

	public static function getLock(SessionManager $manager, string $k) : ?Lock{
		return $manager->getLock($manager->sessions->get($k, self::TABLE_LOCK_ID));
	}

	public static function getId(Table $table, string $k) : int{
		return $table->get($k, self::TABLE_ID);
	}

	public static function isTemporal(Table $table, string $k) : bool {
		return TableHelper::getBool($table, $k, self::TABLE_IS_TEMPORAL);
	}

	public static function isConnected(Table $table, string $k) : bool {
		$state = $table->get($k, self::TABLE_STATE);
		return $state !== self::STATE_DISCONNECTING and $state !== self::STATE_DISCONNECTED;
	}

	public static function getAddress(Table $table, string $k) : InternetAddress{
		return TableHelper::getObject($table, $k, self::TABLE_ADDRESS);
	}

	public static function getLastUpdate(Table $table, string $k) : float {
		return $table->get($k, self::TABLE_LAST_UPDATE);
	}

	public static function prepareSession(SessionManager $manager, string $k) : ?Session{
		$lock = self::getLock($manager, $k);
		if($lock === null){
			return null;
		}
		$lock->lock();
		$session = TableHelper::getObject($manager->sessions, $k, self::TABLE_SESSION_OBJECT);
		if(!$session instanceof Session){
			return null;
		}
		return $session;
	}

	public static function storeSession(SessionManager $manager, Session $session) : bool {
		if($session->state !== self::STATE_DISCONNECTED){
			$lock = self::getLock($manager, $session->getIdentifier());
			if($lock === null){
				return false;
			}
			$lock->unlock();
			TableHelper::putObject($manager->sessions, $session->getIdentifier(), self::TABLE_SESSION_OBJECT, $session);
			return true;
		}
		return false;
	}

	public function __construct(InternetAddress $address, int $mtuSize = 0){
		$this->sendQueue = new Datagram();

		$this->windowStart = 0;
		$this->windowEnd = self::WINDOW_SIZE;

		$this->reliableWindowStart = 0;
		$this->reliableWindowEnd = self::WINDOW_SIZE;

		$this->sendOrderedIndex = array_fill(0, self::CHANNEL_COUNT, 0);
		$this->sendSequencedIndex = array_fill(0, self::CHANNEL_COUNT, 0);

		$this->receiveOrderedIndex = array_fill(0, self::CHANNEL_COUNT, 0);
		$this->receiveSequencedHighestIndex = array_fill(0, self::CHANNEL_COUNT, 0);

		$this->receiveOrderedPackets = array_fill(0, self::CHANNEL_COUNT, []);

		$this->mtuSize = $mtuSize;
		$this->address = $address;
		$this->lastReceive = 0;
	}

	public function getIdentifier() : string{
		return $this->address->toString();
	}

	public function update(SessionManager $manager, float $time) : void{
		if(!TableHelper::getBool($manager->sessions, $this->getIdentifier(), self::TABLE_IS_ACTIVE)
			and ($this->lastReceive + 10) < $time){
			$manager->removeSession($this, "timeout");

			return;
		}

		if($this->state === self::STATE_DISCONNECTING and (
				(empty($this->ACKQueue) and empty($this->NACKQueue) and empty($this->packetToSend) and empty($this->recoveryQueue)) or
				$this->disconnectionTime + 10 < $time)
		){
			$this->close($manager);
			return;
		}

		$manager->sessions->set($this->getIdentifier(), [self::TABLE_LAST_UPDATE => microtime(true)]);
		TableHelper::putBool($manager->sessions, $this->getIdentifier(), self::TABLE_IS_ACTIVE, false);

		$diff = $this->highestSeqNumberThisTick - $this->windowStart + 1;
		assert($diff >= 0);
		if($diff > 0){
			//Move the receive window to account for packets we either received or are about to NACK
			//we ignore any sequence numbers that we sent NACKs for, because we expect the client to resend them
			//when it gets a NACK for it

			$this->windowStart += $diff;
			$this->windowEnd += $diff;
		}

		if(count($this->ACKQueue) > 0){
			$pk = new ACK();
			$pk->packets = $this->ACKQueue;
			$this->sendPacket($manager, $pk);
			$this->ACKQueue = [];
		}

		if(count($this->NACKQueue) > 0){
			$pk = new NACK();
			$pk->packets = $this->NACKQueue;
			$this->sendPacket($manager, $pk);
			$this->NACKQueue = [];
		}

		if(count($this->packetToSend) > 0){
			$limit = 16;
			foreach($this->packetToSend as $k => $pk){
				$this->sendDatagram($manager, $pk);
				unset($this->packetToSend[$k]);

				if(--$limit <= 0){
					break;
				}
			}

			if(count($this->packetToSend) > self::WINDOW_SIZE){
				$this->packetToSend = [];
			}
		}

		if(count($this->needACK) > 0){
			foreach($this->needACK as $identifierACK => $indexes){
				if(count($indexes) === 0){
					unset($this->needACK[$identifierACK]);
					$manager->notifyACK($this, $identifierACK);
				}
			}
		}


		foreach($this->recoveryQueue as $seq => $pk){
			if($pk->sendTime < (time() - 8)){
				$this->packetToSend[] = $pk;
				unset($this->recoveryQueue[$seq]);
			}else{
				break;
			}
		}

		if($this->lastPingTime + 5 < $time){
			$this->sendPing($manager);
			$this->lastPingTime = $time;
		}

		$this->sendQueue($manager);
	}

	private function sendDatagram(SessionManager $manager, Datagram $datagram) : void{
		if($datagram->seqNumber !== null){
			unset($this->recoveryQueue[$datagram->seqNumber]);
		}
		$datagram->seqNumber = $this->sendSeqNumber++;
		$datagram->sendTime = microtime(true);
		$this->recoveryQueue[$datagram->seqNumber] = $datagram;
		$this->sendPacket($manager, $datagram);
	}

	private function queueConnectedPacket(SessionManager $manager, Packet $packet, int $reliability,
	                                      int $orderChannel, int $flags = Properties::PRIORITY_NORMAL) : void{
		$packet->encode();

		$encapsulated = new EncapsulatedPacket();
		$encapsulated->reliability = $reliability;
		$encapsulated->orderChannel = $orderChannel;
		$encapsulated->buffer = $packet->buffer;

		$this->addEncapsulatedToQueue($manager, $encapsulated, $flags);
	}

	private function sendPacket(SessionManager $manager, Packet $packet) : void{
		$manager->sendPacket($packet, $this->address);
	}

	public function sendQueue(SessionManager $manager) : void{
		if(count($this->sendQueue->packets) > 0){
			$this->sendDatagram($manager, $this->sendQueue);
			$this->sendQueue = new Datagram();
		}
	}

	private function sendPing(SessionManager $manager, int $reliability = PacketReliability::UNRELIABLE) : void{
		$pk = new ConnectedPing();
		$pk->sendPingTime = $manager->getRakNetTimeMS();
		$this->queueConnectedPacket($manager, $pk, $reliability, 0, Properties::PRIORITY_IMMEDIATE);
	}

	private function addToQueue(SessionManager $manager, EncapsulatedPacket $pk, int $flags = Properties::PRIORITY_NORMAL) : void{
		$priority = $flags & 0b00000111;
		if($pk->needACK and $pk->messageIndex !== null){
			$this->needACK[$pk->identifierACK][$pk->messageIndex] = $pk->messageIndex;
		}

		$length = $this->sendQueue->length();
		if($length + $pk->getTotalLength() > $this->mtuSize - 36){ //IP header (20 bytes) + UDP header (8 bytes) + RakNet weird (8 bytes) = 36 bytes
			$this->sendQueue($manager);
		}

		if($pk->needACK){
			$this->sendQueue->packets[] = clone $pk;
			$pk->needACK = false;
		}else{
			$this->sendQueue->packets[] = $pk->toBinary();
		}

		if($priority === Properties::PRIORITY_IMMEDIATE){
			// Forces pending sends to go out now, rather than waiting to the next update interval
			$this->sendQueue($manager);
		}
	}

	public function addEncapsulatedToQueue(SessionManager $manager, EncapsulatedPacket $packet, int $flags = Properties::PRIORITY_NORMAL) : void{
		if(($packet->needACK = ($flags & Properties::FLAG_NEED_ACK) > 0) === true){
			$this->needACK[$packet->identifierACK] = [];
		}

		if(PacketReliability::isOrdered($packet->reliability)){
			$packet->orderIndex = $this->sendOrderedIndex[$packet->orderChannel]++;
		}elseif(PacketReliability::isSequenced($packet->reliability)){
			$packet->orderIndex = $this->sendOrderedIndex[$packet->orderChannel]; //sequenced packets don't increment the ordered channel index
			$packet->sequenceIndex = $this->sendSequencedIndex[$packet->orderChannel]++;
		}

		//IP header size (20 bytes) + UDP header size (8 bytes) + RakNet weird (8 bytes) + datagram header size (4 bytes) + max encapsulated packet header size (20 bytes)
		$maxSize = $this->mtuSize - 60;

		if(strlen($packet->buffer) > $maxSize){
			$buffers = str_split($packet->buffer, $maxSize);
			$bufferCount = count($buffers);

			$splitID = ++$this->splitID % 65536;
			foreach($buffers as $count => $buffer){
				$pk = new EncapsulatedPacket();
				$pk->splitID = $splitID;
				$pk->hasSplit = true;
				$pk->splitCount = $bufferCount;
				$pk->reliability = $packet->reliability;
				$pk->splitIndex = $count;
				$pk->buffer = $buffer;

				if(PacketReliability::isReliable($pk->reliability)){
					$pk->messageIndex = $this->messageIndex++;
				}

				$pk->sequenceIndex = $packet->sequenceIndex;
				$pk->orderChannel = $packet->orderChannel;
				$pk->orderIndex = $packet->orderIndex;

				$this->addToQueue($manager, $pk, $flags | Properties::PRIORITY_IMMEDIATE);
			}
		}else{
			if(PacketReliability::isReliable($packet->reliability)){
				$packet->messageIndex = $this->messageIndex++;
			}
			$this->addToQueue($manager, $packet, $flags);
		}
	}

	/**
	 * Processes a split part of an encapsulated packet.
	 *
	 * @param EncapsulatedPacket $packet
	 *
	 * @return null|EncapsulatedPacket Reassembled packet if we have all the parts, null otherwise.
	 */
	private function handleSplit(EncapsulatedPacket $packet) : ?EncapsulatedPacket{
		if($packet->splitCount >= self::MAX_SPLIT_SIZE or $packet->splitIndex >= self::MAX_SPLIT_SIZE or $packet->splitIndex < 0){
			Logger::debug("Invalid split packet part from " . $this->address . ", too many parts or invalid split index (part index $packet->splitIndex, part count $packet->splitCount)");
			return null;
		}

		//TODO: this needs to be more strict about split packet part validity

		if(!isset($this->splitPackets[$packet->splitID])){
			if(count($this->splitPackets) >= self::MAX_SPLIT_COUNT){
				Logger::debug("Ignored split packet part from " . $this->address . " because reached concurrent split packet limit of " . self::MAX_SPLIT_COUNT);
				return null;
			}
			$this->splitPackets[$packet->splitID] = [$packet->splitIndex => $packet];
		}else{
			$this->splitPackets[$packet->splitID][$packet->splitIndex] = $packet;
		}

		if(count($this->splitPackets[$packet->splitID]) === $packet->splitCount){ //got all parts, reassemble the packet
			$pk = new EncapsulatedPacket();
			$pk->buffer = "";

			$pk->reliability = $packet->reliability;
			$pk->messageIndex = $packet->messageIndex;
			$pk->sequenceIndex = $packet->sequenceIndex;
			$pk->orderIndex = $packet->orderIndex;
			$pk->orderChannel = $packet->orderChannel;

			for($i = 0; $i < $packet->splitCount; ++$i){
				$pk->buffer .= $this->splitPackets[$packet->splitID][$i]->buffer;
			}

			$pk->length = strlen($pk->buffer);
			unset($this->splitPackets[$packet->splitID]);

			return $pk;
		}

		return null;
	}

	private function handleEncapsulatedPacket(SessionManager $manager, EncapsulatedPacket $packet) : void{
		if($packet->messageIndex !== null){
			//check for duplicates or out of range
			if($packet->messageIndex < $this->reliableWindowStart or $packet->messageIndex > $this->reliableWindowEnd or isset($this->reliableWindow[$packet->messageIndex])){
				return;
			}

			$this->reliableWindow[$packet->messageIndex] = true;

			if($packet->messageIndex === $this->reliableWindowStart){
				for(; isset($this->reliableWindow[$this->reliableWindowStart]); ++$this->reliableWindowStart){
					unset($this->reliableWindow[$this->reliableWindowStart]);
					++$this->reliableWindowEnd;
				}
			}
		}

		if($packet->hasSplit and ($packet = $this->handleSplit($packet)) === null){
			return;
		}

		if(PacketReliability::isSequenced($packet->reliability)){
			if($packet->sequenceIndex < $this->receiveSequencedHighestIndex[$packet->orderChannel] or $packet->orderIndex < $this->receiveOrderedIndex[$packet->orderChannel]){
				//too old sequenced packet, discard it
				return;
			}

			$this->receiveSequencedHighestIndex[$packet->orderChannel] = $packet->sequenceIndex + 1;
			$this->handleEncapsulatedPacketRoute($manager, $packet);
		}elseif(PacketReliability::isOrdered($packet->reliability)){
			if($packet->orderIndex === $this->receiveOrderedIndex[$packet->orderChannel]){
				//this is the packet we expected to get next
				//Any ordered packet resets the sequence index to zero, so that sequenced packets older than this ordered
				//one get discarded. Sequenced packets also include (but don't increment) the order index, so a sequenced
				//packet with an order index less than this will get discarded
				$this->receiveSequencedHighestIndex[$packet->orderIndex] = 0;
				$this->receiveOrderedIndex[$packet->orderChannel] = $packet->orderIndex + 1;

				$this->handleEncapsulatedPacketRoute($manager, $packet);
				for($i = $this->receiveOrderedIndex[$packet->orderChannel]; isset($this->receiveOrderedPackets[$packet->orderChannel][$i]); ++$i){
					$this->handleEncapsulatedPacketRoute($manager, $this->receiveOrderedPackets[$packet->orderChannel][$i]);
					unset($this->receiveOrderedPackets[$packet->orderChannel][$i]);
				}

				$this->receiveOrderedIndex[$packet->orderChannel] = $i;
			}elseif($packet->orderIndex > $this->receiveOrderedIndex[$packet->orderChannel]){
				$this->receiveOrderedPackets[$packet->orderChannel][$packet->orderIndex] = $packet;
			}/*else{
				//duplicate/already received packet
			}*/
		}else{
			//not ordered or sequenced
			$this->handleEncapsulatedPacketRoute($manager, $packet);
		}
	}


	private function handleEncapsulatedPacketRoute(SessionManager $manager, EncapsulatedPacket $packet) : void{
		$id = ord($packet->buffer{0});
		if($id < MessageIdentifiers::ID_USER_PACKET_ENUM){ //internal data packet
			if($this->state === self::STATE_CONNECTING){
				if($id === ConnectionRequest::$ID){
					$dataPacket = new ConnectionRequest($packet->buffer);
					$dataPacket->decode();

					$pk = new ConnectionRequestAccepted;
					$pk->address = $this->address;
					$pk->sendPingTime = $dataPacket->sendPingTime;
					$pk->sendPongTime = $manager->getRakNetTimeMS();
					$this->queueConnectedPacket($manager, $pk, PacketReliability::UNRELIABLE, 0, Properties::PRIORITY_IMMEDIATE);
				}elseif($id === NewIncomingConnection::$ID){
					$dataPacket = new NewIncomingConnection($packet->buffer);
					$dataPacket->decode();

					if($dataPacket->address->port === $manager->getPort() or !$manager->isPortChecking()){
						$this->state = self::STATE_CONNECTED; //FINALLY!
						TableHelper::putBool($manager->sessions, $this->getIdentifier(), self::TABLE_IS_TEMPORAL, false);
						$manager->openSession($this->address);

						//$this->handlePong($dataPacket->sendPingTime, $dataPacket->sendPongTime); //can't use this due to system-address count issues in MCPE >.<
						$this->sendPing($manager);
					}
				}
			}elseif($id === DisconnectionNotification::$ID){
				//TODO: we're supposed to send an ACK for this, but currently we're just deleting the session straight away
				$manager->removeSession($this, "client disconnect");
			}elseif($id === ConnectedPing::$ID){
				$dataPacket = new ConnectedPing($packet->buffer);
				$dataPacket->decode();

				$pk = new ConnectedPong;
				$pk->sendPingTime = $dataPacket->sendPingTime;
				$pk->sendPongTime = $manager->getRakNetTimeMS();
				$this->queueConnectedPacket($manager, $pk, PacketReliability::UNRELIABLE, 0);
			}elseif($id === ConnectedPong::$ID){
				$dataPacket = new ConnectedPong($packet->buffer);
				$dataPacket->decode();

				$this->handlePong($manager, $dataPacket->sendPingTime, $dataPacket->sendPongTime);
			}
		}elseif($this->state === self::STATE_CONNECTED){
			$manager->streamEncapsulated($this, $packet);
		}/*else{
			//$this->sessionManager->getLogger()->notice("Received packet before connection: " . bin2hex($packet->buffer));
		}*/
	}

	private function handlePong(SessionManager $manager, int $sendPingTime, int $sendPongTime) : void{
		$this->lastPingMeasure = $manager->getRakNetTimeMS() - $sendPingTime;
		$manager->streamPingMeasure($this, $this->lastPingMeasure);
	}

	public function handlePacket(SessionManager $manager, Packet $packet) : void{
		TableHelper::putBool($manager->sessions, $this->getIdentifier(), self::TABLE_IS_ACTIVE, true);
		$this->lastReceive = microtime(true);

		if($packet instanceof Datagram){ //In reality, ALL of these packets are datagrams.
			$packet->decode();

			if($packet->seqNumber < $this->windowStart or $packet->seqNumber > $this->windowEnd or isset($this->ACKQueue[$packet->seqNumber])){
				Logger::debug("Received duplicate or out-of-window packet from " . $this->address . " (sequence number $packet->seqNumber, window " . $this->windowStart . "-" . $this->windowEnd . ")");
				return;
			}

			unset($this->NACKQueue[$packet->seqNumber]);
			$this->ACKQueue[$packet->seqNumber] = $packet->seqNumber;
			if($this->highestSeqNumberThisTick < $packet->seqNumber){
				$this->highestSeqNumberThisTick = $packet->seqNumber;
			}

			if($packet->seqNumber === $this->windowStart){
				//got a contiguous packet, shift the receive window
				//this packet might complete a sequence of out-of-order packets, so we incrementally check the indexes
				//to see how far to shift the window, and stop as soon as we either find a gap or have an empty window
				for(; isset($this->ACKQueue[$this->windowStart]); ++$this->windowStart){
					++$this->windowEnd;
				}
			}elseif($packet->seqNumber > $this->windowStart){
				//we got a gap - a later packet arrived before earlier ones did
				//we add the earlier ones to the NACK queue
				//if the missing packets arrive before the end of tick, they'll be removed from the NACK queue
				for($i = $this->windowStart; $i < $packet->seqNumber; ++$i){
					if(!isset($this->ACKQueue[$i])){
						$this->NACKQueue[$i] = $i;
					}
				}
			}else{
				assert(false, "received packet before window start");
			}

			foreach($packet->packets as $pk){
				$this->handleEncapsulatedPacket($manager, $pk);
			}
		}else{
			if($packet instanceof ACK){
				$packet->decode();
				foreach($packet->packets as $seq){
					if(isset($this->recoveryQueue[$seq])){
						foreach($this->recoveryQueue[$seq]->packets as $pk){
							if($pk instanceof EncapsulatedPacket and $pk->needACK and $pk->messageIndex !== null){
								unset($this->needACK[$pk->identifierACK][$pk->messageIndex]);
							}
						}
						unset($this->recoveryQueue[$seq]);
					}
				}
			}elseif($packet instanceof NACK){
				$packet->decode();
				foreach($packet->packets as $seq){
					if(isset($this->recoveryQueue[$seq])){
						$this->packetToSend[] = $this->recoveryQueue[$seq];
						unset($this->recoveryQueue[$seq]);
					}
				}
			}
		}
	}

	public function flagForDisconnection() : void{
		$this->state = self::STATE_DISCONNECTING;
		$this->disconnectionTime = microtime(true);
	}

	public function close(SessionManager $manager) : void{
		$manager->freeLock($this->getIdentifier());
		if($this->state !== self::STATE_DISCONNECTED){
			$this->state = self::STATE_DISCONNECTED;

			//TODO: the client will send an ACK for this, but we aren't handling it (debug spam)
			$this->queueConnectedPacket($manager, new DisconnectionNotification(),
				PacketReliability::RELIABLE_ORDERED, 0, Properties::PRIORITY_IMMEDIATE);

			Logger::debug("Closed session for $this->address");
			$manager->removeSessionInternal($this);
		}
	}
}
