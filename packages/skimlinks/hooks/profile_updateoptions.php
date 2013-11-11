<?php

/**
 * Skimlinks vBulletin Plugin
 *
 * @author Skimlinks
 * @version 2.0.7
 * @copyright © 2011 Skimbit Ltd.
 */

if ($vbulletin->options['skimlinks_enabled'] AND $vbulletin->options['skimlinks_pub_id'] AND $vbulletin->options['skimlinks_allow_user_disable'])
{
	$skimlinks_enabled = $vbulletin->input->clean_gpc('p', 'skimlinks', TYPE_UINT);
	
	$res = $vbulletin->db->query_read("SELECT `enabled` FROM `".TABLE_PREFIX."skimlinks` WHERE `userid` = ".$vbulletin->userinfo['userid']);
	if ($vbulletin->db->num_rows($res) > 0) {
		$vbulletin->db->query_write("UPDATE `".TABLE_PREFIX."skimlinks` SET `enabled` = $skimlinks_enabled WHERE userid = ".$vbulletin->userinfo['userid']);
	} else {
		$vbulletin->db->query_write("INSERT INTO `".TABLE_PREFIX."skimlinks` (`userid`, `enabled`) VALUES ('".$vbulletin->userinfo['userid']."', '$skimlinks_enabled')");
	}	
	@setcookie('skimlinks_enabled', $skimlinks_enabled);
}