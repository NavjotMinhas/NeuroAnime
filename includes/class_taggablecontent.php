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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . "/includes/functions_bigthree.php");

//force the autoloader init so we can use vB_Types.
require_once(DIR . "/includes/class_bootstrap_framework.php");
vB_Bootstrap_Framework::init();
/**
*	Base class for the taggable content items
*
*	This class should be renamed vB_TaggableContent and should be
* moved to 'vb/taggablecontent.php'.  Its children, for modularity
* already follow the new class conventions.
*/
abstract class vB_Taggable_Content_Item
{
	/********************************************************
	*	Constructors / Factory Methods
	********************************************************/

	/**
	*	Create a taggable content item.
	*
	*	@param object vbulletin registry object
	* @param mixed contenttypeid in one of the forms accepted by vB_Types
	* @param int id for the content item to be tagged.
	* @param array content info -- database record for item to be tagged, values vary by
	*   specific content item.  For performance reasons this can be included, otherwise the
	* 	data will be fetched if needed from the provided id.
	*/
	public static function create($registry, $contenttypeid, $contentid, $contentinfo = null)
	{
		$types = vB_Types::instance();
		//force into the numeric form.
		$contenttypeid = $types->getContentTypeID($contenttypeid);
		if (!$contenttypeid)
		{
			return false;
		}

		$package = $types->getContentTypePackage($contenttypeid);
		$class = $types->getContentTypeClass($contenttypeid);

		//This avoids the need to create a bunch of empty classes for
		//individual CMS types.
		if ($package == 'vBCms')
		{
			$class_name = 'vBCms_TaggableContent_Content';
		}
		else
		{
			$class_name = $package . '_TaggableContent_' . $class;
		}

		if (!class_exists($class_name))
		{
			return false;
		}

		return new $class_name($registry, $contenttypeid, $contentid, $contentinfo);
	}

	/**
	*	Private constructor, use 'create' method to instantiate objects.
	*
	*	@private
	*/
	protected function __construct($registry, $contenttypeid, $contentid, $contentinfo)
	{
		if (!$registry)
		{
			$registry = $GLOBALS['vbulletin'];
		}

		$this->registry = $registry;
		$this->dbobject = $registry->db;
		$this->contenttypeid = $contenttypeid;
		$this->contentid = $contentid;
		$this->contentinfo = $contentinfo;
	}

	/********************************************************
	*	Static Methods
	********************************************************/

	/**
	*	Takes a list of tags and returns a list of valid tags
	*
	* Tags are transformed to removed tabs and newlines
	* Tags may be lowercased based on options
	* Tags matching synomyns will
	* Duplicate will be eliminated (case insensitive)
	* Invalid tags will be removed.
	*
	* Fetch the valid tags from a list. Filters are length, censorship, perms (if desired).
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is). If array, ensure there are no commas.
	* @param	array			(output) List of errors that happens
	* @param	boolean		Whether to expand the error phrase
	*
	* @return	array			List of valid tags
	*/
	public static function filter_tag_list($taglist, &$errors, $evalerrors = true)
	{
		global $vbulletin;
		$errors = array();

		if (!is_array($taglist))
		{
			$taglist = self::split_tag_list($taglist);
		}

		$valid_raw = array();

		foreach ($taglist AS $tagtext)
		{
			$tagtext = trim(preg_replace('#[ \r\n\t]+#', ' ', $tagtext));
			if (self::is_tag_valid($tagtext, $errors))
			{
				$valid_raw[] = ($vbulletin->options['tagforcelower'] ? vbstrtolower($tagtext) : $tagtext);
			}
		}

		$valid_raw = self::convert_synonyms($valid_raw, $errors);

		// we need to essentially do a case-insensitive array_unique here
		$valid_unique = array_unique(array_map('vbstrtolower', $valid_raw));
		$valid = array();
		foreach (array_keys($valid_unique) AS $key)
		{
			$valid[] = $valid_raw["$key"];
		}
		$valid_unique = array_values($valid_unique); // make the keys jive with $valid

		//if requested compose the error messages to strings
		if ($evalerrors)
		{
			$errors = fetch_error_array($errors);
		}

		return $valid;
	}

