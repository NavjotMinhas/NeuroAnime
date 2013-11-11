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

define('SALT_LENGTH', 30);

/**
* Class to do data save/delete operations for USERS
*
* Available info fields:
* $this->info['coppauser'] - User is COPPA
* $this->info['override_usergroupid'] - Prevent overwriting of usergroupid (for email validation)
*
* @package	vBulletin
* @version	$Revision: 45207 $
* @date		$Date: 2011-06-28 14:59:12 -0700 (Tue, 28 Jun 2011) $
*/
class vB_DataManager_User extends vB_DataManager
{
	/**
	* Array of recognised and required fields for users, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'             => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'username'           => array(TYPE_STR,        REQ_YES,  VF_METHOD),

		'email'              => array(TYPE_STR,        REQ_YES,  VF_METHOD, 'verify_useremail'),
		'parentemail'        => array(TYPE_STR,        REQ_NO,   VF_METHOD),
		'emailstamp'         => array(TYPE_UNIXTIME,   REQ_NO),

		'password'           => array(TYPE_STR,        REQ_YES,  VF_METHOD),
		'passworddate'       => array(TYPE_STR,        REQ_AUTO),
		'salt'               => array(TYPE_STR,        REQ_AUTO, VF_METHOD),

		'usergroupid'        => array(TYPE_UINT,       REQ_YES,  VF_METHOD),
		'membergroupids'     => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_commalist'),
		'infractiongroupids' => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_commalist'),
		'infractiongroupid'  => array(TYPE_UINT,       REQ_NO,),
		'displaygroupid'     => array(TYPE_UINT,       REQ_NO,   VF_METHOD),

		'styleid'            => array(TYPE_UINT,       REQ_NO),
		'languageid'         => array(TYPE_UINT,       REQ_NO),

		'options'            => array(TYPE_UINT,       REQ_YES),
		'adminoptions'       => array(TYPE_UINT,       REQ_NO),
		'showvbcode'         => array(TYPE_INT,        REQ_NO, 'if (!in_array($data, array(0, 1, 2))) { $data = 1; } return true;'),
		'showbirthday'       => array(TYPE_INT,        REQ_NO, 'if (!in_array($data, array(0, 1, 2, 3))) { $data = 2; } return true;'),
		'threadedmode'       => array(TYPE_INT,        REQ_NO,   VF_METHOD),
		'maxposts'           => array(TYPE_INT,        REQ_NO,   VF_METHOD),
		'ipaddress'          => array(TYPE_STR,        REQ_NO,   VF_METHOD),
		'referrerid'         => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD),
		'posts'              => array(TYPE_UINT,       REQ_NO),
		'daysprune'          => array(TYPE_INT,        REQ_NO),
		'startofweek'        => array(TYPE_INT,        REQ_NO),
		'timezoneoffset'     => array(TYPE_STR,        REQ_NO),
		'autosubscribe'      => array(TYPE_INT,        REQ_NO,   VF_METHOD),

		'homepage'           => array(TYPE_NOHTML,     REQ_NO,   VF_METHOD),
		'icq'                => array(TYPE_NOHTML,     REQ_NO),
		'aim'                => array(TYPE_NOHTML,     REQ_NO),
		'yahoo'              => array(TYPE_NOHTML,     REQ_NO),
		'msn'                => array(TYPE_STR,        REQ_NO,   VF_METHOD),
		'skype'              => array(TYPE_NOHTML,     REQ_NO,   VF_METHOD),

		'usertitle'          => array(TYPE_STR,        REQ_NO),
		'customtitle'        => array(TYPE_UINT,       REQ_NO, 'if (!in_array($data, array(0, 1, 2))) { $data = 0; } return true;'),

		'ipoints'            => array(TYPE_UINT,       REQ_NO),
		'infractions'        => array(TYPE_UINT,       REQ_NO),
		'warnings'           => array(TYPE_UINT,       REQ_NO),

		'joindate'           => array(TYPE_UNIXTIME,   REQ_AUTO),
		'lastvisit'          => array(TYPE_UNIXTIME,   REQ_NO),
		'lastactivity'       => array(TYPE_UNIXTIME,   REQ_NO),
		'lastpost'           => array(TYPE_UNIXTIME,   REQ_NO),
		'lastpostid'         => array(TYPE_UINT,       REQ_NO),

		'birthday'           => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD),
		'birthday_search'    => array(TYPE_STR,        REQ_AUTO),

		'reputation'         => array(TYPE_NOHTML,     REQ_NO,   VF_METHOD),
		'reputationlevelid'  => array(TYPE_UINT,       REQ_AUTO),

		'avatarid'           => array(TYPE_UINT,       REQ_NO),
		'avatarrevision'     => array(TYPE_UINT,       REQ_NO),
		'profilepicrevision' => array(TYPE_UINT,       REQ_NO),
		'sigpicrevision'     => array(TYPE_UINT,       REQ_NO),

		'pmpopup'            => array(TYPE_INT,        REQ_NO),
		'pmtotal'            => array(TYPE_UINT,       REQ_NO),
		'pmunread'           => array(TYPE_UINT,       REQ_NO),

		'assetposthash'      => array(TYPE_STR,        REQ_NO),

		// socnet counter fields
		'profilevisits'      => array(TYPE_UINT,       REQ_NO),
		'friendcount'        => array(TYPE_UINT,       REQ_NO),
		'friendreqcount'     => array(TYPE_UINT,       REQ_NO),
		'vmunreadcount'      => array(TYPE_UINT,       REQ_NO),
		'vmmoderatedcount'   => array(TYPE_UINT,       REQ_NO),
		'pcunreadcount'      => array(TYPE_UINT,       REQ_NO),
		'pcmoderatedcount'   => array(TYPE_UINT,       REQ_NO),
		'gmmoderatedcount'   => array(TYPE_UINT,       REQ_NO),

		// usertextfield fields
		'subfolders'         => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_serialized'),
		'pmfolders'          => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_serialized'),
		'searchprefs'        => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_serialized'),
		'buddylist'          => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_spacelist'),
		'ignorelist'         => array(TYPE_NOCLEAN,    REQ_NO,   VF_METHOD, 'verify_spacelist'),
		'signature'          => array(TYPE_STR,        REQ_NO),
		'rank'               => array(TYPE_STR,        REQ_NO),

		// facebook fields
		'fbuserid'           => array(TYPE_STR,        REQ_NO),
		'fbname'             => array(TYPE_STR,        REQ_NO),
		'fbjoindate'         => array(TYPE_UINT,       REQ_NO),
		'logintype'          => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array(\'vb\', \'fb\'))) { $data = \'vb\'; } return true; ')
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array(
		'options'      => 'bf_misc_useroptions',
		'adminoptions' => 'bf_misc_adminoptions',
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'user';

	/**#@+
	* Arrays to store stuff to save to user-related tables
	*
	* @var	array
	*/
	var $user = array();
	var $userfield = array();
	var $usertextfield = array();
	/**#@-*/

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('userid = %1$d', 'userid');

	/**
	* Whether or not we have inserted an administrator record
	*
	* @var	boolean
	*/
	var $insertedadmin = false;

	/**
	* Whether or not to skip some checks from the admin cp
	*
	* @var	boolean
	*/
	var $adminoverride = false;

	/**
	* Types of lists stored in usertextfield, named <X>list.
	*
	* @var	array
	*/
	var $list_types = array('buddy', 'ignore');

	/**
	* Arrays to store stuff to save to userchangelog table
	*
	* @var	array
	*/
	var $userchangelog = array();

	/**
	* We want to log or not the user changes
	*
	* @var	boolean
	*/
	var $user_changelog_state = true;

