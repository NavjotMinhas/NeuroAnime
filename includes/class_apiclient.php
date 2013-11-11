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

class vB_APIClient
{
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	protected $baseurl;
	protected $sessionhash;
	protected $securitytoken;

	protected $getparams;
	protected $postparams;

	protected $api_sig;
	protected $authsign;

	protected $result;

	protected $vurl;

	public function __construct(&$registry, $sessionhash = null, $securitytoken = null)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_APIClient::Registry object is not an object", E_USER_ERROR);
		}

		$this->baseurl = $this->registry->options['bburl'] . '/api.php';
		$this->sessionhash = $sessionhash;
		$this->securitytoken = $securitytoken;

		require_once(DIR . '/includes/class_vurl.php');

		$this->vurl = new vB_vURL($this->registry);
		$this->vurl->set_option(VURL_HEADER, true);
		$this->vurl->set_option(VURL_RETURNTRANSFER, true);
		$this->vurl->set_option(VURL_CLOSECONNECTION, true);
	}

	public function setSessionhash($sessionhash)
	{
		$this->sessionhash = $sessionhash;
	}

	public function setSecuritytoken($securitytoken)
	{
		$this->securitytoken = $securitytoken;
	}

	public function call($methodname, $getparams = array(), $postparams = array(), $apiversion = 1, $rawdata = false, $debug = false, $showall = false)
	{
		$methodname = trim($methodname);
		$apiparams['api_m'] = $methodname;
		$apiparams['api_v'] = $apiversion;
		if ($this->sessionhash)
		{
			$apiparams['api_s'] = $this->sessionhash;
			$apiparams['api_sig'] = $this->api_sig = $this->sign($methodname, $getparams);
		}
		if ($debug)
		{
			$apiparams['debug'] = 1;
		}
		if ($showall)
		{
			$apiparams['showall'] = 1;
		}
		if ($postparams)
		{
			$this->vurl->set_option(VURL_POST, 1);
			$this->vurl->set_option(VURL_POSTFIELDS, http_build_query($postparams, '', '&'));
		}
		
		$this->vurl->set_option(VURL_URL, $this->baseurl . '?' . http_build_query($apiparams, '', '&') . '&' . http_build_query($getparams, '', '&'));
		if ($results = $this->vurl->exec())
		{
			if ($results['headers']['http-response']['statuscode'] == 200)
			{
				$headers = $results['headers'];
				$body = $results['body'];

				if ($this->sessionhash)
				{
					// Verify the data from server
					$authsign = $this->authsign = $headers['authorization'];
					if ($authsign)
					{
						$signtoverify = md5($body . $this->sessionhash . $this->securitytoken);
						if ($authsign != $signtoverify)
						{
							throw new ExceptionInvalidSign('Return Signature Verification Failed');
						}
					}
				}

				if ($rawdata)
				{
					return $body;
				}
				else
				{
					return json_decode($body, true);
				}
			}
			else
			{
				throw new ExceptionConnection('Request Failed');
			}
		}
		else
		{
			throw new ExceptionConnection('Request Failed');
		}

	}

	public function getApi_sig()
	{
		return $this->api_sig;
	}

	public function getAuthsign()
	{
		return $this->authsign;
	}

	private function sign($methodname, &$getparams)
	{
		$params = $getparams;
		$params['api_m'] = $methodname;
		ksort($params);
		$signstr = http_build_query($params, '', '&');
		return md5($signstr . $this->sessionhash . $this->securitytoken);
	}

}

class ExceptionInvalidSign extends Exception {

}

class ExceptionConnection extends Exception {

}
