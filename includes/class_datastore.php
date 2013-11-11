<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/liceNse.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!class_exists('vB_Datastore', false))
{
	exit;
}

/**
* Class for fetching and initializing the vBulletin datastore from eAccelerator
*
* @package	vBulletin
* @version	$Revision: 44660 $
* @date		$Date: 2011-06-17 11:48:08 -0700 (Fri, 17 Jun 2011) $
*/
class vB_Datastore_eAccelerator extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Fetches the contents of the datastore from eAccelerator
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($items)
	{
		if (!function_exists('eaccelerator_get'))
		{
			trigger_error('eAccelerator not installed', E_USER_ERROR);
		}

		if (!sizeof($items = $this->prepare_itemarray($items)))
		{
			return;
		}

		$unfetched_items = array();
		foreach ($items AS $item)
		{
			$this->do_fetch($item, $unfetched_items);
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (sizeof($unfetched_items))
		{
			if (!($result = $this->do_db_fetch($this->prepare_itemlist($unfetched_items))))
			{
				return false;
			}
		}

		$this->check_options();
		$this->store_result = false;

		return true;
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$unfetched_items)
	{
		$ptitle = $this->prefix . $title;

		if (($data = eaccelerator_get($ptitle)) === null)
		{ // appears its not there, lets grab the data
			$unfetched_items[] = $title;
			return false;
		}

		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}

		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		$ptitle = $this->prefix . $title;

		eaccelerator_rm($ptitle);
		eaccelerator_put($ptitle, $data);
	}
}

// #############################################################################
// Memcached

/**
* Class for fetching and initializing the vBulletin datastore from a Memcache Server
*
* @package	vBulletin
* @version	$Revision: 44660 $
* @date		$Date: 2011-06-17 11:48:08 -0700 (Fri, 17 Jun 2011) $
*/
class vB_Datastore_Memcached extends vB_Datastore
{
	/**
	* The Memcache object
	*
	* @var	Memcache
	*/
	var $memcache = null;

	/**
	* To prevent locking when the memcached has been restarted we want to use add rather than set
	*
	* @var	boolean
	*/
	var $memcache_set = true;

	/**
	* To verify a connection is still active
	*
	* @var	boolean
	*/
	var $memcache_connected = false;

	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Constructor - establishes the database object to use for datastore queries
	*
	* @param	vB_Registry	The registry object
	* @param	vB_Database	The database object
	*/
	function vB_Datastore_Memcached(&$registry, &$dbobject)
	{
		parent::vB_Datastore($registry, $dbobject);

		if (!class_exists('Memcache', false))
		{
			trigger_error('Memcache is not installed', E_USER_ERROR);
		}

		$this->memcache = new Memcache;
	}

	/**
	* Connect Wrapper for Memcache
	*
	* @return	integer	When a new connection is made 1 is returned, 2 if a connection already existed, 3 if a connection failed.
	*/
	function connect()
	{
		if (!$this->memcache_connected)
		{
			if (is_array($this->registry->config['Misc']['memcacheserver']))
			{
				if (method_exists($this->memcache, 'addServer'))
				{
					foreach (array_keys($this->registry->config['Misc']['memcacheserver']) AS $key)
					{
						$this->memcache->addServer(
							$this->registry->config['Misc']['memcacheserver'][$key],
							$this->registry->config['Misc']['memcacheport'][$key],
							$this->registry->config['Misc']['memcachepersistent'][$key],
							$this->registry->config['Misc']['memcacheweight'][$key],
							$this->registry->config['Misc']['memcachetimeout'][$key],
							$this->registry->config['Misc']['memcacheretry_interval'][$key]
						);
					}
				}
				else if (!$this->memcache->connect($this->registry->config['Misc']['memcacheserver'][1], $this->registry->config['Misc']['memcacheport'][1], $this->registry->config['Misc']['memcachetimeout'][1]))
				{
					return 3;
				}
			}
			else if (!$this->memcache->connect($this->registry->config['Misc']['memcacheserver'], $this->registry->config['Misc']['memcacheport']))
			{
				return 3;
			}
			$this->memcache_connected = true;
			return 1;
		}
		return 2;
	}

	/**
	* Close Wrapper for Memcache
	*/
	function close()
	{
		if ($this->memcache_connected)
		{
			$this->memcache->close();
			$this->memcache_connected = false;
		}
	}

