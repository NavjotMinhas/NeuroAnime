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
 * @package vBForum
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 30635 $
 * @since $Date: 2009-05-04 17:00:33 -0700 (Mon, 04 May 2009) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/type.php');
require_once (DIR . '/vb/legacy/post.php');
require_once (DIR . '/packages/vbforum/search/result/post.php');
require_once (DIR . '/packages/vbforum/search/result/thread.php');

/**
* There is a type file for each search type. This is the one for posts
*
* @package vBulletin
* @subpackage Search
*/
class vBForum_Search_Type_Post extends vB_Search_Type
{
// ###################### Start fetch_validated_list ######################
/**
* vBForum_Search_Type_Post::fetch_validated_list()
* When displaying results we get passed a list of id's. This
* function determines which are viewable by the user.
*
* @param object $user
* @param array $ids : the postid's returned from a search
* @param array $gids : the group id's for the posts
* @return array (array of viewable posts, array of rejected posts)
*/
	public function fetch_validated_list($user, $ids, $gids)
	{
		require_once(DIR . '/includes/functions_forumlist.php');
		cache_moderators_once();

		global $vbulletin;
		$map = array();
		foreach ($ids AS $i => $id)
		{
			$map[$gids[$i]][] = $id;
		}

		$threads = vB_Legacy_Thread::create_array(array_unique($gids));
		$rejected_groups = array();
		foreach ($threads as $thread)
		{
			if (!$thread->can_search($user))
			{
				$rejected_groups[] = $thread->get_field('threadid');
				unset($map[$thread->get_field('threadid')]);
			}
		}

		$searchids = array();
		foreach ($map as $gid => $value)
		{
			$searchids = array_merge($searchids, $value);
		}

		$list = array_fill_keys($ids, false);
		if (count($searchids))
		{
			$posts = vB_Legacy_Post::create_array($searchids, $threads);
			foreach ($posts AS $id => $post)
			{
				$item = vBForum_Search_Result_Post::create_from_object($post);
				if ($item->can_search($user))
				{
					$list[$id] = $item;
				}
			}
		}
		return array('list' => $list, 'groups_rejected' => $rejected_groups);
	}

// ###################### Start prepare_render ######################
/**
* vBForum_Search_Type_Post::prepare_render()
* This function does setup preparatory to  rendering the search resuls.
* In this case that means checking moderation options for this user.
*
* @param object $user
* @param object $results
* @return nothing
*/
	public function prepare_render($user, $results)
	{
		foreach ($results AS $result)
		{
			$forumid = $result->get_post()->get_thread()->get_field('forumid');

			$this->mod_rights['managethread'] = ($this->mod_rights['managethread'] OR
				$user->canModerateForum($item['forumid'], 'canmanagethreads'));

			$this->mod_rights['managepost'] = ($this->mod_rights['managepost'] OR
				($user->canModerateForum($item['forumid'], 'candeleteposts') OR
					$user->canModerateForum($item['forumid'], 'canremoveposts')));

			$this->mod_rights['approvepost'] = ($this->mod_rights['approvepost'] OR
				$user->canModerateForum($item['forumid'], 'canmoderateposts'));

			$this->mod_rights['approveattachment'] = ($this->mod_rights['approveattachment'] OR
				$user->canModerateForum($item['forumid'], 'canmoderateattachments'));
		}
	}

// ###################### Start get_display_name ######################
/**
* vBForum_Search_Type_Post::get_display_name()
* Each search type has some responsibilities, one of which is to give
* its display name.
*
* @return string
*/
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_posts');
	}

// ###################### Start create_item ######################
/**
* vBForum_Search_Type_Post::create_item()
* This is how the type objects are created
*
* @param integer $id
* @return vBForum_Search_Type_Post object
*/
	public function create_item($id)
	{
		return vBForum_Search_Result_Post::create($id);
	}

