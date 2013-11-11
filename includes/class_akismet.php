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

/*
Example code
$akismet = new vB_Akismet($vbulletin);
$akismet->akismet_board = 'http://dev.vbulletin.com/vbblog/';
$akismet->akismet_key = '<ENTER YOUR OWN KEY>';

var_dump($akismet->verify_text(array('user_ip' => IPADDRESS, 'user_agent' => USER_AGENT, 'comment_author' => 'viagra-test-123', 'comment_content' => 'This is a test')));
*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_vurl.php');

/**
* Class to handle interacting with the Akismet service
*
* @package	vBulletin
*/
class vB_Akismet
{
	/**
	* vBulletin Registry Object
	*
	* @var	Object
	*/
	var $registry = null;

	/**
	* Akismet host
	*
	* @var	string
	*/
	var $akismet_host = 'rest.akismet.com';

	/**
	* Akismet version, used in URI
	*
	* @var	string
	*/
	var $akismet_version = '1.1';

	/**
	* Akismet key
	*
	* @var	string
	*/
	var $akismet_key = '';

	/**
	* Akismet board URL
	*
	* @var	string
	*/
	var $akismet_board = '';

	/**
	* Akismet built URL
	*
	* @var	string
	*/
	var $_akismet_api_url = null;

	/**
	* Constructor
	*
	* @param	vB_Registry	The instance of the vB_Registry object
	*/
	function vB_Akismet(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error(get_class($this) . '::Registry object is not an object', E_USER_ERROR);
		}

		switch ($this->registry->options['vb_antispam_type'])
		{
			case 1:
				$this->akismet_host = 'rest.akismet.com';
			break;
			case 2:
				$this->akismet_host = 'api.antispam.typepad.com';
			break;
		}
	}

	/**
	* Makes a verification call to Aksimet to check content
	*
	* @param	array	Array of keys and values, http://akismet.com/development/api/
	*
	* @return	string	spam or ham
	*/
	function verify_text($params)
	{
		if (!$this->_build())
		{
			return false;
		}
		$result = $this->_submit($this->_akismet_api_url . '/comment-check', $params);
		return (strpos($result, 'true') !== false) ? 'spam' : 'ham';
	}

	/**
	* Identify a missed item as spam
	*
	* @param	array	Array of keys and values, http://akismet.com/development/api/
	*
	* @return	string	direct result from API call
	*/
	function mark_as_spam($params)
	{
		if (!$this->_build())
		{
			return false;
		}
		$result = $this->_submit($this->_akismet_api_url . '/submit-spam', $params);
		return $result;
	}

	/**
	* Identify a missed identified item as ham (false positive)
	*
	* @param	array	Array of keys and values, http://akismet.com/development/api/
	*
	* @return	string	direct result from API call
	*/
	function mark_as_ham($params)
	{
		if (!$this->_build())
		{
			return false;
		}
		$result = $this->_submit($this->_akismet_api_url . '/submit-ham', $params);
		return $result;
	}

	/**
	* Verify that the supplied Akismet key is valid and build the API URL
	*
	* @return	boolean	True if the building succeeded else false
	*/
	function _build()
	{
		if ($this->_akismet_api_url === null)
		{
			// deal with new setting if scanning is disabled
			if (!$this->registry->options['vb_antispam_type'])
			{
				return false;
			}

			$check_key = 'http://' . $this->akismet_host . '/' . $this->akismet_version . '/verify-key';
			// if they entered the key in vB Options we'll assume its correct.
			if ($this->akismet_key == $this->registry->options['vb_antispam_key'] OR strpos($this->_submit($check_key, array('key' => $this->akismet_key)), 'invalid') === false)
			{
				$this->_akismet_api_url = 'http://' . $this->akismet_key . '.' . $this->akismet_host . '/' . $this->akismet_version;
				return true;
			}
			// trigger_error or something else :)
			return false;
		}
		return true;
	}

	/**
	* Submits a request to the Akismet service (POST)
	*
	* @access	private
	*
	* @param	string	URL to submit to
	* @param	array	Array of data to submit
	*
	* @return	string	Data returned by Akismet
	*/
	function _submit($url, $params)
	{
		$query = array();
		$params['blog'] = $this->akismet_board;
		foreach($params AS $key => $val)
		{
			if (!empty($val))
			{
				$query[] = $key . '=' . urlencode($val);
			}
		}

		$vurl = new vB_vURL($this->registry);
		$vurl->set_option(VURL_URL, $url);
		$vurl->set_option(VURL_USERAGENT, 'vBulletin/' . FILE_VERSION . ' | Akismet/1.1');
		$vurl->set_option(VURL_POST, 1);
		$vurl->set_option(VURL_POSTFIELDS, implode('&', $query));
		$vurl->set_option(VURL_RETURNTRANSFER, 1);
		$vurl->set_option(VURL_CLOSECONNECTION, 1);
		return $vurl->exec();
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>