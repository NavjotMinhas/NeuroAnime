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
* Helper class for running a multi-page sitemap generation process.
*
* @package	vBulletin
*/
class vB_SiteMapRunner
{
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* The current sitemap runner session. Tracks progress across pages.
	*
	* @var	array
	*/
	protected $session = array();

	/**
	* Stores if the environment is ok for execution.
	*
	* @var	bool|null	Null = unknown, otherwise treat as value
	*/
	protected $environment_ok = null;

	/**
	* If the entire sitemap generation process is known to be finished.
	*
	* @var	bool
	*/
	public $is_finished = false;

	/**
	* Name of the written out filename
	*
	* @var	string
	*/
	public $written_filename = '';

	/**
	* Constructor. Automatically sets up the session.
	*
	* @param	vB_Registry	Registry object
	*/
	public function __construct(vB_Registry $registry)
	{
		$this->registry = $registry;
		$this->session = self::fetch_session_from_db($registry);
	}

	/**
	* Fetches the session info for this run from the database.
	*
	* @param	vB_Registry
	*
	* @return	array	Array of session info; new session is created if needed
	*/
	public static function fetch_session_from_db(vB_Registry $registry)
	{
		global $vbulletin, $vbphrase;

		$sitemap_status = $registry->db->query_first("SELECT text FROM " . TABLE_PREFIX . "adminutil WHERE title = 'sitemapsession'");
		if ($sitemap_status)
		{
			$session = unserialize($sitemap_status['text']);
		}

		if (!is_array($session))
		{
			$contenttypes = array();
			if (isset($vbulletin->products['vbblog']) AND $vbulletin->products['vbblog'])
			{
				$contenttypes['blog'] = 'blog';
			}
			$contenttypes[] = 'thread';
			$contenttypes[] = 'forum';
			if (isset($vbulletin->products['vbcms']) AND $vbulletin->products['vbcms'])
			{
				$contenttypes['cms'] = 'cms';
			}

			reset($contenttypes);
			$session = array(
				'types' => $contenttypes,
				'current_content' => current($contenttypes),
				'startat' => 0,
				'state' => 'start'
			);
		}

		($hook = vBulletinHook::fetch_hook('sitemap_add_content_types')) ? eval($hook) : false;

		return $session;
	}

	/**
	* Fetches the current, "in progress" session. This may differ from the state
	* in the DB if changes are pending.
	*
	* @return	array
	*/
	public function fetch_session()
	{
		return $this->session;
	}

	/**
	* Check that the environment is ok for building the sitemap.
	*
	* @return	array	Array of status information. Check 'error' key.
	*/
	public function check_environment()
	{
		$status = $this->check_environment_internal();
		$this->environment_ok = ($status['error'] != '');

		return $status;
	}

	/**
	* Internal function for checking the environment. This is where specific checks should be run.
	*
	* @return	array	Array of status info. Check 'error' key.
	*/
	protected function check_environment_internal()
	{
		$status = array(
			'error' => '',
			'loggable' => false
		);

		if ($this->session['state'] == 'failed')
		{
			$status['error'] = $this->session['failure_reason'];
			$status['loggable'] = false; // should be logged when it occurs, not each "hit"
		}

		if (!$this->registry->options['sitemap_path'] OR !is_writable($this->registry->options['sitemap_path']))
		{
			$status['error'] = 'sitemap_path_not_writable';
			// only log on the first occurance in a session
			$status['loggable'] = ($this->session['state'] == 'start');
		}

		return $status;
	}

	/**
	* Generates one "page" worth of a sitemap and prepares for the next page or finalizes.
	*
	* @return	bool	True on success
	*/
	public function generate()
	{
		if ($this->environment_ok === null)
		{
			$status = $this->check_environment();
			if ($status['error'])
			{
				return false;
			}
		}

		$first_page = ($this->session['state'] == 'start');

		$this->set_state();

		$sitemap_obj = self::get_content_handler($this->session['current_content'], $this->registry);
		if (!$sitemap_obj)
		{
			$this->trigger_failure('invalid_sitemap_content_type');
			return false;
		}

		if ($first_page)
		{
			$sitemap_obj->remove_sitemaps();
		}

		$items_processed = $sitemap_obj->generate_sitemap($this->session['startat'], $this->registry->options['sitemap_url_perpage']);
		if (!$this->write_sitemap($sitemap_obj))
		{
			$this->trigger_failure('sitemap_creation_failed');
			return false;
		}

		$this->is_finished = $this->is_finished($sitemap_obj);

		if ($this->is_finished)
		{
			return $this->finalize($sitemap_obj);
		}
		else
		{
			return $this->prepare_next_page();
		}
	}

	/**
	* Sets the session state at the beginning of generating a "page".
	*/
	protected function set_state()
	{
	}

	/**
	* Fetches the handler class for a particular type of content.
	*
	* @return	vB_Sitemap	Subclass of vB_Sitemap
	*/
	public static function get_content_handler($type, vB_Registry $registry)
	{
		if (empty($type))
		{
			return false;
		}

		$classname = 'vB_SiteMap_' . ucfirst(strtolower($type));
		if (class_exists($classname, false))
		{
			return new $classname($registry);
		}

		return false;
	}

