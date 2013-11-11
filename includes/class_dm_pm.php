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
* Class to do data save/delete operations for PRIVATE MESSAGES
* Note: you may only do inserts with this class.
*
* The following "info" options are supported:
*	- savecopy (bool): whether to save a copy in the sent items folder
*	- receipt (bool): whether to ask for a read receipt
*	- cantrackpm (bool): whether the person sending the message has permission to track PMs
*	- parentpmid (int): the parent PM this is in response to; will be resolved to the first message in a "thread"
*	- replypmid (int): the PM this one is in response to
*	- forward (bool): whether this is a forward of the parent (true) or a reply (false)
*
* @package	vBulletin
* @version	$Revision: 34509 $
* @date		$Date: 2009-12-15 16:58:30 -0800 (Tue, 15 Dec 2009) $
*/
class vB_DataManager_PM extends vB_DataManager
{
	/**
	* Array of recognised and required fields for private messages, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'pmtextid'      => array(TYPE_UINT,     REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'fromuserid'    => array(TYPE_UINT,     REQ_YES),
		'fromusername'  => array(TYPE_STR,      REQ_YES),
		'title'         => array(TYPE_STR,      REQ_YES,  VF_METHOD),
		'message'       => array(TYPE_STR,      REQ_YES,  VF_METHOD),
		'touserarray'   => array(TYPE_NOCLEAN,  REQ_YES,  VF_METHOD),
		'iconid'        => array(TYPE_UINT,     REQ_NO),
		'dateline'      => array(TYPE_UINT,     REQ_NO),
		'showsignature' => array(TYPE_BOOL,     REQ_NO),
		'allowsmilie'   => array(TYPE_BOOL,     REQ_NO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pmtext';

	/**
	* Array to store stuff to save to pm/pmtext tables
	*
	* @var	array
	*/
	var $pmtext = array();

	/**
	* Switch to allow overriding of quota / receivepm errors when sending pm
	*
	* @var	bool
	*/
	var $overridequota = false;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_PM(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pmdata_start')) ? eval($hook) : false;
	}

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('pmtextid = %1$d', 'pmtextid');

	function set_condition()
	{
		trigger_error('The PM data manager does not support updates at this time.', E_USER_ERROR);
	}

	// #############################################################################
	// data validation

	/**
	* Verifies that the title field is valid
	*
	* @param	string	Title / Subject
	*
	* @return	boolean
	*/
	function verify_title(&$title)
	{
		if ($title == '')
		{
			$this->error('nosubject');
			return false;
		}
		else
		{
			$title = fetch_censored_text($title);
			return true;
		}
	}

	/**
	* Verifies that the message field is valid
	*
	* @param	string	Message text
	*
	* @return	boolean
	*/
	function verify_message(&$message)
	{
		if ($message == '')
		{
			$this->error('nosubject');
			return false;
		}

		// check message length
		if (empty($this->info['is_automated']) AND $this->registry->options['pmmaxchars'] > 0)
		{
			$messagelength = vbstrlen($message);
			if ($messagelength > $this->registry->options['pmmaxchars'])
			{
				$this->error('toolong', $messagelength, $this->registry->options['pmmaxchars']);
				return false;
			}
		}

		$message = fetch_censored_text($message);

		require_once(DIR . '/includes/functions_video.php');
		$message = parse_video_bbcode($message);

		return true;
	}

