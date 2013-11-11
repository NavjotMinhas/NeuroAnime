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


$parentassoc = array();

/**
 * Constructs a Forum Jump Menu for use when moving an item to a new forum
 *
 * @param	integer	The "Root" ID from which to generate this Menu
 * @param	integer	A Forum ID to "exclude" from the menu
 * @param	integer	If 1, removes all previous information from the Forum Jump Menu
 * @param	string	Characters to prepend to the items in the Jump Box
 *
 * @return	string	The generated forum jump menu
 *
 */
function construct_move_forums_options($parentid = -1, $excludeforumid = NULL, $addbox = 1)
{
	global $vbulletin, $optionselected, $jumpforumid, $jumpforumtitle, $jumpforumbits, $vbphrase, $curforumid;
	if (empty($vbulletin->iforumcache))
	{
		// get the vbulletin->iforumcache, as we use it all over the place, not just for forumjump
		cache_ordered_forums(0, 1);
	}
	if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	if ($addbox == 1)
	{
		$jumpforumbits = '';
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			continue;
		}
		else
		{
			// set $forum from the $vbulletin->forumcache
			$forum = $vbulletin->forumcache["$forumid"];

			$optionvalue = $forumid;
			$optiontitle = $forum[title];

			if ($forum['link'])
			{
				$optiontitle .= " ($vbphrase[link])";
			}
			else if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
			{
				$optiontitle .= " ($vbphrase[category])";
			}
			else if (!($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']))
			{
				$optiontitle .= " ($vbphrase[no_posting])";
			}

			
			$optionclass = 'd' . iif($forum['depth'] > 3, 3, $forum['depth']);

			if ($curforumid == $optionvalue)
			{
				$optionselected = ' ' . 'selected="selected"';
				$optionclass .= ' fjsel';
				$selectedone = 1;
			}
			else
			{
				$optionselected = '';
			}
			if ($excludeforumid == NULL OR $excludeforumid != $forumid)
			{
				$jumpforumbits .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
			}

			construct_move_forums_options($optionvalue, $excludeforumid, 0);

		} // if can view
	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

	return $jumpforumbits;
}

/**
 * Is this user the first poster in a threadid ?
 *
 * @param	integer	Thread ID to check
 * @param	integer	The User ID, or -1 for currently logged in user
 *
 * @return	boolean	Whether the user is the first poster.
 *
 */
function is_first_poster($threadid, $userid = -1)
{
	global $vbulletin;

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}
	$firstpostinfo = $vbulletin->db->query_first_slave("
		SELECT userid
		FROM " . TABLE_PREFIX . "post
		WHERE threadid = " . intval($threadid) . "
		ORDER BY dateline
	");
	return ($firstpostinfo['userid'] == $userid);
}

/**
* Extracts the threadid from the URL, correctly handles the different friendly URLs
*
* @param	string	The URL to try to pull the threadid from.
*
* @return	integer	Returns the threadid or 0 if no threadid is found.
*
*/
function extract_threadid_from_url($url)
{
	global $vbulletin;
	$threadid = 0;

	// Disallow relative URLs, since the t=threadid in the URL refers to another thread
	// Not needed since these URLs now redirect to the canonical URL?
	if (stripos($url, 'goto=next') !== false)
	{
		return $threadid;
	}

	$search = array(
		'#[\?&](?:threadid|t)=([0-9]+)#',
		'#showthread.php[\?/]([0-9]+)#',
		'#/threads/([0-9]+)#'
	);

	foreach ($search AS $regex)
	{
		if (preg_match($regex, $url, $matches))
		{
			$threadid = intval($matches[1]);
			break;
		}
	}

	if (!$threadid)
	{
		if (preg_match('#[\?&](postid|p)=([0-9]+)#', $url, $matches))
		{
			$postid = verify_id('post', $matches[2], false);
			if ($postid)
			{
				$postinfo = fetch_postinfo($postid);
				$threadid = intval($postinfo['threadid']);
			}
		}
	}

	return $threadid;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34059 $
|| ####################################################################
\*======================================================================*/
?>
