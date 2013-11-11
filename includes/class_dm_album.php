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

/**
* Class to do data save/delete operations for albums
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Album extends vB_DataManager
{
	/**
	* Array of recognised and required fields for RSS feeds, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'albumid'           => array(TYPE_UINT,       REQ_INCR, 'return ($data > 0);'),
		'userid'            => array(TYPE_UINT,       REQ_YES),
		'createdate'        => array(TYPE_UNIXTIME,   REQ_AUTO),
		'lastpicturedate'   => array(TYPE_UNIXTIME,   REQ_NO),
		'visible'           => array(TYPE_UINT,       REQ_NO),
		'moderation'        => array(TYPE_UINT,       REQ_NO),
		'title'             => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD),
		'description'       => array(TYPE_NOHTMLCOND, REQ_NO),
		'state'             => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array(\'public\', \'private\', \'profile\'))) { $data = \'public\'; } return true; '),
		'coverattachmentid' => array(TYPE_UINT,       REQ_NO)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'album';

	/**
	* Arrays to store stuff to save to album-related tables
	*
	* @var	array
	*/
	var $album = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('albumid = %1$d', 'albumid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Album(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('albumdata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies that the title of the album is valid. Errors if not valid.
	*
	* @param	string	Title of album
	*
	* @return	boolean	True if valid
	*/
	function verify_title(&$title)
	{
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);
		$title = trim($title);

		if ($title === '')
		{
			$this->error('album_title_no_empty');
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

		if (!$this->condition)
		{
			// setup some default values for fields if we didn't explicitly set them
			if (empty($this->setfields['createdate']))
			{
				$this->set('createdate', TIMENOW);
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('albumdata_presave')) ? eval($hook) : false;

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
		if ($this->condition AND $this->fetch_field('state') == 'private' AND $this->existing['state'] != 'private')
		{
			// making an existing album non-public, get rid of bg images
			$this->remove_usercss_background_image();
		}

		($hook = vBulletinHook::fetch_hook('albumdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$contenttypeid = intval($types->getContentTypeID('vBForum_Album'));

		$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_STANDARD, 'attachment');
		$picture_sql = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.filedataid, a.userid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.contentid = " . $this->fetch_field('albumid') . "
					AND
				a.contenttypeid = $contenttypeid
		");
		while ($picture = $this->registry->db->fetch_array($picture_sql))
		{
			$attachdata->set_existing($picture);
			$attachdata->delete(true, false);
		}
		$this->registry->db->free_result($picture_sql);

		$this->remove_usercss_background_image();

		($hook = vBulletinHook::fetch_hook('albumdata_delete')) ? eval($hook) : false;

		return true;
	}


	/**
	 * Removes a Background Image from a customised UserCSS
	 *
	 */
	function remove_usercss_background_image()
	{
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "usercss
			WHERE
				property = 'background_image'
					AND
				value LIKE '" . $this->fetch_field('albumid') . ",%'
					AND
				userid = " . intval($this->fetch_field('userid')) . "
		");
		if ($this->registry->db->affected_rows() AND $this->fetch_field('userid'))
		{
			require_once(DIR . '/includes/class_usercss.php');
			$usercss = new vB_UserCSS($this->registry, $this->fetch_field('userid'), false);
			$usercss->update_css_cache();
		}
	}

	/**
	 * Rebuilds counts for an album
	 *
	 */
	function rebuild_counts()
	{
		if (!$this->fetch_field('albumid'))
		{
			return;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$contenttypeid = intval($types->getContentTypeID('vBForum_Album'));

		$counts = $this->registry->db->query_first("
			SELECT
				SUM(IF(a.state = 'visible', 1, 0)) AS visible,
				SUM(IF(a.state = 'moderation', 1, 0)) AS moderation,
				MAX(IF(a.state = 'visible', a.dateline, 0)) AS lastpicturedate
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.contentid = " . $this->fetch_field('albumid') . "
					AND
				a.contenttypeid = $contenttypeid
		");

		$this->set('visible', $counts['visible']);
		$this->set('moderation', $counts['moderation']);
		$this->set('lastpicturedate', $counts['lastpicturedate']);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
