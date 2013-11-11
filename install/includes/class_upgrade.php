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
* Fetch upgrade lib based on PHP environment
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_Upgrade
{
	/**
	* Singleton emulation: Select library
	*
	*	@var	vB_Registry object
	*	@var	string	Override library detection routine
	* @var	boolean	Upgrade/true, Install/false
	*/
	public static function &fetch_library(&$registry, $phrases, $library = '', $upgrade = true, $script = null, $forcenew = false)
	{
		global $show;
		static $instance;

		if (!$instance OR $forcenew)
		{
			if ($library)
			{
					$chosenlib = $library;
			}
			else
			{
					$chosenlib = defined('STDIN') /* OR $_SERVER['argc'] */ ? 'cli' : 'ajax';
			}

			$selectclass = 'vB_Upgrade_' . $chosenlib;
			$chosenlib = strtolower($chosenlib);
			require_once(DIR . '/install/includes/class_upgrade_' . $chosenlib . '.php');
			$instance = new $selectclass($registry, $phrases, $upgrade ? 'upgrade' : 'install', $script);
		}
		return $instance;
	}

	public static function fetch_language()
	{
		static $phrases;

		if (!$phrases)
		{
			require_once(CWD . '/includes/class_xml.php');
			$languagecode = defined('UPGRADE_LANGUAGE') ? UPGRADE_LANGUAGE : 'en';
			$xmlobj = new vB_XML_Parser(false, CWD . '/install/upgrade_language_' . $languagecode . '.xml');
			$xml = $xmlobj->parse(defined('UPGRADE_ENCODING') ? UPGRADE_ENCODING : 'ISO-8859-1');

			foreach ($xml['group'] AS $value)
			{
				if (is_array($value['group']))
				{	// step phrases
					foreach($value['group'] AS $value2)
					{
						if (!$value2['phrase'][0])
						{
							$value2['phrase'] = array($value2['phrase']);
						}
						foreach($value2['phrase'] AS $value3)
						{
							$step = $value3['step'] ? "_$value3[step]" : '';
							$phrases[$value['name']][$value2['name']][$value3['name'] . $step] = $value3['value'];
						}
					}
				}
				else
				{
					if (!$value['phrase'][0])
					{
						$value['phrase'] = array($value['phrase']);
					}
					foreach ($value['phrase'] AS $value2)
					{
						$step = $value2['step'] ? "_$value2[step]" : '';
						$phrases[$value['name']][$value2['name'] . $step] = $value2['value'];
					}
				}
			}
			$GLOBALS['vbphrase'] =& $phrases['vbphrase'];
		}
		return $phrases;
	}
}

