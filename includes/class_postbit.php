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
 * Require additional library files
 */
require_once(DIR . '/includes/class_bbcode.php');
require_once(DIR . '/includes/functions_reputation.php');

/**
 * Bitfield value which determines whether a user's age should be displayed with their post.
 */
define('POST_SHOW_AGE', 1);

/**
 * Bitfield value which determines whether a user's reputation power should be displayed with their post.
 */
define('POST_SHOW_REPPOWER', 2);

/**
 * Bitfield value which determined whether a user sees another user' infractions
 */
define('POST_SHOW_INFRACTION', 4);

/**
 * Postbit factory object
 *
 * @package 		vBulletin
 * @version		$Revision: 44754 $
 * @date 		$Date: 2011-06-20 09:57:57 -0700 (Mon, 20 Jun 2011) $
 *
 */
class vB_Postbit_Factory
{
	/**
	 * Main data registry
	 *
	 * @var	vB_Registry
	 */
	var $registry = null;

	/**
	 * Information about the forum this post is in (if applicable)
	 *
	 * @var	array
	 */
	var $forum = null;

	/**
	 * Information about the thread this post is in (if applicable)
	 *
	 * @var	array
	 */
	var $thread = null;

	/**
	 * Cache of elements specific to these posts (eg, sig caching)
	 *
	 * @var	array
	 */
	var $cache = null;

	/**
	 * Parses BB codes. Required if the class does anything related to BB code parsing!
	 *
	 * @var	vB_BbCodeParser
	 */
	var $bbcode_parser = null;

	/**
	 * Fetches the specified postbit object and sets up its default state
	 *
	 * @param	string	Type of postbit to retrieve
	 *
	 * @return	vB_Postbit	Object of a class inherited from vB_Postbit
	 */
	function &fetch_postbit($postbit_type)
	{
		$handled_type = false;
		($hook = vBulletinHook::fetch_hook('postbit_factory')) ? eval($hook) : false;

		if (!$handled_type)
		{
			switch ($postbit_type)
			{
				case 'post':
					$out = new vB_Postbit_Post();
					if ($this->registry->options['legacypostbit'])
					{
						$out->templatename = 'postbit_legacy';
					}
					break;

				case 'announcement':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Announcement();
					break;

				case 'pm':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Pm();
					break;

				case 'usernote':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Usernote();
					break;

				case 'external':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_External();
					break;

				case 'post_ignore':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Post_Ignore();
					break;

				case 'post_global_ignore':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Post_Global_Ignore();
					break;

				case 'post_deleted':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Post_Deleted();
					break;

				case 'auto_moderated':
					require_once(DIR . '/includes/class_postbit_alt.php');
					$out = new vB_Postbit_Post_AutoModerated();
					break;

				default:
					trigger_error('vB_Postbit_Factory::fetch_postbit(): Invalid postbit type.', E_USER_ERROR);
			}
		}

		$out->registry = & $this->registry;
		$out->forum = & $this->forum;
		$out->thread = & $this->thread;
		$out->cache = & $this->cache;
		$out->bbcode_parser = & $this->bbcode_parser;

		return $out;
	}
}

/**
 * Generic Postbit object. This is abstract. You may not instantiate it directly.
 *
 * @package 		vBulletin
 * @version		$Revision: 44754 $
 * @date 		$Date: 2011-06-20 09:57:57 -0700 (Mon, 20 Jun 2011) $
 *
 */
class vB_Postbit
{
	protected $template_prefix = '';

	/*** Enables us to set the template prefix to use a different template***/
	public function set_template_prefix($template_prefix)
	{
		$this->template_prefix = $template_prefix;
	}

	/**
	* Elements of the post.
	*
	* @var	array
	*/
	var $post = array();

	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit';

	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Information about the forum this post is in (if applicable)
	*
	* @var	array
	*/
	var $forum = array();

	/**
	* Information about the thread this post is in (if applicable)
	*
	* @var	array
	*/
	var $thread = array();

	/**
	* Cache of elements specific to these posts (eg, sig caching)
	*
	* @var	array
	*/
	var $cache = array();

	/**
	* Parses BB codes. Required if the class does anything related to BB code parsing!
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode_parser = null;

	/**
	* Array of data about the signature in this post for caching
	*
	* @var	array
	*/
	var $sig_cache = array();

	/**
	 * Whether to add promote links to export post to another contenttype.
	 *
	 * @var bool
	 */
	var $add_promote_links = false;

	/**
	* Constructor. Prevents direct instantiation.
	*/
	function vB_Postbit()
	{
		if (!is_subclass_of($this, 'vB_Postbit'))
		{
			trigger_error('Direct instantiation of vB_Postbit class prohibited. Use the vB_Postbit_Factory class.', E_USER_ERROR);
		}
	}

