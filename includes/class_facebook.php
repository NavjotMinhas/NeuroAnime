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

require_once(DIR . '/includes/facebook/facebook.php');

/**
 * Extension of the Facebook API class, so we can use vUrl instead of cUrl
 *
 * @package vBulletin
 * @author Michael Henretty, vBulletin Development Team
 * @version $Revision: 42666 $
 * @since $Date: 2011-04-05 15:17:42 -0700 (Tue, 05 Apr 2011) $
 * @copyright vBulletin Solutions Inc.
 */
class Facebook_vUrl extends Facebook
{
	/**
	 * Overrides the Facebook API request methods, so we can use vUrl
	 *
	 * @param String $url the URL to make the request to
	 * @param Array $params the parameters to use for the POST body
	 * @param CurlHandler $ch optional initialized curl handle
	 * @return String the response text
	 */
	protected function makeRequest($url, $params, $ch = null)
	{
		// try Facebook's cURL implementation (including the new bundled certificates)
		if (function_exists('curl_init'))
		{
			try
			{
				$result = parent::makeRequest($url, $params, $ch);
			}
			catch (Exception $e)
			{
				$result = false;
			}

			if ($result)
			{
				return $result;
			}
		}

		// use vB_vURL implmentation
		global $vbulletin;
		$opts = self::$CURL_OPTS;

		require_once(DIR . '/includes/class_vurl.php');
		$vurl = new vB_vURL($vbulletin);
		$vurl->set_option(VURL_URL, $url);
		$vurl->set_option(VURL_CONNECTTIMEOUT, $opts[CURLOPT_CONNECTTIMEOUT]);
		$vurl->set_option(VURL_TIMEOUT, $opts[CURLOPT_TIMEOUT]);
		$vurl->set_option(VURL_POST, 1);
		// If we want to use more advanced features such as uploading pictures
		// to facebook, we may need to remove http_build_query and refactor
		// vB_vURL to accept an array of POST data and send the multipart/form-data
		// Content-Type header.
		$vurl->set_option(VURL_POSTFIELDS, http_build_query($params, '', '&'));
		$vurl->set_option(VURL_RETURNTRANSFER, $opts[CURLOPT_RETURNTRANSFER]);
		$vurl->set_option(VURL_CLOSECONNECTION, $opts[CURLOPT_RETURNTRANSFER]);
		$vurl->set_option(VURL_USERAGENT, $opts[CURLOPT_USERAGENT]);

		$result = $vurl->exec();

		// TODO: add some error checking here
		// particularly check if $vurl->fetch_error() returns VURL_ERROR_SSL, meaning the server
		// does not have access to TLS/SSL with which to communicate with facebook

		return $result;
	}
}

