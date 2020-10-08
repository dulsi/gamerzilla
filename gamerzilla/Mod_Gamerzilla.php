<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Gamerzilla extends Controller {

	function init() {
		$channel = App::get_channel();
		if(argc() === 1 && $channel['channel_address']) {
			goaway(z_root() . '/gamerzilla/' . $channel['channel_address'] );
		}

		if(observer_prohibited()) {
			return;
		}
	
		if(argc() > 1) {
			$nick = argv(1);
	
			profile_load($nick);

			$channelx = channelx_by_nick($nick);
	
			if(! $channelx)
				return;
	
			App::$data['channel'] = $channelx;
	
			$observer = App::get_observer();
			App::$data['observer'] = $observer;

			App::$page['htmlhead'] .= "<script> var profile_uid = " . ((App::$data['channel']) ? App::$data['channel']['channel_id'] : 0) . "; </script>" ;
	
		}
	}

	function post() {
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(),'gamerzilla'))
			return;

//		check_form_security_token_redirectOnErr('gamerzilla', 'gamerzilla');

		set_pconfig(local_channel(), 'gamerzilla', 'block_default', $_POST['block_default']);

	}

	function get() {
		if(! Apps::addon_app_installed(App::$data['channel']['channel_id'], 'gamerzilla')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Gamerzilla App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= t('A gamerzilla for addons, you can copy/paste');
			return $o;
		}
		if ((argc() == 2) || ((argc() == 3) && (is_numeric(argv(2)))))
		{
			if ((local_channel() != App::$data['channel']['channel_id']) && (get_pconfig(App::$data['channel']['channel_id'], 'gamerzilla', 'block_default'))) {
				//Do not display any associated widgets at this point
				App::$pdl = '';

				$o = '<b>' . t('Gamerzilla App') . ' (' . t('Not Authorized') . '):</b><br>';
				$o .= t('You are authorized to access this page.');
				return $o;
			}
			$page = 0;
			if ((argc() == 3) && (is_numeric(argv(2))))
				$page = intval(argv(2));
			$r = q("select short_name, game_name, (select count(*) from gamerzilla_userstat u2 where u2.achieved = 1 and g.id = u2.game_id and u2.uuid = %d) as earned, (select count(*) from gamerzilla_trophy t where g.id = t.game_id) as total_trophy, (select max(u2.id) from gamerzilla_userstat u2 where u2.achieved = 1 and g.id = u2.game_id and u2.uuid = %d) as ustat_id from gamerzilla_game g where g.id in (select game_id from gamerzilla_userstat u where u.uuid = %d) order by ustat_id desc limit %d offset %d",
					App::$data['channel']['channel_id'],
					App::$data['channel']['channel_id'],
					App::$data['channel']['channel_id'],
					26,
					$page * 25
				);
			$tmpl = [];
			$items = [];
			if ($r) {
				for($x = 0; $x < count($r); $x ++) {
					if ($x == 25)
						$tmpl['$page_next'] = $page + 1;
					else
						$items[$x] = ['url' => "/gamerzilla/" . argv(1) . "/" . $r[$x]["short_name"], 'name' => $r[$x]["game_name"], 'earned' => $r[$x]["earned"], 'total' => $r[$x]["total_trophy"] ];
				}
			}

			if ($page > 0)
				$tmpl['$page_prev'] = $page - 1;
			$tmpl['$items'] = $items;
			$tmpl['$channel'] = argv(1);
//			$tmpl['$token'] = get_form_security_token('gamerzilla');
			$tmpl['$privacy'] = (local_channel() == App::$data['channel']['channel_id']) ? 1 : 0;
			$sc = replace_macros(get_markup_template('gamelist.tpl', 'addon/gamerzilla/'), $tmpl);
			return $sc;
		}
		if ((argc() == 3) && (argv(2) == ".privacy"))
		{
			if(local_channel() != App::$data['channel']['channel_id']) {
				//Do not display any associated widgets at this point
				App::$pdl = '';

				$o = '<b>' . t('Gamerzilla App') . ' (' . t('Not Authorized') . '):</b><br>';
				$o .= t('You are authorized to access this page.');
				return $o;
			}
			$tmpl = [];
			$tmpl['$channel'] = argv(1);
			$tmpl['$block_default'] = get_pconfig(local_channel(), 'gamerzilla', 'block_default');
			$sc = replace_macros(get_markup_template('gameprivacy.tpl', 'addon/gamerzilla/'), $tmpl);
			return $sc;
		}
		else if (argc() == 3)
		{
			if ((local_channel() != App::$data['channel']['channel_id']) && (get_pconfig(App::$data['channel']['channel_id'], 'gamerzilla', 'block_default'))) {
				//Do not display any associated widgets at this point
				App::$pdl = '';

				$o = '<b>' . t('Gamerzilla App') . ' (' . t('Not Authorized') . '):</b><br>';
				$o .= t('You are authorized to access this page.');
				return $o;
			}
			$g = q("select short_name, game_name from gamerzilla_game g where g.short_name = '%s'",
					dbesc(argv(2))
				);
			$r = q("select trophy_name, trophy_desc, progress, max_progress, coalesce(achieved, 0) achieved from gamerzilla_game g, gamerzilla_trophy t left outer join gamerzilla_userstat u on t.game_id = u.game_id and t.id = u.trophy_id and u.uuid = %d where g.id = t.game_id and g.short_name = '%s' order by achieved desc, t.id",
					App::$data['channel']['channel_id'],
					dbesc(argv(2))
				);
			$items = [];
			if ($r) {
				for($x = 0; $x < count($r); $x ++) {
					$items[$x] = ['trophy_name' => $r[$x]["trophy_name"], 'trophy_desc' => $r[$x]["trophy_desc"], 'achieved' => $r[$x]["achieved"], 'progress' => $r[$x]["progress"], 'max_progress' => $r[$x]["max_progress"] ];
				}
			}


			$sc = replace_macros(get_markup_template('gametrophy.tpl', 'addon/gamerzilla/'), [
				'$items' => $items, '$base_url' => "/gamerzilla/" . argv(1) . "/" . argv(2), '$name' => $g[0]['game_name']
			]);

			return $sc;
		}
		else if (argc() == 4)
		{
			$r_game = q("select photoid from gamerzilla_game g where g.short_name = '%s'",
					dbesc(argv(2))
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
			// return error
		}
		else
		{
			$r_game = q("select truephotoid, falsephotoid from gamerzilla_game g, gamerzilla_trophy t where g.short_name = '%s' and g.id = t.game_id and t.trophy_name = '%s'",
					dbesc(argv(2)),
					dbesc(argv(3))
				);
			if ($r_game) {
				if (argv(4) == "1")
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
			// return error
		}
	}

}




