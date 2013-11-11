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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

/**
* Class to do data save/delete operations for infractions
*
* @package	vBulletin
* @version	$Revision: 41072 $
* @date		$Date: 2010-12-14 08:36:30 -0800 (Tue, 14 Dec 2010) $
*/
class vB_DataManager_Infraction extends vB_DataManager
{
	/**
	* Array of recognised and required fields for infractions, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'infractionid'      => array(TYPE_UINT,      REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'infractionlevelid' => array(TYPE_UINT,      REQ_NO,   VF_METHOD),
		'userid'            => array(TYPE_UINT,      REQ_YES),
		'whoadded'          => array(TYPE_UINT,      REQ_YES),
		'points'            => array(TYPE_UINT,      REQ_YES),
		'dateline'          => array(TYPE_UNIXTIME,  REQ_AUTO),
		'note'              => array(TYPE_NOHTML,    REQ_NO),
		'action'            => array(TYPE_UINT,      REQ_NO),
		'actiondateline'    => array(TYPE_UNIXTIME,  REQ_NO),
		'actionuserid'      => array(TYPE_UINT,      REQ_NO),
		'actionreason'      => array(TYPE_NOHTML,    REQ_NO),
		'postid'            => array(TYPE_UINT,      REQ_NO),
		'expires'           => array(TYPE_UNIXTIME,  REQ_NO),
		'threadid'          => array(TYPE_UINT,      REQ_NO),
		'customreason'      => array(TYPE_NOHTML,    REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('infractionid = %1$d', 'infractionid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'infraction';

	/**
	* Verifies that the infractionlevelid is valid and set points and expires if user hasn't explicitly set them
	*
	* @param	integer	infractionleveid key
	*
	* @return	boolean
	*/
	function verify_infractionlevelid(&$infractionlevelid)
	{
		if ($infractionlevelid != $this->existing['infractionlevelid'])
		{
			if (!($infractionlevel = $this->info['infractionlevel']) AND !($infractionlevel = verify_id('infractionlevel', $infractionlevelid, 0, 1)))
			{
				$this->error('invalidid');
				return false;
			}
			else
			{
				if (!$this->setfields['points'])
				{
					$points = intval($infractionlevel['points']);
					if ($infractionlevel['warning'] AND $this->info['warning'])
					{
						$points = 0;
					}
					$this->set('points', $points);
				}

				if (!$this->setfields['expires'])
				{
					switch($infractionlevel['period'])
					{
						case 'H': $expires = TIMENOW + $infractionlevel['expires'] * 3600; break;     # HOURS
						case 'D': $expires = TIMENOW + $infractionlevel['expires'] * 86400; break;    # DAYS
						case 'M': $expires = TIMENOW + $infractionlevel['expires'] * 2592000; break;  # MONTHS
						case 'N': $expires = 0; break;                                                # NEVER
					}
					$this->set('expires', $expires);
				}
			}
		}

		return true;
	}

