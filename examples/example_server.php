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

require_once "env.php";

use iTXTech\SimpleFramework\Console\TextFormat;
use iTXTech\Synapse\Launcher;

$launcher = (new Launcher())
	->kListen("0.0.0.0", 10305)
	->rListen("0.0.0.0", 19133);
$launcher->rServerName("MCPE;" . TextFormat::LIGHT_PURPLE . "iTXTech Synapse;291;1.7.0;23;666;" . $launcher->getRServerId() . ";Synapse;Creative;");

$syn = load($launcher);

while(true){
	$syn->getRaknet()->setServerName("MCPE;" . TextFormat::LIGHT_PURPLE . "iTXTech Synapse;291;1.7.0;23;666;" . $launcher->getRServerId() . ";Synapse;Creative;");
	sleep(1);
}