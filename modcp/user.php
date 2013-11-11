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
define('CVS_REVISION', '$RCSfile$ - $Revision: 42666 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('banning', 'cpuser', 'forum', 'timezone', 'user', 'cprofilefield', 'profilefield');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_profilefield.php');
require_once(DIR . '/includes/adminfunctions_user.php');

if ($_REQUEST['do'] == 'edit')
{
	$_REQUEST['do'] = 'viewuser';
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array('userid' => TYPE_INT));
log_admin_action(iif($vbulletin->GPC['userid']!=0, 'user id = ' . $vbulletin->GPC['userid'], ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_manager']);

// ############################# start do ips #########################
if ($_REQUEST['do'] == 'doips')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'depth'     => TYPE_INT,
		'username'  => TYPE_STR,
		'ipaddress' => TYPE_NOHTML,
	));

	if (!can_moderate(0, 'canviewips'))
	{
		print_stop_message('no_permission_ips');
	}

	if (($vbulletin->GPC['username'] OR $vbulletin->GPC['userid'] OR $vbulletin->GPC['ipaddress']) AND $_POST['do'] != 'doips')
	{
		// we're doing a search of some type, that's not submitted via post,
		// so we need to verify the CP sessionhash
		verify_cp_sessionhash();
	}

	// the following is now a direct copy of the contents of doips from admincp/user.php
	if (function_exists('set_time_limit') AND !SAFEMODE)
	{
		@set_time_limit(0);
	}

	if (empty($vbulletin->GPC['depth']))
	{
		$vbulletin->GPC['depth'] = 1;
	}

	if (!empty($vbulletin->GPC['username']))
	{
		if ($getuserid = $db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['username'])) . "'
		"))
		{
			$vbulletin->GPC['userid'] =& $getuserid['userid'];
		}
		else
		{
			print_stop_message('invalid_user_specified');
		}

		$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
		if (!$userinfo)
		{
			print_stop_message('invalid_user_specified');
		}
	}
	else if (!empty($vbulletin->GPC['userid']))
	{
		$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
		if (!$userinfo)
		{
			print_stop_message('invalid_user_specified');
		}
		$vbulletin->GPC['username'] = unhtmlspecialchars($userinfo['username']);
	}

	if (!empty($vbulletin->GPC['ipaddress']) OR !empty($vbulletin->GPC['userid']))
	{
		if ($vbulletin->GPC['ipaddress'])
		{
			print_form_header('', '');
			print_table_header(construct_phrase($vbphrase['ip_address_search_for_ip_address_x'], $vbulletin->GPC['ipaddress']));
			$hostname = @gethostbyaddr($vbulletin->GPC['ipaddress']);
			if (!$hostname OR $hostname == $vbulletin->GPC['ipaddress'])
			{
				$hostname = $vbphrase['could_not_resolve_hostname'];
			}
			print_description_row('<div style="margin-' . vB_Template_Runtime::fetchStyleVar('left') . ':20px"><a href="user.php?' . $vbulletin->session->vars['sessionurl'] . 'do=gethost&amp;ip=' . $vbulletin->GPC['ipaddress'] . '">' . $vbulletin->GPC['ipaddress'] . "</a> : <b>$hostname</b></div>");

			$results = construct_ip_usage_table($vbulletin->GPC['ipaddress'], 0, $vbulletin->GPC['depth']);
			print_description_row($vbphrase['post_ip_addresses'], false, 2, 'thead');
			print_description_row($results ? $results : $vbphrase['no_matches_found']);

			$results = construct_ip_register_table($vbulletin->GPC['ipaddress'], 0, $vbulletin->GPC['depth']);
			print_description_row($vbphrase['registration_ip_addresses'], false, 2, 'thead');
			print_description_row($results ? $results : $vbphrase['no_matches_found']);

			print_table_footer();
		}

		if ($vbulletin->GPC['userid'])
		{
			print_form_header('', '');
			print_table_header(construct_phrase($vbphrase['ip_address_search_for_user_x'], htmlspecialchars_uni($vbulletin->GPC['username'])));
			print_label_row($vbphrase['registration_ip_address'], $userinfo['ipaddress']);

			$results = construct_user_ip_table($vbulletin->GPC['userid'], 0, $vbulletin->GPC['depth']);
			print_description_row($vbphrase['post_ip_addresses'], false, 2, 'thead');
			print_description_row($results ? $results : $vbphrase['no_matches_found']);

			print_table_footer();
		}
	}

	print_form_header('user', 'doips');
	print_table_header($vbphrase['search_ip_addresses']);
	print_input_row($vbphrase['find_users_by_ip_address'], 'ipaddress', $vbulletin->GPC['ipaddress'], 0);
	print_input_row($vbphrase['find_ip_addresses_for_user'], 'username', $vbulletin->GPC['username']);
	print_select_row($vbphrase['depth_to_search'], 'depth', array(1 => 1, 2 => 2), $vbulletin->GPC['depth']);
	print_submit_row($vbphrase['find']);
}

// ############################# start gethost #########################
if ($_REQUEST['do'] == 'gethost')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'ip' => TYPE_NOHTML
	));

	print_form_header('', '');
	print_table_header($vbphrase['ip_address']);
	print_label_row($vbphrase['ip_address'], $vbulletin->GPC['ip']);
	$resolvedip = @gethostbyaddr($vbulletin->GPC['ip']);
	if ($resolvedip == $vbulletin->GPC['ip'])
	{
		print_label_row($vbphrase['host_name'], '<i>' . $vbphrase['n_a'] . '</i>');
	}
	else
	{
		print_label_row($vbphrase['host_name'], "<b>$resolvedip</b>");
	}
	print_table_footer();
}

// ###################### Start find #######################
if ($_REQUEST['do'] == 'find')
{
	if (!can_moderate(0, 'canunbanusers') AND !can_moderate(0, 'canbanusers') AND !can_moderate(0, 'canviewprofile') AND !can_moderate(0, 'caneditsigs') AND !can_moderate(0, 'caneditavatar'))
	{
		print_stop_message('no_permission_search_users');
	}

	print_form_header('user', 'findnames');
	print_table_header($vbphrase['search_users']);
	print_input_row($vbphrase['username'], 'findname');
	print_yes_no_row($vbphrase['exact_match'], 'exact', 0);
	print_submit_row($vbphrase['search']);
}