	/**
	* Template method. Calls all the appropriate methods to build a post and then evaluates the template.
	*
	* @param	array	Post information
	*
	* @return	string	HTML for the post
	*/
	function construct_postbit(&$post)
	{
		global $ad_location;

		$this->post =& $post;
		$thread =& $this->thread;
		$forum =& $this->forum;

		// make sure we can display this post
		if ($this->is_displayable() == false)
		{
			return '';
		}

		global $show, $vbphrase;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$template_hook = array();

		($hook = vBulletinHook::fetch_hook('postbit_display_start')) ? eval($hook) : false;

		// put together each part of the post
		$this->prep_post_start();

		$this->process_date_status();
		$this->process_edit_info();
		$this->process_icon();
		$this->process_ip();

		if (!empty($this->post['userid']))
		{
			$this->process_registered_user();
			$this->process_im_icons();
		}
		else
		{
			$this->process_unregistered_user();
		}

		if ($this->post['avatarurl'] AND $this->post['avwidth'] AND $this->post['avheight'])
		{
			$this->post['avwidth'] = 'width="' . $this->post['avwidth'] . '"';
			$this->post['avheight'] = 'height="' . $this->post['avheight'] . '"';
		}
		else
		{
			$this->post['avwidth'] = '';
			$this->post['avheight'] = '';
		}
		$this->bbcode_parser->containerid = $this->post['postid'];
		if (defined('VB_API') AND VB_API === true)
		{
			if (!$post['pagetext'])
			{
				$post['pagetext'] = $post['message']; // PM doesn't have $post['pagetext']
			}
			$post['message_plain'] = build_message_plain($post['pagetext']);
			$post['message_bbcode'] = $post['pagetext'];
		}
		$this->parse_bbcode();

		$this->process_attachments();

		// finish prepping the post
		$this->prep_post_end();

		$pageinfo_post = array(
			'p' => $post['postid']
		);
		if ($this->templatename != 'postbit')
		{
			$pageinfo_post['viewfull'] = 1;
		}

		// execute hook
		($hook = vBulletinHook::fetch_hook('postbit_display_complete')) ? eval($hook) : false;

		$show['last_ad'] = $show['first_ad'] = $show['first_adsense'] = false;
		if ($post['isfirstshown'])
		{
			//this does not appear to be an ad slot, rather it appears to be some shim html
			//that needs to be rendered if the firstpost google ad is included.
			$template = vB_Template::create('ad_showthread_firstpost_sig');
			$template->register('adsense_pub_id', $this->registry->adsense_pub_id);
			$template->register('adsense_host_id', $this->registry->adsense_host_id);
			$ad_location['ad_showthread_firstpost_sig'] = $template->render();

			$template = vB_Template::create('ad_showthread_firstpost_start');
			$template->register('adsense_pub_id', $this->registry->adsense_pub_id);
			$template->register('adsense_host_id', $this->registry->adsense_host_id);
			$ad_location['ad_showthread_firstpost_start'] = trim($template->render(true));

			$ad_location['ad_showthread_firstpost_sig'] = vB_Template::create('ad_showthread_firstpost_sig')->render();
			if ($ad_location['ad_showthread_firstpost_start'])
			{
				$show['first_ad'] = true;
			}

			if ($ad_location['thread_first_post_content'] = trim(vB_Template::create('ad_thread_first_post_content')->render(true)))
			{
				if (preg_match('#^<div id="ad_thread_first_post_content">.+</div>$#si', $ad_location['thread_first_post_content']))
				{
					$show['first_ad'] = true;
				}
				else
				{
					$ad_location['thread_first_post_content'] = '';
				}
			}
		}
		else if
		(
			$post['islastshown']
				AND
			$ad_location['thread_last_post_content'] = trim(vB_Template::create('ad_thread_last_post_content')->render(true))
		)
		{
			if (preg_match('#^<div id="ad_thread_last_post_content">.+</div>$#si', $ad_location['thread_last_post_content']))
			{
				$show['last_ad'] = true;
			}
			else
			{
				$ad_location['thread_last_post_content'] = '';
			}
		}

		// prepare the member action drop-down menu
		// pass the local template hook so that each drop-down gets its own hook (per-postbit)
		$memberaction_dropdown = construct_memberaction_dropdown($post, $template_hook);

		// evaluate template
		$postid =& $post['postid'];

		$templater = vB_Template::create($this->template_prefix . $this->templatename);
			if ($this->add_promote_links AND $this->registry->products['vbcms'])
			{
				if (!isset($this->registry->userinfo['permissions']['cms']))
				{
					bootstrap_framework();
					vBCMS_Permissions::getUserPerms();
				}

				if ($this->registry->userinfo['permissions']['cms']['canpublish'][0] != -1)
				{
					$templater->register('promote_sectionid', $this->registry->userinfo['permissions']['cms']['canpublish'][0]);
					$templater->register('articletypeid', vB_Types::instance()->getContentTypeID('vBCms_Article'));
					$query = 'contenttypeid='. vB_Types::instance()->getContentTypeID('vBCms_Article') .
						'&amp;postid=' . $post['postid'] . '&amp;parentid=1';
					$promote_url = vB_Route::create('vBCms_Route_Content', '1/addcontent/')->getCurrentURL(null, null, $query);
					$templater->register('promote_url', $promote_url);
				}
			}
			$templater->register('ad_location', $ad_location);
			$templater->register('memberaction_dropdown', $memberaction_dropdown);
			$templater->register('pageinfo_post', $pageinfo_post);
			$templater->register('post', $post);
			$templater->register('postid', $postid);
			$templater->register('template_hook', $template_hook);
			$templater->register('thread', $thread);
		$postbit = $templater->render();

		$templater = vB_Template::create('postbit_wrapper');
			$templater->register('post', $post);
			$templater->register('postbit', $postbit);
			$templater->register('postbit_type', ($this->templatename == 'postbit_legacy')?'postbit':$this->templatename);
			$templater->register('spacer_close', $spacer_close);
			$templater->register('spacer_open', $spacer_open);
		$retval = $templater->render();

		return $retval;
	}

