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

require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/class_taggablecontent.php');

/**
* Class to do data operations for Categories
*
* @package	vBulletin
* @author	Kevin Sours
* @version	$Revision:  $
* @date		$Date: $
*
*/
class vB_DataManager_Tag extends vB_DataManager
{
	/**
	* Array of recognised and required fields for keywords, and their types
	*
	*	Should be protected, but base class is php4 and defines as public by 
	* default.
	*
	* @var	array
	*/
	public $validfields = array (
		"tagid" => array(TYPE_UINT, REQ_INCR, VF_METHOD, 'verify_nonzero'),
		"tagtext" => array(TYPE_NOHTML, REQ_YES, VF_METHOD, 'verify_nonempty'), 
		"canonicaltagid" => array(TYPE_UINT, REQ_NO),
		"dateline" => array(TYPE_UNIXTIME, REQ_YES, VF_METHOD, 'verify_nonzero')
	);

	public $table = "tag";
	public $condition_construct = array('tagid = %1$d', 'tagid');

	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD) 
	{
		parent::__construct($registry, $errtype);
	}

	//*****************************************************************
	// Pseudo static methods
	// These aren't declared static because of the way dms work but 
	// they don't require a record be set before calling.
	
	public function log_tag_search($tagid)
	{
		$this->dbobject->query_write(
			"INSERT INTO " . TABLE_PREFIX . "tagsearch (tagid, dateline) 
				VALUES (" . intval($tagid) . ", " . TIMENOW . ")"
		);
	}


	//*****************************************************************
	// Regular methods

	public function fetch_by_id($id) 
	{
		$this->set_condition("tagid = " . intval($id));
		return $this->load_existing();
	}

	public function fetch_by_tagtext($label) 
	{
		$this->set_condition("tagtext = '" . $this->dbobject->escape_string($label) . "'");
		return $this->load_existing();
	}


	/**
	*	Return the array of fields that most the code expects to consume
	*/
	public function fetch_fields()
	{
		$fields = $this->existing;

		if (isset($this->{$this->table})) 
		{
			foreach ($this->{$this->table} as $name => $value) {
				$field[$name] = $value;
			}
		}
		return $fields;
	}

	public function fetch_synonyms() 
	{
		$set = $this->dbobject->query_read("
		  SELECT * 
			FROM " . TABLE_PREFIX . $this->table . "
			WHERE canonicaltagid = " . intval($this->fetch_field("tagid")) . "
			ORDER BY tagtext
		");

		$synonyms = array();
		while ($row = $this->dbobject->fetch_array($set)) 
		{
			$synonym = datamanager_init('tag', $this->registry, ERRTYPE_ARRAY);
			$result = $synonym->set_existing($row);
			$synonyms[] = $synonym;

			//force the reference to change so that we don't end up with every 
			//array linked (which makes them all change to be the same when one
			//changes).
			unset($row);
		}
		return $synonyms;	
	}

	public function is_synonym() 
	{
		return $this->fetch_field("canonicaltagid") > 0;
	}

	public function fetch_canonical_tag() 
	{ 
		if (!$this->is_synonym()) 
		{
			return false;
		}

		$tag = datamanager_init('tag', $this->registry, ERRTYPE_ARRAY);
		$tag->fetch_by_id($this->fetch_field("canonicaltagid"));
		return $tag;
	}

	public function attach_content($type, $id) 
	{
		$this->dbobject->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "tagcontent (contenttypeid, contentid, tagid, userid, dateline) VALUES(
				'" . intval($type) . "', 
				" . intval($id) . ", 
				" . intval($this->fetch_field("tagid")) . ",
				"	. $this->registry->userinfo['userid'] . ",
				" . TIMENOW . "
				)
		"); 
	}


	public function detach_content($type, $id) 
	{
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "tagcontent 
			WHERE entitytype = '" . $this->dbobject->escape_string($type) . "' AND 
				entityid = " . intval($id) . " AND
				tagid = " . intval($this->fetch_field("tagid")) 
		); 
	}

	/**
	*	Make this tag a synonym for another tag
	*
	*	Any associations between this tag and content will be transfered to the parent.
	*
	* @param int $canonical_id id for the tag that this will become the synonym of
	*/
	public function make_synonym($canonical_id) 
	{
		//if we already have synonyms attach them to the new canonical id as well
		//we only allow one level of synonyms
		foreach ($this->fetch_synonyms() as $synonym)
		{
			$synonym->make_synonym($canonical_id);
		}

		//actually make this a synonym
		$this->set("canonicaltagid", $canonical_id);
		$this->save();

		//fix any associated content items
		$associated_content = $this->fetch_associated_content();
		foreach ($associated_content as $contenttypeid => $contentids)
		{
			foreach ($contentids as $contentid)
			{
				$replace_threads[] = "($canonical_id, " . intval($contenttypeid) . ", $contentid, " . 
					$this->registry->userinfo['userid'] . ", " . TIMENOW . ")";
			}
		}

		// add new tag to affected threads
		if (sizeof($replace_threads))
		{	
			$this->dbobject->query_write("
				REPLACE INTO " . TABLE_PREFIX . "tagcontent (tagid, contenttypeid, contentid, userid, dateline) VALUES " .
				implode(',', $replace_threads) . "
			");
		}

		//clear old category associations.
		$this->dbobject->query_write("
			DELETE FROM " . TABLE_PREFIX . "tagcontent WHERE tagid = " . intval($this->fetch_field("tagid"))
		);

		$this->handle_associated_content_removals($associated_content);


		//update the tag search cloud datastore to reflect the change
		$this->dbobject->query_write("
			UPDATE " . TABLE_PREFIX . "tagsearch SET tagid = " . intval($canonical_id). " 
				WHERE tagid = " . intval($this->fetch_field("tagid"))
		);

		return true;
	}

	/**
	* Unlink a synonym from its canonical parent.
	*
	* This will not reestablish any relationships between the tag and content that
	* may have been transfered to the parent when the tag was made a synonym.
	*/
	public function make_independent() 
	{
		$this->set("canonicaltagid", 0);
		$this->save();
	}

	public function pre_save($doquery) 
	{
		if (!$this->condition AND !$this->fetch_field('dateline'))
		{
			$this->set('dateline', TIMENOW);
		}
		return true;
	}

	public function pre_delete($doquery)
	{
		$contentsql = "DELETE FROM " . TABLE_PREFIX . "tagcontent WHERE tagid = " . 
			intval($this->fetch_field("tagid"));

		$searchtagssql = "DELETE FROM " . TABLE_PREFIX . "tagsearch WHERE tagid = " . 
			intval($this->fetch_field("tagid"));


		//if we have synonyms for this tag, delete those as well.
		if ($doquery) 
		{
			foreach ($this->fetch_synonyms() as $synonym)
			{
				$synonym->delete();
			}

			$associated_content = $this->fetch_associated_content();	
			$this->dbobject->query_write($contentsql);
			$this->dbobject->query_write($searchtagssql);
			$this->handle_associated_content_removals($associated_content);
		}
		else 
		{
			//probably should log the other non sql actions
			$this->log_query($contentsql);
			$this->log_query($searchtagssql);
		}
		return true;
	}

	private function fetch_associated_content() 
	{
		$result = $this->dbobject->query_read("
			SELECT contenttypeid, contentid
			FROM " . TABLE_PREFIX . "tagcontent
			WHERE tagid = " . intval($this->fetch_field("tagid"))
		);

		$associated_content = array();
		while (list($contenttypeid, $contentid) = $this->dbobject->fetch_row($result))
		{
			$associated_content[$contenttypeid][] = $contentid;
		}
		$this->dbobject->free_result($result);
		return $associated_content;
	}

	/**
	*	Propogate any content specific effects for removing this tag from a content item
	*
	*	Some content tables may store the list of associated tags in as part of the record
	* in addition to the main association table.  While this is more efficient for lookups
	* we need to keep track of it when tags are removed.  This can happen because a tag 
	* was deleted or because it was replaced with another tag.
	*
	* @param Array $associated_content An array of the form 'contenttypeid' => 'contentids' 
	* 	containing all of the content ids associated with a particular tag
	*/
	private function handle_associated_content_removals($associated_content) 
	{
		//As we add additional content types, we should look at how this logic can be 
		//generalized

		// update thread taglists
		foreach ($associated_content as $contenttypeid => $contendids) 
		{
			foreach ($contendids as $contendid)
			{
				$item = vB_Taggable_Content_Item::create($this->registry, $contenttypeid, $contendid);
				if ($item)
				{
					$item->rebuild_content_tags();
				}
			}
		}
	}

	/*
		Should probably get moved to the base class, but I'm not quite ready to 
		do that.
	*/

	protected function load_existing() 
	{
		if ($this->condition) 
		{
			$fields = array_keys($this->validfields);
			$result = $this->dbobject->query_first_slave ( 
				"SELECT " . implode(", ", $fields) . " " .
				"FROM " . TABLE_PREFIX . $this->table . " " . 
				"WHERE " . $this->condition
			);
			if ($result) 
			{
				$this->set_existing($result);	

				//reset to the default condition so that we use the primary key to 
				//do the update.  This is especially important if somebody does something
				//stupid and calls this function on a condition that selects more than one
				//record -- we could end up updating multiple records if we don't do this.
				$this->set_condition('');
			}
			else {
				//if we don't find a record, then reset the condition so that we will
				//do an insert rather than attempt to update an non existant record
				$this->condition = null;
			}
			return $result;
		}
		else 
		{
			throw new Exception("Fetch existing requires a condition");
		}
	}

	/**
	* Allows future extension to do something other than a raw echo when doquery=false
	*/
	protected function log_query($sql) 
	{
		echo "<pre>$sql<hr /></pre>";
	}
}

