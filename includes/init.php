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

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo 'VB_AREA and THIS_SCRIPT must be defined to continue';
	exit;
}

if (isset($_REQUEST['GLOBALS']) OR isset($_FILES['GLOBALS']))
{
	echo 'Request tainting attempted.';
	exit;
}

@ini_set('pcre.backtrack_limit', -1);

// Force PHP 5.3.0+ to take time zone information from OS
if (version_compare(phpversion(), '5.3.0', '>='))
{
	@date_default_timezone_set(date_default_timezone_get());
}

// start the page generation timer
define('TIMESTART', microtime(true));

// set the current unix timestamp
define('TIMENOW', time());

// Define safe_mode
define('SAFEMODE', (@ini_get('safe_mode') == 1 OR strtolower(@ini_get('safe_mode')) == 'on') ? true : false);

// define current directory
if (!defined('CWD'))
{
	define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
}

// #############################################################################
// fetch the core includes

require_once(CWD . '/includes/class_core.php');
set_error_handler('vb_error_handler');

// initialize the data registry
$vbulletin = new vB_Registry();

// parse the configuration ini file
$vbulletin->fetch_config();

// Add AdSense if present
$vbulletin->adsense_pub_id = '';
$vbulletin->adsense_host_id = '';

if (CWD == '.')
{
	// getcwd() failed and so we need to be told the full forum path in config.php
	if (!empty($vbulletin->config['Misc']['forumpath']))
	{
		define('DIR', $vbulletin->config['Misc']['forumpath']);
	}
	else
	{
		trigger_error('<strong>Configuration</strong>: You must insert a value for <strong>forumpath</strong> in config.php', E_USER_ERROR);
	}
}
else
{
	define('DIR', CWD);
}

if (!empty($vbulletin->config['Misc']['datastorepath']))
{
		define('DATASTORE', $vbulletin->config['Misc']['datastorepath']);
}
else
{
		define('DATASTORE', DIR . '/includes/datastore');
}

if ($vbulletin->debug)
{
	restore_error_handler();
}