	/**
	* Determines whether the post should actually be displayed.
	*
	* @return	bool	True if the post should be displayed; false otherwise
	*/
	function is_displayable()
	{
		// hide users in Coventry from non-staff members
		if ($tachyuser = in_coventry($this->post['userid']) AND !can_moderate($this->thread['forumid']))
		{
			return false;
		}
		return true;
	}

	/**
	* Processes the date information and determines whether the post is new or old
	*/
	function process_date_status()
	{
		global $vbphrase, $firstnew;

		// get new/old post statusicon
		// this is also needed by ignored and deleted posts as they should show read/unread status as well (if you can see them)
		if (!empty($this->thread))
		{
			if (isset($this->thread['threadview']))
			{
				$lastvisit = $this->thread['threadview'];
			}
			else if ($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid'])
			{
				$threadview = max($this->thread['threadread'], $this->thread['forumread'], TIMENOW - ($this->registry->options['markinglimit'] * 86400));
				$lastvisit = $this->thread['threadview'] = intval($threadview);
			}
			else if (($tview = fetch_bbarray_cookie('thread_lastview', $threadid)) > $this->registry->userinfo['lastvisit'])
			{
				$lastvisit = $this->thread['threadview'] = intval($tview);
			}
			else
			{
				$lastvisit = $this->registry->userinfo['lastvisit'];
			}
		}
		else
		{
			$lastvisit = $this->registry->userinfo['lastvisit'];
		}

		if ($this->post['dateline'] > $lastvisit)
		{
			$this->post['statusicon'] = 'new';
			$this->post['statustitle'] = $vbphrase['unread_date'];
			if (!$firstnew)
			{
				$firstnew = $this->post['postid'];
				$this->post['firstnewinsert' ] = '<a name="newpost"></a>';
			}
			else
			{
				$this->post['firstnewinsert'] = '';
			}
		}
		else
		{
			$this->post['statusicon'] = 'old';
			$this->post['statustitle'] = $vbphrase['old'];
			$this->post['firstnewinsert'] = '';
		}

		// format date/time
		$this->post['postdate'] = vbdate($this->registry->options['dateformat'], $this->post['dateline'], true);
		$this->post['posttime'] = vbdate($this->registry->options['timeformat'], $this->post['dateline']);
	}

	/**
	* Processes any attachments to this post.
	*/
	function process_attachments()
	{
		$forumperms = fetch_permissions($this->thread['forumid']);
		require_once(DIR . '/packages/vbattach/attach.php');
		$attach = new vB_Attach_Display_Content($this->registry, 'vBForum_Post');
		$attach->process_attachments(
			$this->post,
			$this->post['attachments'],
			(THIS_SCRIPT == 'external'),
			can_moderate($this->forum['forumid'], 'canmoderateattachments'),
			$forumperms & $this->registry->bf_ugp_forumpermissions['cangetattachment'],
			$forumperms & $this->registry->bf_ugp_forumpermissions['canseethumbnails']
		);
	}

	/**
	* Processes "edited by" info.
	*/
	function process_edit_info()
	{
		global $show;

		if (!is_null($this->post['edit_userid']))
		{
			$this->post['edit_date'] = vbdate($this->registry->options['dateformat'], $this->post['edit_dateline'], true);
			$this->post['edit_time'] = vbdate($this->registry->options['timeformat'], $this->post['edit_dateline']);
			$show['postedited'] = true;

			if ($this->post['hashistory'] AND $this->registry->options['postedithistory'])
			{
				// people who can edit the post can see the history... we also assume that you
				// can see the full version of the post, meaning the deleted checks are uneeded
				$owner_edit = (
					$this->thread['open']
					AND $this->post['userid'] == $this->registry->userinfo['userid']
					AND fetch_permissions($this->thread['forumid']) & $this->registry->bf_ugp_forumpermissions['caneditpost']
					AND (
						$this->registry->options['edittimelimit'] == 0
						OR $this->post['dateline'] >= (TIMENOW - ($this->registry->options['edittimelimit'] * 60))
					)
				);

				$show['postedithistory'] = ($owner_edit OR can_moderate($this->thread['forumid'], 'caneditposts'));
			}
			else
			{
				$show['postedithistory'] = false;
			}
		}
		else
		{
			$show['postedited'] = false;
			$show['postedithistory'] = false;
		}

		$this->post['historyurl'] = 'posthistory.php?' . $this->registry->session->vars['sessionurl'] . 'p=' . $this->post['postid'];
	}

	/**
	* Processes the post's icon.
	*/
	function process_icon()
	{
		global $show, $vbphrase;

		if (!$this->forum['allowicons'] OR $this->post['iconid'] == 0)
		{
			if (!empty($this->registry->options['showdeficon']))
			{
				$this->post['iconpath'] = $this->registry->options['showdeficon'];
				$this->post['icontitle'] = $vbphrase['default'];
			}
		}

		$show['messageicon'] = !empty($this->post['iconpath']);
	}

	/**
	* Processes this post's user info assuming the user is registered.
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;
		$post =& $this->post; // this is a stopgap required for rank's eval code

		fetch_musername($this->post);

		// get online status -- function call also sets values in $this->post
		$this->post['online_status_code'] = fetch_online_status($this->post, true);

		if (empty($this->cache['perms'][$this->post['userid']]))
		{
			$this->cache['perms'][$this->post['userid']] = cache_permissions($this->post, false);
		}

		// get avatar
		if ($this->post['avatarid'])
		{
			$this->post['avatarurl'] = $this->post['avatarpath'];
		}
		else
		{
			if ($this->post['hascustomavatar'] AND $this->registry->options['avatarenabled'])
			{
				if ($this->registry->options['usefileavatar'])
				{
					$this->post['avatarurl'] = $this->registry->options['avatarurl'] . '/avatar' . $this->post['userid'] . '_' . $this->post['avatarrevision'] . '.gif';
				}
				else
				{
					$this->post['avatarurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->post['userid'] . '&amp;dateline=' . $this->post['avatardateline'];
				}
			}
			else
			{
				$this->post['avatarurl'] = '';
			}
		}

		if ( // no avatar defined for this user
			empty($this->post['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->post['avatarid'] AND !($this->cache['perms'][$this->post['userid']]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->post['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		// Generate Reputation Power
		if ($this->registry->options['postelements'] & POST_SHOW_REPPOWER AND $this->registry->options['reputationenable'])
		{
			if (!empty($this->cache['reppower'][$this->post['userid']]))
			{
				$this->post['reppower'] = $this->cache['reppower'][$this->post['userid']];
			}
			else
			{
				$this->post['reppower'] = fetch_reppower($this->post, $this->cache['perms'][$this->post['userid']]);
				$this->cache['reppower'][$this->post['userid']] = $this->post['reppower'];
			}
			$show['reppower'] = true;
		}
		else
		{
			$show['reppower'] = false;
		}

		// get reputation
		if ($this->registry->options['reputationenable'])
		{
			fetch_reputation_image($this->post, $this->cache['perms'][$this->post['userid']]);
			$show['reputation'] = true;
		}
		else
		{
			$show['reputation'] = false;
		}

		// get join date & posts per day
		$jointime = (TIMENOW - $this->post['joindate']) / 86400; // Days Joined
		if ($jointime < 1)
		{
			// User has been a member for less than one day.
			$this->post['postsperday'] = $this->post['posts'];
		}
		else
		{
			$this->post['postsperday'] = vb_number_format($this->post['posts'] / $jointime, 2);
		}
		$this->post['joindateline'] = $this->post['joindate'];
		$this->post['joindate'] = vbdate($this->registry->options['registereddateformat'], $this->post['joindate']);

		// format posts number
		$this->post['posts'] = vb_number_format($this->post['posts']);

		$show['profile'] = true;
		$show['search'] = true;
		$show['buddy'] = true;
		$show['emaillink'] = (
			$this->post['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
			AND $this->registry->userinfo['userid']
		);
		$show['homepage'] = ($this->post['homepage'] != '' AND $this->post['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->post['receivepm'] AND $this->cache['perms'][$this->post['userid']]['pmquota'])
	 				)) ? true : false;

		// Generate Age
		if ($this->registry->options['postelements'] & POST_SHOW_AGE AND ($this->post['showbirthday'] == 1 OR $this->post['showbirthday'] == 2))
		{
			if (!$this->cache['year'])
			{
				$this->cache['year'] = vbdate('Y', TIMENOW, false, false);
				$this->cache['month'] = vbdate('n', TIMENOW, false, false);
				$this->cache['day'] = vbdate('j', TIMENOW, false, false);
			}
			if (empty($this->cache['age'][$this->post['userid']]))
			{
				$date = explode('-', $this->post['birthday']);
				if ($this->cache['year'] > $date[2] AND $date[2] != '0000')
				{
					$this->post['age'] = $this->cache['year'] - $date[2];
					if ($this->cache['month'] < $date[0] OR ($this->cache['month'] == $date[0] AND $this->cache['day'] < $date[1]))
					{
						$this->post['age']--;
					}

					if ($this->post['age'] < 101)
					{
						$this->cache['age'][$this->post['userid']] = $this->post['age'];
					}
					else
					{
						unset($this->post['age']);
					}
				}
			}
			else
			{
				$this->post['age'] = $this->cache['age'][$this->post['userid']];
			}
		}

		// Display infractions
		$show['infraction'] = ($this->post['userid'] AND ($this->registry->options['postelements'] & POST_SHOW_INFRACTION) AND (
			$this->post['ipoints'] OR $this->post['warnings'] OR $this->post['infractions']) AND (
			$this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canreverseinfraction']
			OR $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseeinfraction']
			OR $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction']
			OR ($this->post['userid'] == $this->registry->userinfo['userid'] /*AND $this->registry->options['canseeown']*/)
		));

		// Moved to a function to allow child overriding, i.e. announcements
		$this->process_signature();
	}

	function process_signature()
	{
		if ($this->post['showsignature']
			AND trim($this->post['signature']) != ''
			AND (!$this->registry->userinfo['userid'] OR $this->registry->userinfo['showsignatures'])
			AND $this->cache['perms'][$this->post['userid']]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canusesignature']
		)
		{
			if (isset($this->cache['sig'][$this->post['userid']]))
			{
				// already fully parsed
				$this->post['signature'] = $this->cache['sig'][$this->post['userid']];
			}
			else
			{
				// have a mostly parsed version or no parsed version
				$this->bbcode_parser->set_parse_userinfo($this->post, $this->cache['perms'][$this->post['userid']]);
				$this->post['signature'] = $this->bbcode_parser->parse(
					$this->post['signature'],
					'signature',
					true,
					false,
					$this->post['signatureparsed'],
					$this->post['sighasimages'],
					true
				);
				$this->bbcode_parser->set_parse_userinfo(array());
				if ($this->post['signatureparsed'] === null)
				{
					$this->sig_cache = $this->bbcode_parser->cached;
				}

				$this->cache['sig'][$this->post['userid']] = $this->post['signature'];
			}
		}
		else
		{
			$this->post['signature'] = '';
		}
	}

	/**
	* Process this post's user info assuming its a guest.
	*/
	function process_unregistered_user()
	{
		global $show, $vbphrase;

		$this->post['rank'] = '';
		$this->post['postsperday'] = 0;
		$this->post['displaygroupid'] = 1;
		$this->post['username'] = $this->post['postusername'];
		fetch_musername($this->post);
		//$this->post['usertitle'] = $vbphrase['guest'];
		$this->post['usertitle'] = $this->registry->usergroupcache['1']['usertitle'];
		$this->post['joindate'] = '';
		$this->post['posts'] = 'n/a';
		$this->post['avatar'] = '';
		$this->post['profile'] = '';
		$this->post['email'] = '';
		$this->post['useremail'] = '';
		$this->post['icqicon'] = '';
		$this->post['aimicon'] = '';
		$this->post['yahooicon'] = '';
		$this->post['msnicon'] = '';
		$this->post['skypeicon'] = '';
		$this->post['homepage'] = '';
		$this->post['findposts'] = '';
		$this->post['signature'] = '';
		$this->post['reputationdisplay'] = '';
		$this->post['onlinestatus'] = '';

		$show['avatar'] = false;
		$show['reputation'] = false;
		$show['pmlink'] = false;
		$show['homepage'] = false;
		$show['emaillink'] = false;
		$show['profile'] = false;
		$show['search'] = false;
		$show['buddy'] = false;
		$show['infraction'] = false;
	}

	/**
	* Processes instant messaging program icons.
	*/
	function process_im_icons()
	{
		construct_im_icons($this->post);
	}

	/**
	* Processes this post's IP.
	*/
	function process_ip()
	{
		global $show, $vbphrase;

		$post =& $this->post;

		$this->post['iplogged'] = '';
		if ($this->post['ip'] != '')
		{
			if ($this->registry->options['logip'] == 2)
			{
				$show['ip'] = true;
				$templater = vB_Template::create('postbit_ip');
					$templater->register('post', $post);
				$this->post['iplogged'] = $templater->render();
			}
			else if ($this->registry->options['logip'] == 1 AND can_moderate($this->thread['forumid'], 'canviewips'))
			{
				$show['ip'] = false;
				$templater = vB_Template::create('postbit_ip');
					$templater->register('post', $post);
				$this->post['iplogged'] = $templater->render();
			}
		}
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = $this->bbcode_parser->parse(
			$this->post['pagetext'],
			$this->forum['forumid'],
			$this->post['allowsmilie'],
			false,
			'',
			3,
			false,
			$this->post['htmlstate']
		);
	}

	/**
	* Processes miscellaneous post items at the beginning of the construction process.
	*/
	function prep_post_start()
	{
		$this->post = array_merge($this->post, convert_bits_to_array($this->post['options'], $this->registry->bf_misc_useroptions));
		$this->post = array_merge($this->post, convert_bits_to_array($this->post['adminoptions'], $this->registry->bf_misc_adminoptions));

		// do word wrap
		if ($this->registry->options['wordwrap'])
		{
			$this->post['title'] = fetch_word_wrapped_string($this->post['title']);
		}
		$this->post['title'] = fetch_censored_text($this->post['title']);

		// init imod checkbox value
		$this->post['checkbox_value'] = 0;
	}

	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
	}
}

