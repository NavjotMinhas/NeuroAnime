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

/**
* Class for verifying a vBulletin forum post attachment
*
* @package 		vBulletin
* @version		$Revision: 29997 $
* @date 		$Date: 2009-03-23 10:11:43 -0700 (Mon, 23 Mar 2009) $
*
*/
class vB_Attachment_Display_Single_vBForum_Post extends vB_Attachment_Display_Single
{
	/**
	* Verify permissions of a single attachment
	*
	* @return	bool
	*/
	public function verify_attachment()
	{
		$selectsql = array(
			"thread.forumid, thread.threadid, thread.postuserid",
			"post.visible AS post_visible, thread.visible AS thread_visible",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)",
		);

		if (!$this->verify_attachment_specific('vBForum_Post', $selectsql, $joinsql))
		{
			return false;
		}

		$postinfo = array(
			'forumid'        => $this->attachmentinfo['forumid'],
			'threadid'       =>	$this->attachmentinfo['threadid'],
			'postuserid'     =>	$this->attachmentinfo['postuserid'],
			'post_visible'   =>	$this->attachmentinfo['post_visible'],
			'thread_visible' =>	$this->attachmentinfo['thread_visible'],
		);
		unset(
			$this->attachmentinfo['forumid'],
			$this->attachmentinfo['threadid'],
			$this->attachmentinfo['postuserid'],
			$this->attachmentinfo['post_visible'],
			$this->attachmentinfo['thread_visible']
		);

		$forumperms = fetch_permissions($postinfo['forumid']);

		$this->browsinginfo = array(
			'threadinfo' => array(
				'threadid' => $postinfo['threadid'],
			),
			'foruminfo' => array(
				'forumid' => $postinfo['forumid'],
			),
		);

		if ($this->attachmentinfo['contentid'] == 0)
		{
			if ($this->registry->userinfo['userid'] != $this->attachmentinfo['userid'] AND !can_moderate($postinfo['forumid'], 'caneditposts'))
			{
				return false;
			}
		}
		else
		{
			# Block attachments belonging to soft deleted posts and threads
			if (!can_moderate($postinfo['forumid']) AND ($postinfo['post_visible'] == 2 OR $postinfo['thread_visible'] == 2))
			{
				return false;
			}
			# Block attachments belonging to moderated posts and threads
			if (!can_moderate($postinfo['forumid'], 'canmoderateposts') AND (!$postinfo['post_visible'] OR !$postinfo['thread_visible']))
			{
				return false;
			}

			// check if there is a forum password and if so, ensure the user has it set
			if (!verify_forum_password($postinfo['forumid'], $this->registry->forumcache["$postinfo[forumid]"]['password'], false))
			{
				return false;
			}
			if ($this->attachmentinfo['state'] == 'moderation' AND !can_moderate($postinfo['forumid'], 'canmoderateattachments') AND $this->attachmentinfo['userid'] != $this->registry->userinfo['userid'])
			{
				return false;
			}

			$viewpermission = (($forumperms & $this->registry->bf_ugp_forumpermissions['cangetattachment']));
			$viewthumbpermission = (($forumperms & $this->registry->bf_ugp_forumpermissions['cangetattachment']) OR ($forumperms & $this->registry->bf_ugp_forumpermissions['canseethumbnails']));
			if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canview']) OR !($forumperms & $this->registry->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers']) AND ($postinfo['postuserid'] != $this->registry->userinfo['userid'] OR $this->registry->userinfo['userid'] == 0)))
			{
				return false;
			}
			else if (($this->thumbnail AND !$viewthumbpermission) OR (!$this->thumbnail AND !$viewpermission))
			{
				// Show no permissions instead of invalid ID
				return -1;
			}
		}
		return true;
	}
}

