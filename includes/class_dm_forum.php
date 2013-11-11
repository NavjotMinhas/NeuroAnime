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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

// required for convert_to_valid_html() and others
require_once(DIR . '/includes/adminfunctions.php');

/**
* Class to do data save/delete operations for FORUMS
*
* Example usage (updates forum with forumid = 12):
*
* $f = new vB_DataManager_Forum();
* $f->set_condition('forumid = 12');
* $f->set_info('forumid', 12);
* $f->set('parentid', 5);
* $f->set('title', 'Forum with changed parent');
* $f->save();
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Forum extends vB_DataManager
{
	/**
	* Array of recognised and required fields for forums, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'forumid'           => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'styleid'           => array(TYPE_INT,        REQ_NO,   'if ($data < 0) { $data = 0; } return true;'),
		'title'             => array(TYPE_STR,        REQ_YES,  VF_METHOD),
		'title_clean'       => array(TYPE_STR,        REQ_YES),
		'description'       => array(TYPE_STR,        REQ_NO,   VF_METHOD),
		'description_clean' => array(TYPE_STR,        REQ_NO),
		'options'           => array(TYPE_ARRAY_BOOL, REQ_AUTO),
		'displayorder'      => array(TYPE_UINT,       REQ_NO),
		'replycount'        => array(TYPE_UINT,       REQ_NO),
		'lastpost'          => array(TYPE_UINT,       REQ_NO),
		'lastposter'        => array(TYPE_STR,        REQ_NO),
		'lastposterid'      => array(TYPE_STR,        REQ_NO),
		'lastpostid'        => array(TYPE_UINT,       REQ_NO),
		'lastthread'        => array(TYPE_STR,        REQ_NO),
		'lastthreadid'      => array(TYPE_UINT,       REQ_NO),
		'lasticonid'        => array(TYPE_INT,        REQ_NO),
		'lastprefixid'      => array(TYPE_NOHTML,     REQ_NO),
		'threadcount'       => array(TYPE_UINT,       REQ_NO),
		'daysprune'         => array(TYPE_INT,        REQ_AUTO, 'if ($data == 0) { $data = -1; } return true;'),
		'newpostemail'      => array(TYPE_STR,        REQ_NO,   VF_METHOD, 'verify_emaillist'),
		'newthreademail'    => array(TYPE_STR,        REQ_NO,   VF_METHOD, 'verify_emaillist'),
		'parentid'          => array(TYPE_INT,        REQ_YES,  VF_METHOD),
		'password'          => array(TYPE_NOTRIM,     REQ_NO),
		'link'              => array(TYPE_STR,        REQ_NO), // do not use verify_link on this -- relative redirects are prefectly valid
		'parentlist'        => array(TYPE_STR,        REQ_AUTO, 'return preg_match(\'#^(\d+,)*-1$#\', $data);'),
		'childlist'         => array(TYPE_STR,        REQ_AUTO),
		'showprivate'       => array(TYPE_UINT,       REQ_NO,   'if ($data > 3) { $data = 0; } return true;'),
		'defaultsortfield'  => array(TYPE_STR,        REQ_NO),
		'defaultsortorder'  => array(TYPE_STR,        REQ_NO,   'if ($data != "asc") { $data = "desc"; } return true;'),
		'imageprefix'       => array(TYPE_NOHTML,     REQ_NO,  VF_METHOD)
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array('options' => 'bf_misc_forumoptions');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'forum';

	/**
	* Array to store stuff to save to forum table
	*
	* @var	array
	*/
	var $forum = array();

	/**
	* Array to store stuff to save to tachyforumcounter table
	*
	* @var	array
	*/
	var $tachyforumcounter = array();

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('forumid = %1$d', 'forumid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Forum(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('forumdata_start')) ? eval($hook) : false;
	}


	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		switch($fieldname)
		{
			case 'lastpost':
			case 'lastposter':
			case 'lastposterid':
			case 'lastpostid':
			case 'lastthread':
			case 'lastthreadid':
			case 'lasticonid':
			case 'lastprefixid':
			{
				if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1)
				{
					$table = 'tachyforumpost';
				}
				else
				{
					$table = $this->table;
				}
			}
			break;

			case 'replycount':
			case 'threadcount':
			{
				if (!empty($this->info['coventry']) AND $this->info['coventry']['in_coventry'] == 1)
				{
					$table = 'tachyforumcounter';
				}
				else
				{
					$table = $this->table;
				}
			}
			break;

			default:
			{
				$table = $this->table;
			}
		}

		$this->setfields["$fieldname"] = true;
		$this->{$table}["$fieldname"] =& $value;
	}


	/**
	* Verifies that the given forum title is valid
	*
	* @param	string	Title
	*
	* @return	boolean
	*/
	function verify_title(&$title)
	{
		$this->set('title_clean', htmlspecialchars_uni(strip_tags($title), false));
		$title = convert_to_valid_html($title);


		if ($title == '')
		{
			$this->error('invalid_title_specified');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Converts & to &amp; and sets description_clean for use in meta tags
	*
	* @param	string	Title
	*
	* @return	boolean
	*/
	function verify_description(&$description)
	{
		$this->set('description_clean', htmlspecialchars_uni(strip_tags($description), false));
		$description = convert_to_valid_html($description);

		return true;
	}

	/**
	* Converts an array of 1/0 options into the options bitfield
	*
	* @param	array	Array of 1/0 values keyed with the bitfield names for the forum options bitfield
	*
	* @return	boolean	Returns true on success
	*/
	function verify_options(&$options)
	{
		#require_once(DIR . '/includes/functions_misc.php');
		#return $options = convert_array_to_bits($options, $this->registry->bf_misc_forumoptions);
		trigger_error("Can't set \$this->forum[options] directly - use \$this->set_bitfield('options', $bitname, $onoff) instead", E_USER_ERROR);
	}

	/**
	* Validates a space-separated list of email addresses, prevents duplicates etc.
	*
	* @param	string	Whitespace-separated list of email addresses
	*
	* @return	boolean
	*/
	function verify_emaillist(&$emails)
	{
		$emaillist = array();

		foreach (preg_split('#\s+#s', $emails, -1, PREG_SPLIT_NO_EMPTY) AS $email)
		{
			if ($this->verify_email($email))
			{
				$emaillist["$email"] = $email;
			}
		}

		$emails = implode(' ', $emaillist);

		return true;
	}

	/**
	* Verifies that the parent forum specified exists and is a valid parent for this forum
	*
	* @param	integer	Parent forum ID
	*
	* @return	boolean	Returns true if the parent id is valid, and the parent forum specified exists
	*/
	function verify_parentid(&$parentid)
	{
		if ($parentid == $this->fetch_field('forumid'))
		{
			$this->error('cant_parent_forum_to_self');
			return false;
		}
		else if ($parentid <= 0)
		{
			$parentid = -1;
			return true;
		}
		else if (!isset($this->registry->forumcache["$parentid"]))
		{
			$this->error('invalid_forum_specified');
			return false;
		}
		else if ($this->condition !== null)
		{
			return $this->is_subforum_of($this->fetch_field('forumid'), $parentid);
		}
		else
		{
			// no condition specified, so it's not an existing forum...
			return true;
		}
	}

	/**
	* Verifies that a given forum parent id is not one of its own children
	*
	* @param	integer	The ID of the current forum
	* @param	integer	The ID of the forum's proposed parentid
	*
	* @return	boolean	Returns true if the children of the given parent forum does not include the specified forum... or something
	*/
	function is_subforum_of($forumid, $parentid)
	{
		if (empty($this->registry->iforumcache))
		{
			cache_ordered_forums(0, 1);
		}

		if (is_array($this->registry->iforumcache["$forumid"]))
		{
			foreach ($this->registry->iforumcache["$forumid"] AS $curforumid)
			{
				if ($curforumid == $parentid OR !$this->is_subforum_of($curforumid, $parentid))
				{
					$this->error('cant_parent_forum_to_child');
					return false;
				}
			}
		}

		return true;
	}

	/**
	* Verifies that an image filename prefix is valid
	*
	* @param	string	The image prefix filename
	*
	* @return	boolean
	*/
	function verify_imageprefix(&$prefix)
	{
		if (!preg_match('#^[A-Za-z0-9_./-]*$#', $prefix))
		{
			$this->error('invalid_imageprefix_specified');
			return false;
		}
		return true;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('forumdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($this->condition AND $this->info['applypwdtochild'] AND isset($this->forum['password']) AND $this->forum['password'] != $this->existing['password'])
		{
			$this->dbobject->query_write("
         		UPDATE " . TABLE_PREFIX . "forum
         		SET password = '" . $this->dbobject->escape_string($this->forum['password']) . "'
         		WHERE FIND_IN_SET('" . $this->existing['forumid'] . "', parentlist)
    		");
		}

		if ($this->info['coventry']['in_coventry'] == 1 AND ($this->setfields['replycount'] OR $this->setfields['threadcount']))
		{
			$tachnumrows = $this->dbobject->query_first("
				SELECT COUNT(*) AS numrows FROM " . TABLE_PREFIX . "tachyforumcounter
				WHERE userid = " . $this->info['coventry']['userid'] . "
					AND forumid = " . $this->fetch_field('forumid')
			);
			if ($tachnumrows['numrows'] > 0)
			{
				// updating the existing counters -- this should have things like 'replycount + 1'
				$tachyupdate = '';

				if ($this->setfields['replycount'])
				{
					$tachyupdate .= " replycount = " . $this->tachyforumcounter['replycount'];
				}

				if ($this->setfields['replycount'] AND $this->setfields['threadcount'])
				{
					$tachyupdate .= ",";
				}

				if ($this->setfields['threadcount'])
				{
					$tachyupdate .= " threadcount = " . $this->tachyforumcounter['threadcount'];
				}

				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "tachyforumcounter SET
						$tachyupdate
					WHERE userid = " . $this->info['coventry']['userid'] . "
						AND forumid = " . $this->fetch_field('forumid')
				);

			}
			else
			{
				// first insert - initialize values
				if ($this->setfields['replycount'])
				{
					$this->tachyforumcounter['replycount'] = 1;
				}

				if ($this->setfields['threadcount'])
				{
					$this->tachyforumcounter['threadcount'] = 1;
				}

				$this->tachyforumcounter['userid'] = $this->info['coventry']['userid'];
				$this->tachyforumcounter['forumid'] = $this->fetch_field('forumid');

				$this->dbobject->query_write("
					REPLACE INTO " . TABLE_PREFIX . "tachyforumcounter
						(userid, forumid, threadcount, replycount)
					VALUES
						(" . intval($this->tachyforumcounter['userid']) . ",
						" . intval($this->tachyforumcounter['forumid']) . ",
						" . intval($this->tachyforumcounter['threadcount']) . ",
						" . intval($this->tachyforumcounter['replycount']) . ")
				");

			}
		}

		if ($this->setfields['lastpost'] AND empty($this->info['rebuild']))
		{
			if ($this->info['coventry']['in_coventry'] == 1)
			{
				$this->tachyforumpost['userid'] = $this->info['coventry']['userid'];
				$this->tachyforumpost['forumid'] = $this->fetch_field('forumid');

				$this->dbobject->query_write("
					REPLACE INTO " . TABLE_PREFIX . "tachyforumpost
						(userid, forumid, lastpost, lastposter, lastposterid, lastpostid, lastthread, lastthreadid, lasticonid, lastprefixid)
					VALUES
						(" . intval($this->tachyforumpost['userid']) . ",
						" .  intval($this->tachyforumpost['forumid']) . ",
						" .  intval($this->tachyforumpost['lastpost']) . ",
						'" . $this->dbobject->escape_string($this->tachyforumpost['lastposter']) . "',
						" .  intval($this->tachyforumpost['lastposterid']) . ",
						" .  intval($this->tachyforumpost['lastpostid']) . ",
						'" . $this->dbobject->escape_string($this->tachyforumpost['lastthread']) . "',
						" .  intval($this->tachyforumpost['lastthreadid']) . ",
						" .  intval($this->tachyforumpost['lasticonid']) . ",
						'" . $this->dbobject->escape_string($this->tachyforumpost['lastprefixid']) . "')
				");
			}
			else
			{
				$this->dbobject->query_write("
					DELETE FROM " . TABLE_PREFIX . "tachyforumpost
					WHERE forumid = " . intval($this->fetch_field('forumid'))
				);
			}
		}

		($hook = vBulletinHook::fetch_hook('forumdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed once after all records are updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		if (empty($this->info['disable_cache_rebuild']))
		{
			require_once(DIR . '/includes/adminfunctions.php');
			build_forum_permissions();
		}
	}


	/**
	 * Overridding parent function to add search index updates
	 *
	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	* @param 	bool 	Whether to return the number of affected rows.
	* @param 	bool	Perform REPLACE INTO instead of INSERT
	8 @param 	bool	Perfrom INSERT IGNORE instead of INSERT
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		// Call and get the new id
		$result = parent::save($doquery, $delayed, $affected_rows, $replace, $ignore);
		require_once DIR . '/vb/search/indexcontroller/queue.php' ;
		// Search index maintenance
		vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Forum', 'index',
			  $this->fetch_field('forumid'));

		return $result;
	}


	/**
	* Deletes a forum and its associated data from the database
	*/
	function delete()
	{
		// fetch list of forums to delete
		$forumlist = '';

		// Search index maintenance - Forum delete (calls delete index on threads -> posts)
		require_once DIR . '/vb/search/indexcontroller/queue.php' ;

		$forums = $this->dbobject->query_read_slave("SELECT forumid FROM " . TABLE_PREFIX . "forum WHERE " . $this->condition);
		while($thisforum = $this->dbobject->fetch_array($forums))
		{
			$forumlist .= ',' . $thisforum['forumid'];
			vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'Forum', 'delete', $thisforum['forumid']);
		}
		$this->dbobject->free_result($forums);

		$forumlist = substr($forumlist, 1);

		if ($forumlist == '')
		{
			// nothing to do
			$this->error('invalid_forum_specified');
		}
		else
		{
			$condition = "forumid IN ($forumlist)";

			// delete from extra data tables
			$this->db_delete(TABLE_PREFIX, 'forumpermission', $condition);
			$this->db_delete(TABLE_PREFIX, 'access',          $condition);
			$this->db_delete(TABLE_PREFIX, 'moderator',       $condition);
			$this->db_delete(TABLE_PREFIX, 'announcement',    $condition);
			$this->db_delete(TABLE_PREFIX, 'subscribeforum',  $condition);
			$this->db_delete(TABLE_PREFIX, 'tachyforumpost',  $condition);
			$this->db_delete(TABLE_PREFIX, 'podcast',         $condition);
			$this->db_delete(TABLE_PREFIX, 'forumprefixset',  $condition);

			require_once(DIR . '/includes/functions_databuild.php');

			// delete threads in specified forums
			$threads = $this->dbobject->query_read_slave("SELECT * FROM " . TABLE_PREFIX . "thread WHERE $condition");
			while ($thread = $this->dbobject->fetch_array($threads))
			{
				$threadman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($thread);
				$threadman->set_info('skip_moderator_log', true);
				$threadman->delete($this->registry->forumcache["$thread[forumid]"]['options'] & $this->registry->bf_misc_forumoptions['countposts']);
				unset($threadman);
			}
			$this->dbobject->free_result($threads);

			$this->db_delete(TABLE_PREFIX, 'forum', $condition);

			build_forum_permissions();

			($hook = vBulletinHook::fetch_hook('forumdata_delete')) ? eval($hook) : false;
		}
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>