/**
* Postbit optimized for regular posts
*
* @package 		vBulletin
* @version		$Revision: 44754 $
* @date 		$Date: 2011-06-20 09:57:57 -0700 (Mon, 20 Jun 2011) $
*
*/
class vB_Postbit_Post extends vB_Postbit
{
	/**
	* Reference to the words that should be highlighted in this post.
	*
	* @var	array
	*/
	var $highlight = null;

	/**
	* Reference to the BB code parser's version of the parsed post cache array.
	*
	* @var	array
	*/
	var $post_cache = array();

	/**
	* Determines whether this post's parsed version should be cached by the BB code parser.
	*
	* @var	bool
	*/
	var $cachable = false;

	/**
	 * Whether to add promote links to export post to another contenttype.
	 *
	 * @var bool
	 */
	var $add_promote_links = true;

	/**
	* Processes miscellaneous post items at the beginning of the construction process.
	*/
	function prep_post_start()
	{
		parent::prep_post_start();
		$this->post['checkbox_value'] += ($this->post['visible'] == 0 OR ($this->thread['firstpostid'] == $this->post['postid'] AND $this->thread['visible'] == 0)) ? POST_FLAG_INVISIBLE : 0;
		$this->post['checkbox_value'] += ($this->post['visible'] == 2 OR ($this->thread['firstpostid'] == $this->post['postid'] AND $this->thread['visible'] == 2)) ? POST_FLAG_DELETED : 0;
		$this->post['checkbox_value'] += is_array($this->post['attachments']) ? POST_FLAG_ATTACH : 0;
		$this->post['checkbox_value'] += $this->post['userid'] == 0 ? POST_FLAG_GUEST : 0;
	}

	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show;

