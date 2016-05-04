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
 
namespace synapse;

use synapse\event\server\QueryRegenerateEvent;
use synapse\command\CommandSender;
use synapse\event\Timings;
use synapse\command\ConsoleCommandSender;
use synapse\command\SimpleCommandMap;
use synapse\event\TranslationContainer;
use synapse\lang\BaseLang;
use synapse\command\CommandReader;
use synapse\network\Network;
use synapse\network\query\QueryHandler;
use synapse\network\RakLibInterface;
use synapse\network\rcon\RCON;
use synapse\network\upnp\UPnP;
use synapse\plugin\FolderPluginLoader;
use synapse\plugin\PharPluginLoader;
use synapse\plugin\Plugin;
use synapse\plugin\PluginManager;
use synapse\plugin\ScriptPluginLoader;
use synapse\scheduler\ServerScheduler;
use synapse\utils\Config;
use synapse\utils\MainLogger;
use synapse\utils\ServerException;
use synapse\utils\Terminal;
use synapse\utils\TextFormat;
use synapse\utils\Utils;
use synapse\utils\VersionString;

class Server{
	/** @var Server */
	private static $instance = null;
	/** @var \Threaded */
	private static $sleeper = null;
	private $autoloader;
	/** @var MainLogger */
	private $logger;
	private $filePath;
	private $pluginPath;
	/** @var BaseLang  */
	private $baseLang;
	/** @var Config */
	private $properties;
	/** @var RCON  */
	private $rcon;
	private $serverID;
	/** @var PluginManager */
	private $pluginManager;
	/** @var CommandReader */
	private $console;
	/** @var VersionString */
	private $version;
	/** @var ServerScheduler */
	private $scheduler;
	private $maxPlayers;
	/** @var Player[] */
	private $players = [];
	/** @var QueryHandler */
	private $queryHandler;
	private $tickCounter;
	private $nextTick = 0;
	private $tickAverage = [100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100, 100];
	private $useAverage = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
	private $maxTick = 100;
	private $maxUse = 0;

	private $isRunning = true;

	private $dispatchSignals = false;
	private $identifiers = [];

	public function __construct(\ClassLoader $autoloader, \ThreadedLogger $logger, $filePath, $dataPath, $pluginPath){
		self::$instance = $this;
		self::$sleeper = new \Threaded;
		$this->autoloader = $autoloader;
		$this->logger = $logger;
		$this->filePath = $filePath;
		$this->pluginPath = $pluginPath;
		try{
			if(!file_exists($pluginPath)){
				mkdir($pluginPath, 0777);
			}

			$this->dataPath = realpath($dataPath) . DIRECTORY_SEPARATOR;
			$this->pluginPath = realpath($pluginPath) . DIRECTORY_SEPARATOR;

			$this->console = new CommandReader($logger);

			$version = new VersionString($this->getSynapseVersion());
			$this->version = $version;

			$this->logger->info("Loading server properties...");
			$this->properties = new Config($this->dataPath . "server.properties", Config::PROPERTIES, [
				"motd" => "Minecraft: PE Server",
				"server-port" => 19132,
				"synapse-port" => 10305,
				"password" => mt_rand(0, PHP_INT_MAX),
				"lang" => "eng",
				"async-workers" => "auto",
				"enable-profiling" => false,
				"profile-report-trigger" => 20,
				"max-player" => 20,
				"dynamic-player-count" => false,
				"enable-query" => true,
				"enable-rcon" => false,
				"rcon.password" => substr(base64_encode(@Utils::getRandomBytes(20, false)), 3, 10),
			]);

			$this->baseLang = new BaseLang($this->getConfig("lang", BaseLang::FALLBACK_LANGUAGE));
			$this->logger->info($this->getLanguage()->translateString("language.selected", [$this->getLanguage()->getName(), $this->getLanguage()->getLang()]));

			$this->maxPlayers = $this->getConfig("max-players", 20);
			
			$this->logger->info($this->getLanguage()->translateString("synapse.server.start", [TextFormat::AQUA . $this->getVersion(). TextFormat::WHITE]));

			if(($poolSize = $this->getConfig("async-workers", "auto")) === "auto"){
				$poolSize = ServerScheduler::$WORKERS;
				$processors = Utils::getCoreCount() - 2;

				if($processors > 0){
					$poolSize = max(1, $processors);
				}
			}

			ServerScheduler::$WORKERS = $poolSize;

			$this->scheduler = new ServerScheduler();

			if($this->getConfig("enable-rcon", false) === true){
				$this->rcon = new RCON($this, $this->getConfig("rcon.password", ""), $this->getConfig("rcon.port", $this->getPort()), ($ip = $this->getIp()) != "" ? $ip : "0.0.0.0", $this->getConfigInt("rcon.threads", 1), $this->getConfigInt("rcon.clients-per-thread", 50));
			}

			define('synapse\DEBUG', 2);

			if(\synapse\DEBUG >= 0){
				@cli_set_process_title($this->getName() . " " . $this->getSynapseVersion());
			}

			$this->logger->info($this->getLanguage()->translateString("synapse.server.networkStart", [$this->getIp() === "" ? "*" : $this->getIp(), $this->getPort()]));
			define("BOOTUP_RANDOM", @Utils::getRandomBytes(16));
			$this->serverID = Utils::getMachineUniqueId($this->getIp() . $this->getPort());

			$this->getLogger()->debug("Server unique id: " . $this->getServerUniqueId());
			$this->getLogger()->debug("Machine unique id: " . Utils::getMachineUniqueId());

			$this->network = new Network($this);
			$this->network->setName($this->getMotd());


			$this->logger->info($this->getLanguage()->translateString("synapse.server.info", [
				$this->getName(),
				$this->getSynapseVersion(),
				$this->getCodename(),
				$this->getApiVersion()
			]));
			$this->logger->info($this->getLanguage()->translateString("synapse.server.license", [$this->getName()]));

			Timings::init();

			$this->consoleSender = new ConsoleCommandSender();
			$this->commandMap = new SimpleCommandMap($this);

			$this->pluginManager = new PluginManager($this, $this->commandMap);
			$this->pluginManager->setUseTimings($this->getConfig("enable-profiling", false));
			$this->profilingTickRate = (float) $this->getConfig("profile-report-trigger", 20);
			$this->pluginManager->registerInterface(PharPluginLoader::class);
			$this->pluginManager->registerInterface(FolderPluginLoader::class);
			$this->pluginManager->registerInterface(ScriptPluginLoader::class);

			//register_shutdown_function([$this, "crashDump"]);

			$this->queryRegenerateTask = new QueryRegenerateEvent($this, 5);

			$this->network->registerInterface(new RakLibInterface($this));

			$this->pluginManager->loadPlugins($this->pluginPath);

			$this->enablePlugins();


			$this->start();
		}catch(\Throwable $e){
			$this->exceptionHandler($e);
		}
	}