/**
* Class for display of multiple vBulletin forum post attachments
*
* @package 		vBulletin
* @version		$Revision: 29997 $
* @date 		$Date: 2009-03-23 10:11:43 -0700 (Mon, 23 Mar 2009) $
*
*/
class vB_Attachment_Display_Multiple_vBForum_Post extends vB_Attachment_Display_Multiple
{
	/**
	* Constructor
	*
	* @param	vB_Registry
	* @param	integer			Unique id of this contenttype (forum post, blog entry, etc)
	*
	* @return	void
	*/
	public function __construct(&$registry, $contenttypeid)
	{
		parent::__construct($registry);
		$this->contenttypeid = $contenttypeid;
		require_once(DIR . '/includes/functions_forumlist.php');
		cache_moderators($registry->userinfo['userid']);
	}

	/**
	* Return content specific information that relates to the ownership of attachments
	*
	* @param	array		List of attachmentids to query
	*
	* @return	void
	*/
	public function fetch_sql($attachmentids)
	{
		$selectsql = array(
			"post.postid, post.title AS p_title, post.dateline AS p_dateline",
			"thread.forumid, thread.open, thread.threadid, thread.title AS t_title",
			"user.username",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		return $this->fetch_sql_specific($attachmentids, $selectsql, $joinsql);
	}

	/**
	* Fetches the SQL to be queried as part of a UNION ALL od an attachment query, verifying read permissions
	*
	* @param	string	SQL WHERE criteria
	* @param	string	Contents of the SELECT portion of the main query
	*
	* @return	string
	*/
	protected function fetch_sql_ids($criteria, $selectfields)
	{
		$cangetforumids = $canget = $canviewothers = $canmod = $canmodhidden = $canmodattach = array(0);

		foreach ($this->registry->userinfo['forumpermissions'] AS $forumid => $perm)
		{
			if (
				$password = $this->registry->forumcache["$forumid"]['password']
					AND
				(!verify_forum_password($forumid, $password, false))
			)
			{
				continue;
			}

			if (
				($perm & $this->registry->bf_ugp_forumpermissions['canview'])
					AND
				($perm & $this->registry->bf_ugp_forumpermissions['canviewthreads'])
					AND
				($perm & $this->registry->bf_ugp_forumpermissions['cangetattachment'])
			)
			{
				$cangetforumids["$forumid"] = $forumid;
			}
			else
			{
				continue;
			}

			if ($perm & $this->registry->bf_ugp_forumpermissions['canviewothers'] AND $this->registry->userinfo['userid'])
			{
				$canviewothers["$forumid"] = $forumid;
			}

			if (can_moderate($forumid))
			{
				$canmod["$forumid"] = $forumid;
			}

			if (can_moderate($forumid, 'canmoderateposts'))
			{
				$canmodhidden["$forumid"] = $forumid;
			}

			if (can_moderate($forumid, 'canmoderateattachments'))
			{
				$canmodattach["$forumid"] = $forumid;
			}
		}

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		// This SQL can be condensed down in some fashion
		// This is not optimized beyond the userid level
		$subwheresql = array(
			"thread.forumid IN (" . implode(", ", $cangetforumids) . ")",
			"(
				thread.forumid IN (" . implode(", ", $canviewothers) . ")
					OR
				thread.postuserid = {$this->registry->userinfo['userid']}
			)",
			"(
				a.state <> 'moderation'
					OR
				a.userid = {$this->registry->userinfo['userid']}
					OR
				thread.forumid IN (" . implode(", ", $canmodattach) . ")
			)",
			"(
				(
					post.visible = 1
						AND
					thread.visible = 1
				)
					OR
				(
					thread.forumid IN (" . implode(", ", $canmodhidden) . ")
				)
					OR
				(
					thread.forumid IN (" . implode(", ", $canmod) . ")
						AND
					post.visible = 2
						AND
					thread.visible = 2
				)
			)",
		);

		return $this->fetch_sql_ids_specific($this->contenttypeid, $criteria, $selectfields, $subwheresql, $joinsql);
	}

	/**
	* Formats $post content for display
	*
	* @param	array		Post information
	*
	* @return	array
	*/
	protected function process_attachment_template($post, $showthumbs = false)
	{
		global $show, $vbphrase;

		if (!$post['p_title'])
		{
			$post['p_title'] = '&laquo;' . $vbphrase['n_a'] . '&raquo;';
		}

		$show['thumbnail'] = ($post['hasthumbnail'] == 1 AND $this->registry->options['attachthumbs'] AND $showthumbs);
		$show['inprogress'] = $post['inprogress'];

		$show['candelete'] = false;
		$show['canmoderate'] = can_moderate($post['forumid'], 'canmoderateattachments');
		if ($post['inprogress'])
		{
			$show['candelete'] = true;
		}
		else if ($post['open'] OR $this->registry->options['allowclosedattachdel'] OR can_moderate($post['forumid'], 'canopenclose'))
		{
			if (can_moderate($post['forumid'], 'caneditposts'))
			{
				$show['candelete'] = true;
			}
			else
			{
				$forumperms = fetch_permissions($post['forumid']);
				if (($forumperms & $this->registry->bf_ugp_forumpermissions['caneditpost'] AND $this->registry->userinfo['userid'] == $post['userid']))
				{
					if ($this->registry->options['allowattachdel'] OR !$this->registry->options['edittimelimit'] OR $post['p_dateline'] >= TIMENOW - $this->registry->options['edittimelimit'] * 60)
					{
						$show['candelete'] = true;
					}
				}
			}
		}

		$threadinfo = array(
			'threadid' => $post['threadid'],
			'title'    => $post['t_title'],
		);
		$pageinfo = array(
			'p'        => $post['contentid'],
		);

		return array(
			'template'   => 'post',
			'post'       => $post,
			'threadinfo' => $threadinfo,
		  'pageinfo'   => $pageinfo,
		);
	}

	/**
	* Return forum post specific url to the owner an attachment
	*
	* @param	array		Content information
	*
	* @return	string
	*/
	protected function fetch_content_url_instance($contentinfo)
	{
		return fetch_seo_url('thread', $contentinfo, array('p' => $contentinfo['contentid']), 'threadid', 'threadtitle') . "#post$contentinfo[contentid]";
	}
}

