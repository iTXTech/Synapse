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

use synapse\event\Timings;
use synapse\command\ConsoleCommandSender;
use synapse\command\SimpleCommandMap;
use synapse\lang\BaseLang;
use synapse\command\CommandReader;
use synapse\network\Network;
use synapse\network\rcon\RCON;
use synapse\plugin\FolderPluginLoader;
use synapse\plugin\PharPluginLoader;
use synapse\plugin\PluginManager;
use synapse\plugin\ScriptPluginLoader;
use synapse\scheduler\ServerScheduler;
use synapse\utils\Config;
use synapse\utils\MainLogger;
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
				"lang" => "eng",
				"async-workers" => "auto",
				"enable-query" => true,
				"enable-rcon" => false,
				"rcon.password" => substr(base64_encode(@Utils::getRandomBytes(20, false)), 3, 10),
			]);

			$this->baseLang = new BaseLang($this->getConfig("lang", BaseLang::FALLBACK_LANGUAGE));
			$this->logger->info($this->getLanguage()->translateString("language.selected", [$this->getLanguage()->getName(), $this->getLanguage()->getLang()]));

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
			$this->profilingTickRate = (float) $this->getConfig("profile-report-trigger", 20);//TODO
			$this->pluginManager->registerInterface(PharPluginLoader::class);
			$this->pluginManager->registerInterface(FolderPluginLoader::class);
			$this->pluginManager->registerInterface(ScriptPluginLoader::class);
		}catch(\Throwable $e){
			$this->exceptionHandler($e);
		}
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