	/**
	* Which fieldchanges will be logged
	*
	* @var	array
	*/
	var $user_changelog_fields = array('username', 'usergroupid', 'membergroupids', 'email');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_User(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('userdata_start')) ? eval($hook) : false;
	}

	// #############################################################################
	// data verification functions

	/**
	* Verifies that the user's homepage is valid
	*
	* @param	string	URL
	*
	* @return	boolean
	*/
	function verify_homepage(&$homepage)
	{
		return (empty($homepage)) ? true : $this->verify_link($homepage, true);
	}

	/**
	* Verifies that $threadedmode is a valid value, and sets the appropriate options to support it.
	*
	* @param	integer	Threaded mode: 0 = linear, oldest first; 1 = threaded; 2 = hybrid; 3 = linear, newest first
	*
	* @return	boolean
	*/
	function verify_threadedmode(&$threadedmode)
	{
		// ensure that provided value is valid
		if (!in_array($threadedmode, array(0, 1, 2, 3)))
		{
			$threadedmode = 0;
		}

		// fix linear, newest first
		if ($threadedmode == 3)
		{
			$this->set_bitfield('options', 'postorder', 1);
			$threadedmode = 0;
		}
		// fix linear, oldest first
		else if ($threadedmode == 0)
		{
			$this->set_bitfield('options', 'postorder', 0);
		}

		// set threadedmode to linear / oldest first if threadedmode is disabled
		if ($threadedmode > 0 AND !$this->registry->options['allowthreadedmode'])
		{
			$this->set_bitfield('options', 'postorder', 0);
			$threadedmode = 0;
		}

		return true;
	}

	/**
	* Verifies that an autosubscribe choice is valid and workable
	*
	* @param	integer	Autosubscribe choice: (-1: no subscribe; 0: subscribe, no email; 1: instant email; 2: daily email; 3: weekly email; 4: instant icq notification (dodgy))
	*
	* @return	boolean
	*/
	function verify_autosubscribe(&$autosubscribe)
	{
		// check that the subscription choice is valid
		switch ($autosubscribe)
		{
			// the choice is good
			case -1:
			case 0:
			case 1:
			case 2:
			case 3:
				break;

			// check that ICQ number is valid
			case 4:
				if (!preg_match('#^[0-9\-]+$', $this->fetch_field('icq')))
				{
					// icq number is bad
					$this->set('icq', '');
					$autosubscribe = 1;
				}
				break;

			// all other options
			default:
				$autosubscribe = -1;
				break;
		}

		return true;
	}

	/**
	* Verifies the value of user.maxposts, setting the forum default number if the value is invalid
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_maxposts(&$maxposts)
	{
		if (!in_array($maxposts, explode(',', $this->registry->options['usermaxposts'])))
		{
			$maxposts = -1;
		}

		return true;
	}

	/**
	* Verifies a valid reputation value, and sets the appropriate reputation level
	*
	* @param	integer	Reputation value
	*
	* @return	boolean
	*/
	function verify_reputation(&$reputation)
	{
		if ($reputation > 2147483647)
		{
			$reputation = 2147483647;
		}
		else if ($reputation < -2147483647)
		{
			$reputation = -2147483647;
		}
		else
		{
			$reputation = intval($reputation);
		}

		$reputationlevel = $this->dbobject->query_first("
			SELECT reputationlevelid
			FROM " . TABLE_PREFIX . "reputationlevel
			WHERE $reputation >= minimumreputation
			ORDER BY minimumreputation DESC
			LIMIT 1
		");

		$this->set('reputationlevelid', intval($reputationlevel['reputationlevelid']));

		return true;
	}

	/**
	* Verifies that the provided username is valid, and attempts to correct it if it is not valid
	*
	* @param	string	Username
	*
	* @return	boolean	Returns true if the username is valid, or has been corrected to be valid
	*/
	function verify_username(&$username)
	{
		// fix extra whitespace and invisible ascii stuff
		$username = trim(preg_replace('#[ \r\n\t]+#si', ' ', strip_blank_ascii($username, ' ')));
		$username_raw = $username;

		$username = preg_replace(
			'/&#([0-9]+);/ie',
			"convert_unicode_char_to_charset('\\1', vB_Template_Runtime::fetchStyleVar('charset'))",
			$username
		);

		$username = preg_replace(
			'/&#0*([0-9]{1,2}|1[01][0-9]|12[0-7]);/ie',
			"convert_int_to_utf8('\\1')",
			$username
		);

		$username = str_replace(chr(0), '', $username);
		$username = trim($username);

		$length = vbstrlen($username);
		if ($length == 0)
		{ // check for empty string
			$this->error('fieldmissing_username');
			return false;
		}
		else if ($length < $this->registry->options['minuserlength'] AND !$this->adminoverride)
		{
			// name too short
			$this->error('usernametooshort', $this->registry->options['minuserlength']);
			return false;
		}
		else if ($length > $this->registry->options['maxuserlength'] AND !$this->adminoverride)
		{
			// name too long
			$this->error('usernametoolong', $this->registry->options['maxuserlength']);
			return false;
		}
		else if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $username))
		{
			// name contains semicolons
			$this->error('username_contains_semi_colons');
			return false;
		}
		else if ($username != fetch_censored_text($username) AND !$this->adminoverride)
		{
			// name contains censored words
			$this->error('censorfield', $this->registry->options['contactuslink']);
			return false;
		}
		else if (htmlspecialchars_uni($username_raw) != $this->existing['username'] AND $user = $this->dbobject->query_first("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE userid != " . intval($this->existing['userid']) . "
			AND
			(
				username = '" . $this->dbobject->escape_string(htmlspecialchars_uni($username)) . "'
				OR
				username = '" . $this->dbobject->escape_string(htmlspecialchars_uni($username_raw)) . "'
			)
		"))
		{
			// name is already in use
			if ($this->error_handler == ERRTYPE_CP)
			{
				$this->error('usernametaken_edit_here', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl'], $user['userid']);
			}
			else
			{
				$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
			}
			return false;
		}

		if (!empty($this->registry->options['usernameregex']) AND !$this->adminoverride)
		{
			// check for regex compliance
			if (!preg_match('#' . str_replace('#', '\#', $this->registry->options['usernameregex']) . '#siU', $username))
			{
				$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
				return false;
			}
		}

		if (htmlspecialchars_uni($username_raw) != $this->existing['username']
			AND !$this->adminoverride
			AND $this->registry->options['usernamereusedelay'] > 0
		)
		{
			require_once(DIR . '/includes/class_userchangelog.php');
			$userchangelog = new vB_UserChangeLog($this->registry);
			$userchangelog->set_execute(true);
			$userchangelog->set_just_count(true);
			if ($userchangelog->sql_select_by_username(htmlspecialchars_uni($username), TIMENOW - ($this->registry->options['usernamereusedelay'] * 86400)))
			{
				$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
				return false;
			}
		}

		if (htmlspecialchars_uni($username_raw) != $this->existing['username'] AND !empty($this->registry->options['illegalusernames']) AND !$this->adminoverride)
		{
			// check for illegal username
			$usernames = preg_split('/[ \r\n\t]+/', $this->registry->options['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($usernames AS $val)
			{
				if (strpos(strtolower($username), strtolower($val)) !== false)
				{
					// wierd error to show, but hey...
					$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
					return false;
				}
			}
		}

		$unregisteredphrases = $this->registry->db->query_read("
			SELECT text
			FROM " . TABLE_PREFIX . "phrase
			WHERE varname = 'unregistered'
				AND fieldname = 'global'
		");

		while ($unregisteredphrase = $this->registry->db->fetch_array($unregisteredphrases))
		{
			if (strtolower($unregisteredphrase['text']) == strtolower($username) OR strtolower($unregisteredphrase['text']) == strtolower($username_raw))
			{
				$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
				return false;
			}
		}

		// if we got here, everything is okay
		$username = htmlspecialchars_uni($username);

		// remove any trailing HTML entities that will be cut off when we stick them in the DB.
		// if we don't do this, the affected person won't be able to login, be banned, etc...
		$column_info = $this->dbobject->query_first("SHOW COLUMNS FROM " . TABLE_PREFIX . "user LIKE 'username'");
		if (preg_match('#char\((\d+)\)#i', $column_info['Type'], $match) AND $match[1] > 0)
		{
			$username = preg_replace('/&([a-z0-9#]*)$/i', '', substr($username, 0, $match[1]));
		}

		$username = trim($username);

		return true;
	}

	/**
	* Verifies that the provided birthday is valid
	*
	* @param	mixed	Birthday - can be yyyy-mm-dd, mm-dd-yyyy or an array containing day/month/year and converts it into a valid yyyy-mm-dd
	*
	* @return	boolean
	*/
	function verify_birthday(&$birthday)
	{
		if (!$this->adminoverride AND $this->registry->options['reqbirthday'])
		{	// required birthday. If current birthday is acceptable, don't go any further (bypass form manipulation)
			$bday = explode('-', $this->existing['birthday']);
			if ($bday[2] > 1901 AND $bday[2] <= date('Y') AND @checkdate($bday[0], $bday[1], $bday[2]))
			{
				$this->set('birthday_search', $bday[2] . '-' . $bday[0] . '-' . $bday[1]);
				$birthday = "$bday[0]-$bday[1]-$bday[2]";
				return true;
			}
		}

		if (!is_array($birthday))
		{
			// check for yyyy-mm-dd string
			if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $birthday, $match))
			{
				$birthday = array('day' => $match[3], 'month' => $match[2], 'year' => $match[1]);
			}
			// check for mm-dd-yyyy string
			else if (preg_match('#^(\d{1,2})-(\d{1,2})-(\d{4})$#', $birthday, $match))
			{
				$birthday = array('day' => $match[2], 'month' => $match[1], 'year' => $match[3]);
			}
		}

		// check that all neccessary array keys are set
		if (!isset($birthday['day']) OR !isset($birthday['month']) OR !isset($birthday['year']))
		{
			$this->error('birthdayfield');
			return false;
		}

		// check that any entered year is a number
		if (!empty($birthday['year']) AND !is_numeric($birthday['year']))
		{
			$this->error('birthdayfield');
			return false;
		}

		// force all array keys to integer
		$birthday = $this->registry->input->clean_array($birthday, array(
			'day' =>   TYPE_INT,
			'month' => TYPE_INT,
			'year' =>  TYPE_INT
		));

		if (
			($birthday['day'] <= 0 AND $birthday['month'] > 0) OR
			($birthday['day'] > 0 AND $birthday['month'] <= 0) OR
			(!$this->adminoverride AND $this->registry->options['reqbirthday'] AND ($birthday['day'] <= 0 OR $birthday['month'] <= 0 OR $birthday['year'] <= 0))
		)
		{
			$this->error('birthdayfield');
			return false;
		}

		if ($birthday['day'] <= 0 AND $birthday['month'] <= 0)
		{
			$this->set('birthday_search', '');
			$birthday = '';

			return true;
		}
		else if (
			($birthday['year'] <= 0 OR (
				$birthday['year'] > 1901 AND $birthday['year'] <= date('Y')
			)) AND
			checkdate($birthday['month'], $birthday['day'], ($birthday['year'] == 0 ? 1996 : $birthday['year']))
		)
		{
			$birthday['day']   = str_pad($birthday['day'],   2, '0', STR_PAD_LEFT);
			$birthday['month'] = str_pad($birthday['month'], 2, '0', STR_PAD_LEFT);
			$birthday['year']  = str_pad($birthday['year'],  4, '0', STR_PAD_LEFT);

			$this->set('birthday_search', $birthday['year'] . '-' . $birthday['month'] . '-' . $birthday['day']);

			$birthday = "$birthday[month]-$birthday[day]-$birthday[year]";

			return true;
		}
		else
		{
			$this->error('birthdayfield');
			return false;
		}
	}

	/**
	* Verifies that everything is hunky dory with the user's email field
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	function verify_useremail(&$email)
	{
		$email_changed = (!isset($this->existing['email']) OR $email != $this->existing['email']);

		// check for empty string
		if ($email == '')
		{
			if ($this->adminoverride OR !$email_changed)
			{
				return true;
			}

			$this->error('fieldmissing_email');
			return false;
		}

		// check valid email address
		if (!$this->verify_email($email))
		{
			$this->error('bademail');
			return false;
		}


		// check banned email addresses
		require_once(DIR . '/includes/functions_user.php');
		if (is_banned_email($email) AND !$this->adminoverride)
		{
			if ($email_changed OR !$this->registry->options['allowkeepbannedemail'])
			{
				// throw error if this is a new registration, or if updating users are not allowed to keep banned addresses
				$this->error('banemail', $this->registry->options['contactuslink']);
				return false;
			}
		}

		// check unique address
		if ($this->registry->options['requireuniqueemail'] AND $email_changed)
		{
			if ($user = $this->dbobject->query_first_slave("
				SELECT userid, username, email
				FROM " . TABLE_PREFIX . "user
				WHERE email = '" . $this->dbobject->escape_string($email) . "'
					" . ($this->condition !== null ? 'AND userid <> ' . intval($this->existing['userid']) : '') . "
				LIMIT 1
			"))
			{
				if ($this->error_handler == ERRTYPE_CP)
				{
					$this->error('emailtaken_search_here', $this->registry->session->vars['sessionurl'], $email);
				}
				else
				{
					$this->error('emailtaken', $this->registry->session->vars['sessionurl']);
				}
				return false;
			}
		}

		return true;
	}

	/**
	* Verifies that the provided parent email address is valid
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	function verify_parentemail(&$parentemail)
	{
		if ($this->info['coppauser'] AND !$this->verify_email($parentemail))
		{
			$this->error('fieldmissing_parentemail');
			return false;
		}
		else
		{
			return true;
		}
}

	/**
	* Verifies that the usergroup provided is valid
	*
	* @param	integer	Usergroup ID
	*
	* @return	boolean
	*/
	function verify_usergroupid(&$usergroupid)
	{
		// if usergroupids is set because of email validation, don't allow it to be re-written
		if (isset($this->info['override_usergroupid']) AND $usergroupid != $this->user['usergroupid'])
		{
			$this->error("::Usergroup ID is already set to {$this->user[usergroupid]} and can not be changed due to email validation regulations::");
			return false;
		}

		if ($usergroupid < 1)
		{
			$usergroupid = 2;
		}

		return true;
	}

	/**
	* Verifies that the provided displaygroup ID is valid
	*
	* @param	integer	Display group ID
	*
	* @return	boolean
	*/
	function verify_displaygroupid(&$displaygroupid)
	{
		if ($displaygroupid == $this->fetch_field('usergroupid') OR in_array($displaygroupid, explode(',', $this->fetch_field('membergroupids'))))
		{
			return true;
		}
		else
		{
			$displaygroupid = 0;
			return true;
		}
	}

	/**
	* Verifies a specified referrer
	*
	* @param	mixed	Referrer - either a user ID or a user name
	*
	* @return	boolean
	*/
	function verify_referrerid(&$referrerid)
	{
		if (!$this->registry->options['usereferrer'] OR $referrerid == '')
		{
			$referrerid = 0;
			return true;
		}
		else if ($user = $this->dbobject->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE username = '" . $this->dbobject->escape_string($referrerid) . "'"))
		{
			$referrerid = $user['userid'];
		}
		else if (is_numeric($referrerid) AND $user = $this->dbobject->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($referrerid)))
		{
			$referrerid = $user['userid'];
		}
		else
		{
			$this->error('invalid_referrer_specified');
			return false;
		}

		if ($referrerid > 0 AND $referrerid == $this->existing['userid'])
		{
			$this->error('invalid_referrer_specified');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies an MSN handle
	*
	* @param	string	MSN handle (email address)
	*
	* @return	boolean
	*/
	function verify_msn(&$msn)
	{
		if ($msn == '' OR $this->verify_email($msn))
		{
			$msn = htmlspecialchars_uni($msn);
			return true;
		}
		else
		{
			$this->error('badmsn');
			return false;
		}
	}

	/**
	* Verifies a Skype name
	*
	* @param	string	Skype name
	*
	* @return	boolean
	*/
	function verify_skype(&$skype)
	{
		if ($skype == '' OR preg_match('#^[a-z0-9_.,-]{6,32}$#si', $skype))
		{
			return true;
		}
		else
		{
			$this->error('badskype');
			return false;
		}
	}

	// #############################################################################
	// password related

	/**
	* Converts a PLAIN TEXT (or valid md5 hash) password into a hashed password
	*
	* @param	string	The plain text password to be converted
	*
	* @return	boolean
	*/
	function verify_password(&$password)
	{
		//regenerate the salt when the password is changed.  No reason not to and its
		//an easy way to increase the size when the user changes their password (doing
		//it this way avoids having to reset all of the passwords)
		$this->user['salt'] = $salt = $this->fetch_user_salt();

		// generate the password
		$password = $this->hash_password($password, $salt);

		if (!defined('ALLOW_SAME_USERNAME_PASSWORD'))
		{
			// check if password is same as username; if so, set an error and return false
			if ($password == md5(md5($this->fetch_field('username')) . $salt))
			{
				$this->error('sameusernamepass');
				return false;
			}
		}

		$this->set('passworddate', 'FROM_UNIXTIME(' . TIMENOW . ')', false);

		return true;
	}

	/**
	* Verifies that the user salt is valid
	*
	* @param	string	The salt string
	*
	* @return	boolean
	*/
	function verify_salt(&$salt)
	{
		$this->error('::You may not set salt manually.::');
		return false;
	}

	/**
	* Takes a plain text or singly-md5'd password and returns the hashed version for storage in the database
	*
	* @param	string	Plain text or singly-md5'd password
	*
	* @return	string	Hashed password
	*/
	function hash_password($password, $salt)
	{
		// if the password is not already an md5, md5 it now
		if ($password == '')
		{
		}
		else if (!$this->verify_md5($password))
		{
			$password = md5($password);
		}

		// hash the md5'd password with the salt
		return md5($password . $salt);
	}

	/**
	* Generates a new user salt string
	*
	* @param	integer	(Optional) the length of the salt string to generate
	*
	* @return	string
	*/
	function fetch_user_salt($length = SALT_LENGTH)
	{
		$salt = '';

		for ($i = 0; $i < $length; $i++)
		{
			$salt .= chr(rand(33, 126));
		}

		return $salt;
	}

	/**
	* Checks to see if a password is in the user's password history
	*
	* @param	integer	User ID
	* @param	integer	History time ($permissions['passwordhistory'])
	*
	* @return	boolean	Returns true if password is in the history
	*/
	function check_password_history($password, $historylength)
	{
		// delete old password history
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "passwordhistory
			WHERE userid = " . $this->existing['userid'] . "
			AND passworddate <= FROM_UNIXTIME(" . (TIMENOW - $historylength * 86400) . ")
		");

		// check to see if the password is invalid due to previous use
		if ($historylength AND $historycheck = $this->dbobject->query_first("
			SELECT UNIX_TIMESTAMP(passworddate) AS passworddate
			FROM " . TABLE_PREFIX . "passwordhistory
			WHERE userid = " . $this->existing['userid'] . "
			AND password = '" . $this->dbobject->escape_string($password) . "'"))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	// #############################################################################
	// user title

	/**
	* Sets the values for user[usertitle] and user[customtitle]
	*
	* @param	string	Custom user title text
	* @param	boolean	Whether or not to reset a custom title to the default user title
	* @param	array	Array containing all information for the user's primary usergroup
	* @param	boolean	Whether or not a user can use custom user titles ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle'])
	* @param	boolean	Whether or not the user is an administrator ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	*/
	function set_usertitle($customtext, $reset, $usergroup, $canusecustomtitle, $isadmin)
	{
		$customtitle = $this->existing['customtitle'];
		$usertitle = $this->existing['usertitle'];

		if ($this->existing['customtitle'] == 2 AND isset($this->existing['musername']))
		{
			// fetch_musername has changed this value -- need to undo it
			$usertitle = unhtmlspecialchars($usertitle);
		}

		if ($canusecustomtitle)
		{
			// user is allowed to set a custom title
			if ($reset OR ($customtitle == 0 AND $customtext === ''))
			{
				// reset custom title or we don't have one but are allowed to
				if (empty($usergroup['usertitle']))
				{
					$gettitle = $this->dbobject->query_first("
						SELECT title
						FROM " . TABLE_PREFIX . "usertitle
						WHERE minposts <= " . intval($this->existing['posts']) . "
						ORDER BY minposts DESC
						LIMIT 1
					");
					$usertitle = $gettitle['title'];
				}
				else
				{
					$usertitle = $usergroup['usertitle'];
				}
				$customtitle = 0;
			}
			else if ($customtext)
			{
				// set custom text
				$usertitle = fetch_censored_text($customtext);

				if (!can_moderate() OR (can_moderate() AND !$this->registry->options['ctCensorMod']))
				{
					$usertitle = $this->censor_custom_title($usertitle);
				}
				$customtitle = $isadmin ?
					1: // administrator - don't run htmlspecialchars
					2; // regular user - run htmlspecialchars
				if ($customtitle == 2)
				{
					$usertitle = fetch_word_wrapped_string($usertitle, 25);
				}
			}
		}
		else if ($customtitle != 1)
		{
			if (empty($usergroup['usertitle']))
			{
				$gettitle = $this->dbobject->query_first("
					SELECT title
					FROM " . TABLE_PREFIX . "usertitle
					WHERE minposts <= " . intval($this->existing['posts']) . "
					ORDER BY minposts DESC
					LIMIT 1
				");
				$usertitle = $gettitle['title'];
			}
			else
			{
				$usertitle = $usergroup['usertitle'];
			}
			$customtitle = 0;
		}

		$this->set('usertitle', $usertitle);
		$this->set('customtitle', $customtitle);
	}

	/**
	* Sets the ladder-based or group based user title for a particular amount of posts.
	*
	* @param	integer			Number of posts to consider this user as having
	*
	* @return	false|string	False if they use a custom title or can't process, the new title otherwise
	*/
	function set_ladder_usertitle($posts)
	{
		if ($this->fetch_field('userid')
			AND (!isset($this->user['customtitle']) OR !isset($this->existing['customtitle']))
		)
		{
			// we don't have enough information, try to fetch it
			$user = fetch_userinfo($this->fetch_field('userid'));
			if ($user)
			{
				$this->set_existing($user);
			}
			else
			{
				return false;
			}
		}

		if ($this->fetch_field('customtitle'))
		{
			return false;
		}

		$getusergroupid = ($this->fetch_field('displaygroupid') ? $this->fetch_field('displaygroupid') : $this->fetch_field('usergroupid'));
		$usergroup = $this->registry->usergroupcache["$getusergroupid"];

		if (!$usergroup['usertitle'])
		{
			$gettitle = $this->dbobject->query_first_slave("
				SELECT title
				FROM " . TABLE_PREFIX . "usertitle
				WHERE minposts <= " . intval($posts) . "
				ORDER BY minposts DESC
			");

			$usertitle = $gettitle['title'];
		}
		else
		{
			$usertitle = $usergroup['usertitle'];
		}

		$this->set('usertitle', $usertitle);

		return $usertitle;
	}

	/**
	* Sets the ladder usertitle relative to the current number of posts.
	*
	* @param	integer			Offset to current number of posts
	*
	* @return	false|string	Same return values as set_ladder_usertitle
	*/
	function set_ladder_usertitle_relative($relative_post_offset)
	{
		if ($this->fetch_field('userid') AND !isset($this->existing['posts']))
		{
			// we don't have enough information, try to fetch it
			if (isset($GLOBALS['usercache'][$this->fetch_field('userid')]))
			{
				unset($GLOBALS['usercache'][$this->fetch_field('userid')]);
			}

			$user = fetch_userinfo($this->fetch_field('userid'));
			if ($user)
			{
				$this->set_existing($user);
			}
			else
			{
				return false;
			}
		}

		return $this->set_ladder_usertitle($this->existing['posts'] + $relative_post_offset);
	}

	/**
	* Checks a string for words banned in custom user titles and replaces them with the censor character
	*
	* @param	string	Custom user title
	*
	* @return	string	The censored string
	*/
	function censor_custom_title($usertitle)
	{
		static $ctcensorwords;

		if (empty($ctcensorwords))
		{
			$ctcensorwords = preg_split('#[ \r\n\t]+#', preg_quote($this->registry->options['ctCensorWords'], '#'), -1, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($ctcensorwords AS $censorword)
		{
			if (substr($censorword, 0, 2) == '\\{')
			{
				$censorword = substr($censorword, 2, -2);
				$usertitle = preg_replace('#(?<=[^A-Za-z]|^)' . $censorword . '(?=[^A-Za-z]|$)#si', str_repeat($this->registry->options['censorchar'], vbstrlen($censorword)), $usertitle);
			}
			else
			{
				$usertitle = preg_replace("#$censorword#si", str_repeat($this->registry->options['censorchar'], vbstrlen($censorword)), $usertitle);
			}
		}

		return $usertitle;
	}

	// #############################################################################
	// user profile fields

	/**
	* Validates and sets custom user profile fields
	*
	* @param	array	Array of values for profile fields. Example: array('field1' => 'One', 'field2' => array(0 => 'a', 1 => 'b'), 'field2_opt' => 'c')
	* @param	bool	Whether or not to verify the data actually matches any specified regexes or required fields
	* @param	string	What type of editable value to apply (admin, register, normal)
	* @param	bool	Whether or not to skip verification of required fields that are not present, used for linking facebook accounts
	*
	* @return	string	Textual description of set profile fields (for email phrase)
	*/
	function set_userfields(&$values, $verify = true, $all_fields = 'normal', $skip_unset_required_fields = false)
	{
		global $vbphrase;

		if (!is_array($values))
		{
			$this->error('::$values for profile fields is not an array::');
			return false;
		}

		$customfields = '';

		$field_ids = array();
		foreach (array_keys($values) AS $key)
		{
			if (preg_match('#^field(\d+)\w*$#', $key, $match))
			{
				$field_ids["$match[1]"] = $match[1];
			}
		}
		if (empty($field_ids) AND $all_fields != 'register')
		{
			return false;
		}

		switch($all_fields)
		{
			case 'admin':
				$all_fields_sql = "WHERE profilefieldid IN(" . implode(', ', $field_ids) . ")";
				break;

			case 'register':
				// must read all fields in order to set defaults for fields that don't display
				//$all_fields_sql = "WHERE editable IN (1,2)";
				$all_fields_sql = '';

				// we need to ensure that each field the user could edit is sent through and processed,
				// so ensure that we process everyone one of these fields
				$profilefields = $this->dbobject->query_read_slave("
					SELECT profilefieldid
					FROM " . TABLE_PREFIX . "profilefield
					WHERE editable > 0 AND required <> 0
				");
				while ($profilefield = $this->dbobject->fetch_array($profilefields))
				{
					$field_ids["$profilefield[profilefieldid]"] = $profilefield['profilefieldid'];
				}
				break;

			case 'normal':
			default:
				$all_fields_sql = "WHERE profilefieldid IN(" . implode(', ', $field_ids) . ") AND editable IN (1,2)";
				break;
		}

		// check extra profile fields
		$profilefields = $this->dbobject->query_read_slave("
			SELECT profilefieldid, required, size, maxlength, type, data, optional, regex, def, editable
			FROM " . TABLE_PREFIX . "profilefield
			$all_fields_sql
			ORDER BY displayorder
		");
		while ($profilefield = $this->dbobject->fetch_array($profilefields))
		{
			$varname = 'field' . $profilefield['profilefieldid'];
			$value = $values["$varname"];
			$regex_check = false;

			if ($all_fields != 'admin' AND $profilefield['editable'] == 2 AND !empty($this->existing["$varname"]))
			{
				continue;
			}

			$profilefield['title'] = (!empty($vbphrase[$varname . '_title']) ? $vbphrase[$varname . '_title'] : $varname);

			$optionalvar = 'field' . $profilefield['profilefieldid'] . '_opt';
			$value_opt =& $values["$optionalvar"];

			// text box / text area
			if ($profilefield['type'] == 'input' OR $profilefield['type'] == 'textarea')
			{
				if (in_array($profilefield['profilefieldid'], $field_ids) AND ($all_fields != 'register' OR $profilefield['editable']))
				{
					$value = trim(substr(fetch_censored_text($value), 0, $profilefield['maxlength']));
					$value = (empty($value) AND $value != '0') ? false : $value;
				}
				else if ($all_fields == 'register' AND $profilefield['data'] !== '')
				{
					$value = unhtmlspecialchars($profilefield['data']);
				}
				else
				{
					continue;
				}
				$customfields .= "$profilefield[title] : $value\n";
				$regex_check = true;
			}
			// radio / select
			else if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
			{
				if ($profilefield['optional'] AND $value_opt != '')
				{
					$value = trim(substr(fetch_censored_text($value_opt), 0, $profilefield['maxlength']));
					$value = (empty($value) AND $value != '0') ? false : $value;
					$regex_check = true;
				}
				else
				{
					$data = unserialize($profilefield['data']);
					$value -= 1;
					if (in_array($profilefield['profilefieldid'], $field_ids) AND ($all_fields != 'register' OR $profilefield['editable']))
					{
						if (isset($data["$value"]))
						{
							$value = unhtmlspecialchars(trim($data["$value"]));
						}
						else
						{
							$value = false;
						}
					}
					else if ($all_fields == 'register' AND $profilefield['def'])
					{
						$value = unhtmlspecialchars($data[0]);
					}
					else
					{
						continue;
					}
				}
				$customfields .= "$profilefield[title] : $value\n";
			}
			// checkboxes or select multiple
			else if (($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple') AND in_array($profilefield['profilefieldid'], $field_ids))
			{
				if (is_array($value))
				{
					if (($profilefield['size'] == 0) OR (sizeof($value) <= $profilefield['size']))
					{
						$data = unserialize($profilefield['data']);

						$bitfield = 0;
						$cfield = '';
						foreach($value AS $key => $val)
						{
							$val--;
							$bitfield += pow(2, $val);
							$cfield .= (!empty($cfield) ? ', ' : '') . $data["$val"];
						}
						$value = $bitfield;
					}
					else
					{
						$this->error('checkboxsize', $profilefield['size'], $profilefield['title']);
						$value = false;
					}
					$customfields .= "$profilefield[title] : $cfield\n";
				}
				else
				{
					$value = false;
				}
			}
			else
			{
				continue;
			}

			// check for regex compliance
			if ($verify AND $profilefield['regex'] AND $regex_check)
			{
				if (!preg_match('#' . str_replace('#', '\#', $profilefield['regex']) . '#siU', $value))
				{
					$this->error('regexincorrect', $profilefield['title']);
					$value = false;
				}
			}

			// check for empty required fields
			if (($profilefield['required'] == 1 OR $profilefield['required'] == 3) AND $value === false AND $verify)
			{
				if ($skip_unset_required_fields AND !isset($values["$varname"]))
				{
					continue;
				}
				$this->error('required_field_x_missing_or_invalid', $profilefield['title']);
			}

			$this->setfields["$varname"] = true;
			$this->userfield["$varname"] = htmlspecialchars_uni($value);
		}

		$this->dbobject->free_result($profilefields);
		return $customfields;
	}

	// #############################################################################
	// daylight savings

	/**
	* Sets DST options
	*
	* @param	integer	DST choice: (2: automatic; 1: auto-off, dst on; 0: auto-off, dst off)
	*/
	function set_dst(&$dst)
	{
		switch ($dst)
		{
			case 2:
				$dstauto = 1;
				$dstonoff = $this->existing['dstonoff'];
				break;
			case 1:
				$dstauto = 0;
				$dstonoff = 1;
				break;
			default:
				$dstauto = 0;
				$dstonoff = 0;
				break;
		}

		$this->set_bitfield('options', 'dstauto', $dstauto);
		$this->set_bitfield('options', 'dstonoff', $dstonoff);
	}

	// #############################################################################
	// fill in missing fields from registration default options

	/**
	* Sets registration defaults
	*/
	function set_registration_defaults()
	{
		// on/off fields
		foreach (array(
			'invisible'         => 'invisiblemode',
			'receivepm'         => 'enablepm',
			'emailonpm'         => 'emailonpm',
			'showreputation'    => 'showreputation',
			'showvcard'         => 'vcard',
			'showsignatures'    => 'signature',
			'showavatars'       => 'avatar',
			'showimages'        => 'image',
			'vm_enable'         => 'vm_enable',
			'vm_contactonly'    => 'vm_contactonly',
			'pmdefaultsavecopy' => 'pmdefaultsavecopy',
		) AS $optionname => $bitfield)
		{
			if (!isset($this->user['options']["$optionname"]))
			{
				$this->set_bitfield('options', $optionname,
					($this->registry->bf_misc_regoptions["$bitfield"] &
						$this->registry->options['defaultregoptions'] ? 1 : 0));
			}
		}

		//force the default to true (if it not set).  If we decide to make it an
		//option later, push it into the above loop above.
		if (!isset($this->user['options']['vbasset_enable']))
		{
			$this->set_bitfield('options', 'vbasset_enable', 1);
		}

		// time fields
		foreach (array('joindate', 'lastvisit', 'lastactivity') AS $datefield)
		{
			if (!isset($this->user["$datefield"]))
			{
				$this->set($datefield, TIMENOW);
			}
		}

		// auto subscription
		if (!isset($this->user['autosubscribe']))
		{
			if ($this->registry->bf_misc_regoptions['subscribe_none'] & $this->registry->options['defaultregoptions'])
			{
				$autosubscribe = -1;
			}
			else if ($this->registry->bf_misc_regoptions['subscribe_nonotify'] & $this->registry->options['defaultregoptions'])
			{
				$autosubscribe = 0;
			}
			else if ($this->registry->bf_misc_regoptions['subscribe_instant'] & $this->registry->options['defaultregoptions'])
			{
				$autosubscribe = 1;
			}
			else if ($this->registry->bf_misc_regoptions['subscribe_daily'] & $this->registry->options['defaultregoptions'])
			{
				$autosubscribe = 2;
			}
			else
			{
				$autosubscribe = 3;
			}
			$this->set('autosubscribe', $autosubscribe);
		}

		// show vbcode
		if (!isset($this->user['showvbcode']))
		{
			if ($this->registry->bf_misc_regoptions['vbcode_none'] & $this->registry->options['defaultregoptions'])
			{
				$showvbcode = 0;
			}
			else if ($this->registry->bf_misc_regoptions['vbcode_standard'] & $this->registry->options['defaultregoptions'])
			{
				$showvbcode = 1;
			}
			else
			{
				$showvbcode = 2;
			}
			$this->set('showvbcode', $showvbcode);
		}

		// post order / thread display mode
		if (!isset($this->user['threadedmode']))
		{
			if ($this->registry->bf_misc_regoptions['thread_linear_oldest'] & $this->registry->options['defaultregoptions'])
			{
				$threadedmode = 0;
			}
			else if ($this->registry->bf_misc_regoptions['thread_linear_newest'] & $this->registry->options['defaultregoptions'])
			{
				$threadedmode = 3;
			}
			else if ($this->registry->bf_misc_regoptions['thread_threaded'] & $this->registry->options['defaultregoptions'])
			{
				$threadedmode = 1;
			}
			else if ($this->registry->bf_misc_regoptions['thread_hybrid'] & $this->registry->options['defaultregoptions'])
			{
				$threadedmode = 2;
			}
			else
			{
				$threadedmode = 0;
			}
			$this->set('threadedmode', $threadedmode);
		}

		// usergroupid
		if (!isset($this->user['usergroupid']))
		{
			if ($this->registry->options['verifyemail'])
			{
				$usergroupid = 3;
			}
			else if ($this->registry->options['moderatenewmembers'] OR $this->info['coppauser'])
			{
				$usergroupid = 4;
			}
			else
			{
				$usergroupid = 2;
			}
			$this->set('usergroupid', $usergroupid);
		}

		// reputation
		if (!isset($this->user['reputation']))
		{
			$this->set('reputation', $this->registry->options['reputationdefault']);
		}

		// pm popup
		if (!isset($this->user['pmpopup']))
		{
			$this->set('pmpopup', ($this->registry->bf_misc_regoptions['pmpopup'] & $this->registry->options['defaultregoptions'] ? 1 : 0));
		}

		// max posts per page
		if (!isset($this->user['maxposts']))
		{
			$this->set('maxposts', 1);
		}

		// days prune
		if (!isset($this->user['daysprune']))
		{
			$this->set('daysprune', 0);
		}

		// start of week
		if (!isset($this->user['startofweek']))
		{
			$this->set('startofweek', -1);
		}

		// show user css
		if (!isset($this->user['options']['showusercss']))
		{
			$this->set_bitfield('options', 'showusercss', 1);
		}

		// receive friend request pm
		if (!isset($this->user['options']['receivefriendemailrequest']))
		{
			$this->set_bitfield('options', 'receivefriendemailrequest', 1);
		}
	}

	// #############################################################################
	// data saving

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'userid':
			{
				$tables = array('user', 'userfield', 'usertextfield');
			}
			break;

			case 'subfolders':
			case 'pmfolders':
			case 'searchprefs':
			case 'buddylist':
			case 'ignorelist':
			case 'signature':
			case 'rank':
			{
				$tables = array('usertextfield');
			}
			break;

			default:
			{
				$tables = array('user');
			}
		}

		($hook = vBulletinHook::fetch_hook('userdata_doset')) ? eval($hook) : false;

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
			$this->lasttable = $table;
		}
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	*
	* @return	integer	Returns the user id of the affected data
	*/
	function save($doquery = true, $delayed = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return 0;
		}

		// UPDATE EXISTING USER
		if ($this->condition)
		{
			// update query
			$return = $this->db_update(TABLE_PREFIX, 'user', $this->condition, $doquery, $delayed);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'userfield',     $this->condition, $doquery, $delayed);
				$this->db_update(TABLE_PREFIX, 'usertextfield', $this->condition, $doquery, $delayed);

				// check if we want userchange log and we have the all requirements
				if ($this->user_changelog_state AND is_array($this->user_changelog_fields) AND sizeof($this->user_changelog_fields) AND is_array($this->existing) AND sizeof($this->existing) AND is_array($this->user) AND sizeof($this->user))
				{
					$uniqueid = md5(TIMENOW . $this->existing['userid'] . $this->registry->userinfo['userid']. rand(1111,9999));

					// fill the storage array
					foreach($this->user_changelog_fields AS $fieldname)
					{
						// if no old and new value, or no change: we dont log this field
						if (
							!isset($this->user["$fieldname"])
							OR
							(!$this->existing["$fieldname"] AND !$this->user["$fieldname"])
							OR
							$this->existing["$fieldname"] == $this->user["$fieldname"]
						)
						{
							continue;
						}

						// init storage array
						$this->userchangelog = array(
							'userid'      => $this->existing['userid'],
							'adminid'     => $this->registry->userinfo['userid'],
							'fieldname'   => $fieldname,
							'oldvalue'    => $this->existing["$fieldname"],
							'newvalue'    => $this->user["$fieldname"],
							'change_time' => TIMENOW,
							'change_uniq' => $uniqueid,
							'ipaddress'   => sprintf('%u', ip2long(IPADDRESS)),
						);

						$this->rawfields['ipaddress'] = true;

						// build the sql query string
						$sql = $this->fetch_insert_sql(TABLE_PREFIX, 'userchangelog');

						// do the query ?
						if ($doquery)
						{
							$this->dbobject->query_write($sql);
						}
						else
						{
							echo "<pre>$sql<hr /></pre>";
						}
					}
				}
			}
		}
		// INSERT NEW USER
		else
		{
			// fill in any registration defaults
			$this->set_registration_defaults();

			// insert query
			if ($return = $this->db_insert(TABLE_PREFIX, 'user', $doquery))
			{
				$this->set('userid', $return);
				$this->db_insert(TABLE_PREFIX, 'userfield',     $doquery);
				$this->db_insert(TABLE_PREFIX, 'usertextfield', $doquery);

				// Send welcome PM
				if ($this->fetch_field('usergroupid') == 2)
				{
					$this->send_welcomepm();
				}
			}
		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
		}

		return $return;
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

		// USERGROUP CHECKS
		$usergroups_changed = $this->usergroups_changed();

		if ($usergroups_changed)
		{
			// VALIDATE USERGROUPID / MEMBERGROUPIDS
			$usergroupid = $this->fetch_field('usergroupid');
			$membergroupids = $this->fetch_field('membergroupids');

			if (strpos(",$membergroupids,", ",$usergroupid,") !== false)
			{
				// usergroupid/membergroups conflict
				$this->error('usergroup_equals_secondary');
				return false;
			}

			// if changing usergroups, validate the displaygroup
			$displaygroupid = $this->fetch_field('displaygroupid');
			$this->verify_displaygroupid($displaygroupid); // this will edit the value if necessary
			$this->do_set('displaygroupid', $displaygroupid);
		}

		if ($this->condition)
		{
			$wasadmin = $this->is_admin($this->existing['usergroupid'], $this->existing['membergroupids']);
			$isadmin = $this->is_admin($this->fetch_field('usergroupid'), $this->fetch_field('membergroupids'));

			// if usergroups changed, check we are not de-admining the last admin
			if ($usergroups_changed AND $wasadmin AND !$isadmin AND $this->count_other_admins($this->existing['userid']) == 0)
			{
				$this->error('cant_de_admin_last_admin');
				return false;
			}

			$updateinfractions = false;
			// primary usergroup change, update infractions
			if (isset($this->user['usergroupid']) AND (($usergroupid = $this->user['usergroupid']) != $this->existing['usergroupid']) AND $this->existing['ipoints'] > 0)
			{
				$ipoints = $this->existing['ipoints'];
				$updateinfractions = true;
			}
			else if (is_int($this->user['ipoints']) AND $this->user['ipoints'] != $this->existing['ipoints'])
			{
				$updateinfractions = true;
				$ipoints = $this->user['ipoints'];
			}

			if ($updateinfractions)
			{
 				// If user groups aren't changed, then $usergroupid is not set....
 				if (empty($usergroupid))
 				{
 					$usergroupid = $this->fetch_field('usergroupid');
 				}

				$infractiongroups = array();
				$infractiongroupid = 0;
				$groups = $this->registry->db->query_read_slave("
					SELECT orusergroupid, override
					FROM " . TABLE_PREFIX . "infractiongroup AS infractiongroup
					WHERE infractiongroup.usergroupid IN (-1, $usergroupid)
						AND infractiongroup.pointlevel <= $ipoints
					ORDER BY pointlevel
				");
				while ($group = $this->registry->db->fetch_array($groups))
				{
					if ($group['override'])
					{
						$infractiongroupid = $group['orusergroupid'];
					}
					$infractiongroups["$group[orusergroupid]"] = true;
				}

				$this->set('infractiongroupids', !empty($infractiongroups) ? implode(',', array_keys($infractiongroups)) : '');
				$this->set('infractiongroupid', $infractiongroupid);
			}
		}

		// Attempt to detect if we need a new rank or usertitle
		if ($this->rawfields['posts'])
		{	// posts = posts + 1 / posts - 1 was specified so we need existing posts to determine how many posts we will have
			if ($this->existing['posts'] != null)
			{
				$posts = $this->existing['posts'] + preg_replace('#^.*posts\s*([+-])\s*(\d+?).*$#sU', '\1\2', $this->fetch_field('posts'));
			}
		}
		else if ($this->fetch_field('posts') !== null)
		{
			$posts = $this->fetch_field('posts');
		}

		if (($this->setfields['membergroupids'] OR $this->setfields['posts'] OR $this->setfields['usergroupid'] OR $this->setfields['displaygroupid']) AND !$this->setfields['rank'] AND isset($posts) AND $userid = $this->fetch_field('userid'))
		{	// item affecting user's rank is changing and a new rank hasn't been given to us
			$userinfo = array(
				'userid' => $userid, // we need an userid for is_member_of's cache routine
				'posts' => $posts
			);
			if (($userinfo['usergroupid'] =& $this->fetch_field('usergroupid')) !== null AND
				($userinfo['displaygroupid'] =& $this->fetch_field('displaygroupid')) !== null AND
				($userinfo['membergroupids'] =& $this->fetch_field('membergroupids')) !== null
			)
			{
				require_once(DIR . '/includes/functions_ranks.php');
				$userrank =& fetch_rank($userinfo);

				if ($userrank != $this->existing['rank'])
				{
					$this->setr('rank', $userrank);
				}
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('userdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		$userid = $this->fetch_field('userid');

		if (!$userid OR !$doquery)
		{
			return;
		}

		$usergroups_changed = $this->usergroups_changed();
		$wasadmin = $this->is_admin($this->existing['usergroupid'], $this->existing['membergroupids']);
		$isadmin = $this->is_admin($this->fetch_field('usergroupid'), $this->fetch_field('membergroupids'));

		$wassupermod = $this->is_supermod($this->existing['usergroupid'], $this->existing['membergroupids']);
		$issupermod = $this->is_supermod($this->fetch_field('usergroupid'), $this->fetch_field('membergroupids'));

		if (!$this->condition)
		{
			// save user count and new user id to template
			require_once(DIR . '/includes/functions_databuild.php');
			build_user_statistics();
		}
		else
		{
			// update denormalized username field in various tables
			$this->update_username($userid);

			// if usergroup membership has changed...
			if ($usergroups_changed)
			{
				// update subscriptions
				$this->update_subscriptions($userid, $doquery);

				// update ban status
				$this->update_ban_status($userid, $doquery);

				// recache permissions if the userid is the current browsing user
				if ($userid == $this->registry->userinfo['userid'])
				{
					$this->registry->userinfo['usergroupid'] = $this->fetch_field('usergroupid');
					$this->registry->userinfo['membergroupids'] = $this->fetch_field('membergroupids');
					cache_permissions($this->registry->userinfo);
				}
			}

			// if the primary user group has been changed, we need to update any user activation records
			if (!empty($this->user['usergroupid']) AND $this->user['usergroupid'] != $this->existing['usergroupid'])
			{
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "useractivation
					SET usergroupid = " . $this->user['usergroupid'] . "
					WHERE userid = $userid
						AND type = 0
				");
			}

			if (isset($this->usertextfield['signature']))
			{
				// edited the signature, need to kill any parsed versions
				$this->dbobject->query_write("
					DELETE FROM " . TABLE_PREFIX . "sigparsed
					WHERE userid = $userid
				");
			}
		}

		// admin stuff
		$this->set_admin($userid, $usergroups_changed, $isadmin, $wasadmin);

		// super moderator stuff
		$this->set_supermod($userid, $usergroups_changed, $issupermod, $wasssupermod);

		// update birthday datastore
		$this->update_birthday_datastore($userid);

		// update password history
		$this->update_password_history($userid);

		// reset style cookie
		$this->update_style_cookie($userid);

		// reset threadedmode cookie
		$this->update_threadedmode_cookie($userid);

		// reset languageid cookie
		$this->update_language_cookie($userid);

		// Send parent email
		if ($this->info['coppauser'] AND $username = $this->fetch_field('username') AND $parentemail = $this->fetch_field('parentemail'))
		{
			//this uses in-scope variables in the phrases instead of composing the phrase normally.  It has to do with
			//the behavior of fetch_email_phrases which doesn't allow variables to be passed directly to the phrase.
			//$memberlink and $forumhomelink are referenced in the phrase and get interpolated when the phrase text is
			//eval'd.
			$memberlink = create_full_url(fetch_seo_url('member|nosession', array('userid' => $userid, 'username' => $username)));
			$forumhomelink = create_full_url(fetch_seo_url('forumhome|nosession', array()), true);

			if ($password = $this->info['coppapassword'])
			{
				eval(fetch_email_phrases('parentcoppa_register'));
			}
			else
			{
				eval(fetch_email_phrases('parentcoppa_profile'));
			}

			//$subject and $message are magic variables created by the code returned from fetch_email_phrase 
			//when it gets returned.
			vbmail($parentemail, $subject, $message, true);
		}

		($hook = vBulletinHook::fetch_hook('userdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Deletes a user
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($doquery = true)
	{
		if (!$this->existing['userid'])
		{
			return false;
		}

		// make sure we are not going to delete the last admin o.O
		if ($this->is_admin($this->existing['usergroupid'], $this->existing['membergroupids']) AND $this->count_other_admins($this->existing['userid']) == 0)
		{
			$this->error('cant_delete_last_admin');
			return false;
		}

		if (!$this->pre_delete($doquery))
		{
			return false;
		}

		$return = $this->db_delete(TABLE_PREFIX, 'user', $this->condition, $doquery);
		if ($return)
		{
			$this->db_delete(TABLE_PREFIX, 'userfield', $this->condition, $doquery);
			$this->db_delete(TABLE_PREFIX, 'usertextfield', $this->condition, $doquery);

			$this->post_delete($doquery);
		}

		return $return;
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "post SET
				username = '" . $this->dbobject->escape_string($this->existing['username']) . "',
				userid = 0
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "groupmessage SET
				postusername = '" . $this->dbobject->escape_string($this->existing['username']) . "',
				postuserid = 0
			WHERE postuserid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "discussion SET
				lastposter = '" . $this->dbobject->escape_string($this->existing['username']) . "',
				lastposterid = 0
			WHERE lastposterid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "visitormessage SET
				postusername = '" . $this->dbobject->escape_string($this->existing['username']) . "',
				postuserid = 0
			WHERE postuserid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "visitormessage
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "usernote SET
				username = '" . $this->dbobject->escape_string($this->existing['username']) . "',
				posterid = 0
			WHERE posterid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "usernote
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "access
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "event
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "customavatar
			WHERE userid = " . $this->existing['userid'] . "
		");
		@unlink($this->registry->options['avatarpath'] . '/avatar' . $this->existing['userid'] . '_' . $this->existing['avatarrevision'] . '.gif');

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "customprofilepic
			WHERE userid = " . $this->existing['userid'] . "
		");
		@unlink($this->registry->options['profilepicpath'] . '/profilepic' . $this->existing['userid'] . '_' . $this->existing['profilepicrevision'] . '.gif');

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "sigpic
			WHERE userid = " . $this->existing['userid'] . "
		");
		@unlink($this->registry->options['sigpicpath'] . '/sigpic' . $this->existing['userid'] . '_' . $this->existing['sigpicrevision'] . '.gif');

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "moderator
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "reputation
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribeforum
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribethread
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribeevent
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscriptionlog
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "session
			WHERE userid = " . $this->existing['userid'] . "
		");
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "userban
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "usergrouprequest
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "announcementread
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "infraction
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "groupread
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "discussionread
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribediscussion
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribegroup
			WHERE userid = " . $this->existing['userid'] . "
		");

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "profileblockprivacy
			WHERE userid = " . $this->existing['userid'] . "
		");

		$pendingfriends = array();
		$currentfriends = array();

		$friendlist = $this->dbobject->query_read("
			SELECT relationid, friend
			FROM " . TABLE_PREFIX . "userlist
			WHERE userid = " . $this->existing['userid'] . "
				AND type = 'buddy'
				AND friend IN('pending','yes')
		");

		while ($friend = $this->dbobject->fetch_array($friendlist))
		{
			if ($friend['friend'] == 'yes')
			{
				$currentfriends[] = $friend['relationid'];
			}
			else
			{
				$pendingfriends[] = $friend['relationid'];
			}
		}

		if (!empty($pendingfriends))
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET friendreqcount = IF(friendreqcount > 0, friendreqcount - 1, 0)
				WHERE userid IN (" . implode(", ", $pendingfriends) . ")
			");
		}

		if (!empty($currentfriends))
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET friendcount = IF(friendcount > 0, friendcount - 1, 0)
				WHERE userid IN (" . implode(", ", $currentfriends) . ")
			");
		}

		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "userlist
			WHERE userid = " . $this->existing['userid'] . " OR relationid = " . $this->existing['userid']
		);

		$admindm =& datamanager_init('Admin', $this->registry, ERRTYPE_SILENT);
		$admindm->set_existing($this->existing);
		$admindm->delete();
		unset($admindm);

		$groups = $this->registry->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "socialgroup
			WHERE creatoruserid = " . $this->existing['userid']
		);

		$groupsowned = array();

		while ($group = $this->registry->db->fetch_array($groups))
		{
			$groupsowned[] = $group['groupid'];
		}
		$this->registry->db->free_result($groups);

		if (!empty($groupsowned))
		{
			require_once(DIR . '/includes/functions_socialgroup.php');
			foreach($groupsowned AS $groupowned)
			{
				$group = fetch_socialgroupinfo($groupowned);
				if (!empty($group))
				{
					// dm will have problem if the group is invalid, and in all honesty, at this situation,
					// if the group is no longer present, then we don't need to worry about it anymore.
					$socialgroupdm = datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
					$socialgroupdm->set_existing($group);
					$socialgroupdm->delete();
				}
			}
		}

		$groupmemberships = $this->registry->db->query_read("
			SELECT socialgroup.*
			FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
			INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON
				(socialgroup.groupid = socialgroupmember.groupid)
			WHERE socialgroupmember.userid = " . $this->existing['userid']
		);

		$socialgroups = array();
		while ($groupmembership = $this->registry->db->fetch_array($groupmemberships))
		{
			$socialgroups["$groupmembership[groupid]"] = $groupmembership;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();

		$picture_sql = $this->registry->db->query_read("
			SELECT a.attachmentid, a.filedataid, a.userid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.userid = " . $this->existing['userid'] . "
					AND
				a.contenttypeid IN (" . intval($types->getContentTypeID('vBForum_SocialGroup')) . "," . intval($types->getContentTypeID('vBForum_Album')) . ")
		");
		$pictures = array();

		$attachdm =& datamanager_init('Attachment', $this->registry, ERRTYPE_SILENT, 'attachment');
		while ($picture = $this->registry->db->fetch_array($picture_sql))
		{
			$attachdm->set_existing($picture);
			$attachdm->delete();
		}

		if (!empty($socialgroups))
		{
			$this->registry->db->query_write("DELETE FROM " . TABLE_PREFIX . "socialgroupmember WHERE userid = "  . $this->existing['userid']);

			foreach ($socialgroups AS $group)
			{
				$groupdm =& datamanager_init('SocialGroup', $this->registry, ERRTYPE_STANDARD);
				$groupdm->set_existing($group);
				$groupdm->rebuild_membercounts();
				$groupdm->rebuild_picturecount();
				$groupdm->save();

				list($pendingcountforowner) = $this->registry->db->query_first("
					SELECT SUM(moderatedmembers) FROM " . TABLE_PREFIX . "socialgroup
					WHERE creatoruserid = " . $group['creatoruserid']
				, DBARRAY_NUM);

				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "user
					SET socgroupreqcount = " . intval($pendingcountforowner) . "
					WHERE userid = " . $group['creatoruserid']
				);
			}

			unset($groupdm);
		}

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "socialgroup
			SET transferowner = 0
			WHERE transferowner = " . $this->existing['userid']
		);

		$this->registry->db->query_write("DELETE FROM " . TABLE_PREFIX . "album WHERE userid = " . $this->existing['userid']);


		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "picturecomment SET
				postusername = '" . $this->dbobject->escape_string($this->existing['username']) . "',
				postuserid = 0
			WHERE postuserid = " . $this->existing['userid'] . "
		");

		require_once(DIR . '/includes/adminfunctions.php');
		delete_user_pms($this->existing['userid'], false);

		require_once(DIR . '/includes/functions_databuild.php');

		($hook = vBulletinHook::fetch_hook('userdata_delete')) ? eval($hook) : false;

		build_user_statistics();
		build_birthdays();
	}

	// #############################################################################
	// functions that are executed as part of the user save routine

	/**
	* Updates all denormalized tables that contain a 'username' field (or field that holds a username)
	*
	* @param	integer	User ID
	* @param	string	The user name. Helpful if you want to call this function from outside the DM.
	*/
	function update_username($userid, $username = null)
	{
		if ($username != null AND $username != '')
		{
			$doupdate = true;
		}
		else if (isset($this->user['username']) AND $this->user['username'] != $this->existing['username'])
		{
			$doupdate = true;
			$username = $this->user['username'];
		}
		else
		{
			$doupdate = false;
		}

		if ($doupdate)
		{
			// pm receipt 'tousername'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "pmreceipt SET
					tousername = '" . $this->dbobject->escape_string($username) . "'
				WHERE touserid = $userid
			");

			// pm text 'fromusername'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "pmtext SET
					fromusername = '" . $this->dbobject->escape_string($username) . "'
				WHERE fromuserid = $userid
			");

			// these updates work only when the old username is known,
			// so don't bother forcing them to update if the names aren't different
			if ($this->existing['username'] != $username)
			{
				// pm text 'touserarray'
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "pmtext SET
						touserarray = REPLACE(touserarray,
							'i:$userid;s:" . strlen($this->existing['username']) . ":\"" . $this->dbobject->escape_string($this->existing['username']) . "\";',
							'i:$userid;s:" . strlen($username) . ":\"" . $this->dbobject->escape_string($username) . "\";'
						)
					WHERE touserarray LIKE '%i:$userid;s:" . strlen($this->existing['username']) . ":\"" . $this->dbobject->escape_string_like($this->existing['username']) . "\";%'
				");

				// forum 'lastposter'
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "forum SET
						lastposter = '" . $this->dbobject->escape_string($username) . "'
					WHERE lastposter = '" . $this->dbobject->escape_string($this->existing['username']) . "'
				");

				// thread 'lastposter'
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "thread SET
						lastposter = '" . $this->dbobject->escape_string($username) . "'
					WHERE lastposter = '" . $this->dbobject->escape_string($this->existing['username']) . "'
				");
			}

			// thread 'postusername'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					postusername = '" . $this->dbobject->escape_string($username) . "'
				WHERE postuserid = $userid
			");

			// post 'username'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "post SET
					username = '" . $this->dbobject->escape_string($username) . "'
				WHERE userid = $userid
			");

			// usernote 'username'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "usernote
				SET username = '" . $this->dbobject->escape_string($username) . "'
				WHERE posterid = $userid
			");

			// deletionlog 'username'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "deletionlog
				SET username = '" . $this->dbobject->escape_string($username) . "'
				WHERE userid = $userid
			");

			// editlog 'username'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "editlog
				SET username = '" . $this->dbobject->escape_string($username) . "'
				WHERE userid = $userid
			");

			// postedithistory 'username'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "postedithistory
				SET username = '" . $this->dbobject->escape_string($username) . "'
				WHERE userid = $userid
			");

			// socialgroup 'lastposter'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "socialgroup
				SET lastposter = '" . $this->dbobject->escape_string($username) . "'
				WHERE lastposterid = $userid
			");

			// discussion 'lastposter'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "discussion
				SET lastposter = '" . $this->dbobject->escape_string($username) . "'
				WHERE lastposterid = $userid
			");

			// groupmessage 'postusername'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "groupmessage
				SET postusername = '" . $this->dbobject->escape_string($username) . "'
				WHERE postuserid = $userid
			");

			// visitormessage 'postusername'
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "visitormessage
				SET postusername = '" . $this->dbobject->escape_string($username) . "'
				WHERE postuserid = $userid

			");

			//  Rebuild newest user information
			require_once(DIR . '/includes/functions_databuild.php');

			($hook = vBulletinHook::fetch_hook('userdata_update_username')) ? eval($hook) : false;

			build_user_statistics();
			build_birthdays();
		}
	}

	/**
	* Updates user subscribed threads/forums to reflect new permissions
	*
	* @param	integer	User ID
	*/
	function update_subscriptions($userid)
	{
		unset($this->existing['forumpermissions']);
		$this->existing['permissions'] = cache_permissions($this->existing);

		$old_canview = array();
		$old_canviewthreads = array();
		foreach ($this->existing['forumpermissions'] AS $forumid => $perms)
		{
			if ($perms & $this->registry->bf_ugp_forumpermissions['canview'])
			{
				$old_canview[] = $forumid;
			}
			if ($perms & $this->registry->bf_ugp_forumpermissions['canviewthreads'])
			{
				$old_canviewthreads[] = $forumid;
			}
		}

		$user_perms = array(
			'userid'         => $this->fetch_field('userid'),
			'usergroupid'    => $this->fetch_field('usergroupid'),
			'membergroupids' => $this->fetch_field('membergroupids')
		);

		cache_permissions($user_perms);
		$remove_subs = array();
		$remove_forums = array();
		foreach ($old_canview AS $forumid)
		{
			if (!($user_perms['forumpermissions']["$forumid"] & $this->registry->bf_ugp_forumpermissions['canview']))
			{
				$remove_forums[] = $forumid;
			}
		}
		foreach($old_canviewthreads AS $forumid)
		{
			if (!($user_perms['forumpermissions']["$forumid"] & $this->registry->bf_ugp_forumpermissions['canviewthreads']))
			{
				$remove_subs[] = $forumid;
			}
		}

		$add_subs = array();
		foreach ($user_perms['forumpermissions'] AS $forumid => $perms)
		{
			if (($perms & $this->registry->bf_ugp_forumpermissions['canviewthreads'] AND $perms & $this->registry->bf_ugp_forumpermissions['canview']) AND (!($this->existing['forumpermissions']["$forumid"] & $this->registry->bf_ugp_forumpermissions['canviewthreads']) OR !($this->existing['forumpermissions']["$forumid"] & $this->registry->bf_ugp_forumpermissions['canview'])))
			{
				$add_subs[] = $forumid;
			}
		}

		if (!empty($remove_forums))
		{
			$forum_list = implode(',', $remove_forums);
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "subscribeforum
				WHERE userid = $userid
					AND forumid IN ($forum_list)
			");
		}

		$remove_subs = array_unique(array_merge($remove_subs, $remove_forums));

		if (!empty($remove_subs) OR !empty($add_subs))
		{
			$forum_list = implode(',', array_unique(array_merge($remove_subs, $add_subs)));
			$threads = $this->dbobject->query_read_slave("
				SELECT subscribethread.canview, subscribethreadid, thread.forumid
				FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = subscribethread.threadid)
				WHERE subscribethread.userid = $userid
					AND thread.forumid IN ($forum_list)
			");
			$remove_thread = array();
			$add_thread = array();
			while ($thread = $this->dbobject->fetch_array($threads))
			{
				if ($thread['canview'] == 0 AND in_array($thread['forumid'], $add_subs))
				{
					$add_thread[] = $thread['subscribethreadid'];
				}
				else if ($thread['canview'] == 1 AND in_array($thread['forumid'], $remove_subs))
				{
					$remove_thread[] = $thread['subscribethreadid'];
				}
			}
			unset($add_subs, $remove_subs);
			$this->dbobject->free_result($threads);
			if (!empty($remove_thread) OR !empty($add_thread))
			{
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "subscribethread
					SET canview =
					CASE
						" . (!empty($remove_thread) ? " WHEN subscribethreadid IN (" . implode(', ', $remove_thread) . ") THEN 0" : "") . "
						" . (!empty($add_thread) ? " WHEN subscribethreadid IN (" . implode(', ', $add_thread) . ") THEN 1" : "") . "
					ELSE canview
					END
					WHERE userid = $userid
					AND subscribethreadid IN (" . implode(',', array_unique(array_merge($remove_thread, $add_thread))) . ")
				");
			}
		}
	}

	/**
	* Rebuilds the birthday datastore if the user's birthday has changed
	*
	* @param	integer	User ID
	*/
	function update_birthday_datastore($userid)
	{
		if ($this->registry->options['showbirthdays'])
 		{
			if ($this->fetch_field('birthday') != $this->existing['birthday']
				OR $this->fetch_field('showbirthday') != $this->existing['showbirthday']
				OR $this->usergroups_changed()
			)
			{
				require_once(DIR . '/includes/functions_databuild.php');
				build_birthdays();
			}
		}
	}

	/**
	* Inserts a record into the password history table if the user's password has changed
	*
	* @param	integer	User ID
	*/
	function update_password_history($userid)
	{
		if (isset($this->user['password']) AND $this->user['password'] != $this->existing['password'])
		{
			/*insert query*/
			$this->dbobject->query_write("
				INSERT INTO " . TABLE_PREFIX . "passwordhistory (userid, password, passworddate)
				VALUES ($userid, '" . $this->dbobject->escape_string($this->user['password']) . "', FROM_UNIXTIME(" . TIMENOW . "))
			");
		}
	}

	/**
	* Resets the session styleid and styleid cookie to the user's profile choice
	*
	* @param	integer	User ID
	*/
	function update_style_cookie($userid)
	{
		if (isset($this->user['styleid']) AND $this->registry->options['allowchangestyles'] AND $userid == $this->registry->userinfo['userid'])
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "session SET
					styleid = " . $this->user['styleid'] . "
				WHERE sessionhash = '" . $this->dbobject->escape_string($this->registry->session->vars['dbsessionhash']) . "'
			");
			if (!@headers_sent())
			{
				vbsetcookie('userstyleid', '', 1);
			}
		}
	}

	/**
	* Resets the languageid cookie to the user's profile choice
	*
	* @param	integer	User ID
	*/
	function update_language_cookie($userid)
	{
	if (isset($this->user['languageid']) AND !empty($this->registry->languagecache[$this->user['languageid']]['userselect']))
		{
			if (!@headers_sent())
			{
				vbsetcookie('languageid', '', 1);
			}
		}
	}

	/**
	* Resets the threadedmode cookie to the user's profile choice
	*
	* @param	integer	User ID
	*/
	function update_threadedmode_cookie($userid)
	{
		if (isset($this->user['threadedmode']))
		{
			if (!@headers_sent())
			{
				vbsetcookie('threadedmode', '', 1);
			}
		}
	}

	/**
	* Checks to see if a user's usergroup memberships have changed
	*
	* @return	boolean	Returns true if memberships have changed
	*/
	function usergroups_changed()
	{
		if (isset($this->user['usergroupid']) AND $this->user['usergroupid'] != $this->existing['usergroupid'])
		{
			return true;
		}
		else if (isset($this->user['membergroupids']) AND $this->user['membergroupids'] != $this->existing['membergroupids'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Checks usergroupid and membergroupids to see if the user has admin privileges
	*
	* @param	integer	Usergroupid
	* @param	string	Membergroupids (comma separated)
	*
	* @return	boolean	Returns true if user has admin privileges
	*/
	function is_admin($usergroupid, $membergroupids)
	{
		if ($this->registry->usergroupcache["$usergroupid"]['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
		{
			return true;
		}
		else if ($this->registry->usergroupcache["$usergroupid"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['allowmembergroups'])
		{
			if ($membergroupids != '')
			{
				foreach (explode(',', $membergroupids) AS $membergroupid)
				{
					if ($this->registry->usergroupcache["$membergroupid"]['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	* Checks usergroupid and membergroupids to see if the user has super moderator privileges
	*
	* @param	integer	Usergroupid
	* @param	string	Membergroupids (comma separated)
	*
	* @return	boolean	Returns true if user has super moderator privileges
	*/
	function is_supermod($usergroupid, $membergroupids)
	{
		if ($this->registry->usergroupcache["$usergroupid"]['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
		{
			return true;
		}
		else if ($this->registry->usergroupcache["$usergroupid"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['allowmembergroups'])
		{
			if ($membergroupids != '')
			{
				foreach (explode(',', $membergroupids) AS $membergroupid)
				{
					if ($this->registry->usergroupcache["$membergroupid"]['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	* Counts the number of administrators OTHER THAN the user specified
	*
	* @param	integer	User ID of user to be checked
	*
	* @return	integer	The number of administrators excluding the current user
	*/
	function count_other_admins($userid)
	{
		$admingroups = array();
		$groupsql = '';
		foreach ($this->registry->usergroupcache AS $usergroupid => $usergroup)
		{
			if ($usergroup['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$admingroups[] = $usergroupid;
				if ($usergroup['genericoptions'] & $this->registry->bf_ugp_genericoptions['allowmembergroups'])
				{
					$groupsql .= "
					OR FIND_IN_SET('$usergroupid', membergroupids)";
				}
			}
		}

		$countadmin = $this->dbobject->query_first("
			SELECT COUNT(*) AS users
			FROM " . TABLE_PREFIX . "user
			WHERE userid <> " . intval($userid) . "
			AND
			(
				usergroupid IN(" . implode(',', $admingroups) . ")" .
				$groupsql . "
			)
		");

		return $countadmin['users'];
	}

	/**
	* Inserts or deletes a record from the administrator table if necessary
	*
	* @param	integer	User ID of this user
	* @param	boolean	Whether or not the usergroups of this user have changed
	* @param	boolean	Whether or not the user is now an admin
	* @param	boolean	Whether or not the user was an admin before this update
	*/
	function set_admin($userid, $usergroups_changed, $isadmin, $wasadmin = false)
	{
		if ($isadmin AND !$wasadmin)
		{
			// insert admin record
			$admindm =& datamanager_init('Admin', $this->registry, ERRTYPE_SILENT);
			$admindm->set('userid', $userid);
			$admindm->save();
			unset($admindm);

			$this->insertedadmin = true;
		}
		else if ($usergroups_changed AND $wasadmin AND !$isadmin)
		{
			// delete admin record
			$info = array('userid' => $userid);

			$admindm =& datamanager_init('Admin', $this->registry, ERRTYPE_SILENT);
			$admindm->set_existing($info);
			$admindm->delete();
			unset($admindm);
		}
		/*else
		{
			echo "<p style=\"color:white\">No change needed for Admin record
			wasadmin: " . ($wasadmin ? 'Y' : 'N') . "<br />
			isadmin: " . ($isadmin ? 'Y' : 'N') . "<br />
			ugchanged: " . ($wasadmin ? 'Y' : 'N') . "<br />
			</p>";
		}*/
	}

	/**
	* Inserts or deletes a record from the moderators table if necessary
	*
	* @param	integer	User ID of this user
	* @param	boolean	Whether or not the usergroups of this user have changed
	* @param	boolean	Whether or not the user is now a super moderator
	* @param	boolean	Whether or not the user was a super moderator before this update
	*/
	function set_supermod($userid, $usergroups_changed, $issupermod, $wassupermod = false)
	{
		if ($issupermod AND !$wassupermod)
		{
			// insert super moderator record
			$moddata =& datamanager_init('Moderator', $this->registry, ERRTYPE_SILENT);
			$moddata->set('userid', $userid);
			$moddata->set('forumid', -1);
			// need to insert permissions without looping everything
			// the following doesn't yet work.
			$moddata->set('permissions', 0);
			$moddata->save();
			unset($moddata);

		}
		else if ($usergroups_changed AND $wassupermod AND !$issupermod)
		{
			// delete super moderator record
			$info = array('userid' => $userid, 'forumid' => -1);

			$moddata =& datamanager_init('Moderator', $this->registry, ERRTYPE_SILENT);
			$moddata->set_existing($info);
			$moddata->delete();
			unset($moddata);
		}
	}

	/**
	* Bla bla bla
	*
	* @param	integer	User ID
	*/
	function update_ban_status($userid)
	{
		$userid = intval($userid);
		$usergroupid = $this->fetch_field('usergroupid');

		if ($this->registry->usergroupcache["$usergroupid"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['isnotbannedgroup'])
		{
			// user is going to a non-banned group, so there's no reason to keep this record (it won't be used)
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "userban
				WHERE userid = $userid
			");
		}
		else
		{
			// check to see if there is already a ban record for this user...
			if (!($check = $this->dbobject->query_first("SELECT userid FROM " . TABLE_PREFIX . "userban WHERE userid = $userid")))
			{
				// ... there isn't, so create one
				$ousergroupid = $this->existing['usergroupid'];
				$odisplaygroupid = $this->existing['displaygroupid'];

				// make sure the ban lifting record doesn't loop back to a banned group
				if (!($this->registry->usergroupcache["$ousergroupid"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['isnotbannedgroup']))
				{
					$ousergroupid = 2;
				}
				if (!($this->registry->usergroupcache["$odisplaygroupid"]['genericoptions'] & $this->registry->bf_ugp_genericoptions['isnotbannedgroup']))
				{
					$odisplaygroupid = 0;
				}

				// insert a ban record
				/*insert query*/
				$this->dbobject->query_write("
					INSERT INTO " . TABLE_PREFIX . "userban
						(userid, usergroupid, displaygroupid, customtitle, usertitle, adminid, bandate, liftdate)
					VALUES
						($userid,
						" . $ousergroupid . ",
						" . $odisplaygroupid . ",
						" . intval($this->fetch_field('customtitle')) . ",
						'" . $this->dbobject->escape_string($this->fetch_field('usertitle')) . "',
						" . $this->registry->userinfo['userid'] . ",
						" . TIMENOW . ",
						0)
				");
			}
		}
	}

	/**
	* Sends a welcome pm to the user
	*
	*/
	function send_welcomepm($fromuser = null)
	{
		if ($this->registry->options['welcomepm'] AND $username = unhtmlspecialchars($this->fetch_field('username')))
		{
			if (!$fromuser)
			{
				$fromuser = fetch_userinfo($this->registry->options['welcomepm']);
			}

			if ($fromuser)
			{
				cache_permissions($fromuser, false);
				eval(fetch_email_phrases('welcomepm'));

				// create the DM to do error checking and insert the new PM
				$pmdm =& datamanager_init('PM', $this->registry, ERRTYPE_SILENT);
				$pmdm->set_info('is_automated', true);
				$pmdm->set('fromuserid', $fromuser['userid']);
				$pmdm->set('fromusername', $fromuser['username']);
				$pmdm->set_info('receipt', false);
				$pmdm->set_info('savecopy', false);
				$pmdm->set('title', $subject);
				$pmdm->set('message', $message);
				$pmdm->set_recipients($username, $fromuser['permissions']);
				$pmdm->set('dateline', TIMENOW);
				$pmdm->set('allowsmilie', true);

				($hook = vBulletinHook::fetch_hook('private_insertpm_process')) ? eval($hook) : false;

				$pmdm->pre_save();
				if (empty($pmdm->errors))
				{
					$pmdm->save();
					($hook = vBulletinHook::fetch_hook('private_insertpm_complete')) ? eval($hook) : false;
				}
				unset($pmdm);
			}
		}
	}
}

/**
* Class to do data update operations for multiple USERS simultaneously
*
* @package	vBulletin
* @version	$Revision: 45207 $
* @date		$Date: 2011-06-28 14:59:12 -0700 (Tue, 28 Jun 2011) $
*/
class vB_DataManager_User_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_User';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'userid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "user AS user";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}

	/**
	* Sets the values for user[usertitle] and user[customtitle]
	*
	* @param	string	Custom user title text
	* @param	boolean	Whether or not to reset a custom title to the default user title
	* @param	array	Array containing all information for the user's primary usergroup
	* @param	boolean	Whether or not a user can use custom user titles ($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle'])
	* @param	boolean	Whether or not the user is an administrator ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	*/
	function set_usertitle($customtext, $reset, $usergroup, $canusecustomtitle, $isadmin)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]->set_usertitle($customtext, $reset, $usergroup, $canusecustomtitle, $isadmin);
		}
	}

	/**
	* Validates and sets custom user profile fields
	*
	* @param	array	Array of values for profile fields. Example: array('field1' => 'One', 'field2' => array(0 => 'a', 1 => 'b'), 'field2_opt' => 'c')
	*/
	function set_userfields(&$values)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]-> set_userfields($values);
		}
	}

	/**
	* Sets DST options
	*
	* @param	integer	DST choice: (2: automatic; 1: auto-off, dst on; 0: auto-off, dst off)
	*/
	function set_dst(&$dst)
	{
		if ($this->children)
		{
			$firstid = reset($this->primary_ids);
			$this->children["$firstid"]->set_dst($dst);
		}
	}

	/**
	* Pushes the changes made to the "master" child to the rest.
	*/
	function copy_changes()
	{
		if (sizeof($this->children) > 1)
		{
			$firstid = reset($this->primary_ids);
			$master =& $this->children["$firstid"];

			while ($id = next($this->primary_ids))
			{
				$child =& $this->children["$id"];

				$child->user = $master->user;
				$child->userfield = $master->userfield;
				$child->usertextfield = $master->usertextfield;

				$child->info = $master->info;
			}
		}
	}

	/**
	* Executes the necessary query/queries to update the records
	*
	* @param	boolean	Actually perform the query?
	*/
	function execute_query($doquery = true)
	{
		$condition = 'userid IN (' . implode(',', $this->primary_ids) . ')';
		$master =& $this->children[reset($this->primary_ids)];

		foreach (array('user', 'userfield', 'usertextfield') AS $table)
		{
			if (is_array($master->$table) AND !empty($master->$table))
			{
				$sql = $master->fetch_update_sql(TABLE_PREFIX, $table, $condition);

				if ($doquery)
				{
					$this->dbobject->query_write($sql);
				}
				else
				{
					echo "<pre>$sql<hr /></pre>";
				}
			}
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 45207 $
|| ####################################################################
\*======================================================================*/
?>