	/**
	* Verifies that the touserarray is valid
	*
	* @param	mixed	To user array (array of userid/username pairs, or serialized array)
	*
	* @return	boolean
	*/
	function verify_touserarray(&$tousers)
	{
		// if $tousers is not an array, attempt to unserialize it
		if (!is_array($tousers))
		{
			$tousers = @unserialize($tousers);

			if (!is_array($tousers))
			{
				$this->error('to_user_array_invalid');
				return false;
			}
		}

		// temporary variables
		$userarray = array();
		$cclist = array();
		$bcclist = array();
		$checkarray = array();
		$error = false;

		// validate each entry in the array
		foreach ($tousers AS $key => $item)
		{
			if (is_array($item))
			{
				foreach($item AS $key2 => $subitem)
				{
					$checkarray["$key2"] = ${$key . 'list'}["$key2"] = $subitem;
				}
			}
			else
			{
				$checkarray["$key"] = $bcclist["$key"] = $item;
			}
		}

		foreach($checkarray AS $userid => $username)
		{
			// check userid
			$userid = intval($userid);
			if ($userid < 1)
			{
				$error = true;
			}

			// check username
			$username = trim($username);
			if ($username == '')
			{
				$error = true;
			}
			if (preg_match('#<|>|"|(&(?!\#?[0-9a-z]+;))#', $username))
			{
				// doesn't appear to be htmlspecialchars'd
				if ($cclist["$userid"])
				{
					$cclist["$userid"] = htmlspecialchars_uni($username);
				}
				if ($bcclist["$userid"])
				{
					$bcclist["$userid"] = htmlspecialchars_uni($username);
				}
			}

			$userarray["$userid"] = $username;

			// and add the user id to the recipients array
			$this->info['recipients']["$userid"] = fetch_userinfo($userid);
		}

		// final check for errors
		if ($error)
		{
			$this->error('to_user_array_invalid');
			return false;
		}
		else
		{
			$output = array();
			if (!empty($cclist))
			{
				$output['cc'] =& $cclist;
			}
			if (!empty($bcclist))
			{
				$output['bcc'] =& $bcclist;
			}

			$tousers = serialize($output);
			return true;
		}
	}

	// #############################################################################
	// extra data setting