		// check for autoscrolling
		global $postid, $onload, $threadedmode;
		if ($this->post['postid'] == $postid)
		{
			$this->post['scrolltothis'] = ' id="currentPost"';
			if ($threadedmode == 0)
			{
				$onload = htmlspecialchars_uni("if (document.body.scrollIntoView && fetch_object('currentPost') && (window.location.href.indexOf('#') == -1 || window.location.href.indexOf('#post') > -1)) { fetch_object('currentPost').scrollIntoView(true); }");
			}
		}
		else
		{
			$this->post['scrolltothis'] = '';
		}

		// highlight words from search engine ($_REQUEST[highlight])
		// Highlight word in all posts even if we link to one post since if we come from "Last Page" in thread search results, we don't only care about the last post!
		if (!empty($this->highlight) AND is_array($this->highlight)) // AND ($_REQUEST['postid'] == $post['postid'] OR empty($_REQUEST['postid'])) )
		{
			$this->post['message'] = preg_replace('#(^|>)([^<]+)(?=<|$)#sUe', "\$this->process_highlight_postbit('\\2', \$this->highlight, '\\1')", $this->post['message']);
			$this->post['message'] = preg_replace('#<vb_highlight>(.*)</vb_highlight>#siU', '<span class="highlight">$1</span>', $this->post['message']);
		}