	/**
	* Fetches the contents of the datastore from a Memcache Server
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($items)
	{
		$this->connect();
		if (!sizeof($items = $this->prepare_itemarray($items)))
		{
			return;
		}

		if (!$this->memcache_connected)
		{ 
			return parent::fetch($items); 
		}
		
		//this line must stay under the potential return statement above.
		//this flag is intended to temporarily change the behavior of another function while
		//this function is active (it has to do with the way things are overridden from the
		//parent class).  If we leave this function with the flag set to false bad things can
		//happen.
		$this->memcache_set = false;

		if ($this->prefix)
		{
			foreach ($items as $item)
			{
				$items_fetch[] = $this->prefix . $item;
			}
		}
		else
		{
			$items_fetch =& $items;
		}

		$items_found = $this->memcache->get($items_fetch);
		$unfetched_items = array_keys(array_diff_key(array_flip($items_fetch), $items_found));
		foreach ($items_found AS $key => $data)
		{
			$this->register(substr($key, strlen($this->prefix), 50), $data);
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (!empty($unfetched_items))
		{
			if($this->prefix)
			{ // Remove any prefix for datastore call
				foreach ($unfetched_items as &$data)
				{ 
					$data = substr_replace($data, '', 0, strlen($this->prefix));
				}
				unset($data);
			}
			if (!($result = $this->do_db_fetch($this->prepare_itemlist($unfetched_items))))
			{
				return false;
			}
		}

		$this->memcache_set = true;

		$this->check_options();
		$this->store_result = false;

		return true;
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$unfetched_items)
	{
		$ptitle = $this->prefix . $title;

		if (($data = $this->memcache->get($ptitle)) === false)
		{ // appears its not there, lets grab the data
			$unfetched_items[] = $title;
			return false;
		}

		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}
		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	*
	* @return	void
	*/
	function build($title, $data, $expire = 0)
	{
		$ptitle = $this->prefix . $title;
		$check = $this->connect();
		if ($check == 3)
		{ // Connection failed
			trigger_error('Unable to connect to memcache server', E_USER_ERROR);
		}
		if ($this->memcache_set)
		{
			$this->memcache->set($ptitle, $data, MEMCACHE_COMPRESSED, $expire);
		}
		else
		{
			$this->memcache->add($ptitle, $data, MEMCACHE_COMPRESSED, $expire);
		}
	}
}

// #############################################################################
// APC

/**
* Class for fetching and initializing the vBulletin datastore from APC
*
* @package	vBulletin
* @version	$Revision: 44660 $
* @date		$Date: 2011-06-17 11:48:08 -0700 (Fri, 17 Jun 2011) $
*/
class vB_Datastore_APC extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Fetches the contents of the datastore from APC
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($items)
	{
		if (!function_exists('apc_fetch'))
		{
			trigger_error('APC not installed', E_USER_ERROR);
		}

		if (!sizeof($items = $this->prepare_itemarray($items)))
		{
			return;
		}

		$unfetched_items = array();
		foreach ($items AS $item)
		{
			$this->do_fetch($item, $unfetched_items);
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (!empty($unfetched_items))
		{
			if (!($result = $this->do_db_fetch($this->prepare_itemlist($unfetched_items))))
			{
				return false;
			}
		}

		$this->check_options();
		$this->store_result = false;

		return true;
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$unfetched_items)
	{
		$ptitle = $this->prefix . $title;

		if (($data = apc_fetch($ptitle)) === false)
		{ // appears its not there, lets grab the data, lock the shared memory and put it in
			$unfetched_items[] = $title;
			return false;
		}
		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}
		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		$ptitle = $this->prefix . $title;

		apc_delete($ptitle);
		apc_store($ptitle, $data);
	}

}

// #############################################################################
// XCache

/**
* Class for fetching and initializing the vBulletin datastore from XCache
*
* @package	vBulletin
* @version	$Revision: 44660 $
* @date		$Date: 2011-06-17 11:48:08 -0700 (Fri, 17 Jun 2011) $
*/
class vB_Datastore_XCache extends vB_Datastore
{
	/**
	* Indicates if the result of a call to the register function should store the value in memory
	*
	* @var	boolean
	*/
	var $store_result = false;

