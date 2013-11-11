<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 44592 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('banning', 'cpuser');
$specialtemplates = array('banemail');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_banning.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array('username' => TYPE_STR));
if ($_POST['do'] != 'doliftban')
{
	log_admin_action(!empty($vbulletin->GPC['username']) ? 'username = ' . $vbulletin->GPC['username'] : '');
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_banning']);
$canbanuser = ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadminusers'] OR can_moderate(0, 'canbanusers')) ? true : false;
$canunbanuser = ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadmiusers'] OR can_moderate(0, 'canunbanusers')) ? true : false;

// check banning permissions
if (!$canbanuser AND !$canunbanuser)
{
	print_stop_message('no_permission_ban_users');
}

// set default action
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################
// lift a ban

if ($_REQUEST['do'] == 'liftban')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid' => TYPE_INT
	));

	if (!$canunbanuser)
	{
		print_stop_message('no_permission_un_ban_users');
	}

	$user = $db->query_first("
		SELECT user.*,
		userban.usergroupid, userban.displaygroupid, userban.customtitle, userban.usertitle,
		IF(userban.userid, 1, 0) AS banrecord,
		IF(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ", 0, 1) AS isnotbannedgroup
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
	");

	// check we got a record back and that the returned user is in a banned group
	if (!$user OR !$user['isnotbannedgroup'])
	{
		print_stop_message('invalid_user_specified');
	}

	if (is_unalterable_user($user['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	// show confirmation message
	print_form_header('banning', 'doliftban');
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_table_header($vbphrase['lift_ban']);
	print_description_row(construct_phrase($vbphrase['confirm_ban_lift_on_x'], $user['username']));
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

if ($_POST['do'] == 'doliftban')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'userid' => TYPE_INT
	));

	if (!$canunbanuser)
	{
		print_stop_message('no_permission_un_ban_users');
	}

	$user = $db->query_first("
		SELECT user.*,
			userban.usergroupid AS banusergroupid, userban.displaygroupid AS bandisplaygroupid, userban.customtitle AS bancustomtitle, userban.usertitle AS banusertitle,
			IF(userban.userid, 1, 0) AS banrecord,
			IF(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ", 0, 1) AS isnotbannedgroup
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON(usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
	");

	// check we got a record back and that the returned user is in a banned group
	if (!$user OR !$user['isnotbannedgroup'])
	{
		print_stop_message('invalid_user_specified');
	}

	if (is_unalterable_user($user['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	// get usergroup info
	$getusergroupid = iif($user['bandisplaygroupid'], $user['bandisplaygroupid'], $user['banusergroupid']);
	if (!$getusergroupid)
	{
		$getusergroupid = 2; // ack! magic numbers!
	}

	$usergroup = $vbulletin->usergroupcache["$getusergroupid"];
	if ($user['bancustomtitle'])
	{
		$usertitle = $user['banusertitle'];
	}
	else if (!$usergroup['banusertitle'])
	{
		$gettitle = $db->query_first("
			SELECT title
			FROM " . TABLE_PREFIX . "usertitle
			WHERE minposts <= $user[posts]
			ORDER BY minposts DESC
		");
		$usertitle = $gettitle['title'];
	}
	else
	{
		$usertitle = $usergroup['banusertitle'];
	}

	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
	$userdm->set_existing($user);
	$userdm->set('usertitle', $usertitle);
	$userdm->set('posts', $user['posts']); // This will activate the rank update

	// check to see if there is a ban record for this user
	if ($user['banrecord'])
	{
		$userdm->set('usergroupid', $user['banusergroupid']);
		$userdm->set('displaygroupid', $user['bandisplaygroupid']);
		$userdm->set('customtitle', $user['bancustomtitle']);

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "userban
			WHERE userid = $user[userid]
		");
	}
	else
	{
		$userdm->set('usergroupid', 2);
		$user['usergroupid'] = 2;
		$userdm->set('displaygroupid', 0);
		$user['displaygroupid'] = 0;
	}

	$userdm->save();
	unset($userdm);

	log_admin_action(!empty($user['username']) ? 'username = ' . $user['username'] : 'userid = ' . $vbulletin->GPC['userid']);

	define('CP_REDIRECT', 'banning.php');
	print_stop_message('lifted_ban_on_user_x_successfully', "<b>$user[username]</b>");
}

// #############################################################################
// ban a user

if ($_POST['do'] == 'dobanuser')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'usergroupid' => TYPE_INT,
		'period'      => TYPE_STR,
		'reason'      => TYPE_NOHTML
	));

	$vbulletin->GPC['username'] = htmlspecialchars_uni($vbulletin->GPC['username']);

	if (!$canbanuser)
	{
		print_stop_message('no_permission_ban_users');
	}

	/*$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
	echo "
	<p>Period: {$vbulletin->GPC['period']}</p>
	<p>Banning <b>{$vbulletin->GPC['username']}</b> into usergroup <i>" . $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['title'] . "</i></p>
	<table>
	<tr><td>Time now:</td><td>" . vbdate('g:ia l jS F Y', TIMENOW, false, false) . "</td></tr>
	<tr><td>Lift date:</td><td>" . vbdate('g:ia l jS F Y', $liftdate, false, false) . "</td></tr>
	</table>";
	exit;*/

	// check that the target usergroup is valid
	if (!isset($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]) OR ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
	{
		print_stop_message('invalid_usergroup_specified');
	}

	// check that the user exists
	$user = $db->query_first("
		SELECT user.*,
			IF(moderator.moderatorid IS NULL, 0, 1) AS ismoderator
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(moderator.userid = user.userid AND moderator.forumid <> -1)
		WHERE user.username = '" . $db->escape_string($vbulletin->GPC['username']) . "'
	");
	if (!$user OR $user['userid'] == $vbulletin->userinfo['userid'])
	{
		print_stop_message('invalid_user_specified');
	}

	if (is_unalterable_user($user['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	cache_permissions($user);

	// Non-admins can't ban administrators, supermods or moderators
	if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if ($user['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR $user['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'] OR $user['ismoderator'])
		{
			print_stop_message('no_permission_ban_non_registered_users');
		}
	}
	else if ($user['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		print_stop_message('no_permission_ban_non_registered_users');
	}

	// check that the number of days is valid
	if ($vbulletin->GPC['period'] != 'PERMANENT' AND !preg_match('#^(D|M|Y)_[1-9][0-9]?$#', $vbulletin->GPC['period']))
	{
		print_stop_message('invalid_ban_period_specified');
	}

	// if we've got this far all the incoming data is good
	if ($vbulletin->GPC['period'] == 'PERMANENT')
	{
		// make this ban permanent
		$liftdate = 0;
	}
	else
	{
		// get the unixtime for when this ban will be lifted
		$liftdate = convert_date_to_timestamp($vbulletin->GPC['period']);
	}


	// check to see if there is already a ban record for this user in the userban table
	if ($check = $db->query_first("SELECT userid, liftdate FROM " . TABLE_PREFIX . "userban WHERE userid = $user[userid]"))
	{
		if ($liftdate AND $liftdate < $check['liftdate'])
		{
			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadminusers']) AND !can_moderate(0, 'canunbanusers'))
			{
				print_stop_message('no_permission_un_ban_users');
			}
		}

		// there is already a record - just update this record
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "userban SET
			bandate = " . TIMENOW . ",
			liftdate = $liftdate,
			adminid = " . $vbulletin->userinfo['userid'] . ",
			reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
			WHERE userid = $user[userid]
		");
	}
	else
	{
		// insert a record into the userban table
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "userban
			(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate, reason)
			VALUES
			($user[userid], $user[usergroupid], $user[displaygroupid], $user[customtitle], '" . $db->escape_string($user['usertitle']) . "', " . $vbulletin->userinfo['userid'] . ", " . TIMENOW . ", $liftdate, '" . $db->escape_string($vbulletin->GPC['reason']) . "')
		");
	}

	// update the user record
	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
	$userdm->set_existing($user);
	$userdm->set('usergroupid', $vbulletin->GPC['usergroupid']);
	$userdm->set('displaygroupid', 0);

	// update the user's title if they've specified a special user title for the banned group
	if ($vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle'] != '')
	{
		$userdm->set('usertitle', $vbulletin->usergroupcache["{$vbulletin->GPC['usergroupid']}"]['usertitle']);
		$userdm->set('customtitle', 0);
	}

	$userdm->save();
	unset($userdm);

	define('CP_REDIRECT', 'banning.php');
	if ($vbulletin->GPC['period'] == 'PERMANENT')
	{
		print_stop_message('user_x_has_been_banned_permanently', $user['username']);
	}
	else
	{
		print_stop_message('user_x_has_been_banned_until_y', $user['username'], vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $liftdate));
	}
}

// #############################################################################
// user banning form

if ($_REQUEST['do'] == 'banuser')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'userid'   => TYPE_INT,
		'period'   => TYPE_STR
	));

	if (!$canbanuser)
	{
		print_stop_message('no_permission_ban_users');
	}

	// fill in the username field if it's specified
	if (!$vbulletin->GPC['username'] AND $vbulletin->GPC['userid'])
	{
		$user = $db->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = " . $vbulletin->GPC['userid']);
		$vbulletin->GPC['username'] = $user['username'];
	}
	else
	{
		$vbulletin->GPC['username'] = htmlspecialchars_uni($vbulletin->GPC['username']);
	}

	// set a default banning period if there isn't one specified
	if (empty($vbulletin->GPC['period']))
	{
		$vbulletin->GPC['period'] = 'D_7'; // 7 days
	}

	// make a list of usergroups into which to move this user
	$selectedid = 0;
	$usergroups = array();
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			$usergroups["$usergroupid"] = $usergroup['title'];
			if ($selectedid == 0)
			{
				$selectedid = $usergroupid;
			}
		}
	}
	if (empty($usergroups))
	{
		print_stop_message('no_groups_defined_as_banned');
	}

	$temporary_phrase = $vbphrase['temporary_ban_options'];
	$permanent_phrase = $vbphrase['permanent_ban_options'];

	// make a list of banning period options
	$periodoptions = array(
		$temporary_phrase => array(
			'D_1'  => "1 $vbphrase[day]",
			'D_2'  => "2 $vbphrase[days]",
			'D_3'  => "3 $vbphrase[days]",
			'D_4'  => "4 $vbphrase[days]",
			'D_5'  => "5 $vbphrase[days]",
			'D_6'  => "6 $vbphrase[days]",
			'D_7'  => "7 $vbphrase[days]",
			'D_10' => "10 $vbphrase[days]",
			'D_14' => "2 $vbphrase[weeks]",
			'D_21' => "3 $vbphrase[weeks]",
			'M_1'  => "1 $vbphrase[month]",
			'M_2' => "2 $vbphrase[months]",
			'M_3' => "3 $vbphrase[months]",
			'M_4' => "4 $vbphrase[months]",
			'M_5' => "5 $vbphrase[months]",
			'M_6' => "6 $vbphrase[months]",
			'Y_1' => "1 $vbphrase[year]",
			'Y_2' => "2 $vbphrase[years]",
		),
		$permanent_phrase => array(
			'PERMANENT' => "$vbphrase[permanent] - $vbphrase[never_lift_ban]"
		)
	);

	foreach ($periodoptions["$temporary_phrase"] AS $thisperiod => $text)
	{
		if ($liftdate = convert_date_to_timestamp($thisperiod))
		{
			$periodoptions["$temporary_phrase"]["$thisperiod"] .= ' (' . vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $liftdate) . ')';
		}
	}

	print_form_header('banning', 'dobanuser');
	print_table_header($vbphrase['ban_user']);
	print_input_row($vbphrase['username'], 'username', $vbulletin->GPC['username'], 0);
	print_select_row($vbphrase['move_user_to_usergroup'], 'usergroupid', $usergroups, $selectedid);
	print_select_row($vbphrase['lift_ban_after'], 'period', $periodoptions, $vbulletin->GPC['period']);
	print_input_row($vbphrase['user_ban_reason'], 'reason', '', true, 50, 250);
	print_submit_row($vbphrase['ban_user']);
}

if ($_POST['do'] == 'updatereason')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid' => TYPE_INT,
		'reason' => TYPE_NOHTML
	));

	if (!$canbanuser)
	{
		print_stop_message('no_permission_ban_users');
	}

	// check to see if there is already a ban record for this user in the userban table
	if ($check = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "userban WHERE userid = " . $vbulletin->GPC['userid']))
	{
		// Update the reason
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "userban SET
			reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
			WHERE userid = $check[userid]
		");

		define('CP_REDIRECT', 'banning.php');
		print_stop_message('ban_reason_updated');
	}
	else
	{
		print_stop_message('invalid_user_specified');
	}
}

if ($_REQUEST['do'] == 'editreason')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'     => TYPE_INT,
		'editreason' => TYPE_BOOL
	));

	if (!$canbanuser)
	{
		print_stop_message('no_permission_ban_users');
	}

	if (!($oldban = $db->query_first("SELECT user.userid, user.username, userban.reason FROM " . TABLE_PREFIX . "userban AS userban INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=userban.userid) WHERE user.userid = " . $vbulletin->GPC['userid'])))
	{
		print_stop_message('invalid_user_specified');
	}

	$vbulletin->GPC['username'] = $oldban['username'];

	print_form_header('banning', 'updatereason');
	print_table_header($vbphrase['ban_user']);

	construct_hidden_code('userid', $oldban['userid']);
	print_label_row($vbphrase['username'], $vbulletin->GPC['username']);

	print_input_row($vbphrase['user_ban_reason'], 'reason', $oldban['reason'], false, 50, 250);
	print_submit_row($vbphrase['ban_user']);
}