abstract class vB_Upgrade_Abstract
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* The object that will be used to execute queries
	*
	* @var	vB_Database
	*/
	protected $db = null;

	/**
	* Array of Steps Objects
	*
	*	@var object
	*/
	public $steps = array();

	/**
	* Upgrade start point
	*
	*	@var array
	*/
	protected $scriptinfo = array(
		'version' => null,
		'startat' => 0,
		'perpage' => 20,
		'step'    => 1,
	);

	/**
	* Startup warning messages
	*
	*	@var array
	*/
	protected $startup_warnings = array();

	/**
	* XML file versions
	*
	*	@var array
	*/
	protected $xml_versions = array(
		'language'  => null,
		'style'     => null,
		'adminhelp' => null,
		'settings'  => null
	);

	/**
	* Array of vBulletin versions supported for upgrade
	*
	* @var array
	*/
	protected $versions = array(
		'35*'    => '354',	// allow any version 3.5.x version that is 3.5.4 and greater..
		'360b1'  => '3.6.0 Beta 1',
		'360b2'  => '3.6.0 Beta 2',
		'360b3'  => '3.6.0 Beta 3',
		'360b4'  => '3.6.0 Beta 4',
		'360rc1' => '3.6.0 Release Candidate 1',
		'360rc2' => '3.6.0 Release Candidate 2',
		'360rc3' => '3.6.0 Release Candidate 3',
		'360'    => '3.6.0',
		'361'    => '3.6.1',
		'362'    => '3.6.2',
		'363'    => '3.6.3',
		'364'    => '3.6.4',
		'365'    => '3.6.5',
		'366'    => '3.6.6',
		'367'    => '3.6.7',
		'368'    => '3.6.8',
		'36*'    => '',
		'370b2'  => '3.7.0 Beta 2',
		'370b3'  => '3.7.0 Beta 3',
		'370b4'  => '3.7.0 Beta 4',
		'370b5'  => '3.7.0 Beta 5',
		'370b6'  => '3.7.0 Beta 6',
		'370rc1' => '3.7.0 Release Candidate 1',
		'370rc2' => '3.7.0 Release Candidate 2',
		'370rc3' => '3.7.0 Release Candidate 3',
		'370rc4' => '3.7.0 Release Candidate 4',
		'370'    => '3.7.0',
		'371'    => '3.7.1',
		'37*'    => '',
		'380a2'  => '3.8.0 Alpha 2',
		'380b1'  => '3.8.0 Beta 1',
		'380b2'  => '3.8.0 Beta 2',
		'380b3'  => '3.8.0 Beta 3',
		'380b4'  => '3.8.0 Beta 4',
		'380rc1' => '3.8.0 Release Candidate 1',
		'380rc2' => '3.8.0 Release Candidate 2',
		'380'    => '3.8.0',
		'38*'    => '',
		'400a1'  => '4.0.0 Alpha 1',
		'400a2'  => '4.0.0 Alpha 2',
		'400a3'  => '4.0.0 Alpha 3',
		'400a4'  => '4.0.0 Alpha 4',
		'400a5'  => '4.0.0 Alpha 5',
		'400a6'  => '4.0.0 Alpha 6',
		'400b1'  => '4.0.0 Beta 1',
		'400b2'  => '4.0.0 Beta 2',
		'400b3'  => '4.0.0 Beta 3',
		'400b4'  => '4.0.0 Beta 4',
		'400b5'  => '4.0.0 Beta 5',
		'400rc1' => '4.0.0 Release Candidate 1',
		'400rc2' => '4.0.0 Release Candidate 2',
		'400rc3' => '4.0.0 Release Candidate 3',
		'400rc4' => '4.0.0 Release Candidate 4',
		'400rc5' => '4.0.0 Release Candidate 5',
		'400'    => '4.0.0',
		'401'    => '4.0.1',
		'402'    => '4.0.2',
		'403'    => '4.0.3',
		'404'    => '4.0.4',
		'405'    => '4.0.5',
		'406'    => '4.0.6',
		'407'    => '4.0.7',
		'408'    => '4.0.8',
		'410b1'  => '4.1.0 Beta 1',
		'410'    => '4.1.0',
		'411a1'  => '4.1.1 Alpha 1',
		'411b1'  => '4.1.1 Beta 1',
		'411'    => '4.1.1',
		'412b1'  => '4.1.2 Beta 1',
		'412'    => '4.1.2',
		'413b1'  => '4.1.3 Beta 1',
		'413'    => '4.1.3',
		'414b1'  => '4.1.4 Beta 1',
		'414'    => '4.1.4',
		'415b1'  => '4.1.5 Beta 1',
		'415'    => '4.1.5',
	);

	/**
	* Array of non vB version scripts. 'final' must be at the end
	*
	* @var array
	*/
	protected $endscripts = array(
		'vbblog',
		'vbcms',
		'skimlinks',
		'final',
	);

	/**
	* Array of products installed by suite
	*
	* @var array
	*/
	protected $products = array(
		'vbblog',
		'vbcms',
	);

	/**
	* Execution type, either 'browser' or 'cli'
	*
	* @var string
	*/
	protected $exectype = null;

	/**
	* Phrases
	*
	* @var	array
	*/
	protected $phrase = array();

	/**
	* Startup Errors
	*
	* @var	array
	*/
	protected $startup_errors = array();

	/**
	* Setup type, new install or upgrade?
	*
	* @var	string
	*/
	protected $setuptype = 'upgrade';

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	* @var	string	Setup type - 'install' or 'upgrade'
	*/
	public function __construct(&$registry, $phrases, $setuptype = 'upgrade', $script = null)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
			$this->db =& $this->registry->db;
		}
		else
		{
			trigger_error('vB_Upgrade: $this->registry is not an object.', E_USER_ERROR);
		}

		$this->setuptype = $setuptype;
		$this->phrase = $phrases;

		require_once(DIR . '/includes/adminfunctions.php');

		$this->verify_environment();
		$this->setup_environment();
		$this->sync_database();
		$this->init($script);
	}

	/**
	* Init
	*
	*/
	protected function init()
	{
		if (!defined('SKIPDB'))
		{
			if ($_REQUEST['firstrun'] == 'true' OR $_REQUEST['step'] == 1 OR $this->identifier == 'cli')
			{
				require_once(DIR . '/includes/class_bitfield_builder.php');
				vB_Bitfield_Builder::save($this->db);
			}
		}

		// Where does this upgrade need to begin?
		$this->scriptinfo = $this->get_upgrade_start();

	}

	/**
	* Things to do after each script is processed
	*
	*/
	protected function process_script_end()
	{
		build_bbcode_cache();
		build_options();
		require_once(DIR . '/includes/functions_databuild.php');
		build_bbcode_video(true);
		require_once(DIR . '/includes/class_bitfield_builder.php');
		vB_Bitfield_Builder::save($this->db);
		build_forum_permissions();
	}

	/**
	*	Load an upgrade script and return object
	*
	*	@var	string	Version number
	*
	* @return object
	*/
	protected function load_script($version)
	{
		$callback = create_function('$var', 'return !(strpos($var, \'*\'));');
		$versions = array_merge($this->endscripts, array_keys($this->versions), array('install'));
		$versions = array_filter($versions, $callback);

		// ensure comparisons are done as strings
		$versions = array_map('strval', $versions);
		$version = (string)$version;



		require_once(DIR . "/install/includes/class_upgrade_$version.php");

		$classname = "vB_Upgrade_$version";
		$script = new $classname($this->registry, $this->phrase, end($this->versions));
		$script->caller = $this->identifier;
		$script->limitqueries = $this->limitqueries;
		return $script;
	}

	/**
	*	Verify if specified version number is the next version that we should be upgrading to
	*
	*	@var	string	Version number
	*
	* @return bool
	*/
	protected function verify_version($version, $script)
	{
		if ($version == 'install')
		{
			return true;
		}

		if (version_compare($this->registry->options['templateversion'], $script->VERSION_COMPAT_STARTS, '>=') AND version_compare($this->registry->options['templateversion'], $script->VERSION_COMPAT_ENDS, '<'))
		{
			return true;
		}
		else if ($this->registry->options['templateversion'] == $script->PREV_VERSION)
		{
			return true;
		}
		else if (in_array($version, $this->endscripts) AND end($this->versions) == $this->registry->options['templateversion'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Fetch the upgrade log information from the database - past upgrade process
	*
	* @var		string	If defined, start upgrade at this version
	*
	* @return	array	Version information about upgrade point at which to start
	*/
	protected function get_upgrade_start($version = null)
	{
		if ($this->setuptype == 'install' AND !$version)
		{
			return array(
				'version' => 'install',
			);
		}

		$shortversions = array();
		foreach ($this->versions AS $key => $foo)
		{
			$shortversions[] = strval($key);
		}

		$gotlog = false;
		if (!$version)
		{

			$this->db->hide_errors();
			if (
				$log = @$this->db->query_first("SELECT * FROM " . TABLE_PREFIX . "upgradelog ORDER BY upgradelogid DESC LIMIT 1")
					OR
				$log = @$this->db->query_first("SELECT * FROM " . TABLE_PREFIX . "log_upgrade_step ORDER BY upgradelogid DESC LIMIT 1")
			)
			{
				$gotlog = true;
			}
			$this->db->errno = 0;
			$this->db->show_errors();
		}

		if ($gotlog)
		{
			if (!preg_match('/^upgrade_(\w+)\.php$/siU', $log['script'], $reg))
			{
				$gotlog = false;

				if (in_array($log['script'], $this->endscripts) OR preg_match('#^\d+((a|b|g|rc|pl)\d+)?$#si', $log['script']))
				{
					$gotlog = true;
					$scriptver = $log['script'];
				}
			}
			else
			{
				if (!array_search($reg[1], $shortversions))
				{
					$gotlog = false;
				}
				else
				{
					$scriptver = $reg[1];
					$oldscript = true;
				}
			}
		}

		if ($gotlog)
		{
			if ($log['step'] == 0)
			{
				// the last entry has step = 0, meaning the script completed...
				$versionkey = array_search($scriptver, $shortversions);
				$shorten = 0;

				while ($versionkey === false AND $wildversion != '*')
				{
					$wildversion = substr_replace($scriptver, '*', --$shorten);
					$versionkey = array_search($wildversion, $shortversions);
				}
				++$versionkey;

				/*
				if ($versionkey !== false AND $scriptver < $this->versions[$shortversions["$versionkey"]])
				{
					$versionkey = false;
				}
				*/

				// to handle the case when we are running the version before a wildcard version
				while (strpos($shortversions["$versionkey"], '*') !== false)
				{
					++$versionkey;
				}

				if ($versionkey !== false AND isset($shortversions["$versionkey"]))
				{
					$scriptinfo['version'] = $shortversions["$versionkey"];
				}
				else if (($currentkey = array_search($scriptver, $this->endscripts)) !== false)
				{
					$scriptinfo['version'] = $this->endscripts[$currentkey + 1];
				}
				else if ($this->install_suite())
				{
					$scriptinfo['version'] = $this->endscripts[0];	// vbblog
				}
				else
				{
					$scriptinfo['version'] = $this->endscripts[count($this->products)];	// skimlinks and any other non suite products
				}
	
				$scriptinfo['only'] = false;
			}
			else if ($log['startat'])
			{
				$scriptinfo['version'] = $scriptver;
				$scriptinfo['step']    = $log['step'];
				$scriptinfo['startat'] = $log['startat'] + $log['perpage'];
				$scriptinfo['only'] = $log['only'];
			}
			else
			{
				$scriptinfo['version'] = $scriptver;
				$scriptinfo['step']    = $log['step'] + 1;
				$scriptinfo['only'] = $log['only'];
			}

			if ($log['step'] != 0 AND $oldscript)
			{
				// If last upgrade was done with a 4.1+ upgrade script then $reg[1] will be set
				// $log['step'] has different meanings between pre 4.1 and post 4.1 so for pre 4.1, set the step at one since we now
				// track each individual query as a step rather than groups of queries.
				$log['step'] = 1;
			}
		}
		else
		{
			if ($version)
			{
				$shortver = $version;
			}
			else
			{
				$shortver = $this->fetch_short_version($this->registry->versionnumber);
			}

			$versionkey = array_search($shortver, $shortversions);
			$shorten = 0;
			while ($versionkey === false AND $wildversion != '*')
			{
				$wildversion =  substr_replace($shortver, '*', --$shorten);
				$versionkey = array_search($wildversion, $shortversions);
			}

			/*
			if ($versionkey !== false AND $shortver < $this->versions[$shortversions["$versionkey"]])
			{
				$versionkey = false;
			}
			*/

			++$versionkey;

			// to handle the case when we are running the version before a wildcard version
			while (strpos($shortversions["$versionkey"], '*') !== false)
			{
				++$versionkey;
			}

			$onproduct = false;
			if ($versionkey !== false AND isset($shortversions["$versionkey"]))
			{
				// we know what script this version needs to go to
				$scriptinfo['version'] = $shortversions["$versionkey"];
				$onproduct = true;
			}
			else if (($version == 'install' OR ($versionkey == count($shortversions))))
			{
				if ($this->install_suite())
				{
					$scriptinfo['version'] = $this->endscripts[0]; // 'vbblog'
				}
				else
				{
					$scriptinfo['version'] = $this->endscripts[count($this->products)]; // skimlinks
				}
				$onproduct = true;
			}
			else if ($shortver != 'final' AND (($value = array_search($shortver, $this->endscripts)) !== false))
			{
				$scriptinfo['version'] = $this->endscripts[$value + 1];
				$onproduct = true;
			}
	

			if (!$onproduct)
			{
				if (in_array(intval($this->registry->versionnumber), array(3,4)))
				{
					// assume we are finished
					$scriptinfo['version'] = 'final';
				}
				else
				{
					// no log and invalid version, so assume it's 2.x
					$scriptinfo['version'] = '360';
				}
			}
		}

		return $scriptinfo;
	}

	protected function install_suite()
	{
		foreach ($this->products as $productid)
		{
			if (!file_exists(DIR . "/includes/xml/product-$productid.xml"))
			{
				return false;
			}
		}
		return true;
	}

	/**
	* Convert a "Long version" string into a short version
	*
	* @var string
	*
	* @return string
	*/
	protected function fetch_short_version($version)
	{
		if (preg_match('/^(\w+\s+)?(\d+)\.(\d+)\.(\d+)(\s+(a|alpha|b|beta|g|gamma|rc|release candidate|gold|stable|final|pl|patch level)(\s+(\d+))?)?$/siU', $version, $regs))
		{
			switch (strtolower($regs[6]))
			{
				case 'alpha':
					$regs[6] = 'a';
					break;
				case 'beta':
					$regs[6] = 'b';
					break;
				case 'gamma':
					$regs[6] = 'g';
					break;
				case 'release candidate':
					$regs[6] = 'rc';
					break;
				case 'patch level':
					$regs[6] = 'pl';
					break;
				case 'gold':
				case 'stable':
				case 'final':
					$regs[6] = '';
					$regs[7] = '';
					break;
			}

			return $regs[2] . $regs[3] . $regs[4] . $regs[6] . $regs[8];
		}
		else
		{
			return $version;
		}
	}

	/**
	* Database queries that need to be executed to ensure that the database is in a known state that is functional
	* with the upgrade. Pre 3.6.0 there were quite a bit of queries here
	*
	*/
	protected function sync_database()
	{
		if (defined('SKIPDB'))
		{
			return;
		}
		$this->db->hide_errors();
		// need to do this here or we might get problems if options are built before the end of the script
		// Not sure what this is doing to be honest
		$this->db->query_write("REPLACE INTO " . TABLE_PREFIX . "adminutil (title, text) VALUES ('datastorelock', '0')");

		// post_parsed needs to be called postparsed for some of the rebuild functions to work correctly
		$this->startup_alter("ALTER TABLE " . TABLE_PREFIX . "post_parsed RENAME " . TABLE_PREFIX . "postparsed");

		// These tables are referenced by upgrade scripts that predate these modifications
		$this->startup_alter("ALTER TABLE " . TABLE_PREFIX . "upgradelog ADD only TINYINT NOT NULL DEFAULT '0'");
		$this->startup_alter("ALTER TABLE " . TABLE_PREFIX . "adminmessage ADD args MEDIUMTEXT");
		$this->startup_alter("ALTER TABLE " . TABLE_PREFIX . "language ADD phrasegroupinfo MEDIUMTEXT");
		$this->db->show_errors();
	}

	/**
	 * Database queries that need to be executed to ensure that the database is in a known state that is functional
	 * with the upgrade. Pre 3.6.0 there were quite a bit of queries here. 
	 *	Error: 1142 SQLSTATE: 42000 (ER_TABLEACCESS_DENIED_ERROR)
	 *	Message: %s command denied to user '%s'@'%s' for table '%s'
	 *	Error: 1143 SQLSTATE: 42000 (ER_COLUMNACCESS_DENIED_ERROR)
	 *	Message: %s command denied to user '%s'@'%s' for column '%s' in table '%s'	 *
	 *
	 * @param	string	Alter Query
	 * 
	 */
	private function startup_alter($query)
	{
		static $found = false;

		if ($errorstate = $this->db->reporterror)
		{
			$this->db->hide_errors();
		}
		$this->db->query_write($query);
		if ($errorstate)
		{
			$this->db->show_errors();
		}

		if (!$found AND ($this->db->errno == 1142 OR $this->db->errno == 1143))
		{
			$this->startup_errors[] = $this->phrase['core']['no_alter_permission'];
			$found = true;
		}
	}

	/**
	* Verify CSS dir can be written to
	*
	* @param	int	Styleid to check - -1 to check all
	*
	* @return	boolean
	*/
	protected function verify_cssdir($styleid = -1)
	{
		if ($this->setuptype == 'install' OR !$this->registry->options['storecssasfile'])
		{
			return true;
		}

		if ($styleid != -1)
		{
			if (!$this->verify_write_cssdir($styleid, 'ltr') OR !$this->verify_write_cssdir($styleid, 'rtl'))
			{
				return false;
			}
		}

		$childsets = $this->db->query_read("
			SELECT styleid, title, parentlist
			FROM " . TABLE_PREFIX . "style
			WHERE parentid = $styleid
		");
		while ($childset = $this->db->fetch_array($childsets))
		{
			if (!$this->verify_cssdir($childset['styleid']))
			{
				return false;
			}
		}

		return true;
	}

	/**
	* Verify directory can be written to
	*
	* @param	int	Styelid
	* @param	str	Text direction
	*
	* @return	boolean	Success
	*/
	protected function verify_write_cssdir($styleid, $dir = 'ltr')
	{
		$styledir = DIR . '/clientscript/vbulletin_css/style' . str_pad($styleid, 5, '0', STR_PAD_LEFT) . ($dir == 'ltr' ? 'l' : 'r');
		//if we have a file that's not a directory or not writable something is wrong.
		if (file_exists($styledir) AND (!is_dir($styledir) OR !is_writable($styledir)))
		{
			return false;
		}
		//create the directory -- if it still exists try to continue with the existing dir
		if (!file_exists($styledir))
		{
			if (!@mkdir($styledir))
			{
				return false;
			}
		}

		//check for success.
		if (!is_dir($styledir) OR !is_writable($styledir))
		{
			return false;
		}

		return true;
	}

	/**
	* Verify conditions are acceptable to perform the upgrade/install
	*
	*/
	protected function verify_environment()
	{
		// php version check
		if (!function_exists('version_compare') OR version_compare(PHP_VERSION, '5.2.0', '<'))
		{
			$this->startup_errors[] = sprintf($this->phrase['core']['php_version_too_old'], PHP_VERSION);
		}

		if (!defined('SKIPDB') AND function_exists('version_compare') AND version_compare(MYSQL_VERSION, '4.1.0', '<='))
		{
			$this->startup_errors[] = sprintf($this->phrase['core']['mysql_version_too_old'], MYSQL_VERSION);
		}

		// config file check
		if (!file_exists(DIR . '/includes/config.php'))
		{
			$this->startup_errors[] = $this->phrase['core']['cant_find_config'];
		}
		else if (!is_readable(DIR . '/includes/config.php'))
		{
			$this->startup_errors[] = $this->phrase['core']['cant_read_config'];
		}

		if (($err = verify_optimizer_environment()) !== true)
		{
			$this->startup_errors[] = $this->phrase['core'][$err];
		}

		if (function_exists('mmcache_get'))
		{
			$this->startup_errors[] = $this->phrase['core']['turck'];
		}

		if (!$this->verify_cssdir())
		{
			$this->startup_errors[] = $this->phrase['core']['css_not_writable'];
		}

		// Actually will never get here if the 'connect' function doesn't exist as we've already tried to connect
		if (!function_exists($this->db->functions['connect']))
		{
			$this->startup_errors[] = sprintf($this->phrase['core']['database_functions_not_detected'], $this->registry->config['Database']['dbtype']);
		}

		$this->verify_install_environment();
	}

	protected function verify_install_environment()
	{
		if (defined('SKIPDB'))
		{
			$this->registry->options = array(
				'cpstylefolder' => 'vBulletin_3_Silver',
			);

			$this->db->hide_errors();
			// make database connection
			$this->db->connect(
				$this->registry->config['Database']['dbname'],
				$this->registry->config['MasterServer']['servername'],
				$this->registry->config['MasterServer']['port'],
				$this->registry->config['MasterServer']['username'],
				$this->registry->config['MasterServer']['password'],
				$this->registry->config['MasterServer']['usepconnect'],
				$this->registry->config['SlaveServer']['servername'],
				$this->registry->config['SlaveServer']['port'],
				$this->registry->config['SlaveServer']['username'],
				$this->registry->config['SlaveServer']['password'],
				$this->registry->config['SlaveServer']['usepconnect']
			);

			$connect_errno = $this->db->errno();
			$connect_error = ($this->db->error ? $this->db->error : $this->db->error());

			if (empty($this->registry->config['Database']['no_force_sql_mode']) AND $this->db->connection_master)
			{
				$this->db->force_sql_mode('');
				// small hack to prevent the above query from generating an error below
				$this->db->query_read('SELECT 1 + 1');
			}

			if ($this->db->connection_master)
			{
				if ($connect_errno)
				{ // error found
					if ($connect_errno == 1049)
					{
						$this->db->query_write("CREATE DATABASE " . $this->registry->config['Database']['dbname']);
						$this->db->select_db($this->registry->config['Database']['dbname']);
						if ($this->db->errno() == 1049)
						{ // unable to create database
							$this->startup_errors[] = sprintf($this->phrase['install']['unable_to_create_db'], $this->registry->config['Database']['dbname']);
						}
					}
					else
					{ // Unknown Error
						$this->startup_errors[] = sprintf($this->phrase['install']['connect_failed'], $connect_errno, $connect_error);
					}
				}
				else
				{ // connection suceeded and database already exists
					// What was here in the old install is now Step 1 of the new install..
				}
			}
			else
			{ // Unable to connect to database
				$this->startup_errors[] = $this->phrase['install']['no_connect_permission'];
				$this->startup_errors[] = sprintf($this->phrase['install']['db_error_desc'],  $connect_error);
			}
			$this->db->show_errors();
		}
	}

	/**
	* Setup environment common to all upgrades
	*
	*/
	protected function setup_environment()
	{
		if (function_exists('set_time_limit') AND !SAFEMODE)
		{
			@set_time_limit(0);
		}

		if (!defined('VERSION'))
		{
			define('VERSION', defined('FILE_VERSION') ? FILE_VERSION : '');
		}

		// Notices
		if (empty($this->registry->config['Database']['force_sql_mode']) AND $this->db->connection_master)
		{
			// check to see if MySQL is running strict mode and recommend disabling it
			$this->db->hide_errors();
			$strict_mode_check = $this->db->query_first("SHOW VARIABLES LIKE 'sql\\_mode'");
			if (strpos(strtolower($strict_mode_check['Value']), 'strict_') !== false)
			{
				$this->startup_warnings[] = $this->phrase['core']['mysql_strict_mode'];
			}
			$this->db->show_errors();
		}

		if (is_array($this->phrase['stylevar']))
		{
			foreach ($this->phrase['stylevar'] AS $stylevarname => $stylevarvalue)
			{
				vB_Template_Runtime::addStyleVar($stylevarname, $stylevarvalue);
			}
		}

		// Get versions of .xml files for header diagnostics
		foreach ($this->xml_versions AS $file => $null)
		{
			if ($fp = @fopen(DIR . '/install/vbulletin-' . $file . '.xml', 'rb'))
			{
				$data = @fread($fp, 300);
				if (
					($file != 'settings' AND preg_match('#vbversion="(.*?)"#', $data, $matches))
						OR
					($file == 'settings' AND preg_match('#<setting varname="templateversion".*>(.*)</setting>#sU', $data, $matches) AND preg_match('#<defaultvalue>(.*?)</defaultvalue>#', $matches[1], $matches))
				)
				{
					$this->xml_versions[$file] = $matches[1];
				}
				else
				{
					$this->xml_versions[$file] =  $this->phrase['core']['unknown'];
				}
				fclose($fp);
			}
			else
			{
				$this->xml_versions[$file] = $this->phrase['core']['file_not_found'];
			}
		}
	}
}

abstract class vB_Upgrade_Version
{
	/*Constants=====================================================================*/
	const MYSQL_ERROR_CANT_CREATE_TABLE       = 1005;
	const MYSQL_ERROR_TABLE_EXISTS            = 1050;
	const MYSQL_ERROR_COLUMN_EXISTS           = 1060;
	const MYSQL_ERROR_KEY_EXISTS              = 1061;
	const MYSQL_ERROR_UNIQUE_CONSTRAINT       = 1062;
	const MYSQL_ERROR_PRIMARY_KEY_EXISTS      = 1068;
	const MYSQL_ERROR_DROP_KEY_COLUMN_MISSING = 1091;
	const MYSQL_ERROR_TABLE_MISSING           = 1146;
	const FIELD_DEFAULTS                      = '__use_default__';
	const PHP_TRIGGER_ERROR                   = 1;
	const MYSQL_HALT                          = 2;
	const MYSQL_ERROR                         = 3;
	const APP_CREATE_TABLE_EXISTS             = 4;

	/*Properties====================================================================*/
	/**
	* Number of substeps in this step
	*
	* @var int;
	*/
	public $stepcount = 0;

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* The object that will be used to execute queries
	*
	* @var	vB_Database
	*/
	protected $db = null;

	/**
	* A list of modifications to be made when execute is called.
	*
	* @var	array
	*/
	protected $modifications = array();

	/**
	* List of various messages to send back to the delegate class
	*
	* @var	array
	*/
	protected $response = array();

	/**
	* A cache of table alter objects, to reduce the amount of overhead
	* when there are multiple alters to a single table.
	*
	* @var	array
	*/
	public $alter_cache = array();

	/**
	*	Do we support innodb?
	*
	* @var string
	*/
	protected $hightrafficengine = 'MyISAM';

	/**
	* Identifier of library that called this script - cli and ajax at present
	*
	* @var 	string
	*/
	public $caller = '';

	/**
	* Set to true if step queries are to be $perpage limited, yes for Ajax, no for CLI
	*
	* @var 	boolean
	*/
	public $limitqueries = true;

		/**
	* Identifier of max upgrade version for library scripts
	*
	* @var 	string
	*/
	public $maxversion = '';

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	Array		Phrases
	* @param	string	Max upgrade version
	*/
	public function __construct(&$registry, $phrase, $maxversion)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
			$this->db =& $this->registry->db;
			if (VB_AREA == 'Upgrade')
			{
				if (!isset($this->registry->options['upgrade_from']) OR empty($this->registry->options['upgrade_from']))
				{
					$this->set_new_option('upgrade_from', 'version', $this->registry->options['templateversion'], 'string', '' );
					$this->registry->options['upgrade_from'] =  $this->registry->options['templateversion'];
				}
			}
		}
		else
		{
			trigger_error('vB_Upgrade: $this->registry is not an object.', E_USER_ERROR);
		}

		$this->phrase =& $phrase;
		$this->maxversion = $maxversion;

		foreach(get_class_methods($this) AS $method_name)
		{
			if (preg_match('#^step_(\d+)$#', $method_name, $matches))
			{
				$this->stepcount++;
			}
		}

		// Maintain backwards compatibility with install system
		require_once(DIR . '/install/functions_installupgrade.php');
		$this->hightrafficengine = get_high_concurrency_table_engine($this->db);
		require_once(DIR . '/includes/class_dbalter.php');


	}

	/**
	* Tests to see if the specified field exists in a table.
	*
	* @param	string	Table to test. Do not include table prefix!
	* @param	string	Name of field to test
	*
	* @return	boolean	True if field exists, false if it doesn't
	*/
	protected function field_exists($table, $field)
	{
		$error_state = $this->db->reporterror;
		if ($error_state)
		{
			$this->db->hide_errors();
		}

		$this->db->query_write("SELECT $field FROM " . TABLE_PREFIX . "$table LIMIT 1");

		if ($error_state)
		{
			$this->db->show_errors();
		}

		if ($this->db->errno())
		{
			$this->db->errno = 0;
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Adds a field to a table.
	*
	* @param	string	Message to display
	* @param	string	Name of the table to alter. Do not include table prefix!
	* @param	string	Name of the field to add
	* @param	array	Extra attributes. Supports: length, attributes, null, default, extra. You may also use the define FIELD_DEFAULTS.
	*/
	protected function add_field($message, $table, $field, $type, $extra)
	{
		if ($extra == self::FIELD_DEFAULTS OR $extra['attributes'] == self::FIELD_DEFAULTS)
		{
			switch (strtolower($type))
			{
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
				{
					$defaults = array(
						'attributes' => 'UNSIGNED',
						'null'       => false,
						'default'    => 0,
						'extra'      => ''
					);
				}
				break;

				case 'char':
				case 'varchar':
				case 'binary':
				case 'varbinary':
				{
					if ($extra == self::FIELD_DEFAULTS)
					{
						$this->add_error("You must specify a length for fields of type $type to use the defaults.", self::PHP_TRIGGER_ERROR, true);
						return $this->response;
					}

					$defaults = array(
						'length'     => $extra['length'],
						'attributes' => '',
						'null'       => false,
						'default'    => '',
						'extra'      => ''
					);
				}
				break;

				case 'tinytext':
				case 'text':
				case 'mediumtext':
				case 'longtext':
				case 'tinyblob':
				case 'blob':
				case 'mediumblob':
				case 'longblob':
				{
					$defaults = array(
						'attributes' => '',
						'null'       => true,
						'extra'      => ''
					);
				}
				break;

				default:
				{
					$this->add_error("No defaults specified for fields of type $type.", self::PHP_TRIGGER_ERROR, true);
					return $this->response;
				}
			}
			if (is_array($extra))
			{
				unset($extra['attributes']);
				$extra = array_merge($defaults, $extra);
			}
			else
			{
				$extra = $defaults;
			}
		}

		$this->modifications[] = array(
			'modification_type' => 'add_field',
			'alter'             => true,
			'message'           => $message,
			'data'              => array(
				'table'      => $table,
				'name'       => $field,
				'type'       => $type,
				'length'     => intval($extra['length']),
				'attributes' => $extra['attributes'],
				'null'       => (!empty($extra['null']) ? true : false),
				'default'    => $extra['default'],
				'extra'      => $extra['extra'],
				'ignorable_errors' => array(self::MYSQL_ERROR_COLUMN_EXISTS),
			)
		);
	}

	/**
	* Drops a field from a table.
	*
	* @param	string	Message to display
	* @param	string	Table to drop from. Do not include table prefix!
	* @param	string	Field to drop
	*/
	protected function drop_field($message, $table, $field)
	{
		$this->modifications[] = array(
			'modification_type' => 'drop_field',
			'alter'             => true,
			'message'           => $message,
			'data'              => array(
				'table' => $table,
				'name'  => $field,
				'ignorable_errors' => array(self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING),
			)
		);
	}

	/**
	* Adds an index to a table. Can span multiple fields.
	*
	* @param	string			Message to display
	* @param	string			Table to add the index to. Do not include table prefix!
	* @param	string			Name of the index
	* @param	string|array	Fields to cover. Must be an array if more than one
	* @param	string			Type of index (empty defaults to a normal/no constraint index)
	*/
	protected function add_index($message, $table, $index_name, $fields, $type = '')
	{
		$this->modifications[] = array(
			'modification_type' => 'add_index',
			'alter'             => true,
			'message'           => $message,
			'data'              => array(
				'table'  => $table,
				'name'   => $index_name,
				'fields' => (!is_array($fields) ? array($fields) : $fields),
				'type'   => $type,
				'ignorable_errors' => array(self::MYSQL_ERROR_KEY_EXISTS),
			)
		);
	}

	protected function add_cronjob($data)
	{
		if (!$this->db->query_first("SELECT filename FROM " . TABLE_PREFIX . "cron WHERE filename = '" . $this->db->escape_string($data['filename']) . "'"))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'cron', 1, 1),
				"INSERT INTO " . TABLE_PREFIX . "cron
					(nextrun, weekday, day, hour, minute, filename, loglevel, varname, volatile, product)
				VALUES
				(
					" . intval($data['nextrun']) . ",
					'" . intval($data['weekday']) . "',
					'" . intval($data['day']) ."',
					'" . intval($data['hour']) . "',
					'" . $this->db->escape_string($data['minute']) . "',
					'" . $this->db->escape_string($data['filename']) . "',
					'" . intval($data['loglevel']) . "',
					'" . $this->db->escape_string($data['varname']) . "',
					" . intval($data['volatile']) . ",
					'" . $this->db->escape_string($data['product']) . "'
				)"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Adds an adminmessage to the system. Checks if message already exists.
	*
	* @param	string			varname of message (unique)
	* @param	array				Adminmessage schema (dismissable, script, action, execurl, method, status)
	* @param	bool				Allow duplicate entry on varname?
	* @param	array				Values to send into the phrase at run time
	*/
	protected function add_adminmessage($varname, $data, $duplicate = false, $args = null)
	{
		$exists = $this->db->query_first("SELECT * FROM " . TABLE_PREFIX . "adminmessage WHERE varname = '" . $this->db->escape_string($varname) . "'");
		if ($exists AND !$duplicate)
		{
			$this->skip_message();
		}
		else
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "adminmessage"),
				"INSERT INTO " . TABLE_PREFIX . "adminmessage
					(varname, dismissable, script, action, execurl, method, dateline, status, args)
				VALUES
					(
						'" . $this->db->escape_string($varname) . "',
						" . intval($data['dismissible']) . ",
						'" . $this->db->escape_string($data['script']) . "',
						'" . $this->db->escape_string($data['action']) . "',
						'" . $this->db->escape_string($data['execurl']) . "',
						'" . $this->db->escape_string($data['method']) . "',
						" . TIMENOW . ",
						'" . $this->db->escape_string($data['status']) . "',
						'" . ($args ? $this->db->escape_string(@serialize($args)) : '') . "'
				)");
		}
	}

	/**
	* Adds a new contenttype
	*
	* @param	string	Productid (vbulletin, vbcms, vbblog, etc)
	* @param	string	Package Class (vBForum, vBBlog, vBCms, etc)
	* @param	string	Contenttype (Post, Thread, Forum, etc)
	* @param	int		Can Place?
	* @param	int		Can Search
	* @param	int		Can Tag
	* @param	int		Can Attach
	* @param	int		Is aggregator
	*/
	protected function add_contenttype($productid, $package_class, $contenttype_class, $canplace = 0, $cansearch = 0, $cantag = 0, $canattach = 0, $isaggregator = 0)
	{
		$packageinfo = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package
			WHERE
				productid = '" . $this->db->escape_string($productid) . "'
					AND
				class = '" . $this->db->escape_string($package_class) . "'
		");
		if ($packageinfo)
		{
			$contenttypeinfo = $this->db->query_first("
				SELECT contenttypeid
				FROM " . TABLE_PREFIX . "contenttype
				WHERE
					packageid = {$packageinfo['packageid']}
						AND
					class = '" . $this->db->escape_string($contenttype_class) . "'
			");
			if (!$contenttypeinfo)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
					"INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
						(class, packageid, canplace, cansearch, cantag, canattach, isaggregator)
					VALUES
						(	'" . $this->db->escape_string($contenttype_class) . "',
							{$packageinfo['packageid']},
							'{$canplace}',
							'{$cansearch}',
							'{$cantag}',
							'{$canattach}',
							'{$isaggregator}'
						)
					"
				);
				
				return true;
			}
		}
		$this->skip_message();
	}

	/**
	* Drops an index from a table.
	*
	* @param	string	Message to display
	* @param	string	Table to drop the index from. Do not include table prefix!
	* @param	string	Name of the index to remove
	*/
	protected function drop_index($message, $table, $index_name)
	{
		$this->modifications[] = array(
			'modification_type' => 'drop_index',
			'alter'             => true,
			'message'           => $message,
			'data'              => array(
				'table' => $table,
				'name'  => $index_name,
				'ignorable_errors' => array(self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING),
			)
		);
	}

	/**
	* Executes the specified step
	*
	* @param	int			Step to execute
	* @param	boolen	Check if table exists for create table commands
	* @param	array		Data to send to step (startat, prompt results, etc)
	*
	* @return	mixed	Return array upon error
	*/
	public function execute_step($step, $check_table = true, $data = null)
	{
		$this->response = array();
		$stepname = "step_$step";
		$result = $this->$stepname($data);
		return $this->execute($check_table, $result);
	}

	/**
	* Executes the specified modifications.
	*
	* @param	boolen	Check if table exists for create table commands
	* @param	array		return value from step execution
	*
	* @return	mixed	Return array upon error
	*/
	public function execute($check_table = true, $result = null)
	{
		$this->response['returnvalue'] = $result;

		if ($check_table AND !$this->check_table_conflict())
		{
			$this->add_message($this->phrase['core']['table_conflict']);
			$this->modifications = array();
			return $this->response;
		}

		foreach ($this->modifications AS $modification)
		{
			$this->add_message($modification['message'], 'STANDARD', $modification['replace'] ? $modification['replace'] : false);

			$data =& $modification['data'];

			if (!empty($modification['alter']))
			{
				$db_alter =& $this->setup_db_alter_class($data['table']);
			}
			else
			{
				unset($db_alter);
			}

			$alter_result = null;

			switch ($modification['modification_type'])
			{
				case 'add_field':
					$alter_result = $db_alter->add_field($data);
					if ($errno = $this->db->errno() AND in_array($errno, $data['ignorable_errors']))
					{
						$alter_result = true;
					}
					break;

				case 'drop_field':
					$alter_result = $db_alter->drop_field($data['name']);
					if ($errno = $this->db->errno() AND in_array($errno, $data['ignorable_errors']))
					{
						$alter_result = true;
					}
					break;

				case 'add_index':
					$alter_result = $db_alter->add_index($data['name'], $data['fields'], $data['type']);
					if ($errno = $this->db->errno() AND in_array($errno, $data['ignorable_errors']))
					{
						$alter_result = true;
					}
					break;

				case 'drop_index':
					$alter_result = $db_alter->drop_index($data['name']);
					if ($errno = $this->db->errno() AND in_array($errno, $data['ignorable_errors']))
					{
						$alter_result = true;
					}
					break;

				case 'run_query':
					$error_state = $this->db->reporterror;
					if ($error_state)
					{
						$this->db->hide_errors();
					}

					$query_result = $this->db->query_write("### vBulletin Database Alter ###\r\n" . $data['query']);

					if ($errno = $this->db->errno())
					{
						if (!in_array($errno, $data['ignorable_errors']))
						{
							if ($errno == self::MYSQL_ERROR_CANT_CREATE_TABLE)
							{
								if (stripos($this->db->error, 'errno: 121') !== false AND stripos($data['query'], 'engine=innodb'))
								{
									preg_match('#CREATE TABLE ([a-z0-9_]+)#si', $data['query'], $matches);
									$this->add_error(sprintf($this->phrase['core']['table_creation_x_failed'], $matches[1]), self::PHP_TRIGGER_ERROR, true);
									$this->modifications = array();
									return $this->response;
								}
							}

							$this->add_error(array(
								'message' => $data['query'],
								'error'   => $this->db->error,
								'errno'   => $this->db->errno
								),
								self::MYSQL_HALT,
								true);

							$this->modifications = array();
							return $this->response;
						}
						else
						{
							// error occurred, but was ignorable
							$this->db->errno = 0;
						}
					}

					if ($error_state)
					{
						$this->db->show_errors();
					}

					break;

				case 'show_message':
					// do nothing -- just show the message
					break;

				case 'debug_break':
				//	echo "</ul><div>Debug break point. Stopping execution.</div>";
				//	exit;

				default:
					$this->add_error(sprintf($this->phrase['core']['invalid_modification_type_x'], $modification['modification_type']), self::PHP_TRIGGER_ERROR, true);
					$this->modifications = array();
					return $this->response;
			}

			if ($alter_result === false)
			{
				if ($db_alter->error_no == ERRDB_MYSQL)
				{
					$this->db->show_errors();
					$this->db->sql = $db_alter->sql;
					$this->db->connection_recent = null;
					$this->db->error = $db_alter->error_desc;
					$this->db->errno = -1;

					$this->add_error(array(
						'message' => $this->db->sql,
						'error'   => $this->db->error,
						'errno'   => $this->db->errno
						), self::MYSQL_HALT, true);

					$this->modifications = array();
					return $this->response;
				}
				else
				{
					if (ob_start())
					{
						print_r($modification);
						$results = ob_get_contents();
						ob_end_clean();
					}
					else
					{
						$results = serialize($modification);
					}

					$this->add_error(array(
						'message' => $results,
						'error'   => $db_alter->error_desc,
						'errno'   => $db_alter->error_no
						), self::MYSQL_ERROR, false);
					//$this->modifications = array();
					//return $this->response;
				}
			}
		}
		$this->modifications = array();
		return $this->response;
	}

	/**
	* Runs an arbitrary query. An error will stop execution unless
	* the error code is listed as ignored
	*
	* @param	string	Message to display
	* @param	string	Query to execute.
	* @param	array	List of error codes that should be ignored.
	*/
	protected function run_query($message, $query, $ignorable_errors = array())
	{
		$this->modifications[] = array(
			'modification_type' => 'run_query',
			'message'           => $message,
			'data'              => array(
				'query'            => $query,
				'ignorable_errors' => (!is_array($ignorable_errors) ? array($ignorable_errors) : $ignorable_errors)
			)
		);
	}

	/**
	* Shortcut for adding the "long next step" message
	*
	*/
	public function long_next_step()
	{
		$this->show_message($this->phrase['core']['next_step_long_time']);
	}

	/**
	* Shortcut for adding the "skipping step" message
	*
	*/
	public function skip_message()
	{
		$this->show_message($this->phrase['core']['skipping_not_needed']);
	}

	/**
	* Does nothing but shows a message.
	*
	* @param	string	Message to display
	* @param	boolean	Replace the previous message with this message, if the previous message also had $replace set
	*/
	public function show_message($message, $replace = false)
	{
		$this->modifications[] = array(
			'modification_type' => 'show_message',
			'message'           => $message,
			'data'              => array(),
			'replace'           => $replace,
		);
	}

	/**
	* This is a function useful for debugging. It will stop execution of the
	* modifications when this call is reached, allowing emulation of an upgrade
	* step that failed at a specific point.
	*/
	protected function debug_break()
	{
		$this->modifications[] = array(
			'modification_type' => 'debug_break',
			'message'           => '',
			'data'              => array()
		);
	}

	/**
	* Sets up a DB alter object for a table. Only called internally.
	*
	* @param	string	Table the object should be instantiated for
	*
	* @return	object	Instantiated alter object
	*/
	private function &setup_db_alter_class($table)
	{
		if (isset($this->alter_cache["$table"]))
		{
			return $this->alter_cache["$table"];
		}
		else
		{
			$this->alter_cache["$table"] = new vB_Database_Alter_MySQL($this->db);
			$this->alter_cache["$table"]->fetch_table_info($table);
			return $this->alter_cache["$table"];
		}
	}

	/**
	* Checks if a create table call will conflict with an existing table of the same name
	*
	* @return	array	Data about the success of the check, 'error' will be empty if the query is ok
	*/
	protected function check_table_conflict()
	{
		$error = false;
		foreach ($this->modifications AS $modification)
		{
			if (
				$modification['modification_type'] == 'run_query'
					AND
				preg_match('#^\s*create\s+table\s+' . TABLE_PREFIX . '([a-z0-9_\-]+)\s+\((.*)\)#si', $modification['data']['query'], $matches)
			)
			{
				$db_alter = $this->setup_db_alter_class($matches[1]);
				if ($this->alter_cache["$matches[1]"]->init)
				{
					$existingtable = array_keys($db_alter->table_field_data);
					$create = preg_split("#,\s*(\r|\t)#si", $matches[2], -1, PREG_SPLIT_NO_EMPTY);
					$newtable = array();

					foreach ($create AS $field)
					{
						$field = trim($field);
						if (preg_match('#^\s*(((fulltext|primary|unique)\s*)?key\s+|index\s+|engine\s*=)#si', $field))
						{
							continue;
						}
						if (preg_match('#^([a-z0-9_\-]+)#si', $field, $matches2))
						{
							$newtable[] = $matches2[1];
						}
					}

					if (array_diff($existingtable, $newtable))
					{
						$this->add_error(TABLE_PREFIX . $matches[1], self::APP_CREATE_TABLE_EXISTS, true);
						$error = true;
					}
				}
			}
		}

		return !$error;
	}

	/**
	* Add an error
	*
	* @param	string	Data of item to be output
	* @param	int			Key of item
	* @param	boolean	This error signals stoppage of the upgrade process if true
	*/
	public function add_error($value = '', $code = '', $fatal = false)
	{
		$this->response['error'][] = array(
			'code'  => $code,
			'value' => $value,
			'fatal' => $fatal,
		);
	}

	/**
	* Add a message
	*
	* @param	string	Key of item
	* @param	string	Data of item to be output
	* @param	boolean	Replace previous message with this message, if it had $replace set as well..
	*/
	private function add_message($value = '', $code = 'STANDARD', $replace = false)
	{
		$this->response['message'][] = array(
			'code'    => $code,
			'value'   => $value,
			'replace' => $replace,
		);
	}


	/** This sets an option. It's for where we need to change an existing value
	 *
	 *	@param string
	 *	@param string //we actually don't currently use this parameter
	 *	@param string
	 *
	 ***/
	protected function set_option($varname, $grouptitle, $value)
	{
		include_once DIR . '/includes/adminfunctions_options.php';
		$values = array($varname => $value);
		save_settings($values);
	}

	/** This sets an option. It should rarely used. Its primary use is for temporarily
	* storing the version number from which this upgrade started. Any other use should be
	* carefully considered as to why you don't just put in the XML file.
	*
	*
	*	@param string
	*	@param string //we actually don't currently use this parameter
	*	@param string
	****/
	protected function set_new_option($varname, $grouptitle, $value, $datatype, $default_value = false, $optioncode = '', $product = 'vbulletin')
	{
		$row = $this->db->query_first("
			SELECT value
			FROM " . TABLE_PREFIX . "setting
			WHERE varname = '" . $this->db->escape_string($varname) . "';");

		if (!$row)
		{
			$sql = "REPLACE INTO " . TABLE_PREFIX . "setting
					(product, varname, grouptitle, value, datatype, optioncode, volatile " . ($default_value ? ',defaultvalue' : '') .
				")VALUES('" . $this->db->escape_string($product) . "', '" . $this->db->escape_string($varname) . "', '" . $this->db->escape_string($grouptitle) .
				"', '" . $this->db->escape_string($value) . "',
				'$datatype', '" . $this->db->escape_string($optioncode) . "', 1 "
				 . ($default_value ? ",'" . $this->db->escape_string($default_value) . "'" : '') .
				")";
			$this->db->query_write($sql);
		}
		include_once DIR . '/includes/adminfunctions_options.php';
		$values = array($varname => $value);
		if ($default_value)
		{
			$values[$varname]['default_value'] = $default_value;
		}

		save_settings($values);
	}

	/**
	* Log the current location of the upgrade
	*
	* @param	string	Upgrade Step
	* @param	int			Startat value for multi step steps
	* @param	bool		Process only the current version upgrade
	*/
	public function log_upgrade_step($step, $startat = 0, $only = false)
	{
		$complete = ($step == $this->stepcount);
		$perpage = 0;
		$insertstep = true;

		if ($complete)
		{
			$step = 0;
			if ($this->SHORT_VERSION == 'final' OR $only)
			{
				//This needs an index on 'script' added
				$this->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "upgradelog
					WHERE script IN ('final', 'vbblog', 'vbcms', 'skimlinks')
				");

				$insertstep = false;
			}
			else
			{
				require_once(DIR . '/includes/adminfunctions_template.php');
				if (is_newer_version($this->LONG_VERSION, $this->registry->options['templateversion']))
				{
					$this->db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . $this->LONG_VERSION . "' WHERE varname = 'templateversion'");
				}
				if (!defined('SKIPDB'))
				{
					build_options();
				}
				$this->registry->options['templateversion'] = $this->LONG_VERSION;
			}
		}

		if ($insertstep AND !defined('SKIPDB'))
		{
			// use time() not TIMENOW to actually time the script's execution
			/*insert query*/
			$this->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "upgradelog(script, steptitle, step, startat, perpage, dateline, only)
				VALUES (
					'" . $this->db->escape_string($this->SHORT_VERSION) . "',
					'',
					$step,
					$startat,
					$perpage,
					" . time() . ",
					" . intval($only) . "
			)");
		}
	}

	/**
	* Parse exception
	*
	* @param	string	error msg to parse
	*
	* @return	string
	*/
	protected function stop_exception($e)
	{
		$args = $e->getParams();
		require_once(DIR . '/includes/functions_misc.php');
		$message = fetch_phrase($args[0], 'error', '', false);

		if (sizeof($args) > 1)
		{
			$args[0] = $message;
			$message = call_user_func_array('construct_phrase', $args);
		}

		return $message;
	}

	/**
	* Converts a version number string into an array that can be parsed
	* to determine if which of several version strings is the newest.
	*
	* @param	string	Version string to parse
	*
	* @return	array	Array of 6 bits, in decreasing order of influence; a higher bit value is newer
	*/
	private function fetch_version_array($version)
	{
		// parse for a main and subversion
		if (preg_match('#^([a-z]+ )?([0-9\.]+)[\s-]*([a-z].*)$#i', trim($version), $match))
		{
			$main_version = $match[2];
			$sub_version = $match[3];
		}
		else
		{
			$main_version = $version;
			$sub_version = '';
		}

		$version_bits = explode('.', $main_version);

		// pad the main version to 4 parts (1.1.1.1)
		if (sizeof($version_bits) < 4)
		{
			for ($i = sizeof($version_bits); $i < 4; $i++)
			{
				$version_bits["$i"] = 0;
			}
		}

		// default sub-versions
		$version_bits[4] = 0; // for alpha, beta, rc, pl, etc
		$version_bits[5] = 0; // alpha, beta, etc number

		if (!empty($sub_version))
		{
			// match the sub-version
			if (preg_match('#^(A|ALPHA|B|BETA|G|GAMMA|RC|RELEASE CANDIDATE|GOLD|STABLE|FINAL|PL|PATCH LEVEL)\s*(\d*)\D*$#i', $sub_version, $match))
			{
				switch (strtoupper($match[1]))
				{
					case 'A':
					case 'ALPHA';
						$version_bits[4] = -4;
						break;

					case 'B':
					case 'BETA':
						$version_bits[4] = -3;
						break;

					case 'G':
					case 'GAMMA':
						$version_bits[4] = -2;
						break;

					case 'RC':
					case 'RELEASE CANDIDATE':
						$version_bits[4] = -1;
						break;

					case 'PL':
					case 'PATCH LEVEL';
						$version_bits[4] = 1;
						break;

					case 'GOLD':
					case 'STABLE':
					case 'FINAL':
					default:
						$version_bits[4] = 0;
						break;
				}

				$version_bits[5] = $match[2];
			}
		}

		// sanity check -- make sure each bit is an int
		for ($i = 0; $i <= 5; $i++)
		{
			$version_bits["$i"] = intval($version_bits["$i"]);
		}

		return $version_bits;
	}

	/**
	* Compares two version strings. Returns true if the first parameter is
	* newer than the second.
	*
	* @param	string	Version string; usually the latest version
	* @param	string	Version string; usually the current version
	*
	* @return	bool	True if the first argument is newer than the second
	*/
	public function is_newer_version($new_version_str, $cur_version_str)
	{
		// if they're the same, don't even bother
		if ($cur_version_str != $new_version_str)
		{
			$cur_version = $this->fetch_version_array($cur_version_str);
			$new_version = $this->fetch_version_array($new_version_str);

			// iterate parts
			for ($i = 0; $i <= 5; $i++)
			{
				if ($new_version["$i"] != $cur_version["$i"])
				{
					// true if newer is greater
					return ($new_version["$i"] > $cur_version["$i"]);
				}
			}
		}

		return false;
	}

	protected function install_suite()
	{
		$suite_products = array('vbblog', 'vbcms');
		foreach ($suite_products as $productid)
		{
			if (!file_exists(DIR . "/includes/xml/product-$productid.xml"))
			{
				return false;
			}
		}
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