	public function comparePassword($pass){
		
	}

	public function addPlayer($identifier, Player $player){
		$this->players[$identifier] = $player;
		$this->identifiers[spl_object_hash($player)] = $identifier;
	}

	public function getQueryInformation(){
		return $this->queryRegenerateTask;
	}

	public function updateQuery(){
		try{
			$this->getPluginManager()->callEvent($this->queryRegenerateTask = new QueryRegenerateEvent($this, 5));
			if($this->queryHandler !== null){
				$this->queryHandler->regenerateInfo();
			}
		}catch(\Throwable $e){
			$this->logger->logException($e);
		}
	}

	public function start(){
		if($this->getConfig("enable-query", true) === true){
			$this->queryHandler = new QueryHandler();
		}

		/*foreach($this->getIPBans()->getEntries() as $entry){
			$this->network->blockAddress($entry->getName(), -1);
		}*/

		if($this->getConfig("network.upnp-forwarding", false) == true){
			$this->logger->info("[UPnP] Trying to port forward...");
			UPnP::PortForward($this->getPort());
		}

		$this->tickCounter = 0;

		if(function_exists("pcntl_signal")){
			pcntl_signal(SIGTERM, [$this, "handleSignal"]);
			pcntl_signal(SIGINT, [$this, "handleSignal"]);
			pcntl_signal(SIGHUP, [$this, "handleSignal"]);
			$this->dispatchSignals = true;
		}

		$this->logger->info($this->getLanguage()->translateString("synapse.server.startFinished", [round(microtime(true) - \synapse\START_TIME, 3)]));

		if(!file_exists($this->getPluginPath() . DIRECTORY_SEPARATOR . "Genisys")){
			@mkdir($this->getPluginPath() . DIRECTORY_SEPARATOR . "Genisys");
		}

		$this->tickProcessor();
		$this->forceShutdown();

		gc_collect_cycles();
	}

	private function tickProcessor(){
		$this->nextTick = microtime(true);
		while($this->isRunning){
			$this->tick();
			$next = $this->nextTick - 0.0001;
			if($next > microtime(true)){
				try{
					time_sleep_until($next);
				}catch(\Throwable $e){
					//Sometimes $next is less than the current time. High load?
				}
			}
		}
	}

	public function getNetwork(){
		return $this->network;
	}