// #############################################################################
// display users from 'banned' usergroups

if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber'   => TYPE_UINT,
	));

	$perpage = 100;
	if (!$vbulletin->GPC['pagenumber'])
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}
	$start = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

	function construct_banned_user_row($user)
	{
		global $vbulletin, $vbphrase;

		if ($user['liftdate'] == 0)
		{
			$user['banperiod'] = $vbphrase['permanent'];
			$user['banlift'] = $vbphrase['never'];
			$user['banremaining'] = $vbphrase['forever'];
		}
		else
		{
			$user['banlift'] = vbdate($vbulletin->options['dateformat'] . ', ~' . $vbulletin->options['timeformat'], $user['liftdate']);
			$user['banperiod'] = ceil(($user['liftdate'] - $user['bandate']) / 86400);
			if ($user['banperiod'] == 1)
			{
				$user['banperiod'] .= " $vbphrase[day]";
			}
			else
			{
				$user['banperiod'] .= " $vbphrase[days]";
			}

			$remain = $user['liftdate'] - TIMENOW;
			$remain_days = floor($remain / 86400);
			$remain_hours = ceil(($remain - ($remain_days * 86400)) / 3600);
			if ($remain_hours == 24)
			{
				$remain_days += 1;
				$remain_hours = 0;
			}

			if ($remain_days < 0)
			{
				$user['banremaining'] = "<i>$vbphrase[will_be_lifted_soon]</i>";
			}
			else
			{
				if ($remain_days == 1)
				{
					$day_word = $vbphrase['day'];
				}
				else
				{
					$day_word = $vbphrase['days'];
				}
				if ($remain_hours == 1)
				{
					$hour_word = $vbphrase['hour'];
				}
				else
				{
					$hour_word = $vbphrase['hours'];
				}
				$user['banremaining'] = "$remain_days $day_word, $remain_hours $hour_word";
			}
		}
		$cell = array("<a href=\"" . iif(($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadminusers']), '../' . $vbulletin->config['Misc']['admincpdir'] . '/') . 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$user[userid]\"><b>$user[username]</b></a>");
		if ($user['bandate'])
		{
			$cell[] = iif($user['adminid'], "<a href=\"" . iif(($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadminusers']), '../' . $vbulletin->config['Misc']['admincpdir'] . '/') . 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$user[adminid]\">$user[adminname]</a>", $vbphrase['n_a']);
			$cell[] = vbdate($vbulletin->options['dateformat'], $user['bandate']);
		}
		else
		{
			$cell[] = $vbphrase['n_a'];
			$cell[] = $vbphrase['n_a'];
		}
		$cell[] = $user['banperiod'];
		$cell[] = $user['banlift'];
		$cell[] = $user['banremaining'];
		if (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadminusers']) OR can_moderate(0, 'canunbanusers'))
		{
			$cell[] = construct_link_code($vbphrase['lift_ban'], 'banning.php?' . $vbulletin->session->vars['sessionurl'] . "do=liftban&amp;u=$user[userid]");
		}

		$cell[] = construct_link_code(!empty($user['reason']) ? $user['reason'] : $vbphrase['n_a'], 'banning.php?' . $vbulletin->session->vars['sessionurl'] . "do=editreason&amp;userid=" . $user['userid']);

		return $cell;
	}

	$querygroups = array();
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			$querygroups["$usergroupid"] = $usergroup['title'];
		}
	}
	if (empty($querygroups))
	{
		print_stop_message('no_groups_defined_as_banned');
	}

	// define the column headings
	$headercell = array(
		$vbphrase['username'],
		$vbphrase['banned_by'],
		$vbphrase['banned_on'],
		$vbphrase['ban_period'],
		$vbphrase['ban_will_be_lifted_on'],
		$vbphrase['ban_time_remaining']
	);
	if (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['canadminusers']) OR can_moderate(0, 'canunbanusers'))
	{
		$headercell[] = $vbphrase['lift_ban'];
	}
	$headercell[] = $vbphrase['ban_reason'];

	$havebanned = false;

	// now query users from the specified groups that are temporarily banned
	$tempusers = $db->query_read("
		SELECT user.userid, user.username, user.usergroupid AS busergroupid,
			userban.usergroupid AS ousergroupid,
			IF(userban.displaygroupid = 0, userban.usergroupid, userban.displaygroupid) AS odisplaygroupid,
			bandate, liftdate, reason,
			adminuser.userid AS adminid, adminuser.username AS adminname
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userban.adminid)
		WHERE user.usergroupid IN(" . implode(',', array_keys($querygroups)) . ")
			AND userban.liftdate <> 0
		ORDER BY userban.liftdate ASC, user.username
	");
	if ($db->num_rows($tempusers))
	{
		$havebanned = true;

		// we're only going to show temporarily banned users on page 1
		if ($vbulletin->GPC['pagenumber'] == 1)
		{
			print_form_header('banning', 'banuser');
			print_table_header("$vbphrase[banned_users]: $vbphrase[temporary_ban] <span class=\"normal\">$vbphrase[usergroups]: " . implode(', ', $querygroups) . "</span>", 8);
			print_cells_row($headercell, 1);
			while ($user = $db->fetch_array($tempusers))
			{
				print_cells_row(construct_banned_user_row($user));
			}
			print_description_row("<div class=\"smallfont\" align=\"center\">$vbphrase[all_times_are_gmt_x_time_now_is_y]</div>", 0, 8, 'thead');
			if ($canbanuser)
			{
				print_submit_row($vbphrase['ban_user'], 0, 8);
			}
			else
			{
				print_table_footer();
			}
		}
	}

	$db->free_result($tempusers);

	// now query users from the specified groups that are permanently banned
	$permusercount = $db->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userban.adminid)
		WHERE user.usergroupid IN(" . implode(',', array_keys($querygroups)) . ")
			AND (userban.liftdate = 0 OR userban.liftdate = NULL)
	");
	if ($permusercount['count'])
	{
		$havebanned = true;

		$pagecount = ceil($permusercount['count'] / $perpage);

		$permusers = $db->query_read("
			SELECT user.userid, user.username, user.usergroupid AS busergroupid,
				userban.usergroupid AS ousergroupid,
				IF(userban.displaygroupid = 0, userban.usergroupid, userban.displaygroupid) AS odisplaygroupid,
				bandate, liftdate, reason,
				adminuser.userid AS adminid, adminuser.username AS adminname
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON(userban.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userban.adminid)
			WHERE user.usergroupid IN(" . implode(',', array_keys($querygroups)) . ")
				AND (userban.liftdate = 0 OR userban.liftdate = NULL)
			ORDER BY user.username
			LIMIT $start, $perpage
		");

		print_form_header('banning', 'banuser');
		construct_hidden_code('period', 'PERMANENT');
		print_table_header("$vbphrase[banned_users]: $vbphrase[permanent_ban] <span class=\"normal\">$vbphrase[usergroups]: " . implode(', ', $querygroups) . '</span>', 8);
		if ($pagecount > 1)
		{
			$pagenav = "<strong>$vbphrase[go_to_page]</strong>";
			for ($thispage = 1; $thispage <= $pagecount; $thispage++)
			{
				if ($thispage == $vbulletin->GPC['pagenumber'])
				{
					$pagenav .= " <strong>[$thispage]</strong> ";
				}
				else
				{
					$pagenav .= " <a href=\"banning.php?$session[sessionurl]do=modify&amp;page=$thispage\" class=\"normal\">$thispage</a> ";
				}
			}

			print_description_row($pagenav, false, 8, '', 'right');
		}

		print_cells_row($headercell, 1);
		while ($user = $db->fetch_array($permusers))
		{
			print_cells_row(construct_banned_user_row($user));
		}
		print_submit_row($vbphrase['ban_user'], 0, 8);
	}

	if (!$havebanned)
	{
		if ($canbanuser)
		{
			print_stop_message('no_users_banned_from_x_board_click_here', '<b>' . $vbulletin->options['bbtitle'] . '</b>', 'banning.php?' . $vbulletin->session->vars['sessionurl'] . 'do=banuser');
		}
		else
		{
			print_stop_message('no_users_banned_from_x_board', '<b>' . $vbulletin->options['bbtitle'] . '</b>');
		}
	}

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44592 $
|| ####################################################################
\*======================================================================*/
?>