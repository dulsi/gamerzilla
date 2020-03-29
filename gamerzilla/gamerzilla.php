<?php
/**
 * Name: Gamerzilla
 * Description: Game trophies
 * Version: 0.1
 * Depends: Core
 * Recommends: None
 * Author: Dennis Payne <dulsi@identicalsoftware.com>
 * Maintainer: Dennis Payne <dulsi@identicalsoftware.com>
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;

function gamerzilla_load(){
	Route::register('addon/gamerzilla/Mod_Gamerzilla.php','gamerzilla');
	gamerzilla_dbsetup();
}


function gamerzilla_unload(){
	Route::unregister('addon/gamerzilla/Mod_Gamerzilla.php','gamerzilla');
}

function gamerzilla_getsysconfig($param) {
	$val = get_config("gamerzilla",$param);
	return $val;
}

function gamerzilla_setsysconfig($param,$val) {
	return set_config("gamerzilla",$param,$val);
}

function gamerzilla_dbsetup () {
	$dbverconfig = gamerzilla_getsysconfig("dbver");
	logger ('[gamerzilla] Current sysconfig dbver:'.$dbverconfig,LOGGER_NORMAL);

	$dbver = $dbverconfig ? $dbverconfig : 0;

	$dbsql[DBTYPE_MYSQL] = Array (
		1 => Array (
			"CREATE TABLE gamerzilla_game (
				id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
				short_name varvhar(128),
				game_name varchar(128)
				) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
			",
			"alter table gamerzilla_game add index (short_name)",
			"CREATE TABLE gamerzilla_trophy (
				id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
				game_id int(10),
				trophy_name varchar(128),
				trophy_desc varchar(255),
				max_progress int(10)
				) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
			",
			"alter table gamerzilla_trophy add index (game_id)",
			"alter table gamerzilla_trophy add index (trophy_name)",
			"CREATE TABLE gamerzilla_userstat (
				id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
				uuid int(10),
				game_id int(10),
				trophy_id int(10),
				achieved tinyint(1),
				progress int(10)
				) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
			",
			"alter table gamerzilla_userstat add index (uuid)",
			"alter table gamerzilla_userstat add index (game_id)",
			"alter table gamerzilla_userstat add index (trophy_id)"
		)
	);

	$dbsql[DBTYPE_POSTGRES] = Array (
		1 => Array (
			"CREATE TABLE gamerzilla_game (
				id serial NOT NULL,
				game_name varchar(128)
				PRIMARY KEY (id)
				);
			",
			"CREATE INDEX idx_game_name ON gamerzilla_game (game_name);",
			"CREATE TABLE gamerzilla_trophy (
				id serial NOT NULL,
				game_id int,
				trophy_name varchar(128),
				trophy_desc varchar(255),
				max_progress int,
				PRIMARY KEY (id)
				);
			",
			"CREATE INDEX idx_game_id ON gamerzilla_trophy (game_id);",
			"CREATE INDEX idx_trophy_name ON gamerzilla_trophy (trophy_name);",
			"CREATE TABLE gamerzilla_userstat (
				id serial NOT NULL,
				uuid int,
				game_id int,
				trophy_id int,
				achieved smallint,
				progress int,
				PRIMARY KEY (id)
				);
			",
			"CREATE INDEX idx_game_id ON gamerzilla_trophy (uuid);",
			"CREATE INDEX idx_game_id ON gamerzilla_trophy (game_id);",
			"CREATE INDEX idx_trophy_name ON gamerzilla_trophy (trophy_id);"
		)
	);

	foreach ($dbsql[ACTIVE_DBTYPE] as $ver => $sql) {
		if ($ver <= $dbver) {
			continue;
		}
		foreach ($sql as $query) {
			logger ('[gamerzilla] dbSetup:'.$query,LOGGER_DATA);
			$r = q($query);
			if (!$r) {
				notice ('[gamerzilla] Error running dbUpgrade.');
				logger ('[gamerzilla] Error running dbUpgrade. sql query: '.$query);
				return UPDATE_FAILED;
			}
		}
		gamerzilla_setsysconfig("dbver",$ver);
	}
	$response = UPDATE_SUCCESS;
	logger("GAMERZILLA: run db_upgrade hooks",LOGGER_DEBUG);
	return $response;
}
