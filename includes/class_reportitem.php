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

require_once(DIR . '/includes/functions_misc.php'); // for fetch_phrase


/**
 * Report Item Abstract Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @abstract
 *
 */
class vB_ReportItem
{
	/**
	 * @var	vB_Registry	The registry
	 */
	var $registry;

	/**
	 * @var	array	Information regarding the item being reported
	 */
	var $iteminfo;

	/**
	 * @var	array	Extra information regarding the item being reported
	 */
	var $extrainfo;

	/**
	 * @var	array	Information for the reporting form
	 */
	var $forminfo;

	/**
	 * @var array	List of Moderators affected by this report
	 */
	var $modlist = array();

	/**
	 * The reason for this report
	 */
	var $reason = '';

	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 *
	 * @abstract
	 */
	var $phrasekey = '';

	/**
	 * var array	Hidden Fields
	 */
	var $hiddenfields = array();

	/**
	 * Constructor
	 *
	 * @param	vB_Registry	The vBulletin registry object
	 *
	 */
	function vB_ReportItem(&$registry)
	{
		$this->registry =& $registry;
	}

	/**
	 * Sets extra information regarding the reported item
	 *
	 * @param	string	Extra Information key
	 * @param	string	Extra Information value
	 *
	 */
	function set_extrainfo($key, $value)
	{
		$this->extrainfo["$key"] = $value;
	}