	/**
	*	Delete tag attachments for a list of content items
	*
	* @param mixed contenttypeid in one of the forms accepted by vB_Types
	* @param array $contentids
	*/
	public static function delete_tag_attachments_list($contenttypeid, $contentids)
	{
	 	$contenttypeid = vB_Types::instance()->getContentTypeID($contenttypeid);

		global $vbulletin;
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "tagcontent
			WHERE contentid IN (" . implode(',', array_map('intval', $contentids)) . ") AND
				contenttypeid = " . intval($contenttypeid)
		);
	}

	public static function merge_users($olduserid, $newuserid)
	{
		global $vbulletin;
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "tagcontent
			SET userid = " . intval($newuserid) . "
			WHERE userid = " . intval($olduserid)
		);
	}

   /********* provides a list of content types

	/**
	*	Checks to see if the tag is valid.
	*
	* Does not check the validity of any tag associations.
	* @param 	string $tagtext tag text to validate
	* @param	array	$errors (output) List of errors that happens
	*/
	protected static function is_tag_valid($tagtext, &$errors)
	{
		global $vbulletin;
		static $taggoodwords = null;
		static $tagbadwords = null;

		// construct stop words and exception lists (if not previously constructed)
		if (is_null($taggoodwords) or is_null($tagbadwords))
		{

			// filter the stop words by adding custom stop words (tagbadwords) and allowing through exceptions (taggoodwords)
			if (!is_array($tagbadwords))
			{
				$tagbadwords = preg_split('/\s+/s', vbstrtolower($vbulletin->options['tagbadwords']), -1, PREG_SPLIT_NO_EMPTY);
			}

			if (!is_array($taggoodwords))
			{
				$taggoodwords = preg_split('/\s+/s', vbstrtolower($vbulletin->options['taggoodwords']), -1, PREG_SPLIT_NO_EMPTY);
			}

			// get the stop word list; allow multiple requires
			require(DIR . '/includes/searchwords.php');
			// merge hard-coded badwords and tag-specific badwords
			$tagbadwords = array_merge($badwords, $tagbadwords);
		}

		if ($tagtext === '')
		{
			return false;
		}

		if (in_array(vbstrtolower($tagtext), $taggoodwords))
		{
			return true;
		}

		$char_strlen = vbstrlen($tagtext, true);
		if ($vbulletin->options['tagminlen'] AND $char_strlen < $vbulletin->options['tagminlen'])
		{
			$errors['min_length'] = array('tag_too_short_min_x', $vbulletin->options['tagminlen']);
			return false;
		}

		// Correct potentially odd value.
		$vbulletin->options['tagmaxlen'] = $vbulletin->options['tagmaxlen'] > 100 ? 100 : $vbulletin->options['tagmaxlen'];

		if ($char_strlen > $vbulletin->options['tagmaxlen'])
		{
			$errors['max_length'] = array('tag_too_long_max_x', $vbulletin->options['tagmaxlen']);
			return false;
		}

		if (strlen($tagtext) > 100)
		{
			// only have 100 bytes to store a tag
			$errors['max_length'] = array('tag_too_long_max_x', $vbulletin->options['tagmaxlen']);
			return false;
		}

		$censored = fetch_censored_text($tagtext);
		if ($censored != $tagtext)
		{
			// can't have tags with censored text
			$errors['censor'] = 'tag_no_censored';
			return false;
		}

		if (count(self::split_tag_list($tagtext)) > 1)
		{
			// contains a delimiter character
			$errors['comma'] = $evalerrors ? fetch_error('tag_no_comma') : 'tag_no_comma';
			return false;
		}

		if (in_array(strtolower($tagtext), $tagbadwords))
		{
			$errors['common'] = array('tag_x_not_be_common_words', $tagtext);
			return false;
		}

		return true;
	}

	/**
	* Splits the tag list based on an admin-specified set of delimiters (and comma).
	*
	* @param	string	List of tags
	*
	* @return	array	Tags in seperate array entries
	* temporarily make public
	*/
	public static function split_tag_list($taglist)
	{
		global $vbulletin;
		static $delimiters = array();

		$taglist = unhtmlspecialchars($taglist);

		if (empty($delimiters))
		{
			$delimiter_list = $vbulletin->options['tagdelimiter'];
			$delimiters = array(',');

			// match {...} segments as is, then remove them from the string
			if (preg_match_all('#\{([^}]*)\}#s', $delimiter_list, $matches, PREG_SET_ORDER))
			{
				foreach ($matches AS $match)
				{
					if ($match[1] !== '')
					{
						$delimiters[] = preg_quote($match[1], '#');
					}
					$delimiter_list = str_replace($match[0], '', $delimiter_list);
				}
			}

			// remaining is simple, space-delimited text
			foreach (preg_split('#\s+#', $delimiter_list, -1, PREG_SPLIT_NO_EMPTY) AS $delimiter)
			{
				$delimiters[] = preg_quote($delimiter, '#');
			}
		}

		$taglist = preg_split('#(' . implode('|', $delimiters) . ')#', $taglist, -1, PREG_SPLIT_NO_EMPTY);

		return array_map('htmlspecialchars_uni', $taglist);
	}

	/**
	*	Converts synomyns to canonical tags
	*
	* If a tag is converted a message will be added to the error array to alert the user
	* Does not handle removing duplicates created by the coversion process
	*
	* @param array array of tags to convert
	* @param array array of errors (in/out param)
	*
	*	@return array the new list of tags
	*/
	protected static function convert_synonyms($tags, &$errors)
	{
		global $vbulletin;
		$escaped_tags = array_map(array(&$vbulletin->db, 'escape_string'), $tags);
		$set = $vbulletin->db->query_read("
		  SELECT t.tagtext, p.tagtext as canonicaltagtext
			FROM " . TABLE_PREFIX . "tag t JOIN
				" . TABLE_PREFIX . "tag p ON t.canonicaltagid = p.tagid
			WHERE t.tagtext IN ('" . implode ("', '", $escaped_tags) . "')
		");

		$map = array();
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$map[vbstrtolower($row['tagtext'])] = $row['canonicaltagtext'];
		}
		$vbulletin->db->free_result($set);

		$new_tags = array();
		foreach ($tags as $key => $tag)
		{
			$tag_lower = vbstrtolower($tag);
			if (array_key_exists($tag_lower, $map))
			{
				$errors["$tag_lower-convert"] = array('tag_x_converted_to_y', $tag, $map[$tag_lower]);
				$new_tags[] = $map[$tag_lower];
			}
			else
			{
				$new_tags[] = $tag;
			}
		}
		return $new_tags;
	}


	/********************************************************
	*	Public Methods
	********************************************************/

	/**
	*	Determines if a user can delete a tag associated with this content item
	*
	* A user can delete his or her own tags.
	* A user with moderator rights can delete a tag.
	* Otherwise the permissions are defined based on the contenttypeid
	* If not otherwise specified a user can delete a tag if they own the content item
	*
	*	This function requires that content info is set.
	*
	*	@param int The user id for the tag/content association
	* @return bool
	*/
	public function can_delete_tag($taguserid)
	{
		// Attempt some decent content agnostic defaults
		// Content types that care should override this function

		//the user can delete his own tag associations
		if ($taguserid == $this->registry->userinfo['userid'])
		{
			return true;
		}

		//moderators can delete tags
		if ($this->can_moderate_tag())
		{
			return true;
		}

		//the object's owner can delete tags
		return $this->is_owned_by_current_user();
	}

	/**
	*	Checks to see if the user has permission to "moderate" tags for this content items.
	*
	* This is specific to the content type and defaults to false.
	*	This function requires that content info be set.
	*
	* @return bool
	*/
	public function can_moderate_tag()
	{
		// Basic logic is that only super admin can moderate tags.
		// Content types with more granular permissions should override this function

		return (bool) ($this->registry->userinfo['permissions']['adminpermissions'] &
		$this->registry->bf_ugp_adminpermissions['ismoderator']);
	}

	/**
	*	Checks to see if the user can add tags to this content item
	*
	*	This function requires that content info be set.
	* @return bool
	*/
	public function can_add_tag()
	{
		// By default, logged in users can add tags
		// Content types that care should override this function

		return (bool) $this->registry->userinfo['userid'];
	}

	/**
	*	Can the current user manage existing tags?
	*
	*	The only current operation on existing tags is to remove them from the content
	* item.
	*
	*	This is odd.  It controls whether or not we show the checkboxes beside the
	* tags in tagUI (and if we check at all for deletes).  It exists primarily to
	* capture some logic in the thread to handle the situation where a user can
	* delete tags but not add them (if a user can add tags we'll always display the
	* delete UI in the legacy logic).  Note that there is a seperate check for each
	* tag to determine if the user can actually delete that particular tag.  Most
	* new types aren't likely to require that kind of granularity and probably
	* won't need to to extend this function.
	*
	*	This function requires that content info be set.
	*
	* @return bool
	*/
	public function can_manage_tag()
	{
		// By default, logged in users can add tags
		// Content types that care should override this function

		return $this->can_add_tag();
	}

	/**
	*	Determines if the current user owns this content item
	*
	*	Ownership is a content specific concept.  For example the "owner" of a thread
	* is the thread starter.
	*	This function requires that content info be set.
	*
	* @return bool
	*/
	public function is_owned_by_current_user()
	{
		// Attempt some decent content agnostic defaults
		// Content types that care should override this function

		$contentinfo = $this->fetch_content_info();
		if (array_key_exists('userid', $contentinfo))
		{
			return ($contentinfo['userid'] == $this->registry->userinfo['userid']);
		}
		else {
			return false;
		}
	}
	
	/**
	*	Get the user permission to create tags
	*
	* @return bool
	*/
	function check_user_permission()
	{
		return $this->registry->check_user_permission('genericpermissions', 'cancreatetag');
	}
	
	/**
	*	Get the tag limits for the content type
	*
	*	This function requires that content info be set.
	*
	* @return array ('content_limit' => total tags for content type, 'user_limit' => total tags the
	*		current user can have on this item)
	*/
	public function fetch_tag_limits()
	{
		if ($this->can_moderate_tag())
		{
			$user_limit = 0;
		}
		else
		{
			if ($this->is_owned_by_current_user())
			{
				$user_limit = $this->registry->options['tagmaxstarter'];
			}
			else
			{
				$user_limit = $this->registry->options['tagmaxuser'];
			}
		}

		return array('content_limit' => $this->registry->options['tagmaxthread'], 'user_limit' => $user_limit);
	}

	/**
	*	Get the diplay label for the current content type
	*
	*	@return string
	*/
	public function fetch_content_type_diplay()
	{
		return "";
		{
			return $vbphrase['picture'];
		}
	}

  /**
	* Adds tags to the content item. Tags are created if they don't already exist
	* (assuming the user has permissions)
	*
	*	If a tag cannot be processed it is dropped from the list and an error is returned, however
	* this does not prevent valid tags from being processed.
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is).
	*												If array, ensure there are no commas.
	* @param	array			array of tag limit constraints.  If a limit is not specified a suitable
	*										default will be used (currently unlimited, but a specific default should
	*										not be relied on). Current limits recognized are 'content_limit' which is
	*										the maximum number of tags for a content item and 'user_limit' which is the
	*										maximum number of tags the current user can add to the content item.
	*
	* @return	array			Array of errors, if any
	*/
	public function add_tags_to_content($taglist, $limits)
	{
		$this->invalidate_tag_list();

		if (!$this->contentid)
		{
			return array();
		}

		$taglist = $this->filter_tag_list_content_limits($taglist, $limits, $errors);
		if (!$taglist OR !is_array($taglist))
		{
			return $errors;
		}

		$taglist_db = array_map(array(&$this->dbobject, 'escape_string'), $taglist);


		// create new tags
		$taglist_insert = array();
		foreach ($taglist_db AS $tag)
		{
			$taglist_insert[] = "('$tag', " . TIMENOW . ")";
		}
		$this->dbobject->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "tag
				(tagtext, dateline)
			VALUES
				" . implode(',', $taglist_insert)
		);

		// now associate with content item
		$tagcontent = array();
		$tagid_sql = $this->dbobject->query_read("
			SELECT tagid
			FROM " . TABLE_PREFIX . "tag
			WHERE tagtext IN ('" . implode("', '", $taglist_db) . "')
		");
		while ($tag = $this->dbobject->fetch_array($tagid_sql))
		{
			$tagcontent[] = "(" . intval($this->contenttypeid)  . ", " .
				intval($this->contentid) . ", $tag[tagid], " . $this->registry->userinfo['userid'] . ", " .
				TIMENOW . ")";
		}

		if ($tagcontent)
		{
			// this should always happen
			$this->dbobject->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "tagcontent
					(contenttypeid, contentid, tagid, userid, dateline)
				VALUES
					" . implode(',', $tagcontent)
			);
		}

		// do any content type specific updates for new tags
		$this->rebuild_content_tags();
		return $errors;
	}


	/**
	*	Copy the tag attachments from one item to another
	*
	*	Copying of tag attachements from an item of a different type is supported.
	*
	*	@param $sourcetypeid The contenttypeid of the item whose tags should be copied
	*	@param $sourceid The id of the item whose tags should be copied
	*/
	public function copy_tag_attachments($sourcetypeid, $sourceid)
	{
		$this->invalidate_tag_list();
		$this->dbobject->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "tagcontent
				(contenttypeid, contentid, tagid, userid, dateline)
			SELECT " . intval($this->contenttypeid) . ", " . intval($this->contentid) . ", tagid, userid, dateline
				FROM " . TABLE_PREFIX . "tagcontent
			WHERE contentid = " . intval($sourceid) . " AND contenttypeid = " . intval($sourcetypeid)
		);
		$this->rebuild_content_tags();
	}

	/**
	*	Merge the tag attachments for one or more tagged items to this item
	*
	*	Designed to handle the results of merging items (the tags also need to be
	* merged).  Items merged are assumed to be the same type as this item. Merged
	* tags are detached from the items they are merged from.
	*
	*	@param $contenttypeid The contenttypeid items whose tags should be merged
	*	@param $sourceids The id of the item whose tags should be merged
	*	@param $destid The id of the item to merge tags to
	*
	*/
	public function merge_tag_attachments($sourceids)
	{
		$this->invalidate_tag_list();
		$safeids = array_map('intval', $sourceids);

		//some places like to include the target id in the array of
		//merged items.  This fixes that.
		$safeids = array_diff($safeids, array($this->contentid));

		$this->dbobject->query_write("
			UPDATE IGNORE " . TABLE_PREFIX . "tagcontent
			SET contentid = " . intval($this->contentid) . "
			WHERE contentid IN (" . implode(',', $safeids) . ") AND
				contenttypeid = " . intval($this->contenttypeid)
		);

		//if the above query causes duplicates then
		//remove any duplicated tags
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "tagcontent
			WHERE contentid IN(" . implode(',', $safeids) . ") AND
				contenttypeid = " . intval($this->contenttypeid)
		);

		$this->rebuild_content_tags();
	}

	/**
	*	Delete all tag attachments for this item
	*/
	public function delete_tag_attachments()
	{
		$this->invalidate_tag_list();
		self::delete_tag_attachments_list($this->contenttypeid, array($this->contentid));
	}


	/**
	* Filters the tag list to exclude invalid tags based on the content item the tags
	* are assigned to.
	*
	*	Calls filter_tag_list internally to handle invalid tags.
	*
	* @param	string|array	List of tags to add (comma delimited, or an array as is).
	*  											If array, ensure there are no commas.
	* @param	array			array of tag limit constraints.  If a limit is not specified a suitable
	*										default will be used (currently unlimited, but a specific default should
	*										not be relied on). Current limits recognized are 'content_limit' which
	*										is the maximum number of tags for a content item and 'user_limit' which
	*										is the maximum number of tags the current user can add to the content item.
	* @param	int				The maximum number of tags the current user can assign to this item (0 is unlimited)
	* @param	boolean		Whether to check the browsing user's create tag perms
	* @param	boolean		Whether to expand the error phrase
	*
	* @return	array			List of valid tags.  If there are too many tags to add, the list will
	*		be truncated first.  An error will be set in this case.
	*/
	public function filter_tag_list_content_limits (
		$taglist,
		$limits,
		&$errors,
		$check_browser_perms = true,
		$evalerrors = true
	)
	{
		$content_tag_limit = isset($limits['content_limit']) ? intval($limits['content_limit']) : 0;
		$user_tag_limit = isset($limits['user_limit']) ? intval($limits['user_limit']) : 0;

		$existing_tag_count = $this->fetch_existing_tag_count();
		if ($content_tag_limit AND $existing_tag_count >= $content_tag_limit)
		{
			$errors['threadmax'] = $evalerrors ? fetch_error('item_has_max_allowed_tags') : 'item_has_max_allowed_tags';
			return array();
		}

		$errors = array();
		$valid_tags = self::filter_tag_list($taglist, $errors, $evalerrors);
		$valid_tags_lower = array_map('vbstrtolower', $valid_tags);

		if ($valid_tags)
		{
			$existing_sql = $this->dbobject->query_read("
				SELECT tag.tagtext, IF(tagcontent.tagid IS NULL, 0, 1) AS tagincontent
				FROM " . TABLE_PREFIX . "tag AS tag
				LEFT JOIN " . TABLE_PREFIX . "tagcontent AS tagcontent ON
					(tag.tagid = tagcontent.tagid AND tagcontent.contenttypeid = " . intval($this->contenttypeid) . " AND
					tagcontent.contentid = " . intval($this->contentid) . ")
				WHERE tag.tagtext IN ('" . implode("','", array_map(array(&$this->dbobject, 'escape_string'), $valid_tags)) . "')
			");

			if ($check_browser_perms AND !$this->check_user_permission())
			{
				// can't create tags, need to throw errors about bad ones
				$new_tags = array_flip($valid_tags_lower);

				while ($tag = $this->dbobject->fetch_array($existing_sql))
				{
					unset($new_tags[vbstrtolower($tag['tagtext'])]);
				}

				if ($new_tags)
				{
					// trying to create tags without permissions. Remove and throw an error
					$errors['no_create'] = $evalerrors ? fetch_error('tag_no_create') : 'tag_no_create';

					foreach ($new_tags AS $new_tag => $key)
					{
						// remove those that we can't add from the list
						unset($valid_tags["$key"], $valid_tags_lower["$key"]);
					}
				}
			}
			
			$this->dbobject->data_seek($existing_sql, 0);

			// determine which tags are already in the thread and just ignore them
			while ($tag = $this->dbobject->fetch_array($existing_sql))
			{
				if ($tag['tagincontent'])
				{
					// tag is in thread, find it and remove
					if (($key = array_search(vbstrtolower($tag['tagtext']), $valid_tags_lower)) !== false)
					{
						unset($valid_tags["$key"], $valid_tags_lower["$key"]);
					}
				}
			}

 			//approximate "unlimited" as PHP_INT_MAX -- makes the min logic cleaner
			$content_tags_remaining = PHP_INT_MAX;
			if ($content_tag_limit)
			{
				$content_tags_remaining = $content_tag_limit - $existing_tag_count - count($valid_tags);

			}

			$user_tags_remaining = PHP_INT_MAX;
			if ($user_tag_limit)
			{
				list($user_tag_count) = $this->dbobject->query_first("
					SELECT COUNT(*) AS count
					FROM " . TABLE_PREFIX . "tagcontent AS tagcontent
					WHERE contenttypeid = " . intval($this->contenttypeid) . "
						AND contentid = " . intval($this->contentid) . "
						AND userid = " . $this->registry->userinfo['userid']
					,
					DBARRAY_NUM
				);
				$user_tags_remaining = $user_tag_limit - $user_tag_count - count($valid_tags);
			}

			$remaining_tags = min($existing_tag_count, $user_tags_remaining);
			if ($remaining_tags < 0)
			{
				$errors['threadmax'] = $evalerrors ?
					fetch_error('number_tags_add_exceeded_x', vb_number_format($remaining_tags * -1)) :
					array('number_tags_add_exceeded_x', vb_number_format($remaining_tags * -1));

				$allowed_tag_count = count($valid_tags) + $remaining_tags;
				if ($allowed_tag_count > 0)
				{
					$valid_tags = array_slice($valid_tags, 0, count($valid_tags) + $remaining_tags);
				}
				else
				{
					$valid_tags = array();
				}
			}
		}
		return $valid_tags;
	}

	/**
	*	Handle any content specific changes that are required when the main tag data
	*	changes.
	*/
	public function rebuild_content_tags() {
		//intentionally does nothing by default.  A hook for subclasses to handle
	}


	/**
	*	Get the number of existing tags for this item
	*
	*	@return int the tag count
	*/
	public function fetch_existing_tag_count()
	{
		if (!is_null($this->tags))
		{
			return count($this->tags);
		}

		list($count) = $this->dbobject->query_first("
			SELECT COUNT(*)
			FROM " . TABLE_PREFIX . "tag AS tag
			JOIN " . TABLE_PREFIX . "tagcontent AS tagcontent ON (tag.tagid = tagcontent.tagid)
			WHERE tagcontent.contenttypeid = " . intval($this->contenttypeid) . "
				AND tagcontent.contentid = " . intval($this->contentid),
			DBARRAY_NUM
		);
		return $count;
	}


	/**
	*	Get the list of tags associated with this item
	*
	* @return array Array of tag text for the associated tags
	*/
	public function fetch_existing_tag_list()
	{
		if (!is_null($this->tags))
		{
			return $this->tags;
		}

		$tags_set = $this->dbobject->query_read("
			SELECT tag.tagtext
			FROM " . TABLE_PREFIX . "tag AS tag
			JOIN " . TABLE_PREFIX . "tagcontent AS tagcontent ON (tag.tagid = tagcontent.tagid)
			WHERE tagcontent.contenttypeid = " . intval($this->contenttypeid) . "
				AND tagcontent.contentid = " . intval($this->contentid) . "
			ORDER BY tag.tagtext
		");

		$this->tags = array();
		while ($tag = $this->dbobject->fetch_array($tags_set))
		{
			$this->tags[] = $tag['tagtext'];
		}

		return $this->tags;
	}


	/**
	*	Get the html rendered tag list for this item.
	*
	*	Allows types to override the display of tags based on their own formatting
	*/
	public function fetch_rendered_tag_list()
	{
		$taglist = $this->fetch_existing_tag_list();
		return fetch_tagbits(implode(", ", $taglist));
	}

	/**
	*	Allow access to the content array
	*
	* Lazy loads content info array.  Used internally so that we only load this if
	* we actually need it (and don't load it multiple times).
	*
	*	This function is exposed publicly for the benefit of code that needs the
	* content array but may not know precisely how to load it (because it isn't
	* aware of the type of content being tagged).
	*
	* Actually, this is a bad idea precisely because the code doesn't know what
	* type its dealing with.  Its a paint to have to create a bunch of getters
	* for the details, but we need to do just that to ensure a consistant
	* interface.
	*
	*	@return array Content info array.
	*/
	protected final function fetch_content_info()
	{
		if (is_null($this->contentinfo))
		{
			$this->contentinfo = $this->load_content_info();
		}
		return $this->contentinfo;
	}

	public function get_title()
	{
		//probably shouldn't leave this as the default, but provides
		//shim code for existing implementations
		$contentinfo = $this->fetch_content_info();
		return $contentinfo['title'];
	}

	/**
	*	Is the tag cloud cachable
	*
	*	This function does not rely on the content information and can be
	* called from an object initialized with a null contentid
	*/
	public function is_cloud_cachable()
	{
		return false;
	}

	/**
	* Get the joins and where filters for this types search cloud query
	*
	*	This function does not rely on the content information and can be
	* called from an object initialized with a null contentid
	*
	*	Not a clean interface, but we've got to do something.  The joins
	* should assume a join to the "tagcontent" table (using that alias).
	* The resulting filters should remove any items that shouldn't
	* be included in the tag count despite being tagged.
	*
	* The result will automatically be filtered by content type so that
	* filter need not be included.
	*
	* @return false|array 'join' => array of join clauses to add to the query
	*  	in the form of 'tablealias' => join clause
	* 	false if this type should not be included in the tag cloud
	*/
	public function fetch_tag_cloud_query_bits()
	{
		return false;
	}

	/********************************************************
	*	Management Page Methods
	********************************************************/
	/*
	 This part of the interface should not be considered somwhere volatile

	 These don't really belong here in their current form
	 They probably don't belong anywhere in their current form
	 but until we figure out a better way to deal with it
	 we're kind of stuck with them.
	*/

	/**
	*	Get the return url for the tag UI
	*
	* This is where we go when we finish saving tag changes.
	*
	*/
	public function fetch_return_url()
	{
		$this->registry->input->clean_array_gpc('r', array(
			'returnurl' => TYPE_STR
		));

		if ($this->registry->GPC_exists['returnurl'])
		{
			return $this->registry->GPC['returnurl'];
		}
		else {
			return "";
		}
	}

	/**
	* Get the page navigation elements for the tag UI
	*/
	public function fetch_page_nav()
	{
		global $vbphrase;

		// navbar and output
		$navbits = array();
		$navbits[''] = $vbphrase['tag_management'];
		$navbits = construct_navbits($navbits);
		return $navbits;
	}

	/**
	*	Verify that the current user has basic rights to manipulate tags for this item
	*
	*	Redirects with appropriate error message if the user can't access the UI.
	*	Its ugly to put it here but the rules very by content type and we want to
	* hide that from the tag UI.
	*
	*	@return should not return if the user does not have permissions.
	*/
	public function verify_ui_permissions()
	{
		global $vbulletin;
		if (!$vbulletin->options['threadtagging'])
		{
			print_no_permission();
		}

		if ( !($this->can_add_tag() OR $this->can_manage_tag()) )
		{
			print_no_permission();
		}
	}

	/********************************************************
	*	Private Methods
	********************************************************/

	/**
	*	Load the Content Info
	*
	* Actually loads the content info for this type
	*
	*	@return array The content info
	*/
	abstract protected function load_content_info();

	/**
	*	Invalidates the cached list of tags for this item.
	*
	*	Should be called by any method that alters the tag
	* types.
	*/
	protected function invalidate_tag_list()
	{
		$this->tags = null;
	}

	/********************************************************
	*	Private Members
	********************************************************/

	protected $registry;
	protected $dbobject;
	protected $contenttypeid;
	protected $contentid;
	protected $contentinfo;
	protected $tags = null;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/
