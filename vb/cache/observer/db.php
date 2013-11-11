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
 * Db Cache Observer
 * Tracks events using vB::$db.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Cache_Observer_Db extends vB_Cache_Observer
{
	/*Properties====================================================================*/

	/**
	 * Events that have been registered.
	 * The array is assoc in the form $key => array($events)
	 *
	 * @var array
	 */
	protected $registered_events = array();


	/**
	 * Cache entries that have been purged.
	 * The array is in the form $key => true
	 *
	 * @var array
	 */
	protected $purged_entries = array();

	/**
	 * Events that have been triggered
	 * The array is in the form $event => true
	 *
	 * @var bool
	 */
	protected $triggered_events = array();



	/*Construction==================================================================*/

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



	/*Cache Events==================================================================*/

	/**
	 * Notifies observer that a cache entry has been created with event id's.
	 *
	 * @param string $key						- The key of the cache entry
	 * @param array string $events				- Array of associated events
	 */
	public function written($key, $events)
	{
		$this->registered_events[(string)$key] = $events;
	}


	/**
	 * Notifies observer that a cache entry was purged.
	 *
	 * @param string $key						- The key of the cache entry that was purged
	 */
	public function purged($key)
	{
		$this->purged_entries[(string)$key] = true;
	}


	/**
	 * Notifies observer of a crud event.
	 *
	 * @param array | string $event						- The id of the crud event
	 */
	public function event($events)
	{
		if (empty($events))
		{
			return;
		}
		
		$events = (array)$events;
		
		foreach ($events AS $event)
		{
			$this->triggered_events[strval($event)] = true;
		}
	}


	/**
	 * Notifies observer that a cache entry expired.
	 * The db observer has nothing to do.
	 *
	 * @param string $key						- The key of the cache entry that expired
	 */
	public function expired($key){}


	/**
	 * Notifies observer that the cache was cleaned.
	 * The observer will remove all event associations.
	 */
	public function clean()
	{
		vB::$db->query_write("
			TRUNCATE TABLE " . TABLE_PREFIX . "cacheevent
		");
	}



	/*Shutdown======================================================================*/

	/**
	 * Ensures that all event maintenance is executed before shutdown.
	 */
	public function shutdown()
	{
		// Save registered events
		$this->registerEvents();

		// Purge events that are no longer associated
		$this->purgeEvents();

		// Expire cache entries triggered by events
		if ($this->triggerEvents())
		{
			$this->cache->shutdown();
		}
	}


	/**
	 * Registers all new cache entry -> event associations.
	 * Pending events are in $this->registered_events in the form
	 * $key => array($events)
	 */
	protected function registerEvents()
	{
		if (!sizeof($this->registered_events))
		{
			return;
		}

		$values = array();

		// Register events
		foreach ($this->registered_events AS $key => $events)
		{
			$key = vB::$db->escape_string($key);
			$events = array_unique($events);

			foreach ($events AS $event)
			{
				if (is_array($event))
				{
					foreach($event as $event_detail)
					{
						$event_detail = vB::$db->escape_string($event_detail);
						$values[] = "('$key', '$event_detail')";
					}
				}
				else
				{
				$event = vB::$db->escape_string($event);
				$values[] = "('$key', '$event')";
			}
		}
		}

		if (!sizeof($values))
		{
			return;
		}

		vB::$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "cacheevent
				(cacheid, event)
			VALUES " . implode(",\r\n\t", $values)
		);

		$this->registered_events = array();
	}


	/**
	 * Expires cache entries associated with triggered events.
	 *
	 * @return bool								- Whether any events were triggered
	 */
	protected function triggerEvents()
	{
		if (!sizeof($this->triggered_events))
		{
			return;
		}

		$events = array();

		// Query all keys associated with each event and expire them
		foreach (array_keys($this->triggered_events) AS $event)
		{
			$events[] = vB::$vbulletin->db->escape_string($event);
		}

		if (!sizeof($events))
		{
			return;
		}

		// Get affected cache entries
		$result = vB::$vbulletin->db->query_read("
			SELECT cacheid
			FROM " . TABLE_PREFIX . "cacheevent
			WHERE event IN ('" . implode("','", $events) . "')"
		);

		while ($entry = vB::$vbulletin->db->fetch_array($result))
		{
			$this->cache->expire($entry['cacheid']);
		}

		$this->triggered_events = array();

		return true;
	}


	/**
	 * Purges events that are no longer associated.
	 */
	protected function purgeEvents()
	{
		if (!sizeof($this->purged_entries))
		{
			return;
		}

		$entries = array();

		foreach (array_keys($this->purged_entries) AS $key)
		{
			$entries[] = vB::$db->escape_string($key);
		}

		vB::$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "cacheevent
			WHERE cacheid IN ('" . implode("','", $entries) . "')"
		);

		$this->purged_entries = array();
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/