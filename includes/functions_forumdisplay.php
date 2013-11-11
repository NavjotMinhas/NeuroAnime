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

define('THREAD_FLAG_CLOSED',    1);
define('THREAD_FLAG_INVISIBLE', 2);
define('THREAD_FLAG_DELETED',   4);
define('THREAD_FLAG_STICKY',    8);
define('THREAD_FLAG_POLL',      16);
define('THREAD_FLAG_ATTACH',    32);

// ###################### Start getDotThreads #######################
// --> Queries a list of given ids and generates an array of ids that the user has posted in
function fetch_dot_threads_array($ids)
{
	global $vbulletin;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('dot_threads_array')) ? eval($hook) : false;

	if ($ids AND $vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
	{
		$dotthreads = array();
		$mythreads = $vbulletin->db->query_read_slave("
			SELECT COUNT(*) AS count, threadid, MAX(dateline) AS lastpost
				$hook_query_fields
			FROM " . TABLE_PREFIX . "post AS post
			$hook_query_joins
			WHERE post.userid = " . $vbulletin->userinfo['userid'] . " AND
				post.visible = 1 AND
				post.threadid IN (0$ids)
				$hook_query_where
			GROUP BY threadid
		");

		while ($mythread = $vbulletin->db->fetch_array($mythreads))
		{
			$dotthreads["$mythread[threadid]"]['count'] = $mythread['count'];
			$dotthreads["$mythread[threadid]"]['lastpost'] = vbdate($vbulletin->options['dateformat'], $mythread['lastpost'], true);
		}

		return $dotthreads;
	}

	return false;

}

// ###################### Start parseThreadData #######################
// translate stuff from the db into data for a template like threadbit
// note: this function requires the use of $iconcache - include it in $specialtemplates!
function process_thread_array($thread, $lastread = -1, $allowicons = -1)
{
	global $vbphrase, $foruminfo, $vbulletin;
	global $newthreads, $dotthreads, $perpage, $ignore, $show;
	static $pperpage;

	if ($pperpage == 0)
	{ // lets calculate posts per page
		// the following code should be left just in case we plan to use this function in showthread at some point

		if (THIS_SCRIPT != 'showthread')
		{
			$pperpage = sanitize_maxposts();
		}
		else
		{
			$pperpage = sanitize_maxposts($perpage);
		}
	}

	// init value for the inline moderation checkbox
	$thread['checkbox_value'] = 0;

	if (
		can_moderate($thread['forumid'], 'caneditthreads')
		OR
		(
			$thread['open']
			AND
			$thread['postuserid'] == $vbulletin->userinfo['userid']
			AND
			($forumperms = fetch_permissions($thread['forumid'])) AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost'])
			AND
			($thread['dateline'] + $vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW
		)
	)
	{
		$thread['title_editable'] = true;
		$show['ajax_js'] = true;
	}

	if (
		$thread['open'] != 10
		AND
		(
			can_moderate($thread['forumid'], 'canopenclose')
			OR
			(
				$thread['postuserid'] == $vbulletin->userinfo['userid']
				AND
				($forumperms = fetch_permissions($thread['forumid'])) AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose'])
			)
		)
	)
	{
		$thread['openclose_editable'] = true;
		$show['ajax_js'] = true;
	}

	/*if ($thread['postuserid'] == $vbulletin->userinfo['userid'])
	{
		$forumperms = fetch_permissions($thread['forumid']);
		if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose'])
		{
			$thread['openclose_editable'] .= "<div><strong>Own thread</strong></div>";
		}
	}*/

	if ($allowicons == -1)
	{
		$allowicons = $vbulletin->forumcache["$thread[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'];
	}

	if ($lastread == -1)
	{
		$lastread = $vbulletin->userinfo['lastvisit'];
	}

	$show['rexpires'] = $show['rmanage'] = $show['threadmoved'] = $show['paperclip'] = $show['unsubscribe'] = false;

	// thread forumtitle
	if (empty($thread['forumtitle']))
	{
		$thread['forumtitle'] = $vbulletin->forumcache["$thread[forumid]"]['title'];
	}
	$thread['forumtitleclean'] = $vbulletin->forumcache["$thread[forumid]"]['title_clean'];

	// word wrap title
	if ($vbulletin->options['wordwrap'] != 0)
	{
		$thread['threadtitle'] = fetch_word_wrapped_string($thread['threadtitle']);
	}

	$thread['threadtitle'] = fetch_censored_text($thread['threadtitle']);

	if ($thread['prefixid'])
	{
		$thread['prefix_plain_html'] = htmlspecialchars_uni($vbphrase["prefix_$thread[prefixid]_title_plain"]);
		$thread['prefix_rich'] = $vbphrase["prefix_$thread[prefixid]_title_rich"];
	}
	else
	{
		$thread['prefix_plain_html'] = '';
		$thread['prefix_rich'] = '';
	}

	// format thread preview if there is one
	if ($ignore["$thread[postuserid]"])
	{
		$thread['preview'] = '';
	}
	else if (isset($thread['preview']))
	{
		if($vbulletin->options['threadpreview'] > 0)
		{
			$thread['preview'] = strip_quotes($thread['preview']);
			$thread['preview'] = htmlspecialchars_uni(fetch_censored_text(
				fetch_trimmed_title(strip_bbcode($thread['preview'], false, true, true, true),
					$vbulletin->options['threadpreview'])
			));
		}
		//if the preview text is disabled, then make sure that we don't leave this function
		//with it set.
		else
		{
			unset($thread['preview']);
		}
	}

	// thread last reply date/time
	$thread['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $thread['lastpost'], true);
	$thread['lastposttime'] = vbdate($vbulletin->options['timeformat'], $thread['lastpost']);

	// post reply date/time (for search results as posts mainly)
	if ($thread['postdateline'])
	{
		$thread['postdate'] = vbdate($vbulletin->options['dateformat'], $thread['postdateline'], true);
		$thread['posttime'] = vbdate($vbulletin->options['timeformat'], $thread['postdateline']);
	}
	else
	{
		$thread['postdate'] = '';
		$thread['posttime'] = '';
	}

	// get the thread starting date and time if applicable
	if ($thread['dateline'])
	{
		$thread['startdate'] = vbdate($vbulletin->options['dateformat'], $thread['dateline'], true);
		$thread['starttime'] = vbdate($vbulletin->options['timeformat'], $thread['dateline']);
	}
	else
	{
		$thread['startdate'] = '';
		$thread['starttime'] = '';
	}

	// non magical thread status
	if (2 == $thread['visible'])
	{
		$thread['status']['deleted'] = 'deleted';
	}
	else if (!$thread['visible'])
	{
		$thread['status']['moderated'] = 'moderated';
	}

	// thread not moved
	if ($thread['open'] != 10)
	{
		// allow ratings?
		if ($foruminfo['allowratings'])
		{
			// show votes?
			if ($thread['votenum'] AND $thread['votenum'] >= $vbulletin->options['showvotes'])
			{
				$thread['voteavg'] = vb_number_format($thread['votetotal'] / $thread['votenum'], 2);
				$thread['rating'] = intval(round($thread['votetotal'] / $thread['votenum']));
			}
			// do not show votes
			else
			{
				$thread['rating'] = 0;
			}
		}
		// do not allow ratings
		else
		{
			 $thread['rating'] = 0;
			 $thread['votenum'] = 0;
		}

		// moderated thread?
		if (!$thread['visible'])
		{
			$thread['moderatedprefix'] = $vbphrase['moderated_thread_prefix'];
			$thread['checkbox_value'] += THREAD_FLAG_INVISIBLE;
		}
		else
		{
			$thread['moderatedprefix'] = '';
		}

		// deleted thread?
		if ($thread['visible'] == 2)
		{
			$thread['checkbox_value'] += THREAD_FLAG_DELETED;
			$thread['del_reason'] = fetch_censored_text($thread['del_reason']);
		}

		// sticky thread?
		if ($thread['sticky'])
		{
			$show['sticky'] = true;
			$thread['typeprefix'] = $vbphrase['sticky_thread_prefix'];
			$thread['checkbox_value'] += THREAD_FLAG_STICKY;
		}
		else
		{
			$show['sticky'] = false;
			$thread['typeprefix'] = '';
		}

		// thread contains poll?
		if ($thread['pollid'] != 0)
		{
			$thread['typeprefix'] .= $vbphrase['poll_thread_prefix'];
			$thread['checkbox_value'] += THREAD_FLAG_POLL;
		}

		// multipage nav
		$thread['totalposts'] = $thread['replycount'] + 1;
		$total =& $thread['totalposts'];
		if (
			($vbulletin->options['allowthreadedmode'] == 0 OR
				($vbulletin->userinfo['threadedmode'] == 0 AND
					empty($vbulletin->GPC[COOKIE_PREFIX . 'threadedmode'])) OR
				$vbulletin->GPC[COOKIE_PREFIX . 'threadedmode'] == 'linear') AND
			$thread['totalposts'] > $pperpage AND $vbulletin->options['linktopages']
		)
		{
			$thread['totalpages'] = ceil($thread['totalposts'] / $pperpage);
			#$address2 = "$thread[highlight]";

			$curpage = 0;

			$thread['pagenav'] = '';
			$show['pagenavmore'] = false;

			while ($curpage++ < $thread['totalpages'])
			{
				if ($vbulletin->options['maxmultipage'] AND $curpage > $vbulletin->options['maxmultipage'])
				{
					$lastpageinfo = array(
						'page' => $thread['totalpages']
					);

					if ($thread['highlight'])
					{
						$lastpageinfo['highlight'] = urlencode(implode(' ', $thread['highlight']));
					}

					$thread['lastpagelink'] = fetch_seo_url('thread', $thread, $lastpageinfo, 'threadid', 'threadtitle');
					$show['pagenavmore'] = true;
					break;
				}

				$pageinfo = array(
					'page' => $curpage
				);
				if ($thread['highlight'])
				{
					$pageinfo['highlight'] = urlencode(implode(' ', $thread['highlight']));
				}

				$pagenumbers = fetch_start_end_total_array($curpage, $pperpage, $thread['totalposts']);
				$templater = vB_Template::create('threadbit_pagelink');
					$templater->register('curpage', $curpage);
					$templater->register('pageinfo', $pageinfo);
					$templater->register('thread', $thread);
				$thread['pagenav'] .= ' ' . $templater->render();
			}
		}
		// do not show pagenav
		else
		{
			$thread['pagenav'] = '';
		}

		// allow thread icons?
		if ($allowicons)
		{
			// get icon from icon cache
			if ($thread['threadiconid'])
			{
				$thread['threadiconpath'] = $vbulletin->iconcache["$thread[threadiconid]"]['iconpath'];
				$thread['threadicontitle'] = $vbulletin->iconcache["$thread[threadiconid]"]['title'];
			}

			// show poll icon
			if ($thread['pollid'] != 0)
			{
				$show['threadicon'] = true;
				$thread['threadiconpath'] = vB_Template_Runtime::fetchStyleVar('imgdir_misc') . "/poll_posticon.gif";
				$thread['threadicontitle'] = $vbphrase['poll'];
			}
			// show specified icon
			// this looks obsolete -- there isn't anything that appears to set threadiconpath
			// anywhere outside of this file.
			else if ($thread['threadiconpath'])
			{
				$show['threadicon'] = true;
			}
			// show default icon
			else if (!empty($vbulletin->options['showdeficon']))
			{
				$show['threadicon'] = true;
				$thread['threadiconpath'] = $vbulletin->options['showdeficon'];
				$thread['threadicontitle'] = '';
			}
			// do not show icon
			else
			{
				$show['threadicon'] = false;
				$thread['threadiconpath'] = '';
				$thread['threadicontitle'] = '';
			}
		}
		// do not allow icons
		else
		{
			$show['threadicon'] = false;
			$thread['threadiconpath'] = '';
			$thread['threadicontitle'] = '';
		}

		// thread has attachment?
		if ($thread['attach'] > 0)
		{
			$show['paperclip'] = true;
			$thread['checkbox_value'] += THREAD_FLAG_ATTACH;
		}

		// folder icon generation
		$thread['status'] = array();

		// show dot folder?
		if ($vbulletin->userinfo['userid'] AND $vbulletin->options['showdots'] AND $dotthreads["$thread[threadid]"])
		{
			$thread['status']['dot'] = 'dot';
			$thread['dot_count'] = $dotthreads["$thread[threadid]"]['count'];
			$thread['dot_lastpost'] = $dotthreads["$thread[threadid]"]['lastpost'];
		}
		// show hot folder?
		if ($vbulletin->options['usehotthreads'] AND (($thread['replycount'] >= $vbulletin->options['hotnumberposts'] AND $vbulletin->options['hotnumberposts'] > 0) OR ($thread['views'] >= $vbulletin->options['hotnumberviews'] AND $vbulletin->options['hotnumberviews'] > 0)))
		{
			$thread['status']['hot'] = 'hot';
		}
		// show locked folder?
		if (!$thread['open'])
		{
			$thread['status']['lock'] = 'lock';
			$thread['checkbox_value'] += THREAD_FLAG_CLOSED;
		}

		// show new folder?
		if ($thread['lastpost'] > $lastread)
		{
			if ($vbulletin->options['threadmarking'] AND $thread['threadread'])
			{
				$threadview = $thread['threadread'];
			}
			else
			{
				$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
			}

			if ($thread['lastpost'] > $threadview)
			{
				$thread['status']['new'] = 'new';
				$show['gotonewpost'] = true;
			}
			else
			{
				$newthreads--;
				$show['gotonewpost'] = false;
			}
		}
		else
		{
			$show['gotonewpost'] = false;
		}

		// format numbers nicely
		$thread['replycount'] = vb_number_format($thread['replycount']);
		$thread['views'] = vb_number_format($thread['views']);
		$thread['realthreadid'] = $thread['threadid'];
	}
	// thread moved?
	else
	{
		// thread has been moved, lets delete if required!
		if (can_moderate($thread['forumid']))
		{
			if ($thread['expires'])
			{
				if ($thread['expires'] <= TIMENOW)
				{
					$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
					$threadman->set_existing($thread);
					$threadman->delete(false, true, NULL, false);
					unset($threadman);
				}

				$show['rexpires'] = true;
				$thread['expiredate'] = vbdate($vbulletin->options['dateformat'], $thread['expires']);
				$thread['expiretime'] = vbdate($vbulletin->options['timeformat'], $thread['expires']);
			}
			$show['rmanage']  = can_moderate($thread['forumid'], 'canmanagethreads');
		}
		$thread['realthreadid'] = $thread['threadid'];
		$thread['redirectthreadid'] = $thread['threadid'];
		$thread['threadid'] = $thread['pollid'];
		$thread['replycount'] = '-';
		$thread['views'] = '-';
		$show['threadicon'] = false;
		$thread['status'] = array('moved' => 'moved', 'new' => ($thread['lastpost'] > $lastread) ? 'new' : false);
		$thread['pagenav'] = '';
		$thread['movedprefix'] = $vbphrase['moved_thread_prefix'];
		$thread['rating'] = 0;
		$thread['votenum'] = 0;
		$show['gotonewpost'] = false;
		$thread['showpagenav'] = false;
		$show['sticky'] = false;
		$show['threadmoved'] = true;
	}

	$show['subscribed'] = iif ($thread['issubscribed'], true, false);
	$show['pagenav'] = iif ($thread['pagenav'] != '', true, false);
	$show['guestuser'] = iif (!$thread['postuserid'], true, false);
	$show['threadrating'] = iif ($thread['rating'] > 0, true, false);
	$show['threadcount'] = iif ($thread['dot_count'], true, false);
	$show['taglist'] = ($vbulletin->options['threadtagging'] AND !empty($thread['taglist']));

	($hook = vBulletinHook::fetch_hook('threadbit_process')) ? eval($hook) : false;

	$thread['statusstring'] = implode(' ', $thread['status']);

	return $thread;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42666 $
|| ####################################################################
\*======================================================================*/
