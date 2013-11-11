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
* Postbit optimized for announcements
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Announcement extends vB_Postbit
{
	/**
	* Processes the date information and determines whether the post is new or old
	*/
	function process_date_status()
	{
		$mindate = TIMENOW - 2592000; // 30 days

		if ($this->post['readannouncement'] OR $this->post['startdate'] <= $mindate)
		{
			$this->post['statusicon'] = 'old';
			$this->post['statustitle'] = $vbphrase['old'];
		}
		else
		{
			$this->post['statusicon'] = 'new';
			$this->post['statustitle'] = $vbphrase['unread_date'];
		}
		$this->post['postdate'] = vbdate($this->registry->options['dateformat'], $this->post['startdate'], true);
		$this->post['posttime'] = vbdate($this->registry->options['timeformat'], $this->post['startdate']);

		$this->post['startdate'] = vbdate($this->registry->options['dateformat'], $this->post['startdate']);
		$this->post['enddate'] = vbdate($this->registry->options['dateformat'], $this->post['enddate']);
	}

	/**
	* Processes the post's icon.
	*/
	function process_icon()
	{
		global $show;

		$show['messageicon'] = false;
	}

	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show;

		$this->post['editlink'] = (can_moderate($this->forum['forumid'], 'canannounce'))
			? 'announcement.php?' . $this->registry->session->vars['sessionurl'] . 'do=edit&amp;a=' . $this->post['announcementid']
			: false;
		$this->post['replylink'] = false;
		$this->post['forwardlink'] = false;

		$show['postcount'] = false;
		$show['reputationlink'] = false;
		$show['reportlink'] = false;
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		if ($this->post['announcementoptions'] & $this->registry->bf_misc_announcementoptions['allowbbcode'] AND
			$this->post['announcementoptions'] & $this->registry->bf_misc_announcementoptions['parseurl'])
		{
			require_once(DIR . '/includes/functions_newpost.php');
			$this->post['pagetext'] = convert_url_to_bbcode($this->post['pagetext']);
		}
		$this->post['message'] = $this->bbcode_parser->parse($this->post['pagetext'], 'announcement', ($this->post['announcementoptions'] & $this->registry->bf_misc_announcementoptions['allowsmilies']));
	}

	/**
	* overriding the parent function
	*/
	function process_signature()
	{
		 if ($this->post['announcementoptions'] & $this->registry->bf_misc['announcementoptions']['signature']
			AND trim($this->post['signature']) != ''
			AND  (!$this->registry->userinfo['userid'] OR $this->registry->userinfo['showsignatures'])
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
}

/**
* Postbit optimized for private messages
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Pm extends vB_Postbit
{
	/**
	* Determines whether the post should actually be displayed.
	*
	* @return	bool	True if the post should be displayed; false otherwise
	*/
	function is_displayable()
	{
		// PMs ignore tachy status
		return true;
	}

	/**
	* Processes miscellaneous post items at the beginning of the construction process.
	*/
	function prep_post_start()
	{
		if ($userinfo = fetch_userinfo($this->post['fromuserid'], 3))
		{
			$this->post = array_merge($this->post, $userinfo);
		}
		else
		{
			// Deleted user?
			$this->post['userid'] = 0;
			$this->post['postusername'] = $this->post['fromusername'];
		}

		parent::prep_post_start();
	}

	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show;

		$this->post['forwardlink'] = false;

		if ($show['pmsendlink'])
		{
			if ($this->post['userid'])
			{
				$this->post['replylink'] = 'private.php?' . $this->registry->session->vars['sessionurl'] . 'do=newpm&amp;pmid=' . $this->post['pmid'];
			}
			$this->post['forwardlink'] = 'private.php?' . $this->registry->session->vars['sessionurl'] . 'do=newpm&amp;forward=1&amp;pmid=' . $this->post['pmid'];
		}
		else
		{
			$this->post['replylink'] = false;
			$this->post['forwardlink'] = false;
		}

		$show['postcount'] = false;
		$show['reputationlink'] = false;
		// report pm icon?
		$show['reportlink'] = (
			($this->registry->options['rpforumid'] OR
			($this->registry->options['enableemail'] AND $this->registry->options['rpemail'])) AND
			($this->registry->userinfo['userid'] != $this->post['fromuserid'])
		);
		$this->post['reportlink'] = 'private.php?' . $this->registry->session->vars['sessionurl'] . 'do=report&amp;pmid=' . $this->post['pmid'];
	}

	/**
	* Processes the date information and determines whether the post is new or old
	*/
	function process_date_status()
	{
		if ($this->post['messageread'])
		{
			$this->post['statusicon'] = 'old';
			$this->post['statustitle'] = $vbphrase['old'];
		}
		else
		{
			$this->post['statusicon'] = 'new';
			$this->post['statustitle'] = $vbphrase['unread_date'];
		}

		// format date/time
		$this->post['postdate'] = vbdate($this->registry->options['dateformat'], $this->post['dateline'], true);
		$this->post['posttime'] = vbdate($this->registry->options['timeformat'], $this->post['dateline']);
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = parse_pm_bbcode($this->post['message'], $this->post['allowsmilie']);
	}
}