	/**
	* Accepts a list of recipients names to create the touserarray field
	*
	* @param	string	Single user name, or semi-colon separated list of user names
	* @param	array	$permissions array for sending user.
	*
	* @return	boolean
	*/
	function set_recipients($recipientlist, &$permissions, $type = 'bcc')
	{
		$names = array();      // names in the recipient list
		$users = array();      // users from the recipient list found in the user table
		$notfound = array();   // names from the recipient list NOT found in the user table
		$recipients = array(); // users to whom the message WILL be sent
		$errors = array();

		$recipientlist = trim($recipientlist);

		$this->info['permissions'] =& $permissions;

		if (!empty($this->info['is_automated']))
		{
			$this->overridequota = true;
		}

		// pmboxfull needs $fromusername defined
		if (($fromusername = $this->fetch_field('fromusername')) === null)
		{
			trigger_error('Set fromusername before calling set_recipients()', E_USER_ERROR);
		}

		if (($fromuserid = $this->fetch_field('fromuserid')) === null)
		{
			trigger_error('Set fromuserid before calling set_recipients()', E_USER_ERROR);
		}
		$fromuser = fetch_userinfo($fromuserid);

		// check for valid recipient string
		if ($recipientlist == '')
		{
			return false;
		}

		// split multiple recipients into an array
		if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $recipientlist)) // multiple recipients attempted
		{
			$recipientlist = preg_split('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $recipientlist, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($recipientlist AS $recipient)
			{
				$recipient = trim($recipient);
				if ($recipient != '')
				{
					$names[] = htmlspecialchars_uni($recipient);
				}
			}
		}
		// just a single user
		else
		{
			$names[] = htmlspecialchars_uni($recipientlist);
		}

		// check for max allowed recipients
		if ($permissions['pmsendmax'] > 0)
		{
			$this->info['numusers'] += sizeof($names);
		}

		// query recipients
		$checkusers = $this->dbobject->query_read_slave("
			SELECT usertextfield.*, user.*
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
			WHERE username IN('" . implode('\', \'', array_map(array($this->dbobject, 'escape_string'), $names)) . "')
			ORDER BY user.username
		");

		// build array of checked users
		while ($checkuser = $this->dbobject->fetch_array($checkusers))
		{
			$lowname = vbstrtolower($checkuser['username']);

			$checkuserperms = fetch_permissions(0, $checkuser['userid'], $checkuser);
			if ($checkuserperms['pmquota'] < 1 AND !$this->overridequota) // can't use pms
			{
				if ($checkuser['options'] & $this->registry->bf_misc_useroptions['receivepm'])
				{
					// This will cause the 'can't receive pms' error below to be triggered
					$checkuser['options'] -= $this->registry->bf_misc_useroptions['receivepm'];
				}
			}

			$users["$lowname"] = $checkuser;
		}

		$this->dbobject->free_result($checkusers);

		// check to see if any recipients were not found
		foreach ($names AS $name)
		{
			$lowname = vbstrtolower($name);
			if (!isset($users["$lowname"]))
			{
				$notfound[] = $name;
			}
		}
		if (!empty($notfound)) // error - some users were not found
		{
			$this->error('pmrecipientsnotfound', implode("</li>\r\n<li>", $notfound));
			return false;
		}

		// run through recipients to check if we can insert the message
		foreach ($users AS $lowname => $user)
		{
			if (!($user['options'] & $this->registry->bf_misc_useroptions['receivepm']) AND !$this->overridequota)
			{
				// recipient has private messaging disabled
				$this->error('pmrecipturnedoff', $user['username']);
				return false;
			}
			else if (($user['options'] & $this->registry->bf_misc_useroptions['receivepmbuddies']) AND strpos(" $user[buddylist] ", " $fromuser[userid] ") === false AND !can_moderate() AND !$this->overridequota)
			{
				// recipient receives PMs only from buddies and sender is not on the list and not board staff
				$this->error('pmrecipturnedoff', $user['username']);
				return false;
			}
			else
			{
				// don't allow a tachy user to sends pms to anyone other than himself
				if (in_coventry($fromuser['userid'], true) AND $user['userid'] != $fromuser['userid'])
				{
					$this->info['tostring']["$type"]["$user[userid]"] = $user['username'];
					continue;
				}
				else if (strpos(" $user[ignorelist] ", ' ' . $fromuser['userid'] . ' ') !== false AND !$this->overridequota)
				{
					// recipient is ignoring sender
					if ($permissions['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$recipients["$lowname"] = true;
						$this->info['tostring']["$type"]["$user[userid]"] = $user['username'];
					}
					else
					{
						// bbuser is being ignored by recipient - do not send, but do not error
						$this->info['tostring']["$type"]["$user[userid]"] = $user['username'];
						continue;
					}
				}
				else
				{
					cache_permissions($user, false);
					if ($user['permissions'] < 1)
					{
						// recipient has no pm permission
						$this->error('pmusernotallowed', $user['username']);
					}
					else
					{
						if ($user['pmtotal'] >= $user['permissions']['pmquota'] AND !$this->overridequota)
						{
							// recipient is over their pm quota, is the sender allowed to ignore it?
							if ($permissions['pmpermissions'] & $this->registry->bf_ugp_pmpermissions['canignorequota'])
							{
								$recipients["$lowname"] = true;
								$this->info['tostring']["$type"]["$user[userid]"] = $user['username'];
							}
							else if ($user['usergroupid'] != 3 AND $user['usergroupid'] != 4)
							{
								$touserinfo =& $user;
								eval(fetch_email_phrases('pmboxfull', $touserinfo['languageid'], '', 'email'));
								vbmail($touserinfo['email'], $emailsubject, $emailmessage, true);
								$this->error('pmquotaexceeded', $user['username']);
							}
							else
							{
								$this->error('pmquotaexceeded', $user['username']);
							}
						}
						else
						{
							if (!($user['options'] & $this->registry->bf_misc_useroptions['pmboxwarning']) AND $user['permissions']['pmquota'] AND ((($user['pmtotal'] + 1 ) / $user['permissions']['pmquota']) >= .9))
							{	// Send email about box being almost full
								$this->info['pmwarning']["$user[userid]"] = true;
							}

							// okay, send the message!
							$recipients["$lowname"] = true;
							$this->info['tostring']["$type"]["$user[userid]"] = $user['username'];
						}
					}
				}
			}
		}

		if (empty($this->errors))
		{
			foreach ($recipients AS $lowname => $bool)
			{
				$user =& $users["$lowname"];

				$this->info['recipients']["$user[userid]"] = $user;
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	// #############################################################################
	// data saving

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

		if (empty($this->errors))
		{
			if (empty($this->info['tostring']))
			{
				$this->error('pminvalidrecipient', $this->registry->session->vars['sessionurl_q']);
				return false;
			}
			else if ($this->info['numusers'] > $this->info['permissions']['pmsendmax'])
			{
				$this->error('pmtoomanyrecipients', $this->info['numusers'], $this->info['permissions']['pmsendmax']);
				return false;
			}
		}

		// Check if the parent pm is already a child of a thread
		if ($parentpmid = intval($this->info['parentpmid']))
		{
			$pm = $this->dbobject->query_first_slave("
				SELECT parentpmid
				FROM " . TABLE_PREFIX . "pm
				WHERE pmid = $parentpmid
					AND userid = " . intval($this->fetch_field('fromuserid'))
			);

			$this->info['parentpmid'] = (intval($pm['parentpmid']) ? $pm['parentpmid'] : $parentpmid);
		}

		$tostring = serialize($this->info['tostring']);
		$this->do_set('touserarray', $tostring);

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pmdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true, $result = false)
	{
		$pmtextid = ($this->existing['pmtextid'] ? $this->existing['pmtextid'] : $this->pmtext['pmtextid']);
		$fromuserid = intval($this->fetch_field('fromuserid'));
		$fromusername = $this->fetch_field('fromusername');
		$parentpmid = intval($this->info['parentpmid']);

		if (!$this->condition)
		{
			if (is_array($this->info['recipients']))
			{
				$receipt_sql = array();
				$popupusers = array();
				$warningusers = array();

				require_once(DIR . '/includes/class_bbcode_alt.php');
				$plaintext_parser = new vB_BbCodeParser_PlainText($this->registry, fetch_tag_list());
				$plaintext_title = unhtmlspecialchars($this->fetch_field('title'));

				// insert records for recipients
				foreach ($this->info['recipients'] AS $userid => $user)
				{
					/*insert query*/
					$query = $this->dbobject->query_write($sql = "INSERT INTO " . TABLE_PREFIX . "pm (pmtextid, userid, parentpmid) VALUES ($pmtextid, $user[userid], $parentpmid)");

					// ensure all subsequent pm's use a common parentpmid
					if (!$parentpmid)
					{
						$parentpmid = $this->info['parentpmid'] = $this->dbobject->insert_id();
					}

					if ($this->info['receipt'])
					{
						$receipt_sql[] = "(" . $this->dbobject->insert_id() . ", $fromuserid, $user[userid],
							'" . $this->dbobject->escape_string($user['username']) . "', '" . $this->dbobject->escape_string($this->pmtext['title']) .
							"', " . TIMENOW . ")";
					}

					if ($user['pmpopup'])
					{
						$popupusers[] = $user['userid'];
					}

					$email_phrases = array(
						'pmreceived' => 'pmreceived',
						'pmboxalmostfull' => 'pmboxalmostfull'
					);
					($hook = vBulletinHook::fetch_hook('pmdata_postsave_recipient')) ? eval($hook) : false;

					if (($user['options'] & $this->registry->bf_misc_useroptions['emailonpm']) AND $user['usergroupid'] != 3 AND $user['usergroupid'] != 4)
					{
						$touserinfo =& $user;
						$plaintext_parser->set_parsing_language($touserinfo['languageid']);
						$plaintext_message = $plaintext_parser->parse($this->fetch_field('message'), 'privatemessage');

						eval(fetch_email_phrases($email_phrases['pmreceived'], $touserinfo['languageid'], '', 'email'));
						vbmail($touserinfo['email'], $emailsubject, $emailmessage);
					}

					if (!empty($this->info['pmwarning']["$user[userid]"]) AND !($user['options'] & $this->registry->bf_misc_useroptions['pmboxwarning']))
					{	// email user about pm box nearly being full
						$warningusers[] = $user['userid'];
						$touserinfo =& $user;
						eval(fetch_email_phrases($email_phrases['pmboxalmostfull'], $touserinfo['languageid'], '', 'email'));
						vbmail($touserinfo['email'], $emailsubject, $emailmessage, true);
					}
				}

				// insert receipts
				if (!empty($receipt_sql) AND $this->info['cantrackpm'])
				{
					/*insert query*/
					$this->dbobject->query_write("INSERT INTO " . TABLE_PREFIX . "pmreceipt\n\t(pmid, userid, touserid, tousername, title, sendtime)\nVALUES\n\t" . implode(",\n\t", $receipt_sql));
				}


				$querysql = array(
					"pmtotal = pmtotal + 1",
					"pmunread = pmunread + 1"
				);

				if (!empty($warningusers))
				{
					$querysql[] = "
					options =
					CASE
						WHEN userid IN(" . implode(', ', $warningusers) . ") THEN options | " . $this->registry->bf_misc_useroptions['pmboxwarning'] . "
					ELSE options
					END
					";
				}
				if (!empty($popupusers))
				{
					$querysql[] = "
					pmpopup =
					CASE
						WHEN userid IN(" . implode(', ', $popupusers) . ") THEN 2
					ELSE pmpopup
					END
					";
				}

				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "user
					SET " . implode(', ', $querysql) . "
					WHERE userid IN(" . implode(', ', array_keys($this->info['recipients'])) . ")
				");

			}

			// update replied to / forwarded message 'messageread' status
			if (!empty($this->info['replypmid']))
			{
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "pm SET
						messageread = " . ($this->info['forward'] ? 3 : 2) . "
					WHERE userid = $fromuserid AND pmid = " . intval($this->info['replypmid'])
				);

				// mark receipt as read
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "pmreceipt
					SET readtime = " . TIMENOW . ",
						denied = 0
					WHERE pmid = " . intval($this->info['replypmid']) . "
					AND touserid = " . $fromuserid . "
					AND readtime = 0
				");
			}

			// save a copy in the sent items folder
			if ($this->info['savecopy'])
			{
				/*insert query*/
				$this->dbobject->query_write("
					INSERT INTO " . TABLE_PREFIX . "pm
						(pmtextid, userid, folderid, messageread, parentpmid)
					VALUES
						($pmtextid, $fromuserid, -1, 1, $parentpmid)
				");

				$user = fetch_userinfo($fromuserid);
				$userdm =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$userdm->set_existing($user);
				$userdm->set('pmtotal', 'pmtotal + 1', false);
				$userdm->save();
				unset($userdm);
			}

			// log message for throttling
			$frompermissions = fetch_permissions(0, $fromuserid);
			if ($this->registry->options['pmthrottleperiod'] AND $frompermissions['pmthrottlequantity'])
			{
				$throttle_sql = array();
				foreach ($this->info['recipients'] AS $user)
				{
					$throttle_sql[] = "($fromuserid, " . TIMENOW . ")";
				}

				if (!empty($throttle_sql))
				{
					$this->registry->db->query_write("
						INSERT INTO " . TABLE_PREFIX . "pmthrottle (userid, dateline) VALUES " .
						implode(",\n\t", $throttle_sql)
					);
				}
			}
		}

		($hook = vBulletinHook::fetch_hook('pmdata_postsave')) ? eval($hook) : false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34509 $
|| ####################################################################
\*======================================================================*/
?>