// #############################################################################
// load database class
switch (strtolower($vbulletin->config['Database']['dbtype']))
{
	// load standard MySQL class
	case 'mysql':
	case '':
	{
		if ($vbulletin->debug AND ($vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
		{
			// load 'explain' database class
			require_once(DIR . '/includes/class_database_explain.php');
			$db = new vB_Database_Explain($vbulletin);
		}
		else
		{
			$db = new vB_Database($vbulletin);
		}
		break;
	}

	case 'mysql_slave':
	{
		require_once(DIR . '/includes/class_database_slave.php');
		$db = new vB_Database_Slave($vbulletin);
		break;
	}

	// load MySQLi class
	case 'mysqli':
	{
		if ($vbulletin->debug AND ($vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
		{
			// load 'explain' database class
			require_once(DIR . '/includes/class_database_explain.php');
			$db = new vB_Database_MySQLi_Explain($vbulletin);
		}
		else
		{
			$db = new vB_Database_MySQLi($vbulletin);
		}
		break;
	}

	case 'mysqli_slave':
	{
		require_once(DIR . '/includes/class_database_slave.php');
		$db = new vB_Database_Slave_MySQLi($vbulletin);
		break;
	}

	// load extended, non MySQL class
	default:
	{
	// this is not implemented fully yet
	//	$db = 'vB_Database_' . $vbulletin->config['Database']['dbtype'];
	//	$db = new $db($vbulletin);
		die('Fatal error: Database class not found');
	}
}


// get core functions
if (!empty($db->explain))
{
	$db->timer_start('Including Functions.php');
	require_once(DIR . '/includes/functions.php');
	$db->timer_stop(false);
}
else
{
	require_once(DIR . '/includes/functions.php');
}

// make database connection
$db->connect(
	$vbulletin->config['Database']['dbname'],
	$vbulletin->config['MasterServer']['servername'],
	$vbulletin->config['MasterServer']['port'],
	$vbulletin->config['MasterServer']['username'],
	$vbulletin->config['MasterServer']['password'],
	$vbulletin->config['MasterServer']['usepconnect'],
	$vbulletin->config['SlaveServer']['servername'],
	$vbulletin->config['SlaveServer']['port'],
	$vbulletin->config['SlaveServer']['username'],
	$vbulletin->config['SlaveServer']['password'],
	$vbulletin->config['SlaveServer']['usepconnect'],
	$vbulletin->config['Mysqli']['ini_file'],
	(isset($vbulletin->config['Mysqli']['charset']) ? $vbulletin->config['Mysqli']['charset'] : '')
);

//30443 Right now the product doesn't work in strict mode at all.  Its silly to make people have to edit their
//config to handle what appears to be a very common case (though the mysql docs say that no mode is the default)
//we no longer use the force_sql_mode parameter, though if the app is fixed to handle strict mode then we
//may wish to change the default again, in which case we should honor the force_sql_mode option.
//added the force parameter

if (
	empty($vbulletin->config['Database']['no_force_sql_mode'])
	OR
	!empty($vbulletin->config['Database']['force_sql_mode'])
)
{
	$db->force_sql_mode('');
}

if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_db'))
{
	vbulletin_demo_init_db();
}

// make $db a member of $vbulletin
$vbulletin->db =& $db;

// #############################################################################
// fetch options and other data from the datastore
if (!empty($db->explain))
{
	$db->timer_start('Datastore Setup');
}

$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';

if ($datastore_class != 'vB_Datastore')
{
	require_once(DIR . '/includes/class_datastore.php');
}
$vbulletin->datastore = new $datastore_class($vbulletin, $db);
if (!$vbulletin->datastore->fetch($specialtemplates))
{
	switch(VB_AREA)
	{
		case 'AdminCP':
		case 'Archive':
			exec_header_redirect('../install/install.php');
			break;
		case 'Forum':
		default:
			exec_header_redirect('install/install.php');
	}
}

if ($vbulletin->bf_ugp === null)
{
	echo '<div>vBulletin datastore error caused by one or more of the following:
		<ol>
			' . (function_exists('mmcache_get') ? '<li>Turck MMCache has been detected on your server, first try disabling Turck MMCache or replacing it with eAccelerator</li>' : '') . '
			<li>You may have uploaded vBulletin files without also running the vBulletin upgrade script. If you have not run the upgrade script, do so now.</li>
			<li>The datastore cache may have been corrupted. Run <em>Rebuild Bitfields</em> from <em>tools.php</em>, which you can upload from the <em>do_not_upload</em> folder of the vBulletin package.</li>
		</ol>
	</div>';

	trigger_error('vBulletin datastore cache incomplete or corrupt', E_USER_ERROR);
}

if (defined('VB_PRODUCT') AND (!isset($vbulletin->products[VB_PRODUCT]) OR !($vbulletin->products[VB_PRODUCT])))
{
	exec_header_redirect(fetch_seo_url('forumhome|bburl', array()), 302);
}

if (!empty($db->explain))
{
	$db->timer_stop(false);
}

if ($vbulletin->options['cookietimeout'] < 60)
{
	// values less than 60 will probably break things, so prevent that
	$vbulletin->options['cookietimeout'] = 60;
}

// #############################################################################
/**
* If shutdown functions are allowed, register exec_shut_down to be run on exit.
* Disable shutdown function for IIS CGI with Gzip enabled since it just doesn't work, sometimes, unless we kill the content-length header
* Also disable for PHP4 due to the echo() timeout issue
*/
define('SAPI_NAME', php_sapi_name());
/*
if (!defined('NOSHUTDOWNFUNC'))
{
	if ((SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi') AND $vbulletin->options['gzipoutput'] AND strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false)
	{
		define('NOSHUTDOWNFUNC', true);
	}
	else
	{
		vB_Shutdown::add('exec_shut_down');
	}
}
*/
define('NOSHUTDOWNFUNC', true);

// fetch url of referring page after we have access to vboptions['forumhome']
$vbulletin->url = $vbulletin->input->fetch_url();
define('REFERRER_PASSTHRU', $vbulletin->url);

// #############################################################################
// demo mode stuff
if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_page'))
{
	vbulletin_demo_init_page();
}

// #############################################################################
// setup the hooks & plugins system
if ($vbulletin->options['enablehooks'] OR defined('FORCE_HOOKS'))
{
	require_once(DIR . '/includes/class_hook.php');
	if ($vbulletin->options['enablehooks'] AND !defined('DISABLE_HOOKS'))
	{
		if (!empty($vbulletin->pluginlistadmin) AND is_array($vbulletin->pluginlistadmin))
		{
			$vbulletin->pluginlist = array_merge($vbulletin->pluginlist, $vbulletin->pluginlistadmin);
			unset($vbulletin->pluginlistadmin);
		}
		vBulletinHook::set_pluginlist($vbulletin->pluginlist);
	}
}
else
{
	// make a null class for optimization
	/**
	* @ignore
	*/
	class vBulletinHook {
		public static function fetch_hook() { return false; }
		public static function fetch_hookusage() { return array(); }
	}
	$vbulletin->pluginlist = '';
}
$template_hook = array();

// $new_datastore_fetch does not require single quotes
$new_datastore_fetch = $datastore_fetch = array();

($hook = vBulletinHook::fetch_hook('init_startup')) ? eval($hook) : false;

if (!empty($datastore_fetch))
{
	// Remove the single quotes that $datastore_fetch required
	foreach ($datastore_fetch AS $value)
	{
		$new_datastore_fetch[] = substr($value, 1, -1);
	}
}

$vbulletin->datastore->fetch($new_datastore_fetch);
unset($datastore_fetch, $new_datastore_fetch);

// #############################################################################
// Parse the friendly uri for the current request
if (defined('FRIENDLY_URL_LINK'))
{
	require_once(CWD . '/includes/class_friendly_url.php');

	$friendly = vB_Friendly_Url::fetchLibrary($vbulletin, FRIENDLY_URL_LINK . '|nosession');

	if ($vbulletin->input->friendly_uri = $friendly->get_uri())
	{
		// don't resolve the wolpath
		define('SKIP_WOLPATH', 1);
	}
}

// #############################################################################
// do a callback to modify any variables that might need modifying based on HTTP input
// eg: doing a conditional redirect based on a $goto value or $vbulletin->noheader must be set
if (function_exists('exec_postvar_call_back'))
{
	exec_postvar_call_back();
}

// #############################################################################
// initialize $show variable - used for template conditionals
$show = array();

// #############################################################################
// Clean Cookie Vars
$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_collapse'              => TYPE_STR,
	COOKIE_PREFIX . 'referrerid'      => TYPE_UINT,
	COOKIE_PREFIX . 'userid'          => TYPE_UINT,
	COOKIE_PREFIX . 'password'        => TYPE_STR,
	COOKIE_PREFIX . 'lastvisit'       => TYPE_UINT,
	COOKIE_PREFIX . 'lastactivity'    => TYPE_UINT,
	COOKIE_PREFIX . 'threadedmode'    => TYPE_NOHTML,
	COOKIE_PREFIX . 'sessionhash'     => TYPE_NOHTML,
	COOKIE_PREFIX . 'userstyleid'     => TYPE_UINT,
	COOKIE_PREFIX . 'languageid'      => TYPE_UINT,
	COOKIE_PREFIX . 'skipmobilestyle' => TYPE_BOOL,
));


// #############################################################################
// VB API Request Signature Verification
if (defined('VB_API') AND VB_API === true)
{
	// API disabled
	if (!$vbulletin->options['enableapi'] OR !$vbulletin->options['apikey'])
	{
		print_apierror('api_disabled', 'API is disabled');
	}

	global $VB_API_PARAMS_TO_VERIFY, $VB_API_REQUESTS;

	$vbulletin->input->clean_array_gpc('r', array(
		'debug'         => TYPE_BOOL,
		'showall'       => TYPE_BOOL,
	));

	if ($VB_API_REQUESTS['api_c'])
	{
		// Get client information from api_c. api_c has been intvaled in api.php
		$client = $db->query_first("SELECT *
			FROM " . TABLE_PREFIX . "apiclient
			WHERE apiclientid = $VB_API_REQUESTS[api_c]");

		if (!$client)
		{
			print_apierror('invalid_clientid', 'Invalid Client ID');
		}

		// An accesstoken is passed but invalid
		if ($VB_API_REQUESTS['api_s'] AND $VB_API_REQUESTS['api_s'] != $client['apiaccesstoken'])
		{
			print_apierror('invalid_accesstoken', 'Invalid Access Token');
		}

		$signtoverify = md5(http_build_query($VB_API_PARAMS_TO_VERIFY, '', '&') . $VB_API_REQUESTS['api_s'] . $client['apiclientid'] . $client['secret'] . $vbulletin->options['apikey']);
		$vbulletin->input->clean_array_gpc('r', array(
			'debug' => TYPE_BOOL,
		));
		if ($VB_API_REQUESTS['api_sig'] !== $signtoverify AND !($vbulletin->debug AND $vbulletin->GPC['debug']))
		{
			//echo ' Should be: ' . $signtoverify . ' md5("' . http_build_query($VB_API_PARAMS_TO_VERIFY, '', '&') . $VB_API_REQUESTS['api_s'] . $client['apiclientid'] . $client['secret'] . '")';
			print_apierror('invalid_api_signature', 'Invalid API Signature');
		}
		else
		{
			$vbulletin->apiclient = $client;
		}

		if ($vbulletin->options['enableapilog'])
		{
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "apilog (apiclientid, method, paramget, parampost, ipaddress, dateline)
				VALUES (
					$VB_API_REQUESTS[api_c],
					'" . $db->escape_string($VB_API_REQUESTS['api_m']) . "',
					'" . $db->escape_string(serialize($_GET)) . "',
					'" . (($vbulletin->options['apilogpostparam'])?$db->escape_string(serialize($_POST)):'') . "',
					'" . $db->escape_string(IPADDRESS) . "',
					'" . TIMENOW . "'
				)
			");
		}

		// TODO: Disable human verification in this release. enabled it when release API to public
		$vbulletin->options['hvcheck'] = 0;
		$vbulletin->options['vbforum_url'] = '';
		$vbulletin->options['vbcms_url'] = '';
		$vbulletin->options['vbblog_url'] = '';
	}
	// api_init is a special method that is able to generate new client info.
	elseif ($VB_API_REQUESTS['api_m'] != 'api_init' AND !($vbulletin->debug AND $vbulletin->GPC['debug']))
	{
		print_apierror('missing_api_signature', 'Missing API Signature');
	}
}

// #############################################################################
// Setup session
if (!empty($db->explain))
{
	$db->timer_start('Session Handling');
}

$vbulletin->input->clean_array_gpc('r', array(
	's'       => TYPE_NOHTML,
	'styleid' => TYPE_INT,
	'langid'  => TYPE_INT,
));

// conditional used in templates to hide things from search engines.
$show['search_engine'] = ($vbulletin->superglobal_size['_COOKIE'] == 0 AND preg_match("#(google|bingbot|yahoo! slurp|facebookexternalhit)#si", $_SERVER['HTTP_USER_AGENT']));

// handle session input
if (!VB_API)
{
	$sessionhash = (!empty($vbulletin->GPC['s']) ? $vbulletin->GPC['s'] : $vbulletin->GPC[COOKIE_PREFIX . 'sessionhash']); // override cookie
}
else
{
	$sessionhash = '';
}

// Set up user's chosen language
if ($vbulletin->GPC['langid'] AND !empty($vbulletin->languagecache["{$vbulletin->GPC['langid']}"]['userselect']))
{
	$languageid =& $vbulletin->GPC['langid'];
	vbsetcookie('languageid', $languageid);
}
else if ($vbulletin->GPC[COOKIE_PREFIX . 'languageid'] AND !empty($vbulletin->languagecache[$vbulletin->GPC[COOKIE_PREFIX . 'languageid']]['userselect']))
{
	$languageid = $vbulletin->GPC[COOKIE_PREFIX . 'languageid'];
}
else
{
	$languageid = 0;
}

// Test mobile browser
$mobile_browser = false;
$mobile_browser_advanced = false;
if ($vbulletin->options['mobilestyleid_advanced'] OR $vbulletin->options['mobilestyleid_basic'])
{
	if (stripos($_SERVER['HTTP_USER_AGENT'], 'windows') === false)
	{
		if (
			preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android)/i', strtolower($_SERVER['HTTP_USER_AGENT']))
			OR
			stripos($_SERVER['HTTP_ACCEPT'],'application/vnd.wap.xhtml+xml') !== false
			OR
			((isset($_SERVER['HTTP_X_WAP_PROFILE']) OR isset($_SERVER['HTTP_PROFILE'])))
			OR
			stripos($_SERVER['ALL_HTTP'],'OperaMini') !== false
		)
		{
			$mobile_browser = true;
		}
		// This array is big and may be bigger later on. So we move it to a second if.
		elseif (in_array(
					strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4)),
					array(
					'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
					'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
					'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
					'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
					'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
					'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
					'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
					'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
					'wapr','webc','winw','winw','xda ','xda-')
				)
			)
		{
			$mobile_browser = true;
		}
	}

	if (
		$mobile_browser
		AND
		preg_match('/(ipad|ipod|iphone|blackberry|android|pre\/|palm os|palm|hiptop|avantgo|plucker|xiino|blazer|elaine)/i', strtolower($_SERVER['HTTP_USER_AGENT']))
	)
	{
		$mobile_browser_advanced = true;
	}
}

