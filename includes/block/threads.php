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

class vB_BlockType_Threads extends vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = '';

	/**
	 * The title of the block type
	 * We use it only when reload block types in admincp.
	 * Automatically set in the vB_BlockType constructor.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * The description of the block type
	 * We use it only when reload block types in admincp. So it's static.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * The block settings
	 * It uses the same data structure as forum settings table
	 * e.g.:
	 * <code>
	 * $settings = array(
	 *     'varname' => array(
	 *         'defaultvalue' => 0,
	 *         'optioncode'   => 'yesno'
	 *         'displayorder' => 1,
	 *         'datatype'     => 'boolean'
	 *     ),
	 * );
	 * </code>
	 * @see print_setting_row()
	 *
	 * @var string
	 */
	protected $settings = array(
		'threads_type' => array(
			'defaultvalue' => 0,
			'optioncode'   => 'radio:piped
0|new_started
1|new_replied
2|most_replied
3|most_viewed',
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'threads_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
		'threads_titlemaxchars' => array(
			'defaultvalue' => 35,
			'displayorder' => 3,
			'datatype'     => 'integer'
		),
		'threads_forumids' => array(
			'defaultvalue' => -1,
			'optioncode'   => 'selectmulti:eval
$options = construct_forum_chooser_options(0, fetch_phrase("all_forums", "vbblock"));',
			'displayorder' => 4,
			'datatype'     => 'arrayinteger'
		),
		'datecut' => array(
			'defaultvalue' => 30,
			'displayorder' => 5,
			'datatype'     => 'integer'
		)
	);

	public function getData()
	{
		if ($this->config['threads_forumids'])
		{
			if (in_array(-1, $this->config['threads_forumids']))
			{
				$forumids = array_keys($this->registry->forumcache);
			}
			else
			{
				$forumids = $this->config['threads_forumids'];
			}
		}
		else
		{
			$forumids = array_keys($this->registry->forumcache);
		}

		$datecut = TIMENOW - ($this->config['datecut'] * 86400);

		switch (intval($this->config['threads_type']))
		{
			case 0:
				$ordersql = " thread.dateline DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
			case 1:
				$ordersql = " thread.lastpost DESC";
				$datecutoffsql = " AND thread.lastpost > $datecut";
				break;
			case 2:
				$ordersql = " thread.replycount DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
			case 3:
				$ordersql = " thread.views DESC";
				$datecutoffsql = " AND thread.dateline > $datecut";
				break;
		}

		foreach ($forumids AS $forumid)
		{
			$forumperms =& $this->registry->userinfo['forumpermissions']["$forumid"];
			if ($forumperms & $this->registry->bf_ugp_forumpermissions['canview']
				AND ($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers'])
				AND (($forumperms & $this->registry->bf_ugp_forumpermissions['canviewthreads']))
				AND verify_forum_password($forumid, $this->registry->forumcache["$forumid"]['password'], false)
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
			if (trim($this->registry->options['globalignore']) != '')
			{
				require_once(DIR . '/includes/functions_bigthree.php');
				if ($Coventry = fetch_coventry('string'))
				{
					$globalignore = "AND thread.postuserid NOT IN ($Coventry) ";
				}
			}

			// query last threads from visible / chosen forums
			$threads = $this->registry->db->query_read_slave("
				SELECT thread.threadid, thread.title, thread.prefixid, post.attach,
					thread.postusername, thread.dateline, thread.lastpostid, thread.lastpost AS threadlastpost, thread.lastposterid, thread.lastposter, thread.replycount,
					forum.forumid, forum.title_clean as forumtitle,
					post.pagetext AS message, post.allowsmilie, post.postid,
					user.*
					" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				FROM " . TABLE_PREFIX . "thread AS thread
				INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = thread.firstpostid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (thread.postuserid = user.userid)
				" . ($this->registry->products['vbcms'] ? " LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.associatedthreadid = thread.threadid \n" :  '')
			. ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$forumsql
				AND thread.visible = 1
				AND post.visible = 1
				AND open <> 10
				$datecutoffsql
				$globalignore
				" . ($this->userinfo['ignorelist'] ? "AND thread.postuserid NOT IN (" . implode(',', explode(' ', $this->userinfo['ignorelist'])) . ")": '')
			. ($this->registry->products['vbcms'] ? " AND info.associatedthreadid IS NULL " :  '')
			. "
			ORDER BY$ordersql
			LIMIT 0," . intval($this->config['threads_limit']) . "
			");

			while ($thread = $this->registry->db->fetch_array($threads))
			{
//				$thread['url'] = fetch_seo_url('thread', $thread);
//				$thread['newposturl'] = fetch_seo_url('thread', $thread, array('goto' => 'newpost'));
//				$thread['lastposturl'] = fetch_seo_url('thread', $thread, array('p' => $thread['lastpostid'])) . '#post' . $thread['lastpostid'];

				// still need to censor the title
				$thread['title'] = fetch_censored_text($thread['title']);
				$thread['date'] = vbdate($this->registry->options['dateformat'], $thread['dateline'], true);
				$thread['time'] = vbdate($this->registry->options['timeformat'], $thread['dateline']);

				$thread['lastpostdate'] = vbdate($this->registry->options['dateformat'], $thread['threadlastpost'], true);
				$thread['lastposttime'] = vbdate($this->registry->options['timeformat'], $thread['threadlastpost']);

				// get avatar
				$this->fetch_avatarinfo($thread);

				$threadarray[$thread['threadid']] = $thread;
			}
		}
		return $threadarray;
	}

	public function getHTML($threadarray = false)
	{
		if (!$threadarray)
		{	
			$threadarray = $this->getData();
		}

		if ($threadarray)
		{
			foreach ($threadarray as $key => $thread)
			{	
				$threadarray[$key]['url'] = fetch_seo_url('thread', $thread);
				$threadarray[$key]['newposturl'] = fetch_seo_url('thread', $thread, array('goto' => 'newpost'));
				$threadarray[$key]['lastposturl'] = fetch_seo_url('thread', $thread, array('p' => $thread['lastpostid'])) . 
					'#post' . $thread['lastpostid'];
				$threadarray[$key]['title'] = fetch_trimmed_title($thread['title'], $this->config['threads_titlemaxchars']);
			}

			$templater = vB_Template::create('block_threads');
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('threadstype', $this->config['threads_type']);
				$templater->register('threads', $threadarray);
			return $templater->render();
		}
	}

	public function getHash()
	{
		$context = new vB_Context('forumblock' ,
			array(
				'blockid' => $this->blockinfo['blockid'],
				'permissions' => $this->userinfo['forumpermissions'],
				'ignorelist' => $this->userinfo['ignorelist'],
				THIS_SCRIPT)
			);

		return strval($context);
	}
}