		// hide edit button if they can't use it
		$forumperms = fetch_permissions($this->thread['forumid']);
		if (
			!$this->thread['isdeleted'] AND !$this->post['isdeleted'] AND (
			can_moderate($this->thread['forumid'], 'caneditposts') OR
			//can_moderate($this->thread['forumid'], 'candeleteposts') OR
			(
				$this->thread['open'] AND
				$this->post['userid'] == $this->registry->userinfo['userid'] AND
				($forumperms & $this->registry->bf_ugp_forumpermissions['caneditpost']) AND
				(	$this->post['dateline'] >= (TIMENOW - ($this->registry->options['edittimelimit'] * 60)) OR
					$this->registry->options['edittimelimit'] == 0
				)
			))
		)
		{
			// can edit or delete this post, so show the link
			$this->post['editlink'] = fetch_seo_url('editpost', $this->post, array('do' => 'editpost'));

			if ($this->registry->options['quickedit'])
			{
				$show['ajax_js'] = true;
			}
		}
		else
		{
			$this->post['editlink'] = '';
		}

		if (
			!$this->thread['isdeleted'] AND
			!$this->post['isdeleted'] AND
			 $this->forum['allowposting'] AND
			!$show['search_engine'] AND
			($this->thread['open'] OR can_moderate($this->thread['forumid'], 'canopenclose'))
		)
		{

			$this->post['replylink'] = fetch_seo_url('newreply', array(), array('do' => 'newreply', 'p' => $this->post['postid']));

			if ($show['multiquote_global'])
			{
				$show['multiquote_post'] = true;
				$show['multiquote_selected'] = (is_array($this->registry->GPC['vbulletin_multiquote']) AND
					in_array($this->post['postid'], $this->registry->GPC['vbulletin_multiquote']));
			}
		}
		else
		{
			$this->post['replylink'] = '';
			$show['multiquote_post'] = false;
		}

