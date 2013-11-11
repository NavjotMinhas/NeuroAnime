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

/**
 * Quick Method of building the CPNav Template
 *
 * @param	string	The selected item in the CPNav
 */
function construct_usercp_nav($selectedcell = 'usercp')
{
	global $navclass, $cpnav, $gobutton, $vbphrase;
	global $messagecounters, $subscribecounters, $vbulletin;
	global $show, $subscriptioncache, $template_hook;

	$cells = array(
		'usercp',

		'signature',
		'profile',
		'options',
		'connections',
		'password',
		'avatar',
		'profilepic',
		'album',

		'pm_messagelist',
		'pm_newpm',
		'pm_trackpm',
		'pm_editfolders',

		'substhreads_editfolders',

		'deletedthreads',
		'deletedposts',
		'moderatedthreads',
		'moderatedposts',
		'moderatedvms',
		'deletedvms',
		'moderatedgms',
		'deletedgms',
		'moderateddiscussions',
		'deleteddiscussions',
		'moderatedpcs',
		'deletedpcs',
		'moderatedpics',

		'event_reminders',
		'paid_subscriptions',
		'socialgroups',
		'usergroups',
		'buddylist',
		'ignorelist',
		'attachments',
		'customize',
		'privacy',

		'deleteditems',
		'moderateditems',
		'newitems',
		'newvms',
		'newgms',
		'newdiscussions',
		'newpcs',
		'newpics'
	);

	($hook = vBulletinHook::fetch_hook('usercp_nav_start')) ? eval($hook) : false;

	if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_profile_styling']))
	{
		$show['customizelink'] = false;
	}
	else if (
		($vbulletin->userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditfontfamily'])
		OR ($vbulletin->userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditfontsize'])
		OR ($vbulletin->userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditcolors'])
		OR ($vbulletin->userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditbgimage'])
		OR ($vbulletin->userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditborders'])
	)
	{
		$show['customizelink'] = true;
	}
	else
	{
		$show['customizelink'] = false;
	}

	$show['customizelink'] = false;

	$show['privacylink'] = (($vbulletin->userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditprivacy'])
							AND $vbulletin->options['profileprivacy']);

	if ($show['avatarlink'] AND !($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']))
	{
		$membergroups = fetch_membergroupids_array($vbulletin->userinfo);
		// We don't have any predefined avatars or user's groups are all denied permission
		if (!empty($vbulletin->noavatarperms) AND ($vbulletin->noavatarperms['all'] == true OR !count(array_diff($membergroups, $vbulletin->noavatarperms))))
		{
			$show['avatarlink'] = false;
		}
		else if (!empty($vbulletin->userinfo['infractiongroupids']))
		{
			$show['avatarlink'] = ($categorycache =& fetch_avatar_categories($vbulletin->userinfo));
		}
	}

	// currently, we only have faceboook as external login,
	// but as we add more, we need to add them to this assignment
	$show['externalconnections'] = $vbulletin->options['enablefacebookconnect'];

	if ($selectedcell == 'attachments')
	{
		$show['attachments'] = true;
	}
	else
	{
		require_once(DIR . '/packages/vbattach/attach.php');
		$attachmultiple = new vB_Attachment_Display_Multiple($vbulletin);
		if ($results = $attachmultiple->fetch_results('a.userid = ' . $vbulletin->userinfo['userid'], true))
		{
			$show['attachments'] = true;
		}
	}

	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
	{
		$show['socialgroupslink'] = true;
	}

	if (!$vbulletin->options['subscriptionmethods'])
	{
		$show['paidsubscriptions'] = false;
	}
	else
	{
		// cache all the subscriptions - should move this to a datastore object at some point
		require_once(DIR . '/includes/class_paid_subscription.php');
		$subobj = new vB_PaidSubscription($vbulletin);
		$subobj->cache_user_subscriptions();
		$show['paidsubscriptions'] = false;
		foreach ($subobj->subscriptioncache AS $subscription)
		{
			$subscriptionid =& $subscription['subscriptionid'];
			if ($subscription['active'] AND (empty($subscription['deniedgroups']) OR count(array_diff(fetch_membergroupids_array($vbulletin->userinfo), $subscription['deniedgroups']))))
			{
				$show['paidsubscriptions'] = true;
				break;
			}
		}
	}

	// check to see if there are usergroups available
	$show['publicgroups'] = false;
	foreach ($vbulletin->usergroupcache AS $usergroup)
	{
		if ($usergroup['ispublicgroup'] OR ($usergroup['canoverride'] AND is_member_of($vbulletin->userinfo, $usergroup['usergroupid'])))
		{
			$show['publicgroups'] = true;
			break;
		}
	}

	// Setup Moderation Links
	if (can_moderate())
	{
		$show['deleteditems'] = true;
		$show['deletedmessages'] = true;
	}

	$show['moderatedposts'] = can_moderate(0, 'canmoderateposts');
	$show['deletedposts'] = ($show['moderatedposts'] OR can_moderate(0, 'candeleteposts') OR can_moderate(0, 'canremoveposts'));

	// visitor messages
	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging'])
	{
		$show['moderatedvms'] = can_moderate(0, 'canmoderatevisitormessages');
		$show['deletedvms'] = ($show['moderatedvms'] OR can_moderate(0, 'candeletevisitormessages') OR can_moderate(0, 'canremovevisitormessages'));
		$show['newvms'] = ($show['moderatedvms'] OR $show['deletedvms'] OR can_moderate(0, 'caneditvisitormessages'));
	}

	// group messages
	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'] AND $vbulletin->options['socnet_groups_msg_enabled'])
	{
		$show['moderatedgms'] = can_moderate(0, 'canmoderategroupmessages');
		$show['deletedgms'] = ($show['moderatedgms'] OR can_moderate(0, 'candeletegroupmessages') OR can_moderate(0, 'canremovegroupmessages'));
		$show['newgms'] = ($show['moderatedgms'] OR $show['deletedgms'] OR can_moderate(0, 'caneditgroupmessages'));
	}

	// group discussions
	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'] AND $vbulletin->options['socnet_groups_msg_enabled'])
	{
		$show['moderateddiscussions'] = can_moderate(0, 'canmoderatediscussions');
		$show['deleteddiscussions'] = ($show['moderateddiscussions'] OR (can_moderate(0, 'candeletediscussions') OR can_moderate(0, 'canremovediscussions')));
		$show['newdiscussions'] = ($show['moderateddiscussions'] OR $show['deleteddiscussions'] OR can_moderate(0, 'caneditdiscussions'));
	}

	// picture comments
	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['pc_enabled'])
	{
		$show['moderatedpcs'] = can_moderate(0, 'canmoderatepicturecomments');
		$show['deletedpcs'] = ($show['moderatedpcs'] OR can_moderate(0, 'candeletepicturecomments') OR can_moderate(0, 'canremovepicturecomments'));
		$show['newpcs'] = ($show['moderatedpcs'] OR $show['deletedpcs'] OR can_moderate(0, 'caneditpicturecomments'));
	}

	// pictures
	if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'])
	{
		$show['moderatedpics'] = can_moderate(0, 'canmoderatepictures');
		$show['newpics'] = ($show['moderatedpics'] OR can_moderate(0, 'caneditalbumpicture'));
	}

	$show['moderateditems'] = ($show['moderatedposts'] OR $show['moderatedvms'] OR $show['moderatedgms'] OR $show['moderateddiscussions'] OR $show['moderatedpcs'] OR $show['moderatedpics']);
	$show['deleteditems'] = ($show['deletedposts'] OR $show['deletedvms'] OR $show['deletedgms'] OR $show['deleteddiscussions'] OR $show['deletedpcs']);
	$show['newitems'] = ($show['newposts'] OR $show['newvms'] OR $show['newgms'] OR $show['newdiscussions'] OR $show['newpcs'] OR $show['newpics']);
	$show['moderation'] = ($show['moderateditems'] OR $show['deleteditems'] OR $show['newitems']);

	// album setup
	$show['albumlink'] = ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
		AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
		AND $vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
		AND $vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']
	);

	// set the class for each cell/group
	$navclass = array();
	foreach ($cells AS $cellname)
	{
		$navclass["$cellname"] = 'inactive';
	}
	$navclass["$selectedcell"] = 'active';

	// variable to hold templates for pm / subs folders
	$cpnav = array();

	// get PM folders
	$cpnav['pmfolders'] = '';
	$pmfolders = array('0' => $vbphrase['inbox'], '-1' => $vbphrase['sent_items']);
	if (!empty($vbulletin->userinfo['pmfolders']))
	{
		$pmfolders = $pmfolders + unserialize($vbulletin->userinfo['pmfolders']);
	}
	foreach ($pmfolders AS $folderid => $foldername)
	{
		$linkurl = 'private.php?' . $vbulletin->session->vars['sessionurl'] . "folderid=$folderid";
		$templater = vB_Template::create('usercp_nav_folderbit');
			$templater->register('selected', ($selectedcell == 'pm_folder' . $folderid));
			$templater->register('foldername', $foldername);
			$templater->register('linkurl', $linkurl);
		$cpnav['pmfolders'] .= $templater->render();
	}

	// get subscriptions folders
	$cpnav['subsfolders'] = '';
	$subsfolders = unserialize($vbulletin->userinfo['subfolders']);
	if (!empty($subsfolders))
	{
		foreach ($subsfolders AS $folderid => $foldername)
		{
			$linkurl = fetch_seo_url('subscription', array(), array('folderid' => $folderid));
			$templater = vB_Template::create('usercp_nav_folderbit');
				$templater->register('selected', ($selectedcell == 'subscr_folder' . $folderid));
				$templater->register('foldername', $foldername);
				$templater->register('linkurl', $linkurl);
			$cpnav['subsfolders'] .= $templater->render();
		}
	}
	if ($cpnav['subsfolders'] == '')
	{
		$linkurl = fetch_seo_url('subscription', array(), array('folderid' => 0));
		$foldername = $vbphrase['subscriptions'];
		$templater = vB_Template::create('usercp_nav_folderbit');
			$templater->register('selected', $selectedcell == 'subscr_folder0');
			$templater->register('foldername', $foldername);
			$templater->register('linkurl', $linkurl);
		$cpnav['subsfolders'] .= $templater->render();
	}

	($hook = vBulletinHook::fetch_hook('usercp_nav_complete')) ? eval($hook) : false;
}

/**
 * Fetches the Avatar Category Cache
 *
 * @param	array	User Information
 *
 * @return	array	Avatar Category Cache
 *
 */
function &fetch_avatar_categories(&$userinfo)
{
	global $vbulletin;
	static $categorycache = array();

	if (isset($categorycache["$userinfo[userid]"]))
	{
		return $categorycache["$userinfo[userid]"];
	}
	else
	{
		$categorycache["$userinfo[userid]"] = array();
	}

	$membergroups = fetch_membergroupids_array($userinfo);
	$infractiongroups = explode(',', str_replace(' ', '', $userinfo['infractiongroupids']));

	// ############### DISPLAY AVATAR CATEGORIES ###############
	// get all the available avatar categories
	$avperms = $vbulletin->db->query_read_slave("
		SELECT imagecategorypermission.imagecategoryid, usergroupid
		FROM " . TABLE_PREFIX . "imagecategorypermission AS imagecategorypermission, " . TABLE_PREFIX . "imagecategory AS imagecategory
		WHERE imagetype = 1
			AND imagecategorypermission.imagecategoryid = imagecategory.imagecategoryid
		ORDER BY imagecategory.displayorder
	");
	$noperms = array();
	while ($avperm = $vbulletin->db->fetch_array($avperms))
	{
		$noperms["{$avperm['imagecategoryid']}"][] = $avperm['usergroupid'];
	}
	foreach($noperms AS $imagecategoryid => $usergroups)
	{
		foreach($usergroups AS $usergroupid)
		{
			if (in_array($usergroupid, $infractiongroups))
			{
				$badcategories .= ",$imagecategoryid";
			}
		}
		if (!count(array_diff($membergroups, $usergroups)))
		{
			$badcategories .= ",$imagecategoryid";
		}
	}

	$categories = $vbulletin->db->query_read_slave("
		SELECT imagecategory.*, COUNT(avatarid) AS avatars
		FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON
			(avatar.imagecategoryid=imagecategory.imagecategoryid)
		WHERE imagetype=1
		AND avatar.minimumposts <= " . intval($userinfo['posts']) . "
		AND avatar.avatarid <> " . intval($userinfo['avatarid']) . "
		AND imagecategory.imagecategoryid NOT IN (0$badcategories)
		GROUP BY imagecategory.imagecategoryid
		HAVING avatars > 0
		ORDER BY imagecategory.displayorder
	");

	while ($category = $vbulletin->db->fetch_array($categories))
	{
		$categorycache["$userinfo[userid]"]["{$category['imagecategoryid']}"] = $category;
	}

	return $categorycache["$userinfo[userid]"];
}

/**
 * Fetches the URL for a User's Avatar if we already have a database record.
 *
 * @param	array		The database record
 * @param	boolean	Whether to get the Thumbnailed avatar or not
 *
 * @return	array	Information regarding the avatar
 *
 */
function fetch_avatar_from_record($avatarinfo, $thumb = false)
{
	if (!$avatarinfo['userid'])
	{
		return false;
	}

 	$userid = $avatarinfo['userid'];

 	if (!empty($avatarinfo['avatarpath']))
 	{
 		return array($avatarinfo['avatarpath']);
 	}
 	else if ($avatarinfo['hascustomavatar'])
 	{
 		$avatarurl = array('hascustomavatar' => 1);

 		if (vB::$vbulletin->options['usefileavatar'])
 		{
 			$avatarurl[] = vB::$vbulletin->options['avatarurl'] . ($thumb ? '/thumbs' : '') . "/avatar{$userid}_{$avatarinfo['avatarrevision']}.gif";
 		}
 		else
 		{
 			$avatarurl[] = "image.php?u=$userid&amp;dateline=$avatarinfo[dateline]" . ($thumb ? '&amp;type=thumb' : '') ;
 		}

 		if ($thumb)
 		{
 			if ($avatarinfo['width_thumb'] AND $avatarinfo['height_thumb'])
 			{
 				$avatarurl[] = " width=\"$avatarinfo[width_thumb]\" height=\"$avatarinfo[height_thumb]\" ";
 			}
 		}
 		else
 		{
 			if ($avatarinfo['width'] AND $avatarinfo['height'])
 			{
 				$avatarurl[] = " width=\"$avatarinfo[width]\" height=\"$avatarinfo[height]\" ";
 			}
 		}
 		return $avatarurl;
 	}
 	else
 	{
 		return '';
 	}
}

/**
 * Fetches the URL for a User's Avatar
 *
 * @param	integer	The User ID
 * @param	boolean	Whether to get the Thumbnailed avatar or not
 *
 * @return	array	Information regarding the avatar
 *
 */
function fetch_avatar_url($userid, $thumb = false)
{
	global $vbulletin, $show;
	static $avatar_cache = array();

	if (isset($avatar_cache["$userid"]))
	{
		$avatarurl = $avatar_cache["$userid"]['avatarurl'];
		$avatarinfo = $avatar_cache["$userid"]['avatarinfo'];
	}
	else
	{
		if ($avatarinfo = fetch_userinfo($userid, 2, 0, 1))
		{
			$perms = cache_permissions($avatarinfo, false);
			$avatarurl = array();

			if ($avatarinfo['hascustomavatar'])
			{
				$avatarurl = array('hascustom' => 1);

				if ($vbulletin->options['usefileavatar'])
				{
					$avatarurl[] = $vbulletin->options['avatarurl'] . ($thumb ? '/thumbs' : '') . "/avatar{$userid}_{$avatarinfo['avatarrevision']}.gif";
				}
				else
				{
					$avatarurl[] = "image.php?" . $vbulletin->session->vars['sessionurl'] . "u=$userid&amp;dateline=$avatarinfo[avatardateline]" . ($thumb ? '&amp;type=thumb' : '') ;
				}

				if ($thumb)
				{
					if ($avatarinfo['width_thumb'] AND $avatarinfo['height_thumb'])
					{
						$avatarurl[] = " width=\"$avatarinfo[width_thumb]\" height=\"$avatarinfo[height_thumb]\" ";
					}
				}
				else
				{
					if ($avatarinfo['avwidth'] AND $avatarinfo['avheight'])
					{
						$avatarurl[] = " width=\"$avatarinfo[avwidth]\" height=\"$avatarinfo[avheight]\" ";
					}
				}
			}
			elseif (!empty($avatarinfo['avatarpath']))
			{
				$avatarurl = array('hascustom' => 0, $avatarinfo['avatarpath']);
			}
			else
			{
				$avatarurl = '';
			}

		}
		else
		{
			$avatarurl = '';
		}

		$avatar_cache["$userid"]['avatarurl'] = $avatarurl;
		$avatar_cache["$userid"]['avatarinfo'] = $avatarinfo;
	}
	
	if ( // no avatar defined for this user
		empty($avatarurl)
		OR // visitor doesn't want to see avatars
		($vbulletin->userinfo['userid'] > 0 AND !$vbulletin->userinfo['showavatars'])
		OR // user has a custom avatar but no permission to display it
		(!$avatarinfo['avatarid'] AND !($perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']) AND !$avatarinfo['adminavatar']) //
	)
	{
		$show['avatar'] = false;
	}
	else
	{
		$show['avatar'] = true;
	}

	return $avatarurl;
}

/**
 * Fetches the User's Avatar from Pre-processed information. Avatar data placed
 * in $userinfo (avatarurl, avatarwidth, avatarheight)
 *
 * @param	array	User Information
 * @param	boolean	Whether to return a Thumbnail
 * @param	boolean	Whether to return a placeholder Avatar if no avatar is found
*/
function fetch_avatar_from_userinfo(&$userinfo, $thumb = false, $returnfakeavatar = true)
{
	global $vbulletin;

	if (!empty($userinfo['avatarpath']))
	{
		// using a non custom avatar
		if ($thumb)
		{
			if (@file_exists(DIR . '/images/avatars/thumbs/' . $userinfo['avatarid'] . '.gif'))
			{
				$userinfo['avatarurl'] = 'images/avatars/thumbs/' . $userinfo['avatarid'] . '.gif';
			}
			else
			{
				// no width/height known, scale to the maximum allowed width
				$userinfo['avatarwidth'] = FIXED_SIZE_AVATAR_WIDTH;
				$userinfo['avatarurl'] = $userinfo['avatarpath'];
			}
		}
		else
		{
			$userinfo['avatarurl'] = $userinfo['avatarpath'];
		}
	}
	else if ($userinfo['hascustom'] OR $userinfo['hascustomavatar'])
	{
		if (!isset($userinfo['adminavatar']))
		{
			$userinfo = array_merge($userinfo, convert_bits_to_array($userinfo['adminoptions'], $vbulletin->bf_misc_adminoptions));
		}
		
		if ($userinfo['adminavatar'])
		{
			$can_use_custom_avatar = true;
		}
		else
		{
			if (!isset($userinfo['permissions']))
			{
				cache_permissions($userinfo, false);
			}

			$can_use_custom_avatar = ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']);
		}

		if ($can_use_custom_avatar)
		{
			// custom avatar
			if ($vbulletin->options['usefileavatar'])
			{
				if ($thumb AND @file_exists($vbulletin->options['avatarpath'] . "/thumbs/avatar$userinfo[userid]_$userinfo[avatarrevision].gif"))
				{
					$userinfo['avatarurl'] = $vbulletin->options['avatarurl'] . "/thumbs/avatar$userinfo[userid]_$userinfo[avatarrevision].gif";
				}
				else
				{
					$userinfo['avatarurl'] =  $vbulletin->options['avatarurl'] . "/avatar$userinfo[userid]_$userinfo[avatarrevision].gif";
				}
			}
			else
			{
				if ($thumb AND $userinfo['filedata_thumb'])
				{
					$userinfo['avatarurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[avatardateline]&amp;type=thumb";
				}
				else
				{
					$userinfo['avatarurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[avatardateline]";
				}
			}

			if ($thumb)
			{
				// use the known sizes if available, otherwise calculate as necessary
				if ($userinfo['width_thumb'])
				{
					$userinfo['avatarwidth'] = $userinfo['width_thumb'];
					$userinfo['avatarheight'] = $userinfo['height_thumb'];
				}
				else if ($userinfo['avwidth'] AND $userinfo['avheight'])
				{
					// resize to the most restrictive size; never increase size (ratios > 1)
					$resize_ratio = min(1, FIXED_SIZE_AVATAR_WIDTH / $userinfo['avwidth'], FIXED_SIZE_AVATAR_HEIGHT / $userinfo['avheight']);
					$userinfo['avatarwidth'] = floor($userinfo['avwidth'] * $resize_ratio);
					$userinfo['avatarheight'] = floor($userinfo['avheight'] * $resize_ratio);
				}
				else
				{
					// no width/height known, scale to the maximum allowed width
					$userinfo['avatarwidth'] = FIXED_SIZE_AVATAR_WIDTH;
				}
			}
			else
			{
				$userinfo['avatarwidth'] = $userinfo['avwidth'];
				$userinfo['avatarheight'] = $userinfo['avheight'];
			}
		}
	}

	// final case: didn't get an avatar, so use the fake one
	if (empty($userinfo['avatarurl']) AND $returnfakeavatar AND $vbulletin->options['avatarenabled'])
	{
		$userinfo['avatarurl'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
	}
}

/**
 * Generates a totally random string
 *
 * @param	integer	Length of string to create
 *
 * @return	string	Generated String
 *
 */
function fetch_user_salt($length = 30)
{
	$salt = '';
	for ($i = 0; $i < $length; $i++)
	{
		$salt .= chr(vbrand(33, 126));
	}
	return $salt;
}

// nb: function verify_profilefields no longer exists, and is handled by vB_DataManager_User::set_userfields($values)

/**
 * Fetches the Profile Fields for a User input form
 *
 * @param	integer	Forum Type: 0 indicates a profile field, 1 indicates an option field
 *
 */
function fetch_profilefields($formtype = 0) // 0 indicates a profile field, 1 indicates an option field
{
	global $vbulletin, $customfields, $bgclass, $show;
	global $vbphrase, $altbgclass, $bgclass1, $tempclass;

	// get extra profile fields
	$profilefields = $vbulletin->db->query_read_slave("
		SELECT * FROM " . TABLE_PREFIX . "profilefield
		WHERE editable IN (1,2)
			AND form " . iif($formtype, '>= 1', '= 0'). "
		ORDER BY displayorder
	");
	while ($profilefield = $vbulletin->db->fetch_array($profilefields))
	{
		$profilefieldname = "field$profilefield[profilefieldid]";
		if ($profilefield['editable'] == 2 AND !empty($vbulletin->userinfo["$profilefieldname"]))
		{
			continue;
		}

		if ($formtype == 1 AND in_array($profilefield['type'], array('select', 'select_multiple')))
		{
			$show['optionspage'] = true;
		}
		else
		{
			$show['optionspage'] = false;
		}

		if (($profilefield['required'] == 1 OR $profilefield['required'] == 3) AND $profilefield['form'] == 0) // Ignore the required setting for fields on the options page
		{
			exec_switch_bg(1);
		}
		else
		{
			exec_switch_bg($profilefield['form']);
		}

		$tempcustom = fetch_profilefield($profilefield);

		// now add the HTML to the completed lists

		if (($profilefield['required'] == 1 OR $profilefield['required'] == 3) AND $profilefield['form'] == 0) // Ignore the required setting for fields on the options page
		{
			$customfields['required'] .= $tempcustom;
		}
		else
		{
			if ($profilefield['form'] == 0)
			{
				$customfields['regular'] .= $tempcustom;
			}
			else // not implemented
			{
				switch ($profilefield['form'])
				{
					case 1:
						$customfields['login'] .= $tempcustom;
						break;
					case 2:
						$customfields['messaging'] .= $tempcustom;
						break;
					case 3:
						$customfields['threadview'] .= $tempcustom;
						break;
					case 4:
						$customfields['datetime'] .= $tempcustom;
						break;
					case 5:
						$customfields['other'] .= $tempcustom;
						break;
					default:
						($hook = vBulletinHook::fetch_hook('profile_fetch_profilefields_loc')) ? eval($hook) : false;
				}
			}
		}


	}
}

/**
 * Fetches a single profile Field
 *
 * @param	array	Profile Field Record from the database
 * @param	string	Template to wrap this profilefield in
 *
 * @return	string	HTML for this Profile Field
 *
 */
function fetch_profilefield($profilefield, $wrapper_template = 'userfield_wrapper')
{
	global $vbulletin, $customfields, $bgclass, $show;
	global $vbphrase, $altbgclass, $bgclass1, $tempclass;

	$profilefieldname = "field$profilefield[profilefieldid]";
	$optionalname = $profilefieldname . '_opt';
	$optional = '';
	$optionalfield = '';

	$profilefield['title'] = $vbphrase[$profilefieldname . '_title'];
	$profilefield['description'] = $vbphrase[$profilefieldname . '_desc'];
	$profilefield['currentvalue'] = $vbulletin->userinfo["$profilefieldname"];

	($hook = vBulletinHook::fetch_hook('profile_fetch_profilefields')) ? eval($hook) : false;

	if ($profilefield['type'] == 'input')
	{
		$templater = vB_Template::create('userfield_textbox');
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'textarea')
	{
		$templater = vB_Template::create('userfield_textarea');
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'select')
	{
		$data = unserialize($profilefield['data']);
		$selectbits = '';
		$foundselect = 0;
		foreach ($data AS $key => $val)
		{
			$key++;
			$selected = '';
			if ($vbulletin->userinfo["$profilefieldname"])
			{
				if (trim($val) == $vbulletin->userinfo["$profilefieldname"])
				{
					$selected = 'selected="selected"';
					$foundselect = 1;
				}
			}
			else if ($profilefield['def'] AND $key == 1)
			{
				$selected = 'selected="selected"';
				$foundselect = 1;
			}
			$templater = vB_Template::create('userfield_select_option');
				$templater->register('key', $key);
				$templater->register('selected', $selected);
				$templater->register('val', $val);
			$selectbits .= $templater->render();
		}
		if ($profilefield['optional'])
		{
			if (!$foundselect AND (!empty($vbulletin->userinfo["$profilefieldname"]) OR $vbulletin->userinfo["$profilefieldname"] === '0'))
			{
				$optional = $vbulletin->userinfo["$profilefieldname"];
			}
			$templater = vB_Template::create('userfield_optional_input');
				$templater->register('optional', $optional);
				$templater->register('optionalname', $optionalname);
				$templater->register('profilefield', $profilefield);
				$templater->register('tabindex', $tabindex);
			$optionalfield = $templater->render();
		}
		if (!$foundselect)
		{
			$selected = 'selected="selected"';
		}
		else
		{
			$selected = '';
		}
		$show['noemptyoption'] = iif($profilefield['def'] != 2, true, false);
		$templater = vB_Template::create('userfield_select');
			$templater->register('optionalfield', $optionalfield);
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('selectbits', $selectbits);
			$templater->register('selected', $selected);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'radio')
	{
		$data = unserialize($profilefield['data']);
		$radiobits = '';
		$foundfield = 0;

		foreach ($data AS $key => $val)
		{
			$key++;
			$checked = '';
			if (!$vbulletin->userinfo["$profilefieldname"] AND $key == 1 AND $profilefield['def'] == 1)
			{
				$checked = 'checked="checked"';
			}
			else if (trim($val) == $vbulletin->userinfo["$profilefieldname"])
			{
				$checked = 'checked="checked"';
				$foundfield = 1;
			}
			$templater = vB_Template::create('userfield_radio_option');
				$templater->register('checked', $checked);
				$templater->register('key', $key);
				$templater->register('profilefieldname', $profilefieldname);
				$templater->register('val', $val);
			$radiobits .= $templater->render();
		}

		if ($profilefield['optional'])
		{
			if (!$foundfield AND $vbulletin->userinfo["$profilefieldname"])
			{
				$optional = $vbulletin->userinfo["$profilefieldname"];
			}
			$templater = vB_Template::create('userfield_optional_input');
				$templater->register('optional', $optional);
				$templater->register('optionalname', $optionalname);
				$templater->register('profilefield', $profilefield);
				$templater->register('tabindex', $tabindex);
			$optionalfield = $templater->render();
		}
		$templater = vB_Template::create('userfield_radio');
			$templater->register('optionalfield', $optionalfield);
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('radiobits', $radiobits);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'checkbox')
	{
		$data = unserialize($profilefield['data']);
		$radiobits = '';
		foreach ($data AS $key => $val)
		{
			if ($vbulletin->userinfo["$profilefieldname"] & pow(2,$key))
			{
				$checked = 'checked="checked"';
			}
			else
			{
				$checked = '';
			}
			$key++;
			$templater = vB_Template::create('userfield_checkbox_option');
				$templater->register('checked', $checked);
				$templater->register('key', $key);
				$templater->register('profilefieldname', $profilefieldname);
				$templater->register('val', $val);
			$radiobits .= $templater->render();
		}

		$templater = vB_Template::create('userfield_radio');
			$templater->register('optionalfield', $optionalfield);
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('radiobits', $radiobits);
		$custom_field_holder = $templater->render();
	}
	else if ($profilefield['type'] == 'select_multiple')
	{
		$data = unserialize($profilefield['data']);
		$selectbits = '';

		if ($profilefield['height'] == 0)
		{
			$profilefield['height'] = count($data);
		}

		foreach ($data AS $key => $val)
		{
			if ($vbulletin->userinfo["$profilefieldname"] & pow(2, $key))
			{
				$selected = 'selected="selected"';
			}
			else
			{
				$selected = '';
			}
			$key++;
			$templater = vB_Template::create('userfield_select_option');
				$templater->register('key', $key);
				$templater->register('selected', $selected);
				$templater->register('val', $val);
			$selectbits .= $templater->render();
		}
		$templater = vB_Template::create('userfield_select_multiple');
			$templater->register('profilefield', $profilefield);
			$templater->register('profilefieldname', $profilefieldname);
			$templater->register('selectbits', $selectbits);
		$custom_field_holder = $templater->render();
	}

	$templater = vB_Template::create($wrapper_template);
		$templater->register('custom_field_holder', $custom_field_holder);
		$templater->register('profilefield', $profilefield);
	return $templater->render();
}

/**
 * Checks whether the email provided is banned from the forums
 *
 * @param	string	The email address to check
 *
 * @return	boolean	Whether the email address is banned or not
 *
 */
function is_banned_email($email)
{
	global $vbulletin;

	if ($vbulletin->options['enablebanning'] AND $vbulletin->banemail !== null)
	{
		$bannedemails = preg_split('/\s+/', $vbulletin->banemail, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($bannedemails AS $bannedemail)
		{
			if (is_valid_email($bannedemail))
			{
				$regex = '^' . preg_quote($bannedemail, '#') . '$';
			}
			else
			{
				$regex = preg_quote($bannedemail, '#') . ($vbulletin->options['aggressiveemailban'] ? '' : '$');
			}

			if (preg_match("#$regex#i", $email))
			{
				return 1;
			}
		}
	}

	return 0;
}

/**
 * (Re)Generates an Activation ID for a user
 *
 * @param	integer	User's ID
 * @param	integer	The group to move the user to when they are activated
 * @param	integer	0 for Normal Activation, 1 for Forgotten Password
 * @param	boolean	Whether this is an email change or not
 *
 * @return	string	The Activation ID
 *
 */
function build_user_activation_id($userid, $usergroupid, $type, $emailchange = 0)
{
	global $vbulletin;

	if ($usergroupid == 3 OR $usergroupid == 0)
	{ // stop them getting stuck in email confirmation group forever :)
		$usergroupid = 2;
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid = $userid AND type = $type");
	$activateid = fetch_random_string(40);
	/*insert query*/
	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "useractivation
			(userid, dateline, activationid, type, usergroupid, emailchange)
		VALUES
			($userid, " . TIMENOW . ", '$activateid' , $type, $usergroupid, " . intval($emailchange) . ")
	");

	if ($userinfo = fetch_userinfo($userid))
	{
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdata->set_existing($userinfo);
		$userdata->set_bitfield('options', 'noactivationmails', 0);
		$userdata->save();
	}

	return $activateid;
}

/**
 * Constructs a Forum Jump Menu based on moderator permissions
 *
 * @param	integer	The "root" forum to work from
 * @param	integer	The ID of the forum that is currently selected
 * @param	integer	Characters to prepend to the item in the menu
 * @param	string	The moderator permission to check when building the Forum Jump Menu
 *
 * @return	string	The built forum Jump menu
 *
 */
function construct_mod_forum_jump($parentid = -1, $selectedid, $modpermission = '')
{
	global $vbulletin;

	if (empty($vbulletin->iforumcache))
	{
		cache_ordered_forums();
	}

	if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR $vbulletin->forumcache["$forumid"]['link'])
		{
			continue;
		}

		$children = construct_mod_forum_jump($forumid, $selectedid, $modpermission);

		if (!can_moderate($forumid, $modpermission) AND !$children)
		{
			continue;
		}

		// set $forum from the $vbulletin->forumcache
		$forum = $vbulletin->forumcache["$forumid"];

		$optionvalue = $forumid;
		$optiontitle = $forum[title_clean];
		$optionclass = 'd' . iif($forum['depth'] > 4, 4, $forum['depth']);
		$optionselected = '';

		if ($selectedid == $optionvalue)
		{
			$optionselected = 'selected="selected"';
			$optionclass .= ' fjsel';
		}

		$forumjumpbits .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);

		$forumjumpbits .= $children;

	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

	return $forumjumpbits;

}

/**
* Constructs the User's Custom CSS
*
* @param	array	An array of userinfo
* @param	bool	(Return) Whether to show the user css on/off switch to the user
*
* @return	string	HTML for the User's CSS
*/
function construct_usercss(&$userinfo, &$show_usercss_switch)
{
	global $vbulletin;

	// profile styling globally disabled
	if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_profile_styling']))
	{
		$show_usercss_switch = false;
		return '';
	}

	// check if permissions have changed and we need to rebuild this user's css
	if ($userinfo['hascachedcss'] AND $userinfo['cssbuildpermissions'] != $userinfo['permissions']['usercsspermissions'])
	{
		require_once(DIR . '/includes/class_usercss.php');
		$usercss = new vB_UserCSS($vbulletin, $userinfo['userid'], false);
		$userinfo['cachedcss'] = $usercss->update_css_cache();
	}

	if (!($vbulletin->userinfo['options'] & $vbulletin->bf_misc_useroptions['showusercss']) AND $vbulletin->userinfo['userid'] != $userinfo['userid'])
	{
		// user has disabled viewing css; they can reenable
		$show_usercss_switch = (trim($userinfo['cachedcss']) != '');
		$usercss = '';
	}
	else if (trim($userinfo['cachedcss']))
	{
		$show_usercss_switch = true;
		$userinfo['cachedcss'] = str_replace('/*sessionurl*/', $vbulletin->session->vars['sessionurl_js'], $userinfo['cachedcss']);
		$templater = vB_Template::create('memberinfo_usercss');
			$templater->register('userinfo', $userinfo);
		$usercss = $templater->render();
	}
	else
	{
		$show_usercss_switch = false;
		$usercss = '';
	}

	return $usercss;
}

/**
* Constructs the User's Custom CSS Switch Phrase
*
* @param	bool	If the switch is going to be shown or not
* @param	string	The phrase to use (Reference)
*
* @return	void
*/
function construct_usercss_switch($show_usercss_switch, &$usercss_switch_phrase)
{
	global $vbphrase, $vbulletin;

	if ($show_usercss_switch AND $vbulletin->userinfo['userid'])
	{
		if ($vbulletin->userinfo['options'] & $vbulletin->bf_misc_useroptions['showusercss'])
		{
			$usercss_switch_phrase = $vbphrase['hide_user_customizations'];
		}
		else
		{
			$usercss_switch_phrase = $vbphrase['show_user_customizations'];
		}
	}
}

/**
 * Gets the relationship of one user to another.
 *
 * The relationship level can be:
 *
 * 	3 - User 2 is a Friend of User 1 or is a Moderator
 *  2 - User 2 is on User 1's contact list
 *  1 - User 2 is a registered forum member
 *  0 - User 2 is a guest or ignored user
 *
 * @param int	$user1						- Id of user 1
 * @param int	$user2						- Id of user 2
 */
function fetch_user_relationship($user1, $user2)
{
	global $vbulletin;
	static $privacy_cache = array();

	$user1 = intval($user1);
	$user2 = intval($user2);

	if (!$user2)
	{
		return 0;
	}

	if (isset($privacy_cache["$user1-$user2"]))
	{
		return $privacy_cache["$user1-$user2"];
	}

	if ($user1 == $user2 OR can_moderate(0, '', $user2))
	{
		$privacy_cache["$user1-$user2"] = 3;
		return 3;
	}

	$contacts = $vbulletin->db->query_read_slave("
		SELECT type, friend
		FROM " . TABLE_PREFIX . "userlist AS userlist
		WHERE userlist.userid = " . $user1 . "
			AND userlist.relationid = " . $user2 . "
	");

	$return_value = 1;
	while ($contact = $vbulletin->db->fetch_array($contacts))
	{
		if ($contact['friend'] == 'yes')
		{
			$return_value = 3;
			break;
		}
		else if ($contact['type'] == 'ignore')
		{
			$return_value = 0;
			break;
		}
		else if ($contact['type'] == 'buddy')
		{
			// no break here, we neeed to make sure there is no other more definitive record
			$return_value = 2;
		}
	}
	$vbulletin->db->free_result($contacts);

	$privacy_cache["$user1-$user2"] = $return_value;
	return $return_value;
}

/**
* Determines if the browsing user can view a specific section of a user's profile.
*
* @param	integer	User ID to check against
* @param	string	Name of the section to check
* @param	string	Optional override for privacy requirement (prevents query)
* @param	array	Optional array of userinfo (to save on querying)
*
* @return	boolean
*/
function can_view_profile_section($userid, $section, $privacy_requirement = null, $userinfo = null)
{
	global $vbulletin;

	if (!$vbulletin->options['profileprivacy'])
	{
		// not enabled - always viewable
		return true;
	}

	if (!is_array($userinfo))
	{
		if ($userid == $vbulletin->userinfo['userid'])
		{
			return true;
		}

		$userinfo = fetch_userinfo($userid);
		if (!$userinfo)
		{
			return true;
		}
	}
	else if ($userinfo['userid'] == $vbulletin->userinfo['userid'])
	{
		return true;
	}

	if (!isset($userinfo['permissions']))
	{
		cache_permissions($userinfo, false);
	}

	if (!($userinfo['permissions']['usercsspermissions'] & $vbulletin->bf_ugp_usercsspermissions['caneditprivacy']))
	{
		// user doesn't have permission - always viewable
		return true;
	}

	if ($privacy_requirement === null)
	{
		$privacy_requirement = $vbulletin->db->query_first_slave("
			SELECT requirement
			FROM " . TABLE_PREFIX . "profileblockprivacy
			WHERE userid = " . intval($userinfo['userid']) . "
				AND blockid = '" . $vbulletin->db->escape_string($section) . "'
		");
		$privacy_requirement = ($privacy_requirement['requirement'] ? $privacy_requirement['requirement'] : 0);
	}

	return (!$privacy_requirement OR fetch_user_relationship($userinfo['userid'], $vbulletin->userinfo['userid']) >= $privacy_requirement);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44510 $
|| ####################################################################
\*======================================================================*/
?>
