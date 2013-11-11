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
*  vB_DataManager_SocialGroupIcon
* Class to do data save/delete operations for SocialGroupIcons.
* You should call the fetch_library() function to instantiate the correct
* object based on how social group icons are being stored.
*
* @package	vBulletin
* @version	$Revision: 26965 $
* @date		$Date: 2008-06-18 10:36:51 +0100 (Wed, 18 Jun 2008) $
*/
class vB_DataManager_SocialGroupIcon extends vB_DataManager
{
	/**
	* Array of recognized and required fields for icon inserts
	*
	* @access protected
	* @var	array
	*/
	var $validfields = array(
		'groupid'   => array(TYPE_UINT,     REQ_YES, VF_METHOD, 'verify_nonzero'),
		'userid'   => array(TYPE_UINT,     REQ_NO, VF_METHOD, 'verify_nonzero'),
		'filedata' => array(TYPE_BINARY,   REQ_NO),
		'extension'=> array(TYPE_NOHTMLCOND, REQ_YES,  VF_METHOD),
		'dateline' => array(TYPE_UNIXTIME, REQ_AUTO),
		'width'    => array(TYPE_UINT,     REQ_NO),
		'height'   => array(TYPE_UINT,     REQ_NO),
		'thumbnail_filedata' => array(TYPE_BINARY, REQ_NO),
		'thumbnail_width' => array(TYPE_UINT,	REQ_NO),
		'thumbnail_height' => array(TYPE_UINT,	REQ_NO),
	);

	/**
	* Default table to be used in queries
	*
	* @access protected
	* @var	string
	*/
	var $table = 'socialgroupicon';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @access protected
	* @var	array
	*/
	var $condition_construct = array('groupid = %1$d', 'groupid');

	// #######################################################################