/**
* Class for storing a vBulletin forum post attachment
*
* @package 		vBulletin
* @version		$Revision: 29997 $
* @date 		$Date: 2009-03-23 10:11:43 -0700 (Mon, 23 Mar 2009) $
*
*/
class vB_Attachment_Store_vBForum_Post extends vB_Attachment_Store
{
	/**
	*	Postinfo
	*
	* @var	array
	*/
	protected $postinfo = array();

	/**
	* Threadinfo
	*
	* @var 	array
	*/
	protected $threadinfo = array();

	/**
	* Foruminfo
	*
	* @var	array
	*/
	protected $foruminfo = array();

	/**
	* Constructor
	*
	* @return	void
	*/
	public function __construct(&$registry, $contenttypeid, $categoryid, $values)
	{
		parent::__construct($registry, $contenttypeid, $categoryid, $values);
	}

	/**
	* Given an attachmentid, retrieve values that verify_permissions needs
	*
	* @param	int	Attachmentid
	*
	* @return	array
	*/
	public function fetch_associated_contentinfo($attachmentid)
	{
		return $this->registry->db->query_first("
			SELECT
				p.postid, p.threadid, t.forumid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "post AS p ON (p.postid = a.contentid)
			INNER JOIN " . TABLE_PREFIX . "thread AS t ON (p.threadid = t.threadid)
			WHERE
				a.attachmentid = " . intval($attachmentid) . "
		");
	}

	/**
	* Verifies permissions to attach content to posts
	*
	* @param	array	Contenttype information - bypass reading environment settings
	*
	* @return	boolean
	*/
	public function verify_permissions($info = array())
	{
		global $show;

		if ($info)
		{
			$this->values['postid'] = $info['postid'];
			$this->values['threadid'] = $info['threadid'];
			$this->values['forumid'] = $info['forumid'];
		}
		else
		{
			$this->values['postid'] = intval($this->values['p']) ? intval($this->values['p']) : intval($this->values['postid']);
			$this->values['threadid'] = intval($this->values['t']) ? intval($this->values['t']) : intval($this->values['threadid']);
			$this->values['forumid'] = intval($this->values['f']) ? intval($this->values['f']) : intval($this->values['forumid']);
		}

		if ($this->values['postid'])
		{
			if (!($this->postinfo = fetch_postinfo($this->values['postid'])))
			{
				return false;
			}
			$this->values['threadid'] = $this->postinfo['threadid'];
		}

		if ($this->values['threadid'])
		{
			if (!($this->threadinfo = fetch_threadinfo($this->values['threadid'])))
			{
				return false;
			}
			$this->values['forumid'] = $this->threadinfo['forumid'];
		}

		if ($this->values['forumid'] AND !($this->foruminfo = fetch_foruminfo($this->values['forumid'])))
		{
			return false;
		}

		if (!$this->foruminfo AND !$this->threadinfo AND !($this->postinfo AND $this->values['editpost']))
		{
			return false;
		}

		$forumperms = fetch_permissions($this->foruminfo['forumid']);

		// No permissions to post attachments in this forum or no permission to view threads in this forum.
		if (
			!($forumperms & $this->registry->bf_ugp_forumpermissions['canpostattachment'])
				OR
			!($forumperms & $this->registry->bf_ugp_forumpermissions['canview'])
				OR
			!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewthreads'])
		)
		{
			return false;
		}

		if (
			(!$this->postinfo AND !$this->foruminfo['allowposting'])
				OR
			$this->foruminfo['link']
				OR
			!$this->foruminfo['cancontainthreads']
		)
		{
			return false;
		}

		if ($this->threadinfo) // newreply.php or editpost.php called
		{
			if ($this->threadinfo['isdeleted'] OR (!$this->threadinfo['visible'] AND !can_moderate($this->threadinfo['forumid'], 'canmoderateposts')))
			{
				return false;
			}
			if (!$this->threadinfo['open'])
			{
				if (!can_moderate($this->threadinfo['forumid'], 'canopenclose'))
				{
					return false;
				}
			}
			if (
				($this->registry->userinfo['userid'] != $this->threadinfo['postuserid'])
					AND
				(
					!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers'])
						OR
					!($forumperms & $this->registry->bf_ugp_forumpermissions['canreplyothers'])
				))
			{
				return false;
			}

			// don't call this part on editpost.php (which will have a $postid)
			if (
				!$this->postinfo
					AND
				!($forumperms & $this->registry->bf_ugp_forumpermissions['canreplyown'])
					AND
				$this->registry->userinfo['userid'] == $this->threadinfo['postuserid']
			)
			{
				return false;
			}
		}
		else if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canpostnew'])) // newthread.php
		{
			return false;
		}

		if ($this->postinfo) // editpost.php
		{
			if (!can_moderate($this->threadinfo['forumid'], 'caneditposts'))
			{
				if (!($forumperms & $this->registry->bf_ugp_forumpermissions['caneditpost']))
				{
					return false;
				}
				else
				{
					if ($this->registry->userinfo['userid'] != $this->postinfo['userid'])
					{
						// check user owns this post
						return false;
					}
					else
					{
						// check for time limits
						if ($this->postinfo['dateline'] < (TIMENOW - ($this->registry->options['edittimelimit'] * 60)) AND $this->registry->options['edittimelimit'])
						{
							return false;
						}
					}
				}
			}

			$this->contentid = $this->postinfo['postid'];
			$this->userinfo = fetch_userinfo($this->postinfo['userid']);
			cache_permissions($this->userinfo, true);
		}
		else
		{
			$this->userinfo = $this->registry->userinfo;
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($this->foruminfo['forumid'], $this->foruminfo['password'], false);

		if (!$this->foruminfo['allowposting'])
		{
			$show['attachoption'] = false;
			$show['forumclosed'] = true;
		}

		return true;
	}

	/**
	* Ensures that attachment interface isn't display if forum doesn't allow posting and there are no existing attachments
	*
	* @return	boolean
	*/
	public function fetch_attachcount()
	{
		parent::fetch_attachcount();

		if (!$this->foruminfo['allowposting'] AND !$this->attachcount)
		{
			return false;
		}

		return true;
	}

	/**
	* Verifies permissions to attach content to posts
	*
	* @param	object	vB_Upload
	* @param	array		Information about uploaded attachment
	*
	* @return	void
	*/
	protected function process_upload($upload, $attachment, $imageonly = false)
	{
		if (!$this->foruminfo['allowposting'])
		{
			$error = $vbphrase['this_forum_is_not_accepting_new_attachments'];
			$errors[] = array(
				'filename' => is_array($attachment) ? $attachment['name'] : $attachment,
				'error'    => $error
			);
		}
		else
		{
			if (
				($attachmentid = parent::process_upload($upload, $attachment, $imageonly))
					AND
				$this->registry->userinfo['userid'] != $this->postinfo['userid']
					AND
				can_moderate($this->threadinfo['forumid'], 'caneditposts')
			)
			{
				$this->postinfo['attachmentid'] = $attachmentid;
				$this->postinfo['forumid'] = $foruminfo['forumid'];
				require_once(DIR . '/includes/functions_log_error.php');
				log_moderator_action($this->postinfo, 'attachment_uploaded');
			}

			return $attachmentid;
		}
	}

	/**
	* Set attachment to moderated if the forum dictates it so
	*
	* @return	object
	*/
	protected function &fetch_attachdm()
	{
		$attachdata =& parent::fetch_attachdm();
		$state = (
			!isset($this->foruminfo['moderateattach'])
				OR
			(
				!$this->foruminfo['moderateattach']
					OR
				can_moderate($this->foruminfo['forumid'], 'canmoderateattachments')
			)
		) ? 'visible' : 'moderation';
		$attachdata->set('state', $state);

		return $attachdata;
	}
}

/**
* Class for deleting post attachments
*
* @package 		vBulletin
* @version		$Revision: 29997 $
* @date 		$Date: 2009-03-23 10:11:43 -0700 (Mon, 23 Mar 2009) $
*
*/
class vB_Attachment_Dm_vBForum_Post extends vB_Attachment_Dm
{
	/**
	* pre_approve function - extend if the contenttype needs to do anything
	*
	* @param	array		list of attachment ids to approve
	* @param	boolean	verify permission to approve
	*
	* @return	boolean
	*/
	public function pre_approve($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		if ($checkperms)
		{
			// Verify that we have permission to view these attachmentids
			$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
			$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

			if (count($list) != count($attachments))
			{
				return false;
			}
		}

		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress,
				post.postid, post.threadid, post.dateline AS p_dateline, post.userid AS post_userid,
				thread.forumid, thread.threadid, thread.open
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = a.contentid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.postid = post.postid)
			WHERE a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if (!can_moderate($id['forumid'], 'canmoderateattachments') AND $checkperms)
			{
				return false;
			}
		}
		return true;
	}