// ###################### Start findname #######################
if ($_REQUEST['do'] == 'findnames')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'findname' => TYPE_NOHTML,
		'exact'    => TYPE_STR, // leave this as str because the main page sends a string value through
	));

	$canbanusers = can_moderate(0, 'canbanusers');
	$canunbanusers = can_moderate(0, 'canunbanusers');
	$canviewprofile = can_moderate(0, 'canviewprofile');
	$caneditsigs = can_moderate(0, 'caneditsigs');
	$caneditavatar = can_moderate(0, 'caneditavatar');
	$caneditprofilepic = can_moderate(0, 'caneditprofilepic');
	$caneditreputation = iif(can_moderate(0, 'caneditreputation') AND $vbulletin->options['reputationenable'], true);
	if (!$canbanusers AND !$canunbanusers AND !$canviewprofile AND !$caneditsigs AND !$caneditavatar AND !$caneditprofilepic AND !$caneditreputation)
	{
		print_stop_message('no_permission_search_users');
	}

	if (empty($vbulletin->GPC['findname']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['exact'])
	{
		$condition = "username = '" . $db->escape_string($vbulletin->GPC['findname']) . "'";
	}
	else
	{
		$condition = "username LIKE '%" . $db->escape_string_like($vbulletin->GPC['findname']) . "%'";
	}

	// get banned usergroups
	$querygroups = array('0' => true);
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if (!($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			$querygroups["$usergroupid"] = $usergroup['title'];
		}
	}

	$users = $db->query_read("
		SELECT userid, username, usergroupid IN(" . implode(',', array_keys($querygroups)) . ") AS inbannedgroup
		FROM " . TABLE_PREFIX . "user
		WHERE $condition
		ORDER BY username
	");
	if ($db->num_rows($users) > 0)
	{
		print_form_header('', '', 0, 1, 'cpform', '70%');
		print_table_header(construct_phrase($vbphrase['showing_users_x_to_y_of_z'], '1', $db->num_rows($users), $db->num_rows($users)), 8);
		while ($user = $db->fetch_array($users))
		{
			$cell = array("<b>$user[username]</b>");

			if ($canbanusers AND !$user['inbannedgroup'])
			{
				$cell[] = '<span class="smallfont">' . construct_link_code($vbphrase['ban_user'], 'banning.php?' . $vbulletin->session->vars['sessionurl'] . "do=banuser&amp;u=$user[userid]") . '</span>';
			}
			elseif ($canunbanusers AND $user['inbannedgroup'])
			{
				$cell[] = '<span class="smallfont">' . construct_link_code($vbphrase['lift_ban'], 'banning.php?' . $vbulletin->session->vars['sessionurl'] . "do=liftban&amp;u=$user[userid]") . '</span>';
			}
			else
			{
				$cell[] = '';
			}

			$cell[] = iif($canviewprofile, '<span class="smallfont">' . construct_link_code($vbphrase['view_profile'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&amp;u=$user[userid]") . '</span>');
			$cell[] = iif($caneditsigs, '<span class="smallfont">' . construct_link_code($vbphrase['change_signature'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=editsig&amp;u=$user[userid]") . '</span>');
			$cell[] = iif($caneditavatar, '<span class="smallfont">' . construct_link_code($vbphrase['change_avatar'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=avatar&amp;u=$user[userid]") . '</span>');
			$cell[] = iif($caneditprofilepic, '<span class="smallfont">' . construct_link_code($vbphrase['change_profile_picture'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=profilepic&amp;u=$user[userid]") . '</span>');
			$cell[] = iif($caneditsigs, '<span class="smallfont">' . construct_link_code($vbphrase['change_signature_picture'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=sigpic&amp;u=$user[userid]") . '</span>');
			$cell[] = iif($caneditreputation, '<span class="smallfont">' . construct_link_code($vbphrase['edit_reputation'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=reputation&amp;u=$user[userid]") . '</span>');

			print_cells_row($cell);
		}
		print_table_footer();
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// ###################### Start viewuser #######################
if ($_REQUEST['do'] == 'viewuser')
{
	if (!can_moderate(0, 'canviewprofile'))
	{
		print_stop_message('no_permission');
	}

	$OUTERTABLEWIDTH = '95%';
	$INNERTABLEWIDTH = '100%';

	if (empty($vbulletin->GPC['userid']))
	{
		print_stop_message('invalid_user_specified');
	}

	$user = $db->query_first("
		SELECT user.*,usertextfield.signature,avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar,
			customavatar.width AS avatarwidth, customavatar.height AS avatarheight, customprofilepic.height AS profilepicheight,
			customprofilepic.width AS profilepicwidth, NOT ISNULL(sigpic.userid) AS hassigpic, sigpic.width AS sigpicwidth, 
			sigpic.height AS sigpicheight,
			customavatar.dateline AS avatardateline, customprofilepic.userid AS profilepic, customprofilepic.dateline AS 
			profilepicdateline, sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON avatar.avatarid = user.avatarid
		LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON customavatar.userid = user.userid
		LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON customprofilepic.userid = user.userid
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON sigpic.userid = user.userid
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
	");
	$user = array_merge($user, convert_bits_to_array($user['options'], $vbulletin->bf_misc_useroptions));
	$user = array_merge($user, convert_bits_to_array($user['adminoptions'], $vbulletin->bf_misc_adminoptions));

	// get threaded mode options
	if ($user['threadedmode'] == 1 OR $user['threadedmode'] == 2)
	{
		$threaddisplaymode = $user['threadedmode'];
	}
	else
	{
		if ($user['postorder'] == 0)
		{
			$threaddisplaymode = 0;
		}
		else
		{
			$threaddisplaymode = 3;
		}
	}

	$userfield = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "userfield WHERE userid=" . $vbulletin->GPC['userid']);

	// make array for daysprune menu
	$pruneoptions = array(
		'0'    => '- ' . $vbphrase['use_forum_default'] . ' -',
		'1'    => $vbphrase['show_threads_from_last_day'],
		'2'    => construct_phrase($vbphrase['show_threads_from_last_x_days'], 2),
		'7'    => $vbphrase['show_threads_from_last_week'],
		'10'   => construct_phrase($vbphrase['show_threads_from_last_x_days'], 10),
		'14'   => construct_phrase($vbphrase['show_threads_from_last_x_weeks'], 2),
		'30'   => $vbphrase['show_threads_from_last_month'],
		'45'   => construct_phrase($vbphrase['show_threads_from_last_x_days'], 45),
		'60'   => construct_phrase($vbphrase['show_threads_from_last_x_months'], 2),
		'75'   => construct_phrase($vbphrase['show_threads_from_last_x_days'], 75),
		'100'  => construct_phrase($vbphrase['show_threads_from_last_x_days'], 100),
		'365'  => $vbphrase['show_threads_from_last_year'],
		'-1'   => $vbphrase['show_all_threads']
	);
	if ($pruneoptions["$user[daysprune]"] == '')
	{
		$pruneoptions["$user[daysprune]"] = construct_phrase($vbphrase['show_threads_from_last_x_days'], $user['daysprune']);
	}

	($hook = vBulletinHook::fetch_hook('useradmin_edit_start')) ? eval($hook) : false;

	print_form_header('user', 'viewuser', 0, 0);
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	?>
	<table cellpadding="0" cellspacing="0" border="0" width="<?php echo $OUTERTABLEWIDTH; ?>" align="center"><tr valign="top"><td>
	<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
	<?php

	// start main table
	require_once(DIR . '/includes/functions_misc.php');

	// PROFILE SECTION
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user'], $user['username'], $user['userid']));
	print_input_row($vbphrase['username'], 'user[username]', $user['username'], 0);
	print_input_row($vbphrase['email'], 'user[email]', $user['email'], 0);
	print_select_row($vbphrase['language'], 'user[languageid]', fetch_language_titles_array('', 0), $user['languageid'] );
	print_input_row($vbphrase['user_title'], 'user[usertitle]', $user['usertitle']);
	print_yes_no_row($vbphrase['custom_user_title'], 'options[customtitle]', $user['customtitle']);
	print_input_row($vbphrase['home_page'], 'user[homepage]', $user['homepage'], 0);
	print_time_row($vbphrase['birthday'], 'birthday', $user['birthday'], 0, 1);
	print_select_row($vbphrase['privacy'], 'user[showbirthday]', array(
		0 => $vbphrase['hide_age_and_dob'],
		1 => $vbphrase['display_age'],
		3 => $vbphrase['display_day_and_month'],
		2 => $vbphrase['display_age_and_dob']
	), $user['showbirthday']);
	print_textarea_row($vbphrase['signature'] . iif(can_moderate(0, 'caneditsigs'), '<br /><br />' . construct_link_code($vbphrase['edit_signature'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=editsig&amp;u=$user[userid]")), 'signature', $user['signature'], 8, 45, 1, 0);
	print_input_row($vbphrase['icq_uin'], 'user[icq]', $user['icq'], 0);
	print_input_row($vbphrase['aim_screen_name'], 'user[aim]', $user['aim'], 0);
	print_input_row($vbphrase['yahoo_id'], 'user[yahoo]', $user['yahoo'], 0);
	print_input_row($vbphrase['msn_id'], 'user[msn]', $user['msn'], 0);
	print_input_row($vbphrase['skype_name'], 'user[skype]', $user['skype'], 0);
	print_yes_no_row($vbphrase['coppa_user'], 'options[coppauser]', $user['coppauser']);
	print_input_row($vbphrase['parent_email_address'], 'user[parentemail]', $user['parentemail'], 0);
	print_input_row($vbphrase['post_count'], 'user[posts]', $user['posts']);
	if ($user['referrerid'])
	{
		$referrername = $db->query_first("SELECT username FROM " . TABLE_PREFIX . "user WHERE userid = $user[referrerid]");
		$user['referrer'] = $referrername['username'];
	}
	print_input_row($vbphrase['referrer'], 'referrer', $user['referrer']);
	if (can_moderate(0, 'canviewips'))
	{
		print_input_row($vbphrase['ip_address'], 'user[ipaddress]', $user['ipaddress']);
	}
	print_table_break('', $INNERTABLEWIDTH);

	// USER IMAGE SECTION
	print_table_header($vbphrase['image_options']);
	if ($user['avatarid'])
	{
		$avatarurl = resolve_cp_image_url($user['avatarpath']);
	}
	else
	{
		if ($user['hascustomavatar'])
		{
			if ($vbulletin->options['usefileavatar'])
			{
				$avatarurl = resolve_cp_image_url($vbulletin->options['avatarurl'] . "/avatar$user[userid]_$user[avatarrevision].gif");
			}
			else
			{
				$avatarurl = '../image.php?' . $vbulletin->session->vars['sessionurl'] . "u=$user[userid]&amp;dateline=$user[avatardateline]";
			}
			if ($user['avatarwidth'] AND $user['avatarheight'])
			{
				$avatarurl .= "\" width=\"$user[avatarwidth]\" height=\"$user[avatarheight]";
			}
		}
		else
		{
			$avatarurl = '../' . $vbulletin->options['cleargifurl'];
		}
	}
	if ($user['profilepic'])
	{
		if ($vbulletin->options['usefileavatar'])
		{
			$profilepicurl = resolve_cp_image_url($vbulletin->options['profilepicurl'] . "/profilepic$user[userid]_$user[profilepicrevision].gif");
		}
		else
		{
			$profilepicurl = '../image.php?' . $vbulletin->session->vars['sessionurl'] . "u=$user[userid]&amp;type=profile&amp;dateline=$user[profilepicdateline]";
		}
		if ($user['profilepicwidth'] AND $user['profilepicheight'])
		{
			$profilepicurl .= "\" width=\"$user[profilepicwidth]\" height=\"$user[profilepicheight]";
		}
	}
	else
	{
		$profilepicurl = '../' . $vbulletin->options['cleargifurl'];
	}
	if ($user['hassigpic'])
	{
		if ($vbulletin->options['usefileavatar'])
		{
			$sigpicurl = resolve_cp_image_url($vbulletin->options['sigpicurl'] . "/sigpic$user[userid]_$user[sigpicrevision].gif");
		}
		else
		{
			$sigpicurl = "../image.php?" . $vbulletin->session->vars['sessionurl'] . "u=$user[userid]&amp;type=sigpic&amp;dateline=$user[sigpicdateline]";
		}
		
		if ($user['sigpicwidth'] AND $user['sigpicheight'])
		{
			$sigpicurl .= "\" width=$user[sigpicwidth]\" height=\"$user[sigpicheight]";
		}
	}
	else
	{
		$sigpicurl = '../' . $vbulletin->options['cleargifurl'];
	}
	print_label_row($vbphrase['avatar'] . iif(can_moderate(0, 'caneditavatar'), '<br /><br />' . construct_link_code($vbphrase['edit_avatar'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=avatar&amp;u=$user[userid]")) . '<input type="image" src="../' . $vbulletin->options['cleargifurl'] . '" alt="" />','<img src="' . $avatarurl . '" alt="" align="top" />');
	print_label_row($vbphrase['profile_picture'] . iif(can_moderate(0, 'caneditprofilepic'), '<br /><br />' . construct_link_code($vbphrase['edit_profile_picture'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=profilepic&amp;u=$user[userid]")) . '<input type="image" src="../' . $vbulletin->options['cleargifurl'] . '" alt="" />','<img src="' . $profilepicurl . '" alt="" align="top" />');
	print_label_row($vbphrase['signature_picture'] . iif(can_moderate(0, 'caneditsigs'), '<br /><br />' . construct_link_code($vbphrase['edit_signature_picture'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=sigpic&amp;u=$user[userid]")) . '<input type="image" src="../' . $vbulletin->options['cleargifurl'] . '" alt="" />','<img src="' . $sigpicurl . '" alt="" align="top" />');
	print_table_break('', $INNERTABLEWIDTH);

	// PROFILE FIELDS SECTION
	$forms = array(
		0 => $vbphrase['edit_your_details'],
		1 => "$vbphrase[options]: $vbphrase[log_in] / $vbphrase[privacy]",
		2 => "$vbphrase[options]: $vbphrase[messaging] / $vbphrase[notification]",
		3 => "$vbphrase[options]: $vbphrase[thread_viewing]",
		4 => "$vbphrase[options]: $vbphrase[date] / $vbphrase[time]",
		5 => "$vbphrase[options]: $vbphrase[other]",
	);
	$currentform = -1;

	print_table_header($vbphrase['user_profile_fields']);

	$profilefields = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "profilefield AS profilefield
		LEFT JOIN " . TABLE_PREFIX . "profilefieldcategory AS profilefieldcategory ON
			(profilefield.profilefieldcategoryid = profilefieldcategory.profilefieldcategoryid)
		ORDER BY profilefield.form, profilefieldcategory.displayorder, profilefield.displayorder
	");

	while ($profilefield = $db->fetch_array($profilefields))
	{
		if ($profilefield['form'] != $currentform)
		{
			print_description_row(construct_phrase($vbphrase['fields_from_form_x'], $forms["$profilefield[form]"]), false, 2, 'optiontitle');
			$currentform = $profilefield['form'];
		}
		print_profilefield_row('profile', $profilefield, $userfield, false);
	}

	($hook = vBulletinHook::fetch_hook('useradmin_edit_column1')) ? eval($hook) : false;

	if ($vbulletin->options['cp_usereditcolumns'] == 2)
	{
		?>
		</table>
		</td><td>&nbsp;&nbsp;&nbsp;&nbsp;</td><td>
		<table cellpadding="4" cellspacing="0" border="0" align="center" width="100%" class="tborder">
		<?php
	}
	else
	{
		print_table_break('', $INNERTABLEWIDTH);
	}

	// USERGROUP SECTION
	print_table_header($vbphrase['usergroup_options']);
	print_chooser_row($vbphrase['primary_usergroup'], 'user[usergroupid]', 'usergroup', $user['usergroupid']);
	if (!empty($user['membergroupids']))
	{
		$usergroupids = $user['usergroupid'] . (!empty($user['membergroupids']) ? ',' . $user['membergroupids'] : '');
		print_chooser_row($vbphrase['display_usergroup'], 'user[displaygroupid]', 'usergroup', iif($user['displaygroupid'] == 0, -1, $user['displaygroupid']), $vbphrase['default'], 0, "WHERE usergroupid IN ($usergroupids)");
	}
	$tempgroup = $user['usergroupid'];
	$user['usergroupid'] = 0;
	print_membergroup_row($vbphrase['additional_usergroups'], 'membergroup', 0, $user);
	print_table_break('', $INNERTABLEWIDTH);

	// reputation SECTION
	require_once(DIR . '/includes/functions_reputation.php');
	
	if ($user['userid'])
	{
		$perms = fetch_permissions(0, $user['userid'], $user);
	}
	else
	{
		$perms = array();
	}
	$score = fetch_reppower($user, $perms);
	
	print_table_header($vbphrase['reputation']);
	print_yes_no_row($vbphrase['display_reputation'], 'options[showreputation]', $user['showreputation']);
	print_input_row($vbphrase['reputation_level'], 'user[reputation]', $user['reputation']);
	print_label_row($vbphrase['current_reputation_power'], $score, '', 'top', 'reputationpower');
	print_table_break('',$INNERTABLEWIDTH);
	
	//INFRACTIONS SECTION
	print_table_header($vbphrase['infractions']);
	print_input_row($vbphrase['warnings'], 'user[warnings]', $user['warnings'], true, 5);
	print_input_row($vbphrase['infractions'], 'user[infractions]', $user['infractions'], true, 5);
	print_input_row($vbphrase['infraction_points'], 'user[ipoints]', $user['ipoints'], true, 5);
	print_table_break('',$INNERTABLEWIDTH);

	// BROWSING OPTIONS SECTION
	print_table_header($vbphrase['browsing_options']);
	print_yes_no_row($vbphrase['receive_admin_emails'], 'options[adminemail]', $user['adminemail']);
	print_yes_no_row($vbphrase['display_email'], 'options[showemail]', $user[showemail]);
	print_yes_no_row($vbphrase['invisible_mode'], 'options[invisible]', $user['invisible']);
	print_yes_no_row($vbphrase['allow_vcard_download'], 'options[showvcard]', $user['showvcard']);
	print_yes_no_row($vbphrase['receive_private_messages'], 'options[receivepm]', $user['receivepm']);
	print_yes_no_row($vbphrase['pm_from_contacts_only'], 'options[receivepmbuddies]', $user['receivepmbuddies']);
	print_yes_no_row($vbphrase['send_notification_email_when_a_private_message_is_received'], 'options[emailonpm]', $user['emailonpm']);
	print_yes_no_row($vbphrase['pop_up_notification_box_when_a_private_message_is_received'], 'user[pmpopup]', $user['pmpopup']);
	print_yes_no_row(construct_phrase($vbphrase['save_pm_copy_default'], '../private.php?folderid=-1'), 'user[pmdefaultsavecopy]', $user['pmdefaultsavecopy']);
	print_yes_no_row($vbphrase['enable_visitor_messaging'], 'options[vm_enable]', $user['vm_enable']);
	print_yes_no_row($vbphrase['limit_vm_to_contacts_only'], 'options[vm_contactonly]', $user['vm_contactonly']);
	print_yes_no_row($vbphrase['display_signature'], 'options[showsignatures]', $user['showsignatures']);
	print_yes_no_row($vbphrase['display_avatars'], 'options[showavatars]', $user['showavatars']);
	print_yes_no_row($vbphrase['display_images'], 'options[showimages]', $user['showimages']);
	print_yes_no_row($vbphrase['show_others_custom_profile_styles'], 'options[showusercss]', $user['showusercss']);
	print_yes_no_row($vbphrase['receieve_friend_request_notification'], 'options[receivefriendemailrequest]', $user['receivefriendemailrequest']);
	//print_yes_no_row($vbphrase['use_email_notification_by_default'], 'options[emailnotification]', $user['emailnotification']);
	print_radio_row($vbphrase['auto_subscription_mode'], 'user[autosubscribe]', array(
		-1 => $vbphrase['subscribe_choice_none'],
		0  => $vbphrase['subscribe_choice_0'],
		1  => $vbphrase['subscribe_choice_1'],
		2  => $vbphrase['subscribe_choice_2'],
		3  => $vbphrase['subscribe_choice_3'],
	), $user['autosubscribe'], 'smallfont');
	print_radio_row($vbphrase['thread_display_mode'], 'threaddisplaymode', array(
		0 => "$vbphrase[linear] - $vbphrase[oldest_first]",
		3 => "$vbphrase[linear] - $vbphrase[newest_first]",
		2 => $vbphrase['hybrid'],
		1 => $vbphrase['threaded']
	), $threaddisplaymode, 'smallfont');

	print_radio_row($vbphrase['message_editor_interface'], 'user[showvbcode]', array(
		0 => $vbphrase['do_not_show_editor_toolbar'],
		1 => $vbphrase['show_standard_editor_toolbar'],
		2 => $vbphrase['show_enhanced_editor_toolbar']
	), $user['showvbcode'], 'smallfont');

	construct_style_chooser($vbphrase['style'], 'user[styleid]', $user['styleid']);
	print_table_break('', $INNERTABLEWIDTH);
	
	// ADMIN OVERRIDE OPTIONS SECTION
	print_table_header($vbphrase['admin_override_options']);
	foreach ($vbulletin->bf_misc_adminoptions AS $field => $value)
	{
		print_yes_no_row($vbphrase['keep_' . $field], 'adminoptions[' . $field . ']', $user["$field"]);
	}
	print_table_break('', $INNERTABLEWIDTH);

	// TIME FIELDS SECTION
	print_table_header($vbphrase['time_options']);
	print_select_row($vbphrase['timezone'], 'user[timezoneoffset]', fetch_timezones_array(), $user['timezoneoffset']);
	print_yes_no_row($vbphrase['automatically_detect_dst_settings'], 'options[dstauto]', $user['dstauto']);
	print_yes_no_row($vbphrase['dst_currently_in_effect'], 'options[dstonoff]', $user['dstonoff']);
	print_label_row($vbphrase['default_view_age'], '<select name="user[daysprune]" class="bginput" tabindex="1">' . construct_select_options($pruneoptions, $user['daysprune']) . '</select>');
	print_time_row($vbphrase['join_date'], 'joindate', $user['joindate']);
	print_time_row($vbphrase['last_visit'], 'lastvisit', $user['lastvisit']);
	print_time_row($vbphrase['last_activity'], 'lastactivity', $user['lastactivity']);
	print_time_row($vbphrase['last_post'], 'lastpost', $user['lastpost']);

	($hook = vBulletinHook::fetch_hook('useradmin_edit_column2')) ? eval($hook) : false;

	?>
	</table>
	</tr>
	<?php

	print_table_break('', $OUTERTABLEWIDTH);
	$tableadded = 1;
	print_table_footer();
}

// ###################### Start editsig #######################
if ($_REQUEST['do'] == 'editsig')
{

	if (!can_moderate(0, 'caneditsigs'))
	{
		print_stop_message('no_permission_signatures');
	}

	if (empty($vbulletin->GPC['userid']))
	{
		print_stop_message('invalid_user_specified');
	}

	if (is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$user = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING (userid)
		WHERE user.userid = " . $vbulletin->GPC['userid'] . "
	");

	print_form_header('user','doeditsig', 0, 1);
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['signature'], $user['username'], $user['userid']));
	print_textarea_row($vbphrase['signature'], 'signature', $user['signature'], 8, 45, 1, 0);
	print_submit_row();

}

// ###################### Start doeditsig #######################
if ($_POST['do'] == 'doeditsig')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'signature' => TYPE_STR
	));

	if (!can_moderate(0, 'caneditsigs'))
	{
		print_stop_message('no_permission_signatures');
	}

	if (is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$user = fetch_userinfo($vbulletin->GPC['userid']);
	if (!$user)
	{
		print_stop_message('invalid_user_specified');
	}

	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
	$userdm->set_existing($user);
	$userdm->set('signature', $vbulletin->GPC['signature'], true, false);
	$userdm->save();
	unset($userdm);

	if (can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', 'user.php?do=viewuser&amp;u=' . $vbulletin->GPC['userid']);
	}
	else
	{
		define('CP_REDIRECT', 'index.php?do=home');
	}
	print_stop_message('saved_signature_successfully');

}

// ###################### Start modify Signature Pic ##############
if ($_REQUEST['do'] == 'sigpic')
{
	if(!can_moderate(0,'caneditsigs'))
	{
		print_stop_message('no_permission');
	}
	
	if(is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}
	
	$userinfo = fetch_userinfo($vbulletin->GPC['userid'], FETCH_USERINFO_SIGPIC);
	if(!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}
	
	if ($userinfo['sigpicwidth'] AND $userinfo['sigpicheight'])
	{
		$size = " width=\"$userinfo[sigpicwidth]\" height=\"$userinfo[sigpicheight]\"";
	}
	
	print_form_header('user', 'updatesigpic', 1);
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	if(!$userinfo['sigpic'])
	{
		construct_hidden_code('usesigpic',1);
	}
	print_table_header($vbphrase['edit_signature_picture']);
	
	if($userinfo['sigpic'])
	{
		if($vbulletin->options['usefileavatar'])
		{
			$userinfo['sigpicurl'] = '../' . $vbulletin->options['sigpicurl'] . '/sigpic' . $userinfo['userid'] . '_' .
			$userinfo['sigpicrevision'] . '.gif';
		}
		else
		{
			$userinfo['sigpicurl'] = '../image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[sigpicdateline]&amp;type=sigpic";
		}
		print_description_row("<div align=\"center\"><img src=\"$userinfo[sigpicurl]\" $size alt=\"\" title=\"" . construct_phrase($vbphrase['xs_picture'], $userinfo['username']) . "\" /></div>");
		print_yes_no_row($vbphrase['use_signature_picture'], 'usesigpic', iif($userinfo['sigpic'], 1, 0));
	}
	else
	{
		construct_hidden_code('usesigpic',1);
	}
	
	cache_permissions($userinfo,false);
	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] AND ($userinfo['permissions']['sigpicmaxwidth'] > 0 OR $userinfo['permissions']['sigpicmaxheight'] > 0))
	{
		print_yes_no_row($vbphrase['resize_image_to_users_maximum_allowed_size'], 'resize');
	}
	print_input_row($vbphrase['enter_image_url'], 'sigpicurl', 'http://www.');
	print_upload_row($vbphrase['upload_image_from_computer'], 'upload');
	
	print_submit_row($vbphrase['update'], '');
	
}

// ###################### Start Update Signature Pic ##############
if ($_POST['do'] == 'updatesigpic')
{
	if (!can_moderate(0,'caneditsigs'))
	{
		print_stop_message('no_permission');
	}
	
	$vbulletin->input->clean_array_gpc('p', array(
		'usesigpic' => TYPE_BOOL,
		'sigpicurl' => TYPE_STR,
		'resize'    => TYPE_BOOL,
	));
	
	if(is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}
	
	$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
	if(!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}
	
	if($vbulletin->GPC['usesigpic'])
	{
		$vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);
		
		require_once(DIR . '/includes/class_upload.php');
		require_once(DIR . '/includes/class_image.php');
		
		$upload = new vB_Upload_Userpic($vbulletin);
		
		$upload->data =& datamanager_init('Userpic_Sigpic', $vbulletin, ERRTYPE_CP, 'userpic');
		$upload->image =& vB_Image::fetch_library($vbulletin);
		$upload->userinfo =& $userinfo;
		
		cache_permissions($userinfo, false);
		if(
			($userinfo['permissions']['sigpicmaxwidth'] > 0 OR $userinfo['permissions']['sigpicmaxheight'] > 0)
			AND 
			(
				$vbulletin->GPC['resize']
					OR
				(!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			)
		)
		{
			$upload->maxwidth = $userinfo['permissions']['sigpicmaxwidth'];
			$upload->maxheight = $userinfo['permissions']['sigpicmaxheight'];
		}
		
		if(!$upload->process_upload($vbulletin->GPC['sigpicurl']))
		{
			print_stop_message('there_were_errors_encountered_with_your_upload_x', $upload->fetch_error());
		}
	}
	else
	{
		// not using a sigpic
		$userpic =& datamanager_init('Userpic_Sigpic', $vbulletin, ERRTYPE_CP, 'userpic');
		$userpic->condition = "userid = " . $userinfo['userid'];
		$userpic->delete();
	}
	
	if(can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', 'user.php?do=viewuser&amp;u=' . $userinfo['userid']);
	}
	else
	{
		define('CP_REDIRECT', 'index.php?do=home');
	}
	print_stop_message('saved_signature_picture_successfully');
	
}

// ###################### Start modify Profile Pic ################
if ($_REQUEST['do'] == 'profilepic')
{

	if (!can_moderate(0, 'caneditprofilepic'))
	{
		print_stop_message('no_permission');
	}

	if (is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$userinfo = fetch_userinfo($vbulletin->GPC['userid'], FETCH_USERINFO_PROFILEPIC);
	if (!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}

	if ($userinfo['profilepicwidth'] AND $userinfo['profilepicheight'])
	{
		$size = " width=\"$userinfo[profilepicwidth]\" height=\"$userinfo[profilepicheight]\" ";
	}

	print_form_header('user', 'updateprofilepic', 1);
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	if (!$userinfo['profilepic'])
	{
		construct_hidden_code('useprofilepic', 1);
	}
	print_table_header($vbphrase['edit_profile_picture']);

	if ($userinfo['profilepic'])
	{
		if ($vbulletin->options['usefileavatar'])
		{
			$userinfo['profilepicurl'] = '../' . $vbulletin->options['profilepicurl'] . '/profilepic' . $userinfo['userid'] . '_' . $userinfo['profilepicrevision'] . '.gif';
		}
		else
		{
			$userinfo['profilepicurl'] = '../image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[profilepicdateline]&amp;type=profile";
		}
		print_description_row("<div align=\"center\"><img src=\"$userinfo[profilepicurl]\" $size alt=\"\" title=\"" . construct_phrase($vbphrase['xs_picture'], $userinfo['username']) . "\" /></div>");
		print_yes_no_row($vbphrase['use_profile_picture'], 'useprofilepic', iif($userinfo['profilepic'], 1, 0));
	}
	else
	{
		construct_hidden_code('useprofilepic', 1);
	}

	cache_permissions($userinfo, false);
	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] AND ($userinfo['permissions']['profilepicmaxwidth'] > 0 OR $userinfo['permissions']['profilepicmaxheight'] > 0))
	{
		print_yes_no_row($vbphrase['resize_image_to_users_maximum_allowed_size'], 'resize');
	}
	print_input_row($vbphrase['enter_image_url'], 'profilepicurl', 'http://www.');
	print_upload_row($vbphrase['upload_image_from_computer'], 'upload');

	print_submit_row($vbphrase['update'], '');

}

// ###################### Start Update Profile Pic ################
if ($_POST['do'] == 'updateprofilepic')
{
	if (!can_moderate(0, 'caneditprofilepic'))
	{
		print_stop_message('no_permission');
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'useprofilepic' => TYPE_BOOL,
		'profilepicurl' => TYPE_STR,
		'resize'        => TYPE_BOOL,
	));

	if (is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
	if (!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}

	if ($vbulletin->GPC['useprofilepic'])
	{
		$vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);

		require_once(DIR . '/includes/class_upload.php');
		require_once(DIR . '/includes/class_image.php');

		$upload = new vB_Upload_Userpic($vbulletin);

		$upload->data =& datamanager_init('Userpic_Profilepic', $vbulletin, ERRTYPE_CP, 'userpic');
		$upload->image =& vB_Image::fetch_library($vbulletin);
		$upload->userinfo =& $userinfo;

		cache_permissions($userinfo, false);
		if (
				($userinfo['permissions']['profilepicmaxwidth'] > 0 OR $userinfo['permissions']['profilepicmaxheight'] > 0)
				AND
				(
					$vbulletin->GPC['resize']
						OR
					(!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
				)
			)
		{
			$upload->maxwidth = $userinfo['permissions']['profilepicmaxwidth'];
			$upload->maxheight = $userinfo['permissions']['profilepicmaxheight'];
		}

		if (!$upload->process_upload($vbulletin->GPC['profilepicurl']))
		{
			print_stop_message('there_were_errors_encountered_with_your_upload_x', $upload->fetch_error());
		}
	}
	else
	{
		// not using a profilepic
		$userpic =& datamanager_init('Userpic_Profilepic', $vbulletin, ERRTYPE_CP, 'userpic');
		$userpic->condition = "userid = " . $userinfo['userid'];
		$userpic->delete();
	}

	if (can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', 'user.php?do=viewuser&amp;u=' . $userinfo['userid']);
	}
	else
	{
		define('CP_REDIRECT', 'index.php?do=home');
	}
	print_stop_message('saved_profile_picture_successfully');

}

// ###################### Start modify Avatar ################
if ($_REQUEST['do'] == 'avatar')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'startpage' => TYPE_INT,
		'perpage'   => TYPE_INT
	));

	if (!can_moderate(0, 'caneditavatar'))
	{
		print_stop_message('no_permission_avatars');
	}

	if (is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
	if (!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}

	$avatarchecked["{$userinfo['avatarid']}"] = 'checked="checked"';
	$nouseavatarchecked = '';
	if (!$avatarinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "customavatar WHERE userid = " . $vbulletin->GPC['userid']))
	{
		// no custom avatar exists
		if (!$userinfo['avatarid'])
		{
			// must have no avatar selected
			$nouseavatarchecked = 'checked="checked"';
			$avatarchecked[0] = '';
		}
	}
	if ($vbulletin->GPC['startpage'] < 1)
	{
		$vbulletin->GPC['startpage'] = 1;
	}
	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 25;
	}
	$avatarcount = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "avatar");
	$totalavatars = $avatarcount['count'];
	if (($vbulletin->GPC['startpage'] - 1) * $vbulletin->GPC['perpage'] > $totalavatars)
	{
		if ((($totalavatars / $vbulletin->GPC['perpage']) - (intval($totalavatars / $vbulletin->GPC['perpage']))) == 0)
		{
			$vbulletin->GPC['startpage'] = $totalavatars / $vbulletin->GPC['perpage'];
		}
		else
		{
			$vbulletin->GPC['startpage'] = intval(($totalavatars / $vbulletin->GPC['perpage'])) + 1;
		}
	}
	$limitlower = ($vbulletin->GPC['startpage'] - 1) * $vbulletin->GPC['perpage'] + 1;
	$limitupper = ($vbulletin->GPC['startpage']) * $vbulletin->GPC['perpage'];
	if ($limitupper > $totalavatars)
	{
		$limitupper = $totalavatars;
		if ($limitlower > $totalavatars)
		{
			$limitlower = $totalavatars - $vbulletin->GPC['perpage'];
		}
	}
	if ($limitlower <= 0)
	{
		$limitlower = 1;
	}
	$avatars = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "avatar
		ORDER BY title LIMIT " . ($limitlower-1) . ", " . $vbulletin->GPC['perpage'] . "
	");
	$avatarcount = 0;
	if ($totalavatars > 0)
	{
		print_form_header('user', 'avatar');
		construct_hidden_code('userid', $vbulletin->GPC['userid']);
		print_table_header(
			$vbphrase['avatars_to_show_per_page'] .
			': <input type="text" name="perpage" value="' . $vbulletin->GPC['perpage'] . '" size="5" tabindex="1" />
			<input type="submit" class="button" value="' . $vbphrase['go'] . '" tabindex="1" />
		');
		print_table_footer();
	}

	print_form_header('user', 'updateavatar', 1);
	print_table_header($vbphrase['avatars']);

	$output = '<table border="0" cellpadding="6" cellspacing="1" class="tborder" align="center" width="100%">';
	while ($avatar = $db->fetch_array($avatars))
	{
		$avatarid = $avatar['avatarid'];
		$avatar['avatarpath'] = resolve_cp_image_url($avatar['avatarpath']);
		if ($avatarcount == 0)
		{
			$output .= '<tr class="' . fetch_row_bgclass() . '">';
		}
		$output .= "<td valign=\"bottom\" align=\"center\"><input type=\"radio\" name=\"avatarid\" value=\"$avatar[avatarid]\" tabindex=\"1\" $avatarchecked[$avatarid] />";
		$output .= "<img src=\"$avatar[avatarpath]\" alt=\"\" /><br />$avatar[title]</td>";
		$avatarcount++;
		if ($avatarcount == 5)
		{
			echo '</tr>';
			$avatarcount = 0;
		}
	}
	if ($avatarcount != 0)
	{
		while ($avatarcount != 5)
		{
			$output .= '<td>&nbsp;</td>';
			$avatarcount++;
		}
		echo '</tr>';
	}
	if ((($totalavatars / $vbulletin->GPC['perpage']) - (intval($totalavatars / $vbulletin->GPC['perpage']))) == 0)
	{
		$numpages = $totalavatars / $vbulletin->GPC['perpage'];
	}
	else
	{
		$numpages = intval($totalavatars / $vbulletin->GPC['perpage']) + 1;
	}
	if ($vbulletin->GPC['startpage'] == 1)
	{
		$starticon = 0;
		$endicon = $vbulletin->GPC['perpage'] - 1;
	}
	else
	{
		$starticon = ($vbulletin->GPC['startpage'] - 1) * $vbulletin->GPC['perpage'];
		$endicon = ($vbulletin->GPC['perpage'] * $vbulletin->GPC['startpage']) - 1 ;
	}
	if ($numpages > 1)
	{
		for ($x = 1; $x <= $numpages; $x++)
		{
			if ($x == $vbulletin->GPC['startpage'])
			{
				$pagelinks .= " [<b>$x</b>] ";
			}
			else
			{
				$pagelinks .= " <a href=\"user.php?startpage=$x&pp=" . $vbulletin->GPC['perpage'] . "&do=avatar&u=" . $vbulletin->GPC['userid'] . "\">$x</a> ";
			}
		}
	}
	if ($vbulletin->GPC['startpage'] != $numpages)
	{
		$nextstart = $vbulletin->GPC['startpage'] + 1;
		$nextpage = " <a href=\"user.php?startpage=$nextstart&pp=" . $vbulletin->GPC['perpage'] . "&do=avatar&u=" . $vbulletin->GPC['userid'] . "\">" . $vbphrase['next_page'] . "</a>";
		$eicon = $endicon + 1;
	}
	else
	{
		$eicon = $totalavatars;
	}
	if ($vbulletin->GPC['startpage'] != 1)
	{
		$prevstart = $vbulletin->GPC['startpage'] - 1;
		$prevpage = "<a href=\"user.php?startpage=$prevstart&pp=" . $vbulletin->GPC['perpage'] . "&do=avatar&u=" . $vbulletin->GPC['userid'] . "\">" . $vbphrase['prev_page'] . "</a> ";
	}
	$sicon = $starticon +  1;
	if ($totalavatars > 0)
	{
		if ($pagelinks)
		{
			$colspan = 3;
		}
		else
		{
			$colspan = 5;
		}
		$output .= '<tr><td class="thead" align="center" colspan="' . $colspan . '">';
		$output .= construct_phrase($vbphrase['showing_avatars_x_to_y_of_z'], $sicon, $eicon, $totalavatars) . '</td>';
		if ($pagelinks)
		{
			$output .= "<td class=\"thead\" colspan=\"2\" align=\"center\">$vbphrase[page]: <span class=\"normal\">$prevpage $pagelinks $nextpage</span></td>";
		}
		$output .= '</tr>';
	}
	$output .= '</table>';

	if ($totalavatars > 0)
	{
		print_description_row($output);
	}

	if ($nouseavatarchecked)
	{
		print_description_row($vbphrase['user_has_no_avatar']);
	}
	else
	{
		print_yes_row($vbphrase['delete_avatar'], 'avatarid', $vbphrase['yes'], '', -1);
	}
	print_table_break();
	print_table_header($vbphrase['custom_avatar']);

	require_once(DIR . '/includes/functions_user.php');
	$userinfo['avatarurl'] = fetch_avatar_url($userinfo['userid']);

	if ($userinfo['avatarurl'] == '' OR $userinfo['avatarid'] != 0)
	{
		$userinfo['avatarurl'] = '<img src="' . $vbulletin->options['cleargifurl'] . '" alt="" border="0" />';
	}
	else
	{
		$userinfo['avatarurl'] = "<img src=\"../" . $userinfo['avatarurl'][0] . "\" " . $userinfo['avatarurl'][1] . " alt=\"\" border=\"0\" />";
	}
	print_yes_row(
		iif($avatarchecked[0] != '',
			$vbphrase['use_current_avatar'] . ' ' . $userinfo['avatarurl'],
			$vbphrase['add_new_custom_avatar']
		)
	, 'avatarid', $vbphrase['yes'], $avatarchecked[0], 0);

	cache_permissions($userinfo, false);
	if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] AND $userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'] AND ($userinfo['permissions']['avatarmaxwidth'] > 0 OR $userinfo['permissions']['avatarmaxheight'] > 0))
	{
		print_yes_no_row($vbphrase['resize_image_to_users_maximum_allowed_size'], 'resize');
	}
	print_input_row($vbphrase['enter_image_url'], 'avatarurl', 'http://www.');
	print_upload_row($vbphrase['upload_image_from_computer'], 'upload');
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_submit_row($vbphrase['save']);
}

// ###################### Start Update Avatar ################
if ($_POST['do'] == 'updateavatar')
{
	if (!can_moderate(0, 'caneditavatar'))
	{
		print_stop_message('no_permission_avatars');
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'avatarid'  => TYPE_INT,
		'avatarurl' => TYPE_STR,
		'resize'    => TYPE_BOOL,
	));

	if (is_unalterable_user($vbulletin->GPC['userid']))
	{
		print_stop_message('user_is_protected_from_alteration_by_undeletableusers_var');
	}

	$useavatar = iif($vbulletin->GPC['avatarid'] == -1, 0, 1);

	$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
	if (!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}

	// init user datamanager
	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
	$userdata->set_existing($userinfo);

	if ($useavatar)
	{
		if (!$vbulletin->GPC['avatarid'])
		{
			// custom avatar
			$vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);

			require_once(DIR . '/includes/class_upload.php');
			require_once(DIR . '/includes/class_image.php');

			$upload = new vB_Upload_Userpic($vbulletin);

			$upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_CP, 'userpic');
			$upload->image =& vB_Image::fetch_library($vbulletin);
			$upload->userinfo =& $userinfo;

			cache_permissions($userinfo, false);

			// user's group doesn't have permission to use custom avatars so set override
			if (!($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']))
			{
				$userdata->set_bitfield('adminoptions', 'adminavatar', 1);
			}

			if (
					($userinfo['permissions']['avatarmaxwidth'] > 0 OR $userinfo['permissions']['avatarmaxheight'] > 0)
					AND
					(
						$vbulletin->GPC['resize']
							OR
						(!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
					)
				)
			{
				$upload->maxwidth = $userinfo['permissions']['avatarmaxwidth'];
				$upload->maxheight = $userinfo['permissions']['avatarmaxheight'];
			}

			if (!$upload->process_upload($vbulletin->GPC['avatarurl']))
			{
				print_stop_message('there_were_errors_encountered_with_your_upload_x', $upload->fetch_error());
			}
		}
		else
		{
			// predefined avatar
			$userpic =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_CP, 'userpic');
			$userpic->condition = "userid = " . $userinfo['userid'];
			$userpic->delete();
		}
	}
	else
	{
		// not using an avatar
		$vbulletin->GPC['avatarid'] = 0;
		$userpic =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_CP, 'userpic');
		$userpic->condition = "userid = " . $userinfo['userid'];
		$userpic->delete();
	}

	$userdata->set('avatarid', $vbulletin->GPC['avatarid']);
	$userdata->save();

	if (can_moderate(0, 'canviewprofile'))
	{
		define('CP_REDIRECT', 'user.php?do=viewuser&amp;u=' . $userinfo['userid']);
	}
	else
	{
		define('CP_REDIRECT', 'index.php?do=home');
	}
	print_stop_message('saved_avatar_successfully');
}

// ###################### Start Moderate Group Join Requests #######################
if ($_REQUEST['do'] == 'viewjoinrequests')
{
	if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		$userlink = '<a href="../' . $vbulletin->config['Misc']['admincpdir'] . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=%d\" target=\"_blank\">%s</a>";
		$grouplink = '../' . $vbulletin->config['Misc']['admincpdir'] . '/usergroup.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewjoinrequests&amp;usergroupid=%d";
	}
	else
	{
		$userlink = '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=%d\" target=\"_blank\">%s</a>";
		$grouplink = '../joinrequests.php?' . $vbulletin->session->vars['sessionurl'] . "usergroupid=%d";
	}

	// get array of all usergroup leaders
	$bbuserleader = array();
	$leaders = array();
	$groupleaders = $db->query_read("
		SELECT ugl.*, user.username
		FROM " . TABLE_PREFIX . "usergroupleader AS ugl
		INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	");
	while ($groupleader = $db->fetch_array($groupleaders))
	{
		if ($groupleader['userid'] == $vbulletin->userinfo['userid'])
		{
			$bbuserleader[] = $groupleader['usergroupid'];
		}
		$leaders["$groupleader[usergroupid]"]["$groupleader[userid]"] = sprintf($userlink, $groupleader['userid'], $groupleader['username']);
	}
	unset($groupleader);
	$db->free_result($groupleaders);

	if (empty($bbuserleader) AND !($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		print_stop_message('no_permission');
	}

	$requests = $db->query_read("
		SELECT usergrouprequest.usergroupid, COUNT(usergrouprequestid) AS requests
		FROM " . TABLE_PREFIX . "usergrouprequest AS usergrouprequest
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE user.userid IS NOT NULL
		GROUP BY usergroupid
	");
	while ($request = $db->fetch_array($requests))
	{
		$vbulletin->usergroupcache["$request[usergroupid]"]['requests'] = $request['requests'];
	}
	unset($request);
	$db->free_result($requests);

	print_form_header('', '');
	print_table_header($vbphrase['join_requests_manager'], 4);
	print_cells_row(array(
		$vbphrase['usergroup'],
		$vbphrase['usergroup_leader'],
		$vbphrase['join_requests'],
		$vbphrase['controls']
	), 1);
	foreach ($vbulletin->usergroupcache AS $usergroupid => $usergroup)
	{
		if ($usergroup['ispublicgroup'] AND in_array($usergroupid, $bbuserleader))
		{
			print_cells_row(array(
				$usergroup['title'],
				iif(empty($leaders["$usergroupid"]), "<i>$vbphrase[n_a]</i>", implode(', ', $leaders["$usergroupid"])),
				vb_number_format($usergroup['requests']),
				construct_link_code($vbphrase['view_join_requests'], sprintf($grouplink, $usergroupid))
			));
		}
	}
	print_table_footer();

}

// ###################### Start Reputation List #######################
if ($_REQUEST['do'] == 'reputation')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'perpage' => TYPE_INT,
		'page'    => TYPE_INT
	));

	if (!can_moderate(0, 'caneditreputation') OR !$vbulletin->options['reputationenable'])
	{
		print_stop_message('no_permission');
	}

	$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
	if (!$userinfo)
	{
		print_stop_message('invalid_user_specified');
	}

	$repcount = $db->query_first("
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "reputation
		WHERE userid = " . $vbulletin->GPC['userid'] . "
	");
	$totalrep = $repcount['count'];

	sanitize_pageresults($totalrep, $vbulletin->GPC['page'], $vbulletin->GPC['perpage']);
	$startat = ($vbulletin->GPC['page'] - 1) * $vbulletin->GPC['perpage'];
	$totalpages = ceil($totalrep / $vbulletin->GPC['perpage']);

	$comments = $db->query_read("
		SELECT reputation.*, user.username
		FROM " . TABLE_PREFIX . "reputation AS reputation
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (reputation.whoadded = user.userid)
		WHERE reputation.userid = " . $vbulletin->GPC['userid'] . "
		ORDER BY reputation.dateline DESC
		LIMIT $startat, " . $vbulletin->GPC['perpage'] . "
	");
	if ($db->num_rows($comments))
	{

		if ($vbulletin->GPC['page'] != 1)
		{
			$prv = $vbulletin->GPC['page'] - 1;
			$firstpage = "<input type=\"button\" class=\"button\" value=\"&laquo; " . $vbphrase['first_page'] . "\" tabindex=\"1\" onclick=\"window.location='user.php?" . $vbulletin->session->vars['sessionurl'] . "do=reputation&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" value=\"&lt; " . $vbphrase['prev_page'] . "\" tabindex=\"1\" onclick=\"window.location='user.php?" . $vbulletin->session->vars['sessionurl'] . "do=reputation&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&page=$prv'\">";
		}

		if ($vbulletin->GPC['page'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['page'] + 1;
			$nextpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['next_page'] . " &gt;\" tabindex=\"1\" onclick=\"window.location='user.php?" . $vbulletin->session->vars['sessionurl'] . "do=reputation&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" value=\"" . $vbphrase['last_page'] . " &raquo;\" tabindex=\"1\" onclick=\"window.location='user.php?" . $vbulletin->session->vars['sessionurl'] . "do=reputation&u=" . $vbulletin->GPC['userid'] . "&pp=" . $vbulletin->GPC['perpage'] . "&page=$totalpages'\">";
		}

		print_form_header('user', 'reputation');
		print_table_header(construct_phrase($vbphrase['reputation_for_a_page_b_c_there_are_d_comments'], $userinfo['username'], $vbulletin->GPC['page'], vb_number_format($totalpages), vb_number_format($totalrep)), 4);

		$headings = array();
		$headings[] = '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . 'do=reputation&u=' . $vbulletin->GPC['userid'] . '&pp=' . $vbulletin->GPC['perpage'] . '&orderby=user&page=' . $vbulletin->GPC['page'] . "\" title='" . $vbphrase['order_by_username'] . "'>" . $vbphrase['username'] . "</a>";
		$headings[] = '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . 'do=reputation&u=' . $vbulletin->GPC['userid'] . '&pp=' . $vbulletin->GPC['perpage'] . '&orderby=date&page=' . $vbulletin->GPC['page'] . "\" title='" . $vbphrase['order_by_date'] . "'>" . $vbphrase['date'] . "</a>";
		$headings[] = $vbphrase['reason'];
		$headings[] = $vbphrase['edit'];
		print_cells_row($headings, 1);

		while ($comment = $db->fetch_array($comments))
		{
			$cell = array();
			$cell[] = "<a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=viewuser&amp;u=$comment[whoadded]\"><b>$comment[username]</b></a>";
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $comment['dateline']) . '</span>';
			$cell[] = htmlspecialchars_uni($comment['reason']);
			$cell[] = construct_link_code($vbphrase['edit'], 'user.php?' . $vbulletin->session->vars['sessionurl'] . "do=editreputation&reputationid=$comment[reputationid]");
			print_cells_row($cell);
		}

		print_table_footer(4, "$firstpage $prevpage &nbsp; $nextpage $lastpage");

	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

// ###################### Start Reputation Edit Form #######################
if ($_REQUEST['do'] == 'editreputation')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'reputationid' => TYPE_INT
	));

	if (!can_moderate(0, 'caneditreputation') OR !$vbulletin->options['reputationenable'])
	{
		print_stop_message('no_permission');
	}

	$reputation = $db->query_first("
		SELECT reason, dateline, userid
		FROM " . TABLE_PREFIX . "reputation
		WHERE reputationid = " . $vbulletin->GPC['reputationid'] . "
	");

	print_form_header('user', 'doeditreputation');
	construct_hidden_code('reputationid', $vbulletin->GPC['reputationid']);
	construct_hidden_code('userid', $reputation['userid']);
	print_table_header($vbphrase['edit_reputation_comment']);
	print_label_row($vbphrase['date'], vbdate($vbulletin->options['logdateformat'], $reputation['dateline']));
	print_textarea_row($vbphrase['reason'], 'reason', $reputation['reason'], 4, 40, 1, 0);
	print_submit_row($vbphrase['update'], 0);


}

// ###################### Start Actual Reputation Editing #######################
if ($_POST['do'] == 'doeditreputation')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reputationid' => TYPE_INT,
		'reason'       => TYPE_STR,
	));

	if (!can_moderate(0, 'caneditreputation') OR !$vbulletin->options['reputationenable'])
	{
		print_stop_message('no_permission');
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "reputation
		SET reason = '" . $db->escape_string($vbulletin->GPC['reason']) . "'
		WHERE reputationid = " . $vbulletin->GPC['reputationid'] . "
	");

	define('CP_REDIRECT', 'user.php?do=reputation&amp;u=' . $vbulletin->GPC['userid']);
	print_stop_message('updated_reason_successfully');

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42666 $
|| ####################################################################
\*======================================================================*/
?>