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

/**
* Class to do data save/delete operations for picture comments
*
* @package	vBulletin
* @version	$Revision: 42666 $
* @date		$Date: 2011-04-05 15:17:42 -0700 (Tue, 05 Apr 2011) $
*/
class vB_DataManager_PictureComment extends vB_DataManager
{
	/**
	* Array of recognised and required fields for picturecomment, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'commentid'      => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'filedataid'     => array(TYPE_UINT,       REQ_YES),
		'userid'         => array(TYPE_UINT,       REQ_YES),
		'postuserid'     => array(TYPE_UINT,       REQ_NO,   VF_METHOD, 'verify_userid'),
		'postusername'   => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD, 'verify_username'),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'state'          => array(TYPE_STR,        REQ_NO),
		//'title'          => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD),
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
	var $condition_construct = array('commentid = %1$s', 'commentid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'picturecomment';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_PictureComment(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('picturecommentdata_start')) ? eval($hook) : false;
	}

	/**
	 * Code to run before saving
	 *
	 * @param	boolean Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
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

			// Posting to own picture, lets assume we've read it
			if ($this->info['pictureuser']['userid'] AND $this->info['pictureuser']['userid'] == $this->registry->userinfo['userid'])
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
		($hook = vBulletinHook::fetch_hook('picturecommentdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	 * Code to run to delete a picture comment
	 *
	 * @param	boolean Do the query?
	 *
	 * @return	boolean	Whether this code executed correctly
	 *
	 */
	function delete()
	{
		if ($commentid = $this->existing['commentid'])
		{
			$db =& $this->registry->db;

			if ($this->info['hard_delete'])
			{
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $commentid AND type = 'picturecomment'
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "picturecomment WHERE commentid = $commentid
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $commentid AND type = 'picturecomment'
				");

				// Logging?
			}
			else
			{
				$this->set('state', 'deleted');
				$this->save();

				$deletionman =& datamanager_init('Deletionlog_PictureComment', $this->registry, ERRTYPE_SILENT, 'deletionlog');
				$deletionman->set('primaryid', $commentid);
				$deletionman->set('type', 'picturecomment');
				$deletionman->set('userid', $this->registry->userinfo['userid']);
				$deletionman->set('username', $this->registry->userinfo['username']);
				$deletionman->set('reason', $this->info['reason']);
				$deletionman->save();
				unset($deletionman);
			}

			if (!$this->info['pictureuser'])
			{
				$this->info['profileuser'] = fetch_userinfo($this->existing['userid']);
			}

			if ($this->info['pictureuser'])
			{
				build_picture_comment_counters($this->info['picteuser']['userid']);
			}

			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $commentid AND type = 'picturecomment'
			");

			return true;
		}

		$this->post_delete();

		return false;
	}

	/**
	* Code to run after deleting a picture comment
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('picturecommentdata_delete')) ? eval($hook) : false;
		return parent::post_delete($doquery);
	}


	/**
	* Code to run after saving a picture comment
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$commentid = intval($this->fetch_field('commentid'));

		if (!$this->condition)
		{
			if ($this->fetch_field('filedataid') AND $this->fetch_field('userid'))
			{
				$this->insert_dupehash($this->fetch_field('filedataid'), $this->fetch_field('userid'));
			}

			if ($this->info['pictureuser'] AND !in_coventry($this->fetch_field('postuserid'), true))
			{
				$userdata =& datamanager_init('User', $this->registry, ERRTYPE_STANDARD);
				$userdata->set_existing($this->info['pictureuser']);

				if (!$this->fetch_field('messageread') AND $this->fetch_field('state') == 'visible')
				{
					$userdata->set('pcunreadcount', 'pcunreadcount + 1', false);
				}
				else if ($this->fetch_field('state') == 'moderation')
				{
					$userdata->set('pcmoderatedcount', 'pcmoderatedcount + 1', false);
				}

				$userdata->save();
			}
		}

		if ($this->fetch_field('state') == 'moderation')
		{
			/*insert query*/
			$this->dbobject->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "moderation
					(primaryid, type, dateline)
				VALUES
					($commentid, 'picturecomment', " . TIMENOW . ")
			");
		}

		($hook = vBulletinHook::fetch_hook('picturecommentdata_postsave')) ? eval($hook) : false;
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
			if ($this->registry->options['postmaxchars'] != 0 AND ($postlength = vbstrlen($pagetext)) > $this->registry->options['postmaxchars'])
			{
				$this->error('toolong', $postlength, $this->registry->options['postmaxchars']);
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
	 * @return	boolean	Is this classed as flooding?
	 *
	 */
	function is_flooding()
	{
		$floodmintime = TIMENOW - $this->registry->options['floodchecktime'];
		if (!can_moderate() AND $this->fetch_field('dateline') > $floodmintime)
		{
			$flood = $this->registry->db->query_first("
				SELECT dateline
				FROM " . TABLE_PREFIX . "picturecomment_hash
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
	 * @return	boolean	whether this is a duplicate post or not
	 *
	 */
	function is_duplicate()
	{
		$dupemintime = TIMENOW - 300;
		if ($this->fetch_field('dateline') > $dupemintime)
		{
			// ### DUPE CHECK ###
			$dupehash = md5($this->fetch_field('filedataid') . '_' . $this->fetch_field('userid') . $this->fetch_field('pagetext') . $this->fetch_field('postuserid'));

			if ($dupe = $this->registry->db->query_first("
				SELECT hash.filedataid, hash.userid
				FROM " . TABLE_PREFIX . "picturecomment_hash AS hash
				WHERE
					hash.postuserid = " . $this->fetch_field('postuserid') . "
						AND
					hash.dupehash = '" . $this->registry->db->escape_string($dupehash) . "'
						AND
					hash.dateline > " . $dupemintime . "
			"))
			{
				// Do we want to only check for the post for this same user, or for all users???
				if ($dupe['filedataid'] == $this->fetch_field('filedataid') AND $dupe['userid'] == $this->fetch_field('userid'))
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
	 * @param	integer	Group ID (-1 for current group from DM)
	 *
	 */
	function insert_dupehash($filedataid = -1, $userid = -1)
	{
		if ($filedataid == -1)
		{
			$filedataid = $this->fetch_field('filedataid');
		}

		$postuserid = $this->fetch_field('postuserid');

		$dupehash = md5($filedataid . '_' . $userid . $this->fetch_field('pagetext') . $postuserid);
		/*insert query*/
		$this->dbobject->query_write("
			INSERT INTO " . TABLE_PREFIX . "picturecomment_hash
				(postuserid, filedataid, userid, dupehash, dateline)
			VALUES
				(" . intval($postuserid) . ", " . intval($filedataid) . ", " . intval($userid) . ", '" . $dupehash . "', " . TIMENOW . ")
		");
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42666 $
|| ####################################################################
\*======================================================================*/
?>