	/**
	* Updates user's infraction group ids. Call whenever user.ipoints is modified, Do not call from pre_save()
	*
	* @param	integer	Action status of infraction before save
	* @param	integer	Points awarded for this infraction
	*
	*/
	function update_infraction_groups($action, $points)
	{
		if ($action OR !$points)
		{	// Don't go forward if this item didn't start out active or doesn't have any points (warning)
			return;
		}

		if ($userinfo = $this->info['userinfo'] OR ($this->existing['userid'] AND $userinfo = fetch_userinfo($this->existing['userid'])))
		{
			// Fetch latest total points for this user
			if ($pointinfo = $this->registry->db->query_first("
				SELECT ipoints, usergroupid
				FROM " . TABLE_PREFIX . "user
				WHERE userid = $userinfo[userid]
			"))
			{
				$infractiongroupid = 0;
				$infractiongroupids = array();
				$groups = $this->registry->db->query_read("
					SELECT orusergroupid, override
					FROM " . TABLE_PREFIX . "infractiongroup AS infractiongroup
					WHERE infractiongroup.usergroupid IN (-1, $pointinfo[usergroupid])
						AND infractiongroup.pointlevel <= $pointinfo[ipoints]
					ORDER BY pointlevel
				");
				while ($group = $this->registry->db->fetch_array($groups))
				{
					if ($group['override'])
					{
						$infractiongroupid = $group['orusergroupid'];
					}
					$infractiongroupids["$group[orusergroupid]"] = true;
				}

				$userdata =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$userdata->set_existing($userinfo);
				$userdata->set('infractiongroupids', !empty($infractiongroupids) ? implode(',', array_keys($infractiongroupids)) : '');
				$userdata->set('infractiongroupid', $infractiongroupid);
				$userdata->save();
				unset($userdata);
			}
		}
	}

	/**
	* Resets infraction information in user and post record after a reversal or removal
	*
	*/
	function reset_infraction()
	{
		if ($this->existing['action'] != 0)
		{	// Only reset infraction information for an active infraction. Expired and reversed infractions have already done this
			return;
		}

		if ($postinfo = $this->info['postinfo'] OR ($this->existing['postid'] AND $postinfo = fetch_postinfo($this->existing['postid'])))
		{
			$dataman =& datamanager_init('Post', $this->registry, ERRTYPE_SILENT, 'threadpost');
			$dataman->set_existing($postinfo);
			$dataman->set('infraction', 0);
			$dataman->save();
			unset($dataman);
		}

		if ($userinfo = $this->info['userinfo'] OR ($this->existing['userid'] AND $userinfo = fetch_userinfo($this->existing['userid'])))
		{	// Decremement infraction counters and remove any points
			$userdata =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
			$userdata->set_existing($userinfo);
			if ($points = $this->existing['points'])
			{
				$userdata->set('ipoints', "ipoints - $points", false);
				$userdata->set('infractions', 'infractions - 1', false);
			}
			else
			{
				$userdata->set('warnings', 'warnings - 1', false);
			}
			$userdata->save();
			unset($userdata);
		}
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Infraction(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('infractiondata_start')) ? eval($hook) : false;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->fetch_field('userid') AND $this->info['userinfo']['userid'])
		{
			$this->set('userid', $this->info['userinfo']['userid']);
		}

		if (!$this->fetch_field('dateline') AND !$this->condition)
		{
			$this->set('dateline', TIMENOW);
		}

		if (!$this->fetch_field('action') AND !$this->condition)
		{	// active infraction
			$this->set('action', 0);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('infractiondata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		global $vbphrase;

		if (!$this->condition)
		{
			if ($postinfo =& $this->info['postinfo'])
			{
				$dataman =& datamanager_init('Post', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$dataman->set_existing($postinfo);
				$dataman->set('infraction', ($this->fetch_field('points') == 0) ? 1 : 2);
				$dataman->save();
				unset($dataman);

				$threadinfo =& $this->info['threadinfo'];
			}

			if ($userinfo =& $this->info['userinfo'])
			{
				$userdata =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$userdata->set_existing($userinfo);
				if ($points = $this->fetch_field('points'))
				{
					$userdata->set('ipoints', "ipoints + $points", false);
					$userdata->set('infractions', 'infractions + 1', false);
				}
				else
				{
					$userdata->set('warnings', 'warnings + 1', false);
				}
				$userdata->save();
				unset($userdata);

				if ($points)
				{
					$this->update_infraction_groups($this->fetch_field('action'), $points);
				}

				// Insert thread
				if ($this->registry->options['uiforumid'] AND $foruminfo = fetch_foruminfo($this->registry->options['uiforumid']))
				{
					$infractioninfo = array(
						'title'       => $this->fetch_field('customreason') ? unhtmlspecialchars($this->fetch_field('customreason')) : fetch_phrase('infractionlevel' . $this->fetch_field('infractionlevelid') . '_title', 'infractionlevel', '', false, true, 0),
						'points'      => $points,
						'note'        => unhtmlspecialchars($this->fetch_field('note')),
						'message'     => $this->info['message'],
						'username'    => unhtmlspecialchars($userinfo['username']),
						'threadtitle' => unhtmlspecialchars($threadinfo['title']),
					);
					
					if ($threadinfo['prefixid'])
					{
						// need prefix in correct language
						$infractioninfo['prefix_plain'] = fetch_phrase("prefix_$threadinfo[prefixid]_title_plain", 'global', '', false, true, 0, false) . ' ';
					}
					else
					{
						$infractioninfo['prefix_plain'] = '';
					}

					//variables for phrase eval below
					if ($postinfo)
					{
						$infractioninfo['postlink'] = fetch_seo_url('thread|nosession|bburl|js', 
							array('threadid' => $threadinfo['threadid'], 'title' => $infractioninfo['threadtitle']), 
							array('p' => $postinfo['postid'])) . "#post$postinfo[postid]";
					}

					$infractioninfo['userlink'] = fetch_seo_url('member|nosession|bburl|js', 
						array('userid' => $userinfo['userid'], 'username' => $infractioninfo['username']));

					//creates magic vars $subject and $message -- uses variables from current scope.	
					eval(fetch_email_phrases($postinfo ? 'infraction_thread_post' : 'infraction_thread_profile', 0, 
						$points > 0 ? 'infraction_thread_infraction' : 'infraction_thread_warning'));

					$dataman =& datamanager_init('Thread_FirstPost', $this->registry, ERRTYPE_SILENT, 'threadpost');
					$dataman->set_info('forum', $foruminfo);
					$dataman->set_info('is_automated', true);
					$dataman->set_info('mark_thread_read', true);
					$dataman->set('allowsmilie', true);
					$dataman->setr('userid', $this->fetch_field('whoadded'));
					$dataman->set('title', $subject);
					$dataman->setr('pagetext', $message);

					$dataman->setr('forumid', $foruminfo['forumid']);
					$dataman->set('visible', true);
					$threadid = $dataman->save();

					// Update infraction with threadid
					$infdata =& datamanager_init('Infraction', $this->registry, ERRTYPE_SILENT);
					$infractioninfo = array('infractionid' => $this->fetch_field('infractionid'));
					$infdata->set_existing($infractioninfo);
					$infdata->set('threadid', $threadid);
					$infdata->save();
					unset($infdata);
				}

			}
		}
		else if ($this->setfields['action'] AND ($this->fetch_field('action') == 1 OR $this->fetch_field('action') == 2))
		{
			$this->reset_infraction();
			$this->update_infraction_groups($this->existing['action'], $this->existing['points']);

			if ($this->fetch_field('action') == 2 AND $threadid = $this->fetch_field('threadid') AND $threadinfo = fetch_threadinfo($threadid) AND $foruminfo = $this->registry->forumcache["{$threadinfo['forumid']}"] AND $userid = $this->fetch_field('actionuserid'))
			{	// Reversed
				$infractioninfo = array(
					'reason' => unhtmlspecialchars($this->fetch_field('actionreason')),
				);
				eval(fetch_email_phrases('infraction_post', 0, $this->existing['points'] > 0 ? 'infraction_post_infraction' : 'infraction_post_warning'));

				$dataman =& datamanager_init('Post', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$dataman->set_info('thread', $threadinfo);
				$dataman->set_info('forum', $foruminfo);
				$dataman->set('threadid', $threadinfo['threadid']);
				$dataman->set('userid', $userid);
				$dataman->set('allowsmilie', true);
				$dataman->set('visible', true);
				$dataman->set('title', $subject);
				$dataman->set('pagetext', $message);

				$dataman->save();
				unset($dataman);
			}
		}

		($hook = vBulletinHook::fetch_hook('infractiondata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$this->reset_infraction();
		$this->update_infraction_groups($this->existing['action'], $this->existing['points']);

		($hook = vBulletinHook::fetch_hook('infractiondata_delete')) ? eval($hook) : false;
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 41072 $
|| ####################################################################
\*======================================================================*/
?>
