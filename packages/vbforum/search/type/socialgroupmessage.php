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
require_once (DIR . '/packages/vbforum/search/result/socialgroupmessage.php');
require_once (DIR . '/packages/vbforum/collection/socialgroupdiscussion.php');
require_once (DIR . '/packages/vbforum/collection/socialgroupmessage.php');
require_once (DIR . '/includes/functions_socialgroup.php');

/**
 * Search Type definition for Social Group Messages
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Type_SocialGroupMessage extends vB_Search_Type
{
	public function fetch_validated_list($user, $ids, $gids)
	{
		//put ids in buckets by group id.
		$map = array();
		foreach ($ids AS $i => $id)
		{
			$map[$gids[$i]][] = $id;
		}

		$gids_unique = array_unique($gids);
		$discussion_map = array();
		foreach (new vBForum_Collection_SocialGroupDiscussion($gids_unique) as $discussion)
		{
			if ($discussion->isValid() AND $discussion->canBe('searched', $user))
			{
				$discussion_map[$discussion->getId()] = $discussion;
			}
		}

		//determine rejected groups and put the list back together again.
		$rejected_groups = array_diff($gids_unique, array_keys($discussion_map));

		foreach ($rejected_groups as $rejected_gid)
		{
			unset($map[$rejected_gid]);
		}

		$searchids = array();
		foreach ($map as $gid => $value)
		{
			$searchids = array_merge($searchids, $value);
		}

		$list = array();

		if (count($searchids))
		{
			foreach (new vBForum_Collection_SocialGroupMessage($searchids) as $message)
			{

				$item = vBForum_Search_Result_SocialGroupMessage::create_from_object($message);

				if ($item->can_search($user))
				{
					$list[$message->getId()] = $item;
				}
				else
				{
					$list[$message->getId()] = false;
				}
			}
		}

		return array('list' => $list, 'groups_rejected' => $rejected_groups);
	}

	public function is_enabled()
	{
		global $vbulletin;
		return (($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']) AND
			$vbulletin->options['socnet_groups_msg_enabled']);
	}

	public function prepare_render($user, $results)
	{
		require_once(DIR . '/includes/functions_socialgroup.php');
		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('user', 'socialgroups'));

		foreach ($results AS $result)
		{
			$group = $result->get_message()->getDiscussion()->getSocialGroup();
			$privs = array();
			show_group_inlinemoderation($group->get_record(), $privs, false);

			//if we have a right for any item in the result set we have that right
			foreach ($privs AS $key => $priv)
			{
				$this->mod_rights[$key] = ($this->mod_rights[$key] OR (bool) $priv);
			}
		}
	}

	public function additional_header_text()
	{
		return '';
	}

	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_social_group_messages');
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $id
	 */
	public function create_item($id)
	{
		return vBForum_Search_Result_SocialGroupMessage::create($id);
	}


	public function can_group()
	{
		return true;
	}

	public function group_by_default()
	{
		return true;
	}

	public function get_inlinemod_options()
	{
		global $vbphrase, $show;
		$options = array();

		$mod_options = array();

		if ($this->mod_rights['delete'])
		{
			$mod_options[$vbphrase['delete_messages']] = 'inlinedelete';
		}

		if ($this->mod_rights['undelete'])
		{
			$mod_options[$vbphrase['undelete_messages']] = 'inlineundelete';
		}

		if ($this->mod_rights['approve'])
		{
			$mod_options[$vbphrase['approve_messages']] = 'inlineapprove';
			$mod_options[$vbphrase['unapprove_messages']] = 'inlineunapprove';
		}

		//if we have any mod options then we add the rest
		if ($mod_options)
		{
			$options[$vbphrase['option']] = $mod_options;
			$basic_options = array();
			$basic_options[$vbphrase['deselect_all_messages']] = 'clearmessage';
			$options ["____________________"] = $basic_options;
		}
		return $options;
	}


	public function get_inlinemod_type()
	{
		return 'gmessage';
	}

	public function get_inlinemod_action()
	{
		global $vbulletin;
		$base = '';
		if ($vbulletin->options['vbforum_url'])
		{
			$base = $vbulletin->options['vbforum_url'] . '/';
		}
		return $base . 'group_inlinemod.php?inline_discussion=0';
	}