	private function checkTickUpdates($currentTick, $tickTime){
		foreach($this->players as $p){
			if(!$p->loggedIn and ($tickTime - $p->creationTime) >= 10){
				$p->close("", "Login timeout");//TODO
			}
		}
	}

	private function titleTick(){
		if(!Terminal::hasFormattingCodes()){
			return;
		}

		$d = Utils::getRealMemoryUsage();

		$u = Utils::getMemoryUsage(true);
		$usage = round(($u[0] / 1024) / 1024, 2) . "/" . round(($d[0] / 1024) / 1024, 2) . "/" . round(($u[1] / 1024) / 1024, 2) . "/" . round(($u[2] / 1024) / 1024, 2) . " MB @ " . Utils::getThreadCount() . " threads";

		echo "\x1b]0;" . $this->getName() . " " .
			$this->getVersionString()->getMajor() . "-#" . $this->getVersion()->getBuild() .
			" | Online " . count($this->players) . "/" . $this->getMaxPlayers() .
			" | Memory " . $usage .
			" | U " . round($this->network->getUpload() / 1024, 2) .
			" D " . round($this->network->getDownload() / 1024, 2) .
			" kB/s | TPS " . $this->getTicksPerSecondAverage() .
			" | Load " . $this->getTickUsageAverage() . "%\x07";

		$this->network->resetStatistics();
	}

	/**
	 * Returns the last server TPS measure
	 *
	 * @return float
	 */
	public function getTicksPerSecond(){
		return round($this->maxTick, 2);
	}

	/**
	 * Returns the last server TPS average measure
	 *
	 * @return float
	 */
	public function getTicksPerSecondAverage(){
		return round(array_sum($this->tickAverage) / count($this->tickAverage), 2);
	}

	/**
	 * Returns the TPS usage/load in %
	 *
	 * @return float
	 */
	public function getTickUsage(){
		return round($this->maxUse * 100, 2);
	}

	/**
	 * Returns the TPS usage/load average in %
	 *
	 * @return float
	 */
	public function getTickUsageAverage(){
		return round((array_sum($this->useAverage) / count($this->useAverage)) * 100, 2);
	}

	public function getVersionString(){
		return $this->version;
	}

	private function tick(){
		$tickTime = microtime(true);
		if(($tickTime - $this->nextTick) < -0.025){ //Allow half a tick of diff
			return false;
		}

		Timings::$serverTickTimer->startTiming();

		++$this->tickCounter;

		$this->checkConsole();

		Timings::$connectionTimer->startTiming();
		$this->network->processInterfaces();

		if($this->rcon !== null){
			$this->rcon->check();
		}

		Timings::$connectionTimer->stopTiming();

		Timings::$schedulerTimer->startTiming();
		$this->scheduler->mainThreadHeartbeat($this->tickCounter);
		Timings::$schedulerTimer->stopTiming();

		$this->checkTickUpdates($this->tickCounter, $tickTime);

		foreach($this->players as $player){
			$player->checkNetwork();//TODO
		}

		if(($this->tickCounter & 0b1111) === 0){
			$this->titleTick();
			$this->maxTick = 100;
			$this->maxUse = 0;

			if(($this->tickCounter & 0b111111111) === 0){
					$this->updateQuery();
			}

			$this->getNetwork()->updateName();
		}

		if($this->dispatchSignals and $this->tickCounter % 5 === 0){
			pcntl_signal_dispatch();
		}

		Timings::$serverTickTimer->stopTiming();

		$now = microtime(true);
		$tick = min(100, 1 / max(0.001, $now - $tickTime));
		$use = min(1, ($now - $tickTime) / 0.05);

		//TimingsHandler::tick($tick <= $this->profilingTickRate);

		if($this->maxTick > $tick){
			$this->maxTick = $tick;
		}

		if($this->maxUse < $use){
			$this->maxUse = $use;
		}

		array_shift($this->tickAverage);
		$this->tickAverage[] = $tick;
		array_shift($this->useAverage);
		$this->useAverage[] = $use;

		if(($this->nextTick - $tickTime) < -1){
			$this->nextTick = $tickTime;
		}else{
			$this->nextTick += 0.05;
		}

		return true;
	}

	public function handleSignal($signo){
		if($signo === SIGTERM or $signo === SIGINT or $signo === SIGHUP){
			$this->shutdown();
		}
	}

	public function getOnlinePlayers(){
		return $this->players;
	}

	public function hasWhitelist(){
		return false;//TODO: whitelist
	}

	public function enablePlugins(){
		foreach($this->pluginManager->getPlugins() as $plugin){
			if(!$plugin->isEnabled()){
				$this->enablePlugin($plugin);
			}
		}
	}

