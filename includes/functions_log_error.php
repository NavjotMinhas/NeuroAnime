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
* Fetches the integer value associated with a moderator log action string
*
* @param	string	The moderator log action
*
* @return	integer
*/
function fetch_modlogtypes($logtype)
{
	static $modlogtypes = array(
		'closed_thread'                           => 1,
		'opened_thread'                           => 2,
		'thread_moved_to_x'                       => 3,
		'thread_moved_with_redirect_to_a'         => 4,
		'thread_copied_to_x'                      => 5,
		'thread_edited_visible_x_open_y_sticky_z' => 6,
		'thread_merged_with_x'                    => 7,
		'thread_split_to_x'                       => 8,
		'unstuck_thread'                          => 9,
		'stuck_thread'                            => 10,
		'attachment_removed'                      => 11,
		'attachment_uploaded'                     => 12,
		'poll_edited'                             => 13,
		'thread_softdeleted'                      => 14,
		'thread_removed'                          => 15,
		'thread_undeleted'                        => 16,
		'post_x_by_y_softdeleted'                 => 17,
		'post_x_by_y_removed'                     => 18,
		'post_y_by_x_undeleted'                   => 19,
		'post_x_edited'                           => 20,
		'approved_thread'                         => 21,
		'unapproved_thread'                       => 22,
		'thread_merged_from_multiple_threads'     => 23,
		'unapproved_post'                         => 24,
		'approved_post'                           => 25,
		'post_merged_from_multiple_posts'         => 26,
		'approved_attachment'                     => 27,
		'unapproved_attachment'                   => 28,
		'thread_title_x_changed'                  => 29,
		'thread_redirect_removed'                 => 30,
		'posts_copied_to_x'                       => 31,

		'album_x_by_y_edited'                     => 32,
		'album_x_by_y_deleted'                    => 33,
		'picture_x_in_y_by_z_edited'              => 34,
		'picture_x_in_y_by_z_deleted'             => 35,
		// see 46 below as well

		'social_group_x_edited'                   => 36,
		'social_group_x_deleted'                  => 37,
		'social_group_x_members_managed'          => 38,
		'social_group_picture_x_in_y_removed'     => 39,

		'pc_by_x_on_y_edited'                     => 40,
		'pc_by_x_on_y_soft_deleted'               => 41,
		'pc_by_x_on_y_removed'                    => 42,
		'pc_by_x_on_y_undeleted'                  => 43,
		'pc_by_x_on_y_unapproved'                 => 44,
		'pc_by_x_on_y_approved'                   => 45,

		'picture_x_in_y_by_z_approved'            => 46,

		'gm_by_x_in_y_for_z_edited'               => 47,
		'gm_by_x_in_y_for_z_soft_deleted'         => 48,
		'gm_by_x_in_y_for_z_removed'              => 49,
		'gm_by_x_in_y_for_z_undeleted'            => 50,
		'gm_by_x_in_y_for_z_unapproved'           => 51,
		'gm_by_x_in_y_for_z_approved'             => 52,

		'vm_by_x_for_y_edited'                    => 53,
		'vm_by_x_for_y_soft_deleted'              => 54,
		'vm_by_x_for_y_removed'                   => 55,
		'vm_by_x_for_y_undeleted'                 => 56,
		'vm_by_x_for_y_unapproved'                => 57,
		'vm_by_x_for_y_approved'                  => 58,

		'discussion_by_x_for_y_edited'            => 59,
		'discussion_by_x_for_y_soft_deleted'      => 60,
		'discussion_by_x_for_y_removed'           => 61,
		'discussion_by_x_for_y_undeleted'         => 62,
		'discussion_by_x_for_y_unapproved'        => 63,
		'discussion_by_x_for_y_approved'          => 64,
	);

	($hook = vBulletinHook::fetch_hook('fetch_modlogtypes')) ? eval($hook) : false;

	return !empty($modlogtypes["$logtype"]) ? $modlogtypes["$logtype"] : 0;
}