	/**
	* Fetches the appropriate subclass based on how the icons are being stored.
	*
	* @access public
	*
	* @param vB_Registry $registry				Instance of the vBulletin data registry object - expected to have the
	* 											database object as one of its $this->db member.
	* @param integer $errtype					One of the ERRTYPE_x constants
	* @param string $classtype					The required subclass
	* @return vB_DataManager_SocialGroupIcon	Instance of vB_DataManager_SocialGroupIcon
	*/
	function &fetch_library(&$registry, $errtype = ERRTYPE_STANDARD, $classtype = 'SocialGroupIcon')
	{
		// Library
		if ($registry->options['usefilegroupicon'])
		{
			$newclass = new vB_DataManager_SocialGroupIcon_Filesystem($registry, $errtype);
		}
		else
		{
			$newclass = new vB_DataManager_SocialGroupIcon($registry, $errtype);
		}

		return $newclass;
	}


	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @access protected
	*
	* @param vB_Registry $registry				Instance of the vBulletin data registry object - expected to have the
	* 											database object as one of its $this->db member.
	* @param integer $errtype					One of the ERRTYPE_x constants
	*/
	function vB_DataManager_SocialGroupIcon(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('socgroupicondata_start')) ? eval($hook) : false;
	}


	/**
	* Any code to run before deleting.
	*
	* @access protected
	*
	* @param $doquery							Boolean Do the query?
	* @return boolean
	*/
	function pre_delete($doquery = true)
	{
		@ignore_user_abort(true);

		return true;
	}


	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @access protected
	*
	* @param boolean $doquery					Do the query?
	* @return boolean							True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			# Check if we need to insert or overwrite this image.
			if ($this->fetch_field('groupid') AND $this->registry->db->query_first("
				SELECT groupid
				FROM " . TABLE_PREFIX . $this->table . "
				WHERE groupid = " . $this->fetch_field('groupid'))
			)
			{
				$this->condition = "groupid = " . $this->fetch_field('groupid');
			}
		}

		if ($this->fetch_field('filedata') AND !$this->fetch_field('filedata_thumb') AND !$this->registry->options['usefilegroupicon'])
		{
			require_once(DIR . '/includes/class_image.php');
			if ($this->registry->options['safeupload'])
			{
				$filename = $this->registry->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->fetch_field('groupid'));
			}
			else
			{
				$filename = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
			}
			$filenum = @fopen($filename, 'wb');
			@fwrite($filenum, $this->fetch_field('filedata'));
			@fclose($filenum);

			if (!$this->fetch_field('width') OR !$this->fetch_field('height'))
			{
				$imageinfo = @getimagesize($filename);
				if ($imageinfo)
				{
					$this->set('width', $imageinfo[0]);
					$this->set('height', $imageinfo[1]);
				}
			}

			$thumbnail = $this->fetch_thumbnail($filename);

			@unlink($filename);
			if ($thumbnail['filedata'])
			{
				$this->set('thumbnail_width', $thumbnail['width']);
				$this->set('thumbnail_height', $thumbnail['height']);
				$this->set('thumbnail_filedata', $thumbnail['filedata']);
				unset($thumbnail);
			}
			else
			{
				$this->set('thumbnail_width', 0);
				$this->set('thumbnail_height', 0);
				$this->set('thumbnail_filedata', '');
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('socgroupicondata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @access protected
	*
	* @param boolean $doquery					Do the query?
	* @return boolean
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('socgroupicondata_postsave')) ? eval($hook) : false;
		return parent::post_save_each($doquery);
	}


	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @access protected
	*
	* @param boolean $doquery					Do the query?
	*/
	function post_delete($doquery = true)
	{
		require_once(DIR . '/includes/functions_socialgroup.php');
		fetch_socialgroup_newest_groups(true, false, !$this->registry->options['sg_enablesocialgroupicons']);

		($hook = vBulletinHook::fetch_hook('socgroupicondata_delete')) ? eval($hook) : false;
		return parent::post_delete($doquery);
	}


	/**
	 * Creates thumbnail information based on the full image data
	 *
	 * @access protected
	 *
	 * @param string $file						Name of the file to convert, usually a temp file
	 * @return array mixed						Array of the resulting thumbnail information
	 */
	function fetch_thumbnail($file)
	{
		require_once(DIR . '/includes/class_image.php');
		$image =& vB_Image::fetch_library($this->registry);
		$imageinfo = $image->fetch_image_info($file);

		$quality = (isset($this->info['thumbnail_quality']) ? min(100, $this->info['thumbnail_quality']) : $this->registry->options['thumbquality']);
		if ($imageinfo[0] > FIXED_SIZE_GROUP_THUMB_WIDTH OR $imageinfo[1] > FIXED_SIZE_GROUP_THUMB_HEIGHT)
		{
			$filename = 'file.' . ($imageinfo[2] == 'JPEG' ? 'jpg' : strtolower($imageinfo[2]));
			$thumbnail = $image->fetch_thumbnail($filename, $file, FIXED_SIZE_GROUP_THUMB_WIDTH, FIXED_SIZE_GROUP_THUMB_HEIGHT, $quality);
			if ($thumbnail['filedata'])
			{
				return $thumbnail;
			}
		}

		return array(
			'filedata' => @file_get_contents($file),
			'width'    => $imageinfo[0],
			'height'   => $imageinfo[1],
		);
	}

	/**
	 * Makes the extension lowercase
	 *
	 * @access protected
	 *
	 * @param string $extension
	 * @return	true
	 */
	function verify_extension(&$extension)
	{
		$extension = strtolower($extension);
		return true;
	}
}


/**
* vB_DataManager_SocialGroupIcon_Filesystem
* Datamanager for updating social group icons when using the filesystem to store images
* as physical files.
*
* @package	vBulletin
* @version	$Revision: 26965 $
* @date		$Date: 2008-06-18 10:36:51 +0100 (Wed, 18 Jun 2008) $
*/
class vB_DataManager_SocialGroupIcon_Filesystem extends vB_DataManager_SocialGroupIcon
{
	/**
	* Constructor
	*
	* @access public
	*
	* @param vB_Registry $registry				Instance of the vBulletin data registry object - expected to have the
	* 											database object as one of its $this->db member.
	* @param integer $errtype					One of the ERRTYPE_x constants
	* @return vB_DataManager_SocialGroupIcon_Filesystem
	*/
	function vB_DataManager_SocialGroupIcon_Filesystem(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_SocialGroupIcon($registry, $errtype);
	}


	/**
	 * Evaluates the correct path to the file for reading.
	 *
	 * @access protected
	 *
	 * @param integer $groupid					Id of the group the icon is for
	 * @param integer $dateline					Timestamp of when the image was uploaded
	 * @param boolean $thumb					Whether to get the thumbnail path or not
	 * @param boolean $pathonly					Whether to include the filename
	 * @return string							The filepath to the image
	 */
	function fetch_path($groupid, $dateline, $thumb = false, $pathonly = false)
	{
		return $this->registry->options['groupiconpath'] . "/" . ($thumb ? 'thumbs/' : '') . ($pathonly ? '' : "socialgroupicon_{$groupid}_$dateline.gif");
	}


	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @access protected
	*
	* @param boolean $doquery					Do the query?
	* @return boolean							True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if ($file =& $this->fetch_field('filedata'))
		{
			$this->setr_info('filedata', $file);
			$this->do_unset('filedata');

			if (!is_writable($this->fetch_path($this->fetch_field('groupid'), $this->info['group']['icondateline'], false, true)))
			{
				$this->error('upload_invalid_imagepath');
				return false;
			}

			if ($thumb =& $this->fetch_field('thumbnail_filedata'))
			{
				$this->setr_info('thumbnail_filedata', $thumb);
				$this->do_unset('thumbnail_filedata');
			}

			require_once(DIR . '/includes/class_image.php');
			$image =& vB_Image::fetch_library($this->registry);
		}