	/**
	 * @param Plugin $plugin
	 */
	public function enablePlugin(Plugin $plugin){
		$this->pluginManager->enablePlugin($plugin);
	}

	/**
	 * @param Plugin $plugin
	 *
	 * @deprecated
	 */
	public function loadPlugin(Plugin $plugin){
		$this->enablePlugin($plugin);
	}

	public function disablePlugins(){
		$this->pluginManager->disablePlugins();
	}

	public function checkConsole(){
		Timings::$serverCommandTimer->startTiming();
		if(($line = $this->console->getLine()) !== null){
				$this->dispatchCommand($this->consoleSender, $line);
		}
		Timings::$serverCommandTimer->stopTiming();
	}

	/**
	 * Executes a command from a CommandSender
	 *
	 * @param CommandSender $sender
	 * @param string        $commandLine
	 *
	 * @return bool
	 *
	 * @throws \Throwable
	 */
	public function dispatchCommand(CommandSender $sender, $commandLine){
		if(!($sender instanceof CommandSender)){
			throw new ServerException("CommandSender is not valid");
		}

		if($this->commandMap->dispatch($sender, $commandLine)){
			return true;
		}


		$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.notFound"));

		return false;
	}

	/**
	 * @return SimpleCommandMap
	 */
	public function getCommandMap(){
		return $this->commandMap;
	}

	public function registerClient(Client $client){

	}

	public function getPluginManager(){
		return $this->pluginManager;
	}

	/**
	 * @return string
	 */
	public function getFilePath(){
		return $this->filePath;
	}

	/**
	 * @return string
	 */
	public function getDataPath(){
		return $this->dataPath;
	}

	/**
	 * @return string
	 */
	public function getPluginPath(){
		return $this->pluginPath;
	}

	/**
	 * @return int
	 */
	public function getMaxPlayers(){
		return $this->maxPlayers;
	}

	public function getServerUniqueId(){
		return $this->serverID;
	}

	/**
	 * @return string
	 */
	public function getIp(){
		return $this->getConfig("server-ip", "0.0.0.0");
	}

	public function getCodename() :string{
		return \synapse\CODENAME;
	}

	public function exceptionHandler(\Throwable $e, $trace = null){
		if($e === null){
			return;
		}

		global $lastError;

		if($trace === null){
			$trace = $e->getTrace();
		}

		$errstr = $e->getMessage();
		$errfile = $e->getFile();
		$errno = $e->getCode();
		$errline = $e->getLine();

		$type = ($errno === E_ERROR or $errno === E_USER_ERROR) ? \LogLevel::ERROR : (($errno === E_USER_WARNING or $errno === E_WARNING) ? \LogLevel::WARNING : \LogLevel::NOTICE);
		if(($pos = strpos($errstr, "\n")) !== false){
			$errstr = substr($errstr, 0, $pos);
		}

		$errfile = cleanPath($errfile);

		if($this->logger instanceof MainLogger){
			$this->logger->logException($e, $trace);
		}

		$lastError = [
			"type" => $type,
			"message" => $errstr,
			"fullFile" => $e->getFile(),
			"file" => $errfile,
			"line" => $errline,
			"trace" => @getTrace(1, $trace)
		];

		global $lastExceptionError, $lastError;
		$lastExceptionError = $lastError;
		//$this->crashDump();
	}

	public function getScheduler(){
		return $this->scheduler;
	}

	public function getVersion(){
		return \synapse\MINECRAFT_VERSION;
	}

	public function getPort() : int{
		return $this->getConfig("server-port", 10305);
	}

	/**
	 * @return string
	 */
	public function getMotd(){
		return $this->getConfig("motd", "Minecraft: PE Server");
	}

	/**
	 * @return \ClassLoader
	 */
	public function getLoader(){
		return $this->autoloader;
	}

	/**
	 * @return MainLogger
	 */
	public function getLogger(){
		return $this->logger;
	}

	/**
	 * @param string $variable
	 * @param mixed  $defaultValue
	 *
	 * @return mixed
	 */
	public function getConfig($variable, $defaultValue = null){
		if($this->properties->exists($variable)){
			return $this->properties->get($variable);
		}
		return $defaultValue;
	}

	public static function getInstance(){
		return self::$instance;
	}

	public function getName() : string{
		return "Synapse";
	}

	public function getSynapseVersion() : string{
		return \synapse\VERSION;
	}

	public function getApiVersion(){
		return \synapse\API_VERSION;
	}

	public function getLanguage(){
		return $this->baseLang;
	}
}