/**
* Fetches the string associated with a moderator log action integer value
*
* @param	integer	The moderator log action
*
* @return	string
*/
function fetch_modlogactions($logaction)
{
	static $modlogactions = array(
		1	=>	'closed_thread',
		2	=>	'opened_thread',
		3	=>	'thread_moved_to_x',
		4	=>	'thread_moved_with_redirect_to_a',
		5	=>	'thread_copied_to_x',
		6	=>	'thread_edited_visible_x_open_y_sticky_z',
		7	=>	'thread_merged_with_x',
		8	=>	'thread_split_to_x',
		9	=>	'unstuck_thread',
		10	=>	'stuck_thread',
		11	=>	'attachment_removed',
		12	=>	'attachment_uploaded',
		13	=>	'poll_edited',
		14	=>	'thread_softdeleted',
		15	=>	'thread_removed',
		16	=>	'thread_undeleted',
		17	=>	'post_x_by_y_softdeleted',
		18	=>	'post_x_by_y_removed',
		19	=>	'post_y_by_x_undeleted',
		20	=>	'post_x_edited',
		21 => 'approved_thread',
		22 => 'unapproved_thread',
		23 => 'thread_merged_from_multiple_threads',
		24 => 'unapproved_post',
		25 => 'approved_post',
		26 => 'post_merged_from_multiple_posts',
		27 => 'approved_attachment',
		28 => 'unapproved_attachment',
		29 => 'thread_title_x_changed',
		30 => 'thread_redirect_removed',
		31 => 'posts_copied_to_x',

		32 => 'album_x_by_y_edited',
		33 => 'album_x_by_y_deleted',
		34 => 'picture_x_in_y_by_z_edited',
		35 => 'picture_x_in_y_by_z_deleted',
		// see 46 below as well

		36 => 'social_group_x_edited',
		37 => 'social_group_x_deleted',
		38 => 'social_group_x_members_managed',
		39 => 'social_group_picture_x_in_y_removed',

		40 => 'pc_by_x_on_y_edited',
		41 => 'pc_by_x_on_y_soft_deleted',
		42 => 'pc_by_x_on_y_removed',
		43 => 'pc_by_x_on_y_undeleted',
		44 => 'pc_by_x_on_y_unapproved',
		45 => 'pc_by_x_on_y_approved',

		46 => 'picture_x_in_y_by_z_approved',

		47 => 'gm_by_x_in_y_for_z_edited',
		48 => 'gm_by_x_in_y_for_z_soft_deleted',
		49 => 'gm_by_x_in_y_for_z_removed',
		50 => 'gm_by_x_in_y_for_z_undeleted',
		51 => 'gm_by_x_in_y_for_z_unapproved',
		52 => 'gm_by_x_in_y_for_z_approved',

		53 => 'vm_by_x_for_y_edited',
		54 => 'vm_by_x_for_y_soft_deleted',
		55 => 'vm_by_x_for_y_removed',
		56 => 'vm_by_x_for_y_undeleted',
		57 => 'vm_by_x_for_y_unapproved',
		58 => 'vm_by_x_for_y_approved',

		59 => 'discussion_by_x_for_y_edited',
		60 => 'discussion_by_x_for_y_soft_deleted',
		61 => 'discussion_by_x_for_y_removed',
		62 => 'discussion_by_x_for_y_undeleted',
		63 => 'discussion_by_x_for_y_unapproved',
		64 => 'discussion_by_x_for_y_approved',
	);

	($hook = vBulletinHook::fetch_hook('fetch_modlogactions')) ? eval($hook) : false;

	return !empty($modlogactions["$logaction"]) ? $modlogactions["$logaction"] : '';
}

