<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * DB Cache.
 * Handler that caches and retrieves data from the database.
 * @see vB_Cache
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Cache_Db extends vB_Cache
{
	/*Properties====================================================================*/

	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache_Db
	 */
	protected static $instance;

	/**
	 * Cache entries for deferred purging
	 *
	 * @var array int
	 */
	protected $purged = array();

	/**
	 * Cache entries for deferred expiration.
	 *
	 * @var array int
	 */
	protected $expired = array();

	/**
	 * Cache entries that have been written during this request.
	 *
	 * @var array int
	 */
	protected static $written = array();



	/*Construction==================================================================*/

	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 */
	protected function __construct(){}


	/**
	 * Returns singleton instance of self.
	 * @todo This can be inherited once late static binding is available.  For now
	 * it has to be redefined in the child classes
	 *
	 * @return vB_Cache_Db						- Reference to singleton instance of cache handler
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class();
		}

		return self::$instance;
	}



	/*Initialisation================================================================*/

	/**
	 * Writes the cache data to storage.
	 *
	 * @param vB_CacheObject $cache
	 */
	protected function writeCache(vB_CacheObject $cache)
	{
		// Check if we have already written this cache entry for this request
		$key = $cache->getKey();

		if (!empty($key) AND isset(self::$written[$key]))
		{
			return;
		}

		$data = $cache->getData();

		if (is_array($data) OR is_object($data))
		{
			$serialized = '1';
			$data = serialize($data);
		}
		else
		{
			$serialized = '0';
		}

		vB::$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "cache
			SET cacheid = '" . vB::$db->escape_string($cache->getKey()) . "',
				expires = " . intval($cache->getExpiry()) . ",
				created = " . TIMENOW . ",
				locktime = 0,
				data = \"" . vB::$db->escape_string($data) . "\",
				serialized = '" . intval($serialized) . "'"
		);

		self::$written[$cache->getKey()] = true;
	}


	/**
	 * Reads the cache object from storage.
	 *
	 * @param string $key						- Id of the cache entry to read
	 * @return vB_CacheObject
	 */
	protected function readCache($key)
	{
		$entry = vB::$db->query_first_slave("
			SELECT data, expires, locktime, serialized
			FROM " . TABLE_PREFIX . "cache
			WHERE cacheid = '" . vB::$db->escape_string($key) . "'"
		);

		if (!$entry)
		{
			return false;
		}

		if (intval($entry['serialized']))
		{
			$entry['data'] = unserialize($entry['data']);
		}

		return new vB_CacheObject($key, $entry['data'], $entry['expires'], $entry['locktime']);
	}


	/**
	 * Reads an array of cache objects from storage.
	 *
	 * @param string $keys						- Ids of the cache entry to read
	 * @return array of vB_CacheObjects
	 */
	protected function readCacheArray($keys)
	{
		$cacheids = array();
		foreach($keys as $id => $key)
		{
			$cacheids[$id] = "'" . vB::$db->escape_string($key) . "'";
		}
		$rst = vB::$db->query_read_slave("
			SELECT cacheid, data, expires, locktime, serialized
			FROM " . TABLE_PREFIX . "cache
			WHERE cacheid in (" . implode(',' , $cacheids) . ") AND expires > " . TIMENOW
		);

		if (!$rst)
		{
			return false;
		}

		$found = array();
		while($record = vB::$db->fetch_array($rst))
		{
			try
			{
				
				if (intval($record['serialized']))
				{
					$record['data'] = unserialize($record['data']);
				}
				
				if ($record['data'])
				{
					$obj = new vB_CacheObject($record['cacheid'], $record['data'], $record['expires'], $record['locktime']);
					//only return good values
					if (!$obj->isExpired())
					{
						$found[$record['cacheid']] = $record['data'];
					}
					unset($obj);
				}
			}
			catch (exception $e)
			{
				//If we got here, something was improperly serialized
				//There's not much we can do, but we don't want to return bad data.
                                                                
			}
		}
		return $found;

	}


	/**
	 * Removes a cache object from storage.
	 *
	 * @param int $key							- Key of the cache entry to purge
	 * @return bool								- Whether anything was purged
	 */
	protected function purgeCache($key)
	{
		$this->purged[] = vB::$db->escape_string($key);

		return true;
	}


	/**
	 * Sets a cache entry as expired in storage.
	 *
	 * @param string $key						- Key of the cache entry to expire
	 */
	protected function expireCache($key)
	{
		$this->expired[] = vB::$db->escape_string($key);

		return true;
	}


	/**
	 * Locks a cache entry.
	 *
	 * @param string $key						- Key of the cache entry to lock
	 */
	public function lock($key)
	{
		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "cache
			SET locktime = " . TIMENOW . "
			WHERE cacheid = '" . vB::$db->escape_string($key) . "'"
		);
	}



	/*Clean=========================================================================*/

	/**
	 * Cleans cache.
	 *
	 * @param bool $only_expired				- Only clean expired entries
	 * @param int $created_before				- Clean entries created before this time
	 */
	public function clean($only_expired = true, $created_before = false)
	{
		if (!$only_expired AND !$created_before)
		{
			vB::$db->query_write("
				TRUNCATE TABLE " . TABLE_PREFIX . "cache
			");

			$this->notifyClean();
		}
		else
		{
			$conditions = array();

			if ($only_expired)
			{
				$conditions[] = "expires BETWEEN 1 AND " . TIMENOW;
			}

			if ($created_before)
			{
				$conditions[] = "created < " . intval($created_before);
			}

			$result = vB::$db->query_read_slave("
				SELECT cacheid
				FROM " . TABLE_PREFIX . "cache
				WHERE " . implode(" AND ", $conditions)
			);

			while ($entry = vB::$db->fetch_array($result))
			{
				$this->purge($entry['cacheid']);
			}
		}
	}



	/*Shutdown======================================================================*\

	/**
	 * Perform any finalisation on shutdown.
	 */
	public function shutdown()
	{
		if (sizeof($this->purged))
		{
			vB::$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "cache
				WHERE cacheid IN ('" . implode('\',\'', $this->purged) . "')
			");
		}
		$this->purged = array();

		if (sizeof($this->expired))
		{
			vB::$db->query_write("
				UPDATE " . TABLE_PREFIX . "cache
				SET expires = " . (TIMENOW - 1) . "
				WHERE cacheid IN ('" . implode('\',\'', $this->expired) . "')"
			);
		}
		$this->expired = array();

		parent::shutdown();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29401 $
|| ####################################################################
\*======================================================================*/