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
/* NOTE: This file contains the vB_Cache class and the vB_CacheObserver interface */

/**
 * Cache
 * Handler that caches and retrieves data.
 *
 * @tutorial
 *  // Application init
 *  $cache = vB_Cache::create('vB', 'Memcache');
 *
 *  // Read existing cache entry and lock for rebuild if it's expired
 *  if(!($data = $cache::read('hello_world', true)))
 *  {
 * 		// rebuild the cache entry
 *  	$data = 'Bonjour Tout Le Monde!';
 *
 *  	// write cache, last for 50 minutes and purge on event 'widget55.update'
 * 		$cache->write('hello_world', $data, 50, "widget{$widgetid}.update");
 *  }
 *
 *	// Use data
 * 	echo($data);
 *
 *  // Meanwhile... when widget 55 is updated, expire stale cache objects
 *  $cache->event("widget{$widgetid}.update");
 *
 * Note: In order to use events, a vB_CacheObserver must be created and attached with
 * vB_Cache::attachObserver().  The cache observer receives four notifications via:
 * 	vB_CacheObserver::written($key, $events);			- The cacheObserver should register the events
 *  vB_CacheObserver::purged($key);						- The cacheObserver should purge associated events
 * 	vB_CacheObserver::event($event);					- The cacheObserver should expire associated cache objects
 *  vB_CacheObserver::expired($key);					- The cache object has expired
 *
 * It is the responsibility of the cache observer to track cache object -> event
 * associations and call cache::Expire($key) upon events.
 * @see interface vB_CacheObserver
 *
 * The cache handler also provides slam prevention.  Cache slams occur when a cache
 * entry expires and multiple connections attempt to rebuild it.
 * @see vB_Cache::lock()
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Cache
{
	/*Properties====================================================================*/

	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache
	 */
	protected static $instance;

	/**
	 * Array of observers for handling cache events.
	 * When a cache object is written, events can be associated with the cache
	 * object that will trigger it's expiration.  In order to enable this, at least
	 * one cacheObserver must be registered to handle and track cache events.
	 *
	 * Observers must implement cacheObserver and will be notified with
	 * written($cacheID, $events), purged($cacheID) and event($event).
	 * @see cache::attachObserver()
	 *
	 * @var array cacheObserver
	 */
	protected $observers = array();

	/*** array of values available from cache ***/
	protected $values_read = array();

	/*** array of values we know aren't in cache ***/
	protected $no_values = array();

	/*** meta cache lifetime meta or precache is a list of cache keys we know have been
		requested against this view or page ***/
	protected $metadata_life = 1440;

	/*** the minimum time from when we update the metacache key list to when
	* we are willing to again update it. ***/
	protected $metadata_update_min = 5;

	/*** the last metacache update time ***/
	protected $meta_info = false;

	/*** Array of keys we have used this time. This allows us to decide whether to
	* remove keys from precache list ***/
	protected $keys_used;

	/*** Flag indicated where meta cache data has been loaded ***/
	protected $meta_loaded = false;

	/*Construction==================================================================*/

	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 */
	protected function __construct(){}


	/**
	 * Returns an instance of the global cache.
	 * The cache type used is defined in options.
	 *
	 * @return vB_Cache							- Reference to instance of the cache handler
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			// TODO: Use config to determine the cache types to use
			self::$instance = vB_Cache_Db::instance();

			// TODO: Get appropriate class from options
			self::$instance->attachObserver(vB_Cache_Observer_Db::instance(self::$instance));

			vB::$vbulletin->shutdown->add(array(self::$instance, 'shutdown'));
		}

		if (vB::$vbulletin->debug AND $_REQUEST['nocache'])
		{
			vB::$vbulletin->options['nocache'] = 1;
		}

		return self::$instance;
	}



	/*Cache=========================================================================*/

	/**
	 * Writes data as a cache object.
	 *
	 * A string key is required to uniquely identify a cache object.  Client
	 * code should add all information that would affect the individualisation of
	 * the cache object to the key.
	 *
	 * If lifetime_mins is supplied the cache object will be purged the next time it
	 * is read after the TTL has passed.
	 *
	 * If a cache object should be purged on triggered events then events should be
	 * supplied as an array of string id's of the form 'scope.event', for example
	 * 'widget55.updated' may be used to purge a cache of a defined widget with the
	 * id 55 when it is reconfigured.
	 *
	 * Note: To use events, a cacheObserver event handler must be registered first.
	 * @see cache::attachObserver()
	 *
	 * @param string $key						- Identifying key
	 * @param mixed $data						- Data to cache
	 * @param int $lifetime						- Lifetime of cache, in minutes
	 * @param array string $events				- Purge events to associate with the cache object
	 * @return int | bool						- Cache id or false
	 */
	public function write($key, $data, $lifetime_mins = false, $events = false)
	{
		// Check if caching is disabled, usually for debugging
		if (vB::$vbulletin->options['nocache'])
		{
			return false;
		}

		// If data is empty then there's nothing to write
		if (!$data)
		{
			return false;
		}

		// Wrap data in a cache object
		$cache = new vB_CacheObject($key, $data);

		if ($lifetime_mins)
		{
			$cache->setExpiry(TIMENOW + ($lifetime_mins * 60));
		}


		// Write the cache object
		$this->writeCache($cache);

		// Notify observers of cache write and events
		$this->notifyWritten($key, $events);

		// Unlock the cache entry
		$this->unlock($key);

		$this->values_read[$key] = $data;

		//need to clear no_values for this key
		if (isset($this->no_values[$key]))
		{
			unset($this->no_values[$key]);
		}

		return $cache->getKey();
	}


	/**
	 * Writes the cache data to storage.
	 *
	 * @param vB_CacheObject $cache
	 */
	abstract protected function writeCache(vB_CacheObject $cache);


	/** Based on the assumption that if we go back to a page we're likely to request
	* a lot of the information we requested last time we were on that page, let's
	* store the cached information.
	***/
	public function saveCacheInfo($cacheid)
	{
		//If the minimum time hasn't passed, then don't update
		if ($this->meta_info['last_update'] AND
			(((TIMENOW - $this->meta_info['last_update'])/60) < $this->metadata_update_min))
		{
			return true;
		}

		$changecount = 0;
		//If we don't have a method to retrieve the data, don't bother.
		if (method_exists($this, 'readCacheArray'))
		{
			//Let's dump cache keys that aren't being used.
			if (isset($this->meta_info['cacheids']))
			{
				foreach($this->meta_info['cacheids'] AS $cachekey => $last_used)
				{
					if (array_key_exists($cachekey, $this->keys_used))
					{
						$this->meta_info['cacheids'][$cachekey] = TIMENOW;
					}
					else
					{
						$age = TIMENOW - $last_used;

						if (($age/60) > $this->metadata_life)
						{
							unset($this->meta_info['cacheids'][$cachekey]);
							$changecount++;
						}
					}
				}
			}
			else
			{
				$this->meta_info['cacheids'] = array();
			}

			//Now see if we have new keys
			foreach ($this->keys_used as $key => $data)
			{
				if ( ! array_key_exists($key, $this->meta_info['cacheids']))
				{
					$changecount++;
					$this->meta_info['cacheids'][$key] =  TIMENOW;
				}
			}
			$info = array('cacheids' => $this->meta_info['cacheids'] ,
						'last_update' => TIMENOW);

			$this->write($cacheid, $info, $this->metadata_life);
			return true;
		}
		return false;
	}

	/** If we used saveCacheInfo to save data,
	* this will get it back.
	****/
	public function restoreCacheInfo($cacheid)
	{
		//Only do this once.
		if ($this->meta_loaded)
		{
			return true;
		}

		$this->meta_loaded = true;
		//We need a method to retrieve the data.
		if (method_exists($this, 'readCacheArray'))
		{
			$this->meta_info = $this->read($cacheid);

			if ($this->meta_info AND isset($this->meta_info['cacheids']))
			{
				$keys = array_keys($this->meta_info['cacheids']);
				$this->values_read = $this->readCacheArray($keys);

				$this->no_values = array_diff($keys, array_keys($this->values_read));
				return true;
			}
			return false;
		}
	}

	/**
	 * Reads a cache object and returns the data.
	 *
	 * Integrity checking should be performed by the client code, ensuring
	 * that the returned data is in the expected form.
	 *
	 * $key should be a string key with all of the identifying information
	 * for the required cache objects.  This must match the $key used to write
	 * the cache object.
	 *
	 * The implicit lock can be set to true to indicate that the client code will
	 * rebuild the cache on an expired read.  This allows cache handlers to lock the
	 * cache for the current connection.  Normally, if a cache entry is locked then
	 * subsequent reads should return the expired cache data until it is unlocked.
	 * This cannot be done for cache entries that don't yet exist, but can be used
	 * on existing entries to prevent cache slams - where multiple connections
	 * decide to rebuild the cache under a race condition.
	 *
	 * Cache handlers should ensure to implement an expiration on cache locks.
	 *
	 * @see cache::Write()
	 *
	 * @param string $key							- Identifying key
	 * @param bool $write_lock						- Whether a failed read implies a lock for writing
	 * @return mixed								- The cached data or boolean false
	 */
	public function read($key, $write_lock = false, $save_meta = false)
	{
		if ($save_meta)
		{
			$this->keys_used[$key] = 1;
		}
		// Check if caching is disabled, usually for debugging
		if (vB::$vbulletin->options['nocache'])
		{
			return false;
		}

		//Did we already read it?
		if (array_key_exists($key, $this->values_read))
		{
			return $this->values_read[$key];
		}

		//Did we already try to read it?
		if (in_array($key, $this->no_values))
		{
			return false;
		}
		// Fetch the cache object and ensure it hasn't expired
		if ($cache = $this->readCache($key))
		{
			if ($cache->isExpired())
			{
				if ($write_lock)
				{
					// lock cache for writing
					$this->lock($key);
				}
			}
			else
			{
				$data = $cache->getData();
				$this->values_read[$key] = $data;
				return $data;

			}
		}

		$this->no_values[] = $key;
		return false;
	}


	/**
	 * Reads the cache object from storage.
	 *
	 * @param string $key						- Identifying key
	 * @return vB_CacheObject
	 */
	abstract protected function readCache($key);


	/**
	 * Purges a cache object.
	 * If a matching cache entry was found and removed, observers will be notified
	 * so that they can remove associated events.
	 *
	 * @param int $cache_id						- Id of the cache entry to purge
	 */
	public function purge($cache_id)
	{
		if ($this->purgeCache($cache_id))
		{
			$this->notifyPurged($cache_id);
		}

		return $this;
	}


	/**
	 * Removes a cache object from storage.
	 *
	 * @param int $cache_id						- Id of the cache entry to purge
	 */
	abstract protected function purgeCache($cache_id);


	/**
	 * Expires a cache object.
	 * This is preferred to purging a cache entry as it ensures that that the cache
	 * data can still be served while new cache data is being rebuilt.  This should
	 * be called by observers on a cache event.
	 *
	 * Observers are notifed of the expiration incase they want to perform any event
	 * related operations.
	 *
	 * @param int $cache_id						- Id of the cache entry to expire
	 */
	public function expire($cache_id)
	{
		if ($this->expireCache($cache_id))
		{
			$this->notifyExpired($cache_id);
		}

		return $this;
	}


	/**
	 * Sets a cache entry as expired in storage.
	 *
	 * @param int $cache_id						- Id of the cache entry to expire
	 * @return bool
	 */
	abstract protected function expireCache($cache_id);


	/**
	 * Expires cache objects based on a triggered event.
	 *
	 * An event handling vB_CacheObserver must be attached to handle cache events.
	 * Generally the CacheObservers would respond by calling vB_Cache::expire() with
	 * the cache_id's of the objects to expire.
	 *
	 * @param string | array $event				- The name of the event
	 */
	public function event($events)
	{
		// Notify observers of expire event
		$this->notifyEvent($events);

		return $this;
	}


	/**
	 * Locks a cache entry.
	 * This is done to prevent a cache slam where concurrent connections attempt to
	 * rebuild an expired cache entry.  While a cache entry is locked, it should be
	 * considered valid and served to all connections except the one that has the
	 * lock.  After the cache entry has been rebuilt it will be unlocked, allowing
	 * all new connections to consume the fresh entry.
	 *
	 * @param string $key						- Identifying key
	 */
	abstract public function lock($key);


	/**
	 * Unlocks a cache entry.
	 * Most implementations may unlock the cache during write, making this
	 * redundant.
	 *
	 * @param string $key						- Identifying key
	 */
	public function unlock($key){}



	/*Clean=========================================================================*/

	/**
	 * Cleans cache.
	 * $created_before should be a unix timestamp.
	 *
	 * @todo Provide more options
	 *
	 * @param bool $only_expired				- Only clean expired entries
	 * @param int $created_before				- Clean entries created before this time
	 */
	abstract public function clean($only_expired = true, $created_before = false);



	/*Observers=====================================================================*/

	/**
	 * Registers an observer to listen to cache events.
	 *
	 * @param vB_CacheObserver $observer		- The observer that is subscribing
	 */
	public function attachObserver(vB_Cache_Observer $observer)
	{
		if (!in_array($observer, $this->observers, true))
		{
			$this->observers[] = $observer;
		}
	}


	/**
	 * Removes a cache observer
	 *
	 * @param vB_CacheObserver $observer		- The observer to unsubscribe
	 */
	public function removeObserver(vB_Cache_Observer $observer)
	{
		if ($key = array_search($observer, $this->observers, true))
		{
			unset($this->observers[$key]);
		}
	}



	/*Dispatch======================================================================*/

	/**
	 * Notifies observers that a cache object was saved.
	 *
	 * @param int $cache_id						- Id of the cache entry that was written
	 * @param array string $events				- Events that the object should be purged on
	 */
	protected function notifyWritten($cache_id, $events)
	{
		if (!sizeof($this->observers))
		{
			return;
		}

		if (is_null($events) OR $events === false)
		{
			$events = array();
		}
		else
		{
			$events = (array)$events;
		}

		// Inform each observer of the write
		foreach ($this->observers AS $observer)
		{
			$observer->written($cache_id, $events);
		}
	}


	/**
	 * Notifies observers that a cache object was purged.
	 * This allows observers to destroy any event associations with the specified
	 * cache object.
	 *
	 * @param int $cache_id						- Id of the cache entry that was purged
	 */
	protected function notifyPurged($cache_id)
	{
		foreach ($this->observers AS $observer)
		{
			$observer->purged($cache_id);
		}
	}


	/**
	 * Notifies observers that a cache object expired.
	 * Most implementations of observers will not need to do anything on expiration.
	 *
	 * @param int $cache_id						- Id of the cache entry that was expired
	 */
	protected function notifyExpired($cache_id)
	{
		foreach ($this->observers AS $observer)
		{
			$observer->expired($cache_id);
		}
	}


	/**
	 * Notifies observers of a crud event.
	 * Observers should find any cache objects registered with that event using
	 * vB_CacheObserver::notifyWritten() and call vB_Cache::expire($cache_id).
	 *
	 * @param string | array $event				- Id of the crud event
	 */
	protected function notifyEvent($events)
	{
		foreach ($this->observers AS $observer)
		{
			$observer->event($events);
		}
	}


	/**
	 * Notifies observers that the entire cache was cleaned.
	 * Observers should clear all cache associations.
	 */
	protected function notifyClean()
	{
		foreach ($this->observers AS $observer)
		{
			$observer->clean();
		}
	}


	/**
	 * Notifies observers that shutdown has occured.
	 * Observers should commit all pending changes.
	 */
	protected function notifyShutdown()
	{
		foreach ($this->observers AS $observer)
		{
			$observer->shutdown();
		}
	}



	/*Shutdown======================================================================*\

	/**
	 * Perform any finalisation on shutdown.
	 */
	public function shutdown()
	{
		$this->notifyShutdown();
	}

	/**
	 * Tells the cache to trigger all events.
	 */
	public function cleanNow()
	{
		$this->values_read = array();
		$this->no_values = array();
		$this->meta_loaded = false;
		$this->meta_info = false;
		$this->notifyShutdown();
	}
}