// Set up user's chosen style
if ($vbulletin->GPC['styleid'])
{
	$styleid =& $vbulletin->GPC['styleid'];
	vbsetcookie('userstyleid', $styleid);
	if ($styleid == -1)
	{
		vbsetcookie('skipmobilestyle', 1);
		$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'] = 1;
	}
	elseif ($styleid == $vbulletin->options['mobilestyleid_advanced'] OR $styleid == $vbulletin->options['mobilestyleid_basic'])
	{
		vbsetcookie('skipmobilestyle', 0);
		$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'] = 0;
	}
}
elseif ($mobile_browser_advanced && $vbulletin->options['mobilestyleid_advanced'] && !$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'])
{
	$styleid = $vbulletin->options['mobilestyleid_advanced'];
}
elseif ($mobile_browser && $vbulletin->options['mobilestyleid_basic'] && !$vbulletin->GPC[COOKIE_PREFIX . 'skipmobilestyle'])
{
	$styleid = $vbulletin->options['mobilestyleid_basic'];
}
elseif ($vbulletin->GPC[COOKIE_PREFIX . 'userstyleid'])
{
	$styleid = $vbulletin->GPC[COOKIE_PREFIX . 'userstyleid'];
}
else
{
	$styleid = 0;
}
$vbulletin->styleid = $styleid;
$vbulletin->mobile_browser = $mobile_browser;
// build the session and setup the environment
$vbulletin->session = new vB_Session($vbulletin, $sessionhash, $vbulletin->GPC[COOKIE_PREFIX . 'userid'], $vbulletin->GPC[COOKIE_PREFIX . 'password'], $styleid, $languageid);

// Hide sessionid in url if we are a search engine or if we have a cookie
$vbulletin->session->set_session_visibility(($show['search_engine'] OR $vbulletin->superglobal_size['_COOKIE'] > 0) AND !VB_API);
$vbulletin->userinfo =& $vbulletin->session->fetch_userinfo();
$vbulletin->session->do_lastvisit_update($vbulletin->GPC[COOKIE_PREFIX . 'lastvisit'], $vbulletin->GPC[COOKIE_PREFIX . 'lastactivity']);

// put the sessionhash into contact-us links automatically if required (issueid 21522)
if ($vbulletin->session->visible AND $vbulletin->options['contactuslink'] != '' AND substr(strtolower($vbulletin->options['contactuslink']), 0, 7) != 'mailto:')
{
	if (strpos($vbulletin->options['contactuslink'], '?') !== false)
	{
		$vbulletin->options['contactuslink'] = str_replace('?', '?' . $vbulletin->session->vars['sessionurl'], $vbulletin->options['contactuslink']);
	}
	else
	{
		$vbulletin->options['contactuslink'] .= $vbulletin->session->vars['sessionurl_q'];
	}
}

// Because of Signature Verification, VB API won't need to verify securitytoken
// CSRF Protection for POST requests
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' AND !VB_API)
{

	if (empty($_POST) AND isset($_SERVER['CONTENT_LENGTH']) AND $_SERVER['CONTENT_LENGTH'] > 0)
	{
		die('The file(s) uploaded were too large to process.');
	}

	if ($vbulletin->userinfo['userid'] > 0 AND defined('CSRF_PROTECTION') AND CSRF_PROTECTION === true)
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'securitytoken' => TYPE_STR,
		));

		if (!in_array($_POST['do'], $vbulletin->csrf_skip_list))
		{
			if (!verify_security_token($vbulletin->GPC['securitytoken'], $vbulletin->userinfo['securitytoken_raw']))
			{
				switch ($vbulletin->GPC['securitytoken'])
				{
					case '':
						define('CSRF_ERROR', 'missing');
						break;
					case 'guest':
						define('CSRF_ERROR', 'guest');
						break;
					case 'timeout':
						define('CSRF_ERROR', 'timeout');
						break;
					default:
						define('CSRF_ERROR', 'invalid');
				}
			}
		}
	}
	else if (!defined('CSRF_PROTECTION') AND !defined('SKIP_REFERRER_CHECK'))
	{
		if (VB_HTTP_HOST AND $_SERVER['HTTP_REFERER'])
		{
			//The preg_replace is redundant as VB_HTTP_HOST does not contain the port. It is from VB_URL which strips the port.
			$host_parts = @parse_url($_SERVER['HTTP_HOST']);
			$http_host_port = intval($host_parts['port']);
			$http_host = VB_HTTP_HOST . ((!empty($http_host_port) AND $http_host_port != '80') ? ":$http_host_port" : '');

			$referrer_parts = @parse_url($_SERVER['HTTP_REFERER']);
			$ref_port = intval($referrer_parts['port']);
			$ref_host = $referrer_parts['host'] . ((!empty($ref_port) AND $ref_port != '80') ? ":$ref_port" : '');

			$allowed = preg_split('#\s+#', $vbulletin->options['allowedreferrers'], -1, PREG_SPLIT_NO_EMPTY);
			$allowed[] = preg_replace('#^www\.#i', '', $http_host);
			$allowed[] = '.paypal.com';

			$pass_ref_check = false;
			foreach ($allowed AS $host)
			{
				if (preg_match('#' . preg_quote($host, '#') . '$#siU', $ref_host))
				{
					$pass_ref_check = true;
					break;
				}
			}
			unset($allowed);

			if ($pass_ref_check == false)
			{
				die('In order to accept POST request originating from this domain, the admin must add this domain to the whitelist.');
			}
		}
	}
}


// Google Web Accelerator can display sensitive data ignoring any headers regarding caching
// it's a good thing for guests but not for anyone else
if ($vbulletin->userinfo['userid'] > 0 AND isset($_SERVER['HTTP_X_MOZ']) AND strpos($_SERVER['HTTP_X_MOZ'], 'prefetch') !== false)
{
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 403 Forbidden');
	}
	else
	{
		header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
	}
	die('Prefetching is not allowed due to the various privacy issues that arise.');
}

// use the session-specified style if there is one
if ($vbulletin->session->vars['styleid'] != 0)
{
	$vbulletin->userinfo['styleid'] = $vbulletin->session->vars['styleid'];
}

if (!empty($db->explain))
{
	$db->timer_stop(false);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44868 $
|| ####################################################################
\*======================================================================*/
?>
