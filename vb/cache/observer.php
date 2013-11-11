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
 * Cache Observer
 * Cache observers subscribe to a cache handler and track events for event based
 * expiration and purging.
 *
 * The observer is responsible for registering events, as well as handling
 * notification of triggered events.
 *
 * Cache observers are also responsible for maintaining and cleaning up event
 * references when notified about the purging and expiration of cache entries.
 * @see vB_Cache::attachObserver()
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Cache_Observer
{
	/*Properties====================================================================*/

	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache_Observer
	 */
	protected static $instance;

	/**
	 * The cache handler that the observer is watching.
	 *
	 * @var vB_Cache
	 */
	protected $cache;



	/*Construction==================================================================*/

	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 *
	 * @param vB_Cache $cache					- Reference to the cache we are observing
	 */
	protected function __construct(vB_Cache $cache)
	{
		$this->cache = $cache;
	}


	/**
	 * Returns singleton instance of self.
	 * @todo This can be inherited once late static binding is available.  For now
	 * it has to be redefined in the child classes
	 *
	 * @param vB_Cache $cache					- Reference to the cache we are observing
	 * @return vB_Cache_Observer				- Reference to singleton instance of cache observer
	 */
	public static function instance(vB_Cache $cache)
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class($cache);
		}

		return self::$instance;
	}



	/*Events========================================================================*/

	/**
	 * Notifies observer that a cache entry has been created with event id's.
	 * The cache observer will keep track of cache entries so that all entries
	 * associated with a triggered event will be expired when that event is
	 * triggered.
	 *
	 * @param string $key						- The key of the cache entry
	 * @param array string $events				- Array of associated events
	 */
	abstract public function written($key, $events);


	/**
	 * Notifies observer that a cache entry was purged.
	 * This allows the observer to clean up any cache entry -> event associations
	 * that may now be stale.
	 *
	 * @param string $key						- The key of the cache entry that was purged
	 */
	abstract public function purged($cache_id);


	/**
	 * Notifies observer of a crud event.
	 * The observer will tell the cache to expire any cache entries associated with
	 * that event.  The cache will in turn notify the observer that the object was
	 * expired.
	 * @see vB_Cache_Observer::expired()
	 *
	 * @param string | array $events			- The id of the crud event
	 */
	abstract public function event($events);


	/**
	 * Notifies observer that a cache entry expired.
	 * Most implementations will not need to do anything on an expiration.
	 *
	 * @param string $key						- The key of the cache entry that expired
	 */
	public function expired($cache_id){}


	/**
	 * Notifies observer that the cache was cleaned.
	 * The observer will remove all event associations.
	 */
	abstract public function clean();



	/*Shutdown======================================================================*/

	/**
	 * Ensures that all changes are commited before script execution ends.
	 */
	public function shutdown(){}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/