		return parent::pre_save($doquery);
	}


	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @access protected
	*
	* @param boolean $doquery					Do the query?
	* @return boolean
	*/
	function post_save_each($doquery = true)
	{
		// We were given an image so write out a new image.
		if (!empty($this->info['filedata']))
		{
			// get the file paths
			$oldfilename = $this->fetch_path($this->fetch_field('groupid'), $this->info['group']['icondateline']);
			$oldthumbfilename = $this->fetch_path($this->fetch_field('groupid'), $this->info['group']['icondateline'], true);
			$newfilename = $this->fetch_path($this->fetch_field('groupid'), $this->fetch_field('dateline'));
			$thumbfilename = $this->fetch_path($this->fetch_field('groupid'), $this->fetch_field('dateline'), true);

			// delete the old files and create new ones
			if ($filenum = fopen($newfilename, 'wb'))
			{
				@unlink($oldfilename);
				@unlink($oldthumbfilename);
				@fwrite($filenum, $this->info['filedata']);
				@fclose($filenum);

				if ($this->info['thumbnail_filedata'])
				{
					$thumbnail['filedata'] =& $this->info['thumbnail_filedata'];
				}
				else
				{
					$thumbnail = $this->fetch_thumbnail($newfilename, true);
				}

				require_once(DIR . '/includes/functions_file.php');

				if ($thumbnail['filedata'] AND vbmkdir(dirname($thumbfilename)) AND $filenum = @fopen($thumbfilename, 'wb'))
				{
					@fwrite($filenum, $thumbnail['filedata']);
					@fclose($filenum);

					// ensure the image data contains the correct height and width
					if ($thumbnail['height'] AND $thumbnail['width'])
					{
						$this->registry->db->query_write("
							UPDATE " . TABLE_PREFIX . "socialgroupicon
							SET thumbnail_width = $thumbnail[width],
								thumbnail_height = $thumbnail[height]
							WHERE groupid = " . $this->fetch_field('groupid')
						);
					}

					unset($thumbnail);
				}

				($hook = vBulletinHook::fetch_hook('socgroupicondata_postsave')) ? eval($hook) : false;

				return true;
			}
			else
			{
				($hook = vBulletinHook::fetch_hook('socgroupicondata_postsave')) ? eval($hook) : false;

				$this->error('upload_invalid_imagepath');
				return false;
			}
		}
		else
		{
			($hook = vBulletinHook::fetch_hook('socgroupicondata_postsave')) ? eval($hook) : false;

			return true;
		}
	}

	/**
	* Pre-delete to fetch data that will be removed during delete.
	*
	* @return	boolean
	*/
	function pre_delete()
	{
		$icons = array();

		$groups = $this->registry->db->query_read_slave("
			SELECT
				groupid, dateline AS icondateline
			FROM " . TABLE_PREFIX . "socialgroupicon AS socialgroupicon
			WHERE " . $this->condition
		);
		while ($group = $this->registry->db->fetch_array($groups))
		{
			$icons[] = $group;
		}

		$this->info['remove_icons'] = $icons;

		return true;
	}


	/**
	* Any code to run after deleting
	*
	* @access protected
	*
	* @param boolean $doquery					Do the query?
	*/
	function post_delete($doquery = true)
	{
		// remove image files
		if (is_array($this->info['remove_icons']))
		{
			foreach ($this->info['remove_icons'] AS $group)
			{
				@unlink($this->fetch_path($group['groupid'], $group['icondateline']));
				@unlink($this->fetch_path($group['groupid'], $group['icondateline'], true));
			}
		}

		require_once(DIR . '/includes/functions_socialgroup.php');
		fetch_socialgroup_newest_groups(true, false, !$this->registry->options['sg_enablesocialgroupicons']);

		($hook = vBulletinHook::fetch_hook('socgroupicondata_delete')) ? eval($hook) : false;
	}
}
?>