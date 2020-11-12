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

require_once('include/photo/photo_driver.php');

function gamerzilla_load(){
	Hook::register('channel_apps', 'addon/gamerzilla/gamerzilla.php', 'gamerzilla_channel_apps');
	Hook::register('api_register','addon/gamerzilla/gamerzilla.php','gamerzilla_api_register');
	Route::register('addon/gamerzilla/Mod_Gamerzilla.php','gamerzilla');
	gamerzilla_dbsetup();
}

function gamerzilla_unload(){
	Hook::unregister_by_file('addon/gamerzilla/gamerzilla.php');
	Route::unregister('addon/gamerzilla/Mod_Gamerzilla.php','gamerzilla');
}

function gamerzilla_channel_apps(&$b) {
	$uid = ((App::$profile_uid) ? App::$profile_uid : intval(local_channel()));

	if(! Apps::addon_app_installed($uid, 'gallery'))
		return;

	$b['tabs'][] = [
		'label' => t('Games'),
		'url'   => z_root() . '/gamerzilla/' . $b['nickname'],
		'sel'   => ((argv(0) == 'gamerzilla') ? 'active' : ''),
		'title' => t('Games'),
		'id'    => 'games-tab',
		'icon'  => 'cog'
	];
}

function gamerzilla_api_register($x) {

	api_register_func('api/gamerzilla/games','api_games', true);
	api_register_func('api/gamerzilla/game','api_game', true);
	api_register_func('api/gamerzilla/game/add','api_game_add', true);
	api_register_func('api/gamerzilla/game/image','api_game_image', true);
	api_register_func('api/gamerzilla/game/image/show','api_game_image_show', true);
	api_register_func('api/gamerzilla/trophy/set','api_trophy_set', true);
	api_register_func('api/gamerzilla/trophy/set/stat','api_trophy_set_stat', true);
	api_register_func('api/gamerzilla/trophy/image','api_trophy_image', true);
	api_register_func('api/gamerzilla/trophy/image/show','api_trophy_image_show', true);
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
				short_name varchar(128),
				game_name varchar(128),
				photoid char(191),
				vernum int
				) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
			",
			"alter table gamerzilla_game add index (short_name)",
			"CREATE TABLE gamerzilla_trophy (
				id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
				game_id int(10),
				trophy_name varchar(128),
				trophy_desc varchar(255),
				max_progress int(10),
				truephotoid char(191),
				falsephotoid char(191)
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
			"alter table gamerzilla_userstat add index (trophy_id)",
			"CREATE TABLE gamerzilla_image (
				id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
				resource_id char(191),
				filename char(191),
				mimetype char(191),
				height smallint(6),
				width smallint(6),
				filesize int(10),
				content mediumblob
				) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
			",
			"alter table gamerzilla_image add index (resource_id)"
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
	$r_game = q("select id, short_name, game_name, photoid, vernum from gamerzilla_game g where g.short_name = '%s'",
			dbesc($_REQUEST["game"])
		);
	$game = [];
	if ($r_game) {
		$game = ['id' => $r_game[0]["id"], 'shortname' => $r_game[0]["short_name"], 'name' => $r_game[0]["game_name"], 'image' => $r_game[0]["photoid"], 'version' => $r_game[0]["vernum"] ];
	}
	$r = q("select trophy_name, trophy_desc, progress, max_progress, coalesce(achieved, 0) achieved from gamerzilla_game g, gamerzilla_trophy t left outer join gamerzilla_userstat u on t.game_id = u.game_id and t.id = u.trophy_id and u.uuid = %d where g.id = t.game_id and g.id = %d order by t.id",
			local_channel(),
			$r_game[0]["id"]
		);
	$items = [];
	if ($r) {
		for($x = 0; $x < count($r); $x ++) {
			$items[$x] = ['trophy_name' => $r[$x]["trophy_name"], 'trophy_desc' => $r[$x]["trophy_desc"], 'achieved' => $r[$x]["achieved"], 'progress' => $r[$x]["progress"], 'max_progress' => $r[$x]["max_progress"] ];
		}
	}
	$game["game"] = $_REQUEST["game"];
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
		$photo_hash = photo_new_resource();
		$r = q("insert into gamerzilla_game(short_name, game_name, photoid, vernum) values ('%s', '%s', '%s', %d)",
				dbesc($game["shortname"]),
				dbesc($game["name"]),
				dbesc($photo_hash),
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
			$true_photo_hash = photo_new_resource();
			$false_photo_hash = photo_new_resource();
			$r = q("insert into gamerzilla_trophy(game_id, trophy_name, trophy_desc, max_progress, truephotoid, falsephotoid) values (%d, '%s', '%s', %d, '%s', '%s')",
					$r_game[0]["id"],
					dbesc($trophy[$x]["trophy_name"]),
					dbesc($trophy[$x]["trophy_desc"]),
					$trophy[$x]["max_progress"],
					$true_photo_hash,
					$false_photo_hash
				);
		}
	}
	return api_apply_template('game', $type, array('$game' => $game));
}

