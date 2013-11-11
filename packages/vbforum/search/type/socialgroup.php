<?php if (!defined('VB_ENTRY')) die('Access denied.');

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

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 30698 $
 * @since $Date: 2009-05-06 16:24:34 -0700 (Wed, 06 May 2009) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/type.php');
require_once (DIR . '/packages/vbforum/search/result/socialgroup.php');
require_once (DIR."/includes/functions_socialgroup.php");

/**
 */
class vBForum_Search_Type_SocialGroup extends vB_Search_Type
{
	public function fetch_validated_list($user, $ids, $gids)
	{
		$list = array_fill_keys($ids, false);
		$items = vBForum_Search_Result_SocialGroup::create_array($ids);
		foreach ($items as $id => $item)
		{
			if ($item->can_search($user))
			{
				$list[$id] = $item;
			}
		}
		return array('list' => $list, 'groups_rejected' => array());
	}

	public function create_item($id)
	{
		return vBForum_Search_Result_SocialGroup::create($id);
	}

	/**
	 * You can create from an array also
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_array($ids)
	{
		return vBForum_Search_Result_SocialGroup::create_array($ids);
	}

	public function is_enabled()
	{
		global $vbulletin;
		return ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']);
	}

	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_social_groups');
	}

	public function cansearch()
	{
		return true;
	}

	// ###################### Start listUi ######################
	/**
	 * vBForum_Search_Type_SocialGroup::listUi()
	 *This function generates the search elements for a user to search for groups
	 * @param mixed $prefs : the array of user preferences
	 * @param mixed $contenttypeid : the content type for which we are going to
	 *    search
	 * @param array registers : any additional elements to be registered. These are
	 * 	just passed to the template
	 * @param string $template_name : name of the template to use for display. We have
	 *		a default template.
	 * @param boolean $groupable : a flag to tell whether the interface should display
	 * 	grouping option(s).
	 * @return $html: complete html for the search elements
	 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null,
		$template_name = null)
	{
		global $vbulletin, $vbphrase;


		if (! isset($template_name))
		{
			$template_name = 'search_input_socialgroup';
		}

		if (! isset($contenttypeid))
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'SocialGroup');
		}

		$template = vB_Template::create($template_name);
		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('contenttypeid', $contenttypeid);
		$template->register('show', $show);

		// Get categories
		$categories = fetch_socialgroup_category_options();
		$category_options = '<option value="">' . $vbphrase['any_category'] . '</option>';

		if (! isset($prefs['cat']))
		{
			$prefs['cat'] = array();
		}
		foreach ($categories AS $key => $name)
		{
			$category_options .= "<option value=\"$key\""
				. (in_array($key, $prefs['cat'])  ? ' selected="selected" ' : '' )
				. " >" . $name['title'] . "</option>\n";
		}
		$template->register('category_options', $category_options);

		$this->setPrefs($template, $prefs,  array(
			'select'=> array('titleonly', 'messageless', 'discussionless',
				'pictureless', 'memberless',
				'group_filter_date_lteq_month', 'group_filter_date_gteq_month', 'sortby'),
			'cb' => array('nocache'),
		 	'value' => array('query', 'messagelimit', 'discussionlimit',
				'picturelimit', 'memberlimit', 'group_filter_date_lteq_day',
				'group_filter_date_lteq_year', 'group_filter_date_gteq_day',
		 		 'group_filter_date_gteq_year', 'memberlimit')) );
		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);
		if (isset($registers) and is_array($registers) )
		{
			foreach($registers as $key => $value)
			{
				$template->register($key, htmlspecialchars_uni($value));
			}
		}
		return $template->render();
	}

	public function add_advanced_search_filters($criteria, $registry)
	{
		//print "REGISTRY";print_r($this->registry);
		//die;
		if ($registry->GPC['memberlimit'])
		{
			$criteria->add_display_strings('sgmemberlimit',
				vB_Search_Searchtools::getCompareString($registry->GPC['memberless'])
				. $registry->GPC['memberlimit'] . ' ' . $vbphrase['members']);
			$op = $registry->GPC['memberless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('sgmemberlimit', $op, $registry->GPC['memberlimit'], true);
		}

		if ($registry->GPC['messagelimit'])
		{
			$criteria->add_display_strings('sgmessagelimit',
				vB_Search_Searchtools::getCompareString($registry->GPC['messageless'])
				. $registry->GPC['messagelimit'] . ' ' . $vbphrase['messages']);
			$op = $registry->GPC['messageless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('sgmessagelimit', $op, $registry->GPC['messagelimit'], true);
		}

		if ($registry->GPC['discussionlimit'])
		{
			$criteria->add_display_strings('sgdiscussionlimit',
				vB_Search_Searchtools::getCompareString($registry->GPC['discussionless'])
				. $registry->GPC['discussionlimit'] . ' ' . $vbphrase['discussions']);
			$op = $registry->GPC['discussionless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('sgdiscussionlimit', $op, $registry->GPC['discussionlimit'], true);
		}

		if ($registry->GPC['picturelimit'])
		{
			$criteria->add_display_strings('sgpicturelimit',
				vB_Search_Searchtools::getCompareString($registry->GPC['pictureless'])
				. $registry->GPC['picturelimit'] . ' ' . $vbphrase['pictures']);
			$op = $registry->GPC['pictureless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('sgpicturelimit', $op, $registry->GPC['picturelimit'], true);
		}

		if (count($registry->GPC['cat']) > 0)
		{
			$value = $registry->GPC['cat'];
			if (! is_array($value))
			{
				$value = array($value);
			}

			if (!(in_array(' ', $value) OR in_array('',$value)))
			{
				$criteria->add_filter('sgcategory', vB_Search_Core::OP_EQ, $value);
				$criteria->add_display_strings('sgcategory',
					vB_Search_Searchtools::getDisplayString('socialgroupcategory', $vbphrase['categories'],
					'title', 'socialgroupcategoryid', $value, vB_Search_Core::OP_EQ, true ));
			}
		}

		$year = $registry->GPC["group_filter_date_lteq_year"];
		$month = $registry->GPC["group_filter_date_lteq_month"];
		$day = $registry->GPC["group_filter_date_lteq_day"];

		$ltdate = $this->get_date($year, $month, $day);
		if ($ltdate)
		{
			//this is actually a core field with an odd UI, we don't need to handle it in
			//the db_fields function.
			$criteria->add_filter('dateline', vB_Search_Core::OP_LT, $ltdate, true);
			$criteria->add_display_strings('sgdate_before', $vbphrase['before']
				. ' ' . date($vbulletin->options['dateformat'], $ltdate));
		}

		$year = $registry->GPC["group_filter_date_gteq_year"];
		$month = $registry->GPC["group_filter_date_gteq_month"];
		$day = $registry->GPC["group_filter_date_gteq_day"];

		$gtdate = $this->get_date($year, $month, $day);
		if ($gtdate)
		{
			$criteria->add_filter('dateline', vB_Search_Core::OP_GT, $gtdate, true);
			$criteria->add_display_strings('sgdate_after', $vbphrase['after']
				. ' ' . date($vbulletin->options['dateformat'], $gtdate));
		}
	}

	public function get_db_query_info($fieldname)
	{
		$result['corejoin']['socialgroup'] = "INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (
				searchcore.contenttypeid = " . $this->get_contenttypeid() . " AND searchcore.primaryid = socialgroup.groupid)";

		$result['groupjoin']['socialgroup'] = "INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (
				searchgroup.contenttypeid = " . $this->get_contenttypeid() . " AND searchgroup.groupid = socialgroup.groupid)";

		$result['table'] = 'socialgroup';

		if ($fieldname == 'sgmemberlimit')
		{
			$result['field'] = 'members';
		}
		else if ($fieldname == 'sgdiscussionlimit')
		{
			$result['field'] = 'discussions';
		}
		else if ($fieldname == 'sgpicturelimit')
		{
			$result['field'] = 'picturecount';
		}
		else if ($fieldname == 'sgcategory')
		{
			$result['field'] = 'socialgroupcategoryid';
		}
		else if ($fieldname == 'sgmessagelimit')
		{
			$result['field'] = 'visible';
		}
		else
		{
			return false;
		}

		return $result;
	}

	/**
	 * vBForum_Search_Type_SocialGroup::set_display_date()
	 * This function sets the display date if search information is given
	 *
	 * @return
	 */