// ###################### Start additional_pref_defaults ######################
/**
* Each search type has some responsibilities, one of which is to tell
* what the default search preferences are.
* vBForum_Search_Type_Post::additional_pref_defaults()
*
* @return array
*/
	public function additional_pref_defaults()
	{
		return array(
			'childforums' => 1,
			'replyless'   => 0,
			'replylimit'  => 0,
			'query'       => '',
			'forumchoice' => 0,
			'prefixchoice'=> 0,
			'titleonly'   => 0,
			'nocache'     => '',
			'searchuser'  => '',
			'starteronly' => 0,
			'showposts'   => 0,
			'exactname'   => '',
			'searchdate'  => 0,
			'beforeafter' => 0,
			'sortby'      => 'dateline',
			'order' 	     => 'descending',
			'tag'         => '',
			'showposts'	  => 0);
	}

// ###################### Start can_group ######################
/**
* vBForum_Search_Type_Post::can_group()
* Each search type has some responsibilities, one of which is to tell
* whether it is groupable- Forums, for example are not, but posts are.
* They are naturally grouped by thread.
*
* @return
*/
	public function can_group()
	{
		return true;
	}

// ###################### Start group_by_default ######################
/**
* vBForum_Search_Type_Post::group_by_default()
* Each search type has some responsibilities, one of which is to tell
* whether it is grouped by default
*
* @return
*/
	public function group_by_default()
	{
		return true;
	}

