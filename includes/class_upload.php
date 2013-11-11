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

/**
* Abstracted class that handles POST data from $_FILES
*
* @package	vBulletin
* @version	$Revision: 37230 $
* @date		$Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
*/
class vB_Upload_Abstract
{
	/**
	* Any errors that were encountered during the upload or verification process
	*
	* @var	array
	*/
	var $error = '';

	/**
	* Main registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Image object for verifying and resizing
	*
	* @var	vB_Image
	*/
	var $image = null;

	/**
	* Object for save/delete operations
	*
	* @var	vB_DataManager
	*/
	var $upload = null;

	/**
	* Information about the upload that we are working with
	*
	* @var	array
	*/
	var $data = null;

	/**
	* Width and Height up Uploaded Image
	*
	* @var	array
	*/
	var $imginfo = array();

	/**
	* Maximum size of uploaded file. Set to zero to not check
	*
	* @var	int
	*/
	var $maxuploadsize = 0;

	/**
	* Maximum pixel width of uploaded image. Set to zero to not check
	*
	* @var	int
	*/
	var $maxwidth = 0;

	/**
	* Maximum pixel height of uploaded image. Set to zero to not check
	*
	* @var	int
	*/
	var $maxheight = 0;

	/**
	* Information about user who owns the image being uploaded. Mostly we care about $userinfo['userid'] and $userinfo['attachmentpermissions']
	*
	* @var	array
	*/
	var $userinfo = array();

	/**
	* Whether to display an error message if the upload forum is sent in empty or invalid (false = Multiple Upload Forms)
	*
	* @var  bool
	*/
	var $emptyfile = true;

	/**
	* Whether or not animated GIFs are allowed to be uploaded
	*
	* @var boolean
	*/
	var $allowanimation = null;

	function vB_Upload_Abstract(&$registry)
	{
		$this->registry =& $registry;
		// change this to save a file as someone else
		$this->userinfo = $this->registry->userinfo;
	}

	/**
	* Set warning
	*
	* @param	string	Varname of error phrase
	* @param	mixed	Value of 1st variable
	* @param	mixed	Value of 2nd variable
	* @param	mixed	Value of Nth variable
	*/
	function set_warning()
	{
		$args = func_get_args();

		$this->error = call_user_func_array('fetch_error', $args);
	}

	/**
	* Set error state and removes any uploaded file
	*
	* @param	string	Varname of error phrase
	* @param	mixed	Value of 1st variable
	* @param	mixed	Value of 2nd variable
	* @param	mixed	Value of Nth variable
	*/
	function set_error()
	{
		$args = func_get_args();

		$this->error = call_user_func_array('fetch_error', $args);

		if (!empty($this->upload['location']))
		{
			@unlink($this->upload['location']);
		}
	}

	/**
	* Returns the current error
	*
	*/
	function &fetch_error()
	{
		return $this->error;
	}

	/**
	* This function accepts a file via URL or from $_FILES, verifies it, and places it in a temporary location for processing
	*
	* @param	mixed	Valid options are: (a) a URL to a file to retrieve or (b) a pointer to a file in the $_FILES array
	*/
	function accept_upload(&$upload)
	{
		$this->error = '';

		if (!is_array($upload) AND strval($upload) != '')
		{
			$this->upload['extension'] = strtolower(file_extension($upload));

			// Check extension here so we can save grabbing a large file that we aren't going to use
			if (!$this->is_valid_extension($this->upload['extension']))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			// Admins can upload any size file
			if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$this->maxuploadsize = 0;
			}
			else
			{
				$this->maxuploadsize = $this->fetch_max_uploadsize($this->upload['extension']);
				if (!$this->maxuploadsize)
				{
					$newmem = 20971520;
				}
			}

			if (!preg_match('#^((http|ftp)s?):\/\/#i', $upload))
			{
				$upload = 'http://' . $upload;
			}

			if (ini_get('allow_url_fopen') == 0 AND !function_exists('curl_init'))
			{
				$this->set_error('upload_fopen_disabled');
				return false;
			}
			else if ($filesize = $this->fetch_remote_filesize($upload))
			{
				if ($this->maxuploadsize AND $filesize > $this->maxuploadsize)
				{
					$this->set_error('upload_remoteimage_toolarge');
					return false;
				}
				else
				{
					if (function_exists('memory_get_usage') AND $memory_limit = @ini_get('memory_limit') AND $memory_limit != -1)
					{	// Make sure we have enough memory to process this file
						$memorylimit = vb_number_format($memory_limit, 0, false, null, '');
						$memoryusage = memory_get_usage();
						$freemem = $memorylimit - $memoryusage;
						$newmemlimit = !empty($newmem) ? $freemem + $newmem : $freemem + $filesize;

						if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < $newmemlimit AND $current_memory_limit > 0)
						{
							@ini_set('memory_limit', $newmemlimit);
						}
					}

					require_once(DIR . '/includes/class_vurl.php');
					$vurl = new vB_vURL($this->registry);
					$vurl->set_option(VURL_URL, $upload);
					$vurl->set_option(VURL_FOLLOWLOCATION, 1);
					$vurl->set_option(VURL_HEADER, true);
					$vurl->set_option(VURL_MAXSIZE, $this->maxuploadsize);
					$vurl->set_option(VURL_RETURNTRANSFER, true);
					if ($result = $vurl->exec2())
					{

					}
					else
					{
						switch ($vurl->fetch_error())
						{
							case VURL_ERROR_MAXSIZE:
								$this->set_error('upload_remoteimage_toolarge');
								break;
							case VURL_ERROR_NOLIB:	// this condition isn't reachable
								$this->set_error('upload_fopen_disabled');
								break;
							case VURL_ERROR_SSL:
							case VURL_URL_URL:
							default:
								$this->set_error('retrieval_of_remote_file_failed');
						}

						return false;
					}
					unset($vurl);
				}
			}
			else
			{
				$this->set_error('upload_invalid_url');
				return false;
			}