/**
* Log errors to a file
*
* @param	string	The error message to be placed within the log
* @param	string	The type of error that occured. php, database, security, etc.
*
* @return	boolean
*/
function log_vbulletin_error($errstring, $type = 'database')
{
	global $vbulletin;

	// do different things depending on the error log type
	switch($type)
	{
		// log PHP E_USER_ERROR, E_USER_WARNING, E_WARNING to file
		case 'php':
			if (!empty($vbulletin->options['errorlogphp']))
			{
				$errfile = $vbulletin->options['errorlogphp'];
				$errstring .= "\r\nDate: " . date('l dS \o\f F Y h:i:s A') . "\r\n";
				$errstring .= "Username: {$vbulletin->userinfo['username']}\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
			}
			break;

		// log database error to file
		case 'database':
			if (!empty($vbulletin->options['errorlogdatabase']))
			{
				$errstring = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $errstring);
				$errfile = $vbulletin->options['errorlogdatabase'];
			}
			break;

		// log admin panel login failure to file
		case 'security':
			if (!empty($vbulletin->options['errorlogsecurity']))
			{
				$errfile = $vbulletin->options['errorlogsecurity'];
				$username = $errstring;
				$errstring  = 'Failed admin logon in ' . $vbulletin->db->appname . ' ' . $vbulletin->options['templateversion'] . "\r\n\r\n";
				$errstring .= 'Date: ' . date('l dS \o\f F Y h:i:s A') . "\r\n";
				$errstring .= "Script: http://$_SERVER[HTTP_HOST]" . unhtmlspecialchars($vbulletin->scriptpath) . "\r\n";
				$errstring .= 'Referer: ' . REFERRER . "\r\n";
				$errstring .= "Username: $username\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
				$errstring .= "Strikes: $GLOBALS[strikes]/5\r\n";
			}
			break;
	}

	// if no filename is specified, exit this function
	if (!isset($errfile) OR !($errfile = trim($errfile)) OR (defined('DEMO_MODE') AND DEMO_MODE == true))
	{
		return false;
	}

	// rotate the log file if filesize is greater than $vbulletin->options[errorlogmaxsize]
	if ($vbulletin->options['errorlogmaxsize'] != 0 AND $filesize = @filesize("$errfile.log") AND $filesize >= $vbulletin->options['errorlogmaxsize'])
	{
		@copy("$errfile.log", $errfile . TIMENOW . '.log');
		@unlink("$errfile.log");
	}

	// write the log into the appropriate file
	if ($fp = @fopen("$errfile.log", 'a+'))
	{
		@fwrite($fp, "$errstring\r\n=====================================================\r\n\r\n");
		@fclose($fp);
		return true;
	}
	else
	{
		return false;
	}
}

/**
* Performs a check to see if an error email should be sent
*
* @param	mixed	Consistent identifier identifying the error that occured
* @param	string	The type of error that occured. php, database, security, etc.
*
* @return	boolean
*/
function verify_email_vbulletin_error($error = '', $type = 'database')
{
	return true;
}

/**
* Logs the moderation actions that are being performed on the forum
*
* @param	array	Array of information indicating on what data the action was performed
* @param	integer	This value corresponds to the action that was being performed
* @param	string	Other moderator parameters
*/
function log_moderator_action($loginfo, $logtype, $action = '')
{
	global $vbulletin;

	$modlogsql = array();

	if ($result = fetch_modlogtypes($logtype))
	{
		$logtype = $result;
	}

	($hook = vBulletinHook::fetch_hook('log_moderator_action')) ? eval($hook) : false;

	if (is_array($loginfo[0]))
	{
		foreach ($loginfo AS $index => $log)
		{
			if (is_array($action))
			{
				$action = serialize($action);
			}
			$modlogsql[] = "(" . intval($logtype) . ", " . intval($log['userid']) . ", " . TIMENOW . ", " . intval($log['forumid']) . ", " . intval($log['threadid']) . ", " . intval($log['postid']) . ", " . intval($log['pollid']) . ", " . intval($log['attachmentid']) . ", '" . $vbulletin->db->escape_string($action) . "', '" . $vbulletin->db->escape_string(IPADDRESS) . "')";
		}

		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "moderatorlog (type, userid, dateline, forumid, threadid, postid, pollid, attachmentid, action, ipaddress) VALUES " . implode(', ', $modlogsql));
	}
	else
	{
		$moderatorlog['userid'] =& $vbulletin->userinfo['userid'];
		$moderatorlog['dateline'] = TIMENOW;

		$moderatorlog['type'] = intval($logtype);

		$moderatorlog['forumid'] = intval($loginfo['forumid']);
		$moderatorlog['threadid'] = intval($loginfo['threadid']);
		$moderatorlog['postid'] = intval($loginfo['postid']);
		$moderatorlog['pollid'] = intval($loginfo['pollid']);
		$moderatorlog['attachmentid'] = intval($loginfo['attachmentid']);

		$moderatorlog['ipaddress'] = IPADDRESS;

		if (is_array($action))
		{
			$action = serialize($action);
		}
		$moderatorlog['action'] = $action;

		/*insert query*/
		$vbulletin->db->query_write(fetch_query_sql($moderatorlog, 'moderatorlog'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>