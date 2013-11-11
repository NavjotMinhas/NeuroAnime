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

class vB_BlockType_Cmsarticles extends vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = 'vbcms';

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
		'cmsarticles_type' => array(
			'defaultvalue' => 0,
			'optioncode'   => 'radio:piped
0|new_started
1|new_replied
2|most_replied
3|most_viewed',
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'cmsarticles_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
		'cmsarticles_titlemaxchars' => array(
			'defaultvalue' => 35,
			'displayorder' => 3,
			'datatype'     => 'integer'
		),
		'cmsarticles_messagemaxchars' => array(
			'defaultvalue' => 200,
			'displayorder' => 4,
			'datatype'     => 'integer'
		),
		'cmsarticles_catids' => array(
			'defaultvalue' => -1,
			'optioncode'   => 'selectmulti:eval
$options = vB_BlockType_cmsarticles::construct_cat_chooser_options(fetch_phrase("all_categories", "vbblock"));',
			'displayorder' => 5,
			'datatype'     => 'arrayinteger'
		),
		'cmsarticles_sectionids' => array(
			'defaultvalue' => -1,
			'optioncode'   => 'selectmulti:eval
$options = vB_BlockType_cmsarticles::construct_section_chooser_options(fetch_phrase("all_sections", "vbblock"));',
			'displayorder' => 6,
			'datatype'     => 'arrayinteger'
		),
		'datecut' => array(
			'defaultvalue' => 30,
			'displayorder' => 7,
			'datatype'     => 'integer'
		)
	);

	public function getData()
	{
		$catidsql = '';
		$catjoin = '';
		if ($this->config['cmsarticles_catids'])
		{
			if (!in_array(-1, $this->config['cmsarticles_catids']))
			{
				$catjoin = "LEFT JOIN " . TABLE_PREFIX . "cms_nodecategory AS cms_nodecategory ON (cms_node.nodeid = cms_nodecategory.nodeid)";
				$catidsql = " AND cms_nodecategory.categoryid IN (-1";
				foreach ($this->config['cmsarticles_catids'] as $groupid)
				{
					$catidsql .= "," . intval($groupid);
				}
				$catidsql .= ")";
			}
		}

		$sectionidsql = '';
		if ($this->config['cmsarticles_sectionids'])
		{

			if (!in_array(-1, $this->config['cmsarticles_sectionids']))
			{
				$sectionidsql = " AND cms_node.parentnode IN (-1";
				foreach ($this->config['cmsarticles_sectionids'] AS $catid)
				{
					$sectionidsql .= ",$catid";
				}
				$sectionidsql .= ")";
			}
		}

		$datecut = TIMENOW - ($this->config['datecut'] * 86400);

		switch (intval($this->config['cmsarticles_type']))
		{
			case 0:
				$ordersql = " cms_node.publishdate DESC";
				$datecutoffsql = " AND cms_node.publishdate > $datecut";
				break;
			case 1:
				$ordersql = " thread.lastpost DESC";
				$datecutoffsql = " AND thread.lastpost > $datecut";
				break;
			case 2:
				$ordersql = " thread.replycount DESC";
				$datecutoffsql = " AND cms_node.publishdate > $datecut";
				break;
			case 3:
				$ordersql = " cms_nodeinfo.viewcount DESC";
				$datecutoffsql = " AND cms_node.publishdate > $datecut";
				break;
		}

		$results = $this->registry->db->query_read_slave("
			SELECT cms_article.contentid, cms_article.pagetext as message,
				cms_node.nodeid, cms_node.url, cms_node.publishdate,
				cms_nodeinfo.title,
				thread.replycount, thread.lastpost as lastpostarticle, thread.lastposter, thread.lastpostid, thread.lastposterid,
				user.*
				" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "cms_article AS cms_article
			INNER JOIN " . TABLE_PREFIX . "cms_node AS cms_node ON (cms_node.contentid = cms_article.contentid)
			INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS cms_nodeinfo ON (cms_nodeinfo.nodeid = cms_node.nodeid)
			$catjoin
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (cms_nodeinfo.associatedthreadid = thread.threadid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (cms_node.userid = user.userid)
			" . ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$sectionidsql
				$catidsql
				AND cms_node.setpublish = 1
				AND cms_node.publishdate <= " . TIMENOW . "
				AND cms_node.publicpreview = 1
				$datecutoffsql
			ORDER BY$ordersql
			LIMIT 0," . intval($this->config['cmsarticles_limit']) . "
		");

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();

		//$route = vB_Route::create('vBCms_Route_Content');
		while ($row = $this->registry->db->fetch_array($results))
		{
		//	$route->node = $row['nodeid'] . (empty($row['url']) ? '' : '-' . $row['url']);
		//	$row['url'] =  $route->getCurrentURL();

			// trim the title after fetching the url and censor it
			$row['title'] = htmlspecialchars_uni(fetch_trimmed_title(fetch_censored_text($row['title']), $this->config['cmsarticles_titlemaxchars']));
			$row['date'] = vbdate($this->registry->options['dateformat'], $row['publishdate'], true);
			$row['time'] = vbdate($this->registry->options['timeformat'], $row['publishdate']);

			$row['lastpostdate'] = vbdate($this->registry->options['dateformat'], $row['lastpostarticle'], true);
			$row['lastposttime'] = vbdate($this->registry->options['timeformat'], $row['lastpostarticle']);

			$row['message'] = $this->get_summary($row['message'], $this->config['cmsarticles_messagemaxchars']);

			// get avatar
			$this->fetch_avatarinfo($row);

			$array[$row['nodeid']] = $row;
		}
		return $array;
	}

	public function getHTML($articles = false)
	{
		if (! $articles)
		{
			$articles = $this->getData();
		}

		if ($articles)
		{
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			vB_Bootstrap_Framework::init();

			$route = vB_Route::create('vBCms_Route_Content');
			foreach ($articles as $key => $row)
			{
				$route->node = $row['nodeid'] . (empty($row['url']) ? '' : '-' . $row['url']);
				$articles[$key]['url'] =  $route->getCurrentURL();
			}

			$templater = vB_Template::create('block_cmsarticles');
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('articlestype', $this->config['cmsarticles_type']);
				$templater->register('articles', $articles);
			return $templater->render();
		}

	}

	public static function construct_cat_chooser_options($topname = null)
	{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/packages/vbcms/contentmanager.php');
		vB_Bootstrap_Framework::init();

		$selectoptions = array();

		if ($topname)
		{
			$selectoptions['-1'] = $topname;
		}

		// get category options
		$categories = vBCms_ContentManager::getCategories();

		foreach ($categories['results'] as $category)
		{
			$selectoptions[$category['categoryid']] = $category['parent_title'] . '>' . $category['category'];
		}

		return $selectoptions;
	}

	public static function construct_section_chooser_options($topname = null)
	{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/packages/vbcms/contentmanager.php');
		vB_Bootstrap_Framework::init();

		$selectoptions = array();

		if ($topname)
		{
			$selectoptions['-1'] = $topname;
		}

		// get category options
		$nodelist = vBCms_ContentManager::getNodes(1,
				array('contenttypeid' => 'node2.contenttypeid = ' . vb_Types::instance()->getContentTypeID("vBCms_Section")));


		foreach ($nodelist as $section)
		{
			$selectoptions[$section['nodeid']] = str_replace('&gt;', '>', $section['parent']) . $section['leaf'];
		}

		return $selectoptions;
	}

}