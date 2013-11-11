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
* Single attachment display class
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
class vB_Attachment_Display_Single_Library
{
	/**
	* Singleton emulation
	*
	*/
	private static $instance = null;

	/**
	* Select library
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer			Unique id of this contenttype (forum post, blog entry, etc)
	* @param	boolean			Display thumbnail
	* @param	integer			Unique id of this item attachment.attachmentid
	*
	* @return	object
	*/
	public static function &fetch_library(&$registry, $contenttypeid, $thumbnail, $attachmentid)
	{
		if (self::$instance)
		{
			return self::$instance;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();

		$attachmentinfo = array();
		if (!$contenttypeid)
		{
			// Send the contenttypeid into fetch_library to avoid this query!
			$contentinfo = $registry->db->query_first_slave("
				SELECT a.contenttypeid
				FROM " . TABLE_PREFIX . "attachment AS a
				WHERE a.attachmentid = $attachmentid
			");
			$contenttypeid = $contentinfo['contenttypeid'];
		}

		if (!($contenttypeid = $types->getContentTypeID($contenttypeid)))
		{
			return false;
		}

		$package = $types->getContentTypePackage($contenttypeid);
		$class = $types->getContentTypeClass($contenttypeid);

		$selectclass = "vB_Attachment_Display_Single_{$package}_{$class}";

		$path = DIR . '/packages/' . strtolower($package) . '/attach/' . strtolower($class) . '.php';
		if (file_exists($path))
		{
			include_once(DIR . '/packages/' . strtolower($package) . '/attach/' . strtolower($class) . '.php');
			if (class_exists($selectclass))
			{
				self::$instance = new $selectclass($registry, $attachmentid, $thumbnail);
				return self::$instance;
			}
		}

		return false;
	}
}

/**
* Abstracted Attachment display class
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
* @abstract
*/
abstract class vB_Attachment_Display_Single
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Attachmentid
	*
	* @var	Integer
	*/
	protected $attachmentid = 0;

		/**
	* Display thumbnail
	*
	* @var	bool
	*/
	protected $thumbnail = false;

	/**
	* Attachment information
	*
	* @var	Array
	*/
	protected $attachmentinfo = array();

	/**
	* Browsing information for WOL
	*
	* @var	Array
	*/
	protected $browsinginfo = array();

	/**
	* Constructor
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer			Unique id of this item attachment.attachmentid
	* @param	boolean			Display thumbnail
	*
	* @return	void
	*/
	public function __construct(&$registry, $attachmentid, $thumbnail, $attachmentid_2 = false)
	{
		$this->registry =& $registry;
		$this->attachmentid = $attachmentid ? $attachmentid : $attachmentid_2;
		$this->thumbnail = $thumbnail;
	}


	/**
	* Return attachmentinfo array
	*
	* @return	array
	*/
	public function fetch_attachmentinfo()
	{
		return $this->attachmentinfo;
	}

	/**
	* Return information used in session update to modify the session table for WOL
	*
	* @return	array
	*/
	public function fetch_browsinginfo()
	{
		return $this->browsinginfo;
	}

	/**
	* Verify permissions of a single attachment
	*
	* @return	bool
	*/
	abstract public function verify_attachment();

	/**
	*	Verify permissions of a single attachment
	*
	* @return bool
	*/
	protected function verify_attachment_specific($contenttype, $selectsql = array(), $joinsql = array(), $wheresql = array())
	{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$contenttypeid = intval($types->getContentTypeID($contenttype));

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('attachment_start')) ? eval($hook) : false;

		$selectfields = array(
				"a.userid, a.attachmentid, a.state, a.contentid, a.filename, a.contenttypeid",
				"fd.userid as uploader, fd.extension, fd.filedataid",
				$this->thumbnail ? "fd.thumbnail_dateline AS dateline, fd.thumbnail_filesize AS filesize" : "fd.dateline, fd.filesize",
				"at.extension, at.mimetype, at.contenttypes",
		);
		if ($selectsql)
		{
			$selectfields = array_merge($selectfields, $selectsql);
		}

		$joinfields = array(
			"INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)",
			"LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)",
		);
		if ($joinsql)
		{
			$joinfields = array_merge($joinfields, $joinsql);
		}

		$wherefields = array(
			"a.attachmentid = " . intval($this->attachmentid),
			"a.contenttypeid = " . intval($contenttypeid),
		);
		if ($wheresql)
		{
			$wherefields = array_merge($wherefields , $wheresql);
		}
		if (!($this->attachmentinfo = $this->registry->db->query_first_slave("
			SELECT
				" . implode(",\r\n", $selectfields) . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "attachment AS a
			" . implode("\r\n", $joinfields) . "
			$hook_query_joins
			WHERE " . implode(" AND ", $wherefields) . "
			$hook_query_where
		")))
		{
			return false;
		}
		else
		{
			$settings = @unserialize($this->attachmentinfo['contenttypes']);
			$this->attachmentinfo['newwindow'] = empty($settings[$this->attachmentinfo['contenttypeid']]['n'])? 0 : 1;
			return true;
		}
	}
}

/**
* Multiple attachment display class
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
class vB_Attachment_Display_Multiple
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Content Type classes
	*
	* @var	Array
	*/
	protected $contentref = array();

	/**
	*
	*
	* @var	boolean
	*/
	public $usable = true;

	/**
	* Constructor
	* Sets registry
	*
	* @param	vB_Registry
	*
	* @return	void
	*/
	public function __construct(&$registry)
	{
		$this->registry =& $registry;

		if (!is_subclass_of($this, 'vB_Attachment_Display_Multiple'))
		{
			require_once(DIR . "/includes/class_bootstrap_framework.php");
			vB_Bootstrap_Framework::init();

			$indexed_types = array();
			$collection = new vB_Collection_ContentType();
			$collection->filterAttachable(true);
			foreach ($collection AS $type)
			{
				$value['package'] = $type->getPackageClass();
				$value['class'] = $type->getClass();
				$indexed_types[$type->getID()] = $value;
			}

			foreach ($indexed_types AS $contenttypeid => $content)
			{
				$selectclass = "vB_Attachment_Display_Multiple_{$content['package']}_{$content['class']}";

				//we check if the class exists, but also don't want warnings if the content file exists.
				$include_file = DIR . '/packages/' . strtolower($content['package']) . '/attach/' . strtolower($content['class']) . '.php';
				if (!file_exists($include_file))
				{
					continue;
				}

				include_once($include_file);
				if (!class_exists($selectclass))
				{
					continue;
				}

				$this->contentref["$contenttypeid"] = new $selectclass($this->registry, $contenttypeid);
				if (!$this->contentref["$contenttypeid"]->usable)
				{
					unset($this->contentref["$contenttypeid"]);
				}
			}
		}
	}

	/**
	* Fetches the aggregate results of an attachment query
	*
	* @param	string	SQL WHERE criteria
	* @param	boolean	Return the total count and filesize instead of a list of attachments
	* @param	integer	Offset to return results from
	* @param	integer Number of attachments to return - 0 to disable
	* @param	string	SQL order by
	* @param	string	SQL sort order
	*
	* @return	array
	*/
	public function fetch_results($criteria, $countonly = false, $start = 0, $limit = 25, $orderby = 'displayorder', $sortorder = 'DESC')
	{
		$unionsql = array();
		$classes = array();
		$this->criteria = $criteria;

		if (!$countonly)
		{
			$selectfieldssql = array(
				'a.attachmentid',
				'a.contenttypeid',
				'a.displayorder',
			);
			switch($orderby)
			{
				case 'filesize':
					$selectfieldssql[] = "fd.filesize";
					break;
				case 'state':
				case 'dateline':
				case 'filename':
				case 'counter':
					$selectfieldssql[] = "a.$orderby";
					break;
				case 'username':
					$selectfieldssql[] = "user.username";
					break;
			}
			$selectfields = implode(', ', $selectfieldssql);
		}
		else
		{
			$selectfields = "COUNT(*) AS count, SUM(fd.filesize) AS sum";
		}

		foreach ($this->contentref AS $contentref)
		{
			$unionsql[] = $contentref->fetch_sql_ids($criteria, $selectfields);
		}

		$sql = array(
			"(" . implode(") UNION ALL (", $unionsql) . ")"
		);

		if (!$countonly)
		{
			$sql[] = "ORDER BY $orderby $sortorder";
			if ($limit)
			{
				$sql[] = "LIMIT $start, $limit";
			}
		}

		$results = $this->registry->db->query_read_slave(implode("\r\n", $sql));
		if ($countonly)
		{
			$attachdata = $this->registry->db->query_first("
			SELECT SUM(filesize) AS sum
			FROM
			(
				SELECT DISTINCT fd.filedataid, fd.filesize
				FROM " . TABLE_PREFIX . "attachment AS a
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
				WHERE
					$criteria
			) AS x
			");

			$count = 0;
			$sum = 0;
			// simulate query_first
			while ($result = $this->registry->db->fetch_array($results))
			{
				$count += $result['count'];
				$sum += $result['sum'];
			}
			return array('count' => $count, 'sum' => $sum, 'uniquesum' => $attachdata['sum']);
		}
		else
		{
			$bycontent = $byorder = array();
			while ($result = $this->registry->db->fetch_array($results))
			{
				$bycontent["$result[contenttypeid]"]["$result[attachmentid]"] = $result['attachmentid'];
				$byorder["$result[attachmentid]"] = 1;
			}

			foreach ($bycontent AS $contenttypeid => $attachmentids)
			{
				$attachments = $this->contentref["$contenttypeid"]->fetch_sql($attachmentids);
				while ($attach = $this->registry->db->fetch_array($attachments))
				{
					$byorder["$attach[attachmentid]"] = $attach;
				}
			}

			return $byorder;
		}
	}

	protected function fetch_sql_specific($attachmentids, $selectsql = array(), $joinsql = array())
	{
		$selectfields = array(
			"fd.filesize AS size, fd.thumbnail_filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.filesize, fd.thumbnail_dateline, fd.thumbnail_width, fd.thumbnail_height",
			"a.filename, a.counter, a.userid, a.attachmentid, a.dateline, a.contenttypeid, a.contentid, IF(a.contentid = 0, 1, 0) AS inprogress, a.state, a.caption",
		);
		if ($selectsql)
		{
			$selectfields = array_merge($selectfields, $selectsql);
		}

		$attachments = $this->registry->db->query_read_slave("
			SELECT
			" . implode(",\r\n", $selectfields) . "
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
			" . (!empty($joinsql) ? implode("\r\n", $joinsql) : "") . "
			WHERE
				a.attachmentid IN (" . implode(", ", $attachmentids) . ")
		");

		return $attachments;
	}

	protected function fetch_sql_ids_specific($contenttypeid, $criteria, $selectfields, $subwheresql = array(), $joinsql = array())
	{
		if (empty($subwheresql))
		{
			$subwheresql[] = "a.contentid <> 0";
		}
		$wheresql = array(
			"a.contenttypeid = $contenttypeid",
			"
			(
				(
					a.contentid = 0
						AND
					a.userid = {$this->registry->userinfo['userid']}
				)
					OR
				(
					" . implode(" AND ", $subwheresql) . "
				)
			)
			",
		);
		if ($criteria)
		{
			$wheresql[] = $criteria;
		}

		return "
			SELECT
				$selectfields
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)
			" . (!empty($joinsql) ? implode("\r\n", $joinsql) : "") . "
			WHERE
				" . implode(" AND ", $wheresql) . "
		";
	}

	/**
	* Return content specific template for displaying results in aggregate view
	*
	* @param	array		Attachment information
	*
	* @return	string
	*/
	public function process_attachment($attachment, $showthumbs = false)
	{
		global $show;

		$show['moderated'] = ($attachment['state'] == 'moderation');

		$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
		$attachment['counter'] = vb_number_format($attachment['counter']);
		$attachment['size'] = vb_number_format($attachment['size'], 1, true);
		$attachment['postdate'] = vbdate($this->registry->options['dateformat'], $attachment['dateline'], true);
		$attachment['posttime'] = vbdate($this->registry->options['timeformat'], $attachment['dateline']);
		$attachment['attachmentextension'] = strtolower(file_extension($attachment['filename']));

		$result = $this->contentref["$attachment[contenttypeid]"]->process_attachment_template($attachment, $showthumbs);
		if ($show['candelete'] OR $show['canmoderate'])
		{
			$show['inlinemod'] = true;
		}
		return $result;
	}

	/**
	* Return content specific url to the owner (post, entry) of an attachment
	*
	* @param	array		Content information
	* @param	string	Prefix for url if we don't have "http(s)"
	*
	* @return	string
	*/
	public function fetch_content_url($contentinfo, $prefix = null)
	{
		$url = $this->contentref["$contentinfo[contenttypeid]"]->fetch_content_url_instance($contentinfo);
		if ($prefix AND !preg_match('#^https?://#si', $url))
		{
			return $prefix . $url;
		}
		return $url;
	}
}

// #######################################################################
// ############################# STORAGE #################################
// #######################################################################

/**
* Attachment Storage class
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
class vB_Attachment_Store_Library
{
	/**
	* Singleton emulation
	*
	*/
	private static $instance = null;

	/**
	* Select library
	*
	* @return	object
	*/
	public static function &fetch_library(&$registry, $contenttypeid, $categoryid = 0, $values = array())
	{
		if (self::$instance)
		{
			return self::$instance;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();

		if (!($contenttypeid = $types->getContentTypeID($contenttypeid)))
		{
			return false;
		}

		$package = $types->getContentTypePackage($contenttypeid);
		$class = $types->getContentTypeClass($contenttypeid);

		$selectclass = "vB_Attachment_Store_{$package}_{$class}";
		$path = DIR . '/packages/' . strtolower($package) . '/attach/' . strtolower($class) . '.php';
		if (file_exists($path))
		{
			include_once($path);
			if (class_exists($selectclass))
			{
				self::$instance = new $selectclass($registry, $contenttypeid, $categoryid, $values);
				return self::$instance;
			}
		}
		return false;
	}
}

/**
* Abstracted Attachment storage class
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
* @abstract
*/
abstract class vB_Attachment_Store
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	*	Array of information specific to this contenttype, needed for permisson checks, etc
	*
	* @var	array
	*/
	protected $values = array();

	/**
	*	contentypeid of this object
	*
	* @var	integer
	*/
	private $contenttypeid = 0;

	/**
	*	contentid of the attachment owner
	*
	* @var	integer
	*/
	protected $contentid = 0;

	/**
	*	Username of content owner
	*
	* @var	string
	*/
	protected $content_owner = '';

	/**
	*	Attachment count of this content object
	*
	* @var	integer
	*/
	protected $attachcount = 0;

	/**
	*	Override the vboption for thumbnails
	*
	* @var	boolean
	*/
	public $forcethumbnail = false;

	/**
	*	Upload errors
	*
	* @var	array
	*/
	public $errors = array();

	/**
	*	Userinfo of owner of content
	*
	* @var	array
	*/
	public $userinfo = array();

	/**
	* Constructor
	* Sets registry
	*
	* @return	void
	*/
	public function __construct(&$registry, $contenttypeid, $categoryid, $values)
	{
		$this->registry =& $registry;
		$this->values = $values;
		$this->userinfo = $this->registry->userinfo;
		$this->contenttypeid = $contenttypeid;
		$this->categoryid = $categoryid;
	}

	/**
	* Given an attachmentid, retrieve values that verify_permissions needs
	*
	* @param	int	Attachmentid
	*
	* @return	array
	*/
	abstract protected function fetch_associated_contentinfo($attachmentid);

	/**
	* Verify permissions
	*
	* @return	bool
	*/
	abstract protected function verify_permissions($info = array());

	/**
	 * Verify permissions based on specified attachmentid
	 *
	 * @param int $attachmentid
	 *
	 * @return bool
	 */
	public function verify_permissions_attachmentid($attachmentid)
	{
		$values = $this->fetch_associated_contentinfo($attachmentid);
		return $this->verify_permissions($values);
	}

	/**
	* Verify amount of attachments has not exceed maximum number based on permissions
	*  - defaulting to 'Post' type permissions, but can be overidden by subclasses
	*
	* @param	array		Attachment information
	*
	* @return	bool	true if we are under the limit
	*/
	protected function verify_max_attachments($attachment)
	{
		global $vbphrase;

		if ($this->registry->options['attachlimit'] AND $this->attachcount > $this->registry->options['attachlimit'])
		{
			$error = construct_phrase($vbphrase['you_may_only_attach_x_files_per_post'], $this->registry->options['attachlimit']);
			$this->errors[] = array(
				'filename' => is_array($attachment) ? $attachment['name'] : $attachment,
				'error'    => $error
			);
			return false;
		}

		return true;
	}

	/**
	* Count new and pre-existing attachments
	*
	* @return	bool
	*/
	public function fetch_attachcount()
	{
		$currentattaches = $this->registry->db->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "attachment
			WHERE
				posthash = '" . $this->registry->db->escape_string($this->values['posthash']) . "'
					AND
				contenttypeid = " . $this->contenttypeid . "
				" . ($this->contentid ? " AND contentid = 0 "  : "") . "
		");
		$this->attachcount = $currentattaches['count'];

		if ($this->contentid)
		{
			$currentattaches = $this->registry->db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "attachment
				WHERE
					contentid = " . $this->contentid . "
						AND
					contenttypeid = " . $this->contenttypeid . "
			");
			$this->attachcount += $currentattaches['count'];
			//$show['postowner'] doesn't seem to be used anywhere, removed instead of making $show global
		}

		return true;
	}

	/**
	* Fetch new and pre-existing attachments
	*
	* @return	object
	*/
	public function fetch_attachments()
	{
		$attachments = $this->registry->db->query_read(
			($this->contentid ? "(" : "") .
				"SELECT
					a.contentid, a.dateline, a.filename, a.attachmentid, a.userid, a.displayorder,
					fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.filedataid, fd.userid AS fuserid, fd.extension,
					fd.width, fd.height, fd.thumbnail_width, fd.thumbnail_height, fd.thumbnail_dateline
				FROM " . TABLE_PREFIX . "attachment AS a
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
				WHERE
					a.posthash = '" . $this->registry->db->escape_string($this->values['posthash']) . "'
						AND
					a.contenttypeid = " . intval($this->contenttypeid) . "
			" . ($this->contentid ? ") UNION (" : "") . "
			" . ($this->contentid ? "
				SELECT
					a.contentid, a.dateline, a.filename, a.attachmentid, a.userid, a.displayorder,
					fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.filedataid, fd.userid AS fuserid, fd.extension,
					fd.width, fd.height, fd.thumbnail_width, fd.thumbnail_height, fd.thumbnail_dateline
				FROM " . TABLE_PREFIX . "attachment AS a
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
				WHERE
					a.contentid = " . intval($this->contentid) . "
						AND
					a.contenttypeid = " . intval($this->contenttypeid) . "
			)
			" : "") . "
			ORDER BY displayorder
		");

		return $attachments;
	}

	public function delete($ids)
	{
		if (!empty($ids))
		{
			$attachdata =& datamanager_init('Attachment', $this->registry, ERRTYPE_STANDARD, 'attachment');
			$attachids = array_map('intval', array_keys($ids));

			$condition = array(
				"a.attachmentid IN (" . implode(", ", $attachids) . ")",
				"a.contenttypeid = " . intval($this->contenttypeid),
			);
			if ($this->contentid)
			{
				$condition[] = "(a.contentid = " . intval($this->contentid) . " OR a.posthash = '" . $this->registry->db->escape_string($this->values['posthash']) . "')";
			}
			else
			{
				$condition[] = "a.posthash = '" . $this->registry->db->escape_string($this->values['posthash']) . "'";
			}
			$attachdata->condition = implode(" AND ", $condition);
			$attachdata->delete(true, false);
			unset($attachdata);
		}
	}

	public function upload($files, $urls, $filedata, $imageonly = false)
	{
		$errors = array();
		require_once(DIR . '/includes/class_upload.php');
		require_once(DIR . '/includes/class_image.php');

		// check for any funny business
		$filecount = 1;
		if (!empty($files['tmp_name']))
		{
			foreach ($files['tmp_name'] AS $filename)
			{
				if (!empty($filename))
				{
					if ($filecount > $this->registry->options['attachboxcount'])
					{
						@unlink($filename);
					}
					$filecount++;
				}
			}
		}

		// Move any urls into the attachment array if we allow url upload
		if ($this->registry->options['attachurlcount'])
		{
			$urlcount = 1;
			foreach ($urls AS $url)
			{
				if (!empty($url) AND $urlcount <= $this->registry->options['attachurlcount'])
				{
					$index = count($files['name']);
					$files['name']["$index"] = $url;
					$files['url']["$index"] = true;
					$urlcount++;
				}
			}
		}

		if (!empty($filedata))
		{
			foreach($filedata AS $filedataid)
			{
				$index = count($files['name']);
				$files['name']["$index"] = 'filedata';
				$files['filedataid']["$index"] = $filedataid;
			}
		}

		//$this->attachcount = 0;
		$ids = array();
		$uploadsum = count($files['name']);
		for ($x = 0; $x < $uploadsum; $x++)
		{
			if (!$files['name']["$x"])
			{
				if ($files['tmp_name']["$x"])
				{
					@unlink($files['tmp_name']["$x"]);
				}
				continue;
			}

			$attachdata =& $this->fetch_attachdm();

			$upload = new vB_Upload_Attachment($this->registry);
			$upload->contenttypeid = $this->contenttypeid;
			$image =& vB_Image::fetch_library($this->registry);
			$upload->userinfo = $this->userinfo;

			$upload->data =& $attachdata;
			$upload->image =& $image;
			if ($uploadsum > 1)
			{
				$upload->emptyfile = false;
			}

			if ($files['filedataid']["$x"])
			{
				if (!($filedatainfo = $this->registry->db->query_first_slave("
					SELECT
						acu.filedataid, acu.filename, fd.filehash, fd.filesize, fd.extension
					FROM " . TABLE_PREFIX . "attachmentcategoryuser AS acu
					INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (acu.filedataid = fd.filedataid)
					WHERE
						acu.filedataid = " . intval($files['filedataid']["$x"]) . "
							AND
						acu.userid = " . $this->registry->userinfo['userid'] . "
				")))
				{
					$this->errors[] = array(
						'filename' => "",
						'error'    => fetch_error('invalid_filedataid_x', $files['filedataid']["$x"])
					);
					continue;
				}

				$attachment = array(
					'filedataid'	=> $files['filedataid']["$x"],
					'name'			=> $filedatainfo['filename'],
					'filehash'		=> $filedatainfo['filehash'],
					'filesize'		=> $filedatainfo['filesize'],
					'extension'		=> $filedatainfo['extension'],
					'filename'		=> $filedatainfo['filename']
				);
			}
			else if ($files['url']["$x"])
			{
				$attachment = $files['name']["$x"];
			}
			else
			{
				$attachment = array(
					'name'			=> $files['name']["$x"],
					'tmp_name'		=> $files['tmp_name']["$x"],
					'error'			=> $files['error']["$x"],
					'size'			=> $files['size']["$x"],
					'utf8_name'		=> $files['utf8_names']
				);
			}
			$this->attachcount++;
			$ids[] = $this->process_upload($upload, $attachment, $imageonly);
		}

		return implode(',', $ids);
	}

	protected function &fetch_attachdm()
	{
		// here we call the attach/file data combined dm
		$attachdata =& datamanager_init('AttachmentFiledata', $this->registry, ERRTYPE_ARRAY, 'attachment');
		$attachdata->set('contenttypeid', $this->contenttypeid);
		$attachdata->set('posthash', $this->values['posthash']);
		$attachdata->set_info('contentid', $this->contentid);
		$attachdata->set_info('categoryid', $this->categoryid);
		$attachdata->set('state', 'visible');

		return $attachdata;
	}

	protected function process_upload($upload, $attachment, $imageonly = false)
	{
		// first verify maximum number of attachments has not been reached
		if (!$this->verify_max_attachments($attachment))
		{
			return false;
		}

		// process the upload
		if (!($attachmentid = $upload->process_upload($attachment, $this->forcethumbnail, $imageonly)))
		{
			$this->attachcount--;
		}

		// add any upload errors to the error array
		if ($error = $upload->fetch_error())
		{
			$this->errors[] = array(
				'filename' => is_array($attachment) ? $attachment['name'] : $attachment,
				'error'    => $error,
			);
		}

		return $attachmentid;
	}
}

/**
* Class for initiating proper subclass to extende attachment DM operations
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
class vB_Attachment_Dm_Library
{
	/**
	* Select library
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer			Unique id of this contenttype (forum post, blog entry, etc)
	*
	* @return	object
	*/
	public static function &fetch_library(&$registry, $contenttypeid)
	{
		static $instance;

		if (!$instance["$contenttypeid"])
		{
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			vB_Bootstrap_Framework::init();
			$types = vB_Types::instance();

			if (!($contenttypeid = $types->getContentTypeID($contenttypeid)))
			{
				return false;
			}

			$package = $types->getContentTypePackage($contenttypeid);
			$class = $types->getContentTypeClass($contenttypeid);

			$selectclass = "vB_Attachment_Dm_{$package}_{$class}";
			$path = DIR . '/packages/' . strtolower($package) . '/attach/' . strtolower($class) . '.php';
			if (file_exists($path))
			{
				include_once($path);
				if (class_exists($selectclass))
				{
					$instance["$contenttypeid"] = new $selectclass($registry, $contenttypeid);
					return $instance["$contenttypeid"];
				}
			}
			return false;
		}

		return $instance["$contenttypeid"];
	}
}

/**
* Abstract class for attachment dm operation across content types
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
abstract class vB_Attachment_Dm
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Lists of data
	*
	* @var	Array
	*/
	protected $lists = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	*/
	public function __construct(&$registry, $contenttypeid)
	{
		$this->registry =& $registry;
		$this->contenttypeid = $contenttypeid;
	}

	/**
	* post save function - extend if the contenttype needs to do anything
	*
	* @param
	*/
	public function post_save_each()
	{
		return true;
	}

	/**
	* pre_delete function - extend if the contenttype needs to do anything
	*
	* @param	array	list of deleted attachment ids that belong to a specific
	*/
	public function pre_delete($list)
	{
		return true;
	}

	/**
	* post_delete function - extend if the contenttype needs to do anything
	*
	* @param	array	list of deleted attachment ids that belong to a specific
	*/
	public function post_delete()
	{
		return true;
	}

	/**
	* pre_unapprove function - extend if the contenttype needs to do anything
	*
	* @param	array	list of unapproved attachment ids that belong to a specific
	*/
	public function pre_unapprove($list)
	{
		return true;
	}

	/**
	* post_unapprove function - extend if the contenttype needs to do anything
	*
	* @param	array	list of unapproved attachment ids that belong to a specific
	*/
	public function post_unapprove()
	{
		return true;
	}

	/**
	* pre_approve function - extend if the contenttype needs to do anything
	*
	* @param	array	list of approved attachment ids that belong to a specific
	*/
	public function pre_approve($list)
	{
		return true;
	}

	/**
	* post_approve function - extend if the contenttype needs to do anything
	*
	* @param	array	list of approved attachment ids that belong to a specific
	*/
	public function post_approve()
	{
		return true;
	}
}

class vB_Attachment_Upload_Displaybit_Library
{
	/**
	* Singleton emulation
	*
	*/
	private static $instance = null;

	/**
	* Select library
	*
	* @return	object
	*/
	public static function &fetch_library(&$registry, $contenttypeid)
	{
		if (self::$instance)
		{
			return self::$instance;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();

		if (!($contenttypeid = $types->getContentTypeID($contenttypeid)))
		{
			return false;
		}

		$package = $types->getContentTypePackage($contenttypeid);
		$class = $types->getContentTypeClass($contenttypeid);

		$selectclass = "vB_Attachment_Upload_Displaybit_{$package}_{$class}";

		$path = DIR . '/packages/' . strtolower($package) . '/attach/' . strtolower($class) . '.php';
		if (file_exists($path))
		{
			include_once($path);
			if (class_exists($selectclass))
			{
				self::$instance = new $selectclass($registry, $contenttypeid);
				return self::$instance;
			}
		}
		return false;
	}
}

/**
* Abstract class for updating the display of the calling window during uploads
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
abstract class vB_Attachment_Upload_Displaybit
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	*/
	public function __construct(&$registry)
	{
		$this->registry =& $registry;
	}

	/**
	*	Parses the appropriate template for contenttype that is to be updated on the calling window during an upload
	*
	* @param	array	Attachment information
	* @param	array	Values array pertaining to contenttype
	* @param	boolean	Disable template comments
	*
	* @return	string
	*/
	abstract public function process_display_template($attach, $values = array(), $disablecomment = false);

	/**
	*
	*
	* @param	array		Attachment information
	* @param	boolean	Add window.opener to call
	*
	* @return	string
	*/
	public function construct_attachment_add_js($attachment, $addopener = false)
	{
		$attachment['extension'] = strtolower(file_extension($attachment['filename']));
		$attachment['filename']  = htmlspecialchars_uni($attachment['filename']);

		return ($addopener ? "window.opener." : "") . "vB_Attachments.add($attachment[attachmentid], \"" . addslashes(str_replace(array("\r", "\n"), '', $attachment['html'])) . "\", '" . addslashes_js($attachment['filename']) . "', '" . addslashes_js($attachment['filesize']) . "', '".vB_Template_Runtime::fetchStyleVar('imgdir_attach')."/$attachment[extension].gif');\n";
	}
}

/**
* Class for common attachment tasks that are content agnostic
*
* @package 		vBulletin
* @version		$Revision: 44610 $
* @date 		$Date: 2011-06-16 14:41:03 -0700 (Thu, 16 Jun 2011) $
*
*/
class vB_Attach_Display_Content
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Contenttype id
	*
	* @var	integer
	*/
	protected $contenttypeid = 0;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	string			Contenttype
	*/
	public function __construct(&$registry, $contenttype)
	{
		$this->registry =& $registry;

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		$this->contenttypeid = vB_Types::instance()->getContentTypeID($contenttype);
	}

	/**
	* Fetches the contenttypeid
	*
	*	@return	integer
	*/
	public function fetch_contenttypeid()
	{
		return $this->contenttypeid;
	}

	/**
	* Fetches a list of attachments for display on edit or preview
	*
	* @param	string	Posthash of this edit/add
	* @param	integer	Start time of this edit/add
	* @param	array		Combined existing and new attachments belonging to this content
	* @param	integer id of attachments owner
	* @param	string	Content specific values that need to be passed on to the attachment form
	* @param	string	$editorid of the message editor on the page that launched the asset manager
	* @param	integer	Number of fetched attachments, set by this function
	* @param	mixed		Who can view an attachment with no contentid (in progress), other than vbulletin->userinfo
	*
	* @return	string
	*/
	public function fetch_edit_attachments(&$posthash, &$poststarttime, &$postattach, $contentid, $values, $editorid, &$attachcount, $users = null)
	{
		global $show;

		require_once(DIR . '/includes/functions_file.php');
		// $maxattachsize is redundant, never used
		$attachcount = 0;
		$attachment_js = '';

		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $this->registry->userinfo['userid'] . $this->registry->userinfo['salt']);
		}

		if (empty($postattach))
		{
			$postattach = $this->fetch_postattach($posthash, $contentid, $users);
		}

		if (!empty($postattach))
		{
			$attachdisplaylib =& vB_Attachment_Upload_Displaybit_Library::fetch_library($this->registry, $this->contenttypeid);
			foreach($postattach AS $attachmentid => $attach)
			{
				$attachcount++;
				$attach['html'] = $attachdisplaylib->process_display_template($attach, $values);
				$attachments .= $attach['html'];
				$show['attachmentlist'] = true;
				$attachment_js .= $attachdisplaylib->construct_attachment_add_js($attach);
			}
		}

		$templater = vB_Template::create('newpost_attachment');
			$templater->register('attachments', $attachments);
			$templater->register('attachment_js', $attachment_js);
			$templater->register('editorid', $editorid);
			$templater->register('posthash', $posthash);
			$templater->register('contentid', $contentid);
			$templater->register('poststarttime', $poststarttime);
			$templater->register('attachuserid', $this->registry->userinfo['userid']);
			$templater->register('contenttypeid', $this->contenttypeid);
			$templater->register('values', $values);
		return $templater->render();
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	string	Posthash of this edit/add
	* @param	integer id of attachments owner
	* @param	mixed		Who can view an attachment with no contentid (in progress), other than vbulletin->userinfo
	*
	* @return	array
	*/
	public function fetch_postattach($posthash = 0, $contentid = 0, $users = null)
	{
		// if we were passed no information, simply return an empty array
		// to avoid a nasty database error
		if (empty($posthash) AND empty($contentid))
		{
			return array();
		}

		if (!$users)
		{
			$users = array($this->registry->userinfo['userid']);
		}
		else
		{
			if (is_array($users))
			{
				$temp = array_map("intval", $users);
				$users = $temp;
			}
			else if ($userid = intval($users))
			{
				$users = array($userid);
			}
			$users[] = $this->registry->userinfo['userid'];
		}

		$union = array();

		if ($contentid)
		{
			if (is_array($contentid))
			{
				$sql = "a.contentid IN (" . implode(",", $contentid) . ")";
			}
			else
			{
				$sql = "a.contentid = " . intval($contentid);
			}
			$union[] = "
				SELECT
					fd.thumbnail_dateline, fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.thumbnail_filesize,
					a.dateline, a.state, a.attachmentid, a.counter, a.contentid, a.filename, a.userid, a.settings, a.displayorder,
					at.contenttypes
				FROM " . TABLE_PREFIX . "attachment AS a
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
				LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)
				WHERE
					$sql
						AND
					a.contenttypeid = " . $this->contenttypeid . "
			";
		}

		if ($posthash)
		{
			$union[] = "
				SELECT
					fd.thumbnail_dateline, fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.thumbnail_filesize,
					a.dateline, a.state, a.attachmentid, a.counter, a.contentid, a.filename, a.userid, a.settings, a.displayorder,
					at.contenttypes
				FROM " . TABLE_PREFIX . "attachment AS a
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
				LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)
				WHERE
					a.posthash = '" . $this->registry->db->escape_string($posthash) . "'
						AND
					a.userid IN (" . implode(',', $users) . ")
						AND
					a.contenttypeid = " . $this->contenttypeid . "
			";
		}

