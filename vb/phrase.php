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
 * Phrase
 * Stores a phrasegroup / key pair for deferred rendering.
 *
 * When a new phrase is created, the phrasegroup and key is cached.  Whenever a phrase
 * is rendered to a string, all of the phrasegroups / keys are fetched in a single
 * query and the phrasegroup cache is purged.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28674 $
 * @since $Date: 2008-12-03 12:56:57 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Phrase
{
	/*Properties====================================================================*/

	/**
	 * Local phrasekey cache.
	 * The cached phrasegroups.
	 *
	 * @var array array string					- Phrasegroup => Phrasekey
	 */
	protected static $phrasekey_cache = array();

	/**
	 * Local phrase cache.
	 * When the cached phrasegroups are fetched, the phrases are stored here.
	 *
	 * @var array array string					- Phrasegroup => Phrasekey => Phrase
	 */
	protected static $phrase_cache = array();

	/**
	 * Precached phrases.
	 * Precached phrases are phrases fetched before initialisation and assigned with
	 * vB_Phrase::preCache().  The individual groups that the phrases belong to are
	 * unknown, but the groups that are loaded into the precache can also be
	 * specified.
	 * @see vB_Phrase::preCache()
	 *
	 * @var array string
	 */
	protected static $precache = array();

	/**
	 * Groups that were loaded in the precache.
	 * @see vB_Phrase::preCache().
	 *
	 * @var array string => bool
	 */
	protected static $precached_groups = array();

	/**
	 * Phrasegroups that cannot be cached.
	 *
	 * @var array string
	 */
	protected static $uncachable_groups = array(
		'error',
		'frontredirect',
		'emailbody',
		'emailsubject',
		'vbsettings',
		'cphelptext',
		'faqtitle',
		'faqtext',
		'cpstopmsg',
		'hvquestion'
	);

	/**
	 * The phrasegroup of the phrase.
	 * Used with an instatiated object to reference a specific phrase.
	 *
	 * @var string
	 */
	protected $phrasegroup;

	/**
	 * The phrasekey of the phrase.
	 * Used with an instantiated object to reference a specific phrase
	 *
	 * @var string
	 */
	protected $phrasekey;

	/**
	 * Parameters to parse with the phrase.
	 *
	 * @var array mixed
	 */
	protected $parameters;

	/**
	 * The languageid to use throughout the session.
	 *
	 * @var int
	 */
	protected static $languageid = 0;



	/*Initialization================================================================*/

	/**
	 * Constructor.
	 * When a phrase is constructed, the phrasegroup and phrasekey are cached until
	 * the next time a phrase is rendered to a string.
	 *
	 * @param string $phrasegroup				- The phrase group where the phrase is located
	 * @param string $phrasekey					- The phrasekey of the phrase
	 */
	public function __construct($phrasegroup, $phrasekey, $parameters = null)
	{
		$this->phrasegroup = $phrasegroup;
		$this->phrasekey = $phrasekey;

		if (null !== $parameters)
		{
			$parameters = func_get_args();
			$this->parameters = array_slice($parameters, 2);
		}

		if (self::groupCachable($phrasegroup))
		{
			self::cachePhraseKey($phrasegroup, $phrasekey);
		}
	}


	/**
	 * Allows existing phrases to be added.
	 * $phrases should be in the form array(phrasegroup => array(phrasekey => phrase))
	 *
	 * @param array array string $phrases
	 */
	public static function addPhrases($phrasegroups)
	{
		foreach ($phrasegroups AS $phrasegroup => $phrases)
		{
			self::setPhraseGroup($phrasegroup, $phrases);
		}
	}


	/**
	 * Sets the language id to use for phrasing.
	 */
	public static function setLanguage($languageid)
	{
		self::$languageid = $languageid;
	}



	/*Caching=======================================================================*/

	/**
	 * Checks if a phrasegroup is cachable.
	 * Some phrasegroups are not cachable, such as error or redirect messages.
	 *
	 * @param string $phrasegroup				- The name of the phrasegroup to check
	 * @return bool
	 */
	public static function groupCachable($phrasegroup)
	{
		return !in_array($phrasegroup, self::$uncachable_groups);
	}


	/**
	 * Caches a phrasekey.
	 *
	 * @param string $phrasegroup
	 * @param string $phrasekey
	 */
	public static function cachePhraseKey($phrasegroup, $phrasekey)
	{
		if (!isset(self::$phrasekey_cache[$phrasegroup]) AND !isset(self::$phrase_cache[$phrasegroup]))
		{
			self::$phrasekey_cache[$phrasegroup] = array();
		}

		self::$phrasekey_cache[$phrasegroup][] = $phrasekey;
	}


	/**
	 * Clears the phrasekey cache.
	 * This is useful when it is known that all previously cached phrases are no
	 * longer needed; such as when a redirect or user error occurs.
	 */
	public static function clearCache()
	{
		self::$phrasekey_cache = array();
	}


	/**
	 * Fetches the phrases for all of the keys in the cache.
	 */
	protected static function fetchCache()
	{
		// Nothing to fetch
		if (!sizeof(self::$phrasekey_cache))
		{
			return;
		}

		$fields = '';
		foreach (array_keys(self::$phrasekey_cache) AS $phrasegroup)
		{
			$phrasegroup = preg_replace('#[^a-z0-9_]#i', '', $phrasegroup);

			if (isset(self::$phrase_cache[$phrasegroup]))
			{
				continue;
			}

			$fields .= "phrasegroup_$phrasegroup AS $phrasegroup,";
		}

		if (!$fields)
		{
			return;
		}

		$fields = substr($fields, 0, -1);

		$phrasegroups = vB::$db->query_first_slave($sql = "SELECT $fields
				FROM " . TABLE_PREFIX . "language
				WHERE languageid = " . intval(self::$languageid)
		);

		$added_phrases = false;
		foreach ($phrasegroups AS $phrasegroup => $phrases)
		{
			$added_phrases = true;
			self::setPhraseGroup($phrasegroup, unserialize($phrases));
		}

		return $added_phrases;
	}


	/**
	 * Allows prefetched phrases to be assigned to vB_Phrase.
	 *
	 * @param array $phrases					- Assoc array of key => phrase
	 * @param array $groups						- Array of groups in the precache
	 */
	public static function preCache($phrases, $groups)
	{
		if (!sizeof($phrases) OR !sizeof($groups))
		{
			// be conservative for legacy code
			return;
		}

		self::$precache = array_merge(self::$precache, $phrases);

		foreach ($groups AS $group)
		{
			self::$precached_groups[$group] = true;
		}
	}


	/**
	 * Sets a phrasegroup and removes it from the key cache.
	 *
	 * @param string $groupname					- The group to set
	 * @param array string $phrases				- Array of key => phrase
	 */
	public static function setPhraseGroup($groupname, $phrases)
	{
		if (isset(self::$phrasekey_cache[$groupname]))
		{
			unset(self::$phrasekey_cache[$groupname]);
		}

		self::$phrase_cache[$groupname] = $phrases;
	}



	/*Rendering=====================================================================*/

	/**
	 * Renders the phrase to a string.
	 * Can be used inline.
	 *
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			return self::fetchPhrase($this->phrasegroup, $this->phrasekey, $this->parameters);
		}
		catch (Exception $e)
		{
			// __toString is not allowed to throw exceptions
			return (vB::$vbulletin->debug ? $e->getMessage() : '');
		}
	}


	/**
	 * Fetches a rendered phrase.
	 * If the phrase is not in the local phrase cache, then the phrasekey cache is
	 * loaded and purged.
	 *
	 * @param string $phrasegroup				- The phrase group where the phrase is located
	 * @param string $phrasekey					- The phrasekey of the phrase
	 * @param array mixed						- Array of parameters to parse into the phrase
	 * @return string							- The translated phrase
	 */
	public static function fetchPhrase($phrasegroup, $phrasekey, $parameters = array())
	{
		if (isset(self::$phrase_cache[$phrasegroup][$phrasekey]))
		{
			return self::parsePhrase(self::$phrase_cache[$phrasegroup][$phrasekey], $parameters);
		}

		if (isset(self::$precached_groups[$phrasegroup]) AND isset(self::$precache[$phrasekey]))
		{
			return self::parsePhrase(self::$precache[$phrasekey], $parameters);
		}

		if (self::fetchCache() AND isset(self::$phrase_cache[$phrasegroup][$phrasekey]))
		{
			return self::parsePhrase(self::$phrase_cache[$phrasegroup][$phrasekey], $parameters);
		}

		if (!self::groupCachable($phrasegroup) AND ($phrase = self::fetchUncachablePhrase($phrasegroup, $phrasekey)))
		{
			return self::parsePhrase($phrase, $parameters);
		}

		if (vB::$vbulletin->debug)
		{
			return htmlspecialchars("~~$phrasegroup.$phrasekey~~");
		}

		return '';
	}


	/**
	 * Fetches a phrase without using any caching.
	 * This is useful for uncachable phrases such as error messages and redirect messages.
	 *
	 * @param string $phrasegroup				- The phrase group where the phrase is located
	 * @param string $phrasekey					- The phrasekey of the phrase
	 * @param string $languageid				- Id of the language to fetch from
	 * @return string							- The translated phrase
	 */
	protected static function fetchUncachablePhrase($phrasegroup, $phrasekey, $languageid = false)
	{
		$results = vB::$db->query_read_slave("
			SELECT text, languageid
			FROM " . TABLE_PREFIX . "phrase AS phrase
			INNER JOIN " . TABLE_PREFIX . "phrasetype USING(fieldname)
			WHERE phrase.fieldname = '" . vB::$db->escape_string($phrasegroup) . "'
				AND varname = '" . vB::$db->escape_string($phrasekey) . "'
				AND languageid IN (-1, 0, " . (intval($languageid) > 0 ? intval($languageid) : intval(self::$languageid)) . ")"
		);

		$phrases = array();
		while ($phrase = vB::$db->fetch_array($results))
		{
			$phrase['text'] = str_replace('%', '%%', $phrase['text']);
			$phrase['text'] = preg_replace('#\{([0-9]+)\}#sU', '%\\1$s', $phrase['text']);

			$phrases[$phrase['languageid']] = $phrase['text'];
		}
		vB::$db->free_result($results);

		// Resolve appropriate languageid to use
		if (false === $languageid)
		{
			// no language specified, use resolved user language
			$languageid = self::$languageid;
		}
		else if ($languageid == 0)
		{
			// use the forum default
			$languageid = vB::$vbulletin->options['languageid'];
		}

		if (isset($phrases[$languageid]))
		{
			// use resolved language
			return $phrases[$languageid];
		}

		if (isset($phrases[0]))
		{
			// use languageid 0
			return $phrases[0];
		}

		if (isset($phrases['-1']))
		{
			// use master language
			return $phrases['-1'];
		}

		return false;
	}


	/**
	 * Parses the tokens in a string with the given values.
	 *
	 * @param vB_Phrase | string $phrase		- The phrase to parse
	 * @param array mixed $parameters			- The values to parse into the phrase
	 * @return string							- The resulting string
	 */
	public static function parsePhrase($phrase, array $parameters = null)
	{
		if (!$parameters)
		{
			return $phrase;
		}

		if ($parsed = @call_user_func_array('sprintf', array_merge(array($phrase), $parameters)))
		{
			return $parsed;
		}

		if (!vB::$vbulletin->debug)
		{
			return $phrase;
		}

		// show undefined parameters
		for ($i = sizeof($parameters); $i < 10; $i++)
		{
			$parameters[$i] = "[ARG:$i UNDEFINED]";
		}

		if ($parsed = @call_user_func_array('sprintf', array_merge(array($phrase), $parameters)))
		{
			return $parsed;
		}

		return $phrase;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28674 $
|| ####################################################################
\*======================================================================*/