	private function get_date($year, $month, $day)
	{
		if (!$year OR !$month)
		{
			return false;
		}

		if ($month > 12)
		{
			$month = 12;
		}

		if ($month < 1)
		{
			$month = 1;
		}

		if (!$day)
		{
			$day = 1;
		}

		return mktime(0,0,0,$month, $day, $year);
	}



// ###################### Start additional_pref_defaults ######################
/**
* vBForum_Search_Type_SocialGroup::additional_pref_defaults()
* Each search type has some responsibilities, one of which is to tell
* what are its defaults
*
* @return array
*/
	public function additional_pref_defaults()
	{
		return array(
			'query'         => '',
			'titleonly'     => 0,
			'nocache'    	 => '',
			'messageless'   => 0,
			'messagelimit'  => '',
			'discussionless'    => 0,
			'discussionlimit'   => '',
			'pictureless'    => 0,
			'picturelimit'   => '',
			'group_filter_date_gteq_month' => 0,
			'group_filter_date_gteq_day' => '1',
			'group_filter_date_gteq_year' =>'',
			'group_filter_date_lteq_month' => 0,
			'group_filter_date_lteq_day' => '1',
			'group_filter_date_lteq_year' =>'',
			'memberless'    => 0,
			'memberlimit'   => '',
			'sortby'		=> 'dateline');
	}


	protected $package = "vBForum";
	protected $class = "SocialGroup";
	protected $type_globals = array (
		'cat'			  => TYPE_ARRAY,
		'memberless'     => TYPE_UINT,
		'memberlimit'    => TYPE_UINT,
		'messageless'    => TYPE_UINT,
		'messagelimit'   => TYPE_UINT,
		'pictureless'    => TYPE_UINT,
		'picturelimit'   => TYPE_UINT,
		'discussionless' => TYPE_UINT,
		'discussionlimit' => TYPE_UINT,

		'group_filter_date_gteq_year'  => TYPE_NOHTML,
		'group_filter_date_gteq_month' => TYPE_UINT,
		'group_filter_date_gteq_day'   => TYPE_NOHTML,
		'group_filter_date_lteq_year'  => TYPE_NOHTML,
		'group_filter_date_lteq_month' => TYPE_UINT,
		'group_filter_date_lteq_day'   => TYPE_NOHTML
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 30698 $
|| ####################################################################
\*======================================================================*/
