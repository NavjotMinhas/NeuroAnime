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

require_once(DIR . '/includes/functions_newpost.php');

/**
* Base data manager for threads and posts. Uninstantiable.
*
* @package	vBulletin
* @version	$Revision: 45135 $
* @date		$Date: 2011-06-27 11:49:33 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_ThreadPost extends vB_DataManager
{
	/**
	* If doing a flood check, this will hold the flood check object.
	* Needed for rollbacks.
	*
	* @var	null|vB_Floodcheck
	*/
	var $floodcheck = null;

	/**
	* If the post was marked as spam in pre-save, insert a row in postsave
	*
	* @var	boolean
	*/
	var $spamlog_insert = false;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_ThreadPost(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_ThreadPost'))
		{
			trigger_error("Direct Instantiation of vB_DataManager_ThreadPost class prohibited.", E_USER_ERROR);
		}

		parent::vB_DataManager($registry, $errtype);
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
		else if ($userinfo = $this->dbobject->query_first_slave("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
		{
			$this->info['user'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return == true)
		{
			if (isset($this->validfields['username']))
			{
				$this->do_set('username', $this->info['user']['username']);
			}
			else if (isset($this->validfields['postusername']))
			{
				$this->do_set('postusername', $this->info['user']['username']);
			}
		}

		return $return;
	}

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
			else if (empty($this->info['is_automated']) OR !empty($this->info['chop_title']))
			{
				// not showing the title length error, just chop it
				$title = vbchop($title, $this->registry->options['titlemaxchars']);
			}
		}

		// censor, remove all caps subjects, and htmlspecialchars post title
		$title = htmlspecialchars_uni(fetch_no_shouting_text(fetch_censored_text($title)));

		// do word wrapping
		if ($this->registry->options['wordwrap'] != 0)
		{
			$title = fetch_word_wrapped_string($title);
		}

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
	* Verifies that the icon selected is valid.
	*
	* @param	integer	The ID of the icon
	*
	* @return	bool	Whether the icon is valid
	*/
	function verify_iconid(&$iconid)
	{
		if ($iconid)
		{
			// try to improve permission checking on icons
			if (!$this->info['user'])
			{
				$userid = $this->fetch_field('userid');
				if (!$userid)
				{
					$userid = $this->fetch_field('postuserid');
				}

				$this->set_info('user', fetch_userinfo($userid));
			}

			if ($this->info['user'])
			{
				$membergroups = fetch_membergroupids_array($this->info['user']);
			}
			else
			{
				// this is assumed to be a guest; go magic numbers!
				$membergroups = array(1);
			}
			$imagecheck = $this->dbobject->query_read_slave("
				SELECT usergroupid FROM " . TABLE_PREFIX . "icon AS icon
				INNER JOIN " . TABLE_PREFIX . "imagecategorypermission USING (imagecategoryid)
				WHERE icon.iconid = $iconid
					AND usergroupid IN (" . $this->dbobject->escape_string(implode(',', $membergroups)) . ")
			");

			if ($this->dbobject->num_rows($imagecheck) == sizeof($membergroups))
			{
				$iconid = 0;
			}
		}

		return true;
	}

	/**
	* Fetches the amount of attachments associated with a posthash and user
	*
	* @param	string	Post hash
	* @param	integer	User ID associated with post hash (-1 means current user)
	*
	* @return	integer	Number of attachments
	*/
	function fetch_attachment_count($posthash, $userid = -1)
	{
		if ($userid == -1)
		{
			$userid = $this->fetch_field('userid', 'post');
		}
		$userid = intval($userid);

		$attachcount = $this->dbobject->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "attachment
			WHERE
				posthash = '" . $this->dbobject->escape_string($posthash) . "'
					AND
				userid = $userid
		");

		return intval($attachcount['count']);
	}

	function insert_dupehash($threadid = -1)
	{
		if ($threadid == -1)
		{
			$threadid = $this->fetch_field('threadid');
		}

		$type = ($threadid > 0 ? 'reply' : 'thread');

		$forumid = $this->fetch_field('forumid');
		if (!$forumid)
		{
			$forumid = $this->info['forum']['forumid'];
		}

		$userid = $this->fetch_field('postuserid');
		if (!$userid)
		{
			$userid = $this->fetch_field('userid');
		}

		$dupehash = md5($forumid . $this->fetch_field('title') . $this->fetch_field('pagetext', 'post') . $userid . $type);

		/*insert query*/
		$this->dbobject->query_write("
			INSERT INTO " . TABLE_PREFIX . "posthash
			(userid, threadid, dupehash, dateline)
			VALUES
			(" . intval($userid) . ", " . intval($threadid) . ", '" . $dupehash . "', " . TIMENOW . ")
		");
	}

	/**
	* Inserts Post Log data for Akismet
	*
	* @return	void
	*/
	function insert_postlog_data()
	{
		if (empty($this->info['is_automated']))
		{
			$postid = intval($this->fetch_field($this->table == 'post' ? 'postid' : 'firstpostid'));

			/*insert query*/
			$this->dbobject->query_write("
				INSERT INTO " . TABLE_PREFIX . "postlog
				(postid, useragent, ip, dateline)
				VALUES
				(" . $postid . ", '" . $this->dbobject->escape_string(USER_AGENT) . "', " . sprintf('%u', ip2long(IPADDRESS)) . ", " . TIMENOW . ")
			");
		}
	}

	function akismet_mark_as_ham($postid)
	{
		$spamlog_check = $this->dbobject->query_first("SELECT * FROM " . TABLE_PREFIX . "spamlog WHERE postid = " . $postid);
		if (empty($spamlog_check))
		{ // Akismet doesn't appear to have really marked this as spam
			return;
		}

		$postdata = $this->dbobject->query_first("SELECT post.username AS username, post.pagetext AS pagetext, postlog.ip AS ip, postlog.useragent AS useragent FROM " . TABLE_PREFIX . "post AS post INNER JOIN " . TABLE_PREFIX . "postlog AS postlog ON(postlog.postid = post.postid) WHERE post.postid = " . $postid);
		if (!empty($postdata) AND !empty($this->registry->options['vb_antispam_key']))
		{
			require_once(DIR . '/includes/class_akismet.php');
			$akismet = new vB_Akismet($this->registry);
			$akismet->akismet_board = $this->registry->options['bburl'];
			$akismet->akismet_key = $this->registry->options['vb_antispam_key'];
			$akismet->mark_as_ham(array('user_ip' => $postdata['ip'], 'user_agent' => $postdata['useragent'], 'comment_type' => 'post', 'comment_author' => $postdata['username'], 'comment_content' => $postdata['pagetext']));
		}

		$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "spamlog WHERE postid = " . $postid);
	}

	function email_moderators($fields)
	{
		if ($this->info['skip_moderator_email'] OR !$this->info['forum'] OR in_coventry($this->fetch_field('userid', 'post'), true))
		{
			return;
		}

		$mod_emails = fetch_moderator_newpost_emails($fields, $this->info['forum']['parentlist'], $newpost_lang);

		if (!empty($mod_emails))
		{
			$foruminfo = $this->info['forum'];
			$foruminfo['title_clean'] = unhtmlspecialchars($foruminfo['title_clean']);

			$threadinfo = fetch_threadinfo($this->fetch_field('threadid'));

			require_once(DIR . '/includes/class_bbcode_alt.php');
			$plaintext_parser = new vB_BbCodeParser_PlainText($this->registry, fetch_tag_list());

			$email = ($this->info['user']['email'] ? $this->info['user']['email'] : $this->registry->userinfo['email']);
			$browsing_user = $this->registry->userinfo['username'];

			// ugly hack -- should be fixed in the future
			$this->registry->userinfo['username'] = unhtmlspecialchars($this->info['user']['username'] ? $this->info['user']['username'] : $this->registry->userinfo['username']);

			$post = array_merge($this->existing, $this->post);
			if (!$post['postid'])
			{
				$post['postid'] = $this->thread['firstpostid'];
			}

			require_once(DIR . '/includes/functions_misc.php');

			foreach ($mod_emails AS $toemail)
			{
				if ($toemail != $email)
				{
					$plaintext_parser->set_parsing_language(isset($newpost_lang["$toemail"]) ? $newpost_lang["$toemail"] : 0);
					$post['message'] = $plaintext_parser->parse($this->post['pagetext'], $foruminfo['forumid']);

					if ($threadinfo['prefixid'])
					{
						// need prefix in correct language
						$threadinfo['prefix_plain'] = fetch_phrase(
							"prefix_$threadinfo[prefixid]_title_plain",
							'global',
							'',
							false,
							true,
							isset($newpost_lang["$toemail"]) ? $newpost_lang["$toemail"] : 0,
							false
						) . ' ';
					}
					else
					{
						$threadinfo['prefix_plain'] = '';
					}

					$threadlink = fetch_seo_url('thread|nosession|bburl', $threadinfo);
					eval(fetch_email_phrases('moderator', iif(isset($newpost_lang["$toemail"]), $newpost_lang["$toemail"], 0)));
					vbmail($toemail, $subject, $message);
				}
			}

			// back to normal
			$this->registry->userinfo['username'] = htmlspecialchars_uni($browsing_user);
		}
	}

	function rebuild_keywords()
	{
		require_once(DIR . '/includes/functions_newpost.php');

		$threadinfo = array('taglist' => $this->fetch_field('taglist'), 'prefixid' => $this->fetch_field('prefixid'), 'title' => $this->fetch_field('title'));
		$keywords = fetch_keywords_list($threadinfo, (empty($this->info['pagetext']) ? $this->fetch_field('pagetext', 'post') : $this->info['pagetext']));

		$this->set('keywords', $keywords);
	}

	/**
	* This is a pre_save method that only applies to the subclasses that have post
	* fields as their members (ie, not _Thread). Likely only called in those class's
	* pre_save methods.
	*
	* @return	bool	True on success, false on failure
	*/
	function pre_save_post($doquery = true)
	{
		if ($this->info['forum']['podcast'] AND $this->info['podcasturl'] AND empty($this->info['podcastsize']))
		{
			require_once(DIR . '/includes/class_upload.php');
			$upload = new vB_Upload_Abstract($this->registry);
			if (!($this->info['podcastsize'] = intval($upload->fetch_remote_filesize($this->info['podcasturl']))))
			{
				$this->error('invalid_podcasturl');
				return false;
			}
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('userid', 'post') == 0 AND $this->fetch_field('username', 'post') == '')
			{
				$this->error('nousername');
				return false;
			}

			if ($this->fetch_field('dateline', 'post') === null)
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('ipaddress', 'post') === null)
			{
				$this->set('ipaddress', ($this->registry->options['logip'] ? IPADDRESS : ''));
			}

			// flood check
			if ($this->registry->options['floodchecktime'] > 0 AND empty($this->info['preview']) AND empty($this->info['is_automated']) AND $this->fetch_field('userid', 'post'))
			{
				if (!$this->info['user'])
				{
					$this->info['user'] = fetch_userinfo($this->fetch_field('userid', 'post'));
				}
				$user =& $this->info['user'];

				if ($user['lastpost'] <= TIMENOW AND
					!can_moderate($this->info['forum']['forumid'], '', $user['userid'], $user['usergroupid'] . (trim($user['membergroupids']) ? ",$user[membergroupids]" : '')))
				{
					if (!class_exists('vB_FloodCheck', false))
					{
						require_once(DIR . '/includes/class_floodcheck.php');
					}
					$this->floodcheck = new vB_FloodCheck($this->registry, 'user', 'lastpost');
					$this->floodcheck->commit_key($this->registry->userinfo['userid'], TIMENOW, TIMENOW - $this->registry->options['floodchecktime']);
					if ($this->floodcheck->is_flooding())
					{
						$this->error('postfloodcheck', $this->registry->options['floodchecktime'], $this->floodcheck->flood_wait());
						return false;
					}

					if ($this->errors)
					{
						// if we already have errors, the save won't happen, so rollback now...
						$this->floodcheck->rollback();
					}
					else
					{
						// ...or, in case we have a new error
						$this->set_failure_callback(array(&$this->floodcheck, 'rollback'));
					}
				}
			}

		}

		if (!$this->verify_image_count('pagetext', 'allowsmilie', $this->info['forum']['forumid'], 'post'))
		{
			return false;
		}

		if ($this->info['posthash'])
		{
			$this->info['newattach'] = $this->fetch_attachment_count($this->info['posthash'], $this->fetch_field('userid', 'post'));
			$this->set('attach',
				intval($this->fetch_field('attach')) +
				$this->info['newattach']
			);
		}

		// New posts that aren't automated and are visible should be scanned
		if (!$this->condition AND !empty($this->registry->options['vb_antispam_key']) AND empty($this->info['is_automated']) AND $this->fetch_field('visible') == 1 AND (!$this->registry->options['vb_antispam_posts'] OR $this->registry->userinfo['posts'] < $this->registry->options['vb_antispam_posts']) AND !can_moderate())
		{
			require_once(DIR . '/includes/class_akismet.php');
			$akismet = new vB_Akismet($this->registry);
			$akismet->akismet_board = $this->registry->options['bburl'];
			$akismet->akismet_key = $this->registry->options['vb_antispam_key'];
			if ($akismet->verify_text(array('user_ip' => IPADDRESS, 'user_agent' => USER_AGENT, 'comment_type' => 'post', 'comment_author' => ($this->registry->userinfo['userid'] ? $this->registry->userinfo['username'] : $this->fetch_field('username', 'post')), 'comment_author_email' => $this->registry->userinfo['email'], 'comment_author_url' => $this->registry->userinfo['homepage'], 'comment_content' => $this->fetch_field('pagetext', 'post'))) === 'spam')
			{
				$this->set('visible', 0);
				$this->spamlog_insert = true;
			}
		}

		return true;
	}

	/**
	* Post save function run on each record. Applies only if there was a post submitted.
	*/
	function post_save_each_post($doquery = true)
	{
		$postid = intval($this->fetch_field($this->table == 'post' ? 'postid' : 'firstpostid'));

		if (!$this->info['user'] AND $this->registry->userinfo['userid'] AND $this->fetch_field('userid', 'post') == $this->registry->userinfo['userid'])
		{
			$this->set_info('user', $this->registry->userinfo);
		}

		if ($this->info['posthash'] AND $this->fetch_field('attach') AND $postid)
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "attachment
				SET
					contentid = $postid,
					posthash = ''
				WHERE
					posthash = '" . $this->dbobject->escape_string($this->info['posthash']) . "'
						AND
					userid = " . intval($this->fetch_field('userid', 'post')) . "
			");
		}

		if ($this->condition AND $postid)
		{
			if ($this->post['pagetext'])
			{
				if ($this->fetch_field('userid', 'post') != $this->registry->userinfo['userid'])
				{ // if another user edits the post then the postlog information is no longer valid.
					$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "postlog WHERE postid = " . intval($postid));
				}

				$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "postparsed WHERE postid = " . intval($postid));

				if ($this->info['forum'])
				{
					require_once(DIR . '/includes/functions_databuild.php');
					delete_post_index($postid, $this->existing['title'], $this->existing['pagetext']);
				}
			}

			// Check to see if this was a spam post being approved
			if ($this->existing['visible'] == 0 AND $this->fetch_field('visible') == 1)
			{
				$this->akismet_mark_as_ham($postid);
			}
		}

		if ($this->post['pagetext'] AND $this->info['forum'] AND $postid)
		{
			// ### UPDATE SEARCH INDEX ###
			require_once(DIR . '/includes/functions_databuild.php');
			build_post_index($postid, $this->info['forum']);
		}

		if ($this->spamlog_insert AND $postid)
		{
			$this->dbobject->query_write("INSERT INTO " . TABLE_PREFIX . "spamlog (postid) VALUES ($postid)");
		}

		if (!$this->condition AND $this->fetch_field('visible') == 1)
		{
			if ($this->info['forum'] AND $this->fetch_field('dateline') == TIMENOW)
			{
				$forumdata =& datamanager_init('Forum', $this->registry, ERRTYPE_SILENT);
				$forumdata->set_existing($this->info['forum']);
				$forumdata->set_info('disable_cache_rebuild', true);

				if (in_coventry($this->fetch_field('userid', 'post'), true))
				{
					$forumdata->set_info(
						'coventry',
						array(
							'in_coventry'	=> 1,
							'userid'	=> $this->fetch_field('userid', 'post')
						)
					);
				}

				if ($this->table == 'thread')
				{
					// we're inserting a new thread
					$forumdata->set('threadcount', 'threadcount + 1', false);
				}

				$forumdata->set('replycount', 'replycount + 1', false);
				$forumdata->set('lastpost', $this->fetch_field('dateline'));
				$forumdata->set('lastpostid', $postid);
				$forumdata->set('lastposter', $this->fetch_field('username', 'post'));
				$forumdata->set('lastposterid', $this->fetch_field('userid', 'post'));

				if ($this->table == 'thread')
				{
					$forumdata->set('lastthread', $this->fetch_field('title'));
					$forumdata->set('lastthreadid', $this->fetch_field('threadid'));
					$forumdata->set('lasticonid', ($this->fetch_field('pollid') ? -1 : $this->fetch_field('iconid')));
					$forumdata->set('lastprefixid', $this->fetch_field('prefixid'));
				}
				else if ($this->info['thread'])
				{
					$forumdata->set('lastthread', $this->info['thread']['title']);
					$forumdata->set('lastthreadid', $this->info['thread']['threadid']);
					$forumdata->set('lasticonid', ($this->info['thread']['pollid'] ? -1 : $this->info['thread']['iconid']));
					$forumdata->set('lastprefixid', $this->info['thread']['prefixid']);
				}

				$forumdata->save();
			}

			if ($this->info['user'] AND (empty($this->info['is_automated']) OR $this->info['is_automated'] == 'rss'))
			{
				$user =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$user->set_existing($this->info['user']);

				if ($this->info['forum']['countposts'])
				{
					$user->set('posts', 'posts + 1', false);
					$user->set_ladder_usertitle($this->info['user']['posts'] + 1);
				}

				$dateline = $this->fetch_field('dateline');

				if ($dateline == TIMENOW OR (isset($this->info['user']['lastpost']) AND $dateline > $this->info['user']['lastpost']))
				{
					$user->set('lastpost', $dateline);
				}

				$postid = intval($this->fetch_field('postid'));

				if ($dateline == TIMENOW OR (isset($this->info['user']['lastpostid']) AND $postid > $this->info['user']['postid']))
				{
					$user->set('lastpostid', $postid);
				}

				$user->save();
			}
		}
	}
}