	/**
	* Fetches the contents of the datastore from XCache
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($items)
	{
		if (!function_exists('xcache_get'))
		{
			trigger_error('Xcache not installed', E_USER_ERROR);
		}

		if (!ini_get('xcache.var_size'))
		{
			trigger_error('Storing of variables is not enabled within XCache', E_USER_ERROR);
		}

		if (!sizeof($items = $this->prepare_itemarray($items)))
		{
			return;
		}

		$unfetched_items = array();
		foreach ($items AS $item)
		{
			$this->do_fetch($item, $unfetched_items);
		}

		$this->store_result = true;

		// some of the items we are looking for were not found, lets get them in one go
		if (sizeof($unfetched_items))
		{
			if (!($result = $this->do_db_fetch($this->prepare_itemlist($unfetched_items))))
			{
				return false;
			}
		}

		$this->check_options();
		$this->store_result = false;

		return true;
	}

	/**
	* Fetches the data from shared memory and detects errors
	*
	* @param	string	title of the datastore item
	* @param	array	A reference to an array of items that failed and need to fetched from the database
	*
	* @return	boolean
	*/
	function do_fetch($title, &$unfetched_items)
	{
		$ptitle = $this->prefix . $title;

		if (!xcache_isset($ptitle))
		{ // appears its not there, lets grab the data, lock the shared memory and put it in
			$unfetched_items[] = $title;
			return false;
		}

		$data = xcache_get($ptitle);
		$this->register($title, $data);
		return true;
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		if ($this->store_result === true)
		{
			$this->build($title, $data);
		}
		parent::register($title, $data, $unserialize_detect);
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		$ptitle = $this->prefix . $title;

		xcache_unset($ptitle);
		xcache_set($ptitle, $data);
	}

}

// #############################################################################
// datastore using FILES instead of database for storage

/**
* Class for fetching and initializing the vBulletin datastore from files
*
* @package	vBulletin
* @version	$Revision: 44660 $
* @date		$Date: 2011-06-17 11:48:08 -0700 (Fri, 17 Jun 2011) $
*/
class vB_Datastore_Filecache extends vB_Datastore
{
	/**
	* Default items that are always loaded by fetch() when using the file method;
	*
	* @var	array
	*/
	var $cacheableitems = array(
		'options',
		'bitfields',
		'forumcache',
		'usergroupcache',
		'stylecache',
		'languagecache',
		'products',
		'pluginlist',
	);

	/**
	* Constructor - establishes the database object to use for datastore queries
	*
	* @param	vB_Registry	The registry object
	* @param	vB_Database	The database object
	*/
	function vB_Datastore_Filecache(&$registry, &$dbobject)
	{
		parent::vB_Datastore($registry, $dbobject);

		if (defined('SKIP_DEFAULTDATASTORE'))
		{
			$this->cacheableitems = array('options', 'bitfields');
		}
	}

	/**
	* Fetches the contents of the datastore from cache files
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	void
	*/
	function fetch($items)
	{
		$include_return = @include_once(DATASTORE . '/datastore_cache.php');
		if ($include_return === false)
		{
			if (VB_AREA == 'AdminCP')
			{
				trigger_error('Datastore cache file does not exist. Please reupload includes/datastore/datastore_cache.php from the original download.', E_USER_ERROR);
			}
			else
			{
				parent::fetch($items);
				return;
			}
		}

		// Ensure $this->cacheableitems are always fetched
		$unfetched_items = array();
		foreach ($this->cacheableitems AS $item)
		{
			if (!vB_DataStore::$registered[$item])
			{
				if ($$item === '' OR !isset($$item))
				{
					if (VB_AREA == 'AdminCP')
					{
						$$item = $this->fetch_build($item);
					}
					else
					{
						$unfetched_items[] = $item;
						continue;
					}
				}

				if ($this->register($item, $$item) === false)
				{
					trigger_error('Unable to register some datastore items', E_USER_ERROR);
				}

				unset($$item);
			}
		}

		// fetch anything remaining
		$items = $items ? array_merge($items, $unfetched_items) : $unfetched_items;
		if ($items = $this->prepare_itemlist($items, true))
		{
			if (!($result = $this->do_db_fetch($items)))
			{
				return false;
			}
		}

		$this->check_options();
		return true;
	}

