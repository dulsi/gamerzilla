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
	}

	function post() {
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(),'gamerzilla'))
			return;

		check_form_security_token_redirectOnErr('gamerzilla', 'gamerzilla');

		set_pconfig(local_channel(), 'gamerzilla', 'some_setting', $_POST['some_setting']);

	}

	function get() {
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'gamerzilla')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Gamerzilla App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= t('A gamerzilla for addons, you can copy/paste');
			return $o;
		}
		if (argc() == 2)
		{
			$r = q("select short_name, game_name, (select count(*) from gamerzilla_userstat u2 where u2.achieved = 1 and g.id = u2.game_id and u2.uuid = %d) as earned, (select count(*) from gamerzilla_trophy t where g.id = t.game_id) as total_trophy from gamerzilla_game g where g.id in (select game_id from gamerzilla_userstat u where u.uuid = %d)",
					local_channel(),
					local_channel()
				);
			$items = [];
			if ($r) {
				for($x = 0; $x < count($r); $x ++) {
					$items[$x] = ['url' => "/gamerzilla/" . argv(1) . "/" . $r[$x]["short_name"], 'name' => $r[$x]["game_name"], 'earned' => $r[$x]["earned"], 'total' => $r[$x]["total_trophy"] ];
				}
			}


			$sc = replace_macros(get_markup_template('gamelist.tpl', 'addon/gamerzilla/'), [
				'$items' => $items
			]);
			return $sc;
		}
		else
		{
			$r = q("select trophy_name, trophy_desc, progress, max_progress, coalesce(achieved, 0) achieved from gamerzilla_game g, gamerzilla_trophy t left outer join gamerzilla_userstat u on t.game_id = u.game_id and t.id = u.trophy_id and u.uuid = %d where g.id = t.game_id and g.short_name = '%s' order by achieved desc, t.id",
					local_channel(),
					dbesc(argv(2))
				);
			$items = [];
			if ($r) {
				for($x = 0; $x < count($r); $x ++) {
					$items[$x] = ['trophy_name' => $r[$x]["trophy_name"], 'achieved' => $r[$x]["achieved"], 'progress' => $r[$x]["progress"], 'max_progress' => $r[$x]["max_progress"] ];
				}
			}


			$sc = replace_macros(get_markup_template('gametrophy.tpl', 'addon/gamerzilla/'), [
				'$items' => $items
			]);

			return $sc;
		}
	}

}




