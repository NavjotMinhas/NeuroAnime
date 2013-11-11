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

// Temporary
require_once(DIR . '/includes/functions_file.php');

/**
* Abstract class to do data save/delete operations for ATTACHMENTS.
*
* @package	vBulletin
* @version	$Revision: 45684 $
* @date		$Date: 2011-07-08 12:48:02 -0700 (Fri, 08 Jul 2011) $
*/
abstract class vB_DataManager_AttachData extends vB_DataManager
{
	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* Storage holder
	*
	* @var  array   Storage Holder
	*/
	var $lists = array();

	/**
	* Storage Type
	*
	* @var  string
	*/
	var $storage = 'db';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		$this->storage = $registry->options['attachfile'] ? 'fs' : 'db';

		($hook = vBulletinHook::fetch_hook('attachdata_start')) ? eval($hook) : false;
	}

	/**
	* Set the extension of the filename
	*
	* @param	filename
	*
	* @return	boolean
	*/
	function verify_filename(&$filename)
	{
		$ext_pos = strrpos($filename, '.');
		if ($ext_pos !== false)
		{
			$extension = substr($filename, $ext_pos + 1);
			// 100 (filename length in DB) - 1 (.) - length of extension
			$filename = substr($filename, 0, min(100 - 1 - strlen($extension), $ext_pos)) . ".$extension";
		}
		else
		{
			$extension = '';
		}

		if ($this->validfields['extension'])
		{
			$this->set('extension', strtolower($extension));
		}
		return true;
	}

	/**
	* Set the filesize of the thumbnail
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_thumbnail(&$thumbnail)
	{
		if (strlen($thumbnail) > 0)
		{
			$this->set('thumbnail_filesize', strlen($thumbnail));
		}
		return true;
	}

	/**
	* Set the filehash/filesize of the file
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_filedata(&$filedata)
	{
		if (strlen($filedata) > 0)
		{
			$this->set('filehash', md5($filedata));
			$this->set('filesize', strlen($filedata));
		}

		return true;
	}

	/**
	* Verify that posthash is either md5 or empty
	* @param	string the md5
	*
	* @return	boolean
	*/
	function verify_md5_alt(&$md5)
	{
		return (empty($md5) OR (strlen($md5) == 32 AND preg_match('#^[a-f0-9]{32}$#', $md5)));
	}

	/**
	* database pre_save method that only applies to subclasses that have filedata fields
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save_filedata($doquery = true)
	{
		if ($this->condition === null)
		{
			if ($this->fetch_field('filehash', 'filedata'))
			{
				$filehash = $this->fetch_field('filehash', 'filedata');
			}
			else if (!empty($this->info['filedata_location']) AND file_exists($this->info['filedata_location']))
			{
				$filehash = md5_file(($this->info['filedata_location']));
			}
			else if (!empty($this->info['filedata']))
			{
				$filehash = md5($this->info['filedata']);
			}
			else if ($this->fetch_field('filedata', 'filedata'))
			{
				$filehash = md5($this->fetch_field('filedata', 'filedata'));
			}

			// Does filedata already exist?
			if ($filehash AND $fd = $this->registry->db->query_first("
				SELECT filedataid
				FROM " . TABLE_PREFIX . "filedata
				WHERE filehash = '" . $this->registry->db->escape_string($filehash) . "'
			"))
			{
				// file already exists so we are not going to insert a new one
				return $fd['filedataid'];
			}
		}

		if ($this->storage == 'db')
		{
			if (!empty($this->info['filedata_location']) AND file_exists($this->info['filedata_location']))
			{
				$this->set_info('filedata', file_get_contents($this->info['filedata_location']));
			}

			if (!empty($this->info['filedata']))
			{
				$this->setr('filedata', $this->info['filedata']);
			}

			if (!empty($this->info['thumbnail']))
			{
				$this->setr('thumbnail', $this->info['thumbnail']);
			}
		}
		else	// Saving in the filesystem
		{
			// make sure we don't have the binary data set
			// if so move it to an information field
			// benefit of this is that when we "move" files from DB to FS,
			// the filedata/thumbnail fields are not blanked in the database
			// during the update.
			if ($file =& $this->fetch_field('filedata', 'filedata'))
			{
				$this->setr_info('filedata', $file);
				$this->do_unset('filedata', 'filedata');
			}

			if ($thumb =& $this->fetch_field('thumbnail', 'filedata'))
			{
				$this->setr_info('thumbnail', $thumb);
				$this->do_unset('thumbnail', 'filedata');
			}

			if (!empty($this->info['filedata']))
			{
				$this->set('filehash', md5($this->info['filedata']), true, true, 'filedata');
				$this->set('filesize', strlen($this->info['filedata']), true, true, 'filedata');
			}
			else if (!empty($this->info['filedata_location']) AND file_exists($this->info['filedata_location']))
			{
				$this->set('filehash', md5_file($this->info['filedata_location']), true, true, 'filedata');
				$this->set('filesize', filesize($this->info['filedata_location']), true, true, 'filedata');
			}

			if (!empty($this->info['thumbnail']))
			{
				$this->set('thumbnail_filesize', strlen($this->info['thumbnail']), true, true, 'filedata');
			}

			if (!empty($this->info['filedata']) OR !empty($this->info['thumbnail']) OR !empty($this->info['filedata_location']))
			{
				$path = $this->verify_attachment_path($this->fetch_field('userid', 'filedata'));
				if (!$path)
				{
					$this->error('attachpathfailed');
					return false;
				}

				if (!is_writable($path))
				{
					$this->error('upload_file_system_is_not_writable_path', htmlspecialchars($path));
					return false;
				}
			}
		}

		return true;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each_filedata($doquery = true)
	{
		if ($this->storage == 'fs')
		{
			$filedataid =& $this->fetch_field('filedataid', 'filedata');
			$userid =& $this->fetch_field('userid', 'filedata');
			$failed = false;

			// Check for filedata in an information field
			if (!empty($this->info['filedata']))
			{
				$filename = fetch_attachment_path($userid, $filedataid);
				if ($fp = fopen($filename, 'wb'))
				{
					if (!fwrite($fp, $this->info['filedata']))
					{
						$failed = true;
					}
					fclose($fp);
					#remove possible existing thumbnail in case no thumbnail is written in the next step.
					if (file_exists(fetch_attachment_path($userid, $filedataid, true)))
					{
						@unlink(fetch_attachment_path($userid, $filedataid, true));
					}
				}
				else
				{
					$failed = true;
				}
			}
			else if (!empty($this->info['filedata_location']))
			{
				$filename = fetch_attachment_path($userid, $filedataid);
				if (@rename($this->info['filedata_location'], $filename))
				{
					$mask = 0777 & ~umask();
					@chmod($filename, $mask);

 					if (file_exists(fetch_attachment_path($userid, $filedataid, true)))
					{
						@unlink(fetch_attachment_path($userid, $filedataid, true));
					}
				}
				else
				{

					$failed = true;
				}
			}

			if (!$failed AND !empty($this->info['thumbnail']))
			{
				// write out thumbnail now
				$filename = fetch_attachment_path($userid, $filedataid, true);
				if ($fp = fopen($filename, 'wb'))
				{
					if (!fwrite($fp, $this->info['thumbnail']))
					{
						$failed = true;
					}
					fclose($fp);
				}
				else
				{
					$failed = true;
				}
			}

			($hook = vBulletinHook::fetch_hook('attachdata_postsave')) ? eval($hook) : false;

			if ($failed)
			{
				if ($this->condition === null) // Insert, delete filedata
				{
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "filedata
						WHERE filedataid = $filedataid
					");
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "attachmentcategoryuser
						WHERE filedataid = $filedataid
					");
				}

				// $php_errormsg is automatically set if track_vars is enabled
				$this->error('upload_copyfailed', htmlspecialchars_uni($php_errormsg), fetch_attachment_path($userid));
				return false;
			}
			else
			{
				return true;
			}
		}
	}

	/**
	* Any code to run before deleting.
	*
	* @param	string	What are we deleteing?
	*/
	function pre_delete($type = 'attachment', $doquery = true, $checkperms = true)
	{
		$this->lists['content'] = array();
		$this->lists['filedataids'] = array();
		$this->lists['attachmentids'] = array();
		$this->lists['picturecomments'] = array();
		$this->lists['userids'] = array();
		$this->set_info('type', $type);

		if ($type == 'filedata')
		{
			$ids = $this->registry->db->query_read("
				SELECT a.attachmentid, fd.userid, fd.filedataid, a.userid AS auserid, a.contenttypeid
				FROM " . TABLE_PREFIX . "filedata AS fd
				LEFT JOIN " . TABLE_PREFIX . "attachment AS a ON (a.filedataid = fd.filedataid)
				WHERE " . $this->condition
			);
		}
		else
		{
			$ids = $this->registry->db->query_read("
				SELECT a.attachmentid, fd.userid, fd.filedataid, a.userid AS auserid, a.contenttypeid
				FROM " . TABLE_PREFIX . "attachment AS a
				LEFT JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
				WHERE " . $this->condition
			);
		}
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($id['attachmentid'])
			{
				$this->lists['content']["$id[contenttypeid]"][] = $id['attachmentid'];
				$this->lists['attachmentids'][] = $id['attachmentid'];
				$this->lists['userids']["$id[auserid]"] = 1;
				if ($id['filedataid'] AND $id['auserid'])
				{
					$this->lists['picturecomments'][] = "(filedataid = $id[filedataid] AND userid = $id[auserid])";
				}
			}
			if ($id['filedataid'])
			{
				$this->lists['filedataids']["$id[filedataid]"] = $id['userid'];
			}
		}

		require_once(DIR . '/packages/vbattach/attach.php');
		if ($this->registry->db->num_rows($ids) == 0)
		{	// nothing to delete
			return false;
		}
		else
		{
			foreach ($this->lists['content'] AS $contenttypeid => $list)
			{
				if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
				{
					return false;
				}
				if (!$attach->pre_delete($list, $checkperms, $this))
				{
					return false;
				}
				unset($attach);
			}
		}

		return parent::pre_delete($doquery);
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($contenttypeid = intval($this->fetch_field('contenttypeid')))
		{
			require_once(DIR . '/packages/vbattach/attach.php');
			if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
			{
				return false;
			}
			$attach->post_save_each($this);
		}
		return parent::post_save_each($doquery);
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		foreach ($this->lists['content'] AS $contenttypeid => $list)
		{
			if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
			{
				return false;
			}
			$attach->post_delete($this);
			unset($attach);
		}
		// Update the refcount in the filedata table
		if (!empty($this->lists['filedataids']))
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "filedata AS fd
				SET fd.refcount = (
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE fd.filedataid = a.filedataid
				)
				WHERE fd.filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");
		}
		// Hourly cron job will clean out the FS where refcount = 0 and dateline > 1 hour

		// Below here only applies to attachments in pictures/groups but I forsee all attachments gaining the ability to have comments
		if ($this->info['type'] == 'filedata')
		{
			if (!empty($this->lists['filedataids']))
			{
				$this->registry->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "picturecomment
					WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
				");
			}
		}
		else if (!empty($this->lists['picturecomments']))	// deletion type is by attachment
		{
			foreach ($this->lists['picturecomments'] AS $sql)
			{
				if (!($results = $this->registry->db->query_first("
					SELECT a.attachmentid
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE
						$sql
				")))
				{
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "picturecomment
						WHERE
							$sql
					");
				}
			}
		}

		require_once(DIR . '/includes/functions_picturecomment.php');
		foreach (array_keys($this->lists['userids']) AS $userid)
		{
			build_picture_comment_counters($userid);
		}

		return parent::post_delete($doquery);
	}

	/**
	* Verify that user's attach path exists, create if it doesn't
	*
	* @param	int		userid
	*/
	function verify_attachment_path($userid)
	{
		// Allow userid to be 0 since vB2 allowed guests to post attachments
		$userid = intval($userid);

		$path = fetch_attachment_path($userid);
		if (vbmkdir($path))
		{
			return $path;
		}
		else
		{
			return false;
		}
	}
}

/**
* Class to do data save/delete operations for just the Attachment table
*
* @package	vBulletin
* @version	$Revision: 45684 $
* @date		$Date: 2011-07-08 12:48:02 -0700 (Fri, 08 Jul 2011) $
*/

class vB_DataManager_Attachment extends vB_DataManager_AttachData
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'attachmentid'   => array(TYPE_UINT,       REQ_INCR),
		'filedataid'     => array(TYPE_UINT,       REQ_YES),
		'userid'         => array(TYPE_UINT,       REQ_YES),
		'filename'       => array(TYPE_STR,        REQ_YES,  VF_METHOD, 'verify_filename'),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'state'          => array(TYPE_STR,        REQ_NO),
		'counter'        => array(TYPE_UINT,       REQ_NO),
		'posthash'       => array(TYPE_STR,        REQ_NO,   VF_METHOD, 'verify_md5_alt'),
		'contenttypeid'  => array(TYPE_UINT,       REQ_YES),
		'contentid'      => array(TYPE_UINT,       REQ_NO),
		'caption'        => array(TYPE_NOHTMLCOND, REQ_NO),
		'reportthreadid' => array(TYPE_UINT,       REQ_NO),
		'displayorder'   => array(TYPE_UINT,       REQ_NO),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'attachment';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('attachmentid = %1$d', 'attachmentid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('attachdata_start')) ? eval($hook) : false;
	}

	public function pre_delete($doquery = true, $checkperms = true)
	{
		return parent::pre_delete('attachment', $doquery, $checkperms);
	}

	/**
	* Delete from the attachment table
	*
	*/
	public function delete($doquery = true, $checkperms = true)
	{
		if (!$this->pre_delete($doquery, $checkperms) OR empty($this->lists['attachmentids']))
		{
			return false;
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "attachment
			WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
		");

		$this->post_delete($doquery);

		return true;
	}

	/**
	* Any code to run before approving
	*
	* @param	bool	Verify permissions
	*/
	function pre_moderate($checkperms = true, $type = 'approve')
	{
		$this->lists['content'] = array();
		$this->lists['attachmentids'] = array();
		$this->lists['userids'] = array();

		$ids = $this->registry->db->query_read("
			SELECT a.attachmentid, a.userid AS auserid, a.contenttypeid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE " . $this->condition . "
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($id['attachmentid'])
			{
				$this->lists['content']["$id[contenttypeid]"][] = $id['attachmentid'];
				$this->lists['attachmentids'][] = $id['attachmentid'];
				$this->lists['userids']["$id[auserid]"] = 1;
			}
		}

		require_once(DIR . '/packages/vbattach/attach.php');
		if ($this->registry->db->num_rows($ids) == 0)
		{	// nothing to approve
			return false;
		}
		else
		{
			foreach ($this->lists['content'] AS $contenttypeid => $list)
			{
				if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
				{
					return false;
				}
				if ($type == 'approve')
				{
					if (!$attach->pre_approve($list, $checkperms, $this))
					{
						return false;
					}
				}
				else
				{
					if (!$attach->pre_unapprove($list, $checkperms, $this))
					{
						return false;
					}
				}
				unset($attach);
			}
		}

		return true;
	}

	/**
	* Approve in the attachment table
	*
	*/
	public function approve($checkperms = true)
	{
		if (!$this->pre_moderate($checkperms, 'approve') OR empty($this->lists['attachmentids']))
		{
			return false;
		}

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET state = 'visible'
			WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
		");

		$this->post_moderate('approve');

		return true;
	}

	/**
	* Unapprove in the attachment table
	*
	*/
	public function unapprove($checkperms = true)
	{
		if (!$this->pre_moderate($checkperms, 'unapprove') OR empty($this->lists['attachmentids']))
		{
			return false;
		}

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET state = 'moderation'
			WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
		");

		$this->post_moderate('unapprove');

		return true;
	}

	/**
	* Any code to run after approving
	*
	*/
	function post_moderate($type = 'approve')
	{
		foreach ($this->lists['content'] AS $contenttypeid => $list)
		{
			if (!($attach =& vB_Attachment_Dm_Library::fetch_library($this->registry, $contenttypeid)))
			{
				return false;
			}
			if ($type == 'approve')
			{
				$attach->post_approve($this);
			}
			else
			{
				$attach->post_unapprove($this);
			}
			unset($attach);
		}

		return true;
	}

	/**
	* Saves the data from the object into the specified database tables
	* Overwrites parent
	*
 	* @param	boolean	Do the query?
	* @param	mixed	Whether to run the query now; see db_update() for more info
	* @param bool 	Whether to return the number of affected rows.
	* @param bool		Perform REPLACE INTO instead of INSERT
	* @param bool		Perfrom INSERT IGNORE instead of INSERT
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false, $affected_rows = false, $replace = false, $ignore = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
			// If no displayorder is set then default displayorder to be order of attachment insertion
			if (!$this->fetch_field('displayorder'))
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "attachment SET displayorder = $return WHERE attachmentid = $return
				");
			}
			$this->set('attachmentid', $return);
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed, $affected_rows);
		}

		if ($return AND $this->post_save_each($doquery) AND $this->post_save_once($doquery))
		{
			return $return;
		}
		else
		{
			return false;
		}
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($filedataid = intval($this->attachment['filedataid']) AND $this->condition === null)
		{
			// Update the refcount in the filedata table
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "filedata AS fd
				SET fd.refcount = (
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE fd.filedataid = a.filedataid
					GROUP BY a.filedataid
				)
				WHERE fd.filedataid = $filedataid
			");
		}

		return parent::post_save_each($doquery);
	}
}

/**
* Class to do data save/delete operations for just the Filedata table
*
* @package	vBulletin
* @version	$Revision: 45684 $
* @date		$Date: 2011-07-08 12:48:02 -0700 (Fri, 08 Jul 2011) $
*/
class vB_DataManager_Filedata extends vB_DataManager_AttachData
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'filedataid'         => array(TYPE_UINT,     REQ_INCR),
		'userid'             => array(TYPE_UINT,     REQ_YES),
		'dateline'           => array(TYPE_UNIXTIME, REQ_AUTO),
		'filedata'           => array(TYPE_BINARY,   REQ_NO,   VF_METHOD),
		'filesize'           => array(TYPE_UINT,     REQ_YES),
		'filehash'           => array(TYPE_STR,      REQ_YES,  VF_METHOD, 'verify_md5'),
		'thumbnail'          => array(TYPE_BINARY,   REQ_NO,   VF_METHOD),
		'thumbnail_dateline' => array(TYPE_UNIXTIME, REQ_AUTO),
		'thumbnail_filesize' => array(TYPE_UINT,     REQ_NO),
		'extension'          => array(TYPE_STR,      REQ_YES),
		'refcount'           => array(TYPE_UINT,     REQ_NO),
		'width'              => array(TYPE_UINT,     REQ_NO),
		'height'             => array(TYPE_UINT,     REQ_NO),
		'thumbnail_width'    => array(TYPE_UINT,     REQ_NO),
		'thumbnail_height'   => array(TYPE_UINT,     REQ_NO),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'filedata';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('filedataid = %1$d', 'filedataid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('attachdata_start')) ? eval($hook) : false;
	}

	public function pre_delete($doquery = true)
	{
		return parent::pre_delete('filedata', $doquery);
	}

	public function delete($doquery = true)
	{
		if (!$this->pre_delete($doquery) OR empty($this->lists['filedataids']))
		{
			return $false;
		}

		if (!empty($this->lists['filedataids']))
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "filedata
				WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "attachmentcategoryuser
				WHERE filedataid IN (" . implode(", ", array_keys($this->lists['filedataids'])) . ")
			");

			if ($this->storage == 'fs')
			{
				require_once(DIR . '/includes/functions_file.php');
				foreach ($this->lists['filedataids'] AS $filedataid => $userid)
				{
					@unlink(fetch_attachment_path($userid, $filedataid));
					@unlink(fetch_attachment_path($userid, $filedataid, true));
				}
			}

			// unset filedataids so that the post_delete function doesn't bother calculating refcount for the records that we just removed
			unset($this->lists['filedataids']);
		}

		if (!empty($this->lists['attachmentids']))
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "attachment
				WHERE attachmentid IN (" . implode(", ", $this->lists['attachmentids']) . ")
			");
		}

		$this->post_delete();

		return true;
	}

	/**
	* Saves the data from the object into the specified database tables
	* Overwrites parent
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		if ($filedataid = $this->pre_save_filedata($doquery))
		{
			if (!$filedataid)
			{
				return false;
			}

			if ($filedataid !== true)
			{
				// this is an insert and file already exists
				$this->attachment['filedataid'] = $this->filedata['filedataid'] = $filedataid;

				$this->post_save_each($doquery);
				$this->post_save_once($doquery);

				// this is an insert and file already exists
				return $filedataid;
			}
		}

		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
			$this->set('filedataid', $return);
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed);
		}

		if ($return AND $this->post_save_each($doquery) AND $this->post_save_once($doquery) AND $this->post_save_each_filedata($doquery))
		{
			return $return;
		}
		else
		{
			return false;
		}
	}
}

/**
* Class to do data save/delete operations for Attachment/Filedata table
*
* @package	vBulletin
* @version	$Revision: 45684 $
* @date		$Date: 2011-07-08 12:48:02 -0700 (Fri, 08 Jul 2011) $
*/
class vB_DataManager_AttachmentFiledata extends vB_DataManager_Attachment
{
	var $validfields = array(
		//attachment fields
		'attachmentid'       => array(TYPE_UINT,       REQ_INCR),
		'state'              => array(TYPE_STR,        REQ_NO),
		'counter'            => array(TYPE_UINT,       REQ_NO),
		'posthash'           => array(TYPE_STR,        REQ_NO,   VF_METHOD, 'verify_md5_alt'),
		'contenttypeid'      => array(TYPE_UINT,       REQ_YES),
		'contentid'          => array(TYPE_UINT,       REQ_NO),
		'caption'            => array(TYPE_NOHTMLCOND, REQ_NO),
		'reportthreadid'     => array(TYPE_UINT,       REQ_NO),
		'displayorder'       => array(TYPE_UINT,       REQ_NO),

		// Shared fields
		'userid'             => array(TYPE_UINT,       REQ_YES),
		'dateline'           => array(TYPE_UNIXTIME,   REQ_AUTO),
		'filename'           => array(TYPE_STR,        REQ_YES,  VF_METHOD, 'verify_filename'),

		// filedata fields
		'filedata'           => array(TYPE_BINARY,     REQ_NO,   VF_METHOD),
		'filesize'           => array(TYPE_UINT,       REQ_YES),
		'filehash'           => array(TYPE_STR,        REQ_YES,  VF_METHOD, 'verify_md5'),
		'thumbnail'          => array(TYPE_BINARY,     REQ_NO,   VF_METHOD),
		'thumbnail_dateline' => array(TYPE_UNIXTIME,   REQ_AUTO),
		'thumbnail_filesize' => array(TYPE_UINT,       REQ_NO),
		'extension'          => array(TYPE_STR,        REQ_YES),
		'refcount'           => array(TYPE_UINT,       REQ_NO),
		'width'              => array(TYPE_UINT,       REQ_NO),
		'height'             => array(TYPE_UINT,       REQ_NO),
		'thumbnail_width'    => array(TYPE_UINT,       REQ_NO),
		'thumbnail_height'   => array(TYPE_UINT,       REQ_NO),
	);

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('attachdata_start')) ? eval($hook) : false;
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'userid':
			case 'dateline' :
			{
				$tables = array('attachment', 'filedata');
			}
			break;

			case 'filedata':
			case 'filesize':
			case 'filehash':
			case 'thumbnail':
			case 'thumbnail_dateline':
			case 'thumbnail_filesize':
			case 'extension':
			case 'refcount':
			case 'width':
			case 'height':
			case 'thumbnail_width':
			case 'thumbnail_height':
			{
				$tables = array('filedata');
			}
			break;

			default:
			{
				$tables = array('attachment');
			}
		}

		($hook = vBulletinHook::fetch_hook('attachdata_doset')) ? eval($hook) : false;

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
		}
	}

	/**
	* Saves attachment to the database
	*
	* @return	mixed
	*/
	public function save($doquery = true)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($$doquery))
		{
			return 0;
		}

		$insertfiledata = true;
		if ($filedataid = $this->pre_save_filedata($doquery))
		{
			if (!$filedataid)
			{
				return false;
			}

			if ($filedataid !== true)
			{
				// this is an insert and file already exists
				// Check if file is already attached to this content
				if ($info = $this->registry->db->query_first(
					($this->info['contentid'] ? "(" : "") .
						"SELECT filedataid, attachmentid
						FROM " . TABLE_PREFIX . "attachment
						WHERE
							filedataid = " . intval($filedataid) . "
								AND
							posthash = '" . $this->registry->db->escape_string($this->fetch_field('posthash')) . "'
								AND
							contentid = 0
					" . ($this->info['contentid'] ? ") UNION (" : "") . "
					" . ($this->info['contentid'] ? "
						SELECT filedataid, attachmentid
						FROM " . TABLE_PREFIX . "attachment
						WHERE
							filedataid = " . intval($filedataid) . "
								AND
							contentid = " . intval($this->info['contentid']) . "
								AND
							contenttypeid = " . intval($this->fetch_field('contenttypeid')) . "
					)
						" : "") . "
				"))
				{
					// really just do nothing since this file is already attached to this content
					return $info['attachmentid'];
				}

				$this->attachment['filedataid'] = $filedataid;
				$insertfiledata = false;
				unset($this->validfields['filedata'], $this->validfields['filesize'],
					$this->validfields['filehash'], $this->validfields['thumbnail'], $this->validfields['thumbnail_dateline'],
					$this->validfields['thumbnail_filesize'], $this->validfields['extension'], $this->validfields['refcount']
				);
			}
		}

		if ($this->condition)
		{
			$return = $this->db_update(TABLE_PREFIX, 'thread', $this->condition, $doquery);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'filedataid', 'filedataid = ' . $this->fetch_field('filedataid'), $doquery);
			}
		}
		else
		{
			// insert query
			$return = $this->attachment['attachmentid'] = $this->db_insert(TABLE_PREFIX, 'attachment', $doquery);

			if ($return)
			{
				$this->do_set('attachmentid', $return);

				if ($insertfiledata)
				{
					$filedataid = $this->filedata['filedataid'] = $this->attachment['filedataid'] = $this->db_insert(TABLE_PREFIX, 'filedata', $doquery);
				}
				if ($doquery)
				{
					$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "attachment SET filedataid = $filedataid WHERE attachmentid = $return");
					// Verify categoryid
					if (!intval($this->info['categoryid']) OR $this->dbobject->query_first("
						SELECT categoryid
						FROM " . TABLE_PREFIX . "attachmentcategory
						WHERE
							userid = " . $this->fetch_field('userid') . "
								AND
							categoryid = " . intval($this->info['categoryid']) . "
					"))
					{
						$this->dbobject->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "attachmentcategoryuser
								(userid, filedataid, categoryid, filename, dateline)
							VALUES
								(" . $this->fetch_field('userid') . ", $filedataid, " . intval($this->info['categoryid']) . ", '" . $this->dbobject->escape_string($this->fetch_field('filename')) . "', " . TIMENOW . ")
						");
					}
				}
			}
		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
			if ($insertfiledata)
			{
				$this->post_save_each_filedata($doquery);
			}
		}

		return $return;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 45684 $
|| ####################################################################
\*======================================================================*/
?>