/**
* Postbit optimized for soft deleted posts
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Post_Deleted extends vB_Postbit_Post
{
	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_deleted';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}

/**
* Postbit optimized for global ignored (tachy'd) posts
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Post_Global_Ignore extends vB_Postbit_Post
{

	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_ignore_global';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}

/**
* Postbit optimized for regular (ignore list) ignored posts
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Post_Ignore extends vB_Postbit_Post
{

	public function construct_postbit(&$post)
	{
		$post['viewpostlink'] = fetch_seo_url('thread', $this->thread, array('p' => $post['postid'])) . "#post$post[postid]";
		return parent::construct_postbit($post);
	}

	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_ignore';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}


	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}

/**
* Postbit optimized for user notes
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Usernote extends vB_Postbit
{
	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show, $vbulletin;

		if ((($this->post['posterid'] == $vbulletin->userinfo['userid']) AND ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['caneditownusernotes']))
			OR ($this->post['viewself'] AND ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmanageownusernotes']))
			OR (!$this->post['viewself'] AND ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canmanageothersusernotes'])))
		{
			$this->post['editlink'] = 'usernote.php?' . $this->registry->session->vars['sessionurl'] . 'do=editnote&amp;usernoteid=' . $this->post['usernoteid'];
		}

		$this->post['replylink'] = false;
		$this->post['forwardlink'] = false;

		$show['postcount'] = false;
		$show['reputationlink'] = false;
		$show['reportlink'] = false;
		$show['showpostlink'] = false;
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = parse_usernote_bbcode($this->post['message'], $this->post['allowsmilies']);
	}
}

/**
* Postbit optimized for RSS
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_External extends vB_Postbit
{
	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_external';

	/**
	* Template method. Calls all the appropriate methods to build a post and then evaluates the template.
	*
	* @param	array	Post information
	*
	* @return	string	HTML for the post
	*/
	function construct_postbit(&$post)
	{
		$this->post =& $post;
		$thread =& $this->thread;
		$forum =& $this->forum;

		global $show, $vbphrase;

		($hook = vBulletinHook::fetch_hook('postbit_display_start')) ? eval($hook) : false;

		$imgdir_attach = vB_Template_Runtime::fetchStyleVar('imgdir_attach');
		if (!preg_match('#^[a-z]+:#siU', vB_Template_Runtime::fetchStyleVar('imgdir_attach')))
		{
			if ($imgdir_attach[0] == '/')
			{
				$url = parse_url($this->registry->options['bburl']);
				vB_Template_Runtime::addStyleVar('imgdir_attach', 'http://' . $url['host'] . vB_Template_Runtime::fetchStyleVar('imgdir_attach'), 'imgdir');
			}
			else
			{
				vB_Template_Runtime::addStyleVar('imgdir_attach', $this->registry->options['bburl'] . '/' . vB_Template_Runtime::fetchStyleVar('imgdir_attach'), 'imgdir');
			}
		}

		$this->parse_bbcode();

		// Remove session urls from all templates so changing sessionhashes don't trigger the post to appear new
		$sessionurl = $this->registry->session->vars['sessionurl'];
		$this->registry->session->vars['sessionurl'] = '';

		$this->process_attachments();

		if ($post['attachments'])
		{
			$search = '#(href|src)="attachment\.php#si';
			$replace = '\\1="' . $this->registry->options['bburl'] . '/' . 'attachment.php';
			$items = array(
				't' => $post['thumbnailattachments'],
				'a' => $post['imageattachments'],
				'l' => $post['imageattachmentlinks'],
				'o' => $post['otherattachments'],
			);

			$newitems = preg_replace($search, $replace, $items);
			unset($items);
			$post['thumbnailattachments'] =& $newitems['t'];
			$post['imageattachments'] =& $newitems['a'];
			$post['imageattachmentlinks'] =& $newitems['l'];
			$post['otherattachments'] =& $newitems['o'];
		}
		// execute hook
		($hook = vBulletinHook::fetch_hook('postbit_display_complete')) ? eval($hook) : false;

		// evaluate template
		$postid =& $post['postid'];

		$templater = vB_Template::create($this->template_prefix . $this->templatename);
			$templater->register('ad_location', $ad_location);
			$templater->register('pageinfo_post', $pageinfo_post);
			$templater->register('post', $post);
			$templater->register('postid', $postid);
			$templater->register('template_hook', $template_hook);
			$templater->register('thread', $thread);
		$retval = $templater->render();

		$this->registry->session->vars['sessionurl'] = $sessionurl;
		vB_Template_Runtime::addStyleVar('imgdir_attach', $imgdir_attach, 'imgdir');

		return $retval;
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = $this->bbcode_parser->parse($this->post['message'], $this->post['forumid'], false);
	}
}

/**
* Postbit optimized for Auto-Moderated posts
*
* @package 		vBulletin
* @version		$Revision: 40911 $
* @date 		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*
*/
class vB_Postbit_Post_AutoModerated extends vB_Postbit_Post
{
	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_automoderated';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}



/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
?>
