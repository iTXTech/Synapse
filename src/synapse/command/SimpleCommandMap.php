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

namespace synapse\command;

use synapse\command\defaults\BanCommand;
use synapse\command\defaults\BanIpCommand;
use synapse\command\defaults\BanListCommand;
use synapse\command\defaults\BiomeCommand;
use synapse\command\defaults\CaveCommand;
use synapse\command\defaults\ChunkInfoCommand;
use synapse\command\defaults\DefaultGamemodeCommand;
use synapse\command\defaults\DeopCommand;
use synapse\command\defaults\DifficultyCommand;
use synapse\command\defaults\DumpMemoryCommand;
use synapse\command\defaults\EffectCommand;
use synapse\command\defaults\EnchantCommand;
use synapse\command\defaults\GamemodeCommand;
use synapse\command\defaults\GarbageCollectorCommand;
use synapse\command\defaults\GiveCommand;
use synapse\command\defaults\HelpCommand;
use synapse\command\defaults\KickCommand;
use synapse\command\defaults\KillCommand;
use synapse\command\defaults\ListCommand;
use synapse\command\defaults\LoadPluginCommand;
use synapse\command\defaults\LvdatCommand;
use synapse\command\defaults\MeCommand;
use synapse\command\defaults\OpCommand;
use synapse\command\defaults\PardonCommand;
use synapse\command\defaults\PardonIpCommand;
use synapse\command\defaults\ParticleCommand;
use synapse\command\defaults\PluginsCommand;
use synapse\command\defaults\ReloadCommand;
use synapse\command\defaults\SaveCommand;
use synapse\command\defaults\SaveOffCommand;
use synapse\command\defaults\SaveOnCommand;
use synapse\command\defaults\SayCommand;
use synapse\command\defaults\SeedCommand;
use synapse\command\defaults\SetBlockCommand;
use synapse\command\defaults\SetWorldSpawnCommand;
use synapse\command\defaults\SpawnpointCommand;
use synapse\command\defaults\StatusCommand;
use synapse\command\defaults\StopCommand;
use synapse\command\defaults\SummonCommand;
use synapse\command\defaults\TeleportCommand;
use synapse\command\defaults\TellCommand;
use synapse\command\defaults\TimeCommand;
use synapse\command\defaults\TimingsCommand;
use synapse\command\defaults\VanillaCommand;
use synapse\command\defaults\VersionCommand;
use synapse\command\defaults\WhitelistCommand;
use synapse\command\defaults\XpCommand;
use synapse\command\defaults\FillCommand;
use synapse\event\TranslationContainer;
use synapse\Player;
use synapse\Server;
use synapse\utils\MainLogger;
use synapse\utils\TextFormat;

use synapse\command\defaults\MakeServerCommand;
use synapse\command\defaults\ExtractPluginCommand;
use synapse\command\defaults\ExtractPharCommand;
use synapse\command\defaults\MakePluginCommand;
use synapse\command\defaults\BancidbynameCommand;
use synapse\command\defaults\BanipbynameCommand;
use synapse\command\defaults\BanCidCommand;
use synapse\command\defaults\PardonCidCommand;
use synapse\command\defaults\WeatherCommand;

class SimpleCommandMap implements CommandMap{

	/**
	 * @var Command[]
	 */
	protected $knownCommands = [];

	/** @var Server */
	private $server;

	public function __construct(Server $server){
		$this->server = $server;
		$this->setDefaultCommands();
	}

	private function setDefaultCommands(){
		$this->register("synapse", new VersionCommand("version"));
		$this->register("synapse", new StopCommand("stop"));
		$this->register("synapse", new StatusCommand("status"));
		//$this->register("synapse", new GarbageCollectorCommand("gc"));
	}


	public function registerAll($fallbackPrefix, array $commands){
		foreach($commands as $command){
			$this->register($fallbackPrefix, $command);
		}
	}

	public function register($fallbackPrefix, Command $command, $label = null){
		if($label === null){
			$label = $command->getName();
		}
		$label = strtolower(trim($label));
		$fallbackPrefix = strtolower(trim($fallbackPrefix));

		$registered = $this->registerAlias($command, false, $fallbackPrefix, $label);
		
		if(!$registered){
			$command->setLabel($fallbackPrefix . ":" . $label);
		}

		$command->register($this);

		return $registered;
	}

	private function registerAlias(Command $command, $isAlias, $fallbackPrefix, $label){
		$this->knownCommands[$fallbackPrefix . ":" . $label] = $command;
		if(($command instanceof VanillaCommand or $isAlias) and isset($this->knownCommands[$label])){
			return false;
		}

		if(isset($this->knownCommands[$label]) and $this->knownCommands[$label]->getLabel() !== null and $this->knownCommands[$label]->getLabel() === $label){
			return false;
		}

		if(!$isAlias){
			$command->setLabel($label);
		}

		$this->knownCommands[$label] = $command;

		return true;
	}

	public function dispatch(CommandSender $sender, $commandLine){
		$args = explode(" ", $commandLine);

		if(count($args) === 0){
			return false;
		}

		$sentCommandLabel = strtolower(array_shift($args));
		$target = $this->getCommand($sentCommandLabel);

		if($target === null){
			return false;
		}

		$target->timings->startTiming();
		try{
			$target->execute($sender, $sentCommandLabel, $args);
		}catch(\Throwable $e){
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.exception"));
			$this->server->getLogger()->critical($this->server->getLanguage()->translateString("synapse.command.exception", [$commandLine, (string) $target, $e->getMessage()]));
			$logger = $sender->getServer()->getLogger();
			if($logger instanceof MainLogger){
				$logger->logException($e);
			}
		}
		$target->timings->stopTiming();

		return true;
	}

	public function clearCommands(){
		foreach($this->knownCommands as $command){
			$command->unregister($this);
		}
		$this->knownCommands = [];
		$this->setDefaultCommands();
	}

	public function getCommand($name){
		if(isset($this->knownCommands[$name])){
			return $this->knownCommands[$name];
		}

		return null;
	}

	/**
	 * @return Command[]
	 */
	public function getCommands(){
		return $this->knownCommands;
	}
}