	/**
	* pre_delete function - extend if the contenttype needs to do anything
	*
	* @param	array		list of deleted attachment ids to delete
	* @param	boolean	verify permission to delete
	*
	* @return	boolean
	*/
	public function pre_delete($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'postlist'   => array(),
			'threadlist' => array()
		);

		if ($checkperms)
		{
			// Verify that we have permission to view these attachmentids
			$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
			$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

			if (count($list) != count($attachments))
			{
				return false;
			}
		}

		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress,
				post.postid, post.threadid, post.dateline AS p_dateline, post.userid AS post_userid,
				thread.forumid, thread.threadid, thread.open,
				editlog.hashistory
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = a.contentid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
			LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.postid = post.postid)
			WHERE a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if (!$id['inprogress'] AND $checkperms)
			{
				if (!$id['open'] AND !can_moderate($id['forumid'], 'canopenclose') AND !$this->registry->options['allowclosedattachdel'])
				{
					return false;
				}
				else if (!can_moderate($id['forumid'], 'caneditposts'))
				{
					$forumperms = fetch_permissions($id['forumid']);
					if (!($forumperms & $this->registry->bf_ugp_forumpermissions['caneditpost']) OR $this->registry->userinfo['userid'] != $id['userid'])
					{
						return false;
					}
					else if(!$this->registry->options['allowattachdel'] AND $this->registry->options['edittimelimit'] AND $id['p_dateline'] < TIMENOW - $this->registry->options['edittimelimit'] * 60)
					{
						return false;
					}
				}
			}

			if ($id['postid'])
			{
				$this->lists['postlist']["{$id['postid']}"]++;

				if ($this->log)
				{
					if (($this->registry->userinfo['permissions']['genericoptions'] & $this->registry->bf_ugp_genericoptions['showeditedby']) AND $id['p_dateline'] < (TIMENOW - ($this->registry->options['noeditedbytime'] * 60)))
					{
						if (empty($replaced["$id[postid]"]))
						{
							/*insert query*/
							$this->registry->db->query_write("
								REPLACE INTO " . TABLE_PREFIX . "editlog
										(postid, userid, username, dateline, hashistory)
								VALUES
									($id[postid],
									" . $this->registry->userinfo['userid'] . ",
									'" . $this->registry->db->escape_string($this->registry->userinfo['username']) . "',
									" . TIMENOW . ",
									" . intval($id['hashistory']) . ")
							");
							$replaced["$id[postid]"] = true;
						}
					}
					if ($this->registry->userinfo['userid'] != $id['post_userid'] AND can_moderate($id['forumid'], 'caneditposts'))
					{
						$postinfo = array(
							'postid'       =>& $id['postid'],
							'threadid'     =>& $id['threadid'],
							'forumid'      =>& $id['forumid'],
							'attachmentid' =>& $id['attachmentid'],
						);
						require_once(DIR . '/includes/functions_log_error.php');
						log_moderator_action($postinfo, 'attachment_removed');
					}
				}
			}
			if ($id['threadid'])
			{
				$this->lists['threadlist']["{$id['threadid']}"]++;
			}
		}
		return true;
	}

	/**
	* post_delete function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_delete()
	{
		// Update attach in the post table
		if (!empty($this->lists['postlist']))
		{
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			require_once(DIR . '/vb/types.php');
			vB_Bootstrap_Framework::init();
			$types = vB_Types::instance();
			$contenttypeid = intval($types->getContentTypeID('vBForum_Post'));

			// COALASCE() used here due to issue in 5.1.30 (at least) where mysql reports COLUMN CANNOT BE NULL error
			// when the subquery returns a null
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "post AS p
				SET p.attach = COALESCE((
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE
						p.postid = a.contentid
							AND
						a.contenttypeid = $contenttypeid
					GROUP BY a.contentid
				), 0)
				WHERE p.postid IN (" . implode(", ", array_keys($this->lists['postlist'])) . ")
			");
		}

		// Update attach in the thread table
		if (!empty($this->lists['threadlist']))
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "thread AS t
				SET t.attach = (
					SELECT SUM(attach)
					FROM " . TABLE_PREFIX . "post AS p
					WHERE p.threadid = t.threadid
					GROUP BY p.threadid
				)
				WHERE t.threadid IN (" . implode(", ", array_keys($this->lists['threadlist'])) . ")
			");
		}
	}
}

class vB_Attachment_Upload_Displaybit_vBForum_Post extends vB_Attachment_Upload_Displaybit
{
	/**
	*	Parses the appropriate template for contenttype that is to be updated on the calling window during an upload
	*
	* @param	array	Attachment information
	* @param	array	Values array pertaining to contenttype
	* @param	boolean	Disable template comments
	*
	* @return	string
	*/
	public function process_display_template($attach, $values = array(), $disablecomment = true)
	{
		$attach['extension'] = strtolower(file_extension($attach['filename']));
		$attach['filename']  = htmlspecialchars_uni($attach['filename']);
		$attach['filesize']  = vb_number_format($attach['filesize'], 1, true);
		$attach['imgpath']   = vB_Template_Runtime::fetchStyleVar('imgdir_attach') . "/$attach[extension].gif";

		$templater = vB_Template::create('newpost_attachmentbit');
			$templater->register('attach', $attach);
		return $templater->render($disablecomment);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 29983 $
|| ####################################################################
\*======================================================================*/