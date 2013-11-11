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

class vB_BlockType_Blogentries extends vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = 'vbblog';

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
		'blogentries_type' => array(
			'defaultvalue' => 0,
			'optioncode'   => 'radio:piped
0|new_started
1|new_replied
2|most_replied
3|most_viewed',
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'blogentries_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
		'blogentries_titlemaxchars' => array(
			'defaultvalue' => 35,
			'displayorder' => 3,
			'datatype'     => 'integer'
		),
		'blogentries_messagemaxchars' => array(
			'defaultvalue' => 200,
			'displayorder' => 4,
			'datatype'     => 'integer'
		),
		'blogentries_catids' => array(
			'defaultvalue' => -2,
			'optioncode'   => 'selectmulti:eval
$options = vB_BlockType_Blogentries::construct_cat_chooser_options(fetch_phrase("all_categories", "vbblock"));',
			'displayorder' => 5,
			'datatype'     => 'arrayinteger'
		),
		'blogentries_userids' => array(
			'defaultvalue' => '',
			'displayorder' => 6,
			'datatype'     => 'free'
		),
		'datecut' => array(
			'defaultvalue' => 30,
			'displayorder' => 7,
			'datatype'     => 'integer'
		)
	);

	public function getData()
	{
		$vbulletin = &$this->registry;

		if ($this->config['blogentries_userids'])
		{
			$userids = explode(',', $this->config['blogentries_userids']);
			$useridsql = '';
			if (intval($userids[0]))
			{
				$useridsql = " AND blog.userid IN (-1";
				foreach ((array)$userids AS $userid)
				{
					$useridsql .= "," . intval($userid);
				}
				$useridsql .= ")";
			}
		}

		require_once(DIR . '/includes/blog_functions_shared.php');
		prepare_blog_category_permissions($this->registry->userinfo);

		$catjoin = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS cu ON (cu.blogid = blog.blogid)";
		if ($this->config['blogentries_catids'])
		{
			$catidsql = '';
			if (!in_array(-2, $this->config['blogentries_catids']))
			{
				if (in_array(-1, $this->config['blogentries_catids']))
				{
					$catidsql .= " AND (cu.blogcategoryid IS NULL OR cu.blogcategoryid IN (-1";
				}
				else
				{
					$catidsql .= " AND (cu.blogcategoryid IN (-1";
				}
				foreach ($this->config['blogentries_catids'] AS $catid)
				{
					$catidsql .= ",$catid";
				}
				$catidsql .= "))";

				if (!empty($this->registry->userinfo['blogcategorypermissions']['cantview']))
				{
					$catidsql .= " AND cu.blogcategoryid NOT IN (" . implode(", ", $this->registry->userinfo['blogcategorypermissions']['cantview']) . ")";
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



		$datecut = TIMENOW - ($this->config['datecut'] * 86400);

		switch (intval($this->config['blogentries_type']))
		{
			case 0:
				$ordersql = " blog.dateline DESC";
				$datecutoffsql = " AND blog.dateline > $datecut";
				break;
			case 1:
				$ordersql = " blog.lastcomment DESC";
				$datecutoffsql = " AND blog.lastcomment > $datecut";
				break;
			case 2:
				$ordersql = " blog.comments_visible DESC";
				$datecutoffsql = " AND blog.dateline > $datecut";
				break;
			case 3:
				$ordersql = " blog.views DESC";
				$datecutoffsql = " AND blog.dateline > $datecut";
				break;
		}

		// remove threads from users on the global ignore list if user is not a moderator
		$globalignore = '';
		if (trim($this->registry->options['globalignore']) != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			if ($Coventry = fetch_coventry('string'))
			{
				$globalignore = "AND blog.userid NOT IN ($Coventry) ";
			}
		}

		$results = $this->registry->db->query_read_slave("
			SELECT blog.blogid, blog.comments_visible as replycount, blog.title, blog.lastcomment, blog.lastcommenter, blog.postedby_userid, blog.postedby_username, blog.dateline,
				blog_text.blogtextid, blog_text.pagetext AS message,
				blog_user.title as blogtitle, blog_user.description as blogdescription,
				user.*
				" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "blog AS blog
			INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = blog.firstblogtextid)
			INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
			$catjoin
			" . (!empty($sql_join) ? implode("\r\n", $sql_join) : "") . "
			" . ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$useridsql
				$catidsql
				$datecutoffsql
				$globalignore
				AND " . implode("\r\n\tAND ", $sql_and) . "
			ORDER BY$ordersql
			LIMIT 0," . intval($this->config['blogentries_limit']) . "
		");

		while ($row = $this->registry->db->fetch_array($results))
		{
			//$row['url'] = fetch_seo_url('entry', $row);

			// trim the title after fetching the url
			//$row['title'] = fetch_trimmed_title($row['title'], $this->config['blogentries_titlemaxchars']);
			//still need to censor the title
			$row['title'] = fetch_censored_text($row['title']);
			
			$row['blogtitle'] = $row['blogtitle'] ? $row['blogtitle'] : $row['username'];

			$row['date'] = vbdate($this->registry->options['dateformat'], $row['dateline'], true);
			$row['time'] = vbdate($this->registry->options['timeformat'], $row['dateline']);

			$row['lastpostdate'] = vbdate($this->registry->options['dateformat'], $row['lastcomment'], true);
			$row['lastposttime'] = vbdate($this->registry->options['timeformat'], $row['lastcomment']);

			$row['message'] = $this->get_summary($row['message'], $this->config['blogentries_messagemaxchars']);

			// get avatar
			$this->fetch_avatarinfo($row);

			$array[$row['blogid']] = $row;
		}
		return $array;
	}

	public function getHTML($blogentries = false)
	{
		if (! $blogentries)
		{
			$blogentries = $this->getData();
		}

		if ($blogentries)
		{
			foreach ($blogentries as $key => $row)
			{
				$blogentries[$key]['url'] = fetch_seo_url('entry', $row);

				// trim the title after fetching the url
				$blogentries[$key]['title'] = fetch_trimmed_title($row['title'],
					$this->config['blogentries_titlemaxchars']);
			}

			$templater = vB_Template::create('block_blogentries');
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('blogentriestype', $this->config['blogentries_type']);
				$templater->register('blogentries', $blogentries);
			return $templater->render();
		}

	}

	public function getHash()
	{
		$context = new vB_Context('forumblock' , array('blockid' => $this->blockinfo['blockid'],
			'userid' => $this->userinfo['userid'],
			THIS_SCRIPT));
		return strval($context);
	}

	public static function construct_cat_chooser_options($topname = null)
	{
		global $vbulletin, $vbphrase;

		$selectoptions = array();

		if ($topname)
		{
			$selectoptions['-2'] = $topname;
		}

		require_once(DIR . '/includes/blog_functions_category.php');
		require_once(DIR . '/includes/functions_misc.php');
		fetch_ordered_categories(0);

		$selectoptions['-1'] = fetch_phrase('uncategorized', 'vbblogglobal');

		if (!empty($vbulletin->vbblog['categorycache']["0"]))
		{

			foreach ($vbulletin->vbblog['categorycache']["0"] AS $categoryid => $category)
			{
				$depthmark = str_pad('', 4 * $category['depth'], '- - ', STR_PAD_LEFT);

				$selectoptions[$categoryid] = $depthmark .  fetch_phrase('category' . $category['blogcategoryid'] . '_title', 'vbblogcat');
			}
		}

		return $selectoptions;
	}

}
