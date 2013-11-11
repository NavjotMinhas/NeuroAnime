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

class vB_BlockType_Sgdiscussions extends vB_BlockType
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
		'sgdiscussions_type' => array(
			'defaultvalue' => 0,
			'optioncode'   => 'radio:piped
0|new_started
1|new_replied
2|most_replied',
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'sgdiscussions_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'sgdiscussions_titlemaxchars' => array(
			'defaultvalue' => 35,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
		'sgdiscussions_messagemaxchars' => array(
			'defaultvalue' => 200,
			'displayorder' => 3,
			'datatype'     => 'integer'
		),
		'sgdiscussions_catids' => array(
			'defaultvalue' => -1,
			'optioncode'   => 'selectmulti:eval
$options = vB_BlockType_sgdiscussions::construct_sgcat_chooser_options(fetch_phrase("all_categories", "vbblock"));',
			'displayorder' => 4,
			'datatype'     => 'arrayinteger'
		),
		'sgdiscussions_groupids' => array(
			'defaultvalue' => '',
			'displayorder' => 5,
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
		//the user can't see socialgroups, abort now.
		if (
			!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups']) OR
			!($this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['canviewgroups'])
		)
		{
			return '';
		}

		if ($this->config['sgdiscussions_groupids'])
		{
			$groupids = explode(',', $this->config['sgdiscussions_groupids']);
			$groupidsql = '';
			if (intval($groupids[0]))
			{
				$groupidsql = " AND socialgroup.groupid IN (-1";
				foreach ((array)$groupids as $groupid)
				{
					$groupidsql .= "," . intval($groupid);
				}
				$groupidsql .= ")";
			}
		}
		if ($this->config['sgdiscussions_catids'])
		{
			$catidsql = '';
			if (!in_array(-1, $this->config['sgdiscussions_catids']))
			{
				$catidsql = " AND socialgroup.socialgroupcategoryid IN (-1";
				foreach ($this->config['sgdiscussions_catids'] AS $catid)
				{
					$catidsql .= ",$catid";
				}
				$catidsql .= ")";
			}
		}

		$datecut = TIMENOW - ($this->config['datecut'] * 86400);

		switch (intval($this->config['sgdiscussions_type']))
		{
			case 0:
				$ordersql = " groupmessage.dateline DESC";
				$datecutoffsql = " AND groupmessage.dateline > $datecut";
				break;
			case 1:
				$ordersql = " discussion.lastpost DESC";
				$datecutoffsql = " AND discussion.lastpost > $datecut";
				break;
			case 2:
				$ordersql = " discussion.visible DESC";
				$datecutoffsql = " AND groupmessage.dateline > $datecut";
				break;
		}


		// remove threads from users on the global ignore list if user is not a moderator
		$globalignore = '';
		if (trim($this->registry->options['globalignore']) != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			if ($Coventry = fetch_coventry('string'))
			{
				$globalignore = "AND groupmessage.postuserid NOT IN ($Coventry) ";
			}
		}

		require_once(DIR . '/includes/functions_socialgroup.php');
		$canviewprivate = (
			//don't allow groups to be hidden from non members
			!$this->registry->options['sg_allow_join_to_view'] OR

			//can see hidden groups
			can_moderate(0, 'canmoderategroupmessages') OR
			can_moderate(0, 'canremovegroupmessages') OR
			can_moderate(0, 'candeletegroupmessages')	OR
			fetch_socialgroup_perm('canalwayspostmessage') OR
			fetch_socialgroup_perm('canalwascreatediscussion')
		);

		$membertypejoin = "";
		$memberfilter = "";
		if (!$canviewprivate)
		{
			$memberfilter = "AND ( !(socialgroup.options & " . $this->registry->bf_misc_socialgroupoptions["join_to_view"] . ")";
			if ($this->registry->userinfo['userid'])
			{
				$membertypejoin = "LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
					(socialgroupmember.userid = " . $this->registry->userinfo['userid'] . " AND socialgroupmember.groupid = socialgroup.groupid)";

				$memberfilter .= " OR socialgroupmember.type = 'member' ";
			}
			$memberfilter.= ")";
		}

		// VBIV-4609 changed the user.* to come first, to take the discussion.lastpost instead of user.lastpost since both have lastpost.
		$gms = $this->registry->db->query_read_slave("
			SELECT user.*, discussion.discussionid, discussion.groupid, discussion.lastpostid, discussion.lastpost,
				discussion.lastposter, discussion.lastposterid, discussion.visible,
				groupmessage.gmid, groupmessage.postuserid, groupmessage.postusername, groupmessage.dateline,
				groupmessage.title, groupmessage.pagetext as message,
				socialgroup.name as groupname, socialgroup.description as groupdescription
				" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar,
					customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "discussion AS discussion
			INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON(discussion.groupid = socialgroup.groupid)
			INNER JOIN " . TABLE_PREFIX . "groupmessage AS groupmessage ON (discussion.firstpostid = groupmessage.gmid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (groupmessage.postuserid = user.userid)
			" . ($this->registry->options['avatarenabled'] ?
			"LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid)
			LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") .
		"
		$membertypejoin
		WHERE 1=1
			$groupidsql
			$catidsql
			$memberfilter
			AND discussion.visible > 0
			AND groupmessage.state = 'visible'
			$datecutoffsql
			$globalignore
		ORDER BY$ordersql
		LIMIT 0," . intval($this->config['sgdiscussions_limit']) . "
		");

		$gmarray = array();
		while ($gm = $this->registry->db->fetch_array($gms))
		{
			//trim and censor the title
			$gm['title'] = fetch_trimmed_title(fetch_censored_text($gm['title']), $this->config['sgdiscussions_titlemaxchars']);
			$gm['groupname'] = htmlspecialchars_uni($gm['groupname']);
			$gm['groupdescription'] = htmlspecialchars_uni($gm['groupdescription']);
			$gm['date'] = vbdate($this->registry->options['dateformat'], $gm['dateline'], true);
			$gm['time'] = vbdate($this->registry->options['timeformat'], $gm['dateline']);

			$gm['lastpostdate'] = vbdate($this->registry->options['dateformat'], $gm['lastpost'], true);
			$gm['lastposttime'] = vbdate($this->registry->options['timeformat'], $gm['lastpost']);

			$gm['message'] = $this->get_summary($gm['message'], $this->config['sgdiscussions_messagemaxchars']);

			// we need to count replies so
			$gm['replycount'] = $gm['visible'] - 1;

			// get avatar
			$this->fetch_avatarinfo($gm);

			$gmarray[$gm['discussionid']] = $gm;
		}

		return($gmarray);
	}

	public function getHTML($gmarray = false)
	{
		if (!$gmarray)
		{
			$gmarray = $this->getData();
		}

		if ($gmarray AND !empty($gmarray))
		{
			foreach ($gmarray as $key => $gm)
			{
				$gmarray[$key]['url'] = fetch_seo_url('groupmessage', $gm);
				$gm[$key]['groupurl'] = fetch_seo_url('group', $gm);
			}

			$templater = vB_Template::create('block_sgdiscussions');
			$templater->register('blockinfo', $this->blockinfo);
			$templater->register('discussionstype', $this->config['sgdiscussions_type']);
			$templater->register('discussions', $gmarray);

			return $templater->render();
		}
	}

	public function getHash()
	{
		//the permissions for SG's are based on the userid at least in some circumstances.
		//(if we allow SG's to not be viewable by non members).
		//we might be able to be smarter and cache more broadly when that is off.  We can
		//also simply exclude private SG's from the block display entirely.
		$context = new vB_Context('forumblock' ,
			array (
				'blockid' => $this->blockinfo['blockid'],
				'userid' => $this->userinfo['userid'],
				THIS_SCRIPT
			)
		);

		return strval($context);
	}

	public static function construct_sgcat_chooser_options($topname = null)
	{
		$selectoptions = array();

		if ($topname)
		{
			$selectoptions['-1'] = $topname;
		}

		require_once(DIR . '/includes/functions_socialgroup.php');
		// get category options
		$categories = fetch_socialgroup_category_options();
		$categoryoptions = '';

		foreach ($categories as $categoryid => $category)
		{
			$selectoptions[$categoryid] = $category['title'];
		}

		return $selectoptions;
	}

}