/**
 * Cache Data.
 * Meta data container for a cache object.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_CacheObject
{
	/*Properties====================================================================*/

	/**
	 * The cached data.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * The key of the cache entry.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * The expiry time of the cache object.
	 * This should be a unix timestamp.
	 *
	 * @var int
	 */
	protected $expires;

	/**
	 * The time that the cache object was locked for rebuilding.
	 * This should be a unix timestamp.
	 *
	 * @var int
	 */
	protected $lock_time;

	/**
	 * How long a cache entry can be locked before the lock expires, in seconds.
	 *
	 * @var int
	 */
	protected $lock_duration;



	/*Initialization================================================================*/

	/**
	 * Creates a cache objects.
	 * The identifying key and data must be specified for creation.
	 *
	 * @param string $key						- The key of the cache entry
	 * @param mixed $data						- The data to cache
	 * @param int $expires						- The unixtime the cache object expires
	 * @param int $lock_time					- If the cache entry is locked, the time it was locked
	 */
	public function __construct($key, $data, $expires = false, $lock_time = false)
	{
		if (is_resource($data))
		{
			throw (new vB_Exception_Cache('Resource types cannot be cached'));
		}

		$this->key = $key;
		$this->data = $data;
		$this->expires = $expires;
		$this->lock_time = $lock_time;

		$this->lock_duration = (($lock_duration = ini_get('max_execution_time')) ? $lock_duration : 30);
	}



	/*Accessors=====================================================================*/

	/**
	 * Sets the key of the cache entry.
	 * @see vB_Cache
	 *
	 * @param string $key
	 */
	public function setKey($key)
	{
		$this->key = $key;
	}


	/**
	 * Gets the key for the cache object.
	 *
	 * @return string
	 */
	public function getKey()
	{
		return $this->key;
	}


	/**
	 * Sets the expiry time of the cache data.
	 *
	 * @param int $expires
	 */
	public function setExpiry($expires)
	{
		$this->expires = intval($expires);
	}


	/**
	 * Sets the time the cache object was locked.
	 *
	 * @param int $time							- Unixtime the cache entry was locked
	 */
	public function setLock($time)
	{
		$this->lock_time = $time;
	}


	/**
	 * Sets the lock duration allowed for cache locking, in seconds.
	 *
	 * @param int $seconds
	 */
	public function setLockDuration($seconds)
	{
		if ($seconds)
		{
			$this->lock_duration = $seconds;
		}
	}


	/**
	 * Gets the expiry time of the cache object.
	 *
	 * @return int
	 */
	public function getExpiry()
	{
		return $this->expires;
	}


	/**
	 * Checks if the cache entry has expired.
	 * If the cache entry is expired but has a valid lock, then it will act as if
	 * it has not expired so that it will still be served to consecutive connections
	 * while the cache entry is being rebuilt.
	 *
	 * @return int
	 */
	public function isExpired()
	{
		return ($this->expires
				AND ($this->expires < TIMENOW)
				AND (!$this->lock_time
					OR ((TIMENOW - $this->lock_time) > $this->lock_duration)));
	}


	/**
	 * Gets the cached data.
	 *
	 * @return int
	 */
	public function getData()
	{
		return $this->data;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29401 $
|| ####################################################################
\*======================================================================*/