<?php

/**
 * Skimlinks vBulletin Plugin
 *
 * @author Skimlinks
 * @version 2.0.7
 * @copyright © 2011 Skimbit Ltd.
 */

function disableSkimProduct($product, $threadinfo)
{
	global $vbulletin;

	if ($threadinfo['lastpost'] > TIMENOW - 86400 * $vbulletin->options["{$product}_thread_age_limit"])
	{
		return 'true';
	}

	$disableForum = json_decode($vbulletin->options["{$product}_disable_forums"]);
	if (is_array($disableForum) AND in_array($threadinfo['forumid'], $disableForum))
	{
		return 'true';
	}

	$disabledGroups = json_decode($vbulletin->options["{$product}_disable_groups"]);
	if (is_array($disabledGroups) AND is_member_of($vbulletin->userinfo, $disabledGroups))
	{
		return 'true';
	}

	return 'false';
}

$_skimEnabled = ($vbulletin->options['skimlinks_enabled'] AND !empty($vbulletin->options['skimlinks_pub_id']));


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


$_skimUserDisabled = ($vbulletin->options['skimlinks_allow_user_disable'] AND $skimlinks_enabled == 0);

if ($_skimEnabled AND !$_skimUserDisabled)
{
	$headinclude .= '<script type="text/javascript"> vBulletin.add_event("SkimlinksActivate"); </script>';
	$footer .= '<script type="text/javascript">

		var noskimlinks = ' . disableSkimProduct('skimlinks', $threadinfo) . ',
			noskimwords = ' . disableSkimProduct('skimwords', $threadinfo) . ',
			skimlinks_product = \'vbulletin\';

		vBulletin.events.SkimlinksActivate.fire();

	</script>
	<script type="text/javascript" src="http://s.skimresources.com/js/' . $vbulletin->options['skimlinks_pub_id'] . '.skimlinks.js"></script>';
}