	/**
	 * Do we need to do a floodcheck here?
	 *
	 * @return boolean
	 *
	 */
	function need_floodcheck()
	{
		return (
			!($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
			AND $this->registry->options['emailfloodtime']
			AND $this->registry->userinfo['userid']
		);
	}

	/**
	 * Performs the floodchecking before trying to commit
	 *
	 */
	function perform_floodcheck_precommit()
	{
		if (($timepassed = TIMENOW - $this->registry->userinfo['emailstamp']) < $this->registry->options['emailfloodtime'])
		{
			standard_error(fetch_error(
				'report_post_floodcheck',
				$this->registry->options['emailfloodtime'],
				($this->registry->options['emailfloodtime'] - $timepassed)
			));
		}
	}

	/**
	 * Performs atomic floodcheck
	 *
	 */
	function perform_floodcheck_commit()
	{
		$flood_limit = ($this->registry->options['enableemail'] AND $this->registry->options['rpemail'] ?
			$this->registry->options['emailfloodtime'] :
			$this->registry->options['floodchecktime']
		);

		require_once(DIR . '/includes/class_floodcheck.php');
		$floodcheck = new vB_FloodCheck($this->registry, 'user', 'emailstamp');
		$floodcheck->commit_key($this->registry->userinfo['userid'], TIMENOW, TIMENOW - $flood_limit);
		if ($floodcheck->is_flooding())
		{
			standard_error(fetch_error('report_post_floodcheck', $flood_limit, $floodcheck->flood_wait()));
		}
	}

	/**
	 * Sets a hidden form field
	 *
	 * @param	string	The field name
	 * @param	string	The value for the field
	 *
	 */
	function set_reporting_hidden_value($name, $value)
	{
		if (empty($this->hiddenfields["$name"]))
		{
			$this->forminfo['hiddenfields'] .= "\t\t<input type=\"hidden\" name=\"" . htmlspecialchars_uni($name) . "\" value=\"" . htmlspecialchars_uni($value) . "\" />\r\n";
		}
	}

	/**
	 * Does the report
	 *
	 * @param	string	The Reason for the report
	 * @param	array	Information regarding the item being reported
	 *
	 */
	function do_report($reason, &$iteminfo)
	{
		global $vbphrase;

		$this->iteminfo =& $iteminfo;
		$reportinfo = array(
			'rusername' => unhtmlspecialchars($this->registry->userinfo['username']),
			'ruserid'   => $this->registry->userinfo['userid'],
			'remail'    => $this->registry->userinfo['email'],
		);
		

		if ($this->registry->options['postmaxchars'] > 0)
		{
			$reportinfo['reason'] = substr($reason, 0, $this->registry->options['postmaxchars']);
		}
		else
		{
			$reportinfo['reason'] = $reason;
		}

		$reportthread = ($rpforumid = $this->registry->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
		$reportemail = ($this->registry->options['enableemail'] AND $this->registry->options['rpemail']);

		$mods = array();
		$reportinfo['modlist'] = '';
		$moderators = $this->fetch_affected_moderators();
		if ($moderators)
		{
			while ($moderator = $this->registry->db->fetch_array($moderators))
			{
				$mods["$moderator[userid]"] = $moderator;
				$reportinfo['modlist'] .= (!empty($reportinfo['modlist']) ? ', ' : '') . unhtmlspecialchars($moderator['username']);
			}
		}

		if (empty($reportinfo['modlist']))
		{
			$reportinfo['modlist'] = $vbphrase['n_a'];
		}

		//set the item specific report info
		$this->set_reportinfo($reportinfo);
		$this->set_reportinfo_commonlinks($reportinfo);
		
		($hook = vBulletinHook::fetch_hook('report_do_report')) ? eval($hook) : false;
		
		if ($reportthread)
		{
			// Determine if we need to create a thread or a post

			if (!$this->iteminfo['reportthreadid'] OR
				!($rpthreadinfo = fetch_threadinfo($this->iteminfo['reportthreadid'])) OR
				($rpthreadinfo AND (
					$rpthreadinfo['isdeleted'] OR
					!$rpthreadinfo['visible'] OR
					$rpthreadinfo['forumid'] != $rpforuminfo['forumid'])
				))
			{
				//creates magic variables $subject and $message
				eval(fetch_email_phrases('report' . $this->phrasekey . '_newthread', 0));

				if (!$this->registry->options['rpuserid'] OR !($userinfo = fetch_userinfo($this->registry->options['rpuserid'])))
				{
					$userinfo =& $this->registry->userinfo;
				}
				$threadman =& datamanager_init('Thread_FirstPost', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_info('forum', $rpforuminfo);
				$threadman->set_info('is_automated', true);
				$threadman->set_info('skip_moderator_email', true);
				$threadman->set_info('mark_thread_read', true);
				$threadman->set_info('parseurl', true);
				$threadman->set('allowsmilie', true);
				$threadman->set('userid', $userinfo['userid']);
				$threadman->setr_info('user', $userinfo);
				$threadman->set('title', $subject);
				$threadman->set('pagetext', $message);
				$threadman->set('forumid', $rpforuminfo['forumid']);
				$threadman->set('visible', 1);
				if ($userinfo['userid'] != $this->registry->userinfo['userid'])
				{
					// not posting as the current user, IP won't make sense
					$threadman->set('ipaddress', '');
				}

				if ($rpthreadid = $threadman->save())
				{
					if ($this->update_item_reportid($rpthreadid))
					{
						$threadman->set_info('skip_moderator_email', false);
						$threadman->email_moderators(array('newthreademail', 'newpostemail'));
						$this->iteminfo['reportthreadid'] = 0;
						$rpthreadinfo = array(
							'threadid'   => $rpthreadid,
							'forumid'    => $rpforuminfo['forumid'],
							'postuserid' => $userinfo['userid'],
						);

						// check the permission of the other user
						$userperms = fetch_permissions($rpthreadinfo['forumid'], $userinfo['userid'], $userinfo);
						if (($userperms & $this->registry->bf_ugp_forumpermissions['canview']) AND ($userperms & $this->registry->bf_ugp_forumpermissions['canviewthreads']) AND $userinfo['autosubscribe'] != -1)
						{
							$this->registry->db->query_write("
								INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread
									(userid, threadid, emailupdate, folderid, canview)
								VALUES
									(" . $userinfo['userid'] . ", $rpthreadinfo[threadid], $userinfo[autosubscribe], 0, 1)
							");
						}
					}
					else
					{
						// Delete the thread we just created
						if ($delthread = fetch_threadinfo($rpthreadid))
						{
							$threadman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
							$threadman->set_existing($delthread);
							$threadman->delete($rpforuminfo['countposts'], true, NULL, false);
							unset($threadman);
						}

						$this->refetch_iteminfo();
					}
				}
				else
				{
					$reportemail = true;
				}
			}

			if ($this->iteminfo['reportthreadid'] AND
				$rpthreadinfo = fetch_threadinfo($this->iteminfo['reportthreadid']) AND
				!$rpthreadinfo['isdeleted'] AND
				$rpthreadinfo['visible'] == 1 AND
				$rpthreadinfo['forumid'] == $rpforuminfo['forumid'])
			{
				eval(fetch_email_phrases('reportitem_newpost', 0));
				// Already reported, thread still exists/visible, and thread is in the right forum.
				// Technically, if the thread exists but is in the wrong forum, we should create the
				// thread, but that should only occur in a race condition.
				if (!$this->registry->options['rpuserid'] OR (!$userinfo AND !($userinfo = fetch_userinfo($this->registry->options['rpuserid']))))
				{
					$userinfo =& $this->registry->userinfo;
				}

				$postman =& datamanager_init('Post', $this->registry, ERRTYPE_STANDARD, 'threadpost');
				$postman->set_info('thread', $rpthreadinfo);
				$postman->set_info('forum', $rpforuminfo);
				$postman->set_info('is_automated', true);
				$postman->set_info('parseurl', true);
				$postman->set('threadid', $rpthreadinfo['threadid']);
				$postman->set('userid', $userinfo['userid']);
				$postman->set('allowsmilie', true);
				$postman->set('visible', true);
				$postman->set('title', $subject);
				$postman->set('pagetext', $message);
				if ($userinfo['userid'] != $this->registry->userinfo['userid'])
				{
					// not posting as the current user, IP won't make sense
					$postman->set('ipaddress', '');
				}
				$postman->save();
				unset($postman);
			}
		}

		if ($reportemail)
		{
			$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
			$postinfo['title'] = unhtmlspecialchars($postinfo['title']);

			if (empty($mods) OR $this->registry->options['rpemail'] == 2)
			{
				$moderators = $this->fetch_affected_super_moderators($mods);
				if ($moderators)
				{
					while ($moderator = $this->registry->db->fetch_array($moderators))
					{
						$mods["$moderator[userid]"] = $moderator;
					}
				}
			}

			($hook = vBulletinHook::fetch_hook('report_send_process')) ? eval($hook) : false;

			foreach ($mods AS $userid => $moderator)
			{
				if (!empty($moderator['email']))
				{
					$this->send_moderator_email($moderator, $rpthreadinfo, $reportinfo);
				}
			}

			($hook = vBulletinHook::fetch_hook('report_send_complete')) ? eval($hook) : false;
		}
	}

	/**
	 * Sends emails to a moderator regarding the report
	 *
	 * @param	array	Information regarding the moderator to send the email to
	 * @param	array	Informaiton regarding the item being reported
	 * @param 	array	Information regarding the report
	 *
	 */
	function send_moderator_email($moderator, $rpthreadinfo, $reportinfo)
	{
		global $vbphrase;

		$email_langid = ($moderator['languageid'] > 0 ? $moderator['languageid'] : $this->registry->options['languageid']);

		($hook = vBulletinHook::fetch_hook('report_send_email')) ? eval($hook) : false;

		$reportinfo['discuss'] = $rpthreadinfo ? construct_phrase($vbphrase['discussion_thread_created_x'], 
			fetch_seo_url('thread|nosession|js|bburl', $rpthreadinfo)) : '';

		eval(fetch_email_phrases('report' . $this->phrasekey, $email_langid));
		vbmail($moderator['email'], $subject, $message, true);
	}

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return null;
	}

	/**
	 * Fetches the super moderators affected by this report
	 *
	 * @return null|array	The super moderators affected.
	 *
	 */
	function fetch_affected_super_moderators($mods)
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.username, user.userid
			FROM " . TABLE_PREFIX . "usergroup AS usergroup
			INNER JOIN " . TABLE_PREFIX . "user AS user ON
				(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
			WHERE usergroup.adminpermissions > 0
				AND (usergroup.adminpermissions & " . $this->registry->bf_ugp_adminpermissions['ismoderator'] . ")
				" . (!empty($mods) ? "AND userid NOT IN (" . implode(',', array_keys($mods)) . ")" : "") . "
		");
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 * @abstract
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
	}


	/*
	 *	Set the common links that every class is going to need.
	 *	Seperated out from set_report_info because most classes won't need to override it.
	 */
	protected function set_reportinfo_commonlinks(&$reportinfo)
	{
		$reportinfo['reporterlink'] = fetch_seo_url('member|js|nosession|bburl', $reportinfo, null, 'ruserid', 'rusername');
		
		//this isn't common report information, but every type sets a purserid and pusername to the report info
		if (isset($reportinfo['puserid']))
		{
			$reportinfo['posterlink'] = fetch_seo_url('member|js|nosession|bburl', 
				$reportinfo, null, 'puserid', 'pusername');
		}
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 * @abstract
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 * @abstract
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		return false;
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 * @abstract
	 *
	 */
	function refetch_iteminfo()
	{
	}
}

/**
 * Report Post Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_Post extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'post';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.forumid IN (" . $this->extrainfo['forum']['parentlist'] . ")
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'report',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_bad_post'],
			'reporttype'   => $vbphrase['forum'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $this->extrainfo['forum']['title_clean'],
			'itemlink'     => fetch_seo_url('forum', $this->extrainfo['forum']),
		);

		$this->set_reporting_hidden_value('postid', $iteminfo['postid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'forumtitle'  => unhtmlspecialchars($this->extrainfo['forum']['title_clean']),
			'threadtitle' => unhtmlspecialchars($this->extrainfo['thread']['title']),
			'posttitle'   => unhtmlspecialchars($this->iteminfo['title']),
			'pusername'   => unhtmlspecialchars($this->iteminfo['username']),
			'puserid'     => $this->iteminfo['userid'],
			'postid'      => $this->iteminfo['postid'],
			'threadid'    => $this->extrainfo['thread']['threadid'],
			'pagetext'    => $this->iteminfo['pagetext'],
		));

		$reportinfo['postlink'] = fetch_seo_url('thread|nosession|bburl|js', $reportinfo, 
			array('p' => $reportinfo['postid'])) . "#post$reportinfo[postid]";
		$reportinfo['threadlink'] = fetch_seo_url('thread|nosession|bburl|js', $reportinfo);

		$reportinfo['prefix_plain'] = $this->extrainfo['thread']['prefixid'] ? 
			fetch_phrase("prefix_" . $this->extrainfo['thread']['prefixid'] . "_title_plain", 
				'global', '', false, true, 0, false) . ' ' : '';
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$postman =& datamanager_init('Post', $this->registry, ERRTYPE_SILENT, 'threadpost');
		$postman->set_info('is_automated', true);
		$postman->set_info('parseurl', true);
		$postman->set('reportthreadid', $newthreadid);

		// if $this->iteminfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);
		$postman->condition = "postid = " . $this->iteminfo['postid'] . " AND reportthreadid = $checkrpid";

		// affected_rows = 0, meaning another user reported this before us (race condition)
		return $postman->save(true, false, true);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid, forumid
			FROM " . TABLE_PREFIX . "post
			INNER JOIN " . TABLE_PREFIX . "thread USING (threadid)
			WHERE postid = " . $this->iteminfo['postid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Article Comment Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_ArticleComment extends vB_ReportItem_Post
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'articlecomment';

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$content = new vBCms_Item_Content_Article($this->extrainfo['node']);

		$this->forminfo = array(
			'file'         => 'report',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_bad_articlecomment'],
			'reporttype'   => $vbphrase['article'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $content->getTitle(),
			'itemlink'     => vBCms_Route_Content::getURL(array('node' => $this->extrainfo['node'] . '-' . $content->getUrl())),
		);

		$this->set_reporting_hidden_value('postid', $iteminfo['postid']);
		$this->set_reporting_hidden_value('return_node', $this->extrainfo['node']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$content = new vBCms_Item_Content_Article($this->extrainfo['node']);

		parent::set_reportinfo($reportinfo);
		$reportinfo = array_merge($reportinfo, array(
			'entrytitle' => unhtmlspecialchars($content->getTitle()),
			'node'       => $this->extrainfo['node'],
			'itemlink'   => vBCms_Route_Content::getURL(array('node' => $this->extrainfo['node'] . '-' . $content->getUrl())),
			'postid'     => $this->iteminfo['postid'],
		));
	}
}

/**
 * Report Visitor Message Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_VisitorMessage extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'visitormessage';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions2 & " . ($this->registry->bf_misc_moderatorpermissions2['caneditvisitormessages'] | $this->registry->bf_misc_moderatorpermissions2['candeletevisitormessages'] | $this->registry->bf_misc_moderatorpermissions2['canremovevisitormessages'] | $this->registry->bf_misc_moderatorpermissions2['canmoderatevisitormessages']) . "
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'visitormessage',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_bad_visitor_message'],
			'reporttype'   => $vbphrase['visitor_message'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $this->extrainfo['user']['username'],
			'itemlink'     => fetch_seo_url('member', $this->extrainfo['user']),
		);

		$this->set_reporting_hidden_value('vmid', $iteminfo['vmid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$profile_user = array(
			'username' => unhtmlspecialchars($this->extrainfo['user']['username']),
			'userid' => $this->extrainfo['user']['userid']
		);

		$reportinfo = array_merge($reportinfo, $profile_user, array(
			'messagetitle'  => unhtmlspecialchars($this->iteminfo['title']),
			'pusername'     => unhtmlspecialchars($this->iteminfo['postusername']),
			'puserid'       => $this->iteminfo['postuserid'],
			'vmid'          => $this->iteminfo['vmid'],
			'pagetext'      => $this->iteminfo['pagetext'],
			'memberlink_pi' => fetch_seo_url('member|js|nosession|bburl', $profile_user,
				array(
					'tab'  => 'visitor_messaging',
					'vmid' => $this->iteminfo['vmid'],
				)
			),
			'memberlink'    => fetch_seo_url('member|js|nosesson|bburl',$profile_user),
		));
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$dataman =& datamanager_init('VisitorMessage', $this->registry, ERRTYPE_SILENT);
		$dataman->set_info('is_automated', true);
		$dataman->set_info('parseurl', true);
		$dataman->set('reportthreadid', $newthreadid);

		// if $this->iteminfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);
		$dataman->condition = "vmid = " . $this->iteminfo['vmid'] . " AND reportthreadid = $checkrpid";

		// affected_rows = 0, meaning another user reported this before us (race condition)
		return $dataman->save(true, false, true);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "visitormessage
			WHERE vmid = " . $this->iteminfo['vmid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Group Message Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_GroupMessage extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'groupmessage';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions2 & " . ($this->registry->bf_misc_moderatorpermissions2['caneditgroupmessages'] | $this->registry->bf_misc_moderatorpermissions2['candeletegroupmessages'] | $this->registry->bf_misc_moderatorpermissions2['canremovegroupmessages'] | $this->registry->bf_misc_moderatorpermissions2['canmoderategroupmessages']) . "
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'group',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_group_message'],
			'reporttype'   => $vbphrase['social_group'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $this->extrainfo['group']['name'],
			'itemlink'     => fetch_seo_url('groupmessage', $iteminfo),
		);

		//I'd have to rewrite the way the form code handles the post action to make this work 
		//with the seo classes.  Better to just handle the option directly.
		if ($this->registry->options['vbforum_url'])
		{
			$this->forminfo['file'] = $this->registry->options['vbforum_url'] . '/' . 	$this->forminfo['file'];
		}

		$this->set_reporting_hidden_value('gmid', $iteminfo['gmid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'messagetitle'    => unhtmlspecialchars($this->iteminfo['title']),
			'pusername'       => unhtmlspecialchars($this->iteminfo['postusername']),
			'gmid'            => $this->iteminfo['gmid'],
			'groupname'       => unhtmlspecialchars($this->extrainfo['group']['name']),
			'groupid'         => $this->extrainfo['group']['groupid'],
			'discussiontitle' => unhtmlspecialchars($this->extrainfo['discussion']['title']),
			'discussionid'    => $this->extrainfo['discussion']['discussionid'],
			'puserid'         => $this->iteminfo['postuserid'],
			'pagetext'        => $this->iteminfo['pagetext']
		));

		$reportinfo['groupurl'] = fetch_seo_url('group|js|bburl|nosession', $this->extrainfo['group']);
		$reportinfo['discussionurl'] = fetch_seo_url('groupdiscussion|js|bburl|nosession', $this->extrainfo['discussion']);
		$reportinfo['messageurl'] = fetch_seo_url('groupmessage|js|bburl|nosession', $this->iteminfo) . 
			"#gmessage$reportinfo[gmid]";
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$dataman =& datamanager_init('GroupMessage', $this->registry, ERRTYPE_SILENT);
		$dataman->set_info('is_automated', true);
		$dataman->set_info('parseurl', true);
		$dataman->set('reportthreadid', $newthreadid);

		// if $this->iteminfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);
		$dataman->condition = "gmid = " . $this->iteminfo['gmid'] . " AND reportthreadid = $checkrpid";

		// affected_rows = 0, meaning another user reported this before us (race condition)
		return $dataman->save(true, false, true);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "groupmessage
			WHERE gmid = " . $this->iteminfo['gmid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Album Picture Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_AlbumPicture extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'albumpicture';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions2 & " . ($this->registry->bf_misc_moderatorpermissions2['caneditalbumpicture'] | $this->registry->bf_misc_moderatorpermissions2['candeletealbumpicture']) . "
				" . (($this->extrainfo['picture']['state'] == 'moderation') ? "AND moderator.permissions2 & " . $this->registry->bf_misc_moderatorpermissions2['canmoderatepictures'] : "") . "
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Fetches the super moderators affected by this report
	 *
	 * @return null|array	The super moderators affected.
	 *
	 */
	function fetch_affected_super_moderators($mods)
	{
		// Only return super mods / admins that have the proper permissions to do something with the picture.
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.username, user.userid, moderator.moderatorid
			FROM " . TABLE_PREFIX . "usergroup AS usergroup
			INNER JOIN " . TABLE_PREFIX . "user AS user ON
				(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON (moderator.userid = user.userid AND moderator.forumid = -1)
			WHERE usergroup.adminpermissions <> 0
				AND	(moderator.moderatorid IS NULL
						OR
					(
						moderator.permissions2 & " . ($this->registry->bf_misc_moderatorpermissions2['caneditalbumpicture'] | $this->registry->bf_misc_moderatorpermissions2['candeletealbumpicture']) . "
						" . (($this->extrainfo['picture']['state'] == 'moderation') ? "AND moderator.permissions2 & " . $this->registry->bf_misc_moderatorpermissions2['canmoderatepictures'] : "") . "
					)
				)
				" . (!empty($mods) ? "AND user.userid NOT IN (" . implode(',', array_keys($mods)) . ")" : "") . "
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'album',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_picture'],
			'reporttype'   => $vbphrase['album'],
			'description'  => $vbphrase['only_report_inappropriate_pictures'],
			'itemname'     => $this->extrainfo['album']['title'],
			'itemlink'     => "album.php?" . $this->registry->session->vars['sessionurl'] . "albumid=" . $this->extrainfo['album']['albumid'],
		);

		$this->set_reporting_hidden_value('albumid', $this->extrainfo['album']['albumid']);
		$this->set_reporting_hidden_value('attachmentid', $iteminfo['attachmentid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'pusername'    => unhtmlspecialchars($this->extrainfo['user']['username']),
			'puserid'      => $this->extrainfo['user']['userid'],
			'albumtitle'   => unhtmlspecialchars($this->extrainfo['album']['title']),
			'albumid'      => $this->extrainfo['album']['albumid'],
			'attachmentid' => $this->iteminfo['attachmentid'],
			'username'     => unhtmlspecialchars($this->extrainfo['user']['username']),
			'userid'       => $this->extrainfo['user']['userid'],
		));
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET
				reportthreadid = $newthreadid
			WHERE
				attachmentid = " . $this->iteminfo['attachmentid'] . " AND reportthreadid = $checkrpid
		");

		return ($this->registry->db->affected_rows() ? true : false);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "attachment
			WHERE
				attachmentid = " . $this->iteminfo['attachmentid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Group Picture Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_GroupPicture extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'grouppicture';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions2 & " . ($this->registry->bf_misc_moderatorpermissions2['caneditalbumpicture'] | $this->registry->bf_misc_moderatorpermissions2['candeletealbumpicture']) . "
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'group',
			'action'       => 'sendpictureemail',
			'reportphrase' => $vbphrase['report_picture'],
			'reporttype'   => $vbphrase['social_group'],
			'description'  => $vbphrase['only_report_inappropriate_pictures'],
			'itemname'     => $this->extrainfo['group']['name'],
			'itemlink'     => fetch_seo_url('group', $this->extrainfo['group']),
		);

		//I'd have to rewrite the way the form code handles the post action to make this work 
		//with the seo classes.  Better to just handle the option directly.
		if ($this->registry->options['vbforum_url'])
		{
			$this->forminfo['file'] = $this->registry->options['vbforum_url'] . '/' . 	$this->forminfo['file'];
		}

		$this->set_reporting_hidden_value('groupid', $this->extrainfo['group']['groupid']);
		$this->set_reporting_hidden_value('attachmentid', $iteminfo['attachmentid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'pusername'    => unhtmlspecialchars($this->extrainfo['user']['username']),
			'puserid'      => $this->extrainfo['user']['userid'],
			'groupname'    => unhtmlspecialchars($this->extrainfo['group']['name']),
			'groupid'      => $this->extrainfo['group']['groupid'],
			'attachmentid' => $this->iteminfo['attachmentid'],
		));

		$reportinfo['groupurl'] = fetch_seo_url('group|js|bburl|nosession', $this->extrainfo['group']);
		$reportinfo['pictureurl'] = fetch_seo_url('group|js|bburl|nosession', $this->extrainfo['group'],
			array('do' => 'picture', 'attachmentid' => $this->iteminfo['attachmentid']));
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET
				reportthreadid = $newthreadid
			WHERE attachmentid = " . $this->iteminfo['attachmentid'] . " AND reportthreadid = $checkrpid
		");

		return ($this->registry->db->affected_rows() ? true : false);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "attachment
			WHERE attachmentid = " . $this->iteminfo['attachmentid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Picture Comment Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_PictureComment extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'picturecomment';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions2 & " . ($this->registry->bf_misc_moderatorpermissions2['caneditpicturecomments'] | $this->registry->bf_misc_moderatorpermissions2['candeletepicturecomments'] | $this->registry->bf_misc_moderatorpermissions2['canremovepicturecomments'] | $this->registry->bf_misc_moderatorpermissions2['canmoderatepicturecomments']) . "
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'picturecomment',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_picture_comment'],
			'reporttype'   => ($this->extrainfo['picture']['albumid'] ? $vbphrase['album'] : $vbphrase['social_group']),
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => ($this->extrainfo['picture']['albumid'] ? $this->extrainfo['album']['title_html'] : $this->extrainfo['group']['name']),
			'itemlink'     => ($this->extrainfo['picture']['albumid']
				? "album.php?" . $this->registry->session->vars['sessionurl'] . "albumid=" . $this->extrainfo['picture']['albumid']
				: fetch_seo_url('group', $this->extrainfo['group'])
			),
		);

		$this->set_reporting_hidden_value('groupid', $this->extrainfo['group']['groupid']);
		$this->set_reporting_hidden_value('albumid', $this->extrainfo['album']['albumid']);
		$this->set_reporting_hidden_value('attachmentid', $this->extrainfo['picture']['attachmentid']);
		$this->set_reporting_hidden_value('commentid', $iteminfo['commentid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'pusername'  => unhtmlspecialchars($this->iteminfo['postusername']),
			'puserid'    => $this->iteminfo['postuserid'],
			'pagetext'   => $this->iteminfo['pagetext'],
		));

		if ($this->extrainfo['picture']['albumid'])
		{
			$reportinfo['commenturl'] = "album.php?albumid=" . 
				$this->extrainfo['picture']['albumid'] . "&attachmentid={$this->iteminfo['attachmentid']}&commentid=" . 
				$this->iteminfo['commentid'] . "#picturecomment{$this->iteminfo['commentid']}";
			$reportinfo['commenturl'] = create_full_url($reportinfo['commenturl'], true);
		}
		else
		{
			$reportinfo['commenturl'] = fetch_seo_url('group|js|bburl|nosession', $this->extrainfo['group'], 
				array('do' => 'picture', 'attachmentid' => $this->iteminfo['attachmentid'], 
					'commentid' => $this->iteminfo['commentid'])) . "#picturecomment{$this->iteminfo['commentid']}";
		}
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "picturecomment SET
				reportthreadid = $newthreadid
			WHERE commentid = " . $this->iteminfo['commentid'] . " AND reportthreadid = $checkrpid
		");

		return ($this->registry->db->affected_rows() ? true : false);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "picturecomment
			WHERE commentid = " . $this->iteminfo['commentid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/**
 * Report Private Message Class
 *
 * @package 	vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 * @final
 *
 */
class vB_ReportItem_PrivateMessage extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'privatemessage';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		return $this->registry->db->query_read_slave("
			SELECT DISTINCT user.email, user.languageid, user.userid, user.username
			FROM " . TABLE_PREFIX . "moderator AS moderator
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = moderator.userid)
			WHERE moderator.permissions & " . ($this->registry->bf_misc_moderatorpermissions['canbanusers']) . "
				AND moderator.forumid <> -1
		");
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'private',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_bad_private_message'],
			'reporttype'   => $vbphrase['private_message'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $iteminfo['title'],
			'itemlink'     => "",
		);

		$this->set_reporting_hidden_value('pmid', $iteminfo['pmid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'pmtitle'     => unhtmlspecialchars($this->iteminfo['title']),
			'pusername'   => unhtmlspecialchars($this->iteminfo['fromusername']),
			'puserid'     => $this->iteminfo['fromuserid'],
			'pmid'        => $this->iteminfo['pmid'],
			'message'    => $this->iteminfo['message'],
		));

		$reportinfo['posterlink'] = fetch_seo_url('member|js|nosession|bburl', 
			$reportinfo, null, 'puserid', 'pusername');
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{

		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pmtext SET
				reportthreadid = $newthreadid
			WHERE pmtextid = " . $this->iteminfo['pmtextid'] . " AND reportthreadid = $checkrpid
		");

		return ($this->registry->db->affected_rows() ? true : false);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "pmtext
			WHERE pmtextid = " . $this->iteminfo['pmtextid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44568 $
|| ####################################################################
\*======================================================================*/

?>