function api_game_image($type) {
	$r_game = q("select photoid from gamerzilla_game g where g.short_name = '%s'",
			dbesc($_POST["game"])
		);
	if ($r_game) {
		$photoid = $r_game[0]["photoid"];
		$imagedata = @file_get_contents($_FILES['imagefile']['tmp_name']);
		$ph = photo_factory($imagedata, $_FILES['imagefile']['type']);
		$ph->doScaleImage(368, 172);
		$ph->clearexif();
		$arr = array('resource_id' => $photoid,
			'filename' => $_FILES['imagefile']['name'], 'imgscale' => 0, 'photo_usage' => PHOTO_NORMAL,
			'width' => 368, 'height' => 172
		);
		$p = [];

		$p['resource_id'] = (($arr['resource_id']) ? $arr['resource_id'] : '');
		$p['filename'] = (($arr['filename']) ? $arr['filename'] : '');
		$p['mimetype'] = (($arr['mimetype']) ? $arr['mimetype'] : $ph->getType());
		$p['width'] = (($arr['width']) ? $arr['width'] : $ph->getWidth());
		$p['height'] = (($arr['height']) ? $arr['height'] : $ph->getHeight());

		$x = q("select id from gamerzilla_image where resource_id = '%s' limit 1", dbesc($p['resource_id']));

		if($x) {
			$r0 = q("UPDATE gamerzilla_image set
				resource_id = '%s',
				filename = '%s',
				mimetype = '%s',
				height = %d,
				width = %d,
				content = '%s',
				filesize = %d
				where id = %d",
			dbesc($p['resource_id']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), intval($p['height']), intval($p['width']), dbescbin($ph->imageString()), strlen($ph->imageString()));
		} else {
			$p['created'] = (($arr['created']) ? $arr['created'] : $p['edited']);
			$r0 = q("INSERT INTO gamerzilla_image
				( resource_id, filename, mimetype, height, width, content, filesize )
				VALUES ( '%s', '%s', '%s', %d, %d, '%s', %d)", dbesc($p['resource_id']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), intval($p['height']), intval($p['width']), dbescbin($ph->imageString()), strlen($ph->imageString()));
		}
	}
	return api_apply_template('game', $type, array('$game' => $_POST["game"]));
}

function api_game_image_show($type) {
	$r_game = q("select photoid from gamerzilla_game g where g.short_name = '%s'",
			dbesc($_REQUEST["game"])
		);
	if ($r_game) {
		$r = q("select mimetype, content from gamerzilla_image where resource_id='%s'",
			dbesc($r_game[0]["photoid"])
		);
		if ($r) {
			header("Content-Type: " . $r[0]["mime_type"]);
			$image = @imagecreatefromstring($r[0]["content"]);
			imagepng($image, NULL, 9);
			imagedestroy($image);
			killme();
		}
	}
}

function api_trophy_set($type) {
	$r_trophy = q("select t.game_id, t.id from gamerzilla_game g, gamerzilla_trophy t where g.short_name = '%s' and g.id = t.game_id and t.trophy_name = '%s'",
			dbesc($_REQUEST["game"]),
			dbesc($_REQUEST["trophy"])
		);
	if ($r_trophy) {
		$r_user = q("select id, achieved from gamerzilla_userstat g where g.game_id = %d and g.trophy_id = %d and g.uuid = %d",
				$r_trophy[0]["game_id"],
				$r_trophy[0]["id"],
				local_channel()
			);
		if ($r_user) {
			if ($r_user[0]["achieved"] != 1) {
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
			dbesc($_REQUEST["game"]),
			dbesc($_REQUEST["trophy"])
		);
	if ($r_trophy) {
		$r_user = q("select id, progress from gamerzilla_userstat g where g.game_id = %d and g.trophy_id = %d and g.uuid = %d",
				$r_trophy[0]["game_id"],
				$r_trophy[0]["id"],
				local_channel()
			);
		if ($r_user) {
			if ($r_user[0]["progress"] < (int)$_REQUEST["progress"]) {
				$r = q("update gamerzilla_userstat set progress = %d where id = %d",
						(int)$_REQUEST["progress"],
						$r_user[0]["id"]
					);
			}
		}
		else {
			$r = q("insert into gamerzilla_userstat(game_id, trophy_id, uuid, progress) values (%d, %d, %d, %d)",
					$r_trophy[0]["game_id"],
					$r_trophy[0]["id"],
					local_channel(),
					(int)$_REQUEST["progress"]
				);
		}
	}
	return api_apply_template('result', $type, array('$result' => true));
}

function api_trophy_image($type) {
	$r_game = q("select truephotoid, falsephotoid from gamerzilla_game g, gamerzilla_trophy t where g.short_name = '%s' and g.id = t.game_id and t.trophy_name = '%s'",
			dbesc($_POST["game"]),
			dbesc($_POST["trophy"])
		);
	if ($r_game) {
		$photoid = $r_game[0]["truephotoid"];
		$imagedata = @file_get_contents($_FILES['trueimagefile']['tmp_name']);
		$ph = photo_factory($imagedata, $_FILES['trueimagefile']['type']);
		$ph->doScaleImage(64, 64);
		$ph->clearexif();
		$arr = array('resource_id' => $photoid,
			'filename' => $_FILES['trueimagefile']['name'], 'imgscale' => 0, 'photo_usage' => PHOTO_NORMAL,
			'width' => 64, 'height' => 64
		);
		$p = [];

		$p['resource_id'] = (($arr['resource_id']) ? $arr['resource_id'] : '');
		$p['filename'] = (($arr['filename']) ? $arr['filename'] : '');
		$p['mimetype'] = (($arr['mimetype']) ? $arr['mimetype'] : $ph->getType());
		$p['width'] = (($arr['width']) ? $arr['width'] : $ph->getWidth());
		$p['height'] = (($arr['height']) ? $arr['height'] : $ph->getHeight());

		$x = q("select id from gamerzilla_image where resource_id = '%s' limit 1", dbesc($p['resource_id']));

		if($x) {
			$r0 = q("UPDATE gamerzilla_image set
				resource_id = '%s',
				filename = '%s',
				mimetype = '%s',
				height = %d,
				width = %d,
				content = '%s',
				filesize = %d
				where id = %d",
			dbesc($p['resource_id']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), intval($p['height']), intval($p['width']), dbescbin($ph->imageString()), strlen($ph->imageString()));
		} else {
			$p['created'] = (($arr['created']) ? $arr['created'] : $p['edited']);
			$r0 = q("INSERT INTO gamerzilla_image
				( resource_id, filename, mimetype, height, width, content, filesize )
				VALUES ( '%s', '%s', '%s', %d, %d, '%s', %d)", dbesc($p['resource_id']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), intval($p['height']), intval($p['width']), dbescbin($ph->imageString()), strlen($ph->imageString()));
		}
		$photoid = $r_game[0]["falsephotoid"];
		$imagedata = @file_get_contents($_FILES['falseimagefile']['tmp_name']);
		$ph = photo_factory($imagedata, $_FILES['falseimagefile']['type']);
		$ph->doScaleImage(64, 64);
		$ph->clearexif();
		$arr = array('resource_id' => $photoid,
			'filename' => $_FILES['falseimagefile']['name'], 'imgscale' => 0, 'photo_usage' => PHOTO_NORMAL,
			'width' => 64, 'height' => 64
		);
		$p = [];

		$p['resource_id'] = (($arr['resource_id']) ? $arr['resource_id'] : '');
		$p['filename'] = (($arr['filename']) ? $arr['filename'] : '');
		$p['mimetype'] = (($arr['mimetype']) ? $arr['mimetype'] : $ph->getType());
		$p['width'] = (($arr['width']) ? $arr['width'] : $ph->getWidth());
		$p['height'] = (($arr['height']) ? $arr['height'] : $ph->getHeight());

		$x = q("select id from gamerzilla_image where resource_id = '%s' limit 1", dbesc($p['resource_id']));

		if($x) {
			$r0 = q("UPDATE gamerzilla_image set
				resource_id = '%s',
				filename = '%s',
				mimetype = '%s',
				height = %d,
				width = %d,
				content = '%s',
				filesize = %d
				where id = %d",
			dbesc($p['resource_id']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), intval($p['height']), intval($p['width']), dbescbin($ph->imageString()), strlen($ph->imageString()));
		} else {
			$p['created'] = (($arr['created']) ? $arr['created'] : $p['edited']);
			$r0 = q("INSERT INTO gamerzilla_image
				( resource_id, filename, mimetype, height, width, content, filesize )
				VALUES ( '%s', '%s', '%s', %d, %d, '%s', %d)", dbesc($p['resource_id']), dbesc(basename($p['filename'])), dbesc($p['mimetype']), intval($p['height']), intval($p['width']), dbescbin($ph->imageString()), strlen($ph->imageString()));
		}
	}
	return api_apply_template('game', $type, array('$game' => $POST["game"]));
}

function api_trophy_image_show($type) {
	$r_game = q("select truephotoid, falsephotoid from gamerzilla_game g, gamerzilla_trophy t where g.short_name = '%s' and g.id = t.game_id and t.trophy_name = '%s'",
			dbesc($_REQUEST["game"]),
			dbesc($_REQUEST["trophy"])
		);
	if ($r_game) {
		if ($_REQUEST["achieved"] == '1')
				$r = q("select mimetype, content from gamerzilla_image where resource_id='%s'",
					dbesc($r_game[0]["truephotoid"])
				);
		else
				$r = q("select mimetype, content from gamerzilla_image where resource_id='%s'",
					dbesc($r_game[0]["falsephotoid"])
				);
		if ($r) {
			header("Content-Type: " . $r[0]["mime_type"]);
			$image = @imagecreatefromstring($r[0]["content"]);
			imagepng($image, NULL, 9);
			imagedestroy($image);
			killme();
		}
	}
}