// ###################### Start listUi ######################
/**
 * vBForum_Search_Type_Post::listUi()
 *This function generates the search elements for the user to search for posts
 * @param mixed $prefs : the array of user preferences / propogated form values.
 * @param mixed $contenttypeid : the content type for which we are going to
 *    search
 * @param array registers : any additional elements to be registered. These are
 * 	just passed to the template
 * @param string $template_name : name of the template to use for display. We have
 *		a default template.
 * @return $html: complete html for the search elements
 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null,
		$template_name = null)
	{
		global $vbulletin, $vbphrase;


		if (!isset($template_name))
		{
			$template_name = 'search_input_post';
		}

		if (!isset($contenttypeid))
		{
			$contenttypeid = $this->get_contenttypeid();
		}

		$template = vB_Template::create($template_name);

		if ($prefs['searchthreadid'])
		{
			if ($thread = vB_Legacy_Thread::create_from_id($prefs['searchthreadid']))
			{
				$template->register('thread_title', $thread->get_field('title'));
			}
		}

		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('contenttypeid', $contenttypeid);
		$template->register('search_forum_options', $this->showForumOptions($prefs['forumchoice']));

		$prefix_selected = array (
			'anythread' => (empty($prefs['prefixchoice']) OR in_array('', $prefs['prefixchoice']) ) ?
				'selected="selected"' : '',
			'anyprefix' => ($prefs['prefixchoice'] AND in_array('-2', $prefs['prefixchoice'])) ?
				'selected="selected"' : '',
			'none' => ($prefs['prefixchoice'] AND in_array('-1', $prefs['prefixchoice'])) ?
				'selected="selected"' : ''
		);

		$template->register('prefix_selected', $prefix_selected);
		$template->register('search_prefix_options', vB_Search_Searchtools::getPrefixOptions($prefs['prefixchoice'], false));

		$this->setPrefs($template, $prefs,
			array(
				'select'=> array('sortby', 'order', 'forumchoice',
					'prefixchoice', 'titleonly', 'starteronly', 'searchdate',
					'beforeafter'),
				'cb' => array('childforums', 'exactname', 'nocache'),
				'value' => array('query', 'searchuser', 'tag', 'searchthreadid'),
				'rb' => array('showposts')
		 	)
		);

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
// ###################### Start showForumSelect ######################
/**
 * vBForum_Search_Type_Post::showForumSelect()
 * This function generates the select scrolling list for forums, use in search for posts
 */
	private function showForumOptions($forumchoice=array())
	{
		global $vbulletin, $vbphrase, $show;

		//this will fill out $searchforumids as well as set the depth param in $vbulletin->forumcache
		global $searchforumids;
		fetch_search_forumids_array();

		$options = array();
		$non_searchable_forums = array();
		foreach ($searchforumids AS $forumid)
		{
			$forum = & $vbulletin->forumcache["$forumid"];
			if (trim($forum['link']))
			{
				continue;
			}

			//note that this code relies on the fact that searchforumids is ordered so that
			//parents appear before their childern (actually the what the display works depends
			//heavily on that fact so its not much of an assumption here).

			//if the forum isn't searchable, then don't show it.
			if(!($forum['options'] & $vbulletin->bf_misc_forumoptions['indexposts']))
			{
				$non_searchable_forums[$forumid] = $forumid;
			}

			//unless it has children that are searchable.
			else
			{
				unset($non_searchable_forums[$forum['parentid']]);
			}

			$optionvalue = $forumid;
			$optiontitle = "$forum[depthmark] $forum[title_clean]";

			if (
				!($vbulletin->userinfo['forumpermissions'][$forumid] & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
			)
			{
				$optiontitle .= '*';
				$show['cantsearchposts'] = true;
			}

			$optionselected = '';

			if ($forumchoice AND in_array($forumid, $forumchoice))
			{
				$optionselected = 'selected="selected"';
				$haveforum = true;
			}

			$options[$forumid] = render_option_template($optiontitle, $forumid, $optionselected,
				'd' . min(4, $forum['depth']));
		}

		foreach ($non_searchable_forums AS $forumid)
		{
			unset($options[$forumid]);
		}

		$options = implode("", $options);
		$options =
			render_option_template($vbphrase['search_all_open_forums'], '',
				$haveforum ? '' : 'selected="selected"') .
			render_option_template($vbphrase['search_subscribed_forums'], 'subscribed') .
			$options;
		return $options;
	}


// ###################### Start get_inlinemod_options ######################
/**
* vBForum_Search_Type_Post::get_inlinemod_options()
* This tells inline moderation what options are applicable to posts
*
* @return array of options
*/
	public function get_inlinemod_options()
	{
		global $vbphrase, $show;
		$options = array();

		$mod_options = array();

		if ($this->mod_rights['managepost'])
		{
			$mod_options[$vbphrase['delete_posts']] = 'deleteposts';
			$mod_options[$vbphrase['undelete_posts']] = 'undeleteposts';
		    $mod_options[$vbphrase['delete_posts_as_spam']] = 'spampost';
        }

		if ($this->mod_rights['approvepost'])
		{
			$mod_options[$vbphrase['approve_posts']] = 'approveposts';
			$mod_options[$vbphrase['unapprove_posts']] = 'unapproveposts';
		}

		if ($this->mod_rights['approveattachment'])
		{
			$mod_options[$vbphrase['approve_attachments']] = 'approveattachments';
			$mod_options[$vbphrase['unapprove_attachments']] = 'unapproveattachments';
		}

		if ($this->mod_rights['managethread'])
		{
			$mod_options[$vbphrase['move_posts']] = 'moveposts';
			$mod_options[$vbphrase['merge_posts']] = 'mergeposts';
			$mod_options[$vbphrase['copy_posts']] = 'copyposts';
		}

		//if we have any mod options then we add the rest
		if ($mod_options)
		{
			$options[$vbphrase['option']] = $mod_options;
			$basic_options = array();
			$basic_options[$vbphrase['view_selected_posts']] = 'viewpost';
			$basic_options[$vbphrase['clear_post_list']] = 'clearpost';
			$options ["____________________"] = $basic_options;
		}
		return $options;
	}

// ###################### Start get_inlinemod_type ######################
/**
* vBForum_Search_Type_Post::get_inlinemod_type()
* Tell what type of object this is for inline moderation
*
* @return string
*/
	public function get_inlinemod_type()
	{
		return 'post';
	}

	public function get_inlinemod_action()
	{
		global $vbulletin;
		$base = '';
		if ($vbulletin->options['vbforum_url'])
		{
			$base = $vbulletin->options['vbforum_url'] . '/';
		}

		return $base . 'inlinemod.php';
	}

	public function add_advanced_search_filters($criteria, $registry)
	{
		global $vbphrase;
		if ($registry->GPC['searchthreadid'])
		{
			$criteria->add_group_filter($registry->GPC['searchthreadid']);

			//some special handling for searching in threads
			$vbulletin->GPC['forumchoice'] = array();
			$vbulletin->GPC['replyless'] = false;
			$vbulletin->GPC['replylimit'] = false;
		}

		if ($registry->GPC_exists['forumchoice'])
		{
			$this->add_forumid_filter($criteria, $registry->GPC['forumchoice'],
				$registry->GPC['childforums']);
		}

		if ($registry->GPC_exists['prefixchoice'])
		{
			$this->add_prefix_filter($criteria, $registry->GPC['prefixchoice']);
		}
		else if ($prefixchoice = $criteria->get_equals_filter('prefix'))
		{
			$this->add_prefix_filter($criteria, $prefixchoice);
		}

		if ($registry->GPC_exists['pollidmin'])
		{
			$this->add_pollid_filter($criteria, $registry->GPC['pollidmin'], vB_Search_Core::OP_GT);
		}

		if ($registry->GPC['pollidmax'])
		{
			$this->add_pollid_filter($criteria, $registry->GPC['pollidmin'], vB_Search_Core::OP_LT);
		}

		if ($registry->GPC['pollid'])
		{
			$this->add_pollid_filter($criteria, $registry->GPC['pollid'], vB_Search_Core::OP_EQ);
		}

		if ($registry->GPC['replylimit'] OR $registry->GPC['replylimit'] === '0')
		{
			$op = $registry->GPC['replyless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('replycount', $op, $registry->GPC['replylimit'], true);

			if ($registry->GPC['replylimit'] === '1')
			{
				$criteria->add_display_strings('replycount',
				vB_Search_Searchtools::getCompareString($registry->GPC['replyless'])
				. $vbphrase['1_reply']);
			}
			else 
			{
				$criteria->add_display_strings('replycount',
				vB_Search_Searchtools::getCompareString($registry->GPC['replyless'])
				. construct_phrase($vbphrase['x_replies'], $registry->GPC['replylimit']));
			}
		}
	}

	public function get_db_query_info($fieldname)
	{
		$result['corejoin']['thread'] = sprintf(self::$thread_join, TABLE_PREFIX,
				vB_Types::instance()->getContentTypeId("vBForum_Post"));

		$result['groupjoin']['thread'] = sprintf(self::$thread_group_join, TABLE_PREFIX,
				vB_Types::instance()->getContentTypeId("vBForum_Thread"));


		$result['table'] = 'thread';

		$fields = array('forumid', 'prefixid', 'replycount', 'views');
		if (in_array($fieldname, $fields))
		{
			$result['field'] = $fieldname;
		}
		else if ($fieldname == 'threadstart')
		{
			$result['field'] = 'dateline';
		}
		else if ($fieldname == 'pollid')
		{
			$result['field'] = 'pollid';
		}
		else if ($fieldname == 'views')
		{
			$result['field'] = 'views';
		}
		else if ($fieldname == 'replycount')
		{
			$result['field'] = 'replycount';
		}
		else if ($fieldname == 'lastpost')
		{
			$result['field'] = 'lastpost';
		}
		else if ($fieldname == 'forum')
		{
			$result['join']['forum'] = sprintf(self::$forum_thread_join, TABLE_PREFIX);
			$result['table'] = 'forum';
			$result['field'] = 'title';
		}
		else
		{
			return false;
		}

		return $result;
	}

	/**
	*	Add a filter for forums to search.
	*
	*	@param array $forumids
	* @param boolean $include_children -- If the children should be included.
	*/
	protected function add_forumid_filter($criteria, $forumids, $include_children)
	{
		if (in_array(' ', $forumids) OR in_array('', $forumids))
		{
			return;
		}

		global $vbulletin, $vbphrase;

		$all_forumids = fetch_search_forumids($forumids, $include_children);
		if ($all_forumids)
		{
			$criteria->add_filter('forumid', vB_Search_Core::OP_EQ, $all_forumids);

			$forum_strings =  vB_Search_Searchtools::getDisplayString('forum', $vbphrase['forum'], 'title',
				'forumid', $all_forumids,	vB_Search_Core::OP_EQ, false);
			$criteria->add_display_strings('forumid', $forum_strings .
 				( $include_children ? ' ' . $vbphrase['and_child_forums'] : ''));
		}
		else
		{
			$criteria->add_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink']);
		}
	}

	protected function add_prefix_filter($criteria, $prefixids)
	{
		global $vbphrase;
		if (!is_array($prefixids))
		{
			$prefixids = array($prefixids);
		}

		//if "everything" is selected, we don't need to filter.
		if (in_array(' ', $prefixids) OR in_array('', $prefixids))
		{
			return;
		}

		$restrictive = true;
		$noprefix_pos = array_search('-1',  $prefixids);
		$anyprefix = in_array('-2', $prefixids);

		//if we select thread with a prefix and threads without a prefix,
		//that's everything -- no filter
		if ($noprefix_pos !== false AND $anyprefix)
		{
			return;
		}

		//if we want any prefix don't worry about any "non special" values
		//we'll take well, anything.  The special values should already
		//be accounted for.
		if ($anyprefix)
		{
			$criteria->add_filter('prefixid', vB_Search_Core::OP_NEQ, '', false);
			return;
		}

		if ($noprefix_pos !== false)
		{
			//convert to the actual database value for no prefix
			$prefixids[$noprefix_pos] = '';

			//if "no prefix" is selected, then this isn't a restrictive filter
			//otherwise it is.
			$restrictive = false;
		}

		$criteria->add_filter('prefixid', vB_Search_Core::OP_EQ, $prefixids, $restrictive);

		$prefixes = array();
		foreach ($prefixids as $prefixid)
		{
			$prefixes[] = $vbphrase["prefix_{$prefixid}_title_rich"];
		}

		$criteria->add_display_strings($field, $vbphrase['prefix'] . ":" .
			implode( ', ' , $prefixes));
	}

	/***
	* This is needed by the poll widget, which passes "pollid > 0"
	*
	***/
	protected function add_pollid_filter($criteria, $pollids, $op)
	{
		global $vbphrase;
		if (!is_array($pollids) and $pollids and $op = vB_Search_Core::OP_EQ)
		{
			$criteria->add_filter('pollid', $op, $pollids, $restrictive);

			$criteria->add_display_strings($field, $vbphrase['poll'] . ' ' . $vbphrase['at_least']
				. ' ' . $pollid);
			return;
		}

		if (!is_array($pollids))
		{
			$pollids = array($pollids);
		}

		//if "everything" is selected, bail -- we don't need to filter.
		if (in_array(' ', $pollids) OR in_array('', $pollids))
		{
			return;
		}

		$restrictive = true;
		$pos = array_search('-1',  $pollids);

		if ($pos !== false)
		{
			//convert to the actual database value for no prefix
			$pollids[$pos] = '';

			//if "no prefix" is selected, then this isn't a restrictive filter
			//otherwise it is.
			$restrictive = false;
		}

		$criteria->add_filter('pollid', $op, $pollids, $restrictive);

		$criteria->add_display_strings($field, $vbphrase['poll'] . vB_Search_Searchtools::getCompareString($op) .
			implode( ', ' , $pollids));
	}


	protected $package = "vBForum";
	protected $class = "Post";
	protected $group_package = "vBForum";
	protected $group_class = "Thread";

	protected $type_globals = array (
		'searchthreadid'      => TYPE_INT,
		'showposts'      => TYPE_INT,
		'forumchoice'	  => TYPE_ARRAY,
		'starteronly'    => TYPE_INT,
		'prefixchoice'	  => TYPE_ARRAY,
		'childforums'	  => TYPE_BOOL,
		'replyless'  => TYPE_BOOL,
		'replylimit' => TYPE_NOHTML
	);

	private static $thread_join =
	  " INNER JOIN %sthread AS thread ON (
				searchcore.contenttypeid = %u  AND searchcore.groupid = thread.threadid)";

	private static $thread_group_join =
	  " INNER JOIN %sthread AS thread ON (
				searchgroup.contenttypeid = %u  AND searchgroup.groupid = thread.threadid)";

	private static $forum_thread_join =
		" INNER JOIN %sforum AS forum ON (thread.forumid = forum.forumid)";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 30635 $
|| ####################################################################
\*======================================================================*/