/**
* Class to do data operations for Categories
*
* @package	vBulletin
* @author	Kevin Sours
* @version	$Revision:  $
* @date		$Date: $
*
*/
class vB_DataManager_category extends vB_DataManager
{
	/*
		Some high level fetch functions.  These don't really use the DM class
		at all, but I want to put the sql all in one place and this is as 
		good as any.
	*/
	
	public function get_all_categories() 
	{
		$set = $this->dbobject->query_read("
			SELECT * 
			FROM " . TABLE_PREFIX . $this->table . "
			ORDER BY displayorder
			"
		);
		$rows = array();
		while ($row = $this->dbobject->fetch_array($set)) 
		{
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	* Array of recognised and required fields for keywords, and their types
	*
	*	Should be protected, but base class is php4 and defines as public by 
	* default.
	*
	* @var	array
	*/
	public $validfields = array (
		"categoryid" => array(TYPE_UINT, REQ_AUTO, VF_METHOD, 'verify_nonzero'),
		"parentid" => array(TYPE_INT, REQ_NO, VF_METHOD, 'verify_nonzero_or_negone'),
		"styleid" => array(TYPE_INT, REQ_NO, VF_METHOD, 'verify_nonzero_or_negone'),
		"labeltext" => array(TYPE_NOHTML, REQ_YES, VF_METHOD, 'verify_nonempty'), 
		"labelhtml" => array(TYPE_NOTRIM, REQ_NO),
		"displayorder" => array(TYPE_INT, REQ_NO) 
	);

	public $table = "category";
	public $condition_construct = array('categoryid = %1$d', 'categoryid');

	public function fetch_by_id($id) 
	{
		$this->set_condition("categoryid = " . intval($id));
		return $this->load_existing();
	}

	public function make_child($parentid) 
	{
		if ($parentid == -1 or $parentid > 0) 
		{
			$this->set('parentid', $parentid);
			return $this->save();
		}
		return false;
	}

	public function make_top() 
	{
		return $this->make_child(-1);
	}

	public function get_root_nodes() 
	{
		return $this->get_children_from_parentid(-1);
	}

	public function get_siblings()
	{
		return $this->get_children_from_parentid($this->fetch_field("parentid"));
	}

	public function get_children() 
	{
		return $this->get_children_from_parentid($this->fetch_field("categoryid"));
	}

	public function reorder_siblings($childids) 
	{
		$child_positions = array_flip($childids);
		$children = $this->get_siblings();

		$extra = count($child_positions);
		foreach ($children as $child) 
		{
			$id = $child->fetch_field("categoryid");
			if (array_key_exists($id, $child_positions)) 
			{
				$order = $child_positions[$id];
			}
			else 
			{
				$order = $extra;
				$extra++;
			}
			$child->set("displayorder", $order);
			$child->save();
		}
	}

	public function pre_delete() 
	{
		//cascade deletes
		foreach ($this->get_children() as $child)
		{
			$child->delete();
		}

		
		return true;
	}

	private function promote_children() 
	{
		$sql = "
			UPDATE " . TABLE_PREFIX . $this->table . "
			SET parentid = " . intval($this->fetch_field('parentid')) . ",
				displayorder = " . intval($this->fetch_field('displayorder')) . "
			WHERE parentid = " . intval($this->fetch_field('categoryid')) ;
		$this->dbobject->query_write($sql);
	}

	private function get_children_from_parentid($parentid) 
	{
		$set = $this->dbobject->query_read("
		  SELECT * 
			FROM " . TABLE_PREFIX . $this->table . "
			WHERE parentid = " . intval($parentid)
		);
		$children = array();
		while ($row = $this->dbobject->fetch_array($set)) 
		{
			$child = datamanager_init('category', $this->registry, ERRTYPE_ARRAY, "tag");
			$result = $child->set_existing($row);
			$children[] = $child;

			//force the reference to change so that we don't end up with every 
			//array linked (which makes them all change to be the same when one
			//changes).
			unset($row);
		}
		return $children;	
	}


	/*
		Should probably get moved to the base class, but I'm not quite ready to 
		do that.
	*/
	protected function load_existing() 
	{
		if ($this->condition) 
		{
			$fields = array_keys($this->validfields);
			$result = $this->dbobject->query_first_slave ( 
				"SELECT " . implode(", ", $fields) . " " .
				"FROM " . TABLE_PREFIX . $this->table . " " . 
				"WHERE " . $this->condition
			);
			if ($result) 
			{
				$this->set_existing($result);	

				//reset to the default condition so that we use the primary key to 
				//do the update.  This is especially important if somebody does something
				//stupid and calls this function on a condition that selects more than one
				//record -- we could end up updating multiple records if we don't do this.
				$this->set_condition('');
			}
			else {
				//if we don't find a record, then reset the condition so that we will
				//do an insert rather than attempt to update an non existant record
				$this->condition = null;
			}
			return $result;
		}
		else 
		{
			throw new Exception("Fetch existing requires a condition");
		}
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27979 $
|| ####################################################################
\*======================================================================*/
?>
