<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace synapse\command\defaults;

use synapse\command\CommandSender;
use synapse\event\Timings;
use synapse\scheduler\GarbageCollectionTask;
use synapse\utils\TextFormat;


class GarbageCollectorCommand extends VanillaCommand{

	public function __construct($name){
		parent::__construct(
			$name,
			"%synapse.command.gc.description",
			"%synapse.command.gc.usage"
		);
	}

	public function execute(CommandSender $sender, $currentAlias, array $args){
		Timings::$garbageCollectorTimer->startTiming();

		$size = $sender->getServer()->getScheduler()->getAsyncTaskPoolSize();
		for($i = 0; $i < $size; ++$i){
			$sender->getServer()->getScheduler()->scheduleAsyncTaskToWorker(new GarbageCollectionTask(), $i);
		}

		$sender->sendMessage(TextFormat::GOLD . "Collected cycles: " . gc_collect_cycles());

		Timings::$garbageCollectorTimer->stopTiming();
		return true;
	}
}