/**
* Class to do data save/delete operations for POSTS
*
* @package	vBulletin
* @version	$Revision: 45135 $
* @date		$Date: 2011-06-27 11:49:33 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_Post extends vB_DataManager_ThreadPost
{
	/**
	* Array of recognised and required fields for posts, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'postid'         => array(TYPE_UINT, REQ_INCR,  'return ($data > 0);'),
		'threadid'       => array(TYPE_UINT, REQ_YES),
		'parentid'       => array(TYPE_UINT, REQ_AUTO),
		'username'       => array(TYPE_STR,  REQ_NO,    VF_METHOD),
		'userid'         => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'title'          => array(TYPE_STR,  REQ_NO,    VF_METHOD),
		'dateline'       => array(TYPE_UINT, REQ_AUTO),
		'pagetext'       => array(TYPE_STR,  REQ_YES,   VF_METHOD),
		'allowsmilie'    => array(TYPE_UINT, REQ_YES), // this is required as we must know whether smilies count as images
		'showsignature'  => array(TYPE_BOOL, REQ_NO),
		'ipaddress'      => array(TYPE_STR,  REQ_AUTO),
		'iconid'         => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'visible'        => array(TYPE_UINT, REQ_NO),
		'attach'         => array(TYPE_UINT, REQ_NO),
		'infraction'     => array(TYPE_UINT, REQ_NO),
		'reportthreadid' => array(TYPE_UINT, REQ_NO),
		'htmlstate'      => array(TYPE_STR, REQ_NO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'post';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('postid = %1$d', 'postid');

	/**
	* Array to store stuff to save to post table
	*
	* @var	array
	*/
	var $post = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Post(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_ThreadPost($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('postdata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->pre_save_post($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->condition AND $this->fetch_field('parentid') === null AND ($this->info['thread'] OR $this->post['threadid']))
		{
			// we're not posting a new thread, so make this post a child of the first post in the thread
			if ($this->info['thread']['firstpostid'])
			{
				$this->set('parentid', $this->info['thread']['firstpostid']);
			}
			else
			{
				//trying to get the first post id from the thread instead of the posts, smaller table, no order, faster response
				$getfirstpost = $this->dbobject->query_first("SELECT firstpostid FROM " . TABLE_PREFIX . "thread WHERE threadid = " . $this->post['threadid']);
				if(!empty($getfirstpost['firstpostid']))
				{
					$this->set('parentid', $getfirstpost['firstpostid']);
				}
				else
				{
					$getfirstpost = $this->dbobject->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid = " . $this->post['threadid'] . " ORDER BY dateline, postid LIMIT 1");
					$this->set('parentid', $getfirstpost['postid']);
				}
			}
		}

		$return_value = true;

		($hook = vBulletinHook::fetch_hook('postdata_presave')) ? eval($hook) : false;

		// we've errored, so try to roll the floodcheck back if it happened
		if ($return_value == false AND !empty($this->floodcheck))
		{
			//$this->floodcheck->rollback();
		}

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	 * Overridding parent function to add search index updates
	 *
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	* @param bool 	Whether to return the number of affected rows.
	* @param bool		Perform REPLACE INTO instead of INSERT
	8 @param bool		Perfrom INSERT IGNORE instead of INSERT
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		// Call and get the new id
		$result = parent::save($doquery, $delayed, $affected_rows, $replace, $ignore);

		if ($result AND ($this->post['postid'] OR $this->existing['postid']))
		{
			// Search index maintenance. Use the new cron'd processing;
			require_once DIR  . '/vb/search/indexcontroller/queue.php' ;
			$msgid = intval($this->existing['postid']) > 0 ? $this->existing['postid'] : $this->post['postid'];
			vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Post', 'index', $msgid);
		}

		return $result;
	}

	function post_save_each($doquery = true)
	{
		$postid = intval($this->fetch_field('postid'));

		if (!$this->condition AND $this->fetch_field('dateline') == TIMENOW)
		{
			$this->insert_dupehash($this->fetch_field('threadid'));
		}

		$this->post_save_each_post($doquery);

		if ($this->info['thread'] AND ($attach = intval($this->info['newattach']) OR !$this->condition))
		{
			$thread =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
			$thread->set_existing($this->info['thread']);

			if ($attach)
			{
				$thread->set('attach', "attach + $attach", false);
			}
		}

		if ($this->info['thread'] AND $this->info['thread']['firstpostid'] == $this->fetch_field('postid'))
		{
			if (!is_object($thread))
			{
				$thread =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$thread->set_existing($this->info['thread']);
			}
			$thread->set_info('pagetext', $this->fetch_field('pagetext'));

			$thread->rebuild_keywords();
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('dateline') == TIMENOW)
			{
				$this->insert_postlog_data();
			}

			if ($this->fetch_field('visible') == 1 AND $this->info['thread'])
			{
				if (in_coventry($this->fetch_field('userid'), true))
				{
					$thread->set_info(
						'coventry',
						array (
							'in_coventry'  => 1,
							'userid'       => $this->fetch_field('userid')
						)
					);
				}


				if ($this->fetch_field('dateline') == TIMENOW)
				{
					$thread->set('lastpost', TIMENOW);
					$thread->set('lastposter', $this->fetch_field('username'));
					$thread->set('lastposterid', $this->fetch_field('userid'));
					$thread->set('lastpostid', $postid);
				}

				// update last post info for this thread
				if ($this->info['thread']['replycount'] % 10 == 0)
				{
					$replies = $this->registry->db->query_first("
						SELECT COUNT(*)-1 AS replies
						FROM " . TABLE_PREFIX . "post AS post
						WHERE threadid = " . intval($this->info['thread']['threadid']) . " AND
							post.visible = 1
					");

					$thread->set('replycount', $replies['replies']);
				}
				else
				{
					$thread->set('replycount', 'replycount + 1', false);
				}


			}
			else if ($this->fetch_field('visible') == 0 AND $this->info['thread'])
			{
				$thread->set('hiddencount', 'hiddencount + 1', false);
			}

			/*if ($this->fetch_field('visible') == 1 AND !in_coventry($this->registry->userinfo['userid'], true))
			{
				// Send out subscription emails
				exec_send_notification($this->fetch_field('threadid'), $this->registry->userinfo['userid'], $this->fetch_field('postid'));
			}*/
		}

		if (is_object($thread))
		{
			$thread->save();
		}

		if ($this->post['visible'] === 0)
		{
			$postid = intval($this->fetch_field('postid'));

			/*insert query*/
			$this->dbobject->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "moderation (primaryid, type, dateline) VALUES ($postid, 'reply', " . TIMENOW . ")");
		}

		if ($this->info['forum']['podcast'] AND $this->info['thread']['firstpostid'] == $postid)
		{
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "podcastitem
					(postid, url, length, explicit, author, keywords, subtitle)
				VALUES
					(
						$postid,
						'" . $this->dbobject->escape_string($this->info['podcasturl']) . "',
						" . intval($this->info['podcastsize']) . ",
						" . intval($this->info['podcastexplicit']) . ",
						'" . $this->dbobject->escape_string($this->info['podcastauthor']) . "',
						'" . $this->dbobject->escape_string($this->info['podcastkeywords']) . "',
						'" . $this->dbobject->escape_string($this->info['podcastsubtitle']) . "'
					)
			");

			// reset rss cache for this forum
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "externalcache
				WHERE forumid = " . intval($this->info['forum']['forumid']) . "
			");
		}

		if (!$this->condition)
		{
			$this->email_moderators('newpostemail');
		}

		($hook = vBulletinHook::fetch_hook('postdata_postsave')) ? eval($hook) : false;
	}


	/**
	* Deletes a post
	*
	* @param	boolean	Whether to consider updating post counts, regardless of forum's settings
	* @param	integer Thread that this post belongs to
	* @param	boolean	Whether to physically remove the thread from the database
	* @param	array	Array of information for a soft delete
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($countposts = true, $threadid = 0, $physicaldel = true, $delinfo = NULL, $dolog = true)
	{
		if ($postid = $this->existing['postid'])
		{
			require_once(DIR . '/includes/functions_databuild.php');
			// note: the skip_moderator_log is the inverse of the $dolog argument

			// Search index maintenance
			require_once(DIR . '/vb/search/indexcontroller/queue.php');
			vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Post', 'delete', $postid);

			($hook = vBulletinHook::fetch_hook('postdata_delete')) ? eval($hook) : false;

			return delete_post($postid, $countposts, $threadid, $physicaldel, $delinfo, ($this->info['skip_moderator_log'] !== null ? !$this->info['skip_moderator_log'] : $dolog));
		}

		return false;
	}
}

/**
* Class to do data save/delete operations for THREADS. Primarily useful when
* updating a thread's settings and you don't want to bring the first post into
* the picture.
*
* @package	vBulletin
* @version	$Revision: 45135 $
* @date		$Date: 2011-06-27 11:49:33 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_Thread extends vB_DataManager_ThreadPost
{
	/**
	* Array of recognised and required fields for threads, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'threadid'      => array(TYPE_UINT, REQ_INCR),
		'title'         => array(TYPE_STR,  REQ_YES,   VF_METHOD),
		'firstpostid'   => array(TYPE_UINT, REQ_NO),
		'lastpost'      => array(TYPE_UINT, REQ_NO),
		'forumid'       => array(TYPE_UINT, REQ_YES),
		'pollid'        => array(TYPE_UINT, REQ_NO),
		'open'          => array(TYPE_UINT, REQ_AUTO,  VF_METHOD),
		'replycount'    => array(TYPE_UINT, REQ_NO),
		'hiddencount'   => array(TYPE_UINT, REQ_NO),
		'deletedcount'  => array(TYPE_UINT, REQ_NO),
		'postusername'  => array(TYPE_STR,  REQ_NO,    VF_METHOD, 'verify_username'),
		'postuserid'    => array(TYPE_UINT, REQ_NO,    VF_METHOD, 'verify_userid'),
		'lastposter'    => array(TYPE_STR,  REQ_NO),
		'lastposterid'  => array(TYPE_UINT, REQ_NO),
		'lastpostid'    => array(TYPE_UINT, REQ_NO),
		'dateline'      => array(TYPE_UINT, REQ_AUTO),
		'views'         => array(TYPE_UINT, REQ_NO),
		'iconid'        => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'notes'         => array(TYPE_STR,  REQ_NO),
		'visible'       => array(TYPE_UINT, REQ_NO),
		'sticky'        => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'votenum'       => array(TYPE_UINT, REQ_NO),
		'votetotal'     => array(TYPE_UINT, REQ_NO),
		'attach'        => array(TYPE_UINT, REQ_NO),
		'similar'       => array(TYPE_STR,  REQ_AUTO),
		'prefixid'      => array(TYPE_STR,  REQ_NO,    VF_METHOD),
		'taglist'       => array(TYPE_STR,  REQ_NO),
		'keywords'      => array(TYPE_STR,  REQ_NO)
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'thread';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('threadid = %1$d', 'threadid');

	/**
	* Array to store stuff to save to thread/post tables
	*
	* @var	array
	*/
	var $thread = array();

	/**
	* Array holding moderator log details to insert
	*
	* @var	array
	*/
	var $modlog = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Thread(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_ThreadPost($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('threaddata_start')) ? eval($hook) : false;
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		switch($fieldname)
		{
			case 'lastpost':
			case 'lastposter':
			case 'lastpostid':
			case 'lastposterid':
			{
				if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1)
				{
					$table = 'tachythreadpost';
				}
				else
				{
					$table = $this->table;
				}
			}
			break;

			case 'replycount':
			{
				if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1)
				{
					$table = 'tachythreadcounter';

				}
				else
				{
					$table = $this->table;

				}
			}
			break;

			default:
			{
				$table = $this->table;
			}
		}

		$this->setfields["$fieldname"] = true;
		$this->{$table}["$fieldname"] =& $value;
	}


	/**
	* Verifies the title. Does the same processing as the general title verifier,
	* but also requires there be a title.
	*
	* @param	string	Title text
	*
	* @return	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		if (!parent::verify_title($title))
		{
			return false;
		}

		if ($title == '')
		{
			$this->error('nosubject');
			return false;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'] AND $title != $this->existing['title'])
		{
			require_once(DIR . '/includes/functions_log_error.php');
			$logtype = fetch_modlogtypes('thread_title_x_changed');
			$this->modlog[] = array('userid' => intval($this->registry->userinfo['userid']), 'type' => intval($logtype), 'action' => $this->existing['title']);
		}

		return true;
	}

	function verify_open(&$open)
	{
		if (!in_array($open, array(0, 1, 10)))
		{
			$open = 1;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'])
		{
			require_once(DIR . '/includes/functions_log_error.php');
			if ($this->fetch_field('open'))
			{
				$logtype = fetch_modlogtypes('closed_thread');
			}
			else
			{
				$logtype = fetch_modlogtypes('opened_thread');
			}
			$this->modlog[] = array('userid' => intval($this->registry->userinfo['userid']), 'type' => intval($logtype));
		}

		return true;
	}

	function verify_sticky(&$sticky)
	{
		if ($sticky != 1)
		{
			$sticky = 0;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'])
		{
			require_once(DIR . '/includes/functions_log_error.php');
			if ($this->fetch_field('sticky'))
			{
				$logtype = fetch_modlogtypes('unstuck_thread');
			}
			else
			{
				$logtype = fetch_modlogtypes('stuck_thread');
			}
			$this->modlog[] = array('userid' => intval($this->registry->userinfo['userid']), 'type' => intval($logtype));
		}

		return true;
	}

	function verify_prefixid(&$prefixid)
	{
		if ($prefixid === '')
		{
			return true;
		}

		if (!$this->registry->db->query_first("
			SELECT prefixid
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixid = '" . $this->registry->db->escape_string($prefixid) . "'
		"))
		{
			$prefixid = '';
		}

		return true;
	}

	//We need to index the changes
	//
	function post_save_once($doquery = true)
	{
		static $saved = array();
		$threadid = $this->fetch_field('threadid');

		if (in_array($threadid, $saved))
		{
			return;
		}
		$saved[] = $threadid;

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Post', 'thread_data_change', $threadid);
	}

	function pre_save($doquery = true)
	{

		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if ($this->thread['username'])
		{
			$this->do_set('postusername', $this->thread['username']);
		}
		if ($this->thread['userid'])
		{
			$this->do_set('postuserid', $this->thread['userid']);
		}

		//the condition constraint means that we don't trigger this if the record
		//hasn't been loaded (probably means that its new). We actually explicitly
		//add this code in most cases when we save the thread, so it might be worth
		//figuring out a better way to trigger this so that we don't have code
		//copied hither and yon.  However we also don't want to do it twice so I'm
		//not going to take the risk of changing it now.
		if (!$this->condition AND $this->registry->options['similarthreadsearch'])
		{
			if (empty($this->info['preview']))
			{ // Dont run on a preview
				require_once(DIR . '/vb/search/core.php');
				$searchcontroller = vB_Search_Core::get_instance()->get_search_controller();
				$similarthreads = $searchcontroller->get_similar_threads($this->fetch_field('title'));
				$this->set('similar', implode(',', $similarthreads));
			}
		}

		if (!$this->condition)
		{
			if (!$this->fetch_field('dateline'))
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('open') === null)
			{
				$oldvalue = $this->info['skip_moderator_log'];
				$this->set_info('skip_moderator_log', true);
				$this->set('open', 1);
				$this->set_info('skip_moderator_log', $oldvalue);
			}
		}

		// updating prefix or forumid (with a prefix), we need to verify that a valid prefix was chosen
		if (!empty($this->thread['prefixid']) OR ($this->fetch_field('prefixid') AND !empty($this->thread['forumid'])))
		{
			if (!$this->registry->db->query_first("
				SELECT forumprefixset.forumid
				FROM " . TABLE_PREFIX . "prefix AS prefix
				INNER JOIN " . TABLE_PREFIX . "forumprefixset AS forumprefixset ON
					(prefix.prefixsetid = forumprefixset.prefixsetid AND forumprefixset.forumid = " . intval($this->fetch_field('forumid')) . ")
				WHERE prefix.prefixid = '" . $this->registry->db->escape_string($this->fetch_field('prefixid')) . "'
			"))
			{
				// selected prefix doesn't apply to this forum, blank it
				$this->set('prefixid', '');
			}
		}

		// no keywords set, and we have the pagetext
		if (empty($this->thread['keywords']) AND !empty($this->info['pagetext']))
		{
			$this->rebuild_keywords();
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('threaddata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;

		return $return_value;
	}

	function insert_moderator_log()
	{
		if ($this->modlog)
		{
			require_once(DIR . '/includes/functions_log_error.php');

			$threadid = intval(($tid = $this->fetch_field('threadid')) ? $tid : $this->info['thread']['threadid']);
			$forumid = intval(($fid = $this->fetch_field('forumid')) ? $fid : $this->info['forum']['forumid']);

			if (can_moderate($forumid))
			{
				foreach ($this->modlog AS $entry)
				{
					$entry['forumid'] = $forumid;
					$entry['threadid'] = $threadid;
					log_moderator_action($entry, $entry['type'], $entry['action']);
				}
			}

			$this->modlog = array();
		}
	}

	function post_save_each($doquery = true)
	{
		$this->insert_moderator_log();

		if (!$this->condition AND $this->fetch_field('visible') == 1 AND $this->info['forum'])
		{
			$forumdata =& datamanager_init('Forum', $this->registry, ERRTYPE_SILENT);
			$forumdata->set_existing($this->info['forum']);
			$forumdata->set_info('disable_cache_rebuild', true);

			if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1)
			{
				$forumdata->set_info('coventry', $this->info['coventry']);
			}

			$forumdata->set('threadcount', 'threadcount + 1', false);

			$forumdata->save();
		}

		if ($this->condition AND $fpid = $this->fetch_field('firstpostid'))
		{
			if ($this->existing['visible'] == 0 AND $this->fetch_field('visible') == 1)
			{
				$this->akismet_mark_as_ham($fpid);
			}

			if (!$this->info['skip_first_post_update'])
			{
				// if we're updating the title/iconid of an existing thread, update the first post
				if ((isset($this->thread['title']) OR isset($this->thread['iconid'])) AND $fp = fetch_postinfo($fpid))
				{
					$postdata =& datamanager_init('Post', $this->registry, ERRTYPE_SILENT, 'threadpost');
					$postdata->set_existing($fp);

					if (isset($this->thread['title']))
					{
						$postdata->set('title', $this->thread['title'], true, false); // don't clean it -- already been cleaned
					}

					if (isset($this->thread['iconid']))
					{
						$postdata->set('iconid', $this->thread['iconid'], true, false);
					}

					$postdata->save();
				}
			}
		}

		if ($this->condition AND $this->thread['title'] AND $this->existing['title'])
		{
			// we're updating the title of a thread, so update redirect titles as well if the redirect title is the same
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					title = '" . $this->dbobject->escape_string($this->thread['title']) . "'
				WHERE
					open = 10 AND
					pollid = " . intval($this->fetch_field('threadid')) . " AND
					title = '" . $this->dbobject->escape_string($this->existing['title']) . "'
			");
		}

		if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1 AND $this->setfields['replycount'])
		{
			$this->dbobject->query_read("SELECT * FROM " . TABLE_PREFIX . "tachythreadcounter WHERE userid = " . $this->info['coventry']['userid'] . " AND threadid = " . $this->fetch_field('threadid'));
			if ($this->dbobject->affected_rows() > 0)
			{
				$tachyupdate = 'replycount = '. $this->tachythreadcounter['replycount'];
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "tachythreadcounter SET ". $tachyupdate . " WHERE userid = " . $this->info['coventry']['userid'] . " AND threadid = " . $this->fetch_field('threadid'));
			}
			else
			{
				$this->tachythreadcounter['replycount'] = 1;

				$this->tachythreadcounter['userid'] = $this->info['coventry']['userid'];
				$this->tachythreadcounter['threadid'] = $this->fetch_field('threadid');

				$this->dbobject->query_write("
					REPLACE INTO " . TABLE_PREFIX . "tachythreadcounter
						(userid, threadid, replycount)
					VALUES
						(" . intval($this->tachythreadcounter['userid']) . ",
						" . intval($this->tachythreadcounter['threadid']) . ",
						" . intval($this->tachythreadcounter['replycount']) . ")
				");
			}
		}

		if (empty($this->info['rebuild']) AND $this->setfields['lastpost'])
		{
			if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1)
			{
				$this->tachythreadpost['userid'] = $this->info['coventry']['userid'];
				$this->tachythreadpost['threadid'] = $this->fetch_field('threadid');


				$this->dbobject->query_write("
					REPLACE INTO " . TABLE_PREFIX . "tachythreadpost
						(userid, threadid, lastpost, lastposter, lastposterid, lastpostid)
					VALUES
						(" . intval($this->tachythreadpost['userid']) . ",
						" . intval($this->tachythreadpost['threadid']) . ",
						" . intval($this->tachythreadpost['lastpost']) . ",
						'" . $this->dbobject->escape_string($this->tachythreadpost['lastposter']) . "',
						" . intval($this->tachythreadpost['lastposterid']) . ",
						" . intval($this->tachythreadpost['lastpostid']) . ")
				");
			}
			else
			{
				$this->dbobject->query_write("
						DELETE FROM " . TABLE_PREFIX . "tachythreadpost
						WHERE threadid = " . intval($this->fetch_field('threadid'))
				);
			}
		}

		($hook = vBulletinHook::fetch_hook('threaddata_postsave')) ? eval($hook) : false;
	}

	/**
	* Deletes a thread
	*
	* @param	boolean	Whether to consider updating post counts, regardless of forum's settings
	* @param	boolean	Whether to physically remove the thread from the database
	* @param	array	Array of information for a soft delete
	* @param	boolean	Whether to add an entry to the moderator log
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($countposts = true, $physicaldel = true, $delinfo = NULL, $dolog = true)
	{
		if ($threadid = $this->existing['threadid'])
		{
			require_once(DIR . '/includes/functions_databuild.php');
			require_once(DIR."/vb/search/core.php");

			($hook = vBulletinHook::fetch_hook('threaddata_delete')) ? eval($hook) : false;

			// Search index maintenance
			if ($physicaldel)
			{
				require_once(DIR . '/includes/class_taggablecontent.php');
				$content = vB_Taggable_Content_Item::create($this->registry, "vBForum_Thread", $threadid);
				$content->delete_tag_attachments();

				//don't queue this, it needs to run before the thread records are deleted.
				$indexcontroller = vB_Search_Core::get_instance()->get_index_controller('vBForum', 'Post');
				$indexcontroller->delete_thread($threadid);
			}

			// note: the skip_moderator_log is the inverse of the $dolog argument
			return delete_thread($threadid, $countposts, $physicaldel, $delinfo, ($this->info['skip_moderator_log'] !== null ? !$this->info['skip_moderator_log'] : $dolog), $this->existing);
		}
		return false;
	}
}

/**
* Class to do data save/delete operations for a THREAD and its FIRST POST.
* This is an important distinction!
*
* @package	vBulletin
* @version	$Revision: 45135 $
* @date		$Date: 2011-06-27 11:49:33 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_Thread_FirstPost extends vB_DataManager_Thread
{
	/**
	* Array of recognised and required fields for threads, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'firstpostid'   => array(TYPE_UINT, REQ_AUTO),
		'lastpost'      => array(TYPE_UINT, REQ_AUTO),
		'forumid'       => array(TYPE_UINT, REQ_YES),
		'pollid'        => array(TYPE_UINT, REQ_NO),
		'open'          => array(TYPE_UINT, REQ_AUTO,   VF_METHOD),
		'replycount'    => array(TYPE_UINT, REQ_AUTO),
		'hiddencount'   => array(TYPE_UINT, REQ_AUTO),
		'deletedcount'  => array(TYPE_UINT, REQ_AUTO),
		'lastposter'    => array(TYPE_STR,  REQ_AUTO),
		'lastposterid'  => array(TYPE_UINT, REQ_AUTO),
		'lastpostid'    => array(TYPE_UINT, REQ_AUTO),
		'views'         => array(TYPE_UINT, REQ_NO),
		'notes'         => array(TYPE_STR,  REQ_NO),
		'sticky'        => array(TYPE_UINT, REQ_NO,     VF_METHOD),
		'votenum'       => array(TYPE_UINT, REQ_NO),
		'votetotal'     => array(TYPE_UINT, REQ_NO),
		'similar'       => array(TYPE_STR,  REQ_AUTO),
		'prefixid'      => array(TYPE_STR,  REQ_NO,     VF_METHOD),
		'taglist'       => array(TYPE_STR,  REQ_NO),
		'keywords'      => array(TYPE_STR,  REQ_NO),

		// shared fields
		'threadid'      => array(TYPE_UINT, REQ_INCR),
		'title'         => array(TYPE_STR,  REQ_YES,    VF_METHOD),
		'username'      => array(TYPE_STR,  REQ_NO,     VF_METHOD), // maps to thread.postusername
		'userid'        => array(TYPE_UINT, REQ_NO,     VF_METHOD), // maps to thread.postuserid
		'dateline'      => array(TYPE_UINT, REQ_AUTO),
		'iconid'        => array(TYPE_UINT, REQ_NO,     VF_METHOD),
		'visible'       => array(TYPE_BOOL, REQ_NO), // note: post.visible will always be 1 with this object!
		'attach'        => array(TYPE_UINT, REQ_NO),

		// post only fields
		'pagetext'      => array(TYPE_STR,  REQ_YES,    VF_METHOD),
		'allowsmilie'   => array(TYPE_UINT, REQ_YES), // this is required as we must know whether smilies count as images
		'showsignature' => array(TYPE_BOOL, REQ_NO),
		'ipaddress'     => array(TYPE_STR,  REQ_AUTO),
		'htmlstate'     => array(TYPE_STR,  REQ_NO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'thread';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('threadid = %1$d', 'threadid');

	/**
	* Array to store stuff to save to thread table
	*
	* @var	array
	*/
	var $thread = array();

	/**
	* Array to store stuff to save to post table
	*
	* @var	array
	*/
	var $post = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Thread_FirstPost(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('threadfpdata_start')) ? eval($hook) : false;
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'threadid':
			case 'title':
			case 'dateline' :
			case 'iconid':
			case 'attach':
			{
				$tables = array('thread', 'post');
			}
			break;

			// post.visible will always be 1
			case 'visible':
			{
				$this->post['visible'] = 1;
				$this->thread['visible'] =& $value;
			}
			break;

			// exist in post table as is, but in the thread table as post<name>
			case 'username':
			case 'userid':
			{
				$this->post["$fieldname"] =& $value;
				$this->thread["post$fieldname"] =& $value;
				return;
			}
			break;

			case 'pagetext':
			case 'allowsmilie':
			case 'showsignature':
			case 'ipaddress':
			case 'htmlstate':
			{
				$tables = array('post');
			}
			break;

			default:
			{
				$tables = array('thread');
			}
		}

		($hook = vBulletinHook::fetch_hook('threadfpdata_doset')) ? eval($hook) : false;

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
		}
	}

	/**
	* Saves thread data to the database
	*
	* @return	mixed
	*/
	function save($doquery = true)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return 0;
		}

		if ($this->condition)
		{
			// update query
			$return = $this->db_update(TABLE_PREFIX, 'thread', $this->condition, $doquery);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'post', 'postid = ' . $this->fetch_field('firstpostid'), $doquery);
			}
		}
		else
		{
			// insert query
			$return = $this->thread['threadid'] = $this->db_insert(TABLE_PREFIX, 'thread', $doquery);

			if ($return)
			{
				$this->do_set('threadid', $return);

				$firstpostid = $this->thread['firstpostid'] = $this->db_insert(TABLE_PREFIX, 'post', $doquery);
				if ($doquery)
				{
					$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "thread SET firstpostid = $firstpostid, lastpostid = $firstpostid WHERE threadid = $return");
				}
			}
		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
		}

		return $return;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!parent::pre_save($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->pre_save_post($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->condition)
		{
			$this->set('lastpost', $this->fetch_field('dateline'));
			$this->set('lastposter', $this->fetch_field('username', 'post'));
			$this->set('lastposterid', $this->fetch_field('userid', 'post'));
			$this->set('replycount', 0);
			$this->set('hiddencount', 0);
			$this->set('deletedcount', 0);
		}
		else
		{
			if (!$this->fetch_field('firstpostid'))
			{
				//trying to get the first post id from the thread instead of the posts, smaller table, no order, faster response
				$getfirstpost = $this->dbobject->query_first("SELECT firstpostid FROM " . TABLE_PREFIX . "thread WHERE threadid = " . $this->fetch_field('threadid'));
				if(!empty($getfirstpost['firstpostid']))
				{
					$this->set('firstpostid', $getfirstpost['firstpostid']);
				}
				else
				{
					$getfirstpost = $this->dbobject->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid = " . $this->fetch_field('threadid') . " ORDER BY dateline, postid LIMIT 1");
					$this->set('firstpostid', $getfirstpost['postid']);
				}
			}
		}

		if (!$this->condition AND $this->fetch_field('open') === null)
		{
			$oldvalue = $this->info['skip_moderator_log'];
			$this->set_info('skip_moderator_log', true);
			$this->set('open', 1);
			$this->set_info('skip_moderator_log', $oldvalue);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('threadfpdata_presave')) ? eval($hook) : false;

		// we've errored, so try to roll the floodcheck back if it happened
		if ($return_value == false AND !empty($this->floodcheck))
		{
			//$this->floodcheck->rollback();
		}

		$this->rebuild_keywords();

		$this->presave_called = $return_value;
		return $return_value;
	}
	//We need to index the changes
	//
	function post_save_once($doquery = true)
	{
		static $saved = array();
		$threadid = $this->fetch_field('threadid');
		$postid = intval($this->fetch_field('firstpostid'));

		if (in_array($threadid, $saved))
		{
			return;
		}
		$saved[] = $threadid;

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Post', 'thread_data_change', $threadid);
		vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Post', 'index', $postid);
	}

	function post_save_each($doquery = true)
	{
		if (!$this->condition AND $this->fetch_field('dateline') == TIMENOW)
		{
			$this->insert_dupehash(0);
		}

		$this->post_save_each_post($doquery);

		if (!$this->condition AND $this->fetch_field('dateline') == TIMENOW)
		{
			$this->insert_postlog_data();
		}

		$threadid = intval($this->fetch_field('threadid'));

		if ($this->thread['visible'] === 0)
		{
			$postid = intval($this->fetch_field('firstpostid'));

			/*insert query*/
			$this->dbobject->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "moderation (primaryid, type, dateline) VALUES ($threadid, 'thread', " . TIMENOW . ")");
		}

		if ($this->info['forum']['podcast'] AND $postid = intval($this->fetch_field('firstpostid')))
		{
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "podcastitem
					(postid, url, length, explicit, author, keywords, subtitle)
				VALUES
					(
						$postid,
						'" . $this->dbobject->escape_string($this->info['podcasturl']) . "',
						" . intval($this->info['podcastsize']) . ",
						" . intval($this->info['podcastexplicit']) . ",
						'" . $this->dbobject->escape_string($this->info['podcastauthor']) . "',
						'" . $this->dbobject->escape_string($this->info['podcastkeywords']) . "',
						'" . $this->dbobject->escape_string($this->info['podcastsubtitle']) . "'
					)
			");

			// reset rss cache for this forum
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "externalcache
				WHERE forumid = " . intval($this->info['forum']['forumid']) . "
			");
		}

		if ($this->info['mark_thread_read'] AND $this->info['forum'] AND $this->registry->options['threadmarking'] AND $userid = $this->fetch_field('postuserid'))
		{
			$threadinfo = fetch_threadinfo($threadid);
			if ($threadinfo)
			{
				require_once(DIR . '/includes/functions_bigthree.php');
				mark_thread_read($threadinfo, $this->info['forum'], $userid, $this->fetch_field('dateline'));
			}
		}

		$this->insert_moderator_log();

		if (!$this->condition)
		{
			$this->email_moderators(array('newthreademail', 'newpostemail'));
		}

		if ($this->info['forum'] AND $this->fetch_field('firstpostid'))
		{
			// ### UPDATE SEARCH INDEX ###
			require_once(DIR . '/includes/functions_databuild.php');
			build_post_index($this->fetch_field('firstpostid'), $this->info['forum'], 1);
		}

		($hook = vBulletinHook::fetch_hook('threadfpdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Deletes a thread with the first post
	*
	* @param	boolean	Whether to consider updating post counts, regardless of forum's settings
	* @param	boolean	Whether to physically remove the thread from the database
	* @param	array	Array of information for a soft delete
	* @param	boolean	Whether to add an entry to the moderator log
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($countposts = true, $physicaldel = true, $delinfo = NULL, $dolog = true)
	{
 		require_once(DIR . '/vb/search/core.php');
      	// TODO: follow up on and check $this->existing['threadid']

		if ($threadid = $this->existing['threadid'])
		{
			// Search index maintenance
			if ($physicaldel)
			{
				require_once(DIR . '/vb/search/indexcontroller/queue.php');
				vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Post', 'delete', $threadid);

				require_once(DIR . '/includes/class_taggablecontent.php');
				$content = vB_Taggable_Content_Item::create($this->registry, "vBForum_Thread", $threadid);
				$content->delete_tag_attachments();
			}
		}

		($hook = vBulletinHook::fetch_hook('threadfpdata_delete')) ? eval($hook) : false;

		return parent::delete($countposts, $physicaldel, $delinfo, ($this->info['skip_moderator_log'] !== null ? !$this->info['skip_moderator_log'] : $dolog));
	}
}

/**
* Class to do data update operations for multiple POSTS simultaneously
*
* @package	vBulletin
* @version	$Revision: 45135 $
* @date		$Date: 2011-06-27 11:49:33 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_Post_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Post';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'postid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "post AS post";

		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

/**
* Class to do data update operations for multiple THREADS simultaneously
*
* @package	vBulletin
* @version	$Revision: 45135 $
* @date		$Date: 2011-06-27 11:49:33 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_Thread_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Thread';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'threadid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "thread AS thread";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 45135 $
|| ####################################################################
\*======================================================================*/
