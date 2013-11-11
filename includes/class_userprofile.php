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

/**
* User Profile Class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_UserProfile
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry;

	/**
	* Array of unprepared userinfo
	*
	* @var	array
	*/
	var $userinfo = array();

	/**
	* Array of prepared User Information
	*
	* @var	vB_Registry
	*/
	var $prepared = array();

	/**
	* Array of fields and their corresponding preperation function
	*
	* @var	array
	*/
	var $prepare_methods = array(
		'age'           => 'prepare_birthday',
		'albuminfo'     => 'prepare_albuminfo',
		'avatarurl'     => 'prepare_avatar',
		'avatarsize'    => 'prepare_avatar',
		'birthday'      => 'prepare_birthday',
		'canbefriend'   => 'prepare_canbefriend',
		'displayemail'  => 'prepare_displayemail',
		'friendcount'   => 'prepare_friendcount',
		'homepage'      => 'prepare_homepage',
		'imicons'       => 'prepare_im_icons',
		'joindate'      => 'prepare_joindate',
		'lastactivity'  => 'prepare_lastactivity',
		'lastvm_date'   => 'prepare_visitor_message_stats',
		'lastvm_time'   => 'prepare_visitor_message_stats',
		'lastvm_user'   => 'prepare_visitor_message_stats',
		'lastpost'      => 'prepare_lastpost',
		'musername'     => 'prepare_musername',
		'myprofile'     => 'prepare_myprofile',
		'onlinestatus'	=> 'prepare_onlinestatus',
		'vm_total'      => 'prepare_visitor_message_stats',
		'posts'         => 'prepare_posts',
		'postsperday'   => 'prepare_postsperday',
		'profilepic'    => 'prepare_profilepic',
		'profileurl'    => 'prepare_profileurl',
		'profilevisits' => 'prepare_profilevisits',
		'referrals'     => 'prepare_referrals',
		'reputation'    => 'prepare_reputation',
		'show'          => 'prepare_show_variables',
		'signature'     => 'prepare_signature',
		'usernotecount' => 'prepare_usernote',
		'usernoteinfo'  => 'prepare_usernote',
		'userperms'     => 'prepare_userperms',
		'wolocation'    => 'prepare_wolocation',
	);

	/**
	* Array of variables to 'automatically' prepare for each profile view
	*
	* @var array
	*/
	var $auto_prepare = array(
		'userid',
		'username',
		'musername',
		'myprofile',
		'usertitle',
		'userperms',
		'imicons',
		'profilepic',
		'rank',
		'reputation',
		'onlinestatus',
		'lastactivity',
		'wolocation',
		'usernoteinfo',
		'isfriend',
		'canbefriend',
		'show',
	);

	/**
	* Constructor - automatically prepares the needed variables.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object
	* @param	array		An Array of user information
	*/
	function vB_UserProfile(&$registry, $userinfo)
	{
		$this->registry =& $registry;
		$this->userinfo = $userinfo;

		($hook = vBulletinHook::fetch_hook('userprofile_create')) ? eval($hook) : false;

		foreach ($this->auto_prepare AS $preparefield)
		{
			$this->prepare($preparefield);
		}
	}

	/**
	* Prepares a Profile Field
	*
	* @param	string	The name of a field to be prepared
	*/
	function prepare($field, $info = null)
	{
		if (isset($this->prepared["$field"]))
		{
			return;
		}

		$handled = false;
		($hook = vBulletinHook::fetch_hook('userprofile_prepare')) ? eval($hook) : false;

		if (!$handled)
		{
			if (isset($this->prepare_methods["$field"]))
			{
				$method = $this->prepare_methods["$field"];
				$this->$method($info);
			}
			else
			{
				$this->prepared["$field"] = $this->userinfo["$field"];
			}
		}
	}

	/**
	* Prepares Information Regarding the user's Albums
	*
	*/
	function prepare_albuminfo()
	{
		if ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums'])
		{

			require_once(DIR . '/includes/functions_album.php');
			$state = array('public');
			if (can_view_private_albums($this->userinfo['userid']))
			{
				$state[] = 'private';
			}
			if (can_view_profile_albums($this->userinfo['userid']))
			{
				$state[] = 'public';
			}

			$albums = $this->registry->db->query_first_slave("
				SELECT COUNT(*) AS albumcount, SUM(album.visible) AS picturecount
				FROM " . TABLE_PREFIX . "album AS album
				WHERE album.userid = ". $this->userinfo['userid'] . "
					AND album.state IN ('" . implode("', '", $state) . "')
			");

			$this->prepared['albuminfo'] = array(
				'albumcount'   => vb_number_format($albums['albumcount']),
				'picturecount' => vb_number_format($albums['picturecount'])
			);
		}
	}

	/**
	* Prepares the User's Avatar
	*
	*/
	function prepare_avatar()
	{
		fetch_avatar_from_userinfo($this->userinfo, true, false);

		if ($this->userinfo['avatarurl'] == '' OR !$this->registry->options['avatarenabled'] OR ($this->userinfo['hascustomavatar'] AND !($this->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->userinfo['adminavatar']))
		{
			$this->prepared['avatarurl'] = '';
			$this->prepared['avatarsize'] = '';
		}
		else
		{
			$this->prepared['avatarsize'] = ($this->userinfo['avatarwidth'] ? ' width="' . $this->userinfo['avatarwidth'] . '"' : '')
				 . ($this->userinfo['avatarheight'] ? ' height="' . $this->userinfo['avatarheight']. '"' : '');
			$this->prepared['avatarurl'] = $this->userinfo['avatarurl'];
		}
	}

	/**
	* Prepares the User's Birthday
	*
	*/
	function prepare_birthday()
	{
		$userinfo =& $this->userinfo;

		$this->prepared['age'] = '';
		$this->prepared['birthday'] = '';

		if ($userinfo['birthday'] AND $userinfo['showbirthday'] > 0)
		{
			$bday = explode('-', $userinfo['birthday']);

			$year = vbdate('Y', TIMENOW, false, false);
			$month = vbdate('n', TIMENOW, false, false);
			$day = vbdate('j', TIMENOW, false, false);
			if ($year > $bday[2] AND $bday[2] != '0000' AND $userinfo['showbirthday'] != 3)
			{
				$this->prepared['age'] = $year - $bday[2];
				if ($month < $bday[0] OR ($month == $bday[0] AND $day < $bday[1]))
				{
					$this->prepared['age']--;
				}
			}

			if ($userinfo['showbirthday'] >= 2)
			{
				if ($year > $bday[2] AND $bday[2] > 1901 AND $bday[2] != '0000' AND $userinfo['showbirthday'] == 2)
				{
					require_once(DIR . '/includes/functions_misc.php');
					$this->registry->options['calformat1'] = mktimefix($this->registry->options['calformat1'], $bday[2]);
					if ($bday[2] >= 1970)
					{
						$yearpass = $bday[2];
					}
					else
					{
						// day of the week patterns repeat every 28 years, so
						// find the first year >= 1970 that has this pattern
						$yearpass = $bday[2] + 28 * ceil((1970 - $bday[2]) / 28);
					}
					$this->prepared['birthday'] = vbdate($this->registry->options['calformat1'], mktime(0, 0, 0, $bday[0], $bday[1], $yearpass), false, true, false);
				}
				else
				{
					// lets send a valid year as some PHP3 don't like year to be 0
					$this->prepared['birthday'] = vbdate($this->registry->options['calformat2'], mktime(0, 0, 0, $bday[0], $bday[1], 1992), false, true, false);
				}
				if ($this->prepared['birthday'] == '')
				{
					if ($bday[2] == '0000')
					{
						$this->prepared['birthday'] = "$bday[0]-$bday[1]";
					}
					else
					{
						$this->prepared['birthday'] = "$bday[0]-$bday[1]-$bday[2]";
					}
				}
			}
		}
	}

	/**
	* Prepares the User's Display Email
	*
	*/
	function prepare_displayemail()
	{
		$this->prepared['displayemail'] = ($this->registry->options['displayemails'] AND !$this->registry->options['secureemail'] AND $this->userinfo['showemail'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']) ? $this->userinfo['email'] : '';
	}

	/**
	* Prepares a count of the user's Friends
	*
	*/
	function prepare_friendcount()
	{
		if (!isset($this->prepared['friendcount']) AND ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_friends']))
		{
			$this->prepared['friendcount'] = vb_number_format($this->userinfo['friendcount']);
		}
		else
		{
			$this->prepared['friendcount'] = 0;
		}
	}

	/**
	* Prepares the User's Homepage
	*
	*/
	function prepare_homepage()
	{
		$this->prepared['homepage'] = (($this->userinfo['homepage'] != 'http://' AND $this->userinfo['homepage'] != '') ? $this->userinfo['homepage'] : '');
	}

	/**
	* Prepares the User's Instant Messaging Icons
	*
	*/
	function prepare_im_icons()
	{
		global $show;

		$this->prepared['icq'] = $this->userinfo['icq'];
		$this->prepared['aim'] = $this->userinfo['aim'];
		$this->prepared['msn'] = $this->userinfo['msn'];
		$this->prepared['yahoo'] = $this->userinfo['yahoo'];
		$this->prepared['skype'] = $this->userinfo['skype'];
		construct_im_icons($this->prepared);
		$this->prepared['hasimicons'] = $show['hasimicons'];
		$this->prepared['hasimdetails'] = ($this->prepared['icq'] OR $this->prepared['aim'] OR $this->prepared['msn'] OR $this->prepared['yahoo'] OR $this->prepared['skype']) ? true : false;
		$this->prepared['imicons'] = true;
	}

	/**
	* Stores information regarding whether the user currently logged in
	* should be considered as able to become a friend of the user being displayed
	*
	*/
	function prepare_canbefriend()
	{
		if
		(
			$this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_friends']
			AND $this->registry->userinfo['userid']
			AND $this->userinfo['userid'] != $this->registry->userinfo['userid']
			AND $this->registry->userinfo['permissions']['genericpermissions2'] & $this->registry->bf_ugp_genericpermissions2['canusefriends']
			AND $this->prepared['userperms']['genericpermissions2'] & $this->registry->bf_ugp_genericpermissions2['canusefriends']
		)
		{
			$this->prepared['canbefriend'] = !$this->userinfo['isfriend'];
			$this->prepared['requestedfriend'] = $this->userinfo['requestedfriend'];
		}
		else
		{
			$this->prepared['canbefriend'] = false;
		}
	}

	/**
	* Prepares the User's Join Date
	*
	*/
	function prepare_joindate()
	{
		$this->prepared['joindate'] = vbdate($this->registry->options['dateformat'], $this->userinfo['joindate']);
	}

	/**
	* Prepares the User's Last Activity Information
	*
	*/
	function prepare_lastactivity()
	{
		if (
			$this->userinfo['lastactivity']
			AND (
				!$this->userinfo['invisible']
				OR
				($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden'])
				OR
				$this->userinfo['userid'] == $this->registry->userinfo['userid']
			)
		)
		{
			$this->prepared['lastactivitydate'] = vbdate($this->registry->options['dateformat'], $this->userinfo['lastactivity'], true);
			$this->prepared['lastactivitytime'] = vbdate($this->registry->options['timeformat'], $this->userinfo['lastactivity'], true);
		}
		else
		{
			$this->prepared['lastactivitydate'] = '';
			$this->prepared['lastactivitytime'] = '';
		}
	}

	 /**
	* Prepares the User's last post information
	*
	*/
	function prepare_lastpost()
	{
		global $show, $vbphrase;

		$this->prepared['lastposttitle'] = '';

		if ($this->registry->options['profilelastpost'] AND $this->userinfo['lastpost'] AND !in_coventry($this->userinfo['userid']))
		{
			if ($this->userinfo['lastpostid'] AND $getlastpost = $this->registry->db->query_first_slave("
				SELECT thread.title, thread.threadid, thread.forumid, thread.postuserid, post.postid, post.dateline
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
				WHERE post.postid = " . $this->userinfo['lastpostid'] . "
					AND post.visible = 1
					AND thread.visible = 1
			"))
			{
				$this->setup_lastpost_internal($getlastpost);
			}

			if ($this->prepared['lastposttitle'] === '')
			{
				$getlastposts = $this->registry->db->query_read_slave("
					SELECT thread.title, thread.threadid, thread.forumid, thread.postuserid, post.postid, post.dateline
					FROM " . TABLE_PREFIX . "post AS post
					INNER JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
					WHERE thread.visible = 1
						AND post.userid  = " . $this->userinfo['userid'] . "
						AND post.visible = 1
					ORDER BY post.dateline DESC
					LIMIT 20
				");
				while ($getlastpost = $this->registry->db->fetch_array($getlastposts))
				{
					if ($this->setup_lastpost_internal($getlastpost))
					{
						break;
					}
				}
			}
		}

		$this->prepared['lastpost'] = true;
	}

	/**
	* Internal function to check the permissions on the last post data
	* and set the fields as necessary.
	*
	* @param	array	Array of last post information
	*
	* @return	boolean	True if last post data prepared
	*/
	function setup_lastpost_internal($getlastpost)
	{
		$forumperms = fetch_permissions($getlastpost['forumid']);

		if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canview']) OR !($forumperms & $this->registry->bf_ugp_forumpermissions['canviewthreads']))
		{
			return false;
		}
		else if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers']) AND ($getlastpost['postuserid'] != $this->registry->userinfo['userid'] OR !$this->registry->userinfo['userid']))
		{
			return false;
		}
		else if (!verify_forum_password($getlastpost['forumid'], $this->registry->forumcache["$getlastpost[forumid]"]['password'], false))
		{
			return false;
		}

		$this->prepared['lastposttitle'] = $getlastpost['title'];
		$this->prepared['lastposturl'] = fetch_seo_url('thread', $getlastpost, array('p' => $getlastpost['postid'] . "#post$getlastpost[postid]"));
		$this->prepared['lastpostdate'] = vbdate($this->registry->options['dateformat'], $getlastpost['dateline'], true);
		$this->prepared['lastposttime'] = vbdate($this->registry->options['timeformat'], $getlastpost['dateline']);

		return true;
	}

	/**
	* Prepares the User's 'marked-up' username
	*
	*/
	function prepare_musername()
	{
		$this->prepared['musername'] = fetch_musername($this->userinfo);
	}

	/**
	* Stores information as to whether the user is viewing their own profile
	*
	*/
	function prepare_myprofile()
	{
		$this->prepared['myprofile'] = ($this->registry->userinfo['userid'] == $this->prepared['userid']) ? true : false;
	}

	/**
	* Prepares the user's online status
	*
	*/
	function prepare_onlinestatus()
	{
		if (!isset($this->prepared['onlinestatus']))
		{
			require_once(DIR . '/includes/functions_bigthree.php');
			fetch_online_status($this->userinfo, true);
			$this->prepared['onlinestatus'] = $this->userinfo['onlinestatus'];
		}
	}

	/**
	* Prepares the User's Post Count
	*
	*/
	function prepare_posts()
	{
		$this->prepared['posts'] = vb_number_format($this->userinfo['posts']);
	}

	/**
	* Prepares the User's Posts Per Day
	*
	*/
	function prepare_postsperday()
	{
		$jointime = (TIMENOW - $this->userinfo['joindate']) / 86400; // Days Joined
		if ($jointime < 1)
		{
			// User has been a member for less than one day.
			$postsperday = vb_number_format($this->userinfo['posts']);
		}
		else
		{
			$postsperday = vb_number_format($this->userinfo['posts'] / $jointime, 2);
		}

		$this->prepared['postsperday'] = $postsperday;
	}

	/**
	* Prepares the User's Profile Picture
	*
	*/
	function prepare_profilepic()
	{
		if ($this->registry->options['profilepicenabled'] AND $this->userinfo['profilepic'] AND ($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseeprofilepic'] OR $this->registry->userinfo['userid'] == $this->userinfo['userid']) AND ($this->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canprofilepic'] OR $this->userinfo['adminprofilepic']))
		{
			if ($this->registry->options['usefileavatar'])
			{
				$this->prepared['profilepicurl'] = $this->registry->options['profilepicurl'] . '/profilepic' . $this->prepared['userid'] . '_' . $this->userinfo['profilepicrevision'] . '.gif';
			}
			else
			{
				$this->prepared['profilepicurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->prepared['userid'] . "&amp;dateline=" . $this->userinfo["profilepicdateline"] . "&amp;type=profile";
			}

			if ($this->userinfo['ppwidth'] AND $this->userinfo['ppheight'])
			{
				$this->prepared['profilepicsize'] = ' width="' . $this->userinfo["ppwidth"] . '" height="' . $this->userinfo["ppheight"] . '" ';
			}
		}
		else
		{
			$this->prepared['profilepicurl'] = '';
		}
	}

	/**
	* Prepares the URL of the User's Profile
	*
	*/
	function prepare_profileurl()
	{
		if(!isset($this->prepared['profileurl']))
		{
			$profileurl = create_full_url(fetch_seo_url('member', $this->userinfo));
			if (!preg_match('#^[a-z]+://#i', $profileurl))
			{
				$profileurl = $this->registry->options['bburl'] . '/' . fetch_seo_url('member', $this->userinfo);

			}
			$this->prepared['profileurl'] = $profileurl;
		}
	}

	/**
	* Prepares the User's Profile Visits Count
	*
	*/
	function prepare_profilevisits()
	{
		$this->prepared['profilevisits'] = vb_number_format($this->userinfo['profilevisits']);
	}

	/**
	* Prepares the User's Visitor Message Statistics
	*
	* @param	array	The Latest Visitor Message
	*/
	function prepare_visitor_message_stats($vminfo)
	{
		global $vbphrase;

		if (
			(
				!isset($this->prepared['vm_total'])
					OR
				!isset($this->prepared['lastvm_date'])
					OR
				!isset($this->prepared['lastvm_time'])
			)
				AND
			$this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_visitor_messaging']
				AND
			(
				!$this->userinfo['vm_contactonly']
					OR
				can_moderate(0,'canmoderatevisitormessages')
					OR
				$this->userinfo['userid'] == $this->registry->userinfo['userid']
					OR
				$this->userinfo['bbuser_iscontact_of_user']
			)
				AND
			(
				$this->userinfo['vm_enable']
					OR
				(
					can_moderate(0,'canmoderatevisitormessages')
						AND
					$this->registry->userinfo['userid'] != $this->userinfo['userid']
				)
			)
		)
		{
			require_once(DIR . '/includes/functions_visitormessage.php');

			$state = array('visible');
			if (fetch_visitor_message_perm('canmoderatevisitormessages', $this->userinfo))
			{
				$state[] = 'moderation';
			}
			if (can_moderate(0,'canmoderatevisitormessages') OR ($this->registry->userinfo['userid'] == $this->userinfo['userid'] AND $this->registry->userinfo['permissions']['visitormessagepermissions'] & $this->registry->bf_ugp_visitormessagepermissions['canmanageownprofile']))
			{
				$state[] = 'deleted';
				$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (visitormessage.vmid = deletionlog.primaryid AND deletionlog.type = 'visitormessage')";
			}
			else
			{
				$deljoinsql = '';
			}

			$state_or = array(
				"visitormessage.state IN ('" . implode("','", $state) . "')"
			);

			if (!fetch_visitor_message_perm('canmoderatevisitormessages', $this->userinfo))
			{
				$state_or[] = "(visitormessage.postuserid = " . $this->registry->userinfo['userid'] . " AND state = 'moderation')";
			}

			$coventry = '';

			if ($this->registry->options['globalignore'] != '')
			{
				if (!can_moderate(0, 'candeletevisitormessages') AND !can_moderate(0, 'canremovevisitormessages'))
				{
					require_once(DIR . '/includes/functions_bigthree.php');

					$coventry = fetch_coventry('string');
				}
			}
			if (empty($vminfo))
			{
				$vminfo = $this->registry->db->query_first("
					SELECT COUNT(*) AS messages, MAX(visitormessage.dateline) AS dateline
					FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
					$deljoinsql
					WHERE visitormessage.userid = " . $this->prepared['userid'] . "
						AND (" . implode(" OR ", $state_or) . ")
					" . ($coventry ? "AND visitormessage.postuserid NOT IN (" . $coventry . ")" : '') . "
				");
			}

			$this->prepared['vm_total'] = intval($vminfo['messages']);

			if ($vminfo['dateline'])
			{
				$this->prepared['lastvm_time'] = vbdate($this->registry->options['timeformat'], $vminfo['dateline'], true);
				$this->prepared['lastvm_date'] = vbdate($this->registry->options['dateformat'], $vminfo['dateline'], true);
			}
			else
			{
				$this->prepared['lastvm_date'] = $vbphrase['never'];
				$this->prepared['lastvm_time'] = '';
			}

		}
	}

	/**
	* Prepares the User's Reputation Display
	*
	*/
	function prepare_reputation()
	{
		if (!isset($this->prepared['reputationdisplay']))
		{
			if(!isset($this->prepared['userperms']))
			{
				$this->prepare('userperms');
			}

			fetch_reputation_image($this->userinfo, $this->prepared['userperms']);

			$this->prepared['reputationdisplay'] = $this->userinfo['reputationdisplay'];
		}
	}

	/**
	* Prepares the User's Referrals
	*
	*/
	function prepare_referrals()
	{
		if ($this->registry->options['usereferrer'])
		{
			$refcount = $this->registry->db->query_first_slave("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "user
				WHERE referrerid = " . $this->userinfo['userid'] . "
					AND usergroupid NOT IN (3,4)
			");
			$this->prepared['referrals'] = vb_number_format($refcount['count']);
		}
		else
		{
			$this->prepared['referrals'] = '';
		}
	}

	/**
	* Prepares $show varaiables
	*
	*/
	function prepare_show_variables()
	{
		global $show;

		if (!isset($this->prepared['show']))
		{
			$show['email'] = (
				$this->userinfo['showemail']
				AND $this->registry->options['enableemail']
				AND $this->registry->options['displayemails']
				AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
				AND $this->registry->userinfo['userid']
			);

			$show['pm'] = (
				$this->registry->options['enablepms']
				AND $this->registry->userinfo['permissions']['pmquota']
	 			AND $this->registry->userinfo['userid']
				AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 				OR ($this->userinfo['receivepm']
		 				AND $this->prepared['userperms']['pmquota']
		 				AND (!$this->userinfo['receivepmbuddies']
		 					OR can_moderate()
		 					OR strpos(" {$this->userinfo[buddylist]} ", ' ' . $this->registry->userinfo['userid'] . ' ') !== false
		 				)
		 			)
	 			)
	 		);

	 		if (!$this->registry->options['showimicons'])
			{
				$show['textimicons'] = true;
			}

			if ($this->registry->userinfo['userid']
				AND $this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_visitor_messaging']
				AND $this->userinfo['vm_enable']
				AND $this->prepared['userperms']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canviewmembers']
				AND (
					!$this->userinfo['vm_contactonly']
					OR $this->userinfo['userid'] == $this->registry->userinfo['userid']
					OR $this->userinfo['bbuser_iscontact_of_user']
					OR can_moderate(0,'canmoderatevisitormessages')
				)
				AND ((
						$this->userinfo['userid'] == $this->registry->userinfo['userid']
						AND $this->registry->userinfo['permissions']['visitormessagepermissions'] & $this->registry->bf_ugp_visitormessagepermissions['canmessageownprofile']
					)
					OR (
						$this->userinfo['userid'] != $this->registry->userinfo['userid']
						AND $this->registry->userinfo['permissions']['visitormessagepermissions'] & $this->registry->bf_ugp_visitormessagepermissions['canmessageothersprofile']
					)
				)
			)
			{
				$show['post_visitor_message'] = true;
			}

			$buddylist = explode(' ', trim($this->registry->userinfo['buddylist']));
			$ignorelist = explode(' ', trim($this->registry->userinfo['ignorelist']));

			$show['addbuddylist'] = (
				$this->registry->userinfo['userid']
				AND !in_array($this->userinfo['userid'], $buddylist)
				AND !$this->prepared['myprofile']
			);
			$show['removebuddylist'] = (
				in_array($this->userinfo['userid'], $buddylist)
				AND !$this->prepared['isfriend']
			);

			$show['addignorelist'] = (
				$this->registry->userinfo['userid']
				AND !in_array($this->userinfo['userid'], $ignorelist)
				AND !$this->prepared['myprofile']
			);
			$show['removeignorelist'] = in_array($this->userinfo['userid'], $ignorelist);

			$show['userlists'] = (
				$this->prepared['canbefriend']
				OR $this->prepared['isfriend']
				OR $show['addbuddylist']
				OR $show['removebuddylist']
				OR $show['addignorelist']
				OR $show['removeignorelist']
			);

			$show['messagelinks'] = ($show['post_visitor_message'] OR $show['email'] OR $show['pm']);
			$show['contactlinks'] = ($show['messagelinks'] OR $show['hasimicons']);

			$cssperms_user = $this->prepared['userperms']['usercsspermissions'];
			$cssperms_bits = $this->registry->bf_ugp_usercsspermissions;
			$show['can_customize_profile'] = (
				$this->prepared['myprofile']
				AND $this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_profile_styling']
				AND (
					$cssperms_user & $cssperms_bits['caneditfontfamily']
					OR $cssperms_user & $cssperms_bits['caneditfontsize']
					OR $cssperms_user & $cssperms_bits['caneditcolors']
					OR $cssperms_user & $cssperms_bits['caneditbgimage']
					OR $cssperms_user & $vcssperms_bits['caneditborders']
				)
			);

			$this->prepared['show'] = true;
		}
	}

	/**
	* Prepares the User's Signature
	*
	*/
	function prepare_signature()
	{
		global $show;

		if ($this->userinfo['signature'] AND $this->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canusesignature'])
		{
			require_once(DIR . '/includes/class_bbcode.php');
			$bbcode_parser = new vB_BbCodeParser($this->registry, fetch_tag_list());
			$bbcode_parser->set_parse_userinfo($this->userinfo, $this->userinfo['permissions']);
			$this->prepared['signature'] = $bbcode_parser->parse($this->userinfo['signature'], 'signature');
		}
	}

	/**
	* Prepares Information regarding the Users's usernotes
	*
	*/
	function prepare_usernote()
	{
		global $show;

		if (!($this->prepared['userperms']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canbeusernoted']))
		{
			$this->prepared['usernotecount'] = 0;
			$this->prepared['usernoteinfo'] = array();
			$show['usernoteview'] = false;
			return;
		}

		if
		(
			($this->prepared['userid'] == $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canviewownusernotes'])
			OR 	($this->prepared['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->userinfo->bf_ugp_genericpermissions['canviewothersusernotes'])
		)
		{
			$show['usernotes'] = true;
		}

		if
		(
			($this->prepared['userid'] == $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canpostownusernotes'])
			OR 	($this->prepared['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canpostothersusernotes'])
		)
		{
			$show['usernotes'] = true;
			$show['usernotepost'] = true;
		}

		$usernote = $this->registry->db->query_first_slave("
			SELECT MAX(dateline) AS lastpost, COUNT(*) AS total
			FROM " . TABLE_PREFIX . "usernote AS usernote
			WHERE userid = " . $this->userinfo['userid']
		);

		$this->prepared['usernotecount'] = vb_number_format($usernote['total']);

		$show['usernoteview'] = intval($usernote['total']) ? true : false;

		$usernote['lastpostdate'] = vbdate($this->registry->options['dateformat'], $usernote['lastpost'], true);
		$usernote['lastposttime'] = vbdate($this->registry->options['timeformat'], $usernote['lastpost'], true);
		$this->prepared['usernoteinfo'] = $usernote;
	}

	/**
	* Cache's the User's Permissions
	*
	*/
	function prepare_userperms()
	{
		$this->prepared['userperms'] = cache_permissions($this->userinfo, false);
	}

	/**
	* Prepares the Who's Online Location
	*
	*/
	function prepare_wolocation()
	{
		if (!isset($this->prepared['action']))
		{
			$this->prepared['action'] = $this->userinfo['action'];
			$this->prepared['where'] = $this->userinfo['where'];
		}
	}

	function prepare_blogurl()
	{
		if (!isset($this->prepared['blogurl']))
		{
			$this->prepared['blogurl'] = fetch_seo_url('blog', $this->userinfo);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 37230 $
|| ####################################################################
\*======================================================================*/
?>