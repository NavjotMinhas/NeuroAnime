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

if (!class_exists('vB_DataManager', false))
{
	exit;
}
require_once (DIR . '/vb/search/indexcontroller/queue.php');

/**
* Class to do data save/delete operations for profile messages
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_DataManager_VisitorMessage extends vB_DataManager
{
	/**
	* Array of recognised and required fields for visitormessage, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'vmid'           => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'userid'         => array(TYPE_UINT,       REQ_YES,  VF_METHOD, 'verify_profileuserid'),
		'postuserid'     => array(TYPE_UINT,       REQ_NO,   VF_METHOD, 'verify_userid'),
		'postusername'   => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD, 'verify_username'),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'state'          => array(TYPE_STR,        REQ_NO),
		'title'          => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD),
		'pagetext'       => array(TYPE_STR,        REQ_YES,  VF_METHOD),
		'ipaddress'      => array(TYPE_STR,        REQ_AUTO, VF_METHOD),
		'allowsmilie'    => array(TYPE_UINT,       REQ_NO),
		'reportthreadid' => array(TYPE_UINT,       REQ_NO),
		'messageread'    => array(TYPE_BOOL,       REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('vmid = %1$s', 'vmid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'visitormessage';

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		$title = trim($title);

		if ($this->registry->options['titlemaxchars'] AND $title != $this->existing['title'])
		{
			if (!empty($this->info['show_title_error']))
			{
				if (($titlelen = vbstrlen($title)) > $this->registry->options['titlemaxchars'])
				{
					// title too long
					$this->error('title_toolong', $titlelen, $this->registry->options['titlemaxchars']);
					return false;
				}
			}
			else if (empty($this->info['is_automated']))
			{
				// not showing the title length error, just chop it
				$title = vbchop($title, $this->registry->options['titlemaxchars']);
			}
		}

		require_once(DIR . '/includes/functions_newpost.php');
		// censor, remove all caps subjects, and htmlspecialchars title
		$title = fetch_no_shouting_text(fetch_censored_text($title));

		// do word wrapping
		$title = fetch_word_wrapped_string($title);

		return true;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_VisitorMessage(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('visitormessagedata_start')) ? eval($hook) : false;
	}


	/**
	 * Code to run before saving a Visitor Message
	 *
	 * @param	booleaan	Should we actually do the query?
	 *
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('state') === null)
			{
				$this->set('state', 'visible');
			}

			if ($this->fetch_field('dateline') === null)
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('ipaddress') === null)
			{
				$this->set('ipaddress', ($this->registry->options['logip'] ? IPADDRESS : ''));
			}

			if (!$this->info['preview'])
			{
				if (($this->registry->options['floodchecktime'] > 0 AND empty($this->info['is_automated']) AND $this->fetch_field('postuserid') AND $this->is_flooding()) OR $this->is_duplicate())
				{
					return false;
				}
			}

			// Posting to own profile, lets assume we've read it
			if ($this->fetch_field('userid') == $this->registry->userinfo['userid'])
			{
				$this->set('messageread', true);
			}
		}

		if (!$this->verify_image_count('pagetext', 'allowsmilie', 'socialmessage'))
		{
			return false;
		}

		// New posts that aren't automated and are visible should be scanned
		if (!$this->condition AND !empty($this->registry->options['vb_antispam_key']) AND empty($this->info['is_automated']) AND $this->fetch_field('state') == 'visible' AND (!$this->registry->options['vb_antispam_posts'] OR $this->info['user']['posts'] < $this->registry->options['vb_antispam_posts']) AND !can_moderate())
		{
			require_once(DIR . '/includes/class_akismet.php');
			$akismet = new vB_Akismet($this->registry);
			$akismet->akismet_board = $this->registry->options['bburl'];
			$akismet->akismet_key = $this->registry->options['vb_antispam_key'];
			if ($akismet->verify_text(array('user_ip' => IPADDRESS, 'user_agent' => USER_AGENT, 'comment_type' => 'post', 'comment_author' => ($this->info['user']['userid'] ? $this->info['user']['username'] : $this->fetch_field('postusername')), 'comment_author_email' => $this->info['user']['email'], 'comment_author_url' => $this->info['user']['homepage'], 'comment_content' => $this->fetch_field('pagetext'))) === 'spam')
			{
				$this->set('state', 'moderation');
				$this->spamlog_insert = true;
			}
		}

		if (in_coventry($this->fetch_field('postuserid'), true))
		{
			$this->set('messageread', true);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('visitormessagedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	 * Code to delete a Visitor Message
	 *
	 * @return	Whether this code successfully completed
	 *
	 */
	function delete()
	{
		if ($vmid = $this->existing['vmid'])
		{
			$db =& $this->registry->db;

			if ($this->info['hard_delete'])
			{
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $vmid AND type = 'visitormessage'
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "visitormessage WHERE vmid = $vmid
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $vmid AND type = 'visitormessage'
				");

				// Logging?
				//let's update the index
				vB_Search_Indexcontroller_Queue::indexQueue('vBForum', 'VisitorMessage', 'delete', $vmid);
			}
			else
			{
				$this->set('state', 'deleted');
				$this->save();

				$deletionman =& datamanager_init('Deletionlog_VisitorMessage', $this->registry, ERRTYPE_SILENT, 'deletionlog');
				$deletionman->set('primaryid', $vmid);
				$deletionman->set('type', 'visitormessage');
				$deletionman->set('userid', $this->registry->userinfo['userid']);
				$deletionman->set('username', $this->registry->userinfo['username']);
				$deletionman->set('reason', $this->info['reason']);
				$deletionman->save();
				unset($deletionman);
			}

			if (!$this->info['profileuser'])
			{
				$this->info['profileuser'] = fetch_userinfo($this->existing['userid']);
			}

			if ($this->info['profileuser'])
			{
				build_visitor_message_counters($this->info['profileuser']['userid']);
			}

			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $vmid AND type = 'visitormessage'
			");
			vB_Search_Indexcontroller_Queue::indexQueue('vBForum', 'VisitorMessage', 'index', $vmid);

			return true;
		}

		return false;
	}

	/**
	* Code to run after deleting a Visitor Message
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('visitormessagedata_delete')) ? eval($hook) : false;
	}


	/**
	* Code to run after Saving a Visitor Message
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$vmid = intval($this->fetch_field('vmid'));
		vB_Search_Indexcontroller_Queue::indexQueue('vBForum', 'VisitorMessage', 'index', $vmid);

		if (!$this->condition)
		{
			if ($this->fetch_field('userid'))
			{
				$this->insert_dupehash($this->fetch_field('userid'));
			}
		}

		if (!$this->info['profileuser'])
		{
			$this->info['profileuser'] = fetch_userinfo($this->fetch_field('userid'));
		}

		if ($this->info['profileuser'] AND !in_coventry($this->fetch_field('postuserid'), true))
		{
			$userdata =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
			$userdata->set_existing($this->info['profileuser']);

			if ($this->fetch_field('state') == 'visible')
			{
				if (!$this->condition AND !$this->fetch_field('messageread'))
				{
					// new vm, not been read, visible -> increase unread count
					$userdata->set('vmunreadcount', 'vmunreadcount + 1', false);
				}
				else if ($this->condition AND $this->fetch_field('messageread') AND isset($this->existing['messageread']) AND !$this->existing['messageread'])
				{
					// existing vm going from unread to read -> decrease unread count
					// isset() check ensures that messageread info was explicitly passed in
					$userdata->set('vmunreadcount', 'vmunreadcount - 1', false);
				}
			}

			if ($this->fetch_field('state') == 'visible' AND $this->existing['state'] == 'moderation')
			{
				// moderated message made visible -> decrease moderated count
				$userdata->set('vmmoderatedcount', 'vmmoderatedcount - 1', false);
			}
			else if ($this->fetch_field('state') == 'moderation' AND $this->fetch_field('state') != $this->existing['state'])
			{
				// message is moderated and wasn't moderated before -> increase moderated count
				$userdata->set('vmmoderatedcount', 'vmmoderatedcount + 1', false);
			}

			$userdata->save();
		}

		if ($this->fetch_field('state') == 'moderation')
		{
			/*insert query*/
			$this->dbobject->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "moderation
					(primaryid, type, dateline)
				VALUES
					($vmid, 'visitormessage', " . TIMENOW . ")
			");
		}
		else if ($this->fetch_field('state') == 'visible' AND $this->existing['state'] == 'moderation')
		{
			// message was made visible, remove the moderation record
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE primaryid = $vmid AND type = 'visitormessage'
			");
		}
		($hook = vBulletinHook::fetch_hook('visitormessagedata_postsave')) ? eval($hook) : false;
	}

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['user'] =& $this->registry->userinfo;
			$return = true;
		}
		else if ($userinfo = fetch_userinfo($userid))
		{	// This case should hit the cache most of the time
			$this->info['user'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return)
		{
				$this->do_set('postusername', $this->info['user']['username']);
		}

		return $return;
	}

	/**
	* Verifies that the specified user exists for the profile
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_profileuserid(&$userid)
	{
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['profileuser'] =& $this->registry->userinfo;
			$return = true;
		}
		else if ($userinfo = fetch_userinfo($userid))
		{	// This case should hit the cache most of the time
			$this->info['profileuser'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		return $return;
	}

	/**
	* Converts ip address into an integer
	*
	* @param	string	IP Address
	*
	* @param	bool	Whether the ip is valid
	*/
	function verify_ipaddress(&$ipaddress)
	{
		// need to run it through sprintf to get the integer representation
		$ipaddress = sprintf('%u', ip2long($ipaddress));
		return true;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_pagetext(&$pagetext)
	{
		if (empty($this->info['is_automated']))
		{
			if ($this->registry->options['vm_maxchars'] != 0 AND ($postlength = vbstrlen($pagetext)) > $this->registry->options['vm_maxchars'])
			{
				$this->error('toolong', $postlength, $this->registry->options['vm_maxchars']);
				return false;
			}

			$this->registry->options['postminchars'] = intval($this->registry->options['postminchars']);
			if ($this->registry->options['postminchars'] <= 0)
			{
				$this->registry->options['postminchars'] = 1;
			}
			if (vbstrlen(strip_bbcode($pagetext, $this->registry->options['ignorequotechars'])) < $this->registry->options['postminchars'])
			{
				$this->error('tooshort', $this->registry->options['postminchars']);
				return false;
			}
		}

		return parent::verify_pagetext($pagetext);

	}

	/**
	 * Determines whether the message being posted would constitute flooding
	 *
	 * @return      boolean Is this classed as flooding?
	 *
	 */
	function is_flooding()
	{
		$floodmintime = TIMENOW - $this->registry->options['floodchecktime'];
		if (!can_moderate() AND $this->fetch_field('dateline') > $floodmintime)
		{
			$flood = $this->registry->db->query_first("
				SELECT dateline
				FROM " . TABLE_PREFIX . "visitormessage_hash
				WHERE postuserid = " . $this->fetch_field('postuserid') . "
					AND dateline > " . $floodmintime . "
				ORDER BY dateline DESC
				LIMIT 1
			");
			if ($flood)
			{
				$this->error(
					'postfloodcheck',
					$this->registry->options['floodchecktime'],
					($flood['dateline'] - $floodmintime)
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this a duplicate post of a message posted with the last 5 minutes?
	 *
	 * @return      boolean whether this is a duplicate post or not
	 *
	 */
	function is_duplicate()
	{
		$dupemintime = TIMENOW - 300;
		if ($this->fetch_field('dateline') > $dupemintime)
		{
			// ### DUPE CHECK ###
			$dupehash = md5($this->fetch_field('userid') . $this->fetch_field('pagetext') . $this->fetch_field('postuserid'));
			if ($dupe = $this->registry->db->query_first("
				SELECT hash.userid, user.username
				FROM " . TABLE_PREFIX . "visitormessage_hash AS hash
				LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE hash.postuserid = " . $this->fetch_field('postuserid') . " AND
					hash.dupehash = '" . $this->registry->db->escape_string($dupehash) . "' AND
					hash.dateline > " . $dupemintime . "
			"))
			{
				// Do we want to only check for the post for this same user, or for all users???
				if ($dupe['userid'] == $this->fetch_field('userid'))
				{
					$this->error('duplicate_post');
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Inserts a hash into the database for use with duplicate checking
	 *
	 * @param       integer Group ID (-1 for current group from DM)
	 *
	 */
	function insert_dupehash($userid = -1)
	{
		if ($userid == -1)
		{
			$userid = $this->fetch_field('userid');
		}

		$postuserid = $this->fetch_field('postuserid');

		$dupehash = md5($userid . $this->fetch_field('pagetext') . $postuserid);
		/*insert query*/
		$this->dbobject->query_write("
			INSERT INTO " . TABLE_PREFIX . "visitormessage_hash
			(postuserid, userid, dupehash, dateline)
			VALUES
			(" . intval($postuserid) . ", " . intval($userid) . ", '" . $dupehash . "', " . TIMENOW . ")
		");
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>