	/**
	* Updates the appropriate cache file
	*
	* @param	string	title of the datastore item
	* @param	mixed	The data associated with the title
	*
	* @return	void
	*/
	function build($title, $data)
	{
		if (!in_array($title, $this->cacheableitems))
		{
			return;
		}

		if (!file_exists(DATASTORE . '/datastore_cache.php'))
		{
			// file doesn't exist so don't try to write to it
			return;
		}

		$data_code = vb_var_export(unserialize(trim($data)), true);

		if ($this->lock())
		{
			$cache = file_get_contents(DATASTORE . '/datastore_cache.php');

			// this is equivalent to the old preg_match system, but doesn't have problems with big files (#23186)
			$open_match = strpos($cache, "### start $title ###");
			if ($open_match) // we don't want to match the first character either!
			{
				// matched and not at the beginning
				$preceding = $cache[$open_match - 1];
				if ($preceding != "\n" AND $preceding != "\r")
				{
					$open_match = false;
				}
			}

			if ($open_match)
			{
				$close_match = strpos($cache, "### end $title ###", $open_match);
				if ($close_match) // we don't want to match the first character either!
				{
					// matched and not at the beginning
					$preceding = $cache[$close_match - 1];
					if ($preceding != "\n" AND $preceding != "\r")
					{
						$close_match = false;
					}
				}
			}

			// if we matched the beginning and end, then update the cache
			if (!empty($open_match) AND !empty($close_match))
			{
				$replace_start = $open_match - 1; // include the \n
				$replace_end = $close_match + strlen("### end $title ###");
				$cache = substr_replace($cache, "\n### start $title ###\n$$title = $data_code;\n### end $title ###", $replace_start, $replace_end - $replace_start);
			}

			// try an atomic operation first, if that fails go for the old method
			$atomic = false;
			if (($fp = @fopen(DATASTORE . '/datastore_cache_atomic.php', 'w')))
			{
				fwrite($fp, $cache);
				fclose($fp);
				$atomic = $this->atomic_move(DATASTORE . '/datastore_cache_atomic.php', DATASTORE . '/datastore_cache.php');
			}

			if (!$atomic AND ($fp = @fopen(DATASTORE . '/datastore_cache.php', 'w')))
			{
				fwrite($fp, $cache);
				fclose($fp);
			}

			$this->unlock();

			/*insert query*/
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "adminutil
					(title, text)
				VALUES
					('datastore', '" . $this->dbobject->escape_string($cache) . "')
			");
		}
		else
		{
			trigger_error('Could not obtain file lock', E_USER_ERROR);
		}
	}

	/**
	* Obtains a lock for the datastore. Attempt to get the lock multiple times before failing.
	*
	* @param	string	title of the datastore item
	*
	* @return	boolean
	*/
	function lock($title = '')
	{
		$lock_attempts = 5;
		while ($lock_attempts >= 1)
		{
			$result = $this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "adminutil SET
					text = UNIX_TIMESTAMP()
				WHERE title = 'datastorelock' AND text < UNIX_TIMESTAMP() - 15
			");
			if ($this->dbobject->affected_rows() > 0)
			{
				return true;
			}
			else
			{
				$lock_attempts--;
				sleep(1);
			}
		}

		return false;
	}

	/**
	* Releases the datastore lock
	*
	* @param	string	title of the datastore item
	*
	* @return	void
	*/
	function unlock($title = '')
	{
		$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "adminutil SET text = 0 WHERE title = 'datastorelock'");
	}

	/**
	* Fetches the specified datastore item from the database and tries
	* to update the file cache with it. Data is automatically unserialized.
	*
	* @param	string	Datastore item to fetch
	*
	* @return	mixed	Data from datastore (unserialized if fetched)
	*/
	function fetch_build($title)
	{
		$data = '';
		$this->dbobject->hide_errors();
		$dataitem = $this->dbobject->query_first("
			SELECT title, data
			FROM " . TABLE_PREFIX . "datastore
			WHERE title = '" . $this->dbobject->escape_string($title) ."'
		");
		$this->dbobject->show_errors();
		if (!empty($dataitem['title']))
		{
			$this->build($dataitem['title'], $dataitem['data']);
			$data = unserialize($dataitem['data']);
		}

		return $data;
	}

	/**
	* Perform an atomic move where a request may occur before a file is written
	*
	* @param	string	Source Filename
	* @param	string	Destination Filename
	*
	* @return	boolean
	*/
	function atomic_move($sourcefile, $destfile)
	{
		if (!@rename($sourcefile, $destfile))
		{
			if (copy($sourcefile, $destfile))
			{
				unlink($sourcefile);
				return true;
			}
			return false;
		}
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44660 $
|| ####################################################################
\*======================================================================*/
?>