		if (count($union) > 1)
		{
			$unionsql = array(
				"(" . implode(") UNION ALL (", $union) . ")",
				"ORDER BY displayorder",
			);
		}
		else
		{
			$unionsql = array(
				$union[0],
				"ORDER BY a.contentid, a.displayorder",
			);
		}

		$postattach = array();
		$attachments = $this->registry->db->query_read_slave(implode("\r\n", $unionsql));
		while ($attachment = $this->registry->db->fetch_array($attachments))
		{
			$content = @unserialize($attachment['contenttypes']);
			$attachment['newwindow'] = $content[$this->contenttypeid]['n'];
			if (is_array($contentid))
			{
				$postattach["$attachment[contentid]"]["$attachment[attachmentid]"] = $attachment;
			}
			else
			{
				$postattach["$attachment[attachmentid]"] = $attachment;
			}
		}

		return $postattach;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	array		Information about the content that owns these attachments
	* @param	array		List of attachments belonging to the specifed post
	* @param	boolean Display download count
	* @param	boolean View has permissions to download attachments
	* @param	boolean Viewer has permission to get attachments
	* @param	boolean Viewer has permission to set thumbnails
	*
	* @return	void
	*/
	function process_attachments(&$post, &$attachments, $hidecounter = false, $canmod = false, $canget = true, $canseethumb = true, $linkonly = false)
	{
		global $show, $vbphrase;

		if (!empty($attachments))
		{
			$show['modattachmentlink'] = ($canmod OR $post['userid'] == $this->registry->userinfo['userid']);
			$show['attachments'] = true;
			$show['moderatedattachment'] = $show['thumbnailattachment'] = $show['otherattachment'] = false;
			$show['imageattachment'] = $show['imageattachmentlink'] = false;

			$attachcount = sizeof($attachments);
			$thumbcount = 0;

			if (!$this->registry->options['viewattachedimages'])
			{
				$showimagesprev = $this->registry->userinfo['showimages'];
				$this->registry->userinfo['showimages'] = false;
			}

			foreach ($attachments AS $attachmentid => $attachment)
			{
				if ($canget AND $canseethumb AND $attachment['thumbnail_filesize'] == $attachment['filesize'])
				{
					// This is an image that is already thumbnail sized..
					$attachment['hasthumbnail'] = 0;
					$attachment['forceimage'] = ($this->registry->options['viewattachedimages'] ? $this->registry->userinfo['showimages'] : 0);
				}
				else if (!$canseethumb)
				{
					$attachment['hasthumbnail'] = 0;
				}

				$show['newwindow'] = $attachment['newwindow'];

				$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
				$attachment['attachmentextension'] = strtolower(file_extension($attachment['filename']));
				$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

				if (vB_Template_Runtime::fetchStyleVar('dirmark'))
				{
					$attachment['filename'] .= vB_Template_Runtime::fetchStyleVar('dirmark');
				}

				($hook = vBulletinHook::fetch_hook('postbit_attachment')) ? eval($hook) : false;

				if ($attachment['state'] == 'visible')
				{
					if ($hidecounter)
					{
						$attachment['counter'] = $vbphrase['n_a'];
						$show['views'] = false;
					}
					else
					{
						$show['views'] = true;
					}

					$lightbox_extensions = array('gif', 'jpg', 'jpeg', 'jpe', 'png', 'bmp');
					$ext = $linkonly ? null : $attachment['attachmentextension'];

					$attachmenturl = create_full_url("attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=$attachment[attachmentid]&d=$attachment[dateline]");
					$imageurl = create_full_url("attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=$attachment[attachmentid]&stc=1&d=$attachment[dateline]");
					$thumburl = create_full_url("attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=$attachment[attachmentid]&stc=1&thumb=1&d=$attachment[thumbnail_dateline]");
					switch($ext)
					{
						case 'gif':
						case 'jpg':
						case 'jpeg':
						case 'jpe':
						case 'png':
						case 'bmp':
						case 'tiff':
						case 'tif':
						case 'psd':
						case 'pdf':
							if (!$this->registry->userinfo['showimages'])
							{
								// Special case for PDF - don't list it as an 'image'
								if ($attachment['attachmentextension'] == 'pdf')
								{
									$templater = vB_Template::create('postbit_attachment');
										$templater->register('attachment', $attachment);
										$templater->register('url', $attachmenturl);
									$post['otherattachments'] .= $templater->render();
									$show['otherattachment'] = true;
								}
								else
								{
									$templater = vB_Template::create('postbit_attachment');
										$templater->register('attachment', $attachment);
										$templater->register('url', $attachmenturl);
									$post['imageattachmentlinks'] .= $templater->render();
									$show['imageattachmentlink'] = true;
								}
							}
							else if ($this->registry->options['viewattachedimages'] == 1 OR ($this->registry->options['viewattachedimages'] == 2 AND $attachcount > 1))
							{
								if ($attachment['hasthumbnail'] OR (!$canget AND !in_array($attachment['attachmentextension'], array('tiff', 'tif', 'psd', 'pdf'))))
								{
									$thumbcount++;
									if ($this->registry->options['attachrow'] AND $thumbcount >= $this->registry->options['attachrow'])
									{
										$thumbcount = 0;
										$show['br'] = true;
									}
									else
									{
										$show['br'] = false;
									}
									$show['cangetattachment'] = ($canget AND in_array($attachment['attachmentextension'], $lightbox_extensions));
									$templater = vB_Template::create('postbit_attachmentthumbnail');
										$templater->register('attachment', $attachment);
										$templater->register('url', $attachmenturl);
										$templater->register('pictureurl', $thumburl);
									$post['thumbnailattachments'] .= $templater->render();
									$show['thumbnailattachment'] = true;
								}
								else if (!in_array($attachment['attachmentextension'], array('tiff', 'tif', 'psd', 'pdf')) AND $attachment['forceimage'])
								{
									$templater = vB_Template::create('postbit_attachmentimage');
										$templater->register('attachment', $attachment);
										$templater->register('url', $attachmenturl);
										$templater->register('pictureurl', $imageurl);
									$post['imageattachments'] .= $templater->render();
									$show['imageattachment'] = true;
								}
								else
								{
									// Special case for PDF - don't list it as an 'image'
									if ($attachment['attachmentextension'] == 'pdf')
									{
										$templater = vB_Template::create('postbit_attachment');
											$templater->register('attachment', $attachment);
											$templater->register('url', $attachmenturl);
										$post['otherattachments'] .= $templater->render();
										$show['otherattachment'] = true;
									}
									else
									{
										$templater = vB_Template::create('postbit_attachment');
											$templater->register('attachment', $attachment);
											$templater->register('url', $attachmenturl);
										$post['imageattachmentlinks'] .= $templater->render();
										$show['imageattachmentlink'] = true;
									}
								}
							}
							else if (!in_array($attachment['attachmentextension'], array('tiff', 'tif', 'psd', 'pdf')) AND ($this->registry->options['viewattachedimages'] == 3 OR ($this->registry->options['viewattachedimages'] == 2 AND $attachcount == 1)))
							{
								$templater = vB_Template::create('postbit_attachmentimage');
									$templater->register('attachment', $attachment);
									$templater->register('url', $attachmenturl);
									$templater->register('pictureurl', $imageurl);
								$post['imageattachments'] .= $templater->render();
								$show['imageattachment'] = true;
							}
							else
							{
								$templater = vB_Template::create('postbit_attachment');
									$templater->register('attachment', $attachment);
									$templater->register('url', $attachmenturl);
								$post['imageattachmentlinks'] .= $templater->render();
								$show['imageattachmentlink'] = true;
							}
							break;
						default:
							$templater = vB_Template::create('postbit_attachment');
								$templater->register('attachment', $attachment);
								$templater->register('url', $attachmenturl);
							$post['otherattachments'] .= $templater->render();
							$show['otherattachment'] = true;
					}
				}
				else
				{
					$templater = vB_Template::create('postbit_attachment');
						$templater->register('attachment', $attachment);
						$templater->register('url', $attachmenturl);
					$post['moderatedattachments'] .= $templater->render();
					$show['moderatedattachment'] = true;
				}
			}
			if (!$this->registry->options['viewattachedimages'])
			{
				$this->registry->userinfo['showimages'] = $showimagesprev;
			}
		}
		else
		{
			$show['attachments'] = false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44610 $
|| ####################################################################
\*======================================================================*/