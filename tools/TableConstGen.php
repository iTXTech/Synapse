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

require_once "../sf/autoload.php";

use iTXTech\SimpleFramework\Console\Logger;
use iTXTech\SimpleFramework\Util\StringUtil;

Initializer::initTerminal(true);

$gen = <<<EOT
	/** @var int */
	private \$messageIndex = 0;

	/** @var int[] */
	private \$sendOrderedIndex;
	/** @var int[] */
	private \$sendSequencedIndex;
	/** @var int[] */
	private \$receiveOrderedIndex;
	/** @var int[] */
	private \$receiveSequencedHighestIndex;
	/** @var EncapsulatedPacket[][] */
	private \$receiveOrderedPackets;

	/** @var SessionManager */
	private \$sessionManager;

	/** @var InternetAddress */
	private \$address;

	/** @var int */
	private \$state = self::STATE_CONNECTING;
	/** @var int */
	private \$mtuSize;
	/** @var int */
	private \$id;
	/** @var int */
	private \$splitID = 0;

	/** @var int */
	private \$sendSeqNumber = 0;

	/** @var float */
	private \$lastUpdate;
	/** @var float|null */
	private \$disconnectionTime;

	/** @var bool */
	private \$isTemporal = true;

	/** @var Datagram[] */
	private \$packetToSend = [];
	/** @var bool */
	private \$isActive = false;

	/** @var int[] */
	private \$ackQueue = [];
	/** @var int[] */
	private \$nackQueue = [];

	/** @var Datagram[] */
	private \$recoveryQueue = [];

	/** @var Datagram[][] */
	private \$splitPackets = [];

	/** @var int[][] */
	private \$needAck = [];

	/** @var Datagram */
	private \$sendQueue;

	/** @var int */
	private \$windowStart;
	/** @var int */
	private \$windowEnd;
	/** @var int */
	private \$highestSeqNumberThisTick = -1;

	/** @var int */
	private \$reliableWindowStart;
	/** @var int */
	private \$reliableWindowEnd;
	/** @var bool[] */
	private \$reliableWindow = [];

	/** @var float */
	private \$lastPingTime = -1;
	/** @var int */
	private \$lastPingMeasure = 1;

EOT;

$gen = explode("\n", $gen);
foreach($gen as $line){
	$line = trim($line);
	if($line === "" or StringUtil::startsWith($line, "/**")){
		continue;
	}
	$name = str_replace(["$", ";"], "", explode(" ", $line)[1]);
	echo(generate($name) . PHP_EOL);
}

function generate($string){
	$parts = [];
	$index = 0;
	$parts[$index] = "";
	$value = $string{0};
	for($i = 0; $i < strlen($string); $i++){
		if(isUpperCase($string{$i})){
			$index++;
			$parts[$index] = "";
			$value .= strtolower($string{$i});
		}
		$parts[$index] .= strtoupper($string{$i});
	}
	return "public const TABLE_" . implode("_", $parts) . " = \"$value\";";
}

function isUpperCase($chr) : bool{
	$str = ord($chr);
	if($str > 64 && $str < 91){
		return true;
	}
	return false;
}