// ###################### Start listUi ######################
/**
 * vBForum_Search_Type_SocialGroupMessage::listUi()
 *This function generates the search elements for a user to search for group messages
 *
	 *
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
		global $show;


		if (! isset($template_name))
		{
			$template_name = 'search_input_groupmessage';
		}

		if (! isset($contenttypeid))
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'SocialGroupMessage');
		}

		$messagegroupoptions = $this->showGroupOptions($prefs['messagegroupid']);
		if ($messagegroupoptions)
		{
			$show['search_messagegroupid'] = true;
		}

		$categoryoptions = $this->showCategoryOptions($prefs['categoryid']);
		if ($categoryoptions)
		{
			$show['search_categoryid'] = true;
		}

		$template = vB_Template::create($template_name);
		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('contenttypeid', $contenttypeid);
		$template->register('search_messagegroup_options', $messagegroupoptions);
		$template->register('search_category_options', $categoryoptions);

		$this->setPrefs($template, $prefs,  array(
			'select'=> array('searchdate', 'beforeafter', 'starteronly', 'messagegroupid', 'sortby'),
			'cb' => array('nocache', 'exactname'),
		 	'value' => array('query', 'searchuser'),
			'rb' => array('showposts') ) );

		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);
		if (isset($registers) and is_array($registers) )
		{
			foreach($registers as $key => $value)
			{
				$template->register($key, htmlspecialchars_uni($value));
			}
		}
		return $template->render();

		//clean up any changes to the show variable.
		unset($show['search_messagegroupid']);
	}

	// ###################### Start showGroupSelect ######################
	/**
	 * vBForum_Search_Type_SocialGroupMessage::showGroupSelect()
	 * This function generates the select scrolling list for groups,
	 *  which is used in search for groupmessages
	 *
	 * @param string $name : name for the select element
	 * @param string $style_string : something like "style=XXXX" or "class=XXX". Or empty
	 * @return $html: complete html for the select element
	 */
	private function showGroupOptions($selected)
	{
		global $vbulletin, $vbphrase;

		if (!is_array($selected))
		{
			$selected = array($selected);
		}

		//get rid of any invalid entries.
		$selected = array_filter($selected);

		//get groups to display.  This is any group that we are a member of
		//and any groups that are on our "tosearch" list
		$mygroups = fetch_socialgroups_mygroups(false);

		$searchgroups = array();
		foreach ($mygroups AS $group)
		{
			$searchgroups[$group['groupid']] = $group['name'];
		}

		//if we have groups selected that aren't in the members groups,
		//account for them.
		$extra = array();
		$extraids = array_diff($selected, array_keys($searchgroups));
		if (count($extraids))
		{
			$rst = $vbulletin->db->query_read("
				SELECT socialgroup.groupid, socialgroup.name
				FROM " . TABLE_PREFIX."socialgroup AS socialgroup
				WHERE socialgroup.groupid IN (" . implode(', ', $extraids) .")"
			);

			while ($row = $vbulletin->db->fetch_array($rst))
			{
				$extra[$row['groupid']] = $row['name'];
			}
		}

		if (count($searchgroups) > 0 OR count($extra) > 0)
		{
			$select = render_option_template($vbphrase['any_group'], '',
				!count($selected) ? 'selected="selected"' : '');

			//make the extra items display first -- prepending keyed items
			//to the searchgroups array proved surprisingly cumbersome
			foreach ($extra AS $id => $name)
			{
				$select .= render_option_template( htmlspecialchars_uni($name), $id,
					in_array($id, $selected) ? 'selected="selected"' : '');
			}

			foreach ($searchgroups AS $id => $name)
			{
				$select .= render_option_template( htmlspecialchars_uni($name), $id,
					in_array($id, $selected) ? 'selected="selected"' : '');
			}
			return $select;
		}
		else
		{
			return false;
		}
	}

	private function showCategoryOptions($selected)
	{
		global $vbulletin, $vbphrase;
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('search');

		$categories = fetch_socialgroup_category_options(false);
		if (! is_array($selected))
		{
			$selected = array($selected);
		}

		if (count($categories) > 0 )
		{
			$select = render_option_template($vbphrase['any_category'], '',
				!count($selected) ? 'selected="selected"' : '');

			foreach ($categories AS $categoryid => $category)
			{
				$select .= render_option_template( htmlspecialchars_uni($category['title']), $categoryid,
					in_array($categoryid, $selected) ? 'selected="selected"' : '');
			}
			return $select;

		}
		else
		{
			return false;
		}
	}


	public function add_advanced_search_filters($criteria, $registry)
	{
		global $vbulletin, $vbphrase;
		if ($registry->GPC_exists['messagegroupid'] AND count($registry->GPC['messagegroupid']) > 0)
		{
			$value = $registry->GPC['messagegroupid'];
			if (!is_array($value))
			{
				$value = array($value);
			}

			if (!(in_array(' ',$value) OR in_array('',$value)))
			{
				if ($rst = $vbulletin->db->query_read("
					SELECT socialgroup.name
					FROM " . TABLE_PREFIX."socialgroup AS socialgroup
					WHERE socialgroup.groupid IN (" . implode(', ', array_map('intval', $value)) .")")
				)
				{
					$groups = array();
					while ($row = $vbulletin->db->fetch_row($rst))
					{
						$groups[] = $row[0];
					}
					$criteria->add_display_strings($field, $vbphrase['social_groups'] . ': ' . implode(', ', $groups) );
				}
				$criteria->add_filter('messagegroupid', vB_Search_Core::OP_EQ, $value);
			}
		}

		if ($registry->GPC_exists['categoryid'] AND count($registry->GPC['categoryid']) > 0)
		{
			$value = $registry->GPC['categoryid'];
			if (!is_array($value))
			{
				$value = array($value);
			}

			if (!(in_array(' ',$value) OR in_array('',$value)))
			{
				if ($rst = $vbulletin->db->query_read("
					SELECT socialgroupcategory.title
					FROM " . TABLE_PREFIX."socialgroupcategory AS socialgroupcategory
					WHERE socialgroupcategory.socialgroupcategoryid IN (" . implode(', ', array_map('intval', $value)) .")")
				)
				{
					$categories = array();
					while($row = $vbulletin->db->fetch_row($rst))
					{
						$categories[] = $row[0];
					}

					$criteria->add_display_strings($field, (new vB_Phrase('socialgroups', 'social_group_category')) .
						': ' . implode(', ', $categories) );
				}
				$criteria->add_filter('sgcategoryid', vB_Search_Core::OP_EQ, $value);
			}
		}
	}

	public function get_db_query_info($fieldname)
	{
		$result['corejoin']['discussion'] = "INNER JOIN " . TABLE_PREFIX . "discussion AS discussion ON (
			searchcore.groupcontenttypeid = " . $this->get_groupcontenttypeid() . " AND
			searchcore.groupid = discussion.discussionid)";

		$result['groupjoin']['discussion'] = "INNER JOIN " . TABLE_PREFIX . "discussion AS discussion ON (
			searchgroup.contenttypeid = " . $this->get_groupcontenttypeid() . " AND
			searchcore.groupid = discussion.discussionid)";

		if ($fieldname == 'messagegroupid')
		{
			$result['table'] = 'discussion';
			$result['field'] = 'groupid';
		}

		else if ($fieldname == 'sgcategoryid')
		{
			$result['table'] = 'socialgroup';
			$result['join']['socialgroup'] = "INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (
				discussion.groupid = socialgroup.groupid)";
			$result['field'] = 'socialgroupcategoryid';
		}

		else if ($fieldname == 'socialgroup')
		{
			$result['table'] = 'socialgroup';
			$result['join']['socialgroup'] = "INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (
				discussion.groupid = socialgroup.groupid)";
			$result['field'] = 'name';
		}

		else
		{
			return false;
		}

		return $result;
	}

// ###################### Start additional_pref_defaults ######################
/**
 * vBForum_Search_Type_SocialGroupMessage::additional_pref_defaults()
 * Each search type has some responsibilities, one of which is to tell
 * what are its defaults
 *
 * @return array
 */
	public function additional_pref_defaults()
	{
		return array (

//			'messagedateline'    => -1,
			'searchdate'					=> 0,
			'beforeafter'					=> 'after',
			'starteronly'					=> 1,
			'searchuser'					=> '',
			'query'								=> '',
			'exactname'						=> 0,
			'nocache'							=> 0,
			'messagegroupid'      => 0,
			'sortby'			=> 'dateline'
		);
	}
	private $messagegroupid;

	protected $package = "vBForum";
	protected $class = "SocialGroupMessage";
	protected $group_package = "vBForum";
	protected $group_class = "SocialGroupDiscussion";
	protected $type_globals = array (
		'nocache'            => TYPE_UINT,
		'messagegroupid'     => TYPE_ARRAY_UINT,
		'categoryid'         => TYPE_ARRAY_UINT,
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 30698 $
|| ####################################################################
\*======================================================================*/