	/**
	* Writes out the sitemap file for the current content.
	*
	* @param	vB_Sitemap	Current sitemap object
	*
	* @return	boolean
	*/
	protected function write_sitemap($sitemap_obj)
	{
		$filename_suffix = $this->session['current_content'] . '_' . count($this->session['sitemaps']);
		$this->written_filename = "vbulletin_sitemap_{$filename_suffix}.xml";

		if ($filename = $sitemap_obj->create_sitemap($filename_suffix))
		{
			$this->session['sitemaps'][] = array('loc' => $filename, 'lastmod' => TIMENOW);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Determines if the sitemap generation is finished. This manipulates the session
	* and helps prepare for the next page.
	*
	* @param	vB_Sitemap	Current sitemap object
	*
	* @return	boolean
	*/
	protected function is_finished($sitemap_obj)
	{
		$has_more = $sitemap_obj->has_more();
		if ($has_more === null)
		{
			// has_more wasn't definitive; use default handler
			$has_more = ($sitemap_obj->get_pagecount() == $this->registry->options['sitemap_url_perpage']);
		}

		if ($has_more)
		{
			$this->session['startat'] = $sitemap_obj->get_lastid() + 1;
			return false;
		}
		else
		{
			$this->step_content_type_forward();
			return (count($this->session['types']) == 0);
		}
	}

	/**
	* Moves forward to the next content type.
	*/
	protected function step_content_type_forward()
	{
		array_shift($this->session['types']);

		$this->session['current_content'] = reset($this->session['types']);
		$this->session['startat'] = 0;
	}

	/**
	* Finalizes the sitemap build by writing an index and contacting the
	* selected search engines.
	*
	* @param	vB_Sitemap	Sitemap object
	*
	* @return	boolean
	*/
	protected function finalize($sitemap_obj)
	{
		global $vbphrase;

		if ($sitemap_obj)
		{
			// Ensure all sitemaps together (possibly existing sitemap index file)
			$sitemap_obj->set_sitemap_index(array_merge($sitemap_obj->get_sitemap_index(), $this->session['sitemaps']));

			// Create the sitemap index file and write it out
			if (!$sitemap_obj->create_sitemap_index())
			{
				$this->trigger_failure('sitemap_creation_failed');
				return false;
			}

			$sitemap_obj->ping_search_engines();
		}

		$this->registry->db->query("DELETE FROM " . TABLE_PREFIX . "adminutil WHERE title = 'sitemapsession'");

		return true;
	}

	/**
	* Prepares for the next page. This is only called when the build isn't finished.
	*
	* @return	boolean
	*/
	protected function prepare_next_page()
	{
		$this->write_session();

		return true;
	}

	/**
	* Writes the sitemap session out. Only needed when not finished.
	*/
	protected function write_session()
	{
		$this->registry->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "adminutil
				(title, text)
			VALUES
				('sitemapsession', '" . $this->registry->db->escape_string(serialize($this->session)) . "')
		");
	}

	/**
	* Triggers a failure event. This prevents the sitemap from being built
	* any further until the error is resolved. Calling this updates
	* the sitemap session automatically.
	*
	* @param	string	Phrase key (in "error messages") that describes the error
	*/
	protected function trigger_failure($error_phrase)
	{
		$this->session['state'] = 'failed';
		$this->session['failure_reason'] = $error_phrase;
		$this->write_session();
	}
}

/**
* Sitemap runner that uses cron-specific checks and triggers.
*
* @package	vBulletin
*/
class vB_SiteMapRunner_Cron extends vB_SiteMapRunner
{
	/**
	* Information about the cron item that triggers the sitemap builds.
	*
	* @var	array
	*/
	protected $cron_item = array();

	/**
	* Information about the cron-triggered sitemap builds (particularly last build time).
	*
	* @var	array
	*/
	protected $build_info = array();

	/**
	* Constructor. Fetches session (via parent) and populates build_info.
	*
	* @param	vB_Registry
	*/
	public function __construct(vB_Registry $registry)
	{
		require_once(DIR . '/includes/functions_cron.php');

		parent::__construct($registry);

		if ($build_info = $registry->db->query_first("
			SELECT text
			FROM " . TABLE_PREFIX . "adminutil
			WHERE title = 'sitemapcronbuild'
		"))
		{
			$this->build_info = unserialize($build_info['text']);
		}
	}

	/**
	* Sets the cron item property.
	*
	* @param	array	Cron item info
	*/
	public function set_cron_item(array $cron_item)
	{
		$this->cron_item = $cron_item;
	}

	/**
	* Internal function for checking the environment. Checks cron-specific items
	* like being enabled and the last build time.
	*
	* @return	array	Array of status info. Check 'error' key.
	*/
	protected function check_environment_internal()
	{
		$status = parent::check_environment_internal();

		if ($this->session['state'] == 'running_admincp')
		{
			$status['error'] = 'sitemap_currently_generated_admincp';
			$status['loggable'] = false;
		}

		if (!$this->registry->options['sitemap_cron_enable'])
		{
			$status['error'] = 'sitemap_cron_option_not_enabled';
			$status['loggable']	= false;
		}

		if ($this->session['state'] == 'start'
			AND $this->build_info
			AND $this->build_info['last_build'] > (TIMENOW - $this->registry->options['sitemap_cron_frequency'] * 86400))
		{
			$status['error'] = 'sitemap_cron_build_not_scheduled';
			$status['loggable']	= false;
		}

		return $status;
	}

	/**
	* Sets the session state to running and updates the last build time if necessary.
	*/
	protected function set_state()
	{
		if ($this->session['state'] == 'start')
		{
			$this->build_info['last_build'] = TIMENOW;

			$this->registry->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "adminutil
					(title, text)
				VALUES
					('sitemapcronbuild',
					'" . $this->registry->db->escape_string(serialize($this->build_info)) . "')
			");
		}
		$this->session['state'] = 'running_cron';
	}

	/**
	* Prepares for the next "page" of building. Handles parent functions and
	* updates the cron to run almost immediately (to allow a multi-page build
	* to be completed quickly.
	*
	* @return	boolean
	*/
	protected function prepare_next_page()
	{
		if (!parent::prepare_next_page())
		{
			return false;
		}

		if ($this->cron_item)
		{
			// if we have more to do, run the next step in approximately a minute
			$this->registry->db->query_write("UPDATE " . TABLE_PREFIX . "cron SET nextrun = " . (TIMENOW + 60) . " WHERE cronid = " . intval($this->cron_item['cronid']));
			build_cron_next_run(TIMENOW + 60);
		}

		return true;
	}
}

/**
* Admin CP-based sitemap build helper.
*
* @package	vBulletin
*/
class vB_SiteMapRunner_Admin extends vB_SiteMapRunner
{
	/**
	* Internal function for checking the environment. Checks ACP-specific items
	* like whether the sitemap is being built by cron.
	*
	* @return	array	Array of status info. Check 'error' key.
	*/
	protected function check_environment_internal()
	{
		$status = parent::check_environment_internal();

		if ($this->session['state'] == 'running_cron')
		{
			$status['error'] = 'sitemap_running_cron';
		}

		return $status;
	}

	/**
	* Sets session state to running.
	*/
	protected function set_state()
	{
		$this->session['state'] = 'running_admincp';
	}
}

/**
* Abstract class to construct sitemap files and the index file. Must be subclassed for specific content types.
*
* @package	vBulletin
*/
abstract class vB_SiteMap
{
	/**
	* The last id of the content, for per_page
	*
	* @var	int
	*/
	protected $lastid = 0;

	/**
	* An array of custom Forum priorities forumid => priority
	*
	* @var	array
	*/
	protected $forum_custom_priority = array();

	/**
	 * An array of custom priorities contenttype => forumid => priority
	 *
	 * @var	array
	 */
	protected $custom_priority = array();

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* The vBulletin database object
	*
	* @var	vB_Database
	*/
	protected $dbobject = null;

	/**
	* A vB_XML_Parser database object
	*
	* @var	vB_XML_Parser
	*/
	protected $xmlobject = null;

	/**
	* String to save the content of the sitemap while being generated before being written out
	*
	* @var	string
	*/
	protected $content = '';

	/**
	* Counter for the numbers of URLs added to the current sitemap content
	*
	* @var	int
	*/
	protected $pagecount = 0;

	/**
	* Determines if there is more of this content type to process.
	*
	* @var	boolean|null	Null is unknown, boolean otherwise
	*/
	protected $has_more = null;

	/**
	* Array to store any errors encountered while building data
	*
	* @var	array
	*/
	protected $errors = array();

	/**
	* Array to store currently generated (or listed) site maps. Used to generate sitemap index file (the master one). ['loc'] && ['lastmod']
	*
	* @var	array
	*/
	protected $sitemap_index = array();

	/**
	* Default name for sitemap_index file
	*
	* @var	string
	*/
	private $sitemap_index_filename = 'vbulletin_sitemap_index';


	/**
	* Default name for sitemap files, which is prepended by the sitemap file count
	*
	* @var	string
	*/
	private $sitemap_filename_prefix = 'vbulletin_sitemap_';

	const FLAG_PING_GOOGLE      = 0x1;
	const FLAG_PING_LIVE_SEARCH = 0x2;
	const FLAG_PING_YAHOO       = 0x4;
	const FLAG_PING_ASK         = 0x8;
	const FLAG_PING_MOREOVER    = 0x10;

	/**
	* Array of search engine urls' for sitemap call back, populated with defaults from options
	*
	* @var 	array
	*/
	public $search_engines = array(
		self::FLAG_PING_GOOGLE      => 'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
		self::FLAG_PING_LIVE_SEARCH => 'http://webmaster.live.com/ping.aspx?siteMap=',
		self::FLAG_PING_YAHOO       => 'http://search.yahooapis.com/SiteExplorerService/V1/ping?sitemap=',
		self::FLAG_PING_ASK         => 'http://submissions.ask.com/ping?sitemap=',
		self::FLAG_PING_MOREOVER    => 'http://api.moreover.com/ping?u=',
	);


	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry		Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	*/
	function __construct(vB_Registry $registry)
	{
		$this->dbobject = $registry->db;
		$this->registry = $registry;
	}

	### abstract ###

	/**
	* This function will generate the actual sitemap content.
	*/
	abstract protected function generate_sitemap();

	### Main ###

	/**
	* Gets the current data that will be used to build the sitemap index
	*
	* @return	array
	*/
	function get_sitemap_index()
	{
		return $this->sitemap_index;
	}

	/**
	* Sets the current data that will be used to build the sitemap index
	*
	* @param	array
	*/
	function set_sitemap_index($value)
	{
		$this->sitemap_index = $value;
	}

	/**
	* Returns indicator for whether there's more of this content to be processed.
	* Useful for the case where there is exactly the "per page" pieces of content.
	*
	* @return	boolean|null	Null is unknown, booling otherwise
	*/
	function has_more()
	{
		return $this->has_more;
	}

	/**
	 * Accessor - place holder
	 *
	 * @param 	int		The forum id to retrive custom priority for
	 * @return 	mixed	False or int
	 */
	public function get_forum_custom_priority($id)
	{
		if (isset($this->forum_custom_priority[$id]))
		{
			return $this->forum_custom_priority[$id];
		}

		return false;
	}

	/**
	* Returns the effective priority for a forum
	*
	* @param	integer	Forum ID
	*
	* @return	float	Usable priority
	*/
	public function get_effective_forum_priority($forumid)
	{
		return (isset($this->forum_custom_priority[$forumid])
			? $this->forum_custom_priority[$forumid]
			: $this->registry->options['sitemap_priority']
		);
	}

	/**
	 * Returns the effective priority for a non-forum type
	 *
	 * @param 	string	content type
	 * @param	integer	content ID
	 *
	 * @return	float	Usable priority
	 */
	public function get_effective_priority($contenttypeid, $contentid)
	{

		return(isset($this->custom_priority[$contenttypeid][$contentid])
			AND isset($this->custom_priority[$contenttypeid][$contentid]['priority'])
			AND ($this->custom_priority[$contenttypeid][$contentid]['priority'] !== false))
			? $this->custom_priority[$contenttypeid][$contentid]['priority']
			: $this->registry->options['sitemap_priority'];
	}


	/**
	 * Accessor
	 *
	 * @return int
	 */
	function get_pagecount()
	{
		return $this->pagecount;
	}


	/**
	 * Accessor
	 *
	 * @return int
	 */
	function get_lastid()
	{
		return $this->lastid;
	}


	/**
	* Write out the sitemap index file using sitemap file refrences in $this->sitemap_index
	*
	* @param	array
	*
	* @return	null		cron log
	*/
	final function create_sitemap_index()
	{
		$content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		foreach ($this->sitemap_index AS $sitemap)
		{
			$content .= "\n" . '<sitemap>';
			$content .= "\n\t" . '<loc>' . htmlspecialchars($this->registry->options['bburl'] . '/xmlsitemap.php?fn=' . urlencode($sitemap['loc'])) . '</loc>';
			$content .= "\n\t" . '<lastmod>' . gmdate(DATE_W3C, $sitemap['lastmod']) . '</lastmod>';
			$content .= "\n" . '</sitemap>';
		}

		$content .= "\n" . '</sitemapindex>';

		// Compress and add extension
		if (function_exists('gzencode'))
		{
			$content = gzencode($content);
			$output_filename = $this->sitemap_index_filename . '.xml.gz';
		}
		else
		{
			$output_filename = $this->sitemap_index_filename . '.xml';
		}

		// Try to write file
		if ($fp = @fopen($this->registry->options['sitemap_path'] . '/' . $output_filename, 'w'))
		{
			fwrite($fp, $content);
			fclose($fp);
			return true;
		}
		else
		{
			$this->errors[] = 'Error writing : ' . $this->registry->options['sitemap_path'] . '/' . $output_filename;
			return false;
		}
	}


	/**
	* Build the actual file for the sitemap
	*
	* @return boolean		completion state
	*/
	final function create_sitemap($filename)
	{
		$this->content =
			'<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
			'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
				$this->content .
			'</urlset>';

		// Next file name
		$new_file = $this->sitemap_filename_prefix . $filename;

		// Compress and add extension
		if (function_exists('gzencode'))
		{
			$content = gzencode($this->content);
			$new_file .= '.xml.gz';
		}
		else
		{
			$content = $this->content;
			$new_file .= '.xml';
		}

		// Create it and write it all out
		if ($fp = @fopen($this->registry->options['sitemap_path'] . '/' . $new_file, 'w'))
		{
			fwrite($fp, $content);
			fclose($fp);
			return $new_file;
		}
		else
		{
			$this->errors[] = 'Error writing : ' . $this->registry->options['sitemap_path'] . '/' . $new_file;
			return false;
		}
	}


	/**
	* Create a url XML text block for one URL
	*
	* @param	string		The URL (not encoded) to add to the sitemap
	* @param 	int			The unix timestamp of the last modifictaion time of the data in UTC
	* @param 	double		The priority of the data from 0.1 to 1.0
	* @param 	boolean		Enable formatting of the output
	*
	* @return	string		Formatted escaped <url> wrapped text
	*/
	protected function url_block($url, $lastmod, $pri = false, $formatting = false)
	{
		$l = "\n" . ($formatting ? "\t\t" : '');

		// Start block
		$data .= "\n" . ($formatting ? "\t" : '') . '<url>';
		
		$url_info = parse_url($url);
		if(empty($url_info['scheme']) AND empty($url_info['host']))
		{
			$url = $this->registry->options['bburl'] . '/' . $url;
		}
		
		$data .= $l . '<loc>' . htmlspecialchars_uni(substr($url, 0, 2048)) . '</loc>';
		$data .= $l . '<lastmod>' . htmlspecialchars_uni(gmdate(DATE_W3C, $lastmod)) . '</lastmod>';

		$changefreq = '';

		if ($lastmod + 600 >= TIMENOW) // 10 mins
		{
			$changefreq = 'always';
		}
		else if ($lastmod + 3600 >= TIMENOW) // 1 hour
		{
			$changefreq = 'hourly';
		}
		else if ($lastmod + 86400 >= TIMENOW) // 1 day
		{
			$changefreq = 'daily';
		}
		else if ($lastmod + 604800 >= TIMENOW) // 1 week
		{
			$changefreq = 'weekly';
		}
		else if ($lastmod + 2629743 >= TIMENOW) // 1 month
		{
			$changefreq = 'monthly';
		}
		else     // Yearly, for yearly and in place of never
		{
			$changefreq = 'yearly';
		}

		$data .= $l .'<changefreq>' . $changefreq . '</changefreq>';

		if ($pri !== false)
		{
			$data .= $l .'<priority>' . floatval($pri) . '</priority>';
		}

		$data .= "\n" . ($formatting ? "\t" : '')  . '</url>';

		return $data;
	}


	/**
	* Delete all sitemaps named : '*_sitemap.xml' '*_sitemap.xml.gz' 'sitemap_index.xml'
	*
	* @return	boolean		FALSE on any fails
	*/
	final function remove_sitemaps()
	{
		$path = $this->registry->options['sitemap_path'];
		$success = true;

		$all = scandir($path);
		foreach ($all AS $filename)
		{
			$is_index_file = (
				$filename == $this->sitemap_index_filename . '.xml'
				OR $filename == $this->sitemap_index_filename . '.xml.gz'
			);

			$is_sitemap_file = (
				substr($filename, 0, strlen($this->sitemap_filename_prefix)) == $this->sitemap_filename_prefix
				AND (substr($filename, -4) == '.xml' OR substr($filename, -7) == '.xml.gz')
			);

			if ($is_index_file OR $is_sitemap_file)
			{
				if (!@unlink("$path/$filename"))
				{
					$this->errors[] = "No Permission to delete sitemap : {$path}/{$filename}";
					$success = false;
				}

			}
		}

		return $success;
	}


	/**
	* Ping the search engines
	* @param 	object		A vB_vURL object
	*
	* @return	none		A blind call, no return currently parsed
	*/
	public function ping_search_engines()
	{
		if (!$this->registry->options['sitemap_se_submit'])
		{
			// value of 0 in bitfield means all search engines are disabled
			return;
		}

		require_once(DIR . '/includes/class_vurl.php');
		$vurl = new vB_vURL($this->registry);
		$vurl->set_option(VURL_HEADER, true);
		$vurl->set_option(VURL_RETURNTRANSFER, true);

		$map_url = urlencode($this->registry->options['bburl'] . "/xmlsitemap.php");

		foreach ($this->search_engines as $bit_option => $callback_url)
		{
			if ($this->registry->options['sitemap_se_submit'] & $bit_option)
			{
				$vurl->set_option(VURL_URL, $callback_url . $map_url);
				$vurl->exec();
			}
		}
	}

	/**
	* Builds the very basic array for the guest viewable forums
	*
	* @param 	bool	Whether the permission to view threads should be checked
	*/
	public static function get_allowed_forums($check_thread_view = true)
	{
		global $vbulletin;

		$guestuser = array(
			'userid'      => 0,
			'usergroupid' => 1,
		);

		cache_permissions($guestuser);

		if (!($guestuser['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			return array();
		}

		$viewable_forums = array();

		foreach ($vbulletin->forumcache AS $forum)
		{
			$forumperms = $guestuser['forumpermissions']["$forum[forumid]"];

			if (!$forum['password']
				AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canview']
				AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']
				AND (!$check_thread_view OR $forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
			)
			{
				$viewable_forums[] = intval($forum['forumid']);
			}
		}

		return $viewable_forums;
	}


	/**
	* Builds the forum priority array
	*
	*/
	protected function set_forum_priorities()
	{
		$forum_priorities = $this->dbobject->query_read("SELECT sourceid, prioritylevel FROM " . TABLE_PREFIX . "contentpriority WHERE contenttypeid = 'forum'");

		while ($f_pri = $this->dbobject->fetch_array($forum_priorities))
		{
			$this->forum_custom_priority["$f_pri[sourceid]"] = $f_pri['prioritylevel'];
		}
	}

	/**
	 * Builds the priority array for an arbitrary content type
	 *
	 */
	protected function set_priorities($contenttype)
	{
		$forum_priorities = $this->dbobject->query_read("SELECT sourceid, prioritylevel FROM " . TABLE_PREFIX . "contentpriority WHERE contenttypeid = '$contenttype'");

		if (! isset($this->custom_priority[$contenttype]))
		{
			$this->custom_priority[$contenttype] = array();
		}

		while ($f_pri = $this->dbobject->fetch_array($forum_priorities))
		{
			if (! isset($this->custom_priority[$contenttype][$f_pri['sourceid']]))
			{
				$this->custom_priority[$contenttype][$f_pri['sourceid']] = array();
			}
			$this->custom_priority[$contenttype][$f_pri['sourceid']]['priority'] = $f_pri['prioritylevel'];
		}

	}

	/**
	 * returns an array of priorities
	 *
	 * @param	string	contenttype
	 *
	 * @return array		$key => priority
	 */
	public function get_priorities($contenttype)
	{
		if (isset($this->custom_priority[$contenttype]))
		{
			return $this->custom_priority[$contenttype];
		}
		return array();
	}

}

/**
* Specific class for generating forum-related sitemaps
*
* @package	vBulletin
*/
class vB_SiteMap_Forum extends vB_SiteMap
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry		Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param 	vB_XML_Parser 	Instance of the vBulletin XML parser.
	*/
	function __construct(vB_Registry $registry, vB_XML_Parser $xml_handler = null)
	{
		parent::__construct($registry, $xml_handler);

		$this->set_forum_priorities();

	}

	/**
	* Adds the URLs to $this->content
	*
	* @param 	int		forumdid to start at
	* @param 	int		perpage limit defaults to 30000
	*/
	public function generate_sitemap($startat = 0, $perpage = 30000)
	{
		$viewable_forums = vB_Sitemap::get_allowed_forums(false);
		if (!$viewable_forums)
		{
			return $this->pagecount;
		}

		$forums = $this->dbobject->query_read("
			SELECT forumid, title, title_clean, lastpost
			FROM " . TABLE_PREFIX . "forum
			WHERE forumid IN (" . implode(',', $viewable_forums) . ")
				AND forumid >= " . intval($startat) . "
			ORDER BY forumid
			LIMIT " . intval($perpage + 1) // for has_more check
		);

		$this->has_more = false;

		while ($forum = $this->dbobject->fetch_array($forums))
		{
			if ($this->pagecount >= $perpage)
			{
				$this->has_more = true;
				break;
			}

			$this->pagecount++;
			$this->lastid = $forum['forumid'];

			$this->content .= $this->url_block(
				fetch_seo_url('forum|nosession', $forum),
				$forum['lastpost'],
				$this->get_effective_forum_priority($forum['forumid'])
			);
		}

		// Return the amout done
		return $this->pagecount;
	}
}

/**
* Specific class for generating thread-related sitemaps
*
* @package	vBulletin
*/
class vB_SiteMap_Thread extends vB_SiteMap
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry		Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param 	vB_XML_Parser 	Instance of the vBulletin XML parser.
	*/
	function __construct(vB_Registry $registry, vB_XML_Parser $xml_handler = null)
	{
		parent::__construct($registry, $xml_handler);

		$this->set_forum_priorities();
	}

	/**
	* Adds the thread URLs to $this->content
	*
	* @param 	int		threadid to start at
	* @param 	int		perpage limit defaults to 30000
	*/
	public function generate_sitemap($startat = 0, $perpage = 30000)
	{
		$viewable_forums = vB_Sitemap::get_allowed_forums(true);
		if (!$viewable_forums)
		{
			return $this->pagecount;
		}

		require_once(DIR . '/includes/functions_bigthree.php');
		$coventry = fetch_coventry('array', true);

		$threads = $this->dbobject->query_read("
			SELECT threadid, forumid, title, lastpost
			FROM " . TABLE_PREFIX . "thread
			WHERE forumid IN (" . implode(',', $viewable_forums) . ")
				AND visible = 1
				AND open <> 10
				" . ($coventry ? "AND postuserid NOT IN (" . implode(',', $coventry) . ")" : '') . "
				AND threadid >= " . intval($startat) . "
			ORDER BY threadid
			LIMIT " . intval($perpage + 1) // + 1 for has_more check
		);

		$this->has_more = false;

		while ($thread = $this->dbobject->fetch_array($threads))
		{
			if ($this->pagecount >= $perpage)
			{
				$this->has_more = true;
				break;
			}

			$this->pagecount++;
			$this->lastid = $thread['threadid'];

			$this->content .= $this->url_block(
				fetch_seo_url('thread|nosession', $thread),
				$thread['lastpost'],
				$this->get_effective_forum_priority($thread['forumid'])
			);
		}

		return $this->pagecount;
	}
}

/**
 * Specific class for generating forum-related sitemaps
 *
 * @package	vBulletin
 */
class vB_SiteMap_Cms extends vB_SiteMap
{
	private $nodes = false;
	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry		Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param 	vB_XML_Parser 	Instance of the vBulletin XML parser.
	 */
	function __construct(vB_Registry $registry, vB_XML_Parser $xml_handler = null)
	{
		parent::__construct($registry, $xml_handler);

		$this->load_data();

	}

	/**
	 * Adds the CMS URLs to $this->content
	 *
	 * @param 	int		forumdid to start at
	 * @param 	int		perpage limit defaults to 30000
	 */
	public function generate_sitemap($startat = 0, $perpage = 30000)
	{

		global $config;
		//make sure we have the node information
		if (! $this->nodes)
		{
			$this->nodes = vBCms_ContentManager::getPublicContent($startat, $perpage);
		}

		$this->has_more = false;

		$route = vB_Route::create('vBCms_Route_Content');
		while($node = next($this->nodes))
		{

			$this->pagecount++;
			$this->lastid = $node['nodeid'];
			$route->node = $node['nodeid'] . (empty($node['url']) ? '' : '-' . $node['url']);
			$rawurl = $route->getCurrentURL();
			$pageurl = str_replace( '/' . vB::$vbulletin->config['Misc']['admincpdir'] . '/' , '/' , $rawurl);
			$priority = $this->get_effective_priority('cms', $node['sectionid']);
			$this->content .= $this->url_block(
				$pageurl,
				$node['publishdate'],
				$priority
			);

			if ($this->pagecount >= $perpage)
			{
				$this->has_more = true;
				break;
			}
		}

		// Return the amout done
		return $this->pagecount;
	}

	/**
	 * load the existing data
	 *
	 */
	private function load_data()
	{
		//See if we need to bootstrap
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();

		$sections = vBCms_ContentManager::getSections();
		$perms = vBCMS_Permissions::getPerms(0);
		$this->custom_priority['cms'] = array();
		$level = array();

		foreach ($sections as $nodeid => $section)
		{
			if ((!$section['hidden']) AND (in_array($section['permissionsfrom'], $perms['canview'])))
			{
				$section['priority'] = false;
				$this->custom_priority['cms'][$section['nodeid']] = $section;
			}
		}

		$this->set_priorities('cms');

	}
}

/**
 * Specific class for generating forum-related sitemaps
 *
 * @package	vBulletin
 */
class vB_SiteMap_Blog extends vB_SiteMap
{
	private $blogs = false;

	private $defaults = array
	(
	'default' => 0.4,
	'age_pts' => 0.02,
	'age_max' => 0.2,
	'c_age_pts' =>  0.02,
	'c_age_max' => 0.2,
	'comm_pts' =>  0.02,
	'comm_max' => 0.2
	);
	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry		Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param 	vB_XML_Parser 	Instance of the vBulletin XML parser.
	 */
	function __construct(vB_Registry $registry, vB_XML_Parser $xml_handler = null)
	{
		parent::__construct($registry, $xml_handler);

		$this->load_data();

	}
	/**
	 * Adds the blog URLs to $this->content
	 *
	 * @param 	int		forumdid to start at
	 * @param 	int		perpage limit defaults to 30000
	 */
	public function generate_sitemap($startat = 0, $perpage = 30000)
	{

		//See if we need to bootstrap
		require_once(DIR . '/includes/blog_functions_search.php');
		require_once(DIR . '/includes/functions.php');
		$guestuser = array(
			'userid'      => 0,
			'usergroupid' => 0,
		);
		cache_permissions($guestuser, false);
		$perms = build_blog_permissions_query($guestuser);

		$this->blogs = array();
		$sql = "SELECT blog.blogid, blog.title, blog.userid, blog.dateline,
			blog.lastcomment, blog.comments_visible, count(bt.blogtextid) AS qty
		 	FROM " . TABLE_PREFIX . "blog AS blog
		 	LEFT JOIN " . TABLE_PREFIX . "blog_text AS bt ON blog.blogid = bt.blogid
			" . $perms['join'] . "
			WHERE " . $perms['where'] . "
			GROUP BY blog.blogid, blog.title, blog.userid, blog.dateline,
			blog.lastcomment, blog.comments_visible LIMIT $startat, $perpage";

		$rst = $this->registry->db->query_read($sql);
		$authorkeys = array_keys($this->custom_priority['blog']['authors']);

		while($blog = $this->registry->db->fetch_array($rst))
		{
			$this->pagecount++;
			$this->lastid = $blog['blogid'];

			//get the two ages- post and last comment, and the author points
			$postmonths = (intval($blog['lastcomment'])) ?
				floor((TIMENOW - $blog['lastcomment'])/(30*86400)) : 0;
			$agemonths = (intval($blog['dateline'])) ?
				floor((TIMENOW - $blog['dateline'])/(30*86400)) : 0;
			$commentmult = (intval($blog['qty']) AND intval($blog['comments_visible'])) ?
				floor($blog['qty']/10)  : 0;
			$authpoints = (in_array($blog['userid'], $authorkeys )) ?
				floatval($this->custom_priority['blog']['authors'][$blog['userid']]) : 0;

			//and calculate the rating
			$priority = $this->custom_priority['blog']['default']
				- min($postmonths * $this->custom_priority['blog']['age_pts'], $this->custom_priority['blog']['age_max'])
				- min($agemonths * $this->custom_priority['blog']['c_age_pts'], $this->custom_priority['blog']['c_age_max'])
				+ min($commentmult * $this->custom_priority['blog']['comm_pts'], $this->custom_priority['blog']['comm_max']);
			//We want a two-digit number between 0 and 1
			$priority = max(min($priority, 1), 0);
			$priority = round($priority, 2);
			//and the url
			$url = fetch_seo_url('entry', array('blogid' => $blog['blogid'],
				'page' => 1 , 'blogtitle' =>$blog['title'], 'title' =>$blog['title'],  'id' => $blog['blogid']), $pageinfo);

			$this->content .= $this->url_block(
				$registry->options['bburl'] . $url,
				$blog['lastcomment'],
				$priority
			);

			if ($this->pagecount >= $perpage)
			{
				$this->has_more = true;
				break;
			}
		}
		// Return the amout done
		return $this->pagecount;
	}

	/**
	 * load the existing data
	 *
	 */
	public function load_data()
	{
		$this->custom_priority['blog'] = $this->defaults;
		$authors = array();
		if ($rst = $this->registry->db->query_read("SELECT name, value, weight from " . TABLE_PREFIX .
			"blog_sitemapconf" ))
		{
			$weights = array();
			while($setting = $this->registry->db->fetch_array($rst))
			{
				if ($setting['name'] == 'author')
				{
					$weights[$setting['value']] = $setting['weight'];
				}
				else
				{
					$this->custom_priority['blog'][$setting['name']] = $setting['weight'];
				}
			}

			if (!empty($weights))
			{
				$sql = "SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid in ("
					. implode(',', array_keys($weights)) . ")";

				if ($rst = $this->registry->db->query_read($sql))
				{
					while($author = $this->registry->db->fetch_array($rst))
					{
						$authors[$author['userid']] = array('name' => $author['username'],
							'weight' => $weights[$author['userid']]);
					}
				}
			}
		}

		$this->custom_priority['blog']['authors'] = $authors;
	}


	/**
	 * save the configuration from the form submission
	 *
	 */
	public function save_data()
	{
		$this->registry->input->clean_array_gpc('p', array(
			'default' => TYPE_NUM,
			'newauthor' => TYPE_STR,
			'newauthor_pts' => TYPE_NUM,
			'age_pts' => TYPE_NUM,
			'age_max' => TYPE_NUM,
			'c_age_pts' => TYPE_NUM,
			'c_age_max' => TYPE_NUM,
			'comm_pts' => TYPE_NUM,
			'comm_max' => TYPE_NUM,
		));

		//First save the basic data
		foreach(array_keys($this->defaults) AS $key)
		{
			if ($this->registry->GPC_exists[$key] AND is_numeric($this->registry->GPC[$key]))
			{
				$sql = "REPLACE INTO " . TABLE_PREFIX . "blog_sitemapconf(name, value, weight) values('$key' , '', " .
					$this->registry->GPC[$key] . ")";
				$this->registry->db->query_write($sql);
			}
		}

		//Now let's update or delete authors whose information was changed or deleted
		foreach ($this->custom_priority['blog']['authors'] as $userid => $authorinfo)
		{
			if (isset($_REQUEST["del_author_$userid"]))
			{
				$sql = "DELETE FROM " . TABLE_PREFIX . "blog_sitemapconf WHERE name = 'author'
				AND value= '$userid'";

				$this->registry->db->query_write($sql);
			}
			else
			{
				$key = "author_pts_$userid";
				$this->registry->input->clean_array_gpc('p', array(
					$key => TYPE_NUM
				));

				//Check to see if we should change
				if ($this->registry->GPC_exists[$key] AND is_numeric($this->registry->GPC[$key])
					AND (round(floatval($this->registry->GPC[$key]),3) != round(floatval($authorinfo['weight']), 3))
					)
				{
					$sql = "UPDATE " . TABLE_PREFIX . "blog_sitemapconf SET weight = "
					. round(floatval($this->registry->GPC[$key]),3) . " WHERE name = 'author'
					AND value= '$userid';" ;
						$this->registry->db->query_write($sql);
				}
			}
		}
		if ($this->registry->GPC_exists['newauthor'] AND !empty($this->registry->GPC['newauthor'])
			AND ($this->registry->GPC_exists['newauthor_pts']))
		{
			//see if this is a value name, and if so get the userid
			$record = $this->registry->db->query_first($sql = "SELECT userid FROM " . TABLE_PREFIX .
				"user WHERE username = '" . $this->registry->GPC['newauthor'] . "'");
			if ($record AND intval($record['userid']))
			{

				$sql = "REPLACE INTO " . TABLE_PREFIX . "blog_sitemapconf(name, value, weight)
				VALUES('author', '" . $record['userid'] ."', "
				. round(floatval($this->registry->GPC['newauthor_pts']), 3) . ");" ;

				$this->registry->db->query_write($sql);
			}
			else
			{
				print_stop_message('could_not_find_user_x', $this->registry->GPC['newauthor']);
				return false;
			}
		}
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 28150 $
|| ####################################################################
\*======================================================================*/