/**
 * vBulletin wrapper for the facebook client api, singleton
 *
 * @package vBulletin
 * @author Michael Henretty, vBulletin Development Team
 * @version $Revision: 42666 $
 * @since $Date: 2011-04-05 15:17:42 -0700 (Tue, 05 Apr 2011) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Facebook
{
	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Facebook
	 */
	protected static $instance = null;

	/**
	 * The facebook client api object
	 *
	 * @var Facebook
	 */
	protected $facebook = null;

	/**
	 * The facebook session array
	 *
	 * @var array
	 */
	protected $fb_session = null;

	/**
	 * The facebook userid if logged in
	 *
	 * @var int
	 */
	protected $registry = null;

	/**
	 * The facebook userid if logged in
	 *
	 * @var int
	 */
	protected $fb_userid = null;

	/**
	 * The associated vBulletin userid if available
	 *
	 * @var int
	 */
	protected $vb_userid = null;

	/**
	 * The user infomation array we want to grab from fb api by default
	 *
	 * @var array
	 */
	protected $fb_userinfo = array();
	protected $fql_fields = array(
		'uid',
		'name',
		'first_name',
		'last_name',
		'about_me',
		'timezone',
		'email',
		'locale',
		'current_location',
		'affiliations',
		'profile_url',
		'sex',
		'pic_square',
		'pic',
		'pic_big',
		'birthday',
		'birthday_date',
		'profile_blurb',
		'website',
		'activities',
		'interests',
		'music',
		'movies',
		'books',
		'website',
		'quotes',
		'work_history'
	);

	/**
	 * The users connection info we want to grab
	 *
	 * @var array
	 */
	protected $fb_userconnectioninfo = array();
	protected $connection_fields = array(
		'activities',
		'interests',
		'music',
		'movies',
		'books',
		'notes',
		'website'
	);

	/**
	 * Returns an instance of the facebook client api object
	 *
	 * @return vB_Facebook
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			// boot up the facebook api
			self::$instance = new vB_Facebook();
		}

		return self::$instance;
	}


	/**
	 * Constructor
	 *
	 * @param int $apikey	the api key for the facebook user
	 * @param int $secret	the facebook secret for the application
	 */
	protected function __construct()
	{
		// cache a reference to the registry object
		global $vbulletin;
		$this->registry = $vbulletin;

		// initialize fb api and grab fb userid to cache locally
		try
		{
			// init the facebook graph api
			$this->facebook = new Facebook_vUrl(array(
			  'appId'  => $this->registry->options['facebookappid'],
			  'secret' => $this->registry->options['facebooksecret'],
			  'cookie' => true
			));

			// check for valid session without pinging facebook
			if ($this->fb_session = $this->facebook->getSession())
			{
				$this->fb_userid = $this->fb_session['uid'];

				// make sure local copy of fb session is up to date
				$this->validateFBSession();
			}
		}
		catch (Exception $e)
		{
			$this->fb_userid = null;
		}
	}


	/**
	 * Checks the fb userid returned from api to make sure its valid
	 *
	 * @return bool, fb userid if logged in, false otherwise
	 */
	protected function isValidUser()
	{
		// check for null restuls, or error code (<1000)
		return (!empty($this->fb_userid) AND !$this->fb_userid < 1000);
	}

	/**
	 * Makes sure local copy of FB session is in synch with actual FB session
	 *
	 * @return bool, fb userid if logged in, false otherwise
	 */
	protected function validateFBSession()
	{
		// grab the current access token stored locally (in cookie or db depending on login status)
		if ($this->registry->userinfo['userid'] == 0)
		{
			$curaccesstoken = $this->registry->input->clean_gpc('c', COOKIE_PREFIX . 'fbaccesstoken', TYPE_STR);
		}
		else
		{
			$curaccesstoken = !empty($this->registry->userinfo['fbaccesstoken']) ? $this->registry->userinfo['fbaccesstoken'] : '';
		}

		// if we have a new access token that is valid, re-query FB for updated info, and cache it locally
		if ($curaccesstoken != $this->fb_session['access_token'] AND $this->isValidAuthToken())
		{
			// update the userinfo array with fresh facebook data
			$this->registry->userinfo['fbaccesstoken'] = $this->fb_session['access_token'];

			//$this->registry->userinfo['fbprofilepicurl'] = $this->fb_userinfo['pic_square'];

			// if user is guest, store fb session info in cookie
			if ($this->registry->userinfo['userid'] == 0)
			{
				vbsetcookie('fbaccesstoken', $this->fb_session['access_token']);
				vbsetcookie('fbprofilepicurl', $this->fb_userinfo['pic_square']);
			}

			// if authenticated user, store fb session in user table
			else
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "user
					SET
						fbaccesstoken = '" . $this->fb_session['access_token'] . "'
					WHERE userid = " . $this->registry->userinfo['userid'] . "
				");
			}
		}
	}


	/**
	 * Checks if the current user is logged into facebook
	 *
	 * @return bool
	 */
	public function userIsLoggedIn()
	{
		// make sure facebook is connect also enabled
		return self::instance()->isValidUser();
	}


	/**
	 * Verifies that the current session auth token is still valid with facebook
	 * 	- performs a Facebook roundtrip
	 *
	 * @return bool, true if auth token is still valid
	 */
	public function isValidAuthToken()
	{
		if (!$this->getFbUserInfo())
		{
			$this->facebook->setSession(null);
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Checks for a currrently logged in user through facebook api
	 *
	 * @return mixed, fb userid if logged in, false otherwise
	 */
	public function getLoggedInFbUserId()
	{
		if (!$this->isValidUser())
		{
			return false;
		}

		return $this->fb_userid;
	}


	/**
	 * Grabs logged in user info from faceboook if user is logged in
	 *
	 * @param bool, forces a roundtrip to the facebook server, ie. dont use cached info
	 *
	 * @return array, fb userinfo array if logged in, false otherwise
	 */
	public function getFbUserInfo($force_reload = false)
	{
		// check for cached versions of this, and return it if so
		if (!empty($this->fb_userinfo) AND !$force_reload)
		{
			return $this->fb_userinfo;
		}

		// make sure we have a fb user and fb session, otherwise we cant return any data
		if (!$this->isValidUser() OR empty($this->fb_session['access_token']))
		{
			return false;
		}

		// attempt to grab userinfo from fb graph api, using FQL
		try
		{
			$response = $this->facebook->api(array(
				'access_token' => $this->fb_session['access_token'],
				'method' => 'fql.query',
				'query' => 'SELECT ' . implode(',', $this->fql_fields) . ' FROM user WHERE uid='.$this->fb_userid,
			));

			if (is_array($response) AND !empty($response))
			{
				$this->fb_userinfo = $response[0];
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		// now return the user info if we got any
		return $this->fb_userinfo;
	}

	/**
	 * Grabs logged in user connections (ie likes, activities, interests, etc)
	 *
	 * @param bool, forces a roundtrip to the facebook server, ie. dont use cached info
	 *
	 * @return array, fb userconnectioninfo array if logged in, false otherwise
	 */
	public function getFbUserConnectionInfo($force_reload = false)
	{
		// check for cached versions of this, and return it if so
		if (!empty($this->fb_userconnectioninfo) AND !$force_reload)
		{
			return $this->fb_userconnectioninfo;
		}

		// make sure we have a fb user and fb session, otherwise we cant return any data
		if (!$this->isValidUser() OR empty($this->fb_session['access_token']))
		{
			return false;
		}

		// attempt to grab userinfo from fb graph api, using FQL
		try
		{
			$response = $this->facebook->api(
				'/me?fields='.implode(',', $this->connection_fields)
			);

			if (is_array($response) AND !empty($response))
			{
				$this->fb_userconnectioninfo = $response[0];
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		// now return the user info if we got any
		return $this->fb_userconnectioninfo;
	}


	/**
	 * Checks if current facebook user is associated with a vb user, and returns vb userid if so
	 *
	 * @param int, facebook userid to check in vb database, if not there well user current
	 * 		logged in user
	 * @return mixed, vb userid if one is associated, false if not
	 */
	public function getVbUseridFromFbUserid($fb_userid = false)
	{
		// if no fb userid was passed in, attempt to use current logged in fb user
		// but if no current fb user, there cannot be an associated vb account, so return false
		if (empty($fb_userid) AND !$fb_userid = $this->getLoggedInFbUserId())
		{
			return false;
		}

		// check if vB userid is already cached in this object
		if ($fb_userid == $this->getLoggedInFbUserId() AND !empty($this->vb_userid))
		{
			return $this->vb_userid;
		}

		// otherwise we have to grab the vb userid from the database
		$user = $this->registry->db->query_first_slave("
			SELECT userid
			FROM `" . TABLE_PREFIX . "user`
			WHERE fbuserid = '$fb_userid' LIMIT 1
		");
		$this->vb_userid = (!empty($user['userid']) ? $user['userid'] : false);

		return $this->vb_userid;
	}

	/**
	 * Checks if current facebook user is associated with a vb user, and returns vb userid if so
	 *
	 * @param int, facebook userid to check in vb database, if not there well user current
	 * 		logged in user
	 * @return mixed, vb userid if one is associated, false if not
	 */
	public function publishFeed($message, $name, $link, $description, $picture = null)
	{
		global $vbulletin;

		$params = array(
			'message'     => $message,
			'name'        => $name,
			'link'        => $link,
			'description' => $description,
		);

		// add picture link if we get one
		if (!empty($picture))
		{
			$params['picture'] = $vbulletin->options['facebookfeedimageurl'];
		}

		// if no link was passed in, try using the admin option
		else if (!empty($vbulletin->options['facebookfeedimageurl']))
		{
			$params['picture'] = $vbulletin->options['facebookfeedimageurl'];
		}

		// attempt to publish to user's wall
		try
		{
			$response = $this->facebook->api(
				'/me/feed',
				'POST',
				$params
			);
			return !empty($response);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Kills the current Facebook session
	 */
	public function doLogoutFbUser()
	{
		// set the current session to null
		$this->facebook->setSession(null);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42666 $
|| ####################################################################
\*======================================================================*/
