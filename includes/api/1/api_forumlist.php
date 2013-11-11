<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

class vB_APIMethod_api_forumlist extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin;

		require_once(DIR . '/includes/functions_forumlist.php');

		if (empty($vbulletin->iforumcache))
		{
			cache_ordered_forums(1, 1);
		}

		return $this->getforumlist(-1);
	}

	private function getforumlist($parentid)
	{
		global $vbulletin, $counters, $lastpostarray;

		if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
		{
			return;
		}

		// call fetch_last_post_array() first to get last post info for forums
		if (!is_array($lastpostarray))
		{
			fetch_last_post_array($parentid);
		}

		foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
		{
			$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
			if (
					(
						!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
						AND
						($vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums']))
					)
					OR
					!$vbulletin->forumcache["$forumid"]['displayorder']
					OR
					!($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['active'])
				)
			{
				continue;
			}
			else
			{
				$forum = $vbulletin->forumcache["$forumid"];
				$is_category = !((bool) ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']));
				
				$forum['threadcount'] = $counters["$forum[forumid]"]['threadcount'];
				$forum['replycount'] = $counters["$forum[forumid]"]['replycount'];

				$forum2 = array(
					'forumid' => $forum['forumid'],
					'title' => $forum['title'],
					'description' => $forum['description'],
					'title_clean' => $forum['title_clean'],
					'description_clean' => $forum['description_clean'],
					'parentid' => $forum['parentid'],
					'threadcount' => $forum['threadcount'],
					'replycount' => $forum['replycount'],
					'is_category' => $is_category,
					'depth' => $forum['depth'],
				);

				$children = explode(',', trim($forum['childlist']));
				if (sizeof($children) > 2)
				{
					if ($subforums = $this->getforumlist($forumid))
					{
						$forum2['subforums'] = $subforums;
					}
				}

				$forums[] = $forum2;
			} // if can view
		} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

		return $forums;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/