			// write file to temporary directory...
			if ($this->registry->options['safeupload'])
			{
				// ... in safe mode
				$this->upload['location'] = $this->registry->options['tmppath'] . '/vbupload' . $this->userinfo['userid'] . substr(TIMENOW, -4);
			}
			else
			{
				// ... in normal mode
				$this->upload['location'] = $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] ? tempnam(ini_get('upload_tmp_dir'), 'vbupload') : @tempnam(ini_get('upload_tmp_dir'), 'vbupload');
			}

			$attachment_write_failed = true;
			if (!empty($result['body']))
			{
				$fp = $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] ? fopen($this->upload['location'], 'wb') : @fopen($this->upload['location'], 'wb');
				if ($fp AND $this->upload['location'])
				{
					@fwrite($fp, $result['body']);
					@fclose($fp);
					$attachment_write_failed = false;
				}
			}
			else if (file_exists($result['body_file']))
			{
				if (@rename($result['body_file'], $this->upload['location']) OR (copy($result['body_file'], $this->upload['location']) AND unlink($result['body_file'])))
				{
					$mask = 0777 & ~umask();
					@chmod($this->upload['location'], $mask);

					$attachment_write_failed = false;
				}
			}

			if ($attachment_write_failed)
			{
				$this->set_error('upload_writefile_failed');
				return false;
			}

			$this->upload['filesize'] = @filesize($this->upload['location']);
			$this->upload['filename'] = basename($upload);
			$this->upload['extension'] = strtolower(file_extension($this->upload['filename']));
			$this->upload['thumbnail'] = '';
			$this->upload['filestuff'] = '';
			$this->upload['url'] = true;
		}
		else
		{
			$this->upload['filename'] = trim($upload['name']);
			$this->upload['filesize'] = intval($upload['size']);
			$this->upload['location'] = trim($upload['tmp_name']);
			$this->upload['extension'] = strtolower(file_extension($this->upload['filename']));
			$this->upload['thumbnail'] = '';
			$this->upload['filestuff'] = '';

			if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] AND $this->upload['error'])
			{
				// Encountered PHP upload error
				if (!($maxupload = @ini_get('upload_max_filesize')))
				{
					$maxupload = 10485760;
				}
				$maxattachsize = vb_number_format($maxupload, 1, true);

				switch($this->upload['error'])
				{
					case '1': // UPLOAD_ERR_INI_SIZE
					case '2': // UPLOAD_ERR_FORM_SIZE
						$this->set_error('upload_file_exceeds_php_limit', $maxattachsize);
						break;
					case '3': // UPLOAD_ERR_PARTIAL
						$this->set_error('upload_file_partially_uploaded');
						break;
					case '4':
						$this->set_error('upload_file_failed');
						break;
					case '6':
						$this->set_error('missing_temporary_folder');
						break;
					case '7':
						$this->set_error('upload_writefile_failed');
						break;
					case '8':
						$this->set_error('upload_stopped_by_extension');
						break;
					default:
						$this->set_error('upload_invalid_file');
				}

				return false;
			}
			else if ($this->upload['error'] OR $this->upload['location'] == 'none' OR $this->upload['location'] == '' OR $this->upload['filename'] == '' OR !$this->upload['filesize'] OR !is_uploaded_file($this->upload['location']))
			{
				if ($this->emptyfile OR $this->upload['filename'] != '')
				{
					$this->set_error('upload_file_failed');
				}
				return false;
			}

			if ($this->registry->options['safeupload'])
			{
				$temppath = $this->registry->options['tmppath'] . '/' . $this->registry->session->fetch_sessionhash();
				$moveresult = $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] ? move_uploaded_file($this->upload['location'], $temppath) : @move_uploaded_file($this->upload['location'], $temppath);
				if (!$moveresult)
				{
					$this->set_error('upload_unable_move');
					return false;
				}
				$this->upload['location'] = $temppath;
			}
		}

		// Check if the filename is utf8
		$this->upload['utf8_name'] = isset($upload['utf8_name']) AND $upload['utf8_name'];

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('upload_accept')) ? eval($hook) : false;

		return $return_value;

	}

	/**
	* Requests headers of remote file to retrieve size without downloading the file
	*
	* @param	string	URL of remote file to retrieve size from
	*/
	function fetch_remote_filesize($url)
	{
		if (!preg_match('#^((http|ftp)s?):\/\/#i', $url, $check))
		{
			$this->set_error('upload_invalid_url');
			return false;
		}

		require_once(DIR . '/includes/class_vurl.php');
		$vurl = new vB_vURL($this->registry);
		$vurl->set_option(VURL_URL, $url);
		$vurl->set_option(VURL_FOLLOWLOCATION, 1);
		$vurl->set_option(VURL_HEADER, 1);
		$vurl->set_option(VURL_NOBODY, 1);
		$vurl->set_option(VURL_USERAGENT, 'vBulletin via PHP');
		$vurl->set_option(VURL_CUSTOMREQUEST, 'HEAD');
		$vurl->set_option(VURL_RETURNTRANSFER, 1);
		$vurl->set_option(VURL_CLOSECONNECTION, 1);
		$result = $vurl->exec2();
		if ($result AND $length = intval($result['content-length']))
		{
			return $length;
		}
		else if ($result['http-response']['statuscode'] == "200")
		{
			// We have an HTTP 200 OK, but no content-length, return -1 and let VURL handle the max fetch size
			return -1;
		}
		else
		{
			return false;
		}
	}

	/**
	* Attempt to resize file if the filesize is too large after an initial resize to max dimensions or the file is already within max dimensions but the filesize is too large
	*
	* @param	bool	Has the image already been resized once?
	* @param	bool	Attempt a resize
	*/
	function fetch_best_resize(&$jpegconvert, $resize = true)
	{
		if (!$jpegconvert AND $this->upload['filesize'] > $this->maxuploadsize AND $resize AND $this->image->is_valid_resize_type($this->imginfo[2]))
		{
			// Linear Regression
			switch($this->registry->options['thumbquality'])
			{
				case 65:
					// No Sharpen
					// $magicnumber = round(379.421 + .00348171 * $this->maxuploadsize);
					// Sharpen
					$magicnumber = round(277.652 + .00428902 * $this->maxuploadsize);
					break;
				case 85:
					// No Sharpen
					// $magicnumber = round(292.53 + .0027378 * $this-maxuploadsize);
					// Sharpen
					$magicnumber = round(189.939 + .00352439 * $this->maxuploadsize);
					break;
				case 95:
					// No Sharpen
					// $magicnumber = round(188.11 + .0022561 * $this->maxuploadsize);
					// Sharpen
					$magicnumber = round(159.146 + .00234146 * $this->maxuploadsize);
					break;
				default:	//75
					// No Sharpen
					// $magicnumber = round(328.415 + .00323415 * $this->maxuploadsize);
					// Sharpen
					$magicnumber = round(228.201 + .00396951 * $this->maxuploadsize);
			}

			$xratio = ($this->imginfo[0] > $magicnumber) ? $magicnumber / $this->imginfo[0] : 1;
			$yratio = ($this->imginfo[1] > $magicnumber) ? $magicnumber / $this->imginfo[1] : 1;

			if ($xratio > $yratio AND $xratio != 1)
			{
				$new_width = round($this->imginfo[0] * $xratio);
				$new_height = round($this->imginfo[1] * $xratio);
			}
			else
			{
				$new_width = round($this->imginfo[0] * $yratio);
				$new_height = round($this->imginfo[1] * $yratio);
			}
			if ($new_width == $this->imginfo[0] AND $new_height == $this->imginfo[1])
			{	// subtract one pixel so that requested size isn't the same as the image size
				$new_width--;
				$forceresize = false;
			}
			else
			{
				$forceresize = true;
			}

			$this->upload['resized'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $new_width, $new_height, $this->registry->options['thumbquality'], false, false, true, false);

			if (empty($this->upload['resized']['filedata']))
			{
				if ($this->image->is_valid_thumbnail_extension(file_extension($this->upload['filename'])) AND !empty($this->upload['resized']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				{
					if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$this->set_error('image_resize_failed_x', htmlspecialchars_uni($error));
						return false;
					}
					else
					{
						$this->set_error($this->upload['resized']['imageerror']);
						return false;
					}
				}
				else
				{
					$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
					#$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else
			{
				$jpegconvert = true;
			}
		}

		if (!$jpegconvert AND $this->upload['filesize'] > $this->maxuploadsize)
		{
			$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
			return false;
		}
		else if ($jpegconvert AND $this->upload['resized']['filesize'] AND ($this->upload['resized']['filesize'] > $this->maxuploadsize OR $forceresize))
		{
			$ratio = $this->maxuploadsize / $this->upload['resized']['filesize'];

			$newwidth = $this->upload['resized']['width'] * sqrt($ratio);
			$newheight = $this->upload['resized']['height'] * sqrt($ratio);

			if ($newwidth > $this->imginfo[0])
			{
				$newwidth = $this->imginfo[0] - 1;
			}
			if ($newheight > $this->imginfo[1])
			{
				$newheight = $this->imginfo[1] - 1;
			}

			$this->upload['resized'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $newwidth, $newheight, $this->registry->options['thumbquality'], false, false, true, false);
			if (empty($this->upload['resized']['filedata']))
			{
				if (!empty($this->upload['resized']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				{
					if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$this->set_error('image_resize_failed_x', htmlspecialchars_uni($error));
						return false;
					}
					else
					{
						$this->set_error($this->upload['resized']['imageerror']);
						return false;
					}
				}
				else
				{
					$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
					#$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else
			{
				$jpegconvert = true;
			}
		}

		return true;
	}

	/**
	* Verifies a valid remote url for retrieval or verifies a valid uploaded file
	*
	*/
	function process_upload() {}

	/**
	* Saves a file that has been verified
	*
	*/
	function save_upload() {}

	/**
	* Public
	* Checks if supplied extension can be used
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function is_valid_extension()
	{}

	/**
	* Public
	* Returns the maximum filesize for the specified extension
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	integer
	*/
	function fetch_max_uploadsize(){}

	/**
	 * Public
	 * NCR encodes a unicode filename
	 *
	 * @return string
	 */
	function ncrencode_filename($filename)
	{
		$extension = file_extension($filename);
		$base = substr($filename, 0, (strpos($filename, $extension) - 1));
		$base = ncrencode($base, true);

		return $base . '.' . $extension;
	}
}

class vB_Upload_Attachment extends vB_Upload_Abstract
{

	function fetch_max_uploadsize($extension)
	{
		if (!empty($this->userinfo['attachmentpermissions']["$extension"]['size']))
		{
			return $this->userinfo['attachmentpermissions']["$extension"]['size'];
		}
		else
		{
			return 0;
		}
	}

	function is_valid_extension($extension)
	{
		// ['e'] = enabed
		return
		(
			!empty($this->userinfo['attachmentpermissions']["$extension"]['permissions'])
				AND
			(
				!$this->userinfo['attachmentpermissions']["$extension"]['contenttypes']["{$this->contenttypeid}"]
					OR
				!isset($this->userinfo['attachmentpermissions']["$extension"]['contenttypes']["{$this->contenttypeid}"]['e'])
					OR
				$this->userinfo['attachmentpermissions']["$extension"]['contenttypes']["{$this->contenttypeid}"]['e']
			)
		);
	}

	function process_upload($uploadstuff = '', $forcethumbnail = false, $imageonly = false)
	{
		if ($this->registry->attachmentcache === null)
		{
			trigger_error('vB_Upload_Attachment: Attachment cache not specfied. Can not continue.', E_USER_ERROR);
		}

		if (is_array($uploadstuff) AND $uploadstuff['filedataid'])
		{
			// Create an attachment from existing filedata
			// Verify Extension is still enabled
			if (!$this->is_valid_extension($uploadstuff['extension']))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			if (!$this->check_attachment_overage($uploadstuff['filehash'], $uploadstuff['filesize']))
			{
				return false;
			}

			if ($this->registry->db->query_first(
				($this->data->info['contentid'] ? "(" : "") .
					"SELECT filedataid
					FROM " . TABLE_PREFIX . "attachment
					WHERE
						filedataid = " . intval($uploadstuff['filedataid']) . "
							AND
						posthash = '" . $this->registry->db->escape_string($this->data->fetch_field('posthash')) . "'
							AND
						contentid = 0
					" . ($this->data->info['contentid'] ? ") UNION (" : "") . "
					" . ($this->data->info['contentid'] ? "
						SELECT filedataid
						FROM " . TABLE_PREFIX . "attachment
						WHERE
							filedataid = " . intval($uploadstuff['filedataid']) . "
								AND
							contentid = " . intval($this->data->info['contentid']) . "
								AND
							contenttypeid = " . intval($this->data->fetch_field('contenttypeid')) . "
					)
						" : "") . "
				"))
			{
				//$this->set_error('asset_already_attached');
				return false;
			}

			if (isset($uploadstuff['utf8_name']) AND $uploadstuff['utf8_name'])
			{
				$uploadstuff['filename'] = $this->ncrencode_filename($uploadstuff['filename']);
			}

			// Save upload - call attachment dm directly
			$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_SILENT, 'attachment');
			$attachdata->set('dateline', TIMENOW);
			$attachdata->set('userid', $this->userinfo['userid']);
			$attachdata->set('filename', $uploadstuff['filename']);
			$attachdata->set('contenttypeid', $this->data->fetch_field('contenttypeid'));
			$attachdata->set('contentid', $this->data->fetch_field('contentid'));
			$attachdata->set('filedataid', $uploadstuff['filedataid']);
			$attachdata->set('state', $this->data->fetch_field('state'));
			$attachdata->set('posthash', $this->data->fetch_field('posthash'));
			return $attachdata->save();
		}
		else if ($this->accept_upload($uploadstuff))
		{
			// Verify Extension is proper
			if (!$this->is_valid_extension($this->upload['extension']) OR (!$this->image->is_valid_info_extension($this->upload['extension']) AND $imageonly))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			$jpegconvert = false;
			// is this a filetype that can be processed as an image?
			if ($this->image->is_valid_info_extension($this->upload['extension']))
			{
				$this->maxwidth = $this->userinfo['attachmentpermissions']["{$this->upload['extension']}"]['width'];
				$this->maxheight = $this->userinfo['attachmentpermissions']["{$this->upload['extension']}"]['height'];

				if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
				{
					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}

					if (($this->maxwidth > 0 AND $this->imginfo[0] > $this->maxwidth) OR ($this->maxheight > 0 AND $this->imginfo[1] > $this->maxheight))
					{
						#$resizemaxwidth = ($this->registry->config['Misc']['maxwidth']) ? $this->registry->config['Misc']['maxwidth'] : 2592;
						#$resizemaxheight = ($this->registry->config['Misc']['maxheight']) ?$this->registry->config['Misc']['maxheight'] : 1944;
						if ($this->registry->options['attachresize'] AND $this->image->is_valid_resize_type($this->imginfo[2]))
						{
							$this->upload['resized'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->maxwidth, $this->maxheight, $this->registry->options['thumbquality'], false, false, true, false);
							if (empty($this->upload['resized']['filedata']))
							{
								if (!empty($this->upload['resized']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
								{
									if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
									{
										$this->set_error('image_resize_failed_x', htmlspecialchars_uni($error));
										return false;
									}
									else
									{
										$this->set_error($this->upload['resized']['imageerror']);
										return false;
									}
								}
								else
								{
									$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
									return false;
								}
							}
							else
							{
								$jpegconvert = true;
							}
						}
						else
						{
							$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
							return false;
						}
					}
				}
				else if ($this->upload['extension'] != 'pdf')
				{	// don't error on .pdf imageinfo failures
					if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
					}
					else
					{
						$this->set_error('upload_invalid_image');
					}
					return false;
				}
			}

			$this->maxuploadsize = $this->fetch_max_uploadsize($this->upload['extension']);

			if ($this->maxuploadsize > 0 AND !$this->fetch_best_resize($jpegconvert, $this->registry->options['attachresize']))
			{
				return false;
			}

			if (!empty($this->upload['resized']))
			{
				if (!empty($this->upload['resized']['filedata']))
				{
					$this->upload['filestuff'] =& $this->upload['resized']['filedata'];
					$this->upload['filesize'] =& $this->upload['resized']['filesize'];
				}
				else
				{
					$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else if (!@filesize($this->upload['location']))
			{
				$this->set_error('upload_file_failed');
				return false;
			}

			$filehash = (empty($this->upload['filestuff']) ? md5_file($this->upload['location']) : md5($this->upload['filestuff']));
			$filesize = $this->upload['filesize'];
			if (!$this->check_attachment_overage($filehash, $filesize))
			{
				@unlink($this->upload['location']);
				return false;
			}

			// Generate Thumbnail
			if ($this->image->is_valid_info_extension($this->upload['extension']))
			{
				if ($this->registry->options['attachthumbs'] OR $forcethumbnail)
				{
					$labelimage = ($this->registry->options['attachthumbs'] == 3 OR $this->registry->options['attachthumbs'] == 4);
					$drawborder = ($this->registry->options['attachthumbs'] == 2 OR $this->registry->options['attachthumbs'] == 4);

					$this->upload['thumbnail'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->registry->options['attachthumbssize'], $this->registry->options['attachthumbssize'], $this->registry->options['thumbquality'], $labelimage, $drawborder, $jpegconvert, true, $this->upload['resized']['width'], $this->upload['resized']['height'], $this->upload['resized']['filesize']);
					if (empty($this->upload['thumbnail']['filedata']) AND !empty($this->upload['thumbnail']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
						{
							$this->set_warning('thumbnail_failed_x', htmlspecialchars_uni($error));
						}
						else
						{
							$this->set_warning($this->upload['thumbnail']['imageerror']);
						}
					}
				}
			}

			if (!empty($this->upload['resized']) AND $this->upload['resized']['filename'])
			{
				$this->upload['filename'] =& $this->upload['resized']['filename'];
			}

			if (isset($this->upload['utf8_name']) AND $this->upload['utf8_name'])
			{
				$this->upload['filename'] = $this->ncrencode_filename($this->upload['filename']);
			}

			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function check_attachment_overage($filehash, $filesize)
	{
		if ($this->registry->options['attachtotalspace'])
		{
			$filedata = $this->registry->db->query_first_slave("
				SELECT SUM(filesize) AS sum
				FROM " . TABLE_PREFIX . "filedata
				WHERE filehash <> '" . $this->registry->db->escape_string($filehash) . "'
			");

			($hook = vBulletinHook::fetch_hook('upload_attachsum_total')) ? eval($hook) : false;

			if (($filedata['sum'] + $filesize) > $this->registry->options['attachtotalspace'])
			{
				$overage = vb_number_format($filedata['sum'] + $filesize - $this->registry->options['attachtotalspace'], 1, true);
				$admincpdir = $this->registry->config['Misc']['admincpdir'];

				eval(fetch_email_phrases('attachfull', 0));
				vbmail($this->registry->options['webmasteremail'], $subject, $message);

				$this->set_error('upload_attachfull_total', $overage);
				return false;
			}
		}

		if ($this->userinfo['permissions']['attachlimit'])
		{
			$attachdata = $this->registry->db->query_first_slave("
				SELECT SUM(filesize) AS sum
				FROM
				(
					SELECT DISTINCT fd.filedataid, fd.filesize
					FROM " . TABLE_PREFIX . "attachment AS a
					INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
					WHERE
						a.userid = " . $this->userinfo['userid'] . "
							AND
						filehash <> '" . $this->registry->db->escape_string($filehash) . "'
				) AS x
			");

			($hook = vBulletinHook::fetch_hook('upload_attachsum_user')) ? eval($hook) : false;

			if (($attachdata['sum'] + $filesize) > $this->userinfo['permissions']['attachlimit'])
			{
				$overage = vb_number_format($attachdata['sum'] + $filesize - $this->userinfo['permissions']['attachlimit'], 1, true);

				$this->set_error('upload_attachfull_user', $overage, $this->registry->session->vars['sessionurl']);
				return false;
			}
		}

		return true;
	}

	function save_upload()
	{
		$this->data->set('dateline', TIMENOW);
		$this->data->set('thumbnail_dateline', TIMENOW);
		$this->data->setr('userid', $this->userinfo['userid']);
		$this->data->setr('filename', $this->upload['filename']);
		$this->data->setr_info('thumbnail', $this->upload['thumbnail']['filedata']);

		$this->data->set('width', $this->imginfo[0]);
		$this->data->set('height', $this->imginfo[1]);
		$this->data->set('thumbnail_width', $this->upload['thumbnail']['width']);
		$this->data->set('thumbnail_height', $this->upload['thumbnail']['height']);

		if (!empty($this->upload['filestuff']))
		{
			$this->data->setr_info('filedata', $this->upload['filestuff']);
		}
		else
		{
			$this->data->set_info('filedata_location', $this->upload['location']);
		}

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}

		@unlink($this->upload['location']);
		unset($this->upload);

		return $result;
	}
}

class vB_Upload_Userpic extends vB_Upload_Abstract
{
	function fetch_max_uploadsize($extension)
	{
		return $this->maxuploadsize;
	}

	function is_valid_extension($extension)
	{
		return !empty($this->image->info_extensions["{$this->upload['extension']}"]);
	}

	function process_upload($uploadurl = '')
	{
		if ($uploadurl == '' OR $uploadurl == 'http://www.')
		{
			$uploadstuff =& $this->registry->GPC['upload'];
		}
		else
		{
			if (is_uploaded_file($this->registry->GPC['upload']['tmp_name']))
			{
				$uploadstuff =& $this->registry->GPC['upload'];
			}
			else
			{
				$uploadstuff =& $uploadurl;
			}
		}

		if ($this->accept_upload($uploadstuff))
		{
			if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
			{
				if ($this->image->is_valid_thumbnail_extension(file_extension($this->upload['filename'])))
				{
					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}
				}
				else
				{
					$this->set_error('upload_invalid_image');
					return false;
				}

				if ($this->allowanimation === false AND $this->imginfo[2] == 'GIF' AND $this->imginfo['animated'])
				{
					$this->set_error('upload_invalid_animatedgif');
					return false;
				}

				if (($this->maxwidth AND $this->imginfo[0] > $this->maxwidth) OR ($this->maxheight AND $this->imginfo[1] > $this->maxheight) OR $this->image->fetch_must_convert($this->imginfo[2]))
				{
					// shrink-a-dink a big fat image or an invalid image for browser display (PSD, BMP, etc)
					$this->upload['resized'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->maxwidth, $this->maxheight, $this->registry->options['thumbquality'], false, false, false, false);
					if (empty($this->upload['resized']['filedata']))
					{
						$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
						return false;
					}
					$jpegconvert = true;
				}
			}
			else
			{
				if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				{
					$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
				}
				else
				{
					$this->set_error('upload_invalid_file');
				}
				return false;
			}

			if ($this->maxuploadsize > 0 AND (!$this->fetch_best_resize($jpegconvert)))
			{
				return false;
			}

			if (!empty($this->upload['resized']))
			{
				if (!empty($this->upload['resized']['filedata']))
				{
					$this->upload['filestuff'] =& $this->upload['resized']['filedata'];
					$this->upload['filesize'] =& $this->upload['resized']['filesize'];
					$this->imginfo[0] =& $this->upload['resized']['width'];
					$this->imginfo[1] =& $this->upload['resized']['height'];
				}
				else
				{
					$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else if (!($this->upload['filestuff'] = @file_get_contents($this->upload['location'])))
			{
				$this->set_error('upload_file_failed');
				return false;
			}
			@unlink($this->upload['location']);

			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function save_upload()
	{
		$this->data->set('userid', $this->userinfo['userid']);
		$this->data->set('dateline', TIMENOW);
		$this->data->set('filename', $this->upload['filename']);
		$this->data->set('width', $this->imginfo[0]);
		$this->data->set('height', $this->imginfo[1]);
		$this->data->setr('filedata', $this->upload['filestuff']);
		$this->data->set_info('avatarrevision', $this->userinfo['avatarrevision']);
		$this->data->set_info('profilepicrevision', $this->userinfo['profilepicrevision']);
		$this->data->set_info('sigpicrevision', $this->userinfo['sigpicrevision']);

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}

		unset($this->upload);

		return $result;
	}
}

class vB_Upload_Image extends vB_Upload_Abstract
{
	/**
	* Path that uploaded image is to be saved to
	*
	* @var	string
	*/
	var $path = '';

	function is_valid_extension($extension)
	{
		return !empty($this->image->info_extensions["{$this->upload['extension']}"]);
	}

	function process_upload($uploadurl = '')
	{
		if ($uploadurl == '' OR $uploadurl == 'http://www.')
		{
			$uploadstuff =& $this->registry->GPC['upload'];
		}
		else
		{
			if (is_uploaded_file($this->registry->GPC['upload']['tmp_name']))
			{
				$uploadstuff =& $this->registry->GPC['upload'];
			}
			else
			{
				$uploadstuff =& $uploadurl;
			}
		}

		if ($this->accept_upload($uploadstuff))
		{
			if ($this->image->is_valid_thumbnail_extension(file_extension($this->upload['filename'])))
			{
				if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
				{
					if (!$this->image->fetch_must_convert($this->imginfo[2]))
					{
						if (!$this->imginfo[2])
						{
							$this->set_error('upload_invalid_image');
							return false;
						}

						if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
						{
							$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
							return false;
						}
					}
					else
					{
						$this->set_error('upload_invalid_image');
						return false;
					}
				}
				else
				{
					$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
					return false;
				}
			}
			else
			{
				$this->set_error('upload_invalid_image');
				return false;
			}

			if (!$this->upload['filestuff'])
			{
				if (!($this->upload['filestuff'] = file_get_contents($this->upload['location'])))
				{
					$this->set_error('upload_file_failed');
					return false;
				}
			}
			@unlink($this->upload['location']);

			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function save_upload()
	{
		if (!is_writable($this->path) OR !($fp = fopen($this->path . '/' . $this->upload['filename'], 'wb')))
		{
			$this->set_error('invalid_file_path_specified');
			return false;
		}

		if (@fwrite($fp, $this->upload['filestuff']) === false)
		{
			$this->set_error('error_writing_x', $this->upload['filename']);
			return false;
		}

		@fclose($fp);
		return $this->path . '/' . $this->upload['filename'];
	}
}


/**
* Upload class for Social Group Icons.
*
* @package	vBulletin
* @version	$Revision: 37230 $
* @date		$Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
*/
class vB_Upload_SocialGroupIcon extends vB_Upload_Abstract
{
	/**
	 * GroupInfo for the group the icon is being updated for
	 *
	 * @access protected
	 *
	 * @var array mixed
	 */
	var $groupinfo;

	// #######################################################################

	function vB_Upload_SocialGroupIcon(&$registry)
	{
		parent::vB_Upload_Abstract($registry);
	}

	/**
	* Sets the group info
	*
	* @access public
	*
	* @param array mixed $groupinfo			The groupinfo for the group
	*/
	function set_group_info($groupinfo)
	{
		$this->groupinfo = $groupinfo;
	}


	/**
	* Gets the set max upload size
	*
	* @access protected
	*
	* @param unknown_type $extension
	* @return unknown
	*/
	function fetch_max_uploadsize($extension)
	{
		return $this->maxuploadsize;
	}


	/**
	* Checks if the extension is valid
	*
	* @access protected
	*
	* @param unknown_type $extension
	* @return unknown
	*/
	function is_valid_extension($extension)
	{
		return !empty($this->image->info_extensions["{$this->upload['extension']}"]);
	}


	/**
	 * The core method.  Handles the uploaded file.
	 *
	 * @access public
	 *
	 * @param string $uploadurl					Optional URL to fetch the file from
	 * @return boolean							Success
	 */
	function process_upload($uploadurl = '')
	{
		if ($uploadurl == '' OR $uploadurl == 'http://www.')
		{
			$uploadstuff =& $this->registry->GPC['upload'];
		}
		else
		{
			if (is_uploaded_file($this->registry->GPC['upload']['tmp_name']))
			{
				$uploadstuff =& $this->registry->GPC['upload'];
			}
			else
			{
				$uploadstuff =& $uploadurl;
			}
		}

		if ($this->accept_upload($uploadstuff))
		{
			if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
			{
				if ($this->image->is_valid_thumbnail_extension(file_extension($this->upload['filename'])))
				{
					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}
				}
				else
				{
					$this->set_error('upload_invalid_image');
					return false;
				}

				if (!$this->allowanimation AND $this->imginfo[2] == 'GIF' AND $this->imginfo['animated'])
				{
					$this->set_error('upload_invalid_animatedgif');
					return false;
				}

				if (($this->imginfo[0] > FIXED_SIZE_GROUP_ICON_WIDTH) OR ($this->imginfo[1] > FIXED_SIZE_GROUP_ICON_HEIGHT) OR $this->image->fetch_must_convert($this->imginfo[2]))
				{
					// shrink-a-dink a big fat image or an invalid image for browser display (PSD, BMP, etc)
					$this->upload['resized'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], FIXED_SIZE_GROUP_ICON_WIDTH, FIXED_SIZE_GROUP_ICON_HEIGHT, $this->registry->options['thumbquality'], false, false, false, false);
					if (empty($this->upload['resized']['filedata']))
					{
						$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
						return false;
					}
					$jpegconvert = true;
				}
			}
			else
			{
				if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				{
					$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
				}
				else
				{
					$this->set_error('upload_invalid_file');
				}
				return false;
			}

			if ($this->maxuploadsize > 0 AND !$this->fetch_best_resize($jpegconvert))
			{
				return false;
			}

			if (!empty($this->upload['resized']))
			{
				if (!empty($this->upload['resized']['filedata']))
				{
					$this->upload['filestuff'] =& $this->upload['resized']['filedata'];
					$this->upload['filesize'] =& $this->upload['resized']['filesize'];
					$this->imginfo[0] =& $this->upload['resized']['width'];
					$this->imginfo[1] =& $this->upload['resized']['height'];
				}
				else
				{
					$this->set_error('upload_exceeds_dimensions', FIXED_SIZE_GROUP_ICON_WIDTH, FIXED_SIZE_GROUP_ICON_HEIGHT, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else if (!($this->upload['filestuff'] = @file_get_contents($this->upload['location'])))
			{
				$this->set_error('upload_file_failed');
				return false;
			}

			$this->build_thumbnail();

			@unlink($this->upload['location']);

			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}


	/**
	* Builds a resized version of the image data
	*
	* @access protected
	*/
	function build_thumbnail()
	{
		$this->upload['thumbnail_filedata'] = $this->image->fetch_thumbnail(
			$this->upload['filename'],
			$this->upload['location'],
			FIXED_SIZE_GROUP_THUMB_WIDTH,
			FIXED_SIZE_GROUP_THUMB_HEIGHT,
			$this->registry->options['thumbquality'],
			false,
			false,
			false,
			true,
			$this->upload['resized']['width'],
			$this->upload['resized']['height'],
			$this->upload['resized']['filesize']
		);
	}


	/**
	* Saves the image data with the supplied DataManager
	*
	* @access protected
	*
	* @return boolean							Success
	*/
	function save_upload()
	{
		$ext_pos = strrpos($this->upload['filename'], '.');

		$this->data->setr_info('group', $this->groupinfo);
		$this->data->set('groupid', $this->groupinfo['groupid']);
		$this->data->set('userid', $this->userinfo['userid']);
		$this->data->set('dateline', TIMENOW);
		$this->data->set('width', $this->imginfo[0]);
		$this->data->set('height', $this->imginfo[1]);
		$this->data->set('extension', substr($this->upload['filename'], $ext_pos + 1));
		$this->data->setr('filedata', $this->upload['filestuff']);
		$this->data->setr('thumbnail_filedata', $this->upload['thumbnail']['filedata']);
		$this->data->set('thumbnail_width', $this->upload['thumbnail']['width']);
		$this->data->set('thumbnail_height', $this->upload['thumbnail']['height']);

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}
		else
		{
			if ($this->registry->options['sg_enablesocialgroupicons'])
			{
				fetch_socialgroup_newest_groups(true);
			}
		}

		unset($this->upload);

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile: class_upload.php,v $ - $Revision: 37230 $
|| ####################################################################
\*======================================================================*/