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
use iTXTech\SimpleFramework\Module\ModuleManager;
use iTXTech\Synapse\Launcher;

Initializer::initTerminal(true);

Logger::info("iTXTech Test Framework: " . basename($argv[0], ".php"));
Logger::info("Loading iTXTech Synapse");

global $classLoader;
try{
	$moduleManager = new ModuleManager($classLoader, __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR, __DIR__ . "data");
	$moduleManager->loadModules();
}catch(Throwable $e){
	Logger::logException($e);
}

function load(Launcher $launcher){
	Logger::info("Launching");
	$time = microtime(true);
	$launcher->launch();
	Logger::info("Launched " . round((microtime(true) - $time) * 1000, 2) . " ms");

	while(true) ;
}