		if (!empty($this->post['del_reason']))
		{
			$this->post['del_reason'] = fetch_censored_text($this->post['del_reason']);
		}

		if ($this->registry->options['apipostidmanage']['enable'] AND $this->post['mobile_platformname'])
		{
			$platformname = strtolower($this->post['mobile_platformname']);
			if (isset($this->registry->options['apipostidmanage'][$platformname]))
			{
				$this->post['api_platform'] = $platformname;
				$this->post['api_platform_link'] = $this->registry->options['apipostidmanage'][$platformname] ? $this->registry->options['apipostidmanage'][$platformname] : '';
				$this->post['api_platform_link_phrase'] = 'posted_from_app_' . $platformname;
				$this->post['api_platform_link_title'] = 'goto_app_' . $platformname;
			}
			else
			{
				$this->post['api_platform'] = '';
			}
		}

		$this->post['forwardlink'] = '';

		$this->post['reportlink'] = 'report.php?' . $this->registry->session->vars['sessionurl'] . 'p=' . $this->post['postid'];
		$show['reportlink'] = (
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
		$show['postcount'] = (!empty($this->post['postcount']) AND !$show['search_engine']);
		$show['reputationlink'] = (
			($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuserep']
				OR $this->post['userid'] == $this->registry->userinfo['userid'])
			AND $this->registry->options['reputationenable']
			AND $this->registry->userinfo['userid']
			AND $this->post['userid']
			AND $this->post['visible'] != 2
			AND $this->registry->usergroupcache[$this->post['usergroupid']]['genericoptions'] & $this->registry->bf_ugp_genericoptions['isnotbannedgroup']
		);

		$show['infractionlink'] = (
			// Must have 'cangiveinfraction' permission. Branch dies right here majority of the time
			$this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction']
			// Can not give yourself an infraction
			AND $this->post['userid'] != $this->registry->userinfo['userid']
			// Can not give an infraction to a post that already has one
			AND empty($this->post['infraction'])
			// Can not give an admin an infraction
			AND !($this->cache['perms'][$this->post['userid']]['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
			// Only Admins can give a supermod an infraction
			AND 			(
				!($this->cache['perms'][$this->post['userid']]['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
				OR $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
			)
			// Can not give guests infractions
			AND $this->post['userid']
		);

		// need to see the card to be able to remove it. 'cansee' is designed for groups who can't give infractions
		$canseeinfraction = (
			$this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canreverseinfraction']
			OR $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseeinfraction']
			OR $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction']
			OR ($this->post['userid'] == $this->registry->userinfo['userid'] /*AND $this->registry->options['canseeown']*/)
		);
		$show['redcard'] = ($this->post['infraction'] == 2 AND $canseeinfraction);
		$show['yellowcard'] = ($this->post['infraction'] == 1 AND $canseeinfraction);
		$show['moderated'] = (!$this->post['visible'] OR (!$this->thread['visible'] AND $this->post['postcount'] == 1)) ? true : false;
		$show['spam'] = ($show['moderated'] AND $this->post['spamlog_postid']) ? true : false;
		$show['deletedpost'] = ($this->post['visible'] == 2 OR ($this->thread['visible'] == 2 AND $this->post['postcount'] == 1)) ? true : false;

		parent::prep_post_end();
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		static $nodecache = array();

		$this->bbcode_parser->attachments =& $this->post['attachments'];
		$this->bbcode_parser->unsetattach = true;

		//If the pagetext_html is not set, we need to see whether this is a
		// cms comment thread. If so, we need to set the comment link information.
		if (
			$this->registry->products['vbcms']
				AND
			$this->registry->options['vbcmsforumid'] > 0
				AND
			$this->registry->options['vbcmsforumid'] == $this->thread['forumid']
				AND
			empty($this->post['pagetext_html'])
		)
		{
			//We need to see if this post is for a forum article;
			if (!isset($this->post['nodeid']))
			{
				if (!isset($nodecache[$this->post['threadid']]))
				{
					$nodecache[$this->post['threadid']] = $this->registry->db->query_first("
						SELECT node.nodeid, node.url
						FROM " . TABLE_PREFIX . "cms_nodeinfo AS info
						INNER JOIN " . TABLE_PREFIX . "cms_node AS node ON node.nodeid = info.nodeid
						WHERE info.associatedthreadid = " . intval($this->post['threadid']) . "
					");
				}
				$this->post['nodeid'] = $nodecache[$this->post['threadid']]['nodeid'];
				$this->post['url'] = $nodecache[$this->post['threadid']]['url'];
			}
			$this->post['nodeid'] = intval($this->post['nodeid']);
			if ($this->post['nodeid'])
			{
				//we need to set the parser to render this quotes as a links to the article
				// rather than showthread.

				//We need the router, which requires the boot.
				require_once(DIR . '/includes/class_bootstrap_framework.php');
				vB_Bootstrap_Framework::init();

				$nodeinfo = $this->post['nodeid'] . (empty($this->post['url']) ? '' :
					'-' . $this->post['url']);
				$page_url =  vBCms_Route_Content::getURL(array('node' => $nodeinfo, 'action' =>'view'));
				$this->bbcode_parser->set_quote_template('vbcms_bbcode_quote');
				$this->bbcode_parser->set_quote_vars(array('page_url' => $page_url .
					(strpos($page_url, '?') == false ? '?' : '&')));
			}
		}

		$this->post['message'] = $this->bbcode_parser->parse(
			$this->post['pagetext'],
			$this->forum['forumid'],
			$this->post['allowsmilie'],
			false,
			$this->post['pagetext_html'],
			$this->post['hasimages'],
			$this->cachable,
			$this->post['htmlstate']
		);
		$this->post_cache =& $this->bbcode_parser->cached;

		//Unset the flags we set above if necessary.
		if (isset($nodeinfo))
		{
			$this->bbcode_parser->set_quote_template('bbcode_quote');
			$this->bbcode_parser->set_quote_vars(false);
		}
	}

	/**
	* Callback for the regular expression that does the highlighting replacements
	*
	* @param	string	Text to run the search on
	* @param	array	Array of words to highlight
	* @param	string	String to prepend (the regex matches an extra character in the beginning)
	*
	* @return	string	String with words highlighted
	*/
	function process_highlight_postbit($text, $words, $prepend)
	{
		$text = str_replace('\"', '"', $text);
		foreach ($words AS $replaceword)
		{
			$text = preg_replace('#(?<=[\s"\]>()\',;]|^)(' . $replaceword . ')(([&\'.,:;-?!()\s"<\[]|$))#siU', '<vb_highlight>\\1</vb_highlight>\\2', $text);
			//$text = preg_replace('#(?<=[^\w=])(' . $replaceword . ')(?=[^\w=])#siU', '<span class="highlight">\\1</span>', $text);
		}

		return "$prepend$text";
	}
}

// #############################################################################

/**
* Construct the icons for various instant messaging programs and set global state ($show).
* Changes are written to the $userinfo array.
*
* @param	array	Reference to an array of user info that contains IM names/numbers
* @param	bool	Whether to ignore the global option that determines if IM icons are shown
*/
function construct_im_icons(&$userinfo, $ignore_off_setting = false)
{
	global $vbulletin, $show, $vbphrase;

	$show['hasimicons'] = false;

	$userinfo['icqicon'] = '';
	$userinfo['aimicon'] = '';
	$userinfo['yahooicon'] = '';
	$userinfo['msnicon'] = '';
	$userinfo['skypeicon'] = '';

	$userinfo['showicq'] = false;
	$userinfo['showaim'] = false;
	$userinfo['showyahoo'] = false;
	$userinfo['showmsn'] = false;
	$userinfo['showskype'] = false;

	if ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers'])
	{
		if (!empty($userinfo['icq']) AND ($vbulletin->options['showimicons'] OR $ignore_off_setting))
		{
			$templater = vB_Template::create('im_icq');
				$templater->register('userinfo', $userinfo);
			$userinfo['icqicon'] = $templater->render();
			$userinfo['showicq'] = true;
			$show['hasimicons'] = true;
		}

		if ($userinfo['aim'] != '' AND ($vbulletin->options['showimicons'] OR $ignore_off_setting))
		{
			$templater = vB_Template::create('im_aim');
				$templater->register('userinfo', $userinfo);
			$userinfo['aimicon'] = $templater->render();
			$userinfo['showaim'] = true;
			$show['hasimicons'] = true;
		}

		if ($userinfo['yahoo'] != '' AND ($vbulletin->options['showimicons'] OR $ignore_off_setting))
		{
			$templater = vB_Template::create('im_yahoo');
				$templater->register('userinfo', $userinfo);
			$userinfo['yahooicon'] = $templater->render();
			$userinfo['showyahoo'] = true;
			$show['hasimicons'] = true;
		}

		if ($userinfo['msn'] != '' AND ($vbulletin->options['showimicons'] OR $ignore_off_setting))
		{
			$templater = vB_Template::create('im_msn');
				$templater->register('userinfo', $userinfo);
			$userinfo['msnicon'] = $templater->render();
			$userinfo['showmsn'] = true;
			$show['hasimicons'] = true;
		}

		if ($userinfo['skype'] != '' AND ($vbulletin->options['showimicons'] OR $ignore_off_setting))
		{
			$userinfo['skypeencoded'] = urlencode($userinfo['skype']);
			$templater = vB_Template::create('im_skype');
				$templater->register('userinfo', $userinfo);
			$userinfo['skypeicon'] = $templater->render();
			$userinfo['showskype'] = true;
			$show['hasimicons'] = true;
		}
	}

	($hook = vBulletinHook::fetch_hook('postbit_imicons')) ? eval($hook) : false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44754 $
|| ####################################################################
\*======================================================================*/
?>