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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_misc.php');

// #############################################################################
/**
* Prints a row containing a <select> showing forums the user has permission to moderate
*
* @param	string	name for the <select>
* @param	mixed	selected <option>
* @param	string	text given to the -1 option
* @param	string	title for the row
* @param	boolean	Display the -1 option or not
* @param	boolean	Allow a multiple <select> or not
* @param	boolean	Display a 'select forum' option or not
* @param	string	If specified, check this permission for each forum
*/
function print_moderator_forum_chooser($name = 'forumid', $selectedid = -1, $topname = NULL, $title = NULL, $displaytop = true, $multiple = false, $displayselectforum = false, $permcheck = '')
{
	global $vbphrase;
	
	if ($title === NULL)
	{
		$title = $vbphrase['parent_forum'];
	}

	$select_options = fetch_moderator_forum_options($topname, $displaytop, $displayselectforum, $permcheck);

	print_select_row($title, $name, $select_options, $selectedid, 0, iif($multiple, 10, 0), $multiple);
}

// #############################################################################
/**
* Returns a nice <select> list of forums, complete with displayorder, parenting and depth information
*
* @param	string	Optional name of the first <option>
* @param	boolean	Show the top <option> or not
* @param	boolean	Display an <option> labelled 'Select a forum'
* @param	string	Name of can_moderate() option to check for each forum - if 'none', show all forums
* @param	string	Character(s) to use to indicate forum depth
* @param	boolean	Show '(no posting)' after title of category-type forums
*
* @return	array	Array for use in building a <select> to show options
*/
function fetch_moderator_forum_options($topname = NULL, $displaytop = true, $displayselectforum = false, $permcheck = '', $depthmark = '--', $show_no_posting = true)
{
	global $vbphrase, $vbulletin;

	$select_options = array();

	if ($displayselectforum)
	{
		$select_options[0] = $vbphrase['select_forum'];
	}

	if ($displaytop)
	{
		$select_options['-1'] = ($topname === NULL ? $vbphrase['no_one'] : $topname);
		$startdepth = $depthmark;
	}
	else
	{
		$startdepth = '';
	}

	foreach($vbulletin->forumcache AS $forum)
	{
		$perms = fetch_permissions($forum['forumid']);
		if (!($perms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			continue;
		}
		if (empty($forum['link']))
		{
			if ($permcheck == 'none' OR can_moderate($forum['forumid'], $permcheck))
			{
				$select_options["$forum[forumid]"] = str_repeat($depthmark, $forum['depth']) . "$startdepth $forum[title_clean]";
				if ($show_no_posting)
				{
					$select_options["$forum[forumid]"] .= ' ' . ($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting'] ? '' : " ($vbphrase[no_posting])") . " $forum[allowposting]";
				}
			}
		}
	}

	return $select_options;
}

// #############################################################################
/**
* Returns an SQL condition to select forums a user has permission to moderate
*
* @param	string	Moderator permission to check (canannounce, canmoderateposts etc.)
*
* @return	string	SQL condition
*/
function fetch_moderator_forum_list_sql($permission = '')
{
	global $vbulletin;

	$modperms = array();
	foreach ($vbulletin->forumcache AS $mforumid => $null)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$mforumid"];
		if (can_moderate($mforumid, $permission) AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
		{
			$modforums[] = $mforumid;
		}
	}

	if ($modforums)
	{
		return "thread.forumid IN(" . implode(", ", $modforums) . ")";
	}
	else
	{
		return '';
	}
}

/**
* Returns a boolean to say whether the user is currently "authenticated" for moderation actions.
* If the user is not a moderator, this will return true!
*
* @param	bool	Whether to update the table to reset the timeout
*
* @return	bool	Whether the user is validated or not
*/

function inlinemod_authenticated($updatetimeout = true)
{
	global $vbulletin;

	$vbulletin->input->clean_array_gpc('c', array(
		COOKIE_PREFIX . 'cpsession' => TYPE_STR,
	));

	// Only moderators can use the mog login part of login.php, for cases that use inlinemod but don't have this permission return true
	if (!can_moderate() OR !$vbulletin->options['enable_inlinemod_auth'])
	{
		return true;
	}

	if (!empty($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']))
	{
		$cpsession = $vbulletin->db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "cpsession
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND hash = '" . $vbulletin->db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'
				AND dateline > " . ($vbulletin->options['timeoutcontrolpanel'] ? intval(TIMENOW - $vbulletin->options['cookietimeout']) : intval(TIMENOW - 3600))
		);

		if (!empty($cpsession))
		{
			if($updatetimeout)
			{
				$vbulletin->db->query_write("
					UPDATE LOW_PRIORITY " . TABLE_PREFIX . "cpsession
					SET dateline = " . TIMENOW . "
					WHERE userid = " . $vbulletin->userinfo['userid'] . "
						AND hash = '" . $vbulletin->db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'
				");
			}

			return true;
		}
	}

	return false;
}

/**
* Shows the form for inline mod authentication.
*/
function show_inline_mod_login($showerror = false)
{
	global $vbulletin, $vbphrase, $show;

	$show['inlinemod_form'] = true;
	$show['passworderror'] = $showerror;

	if (!$showerror)
	{
		$vbulletin->url = SCRIPTPATH;
	}
	eval(standard_error(fetch_error('nopermission_loggedin',
		$vbulletin->userinfo['username'],
		vB_Template_Runtime::fetchStyleVar('right'),
		$vbulletin->session->vars['sessionurl'],
		$vbulletin->userinfo['securitytoken'],
		fetch_seo_url('forumhome', array())
	)));

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
?>
