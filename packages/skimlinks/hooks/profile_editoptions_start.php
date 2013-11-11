<?php

/**
 * Skimlinks vBulletin Plugin
 *
 * @author Skimlinks
 * @version 2.0.7
 * @copyright © 2011 Skimbit Ltd.
 */

if ($vbulletin->options['skimlinks_enabled'] AND !empty($vbulletin->options['skimlinks_pub_id']) AND $vbulletin->options['skimlinks_allow_user_disable'])
{
	if (! isset($_COOKIE['skimlinks_enabled'])) {
		$res = $vbulletin->db->query_read("SELECT `enabled` FROM `".TABLE_PREFIX."skimlinks` WHERE `userid` = ".$vbulletin->userinfo['userid']);
		if ($vbulletin->db->num_rows($res) > 0) {
			$row = $vbulletin->db->fetch_array($res);
			$skimlinks_enabled = $row['enabled'];
		} else {
			$skimlinks_enabled = 1;
		}
		@setcookie('skimlinks_enabled', $skimlinks_enabled);
	} else {
		$skimlinks_enabled = $_COOKIE['skimlinks_enabled'];
	}
	$skimlinks_checked = ($skimlinks_enabled == 1 ? 'checked="checked"' : '');

	if (version_compare($vbulletin->options['templateversion'], 4, '>='))
	{
		$templater = vB_Template::create('modifyoptions_skimlinks_vb4');
		$templater->register('skimlinks_checked', $skimlinks_checked);
		$template_hook['usercp_options_other'] .= $templater->render();
	}
	else
	{
		eval('$template_hook[\'usercp_options_other\'] .= "' . @fetch_template('modifyoptions_skimlinks_vb3') . '";');
	}
}