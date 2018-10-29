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

namespace iTXTech\Synapse\Util;

use Swoole\Table;

abstract class TableHelper{
	/*
	 * [
	 *   TABLE_KEY_NAME => [TABLE_TYPE, DEFAULT_VALUE, ?SIZE],
	 *   ...
	 * ]
	 */

	public static function createTable(int $size, array $structure) : Table{
		$table = new Table($size);
		foreach($structure as $k => $v){
			$defaultValues[$k] = $v[1];
			if($v[0] === Table::TYPE_STRING){
				$table->column($k, $v[0], $v[2]);
			} else {
				$table->column($k, $v[0]);
			}
		}
		$table->create();

		return $table;
	}

	public static function initializeDefaultValue(Table $table, string $key, array $structure){
		$defaultValues = [];
		foreach($structure as $k => $v){
			$defaultValues[$k] = $v[1];
		}
		$table->set($key, $defaultValues);
	}
}