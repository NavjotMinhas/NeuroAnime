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

class vB_APIMethod_api_blogcategorylist extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin;

		$vbulletin->input->clean_array_gpc('r', array(
			'userid' => TYPE_UINT,
		));

		// verify the userid exists, don't want useless entries in our table.
		if ($vbulletin->GPC['userid'] AND $vbulletin->GPC['userid'] != $vbulletin->userinfo['userid'])
		{
			if (!($userinfo = fetch_userinfo($vbulletin->GPC['userid'])))
			{
				standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink']));
			}

			// are we a member of this user's blog?
			if (!is_member_of_blog($vbulletin->userinfo, $userinfo))
			{
				print_no_permission();
			}

			$userid = $userinfo['userid'];

			/* Blog posting check */
			if (
					!($userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpost'])
						OR
					!($userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']	)
			)
			{
				print_no_permission();
			}
		}
		else
		{
			$userinfo =& $vbulletin->userinfo;
			$userid = '';
			/* Blog posting check, no guests! */
			if (
					!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
						OR
					!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpost'])
						OR
					!$vbulletin->userinfo['userid']
			)
			{
				print_no_permission();
			}
		}

		require_once(DIR . '/includes/blog_functions_shared.php');
		prepare_blog_category_permissions($userinfo, true);

		$globalcats = $this->construct_category($userinfo, 'global');
		$localcats = $this->construct_category($userinfo, 'local');

		return array(
			'globalcategorybits' => $globalcats,
			'localcategorybits' => $localcats
		);
	}

	private function construct_category($userinfo, $type = 'global')
	{
		global $vbulletin;

		require_once(DIR . '/includes/blog_functions_category.php');
		if (!$userinfo['permissions'])
		{
			cache_permissions($userinfo, false);
		}
		if (!isset($vbulletin->vbblog['categorycache']["$userinfo[userid]"]))
		{
			fetch_ordered_categories($userinfo['userid']);
		}

		if (empty($vbulletin->vbblog['categorycache']["$userinfo[userid]"]))
		{
			return;
		}

		if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
		{
			$cantusecats = array_unique(array_merge($userinfo['blogcategorypermissions']['cantpost'], $vbulletin->userinfo['blogcategorypermissions']['cantpost'], $userinfo['blogcategorypermissions']['cantview'], $vbulletin->userinfo['blogcategorypermissions']['cantview']));
		}
		else
		{
			$cantusecats = array_unique(array_merge($userinfo['blogcategorypermissions']['cantpost'], $userinfo['blogcategorypermissions']['cantview']));
		}

		$result = array();
		foreach ($vbulletin->vbblog['categorycache']["$userinfo[userid]"] AS $blogcategoryid => $category)
		{
			if (!($userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cancreatecategory']) AND $category['userid'])
			{
				continue;
			}
			else if (in_array($blogcategoryid, $cantusecats))
			{
				continue;
			}
			else if
			(
				($type == 'global' AND $category['userid'] != 0)
					OR
				($type == 'local' AND $category['userid'] == 0)
			)
			{
				continue;
			}

			$result[] = array('blogcategoryid' => $category['blogcategoryid'],
			'category' => array('title' => $category['title']));
		}

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/