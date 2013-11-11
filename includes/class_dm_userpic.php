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
*  vB_DataManager_Avatar
*  vB_DataManager_ProfilePic
* Abstract class to do data save/delete operations for Userpics.
* You should call the fetch_library() function to instantiate the correct
* object based on how userpics are being stored.
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Userpic extends vB_DataManager
{

	/**
	* Array of recognized and required fields for avatar inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'   => array(TYPE_UINT,     REQ_YES),
		'filedata' => array(TYPE_BINARY,   REQ_NO, VF_METHOD),
		'dateline' => array(TYPE_UNIXTIME, REQ_AUTO),
		'filename' => array(TYPE_STR,      REQ_YES),
		'visible'  => array(TYPE_UINT,     REQ_NO),
		'filesize' => array(TYPE_UINT,     REQ_YES),
		'width'    => array(TYPE_UINT,     REQ_NO),
		'height'   => array(TYPE_UINT,     REQ_NO),
	);

	/**
	*
	* @var	string  The main table this class deals with
	*/
	var $table = 'customavatar';

	/**
	* Revision field to update
	*
	* @var	string
	*/
	var $revision = 'avatarrevision';

	/**
	* Path to image directory
	*
	* @var	string
	*/
	var $filepath = 'customavatars';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('userid = %1$d', 'userid');

	/**
	* Fetches the appropriate subclass based on how the userpics are being stored.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*
	* @return	vB_DataManager_Userpic	Subclass of vB_DataManager_Userpic
	*/
	function &fetch_library(&$registry, $errtype = ERRTYPE_STANDARD, $classtype = 'userpic_avatar')
	{
		// Library
		if ($registry->options['usefileavatar'])
		{
			$newclass = new vB_DataManager_Userpic_Filesystem($registry, $errtype);
		}
		else
		{
			$class = 'vB_DataManager_' . $classtype;
			$newclass = new $class($registry, $errtype);
		}

		switch (strtolower($classtype))
		{
			case 'userpic_avatar':
				$newclass->table = 'customavatar';
				$newclass->revision = 'avatarrevision';
				$newclass->filepath =& $registry->options['avatarpath'];

				$validfields = array(
					'filedata_thumb' => array(TYPE_BINARY, REQ_NO,),
					'width_thumb'    => array(TYPE_UINT, REQ_NO),
					'height_thumb'   => array(TYPE_UINT, REQ_NO),
				);

				$newclass->validfields = array_merge($newclass->validfields, $validfields);

				break;
			case 'userpic_profilepic':
				$newclass->table = 'customprofilepic';
				$newclass->revision = 'profilepicrevision';
				$newclass->filepath =& $registry->options['profilepicpath'];
				break;
			case 'userpic_sigpic':
				$newclass->table = 'sigpic';
				$newclass->revision = 'sigpicrevision';
				$newclass->filepath =& $registry->options['sigpicpath'];
				break;
		}

		return $newclass;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Userpic(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('userpicdata_start')) ? eval($hook) : false;
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
			$this->set('filesize', strlen($filedata));
		}

		return true;
	}

	/**
	* Any code to run before deleting.
	*
	* @param	Boolean Do the query?
	*/
	function pre_delete($doquery = true)
	{
		@ignore_user_abort(true);

		return true;
	}

	/**
	*
	*
	*
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
			if ($this->fetch_field('userid') AND $this->registry->db->query_first("
				SELECT userid
				FROM " . TABLE_PREFIX . $this->table . "
				WHERE userid = " . $this->fetch_field('userid'))
			)
			{
				$this->condition = "userid = " . $this->fetch_field('userid');
			}
		}

		if ($this->table == 'customavatar' AND $this->fetch_field('filedata') AND !$this->fetch_field('filedata_thumb') AND !$this->registry->options['usefileavatar'])
		{
			require_once(DIR . '/includes/class_image.php');
			if ($this->registry->options['safeupload'])
			{
				$filename = $this->registry->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->fetch_field('userid'));
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
				$this->set('width_thumb', $thumbnail['width']);
				$this->set('height_thumb', $thumbnail['height']);
				$this->set('filedata_thumb', $thumbnail['filedata']);
				unset($thumbnail);
			}
			else
			{
				$this->set('width_thumb', 0);
				$this->set('height_thumb', 0);
				$this->set('filedata_thumb', '');
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('userpicdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;
		return parent::post_save_each($doquery);
	}

	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('userpicdata_delete')) ? eval($hook) : false;
		return parent::post_delete($doquery);
	}

	function fetch_thumbnail($file, $forceimage = false)
	{
		require_once(DIR . '/includes/class_image.php');
		$image =& vB_Image::fetch_library($this->registry);
		$imageinfo = $image->fetch_image_info($file);
		if ($imageinfo[0] > FIXED_SIZE_AVATAR_WIDTH OR $imageinfo[1] > FIXED_SIZE_AVATAR_HEIGHT)
		{
			$filename = 'file.' . ($imageinfo[2] == 'JPEG' ? 'jpg' : strtolower($imageinfo[2]));
			$thumbnail = $image->fetch_thumbnail($filename, $file, FIXED_SIZE_AVATAR_WIDTH, FIXED_SIZE_AVATAR_HEIGHT);
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
}

class vB_DataManager_Userpic_Avatar extends vB_DataManager_Userpic
{
	function vB_DataManager_Userpic_Avatar(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		$this->table = 'customavatar';
		$this->revision = 'avatarrevision';
		$this->filepath =& $registry->options['avatarpath'];

		parent::vB_DataManager_Userpic($registry, $errtype);
	}
}

class vB_DataManager_Userpic_Profilepic extends vB_DataManager_Userpic
{
	function vB_DataManager_Userpic_Profilepic(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		$this->table = 'customprofilepic';
		$this->revision = 'profilepicrevision';
		$this->filepath =& $registry->options['profilepicpath'];

		parent::vB_DataManager_Userpic($registry, $errtype);
	}
}

class vB_DataManager_Userpic_Sigpic extends vB_DataManager_Userpic
{
	function vB_DataManager_Userpic_Sigpic(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		$this->table = 'sigpic';
		$this->revision = 'sigpicrevision';
		$this->filepath =& $registry->options['sigpicpath'];

		parent::vB_DataManager_Userpic($registry, $errtype);
	}
}

class vB_DataManager_Userpic_Filesystem extends vB_DataManager_Userpic
{
	function fetch_path($userid, $revision, $thumb = false)
	{
		return $this->filepath . "/" . ($thumb ? 'thumbs/' : '') . preg_replace("#^custom#si", '', $this->table) . $userid . "_" . $revision . ".gif";
	}

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
			$this->set('filesize', strlen($this->info['filedata']));
			if (!is_writable($this->filepath))
			{
				$this->error('upload_invalid_imagepath');
				return false;
			}

			if ($thumb =& $this->fetch_field('filedata_thumb'))
			{
				$this->setr_info('filedata_thumb', $thumb);
				$this->do_unset('filedata_thumb');
			}

			require_once(DIR . '/includes/class_image.php');
			$image =& vB_Image::fetch_library($this->registry);
		}

		return parent::pre_save($doquery);
	}

	function post_save_each($doquery = true)
	{
		# Check if revision was passed as an info object or as existing
		if (isset($this->info["{$this->revision}"]))
		{
			$revision = $this->info["{$this->revision}"];
		}
		else if ($this->fetch_field($this->revision) !== null)
		{
			$revision = $this->fetch_field($this->revision);
		}

		// We were given an image and a revision number so write out a new image.
		if (!empty($this->info['filedata']) AND isset($revision))
		{
			$oldfilename = $this->fetch_path($this->fetch_field('userid'), $revision);
			$oldthumbfilename = $this->fetch_path($this->fetch_field('userid'), $revision, true);
			$newfilename = $this->fetch_path($this->fetch_field('userid'), $revision + 1);
			$thumbfilename = $this->fetch_path($this->fetch_field('userid'), $revision + 1, true);
			if ($filenum = fopen($newfilename, 'wb'))
			{
				@unlink($oldfilename);
				if ($this->table == 'customavatar')
				{
					@unlink($oldthumbfilename);
				}
				@fwrite($filenum, $this->info['filedata']);
				@fclose($filenum);

				// init user data manager
				$userdata =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$userdata->setr('userid', $this->fetch_field('userid'));
				$userdata->condition = "userid = " . $this->fetch_field('userid');
				$userdata->set($this->revision, $revision + 1);

				$userdata->save();
				unset($userdata);

				if ($this->table == 'customavatar')
				{
					if ($this->info['filedata_thumb'])
					{
						$thumbnail['filedata'] =& $this->info['filedata_thumb'];
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

						if ($thumbnail['height'] AND $thumbnail['width'])
						{
							$this->registry->db->query_write("
								UPDATE " . TABLE_PREFIX . "customavatar
								SET width_thumb = $thumbnail[width],
									height_thumb = $thumbnail[height]
								WHERE userid = " . $this->fetch_field('userid')
							);
						}

						unset($thumbnail);
					}
				}

				($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;

				return true;
			}
			else
			{
				($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;

				$this->error('upload_invalid_imagepath');
				return false;
			}
		}
		else
		{
			($hook = vBulletinHook::fetch_hook('userpicdata_postsave')) ? eval($hook) : false;

			return true;
		}
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{

		$users = $this->registry->db->query_read_slave("
			SELECT
				userid, {$this->revision} AS revision
			FROM " . TABLE_PREFIX . "user
			WHERE " . $this->condition
		);
		while ($user = $this->registry->db->fetch_array($users))
		{
			@unlink($this->fetch_path($user['userid'], $user['revision']));
			@unlink($this->fetch_path($user['userid'], $user['revision'], true));
		}

		($hook = vBulletinHook::fetch_hook('userpicdata_delete')) ? eval($hook) : false;

	}

}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>