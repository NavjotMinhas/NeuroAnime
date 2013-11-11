<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

class vB_APIMethod_api_getnewtop extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin;

		$data['new']['thread'] = array();
		if ($vbulletin->userinfo['userid'])
		{
			$data['new']['thread'] = $this->getThreads('last');
			$data['whatsnew']['thread'] = true;
		}
		if (!$data['new']['thread'])
		{
			$data['new']['thread'] = $this->getThreads('new');
			$data['whatsnew']['thread'] = false;
		}
		$data['top']['thread'] = $this->getThreads('top');

		$blogenabled = ($vbulletin->products['vbblog'] == '1');
		$cmsenabled = ($vbulletin->products['vbcms'] == '1');

		if ($blogenabled)
		{
			$data['new']['blog'] = array();
			if ($vbulletin->userinfo['userid'])
			{
				$data['new']['blog'] = $this->getBlogs('last');
				$data['whatsnew']['blog'] = true;
			}
			if (!$data['new']['blog'])
			{
				$data['new']['blog'] = $this->getBlogs('new');
				$data['whatsnew']['blog'] = false;
			}
			$data['top']['blog'] = $this->getBlogs('top');
		}
		if ($cmsenabled)
		{
			$data['new']['article'] = array();
			if ($vbulletin->userinfo['userid'])
			{
				$data['new']['article'] = $this->getArticles('last');
				$data['whatsnew']['article'] = true;
			}
			if (!$data['new']['article'])
			{
				$data['new']['article'] = $this->getArticles('new');
				$data['whatsnew']['article'] = false;
			}
			$data['top']['article'] = $this->getArticles('top');
		}

		$data['new']['all'] = array_merge((array)$data['new']['thread'], (array)$data['new']['blog'], (array)$data['new']['article']);
		if ($data['new']['all'])
		{
			foreach ($data['new']['all'] as $key => $row) {
				$time[$key]  = $row['time'];
			}
			array_multisort($time, SORT_DESC, $data['new']['all']);
			array_splice($data['new']['all'], 10);
		}
		$data['top']['all'] = array_merge((array)$data['top']['thread'], (array)$data['top']['blog'], (array)$data['top']['article']);
		if ($data['top']['all'])
		{
			foreach ($data['top']['all'] as $key => $row) {
				$viewcount[$key]  = $row['viewcount'];
			}
			array_multisort($viewcount, SORT_DESC, $data['top']['all']);
			array_splice($data['top']['all'], 10);
		}

		return $data;
	}

	private function getBlogs($type)
	{
		global $vbulletin, $VB_API_REQUESTS;

		$blogentries_catids = $this->verifycommaoption($vbulletin->options['mobilehomeblogcatids']);
		$blogentries_userids = $this->verifycommaoption($vbulletin->options['mobilehomebloguserids']);
		
		if ($blogentries_userids)
		{
			$useridsql = '';
			$useridsql = " AND blog.userid IN (-1";
			foreach ((array)$blogentries_userids AS $userid)
			{
				$useridsql .= "," . intval($userid);
			}
			$useridsql .= ")";
		}
		

		require_once(DIR . '/includes/blog_functions_shared.php');
		prepare_blog_category_permissions($vbulletin->userinfo);


		$catjoin = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid)";
		if ($blogentries_catids)
		{
			$catidsql = '';
			if (!in_array(-2, $blogentries_catids))
			{
				if (in_array(-1, $blogentries_catids))
				{
					$catidsql .= " AND (cu.blogcategoryid IS NULL OR cu.blogcategoryid IN (-1";
				}
				else
				{
					$catidsql .= " AND (cu.blogcategoryid IN (-1";
				}
				foreach ($blogentries_catids AS $catid)
				{
					$catidsql .= ",$catid";
				}
				$catidsql .= "))";

				if (!empty($vbulletin->userinfo['blogcategorypermissions']['cantview']))
				{
					$catidsql .= " AND cu.blogcategoryid NOT IN (" . implode(", ", $vbulletin->userinfo['blogcategorypermissions']['cantview']) . ")";
				}
			}
		}

		if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{
			$sql_and[] = "blog.userid = " . $vbulletin->userinfo['userid'];
		}
		if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
		{
			$sql_and[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
		}

		$state = array('visible');
		if (can_moderate_blog('canmoderateentries'))
		{
			$state[] = 'moderation';
		}

		$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
		$sql_and[] = "blog.dateline <= " . TIMENOW;
		$sql_and[] = "blog.pending = 0";

		$sql_join = array();
		$sql_or = array();
		if (!can_moderate_blog())
		{
			if ($vbulletin->userinfo['userid'])
			{
				$sql_or[] = "blog.userid = " . $vbulletin->userinfo['userid'];
				$sql_or[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
				$sql_or[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
				$sql_or[] = "(options_member & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
				$sql_and[] = "(" . implode(" OR ", $sql_or) . ")";

				$sql_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
				$sql_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";

				$sql_and[] = "
					(blog.userid = " . $vbulletin->userinfo['userid'] . "
						OR
					~blog.options & " . $vbulletin->bf_misc_vbblogoptions['private'] . "
						OR
					(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL))";
			}
			else
			{
				$sql_and[] = "options_guest & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
				$sql_and[] = "~blog.options & " . $vbulletin->bf_misc_vbblogoptions['private'];

			}
		}


		if ($type != 'last')
		{
			$datecut = TIMENOW - ($vbulletin->options['mobilehomeblogdatecut'] * 86400);
		}
		else
		{
			$datecut = $vbulletin->userinfo['lastvisit'];
		}

		switch ($type)
		{
			case 'new':
				$ordersql = " blog.dateline DESC";
				$datecutoffsql = " AND blog.dateline > $datecut";
				break;
			case 'top':
				$ordersql = " blog.views DESC";
				$datecutoffsql = " AND blog.dateline > $datecut";
				break;
			case 'last':
				$ordersql = " blog.lastcomment DESC";
				$datecutoffsql = " AND blog.lastcomment > $datecut";
				break;
			default:
				return null;
		}

		// remove threads from users on the global ignore list if user is not a moderator
		$globalignore = '';
		if (trim($vbulletin->options['globalignore']) != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			if ($Coventry = fetch_coventry('string'))
			{
				$globalignore = "AND blog.userid NOT IN ($Coventry) ";
			}
		}

		$results = $vbulletin->db->query_read_slave("
			SELECT DISTINCT blog.blogid, blog.comments_visible as replycount, blog.title, blog.lastcomment, blog.lastcommenter, blog.postedby_userid, blog.postedby_username, blog.dateline, blog.views,
				blog_text.blogtextid, blog_text.pagetext AS message,
				blog_user.title as blogtitle, blog_user.description as blogdescription,
				user.*
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "blog AS blog
			INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = blog.firstblogtextid)
			INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
			$catjoin
			" . (!empty($sql_join) ? implode("\r\n", $sql_join) : "") . "
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$useridsql
				$catidsql
				$datecutoffsql
				$globalignore
				AND " . implode("\r\n\tAND ", $sql_and) . "
			ORDER BY$ordersql
			LIMIT 0, " . $vbulletin->options['mobilehomemaxitems'] ."
		");

		$i = 0;
		while ($row = $vbulletin->db->fetch_array($results))
		{
			$row['title'] = fetch_censored_text($row['title']);

			// get avatar
			$this->fetch_avatarinfo($row);

			$array[$i] = array(
				'blogid' => $row['blogid'],
				'title' => $row['title'],
				'replycount' => $row['replycount'],
				'viewcount' => $row['views'],
				'userid' => $row['postedby_userid'],
				'username' => $row['postedby_username'],
				'avatarurl' => $row['avatarurl'],
				'type' => 'blog',
				'time' => $row['lastcomment'],
			);

			if ($VB_API_REQUESTS['api_version'] > 1)
			{
				$array[$i]['lastposttime'] = $row['lastcomment'];
			}
			else
			{
				$array[$i]['lastpostdate'] = date($vbulletin->options['dateformat'], $row['lastcomment']);
				$array[$i]['lastposttime'] = date($vbulletin->options['timeformat'], $row['lastcomment']);
			}

			$i++;
		}
		return $array;
	}

	private function getArticles($type)
	{
		global $vbulletin, $VB_API_REQUESTS;

		$catidsql = '';
		$catjoin = '';

		$cmsarticles_catids = $this->verifycommaoption($vbulletin->options['mobilehomearticlecatids']);
		$cmsarticles_sectionids = $this->verifycommaoption($vbulletin->options['mobilehomearticlesectionids']);
		if ($cmsarticles_catids)
		{
			if (!in_array(-1, $cmsarticles_catids))
			{
				$catjoin = "LEFT JOIN " . TABLE_PREFIX . "cms_nodecategory AS cms_nodecategory ON (cms_node.nodeid = cms_nodecategory.nodeid)";
				$catidsql = " AND cms_nodecategory.categoryid IN (-1";
				foreach ($cmsarticles_catids as $groupid)
				{
					$catidsql .= "," . intval($groupid);
				}
				$catidsql .= ")";
			}
		}

		$sectionidsql = '';
		if ($cmsarticles_sectionids)
		{

			if (!in_array(-1, $cmsarticles_sectionids))
			{
				$sectionidsql = " AND cms_node.parentnode IN (-1";
				foreach ($cmsarticles_sectionids AS $catid)
				{
					$sectionidsql .= ",$catid";
				}
				$sectionidsql .= ")";
			}
		}

		if ($type != 'last')
		{
			$datecut = TIMENOW - ($vbulletin->options['mobilehomearticledatecut'] * 86400);
		}
		else
		{
			$datecut = $vbulletin->userinfo['lastvisit'];
		}

		switch ($type)
		{
			case 'new':
				$ordersql = " cms_node.publishdate DESC";
				$datecutoffsql = " AND cms_node.publishdate > $datecut";
				break;
			case 'top':
				$ordersql = " cms_nodeinfo.viewcount DESC";
				$datecutoffsql = " AND cms_node.publishdate > $datecut";
				break;
			case 'last':
				$ordersql = " thread.lastpost DESC";
				$datecutoffsql = " AND thread.lastpost > $datecut";
				break;
			default:
				return null;
		}

		$results = $vbulletin->db->query_read_slave("
			SELECT cms_article.contentid, cms_article.pagetext as message,
				cms_node.nodeid, cms_node.url, cms_node.publishdate,
				cms_nodeinfo.title, cms_nodeinfo.viewcount,
				thread.replycount, thread.lastpost, thread.lastposter, thread.lastpostid, thread.lastposterid,
				user.*
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "cms_article AS cms_article
			INNER JOIN " . TABLE_PREFIX . "cms_node AS cms_node ON (cms_node.contentid = cms_article.contentid)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS cms_nodeinfo ON (cms_nodeinfo.nodeid = cms_node.nodeid)
			$catjoin
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (cms_nodeinfo.associatedthreadid = thread.threadid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (cms_node.userid = user.userid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$sectionidsql
				$catidsql
				AND cms_node.setpublish = 1
				AND cms_node.publishdate <= " . TIMENOW . "
				AND cms_node.publicpreview = 1
				$datecutoffsql
			ORDER BY$ordersql
			LIMIT 0, " . $vbulletin->options['mobilehomemaxitems'] ."
		");

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();

		$i = 0;
		while ($row = $vbulletin->db->fetch_array($results))
		{
			// trim the title after fetching the url and censor it
			$row['title'] = fetch_censored_text($row['title']);

			// get avatar
			$this->fetch_avatarinfo($row);

			$array[$i] = array(
				'nodeid' => $row['nodeid'],
				'title' => $row['title'],
				'replycount' => $row['replycount'],
				'viewcount' => $row['viewcount'],
				'userid' => $row['userid'],
				'username' => $row['username'],
				'avatarurl' => $row['avatarurl'],
				'type' => 'article',
				'time' => $row['publishdate'],
			);

			if ($VB_API_REQUESTS['api_version'] > 1)
			{
				$array[$i]['publishtime'] = $row['publishdate'];
			}
			else
			{
				$array[$i]['publishdate'] = date($vbulletin->options['dateformat'], $row['publishdate']);
				$array[$i]['publishtime'] = date($vbulletin->options['timeformat'], $row['publishdate']);
			}

			$i++;
		}
		return $array;
	}

	private function getThreads($type)
	{
		global $vbulletin, $VB_API_REQUESTS;

		if ($vbulletin->options['mobilehomethreadforumids'])
		{
			$forumids = $this->verifycommaoption($vbulletin->options['mobilehomethreadforumids']);
		}
		if (!$forumids)
		{
			$forumids = array_keys($vbulletin->forumcache);
		}

		if ($type != 'last')
		{
			$datecut = TIMENOW - ($vbulletin->options['mobilehomethreaddatecut'] * 86400);
		}
		else
		{
			$datecut = $vbulletin->userinfo['lastvisit'];
		}
		switch ($type)
		{
			case 'top':
				$ordersql = " thread.views DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
			case 'new':
				$ordersql = " thread.dateline DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
			case 'last':
				$ordersql = " thread.lastpost DESC";
				$datecutoffsql = " AND thread.lastpost > $datecut";
				break;
			default:
				return null;
		}

		foreach ($forumids AS $forumid)
		{
			$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
			if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']
				AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])
				AND (($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
				AND verify_forum_password($forumid, $vbulletin->forumcache["$forumid"]['password'], false)
				)
			{
				$forumchoice[] = $forumid;
			}
		}

		if (!empty($forumchoice))
		{
			$forumsql = "AND thread.forumid IN(" . implode(',', $forumchoice) . ")";

			// remove threads from users on the global ignore list if user is not a moderator
			$globalignore = '';
			if (trim($vbulletin->options['globalignore']) != '')
			{
				require_once(DIR . '/includes/functions_bigthree.php');
				if ($Coventry = fetch_coventry('string'))
				{
					$globalignore = "AND thread.postuserid NOT IN ($Coventry) ";
				}
			}

			// query last threads from visible / chosen forums
			$threads = $vbulletin->db->query_read_slave("
				SELECT thread.threadid, thread.title, thread.prefixid, post.attach,
					thread.postusername, thread.dateline, thread.lastpostid, thread.lastpost AS threadlastpost, thread.lastposterid, thread.lastposter, thread.replycount, thread.views,
					forum.forumid, forum.title_clean as forumtitle,
					post.pagetext AS message, post.allowsmilie, post.postid,
					user.*
					" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				FROM " . TABLE_PREFIX . "thread AS thread
				INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (thread.postuserid = user.userid)
				" . ($vbulletin->products['vbcms'] ? " LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.associatedthreadid = thread.threadid \n" :  '')
			. ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$forumsql
				AND thread.visible = 1
				AND post.visible = 1
				AND open <> 10
				$datecutoffsql
				$globalignore
				" . ($vbulletin->userinfo['ignorelist'] ? "AND thread.postuserid NOT IN (" . implode(',', explode(' ', $vbulletin->userinfo['ignorelist'])) . ")": '')
			. ($vbulletin->products['vbcms'] ? " AND info.associatedthreadid IS NULL " :  '')
			. "
			ORDER BY$ordersql
			LIMIT 0, " . $vbulletin->options['mobilehomemaxitems'] ."
			");

			$i = 0;
			while ($thread = $vbulletin->db->fetch_array($threads))
			{
				// still need to censor the title
				$thread['title'] = fetch_censored_text($thread['title']);

				// get avatar
				$this->fetch_avatarinfo($thread);

				$array[$i] = array(
					'id' => $thread['threadid'],
					'title' => $thread['title'],
					'replycount' => $thread['replycount'],
					'viewcount' => $thread['views'],
					'userid' => $thread['userid'],
					'username' => $thread['postusername'],
					'avatarurl' => $thread['avatarurl'],
					'type' => 'thread',
					'time' => $thread['lastpost'],
				);

				if ($VB_API_REQUESTS['api_version'] > 1)
				{
					$array[$i]['lastposttime'] = $thread['threadlastpost'];
				}
				else
				{
					$array[$i]['lastpostdate'] = date($vbulletin->options['dateformat'], $thread['threadlastpost']);
					$array[$i]['lastposttime'] = date($vbulletin->options['timeformat'], $thread['threadlastpost']);
				}

				$i++;
			}
		}
		return $array;
	}

	private function fetch_avatarinfo(&$userinfo)
	{
		global $vbulletin;

		$userinfo = array_merge($userinfo , convert_bits_to_array($userinfo['adminoptions'] , $vbulletin->bf_misc_adminoptions));

		// get avatar
		if ($userinfo['avatarid'])
		{
			$userinfo['avatarurl'] = $userinfo['avatarpath'];
		}
		else
		{
			if ($userinfo['hascustomavatar'] AND $vbulletin->options['avatarenabled'])
			{
				if ($vbulletin->options['usefileavatar'])
				{
					$userinfo['avatarurl'] = $vbulletin->options['avatarurl'] . '/avatar' . $userinfo['userid'] . '_' . $userinfo['avatarrevision'] . '.gif';
				}
				else
				{
					$userinfo['avatarurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . '&amp;dateline=' . $userinfo['avatardateline'];
				}

				$userinfo['avwidthpx'] = intval($userinfo['avwidth']);
				$userinfo['avheightpx'] = intval($userinfo['avheight']);

				if ($userinfo['avwidth'] AND $userinfo['avheight'])
				{
					$userinfo['avwidth'] = 'width="' . $userinfo['avwidth'] . '"';
					$userinfo['avheight'] = 'height="' . $userinfo['avheight'] . '"';
				}
				else
				{
					$userinfo['avwidth'] = '';
					$userinfo['avheight'] = '';
				}
			}
			else
			{
				$userinfo['avatarurl'] = '';
			}
		}

		if (empty($userinfo['permissions']))
		{
			cache_permissions($userinfo, false);
		}

		if ( // no avatar defined for this user
			empty($userinfo['avatarurl'])
			OR // visitor doesn't want to see avatars
			($vbulletin->userinfo['userid'] > 0 AND !$vbulletin->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$userinfo['avatarid'] AND !($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']) AND !$userinfo['adminavatar']) //
		)
		{
			$userinfo['showavatar'] = false;
		}
		else
		{
			$userinfo['showavatar'] = true;
		}
	}

	private function verifycommaoption($option)
	{
		$options = explode(',', $option);
		$data = array();
		foreach ($options as $option)
		{
			$option = intval($option);
			if ($option)
			{
				$data[] = $option;
			}
		}

		return $data;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/