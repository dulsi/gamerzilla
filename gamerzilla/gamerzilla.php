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
	Hook::register('api_register','addon/gamerzilla/gamerzilla.php','gamerzilla_api_register');
	Route::register('addon/gamerzilla/Mod_Gamerzilla.php','gamerzilla');
	gamerzilla_dbsetup();
}


function gamerzilla_unload(){
	Hook::unregister_by_file('addon/gamerzilla/gamerzilla.php');
	Route::unregister('addon/gamerzilla/Mod_Gamerzilla.php','gamerzilla');
}

function gamerzilla_api_register($x) {

	api_register_func('api/gamerzilla/games','api_games', true);
	api_register_func('api/gamerzilla/game','api_game', true);
	api_register_func('api/gamerzilla/game/add','api_game_add', true);
	api_register_func('api/gamerzilla/trophy/set','api_trophy_set', true);
	api_register_func('api/gamerzilla/trophy/set/stat','api_trophy_set_stat', true);
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
				game_name varchar(128),
				vernum int
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

function api_games($type) {
	$r = q("select short_name, game_name, (select count(*) from gamerzilla_userstat u2 where u2.achieved = 1 and g.id = u2.game_id and u2.uuid = %d) as earned, (select count(*) from gamerzilla_trophy t where g.id = t.game_id) as total_trophy from gamerzilla_game g where g.id in (select game_id from gamerzilla_userstat u where u.uuid = %d)",
			local_channel(),
			local_channel()
		);
	$items = [];
	if ($r) {
		for($x = 0; $x < count($r); $x ++) {
			$items[$x] = ['shortname' => $r[$x]["short_name"], 'name' => $r[$x]["game_name"], 'earned' => $r[$x]["earned"], 'total' => $r[$x]["total_trophy"] ];
		}
	}
	return api_apply_template('games', $type, array('$games' => $items));
}

function api_game($type) {
	$r_game = q("select id, short_name, game_name, vernum from gamerzilla_game g where g.short_name = '%s'",
			dbesc($_POST["game"])
		);
	$game = [];
	if ($r_game) {
		$game = ['id' => $r_game[0]["id"], 'shortname' => $r_game[0]["short_name"], 'name' => $r_game[0]["game_name"], 'version' => $r_game[0]["vernum"] ];
	}
	$r = q("select trophy_name, trophy_desc, progress, max_progress, coalesce(achieved, 0) achieved from gamerzilla_game g, gamerzilla_trophy t left outer join gamerzilla_userstat u on t.game_id = u.game_id and t.id = u.trophy_id and u.uuid = %d where g.id = t.game_id and g.id = %d order by achieved desc, t.id",
			local_channel(),
			$r_game[0]["id"]
		);
	$items = [];
	if ($r) {
		for($x = 0; $x < count($r); $x ++) {
			$items[$x] = ['trophy_name' => $r[$x]["trophy_name"], 'trophy_desc' => $r[$x]["trophy_desc"], 'achieved' => $r[$x]["achieved"], 'progress' => $r[$x]["progress"], 'max_progress' => $r[$x]["max_progress"] ];
		}
	}
	$game["trophy"] = $items;
	return api_apply_template('game', $type, array('$game' => $game));
}

function api_game_add($type) {
	$game = json_decode($_POST["game"], true);
	$r_game = q("select id from gamerzilla_game g where g.short_name = '%s'",
			dbesc($game["shortname"])
		);
	if ($r_game) {
		$r = q("update gamerzilla_game set game_name= '%s', vernum = %d where id = %d",
				dbesc($game["name"]),
				$game["version"],
				$r_game[0]["id"]
			);
	}
	else {
		$r = q("insert into gamerzilla_game(short_name, game_name, vernum) values ('%s', '%s', %d)",
				dbesc($game["shortname"]),
				dbesc($game["name"]),
				$game["version"]
			);
		$r_game = q("select id from gamerzilla_game g where g.short_name = '%s'",
				dbesc($game["shortname"])
			);
	}
	$r_trophy = q("select id, trophy_name, trophy_desc, max_progress from gamerzilla_trophy g where g.game_id = %d",
			$r_game[0]["id"]
		);
	$trophy = $game["trophy"];
	for ($x = 0; $x < count($trophy); $x ++) {
		$found = false;
		if ($r_trophy) {
			for ($y = 0; $y < count($r_trophy); $y ++) {
				if (strcmp($r_trophy[$y]['trophy_name'], $trophy[$x]["trophy_name"]) == 0) {
					$r = q("update gamerzilla_trophy set trophy_name = '%s', trophy_desc= '%s', max_progress = %d where id = %d",
							dbesc($trophy[$x]["trophy_name"]),
							dbesc($trophy[$x]["trophy_desc"]),
							$trophy[$x]["max_progress"],
							$r_trophy[$y]["id"]
						);
					$found = true;
				}
			}
		}
		if (!$found) {
			$r = q("insert into gamerzilla_trophy(game_id, trophy_name, trophy_desc, max_progress) values (%d, '%s', '%s', %d)",
					$r_game[0]["id"],
					dbesc($trophy[$x]["trophy_name"]),
					dbesc($trophy[$x]["trophy_desc"]),
					$trophy[$x]["max_progress"]
				);
		}
	}
	return api_apply_template('game', $type, array('$game' => $game));
}

function api_trophy_set($type) {
	$r_trophy = q("select t.game_id, t.id from gamerzilla_game g, gamerzilla_trophy t where g.short_name = '%s' and g.id = t.game_id and t.trophy_name = '%s'",
			dbesc($_POST["game"]),
			dbesc($_POST["trophy"])
		);
	if ($r_trophy) {
		$r_user = q("select id, achieved from gamerzilla_userstat g where g.game_id = %d and g.trophy_id = %d and g.uuid = %d",
				$r_trophy[0]["game_id"],
				$r_trophy[0]["id"],
				local_channel()
			);
		if ($r_user) {
			if ($r_user[0]["achieved"] != 0) {
				$r = q("update gamerzilla_userstat set achieved = 1 where id = %d",
						$r_user[0]["id"]
					);
			}
		}
		else {
			$r = q("insert into gamerzilla_userstat(game_id, trophy_id, uuid, achieved) values (%d, %d, %d, 1)",
					$r_trophy[0]["game_id"],
					$r_trophy[0]["id"],
					local_channel()
				);
		}
	}
	return api_apply_template('result', $type, array('$result' => true));
}

function api_trophy_set_stat($type) {
	$r_trophy = q("select t.game_id, t.id from gamerzilla_game g, gamerzilla_trophy t where g.short_name = '%s' and g.id = t.game_id and t.trophy_name = '%s'",
			dbesc($_POST["game"]),
			dbesc($_POST["trophy"])
		);
	if ($r_trophy) {
		$r_user = q("select id, progress from gamerzilla_userstat g where g.game_id = %d and g.trophy_id = %d and g.uuid = %d",
				$r_trophy[0]["game_id"],
				$r_trophy[0]["id"],
				local_channel()
			);
		if ($r_user) {
			if ($r_user[0]["progress"] < (int)$_POST["progress"]) {
				$r = q("update gamerzilla_userstat set progress = %d where id = %d",
						(int)$_POST["progress"],
						$r_user[0]["id"]
					);
			}
		}
		else {
			$r = q("insert into gamerzilla_userstat(game_id, trophy_id, uuid, progress) values (%d, %d, %d, %d)",
					$r_trophy[0]["game_id"],
					$r_trophy[0]["id"],
					local_channel(),
					(int)$_POST["progress"]
				);
		}
	}
	return api_apply_template('result', $type, array('$result' => true));
}
