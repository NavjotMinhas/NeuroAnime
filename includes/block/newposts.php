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

class vB_BlockType_Newposts extends vB_BlockType
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
		'newposts_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'newposts_titlemaxchars' => array(
			'defaultvalue' => 35,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
		'newposts_messagemaxchars' => array(
			'defaultvalue' => 200,
			'displayorder' => 3,
			'datatype'     => 'integer'
		),
		'newposts_forumids' => array(
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
		
		if ($this->config['newposts_forumids'])
		{
			if (in_array(-1, $this->config['newposts_forumids']))
			{
				$forumids = array_keys($this->registry->forumcache);
			}
			else
			{
				$forumids = $this->config['newposts_forumids'];
			}
		}
		else
		{
			$forumids = array_keys($this->registry->forumcache);
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
					$globalignore = "AND post.userid NOT IN ($Coventry) ";
				}
			}

			$datecut = TIMENOW - ($this->config['datecut'] * 86400);

			$posts = $this->registry->db->query_read_slave("
				SELECT post.dateline, post.pagetext AS message, post.allowsmilie, post.postid,
					thread.threadid, thread.title, thread.prefixid, post.attach,
					forum.forumid,
					user.*
					" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				FROM " . TABLE_PREFIX . "post AS post
				JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
				JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
				"  . ($this->registry->products['vbcms'] ? " LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.associatedthreadid = thread.threadid \n" :  '')
			. ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$forumsql
				AND thread.visible = 1
				AND post.visible = 1
				AND thread.open <> 10
				AND post.dateline > $datecut
				$globalignore
				" . ($this->userinfo['ignorelist'] ? "AND post.userid NOT IN (" . implode(',', explode(' ', $this->userinfo['ignorelist'])) . ")": '')
			. ($this->registry->products['vbcms'] ? " AND info.associatedthreadid IS NULL " :  '') . "
			ORDER BY post.dateline DESC
			LIMIT 0," . intval($this->config['newposts_limit']) . "
			");

			while ($post = $this->registry->db->fetch_array($posts))
			{
				//$post['url'] = fetch_seo_url('thread', $post, array('p' => $post['postid'])) . '#post' . $post['postid'];
				//$post['newposturl'] = fetch_seo_url('thread', $post, array('goto' => 'newpost'));

				// trim the title after fetching the urls
				//$post['title'] = fetch_trimmed_title($post['title'], $this->config['newposts_titlemaxchars']);
				//still need to censor the title
				$post['title'] = fetch_censored_text($post['title']);
				$post['date'] = vbdate($this->registry->options['dateformat'], $post['dateline'], true);
				$post['time'] = vbdate($this->registry->options['timeformat'], $post['dateline']);

				$post['message'] = $this->get_summary($post['message'], $this->config['newposts_messagemaxchars']);

				// get avatar
				$this->fetch_avatarinfo($post);

				$postarray[$post['postid']] = $post;
			}
			return($postarray);
		}
	}
	
	public function getHTML($postinfo = false)
	{
		if (!$postinfo)
		{
			$postinfo = $this->getData();
		}
		
		if ($postinfo)
		{
			
			foreach ($postinfo as $key => $post)
			{
				$postinfo[$key]['url'] = fetch_seo_url('thread', $post, array('p' => $post['postid'])) . '#post' . $post['postid'];
				$postinfo[$key]['newposturl'] = fetch_seo_url('thread', $post, array('goto' => 'newpost'));
				
				// trim the title after fetching the urls
				$postinfo[$key]['title'] = fetch_trimmed_title($post['title'], $this->config['newposts_titlemaxchars']);
			}

			$templater = vB_Template::create('block_newposts');
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('posts', $postinfo);
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
