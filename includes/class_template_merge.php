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

require_once(DIR . '/includes/adminfunctions_template.php');
require_once(DIR . '/includes/class_merge.php');

@set_time_limit(0);

/**
* Class to act as a wrapper for mass (data-based) template merges. Its primary
* usage is during a template XML import.
*
* @package vBulletin
*/
class vB_Template_Merge
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	protected $registry;

	/**
	* Number of templates processed in this invokation. This includes every
	* template returned from the data object, even those that it decides to skip.
	*
	* @var	int
	*/
	protected $processed_count = 0;

	/**
	* Microtime when the merge was started. Allows for time-based loop breaking.
	*
	* @var	int
	*/
	protected $start_time = 0;

	/**
	* Time limit (in seconds) to limit a merge run to. Use this to paginate
	* the merging process.
	*
	* @var	float
	*/
	public $time_limit = 0;

	/**
	* Number of records to offset from the first match. Use this to start
	* the second (or later) page of a merge set.
	*
	* @var	int
	*/
	public $start_offset = 0;

	/**
	* Controls whether text should be output during the merge process.
	*
	* @var	bool
	*/
	public $show_output = true;

	/**
	* Sets the version written to the template when a successful merge happens.
	*
	* @var	string
	*/
	public $merge_version = '';

	/**
	* Constructor.
	*
	* @param	vB_Registry	Main registry object
	*/
	public function __construct(vB_Registry $registry)
	{
		$this->registry = $registry;
		$this->merge_version = $registry->options['templateversion'];
	}

	/**
	* Actively merge the set of templates matched by the data object.
	* May be paginated by setting members correctly. Does not error;
	* return value indicates whether more data needs to be processed.
	*
	* @param	vB_Template_Merge_Data	Potential merge candidates
	* @param	array										Output data, in array form
	*
	* @return	bool					True when all templates are processed, false when more remain
	*/
	public function merge_templates(vB_Template_Merge_Data $data, &$output)
	{
		$candidates = $data->fetch_merge_candidates();

		$this->merge_start();

		$this->processed_count = 0;

		while ($template = $this->registry->db->fetch_array($candidates))
		{
			$this->processed_count++;

			if (!$data->can_merge_template($template))
			{
				continue;
			}

			$merge = new vB_Text_Merge_Threeway(
				$template['oldmastertext'],
				$template['newmastertext'],
				$template['customtext'],
				$this->show_output
			);
			$merged_text = $merge->get_merged();

			if ($merged_text)
			{
				if ($result = $this->merge_success($merged_text, $template))
				{
					$output[] = $result;
				}
			}
			else
			{
				if ($result = $this->merge_conflict($template))
				{
					$output[] = $result;
				}
			}

			if ($this->break_merge_early())
			{
				return false;
			}
		}

		return true;
	}

	/**
	* Merge setup method. By default, simply starts the timer.
	*/
	protected function merge_start()
	{
		$this->start_time = microtime(true);
	}

	/**
	* Determines whether the merge process should be broken early.
	*
	* @return	bool	True to break early, false to continue
	*/
	protected function break_merge_early()
	{
		if ($this->time_limit)
		{
			return ((microtime(true) - $this->start_time) > $this->time_limit);
		}

		return false;
	}

	/**
	* Called when a merge succeeds (no conflicts).
	*
	* @param	string	Final merged text
	* @param	array	Array of template info. Record returned by data method.
	*/
	protected function merge_success($merged_text, $template_info)
	{
		global $vbphrase;
		$db = $this->registry->db;

		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "templatehistory
				(styleid, title, template, dateline, username, version)
			VALUES
				($template_info[styleid],
				'" . $db->escape_string($template_info['title']) . "',
				'" . $db->escape_string($template_info['customtext']) . "',
				$template_info[dateline],
				'" . $db->escape_string($template_info['username']) . "',
				'" . $db->escape_string($template_info['version']) . "')
		");
		$savedtemplateid = $db->insert_id();

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "templatemerge
				(templateid, template, version, savedtemplateid)
			VALUES
				($template_info[templateid],
				'" . $db->escape_string($template_info['oldmastertext']) . "',
				'" . $db->escape_string($template_info['oldmasterversion']) . "',
				" . intval($savedtemplateid) . "
				)
		");

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "template SET
				styleid = $template_info[styleid],
				templatetype = 'template',
				title = '" . $db->escape_string($template_info['title']) . "',
				template = '" . $db->escape_string(compile_template($merged_text)) . "',
				template_un = '" . $db->escape_string($merged_text) . "',
				dateline = " . TIMENOW . ",
				username = '" . $db->escape_string(!empty($vbphrase['system']) ? $vbphrase['system'] : 'System') . "',
				version = '" . $db->escape_string($this->merge_version) . "',
				product = '" . $db->escape_string($template_info['product']) . "',
				mergestatus = 'merged'
			WHERE
				templateid = " . intval($template_info["templateid"])
		);

		if ($this->show_output)
		{
			return $this->output_merge_success($merged_text, $template_info);
		}
	}

	/**
	* If output is enabled, called to output info about a successful merge.
	*
	* @param	string	Final merged text
	* @param	array	Array of template info. Record returned by data method.
	*/
	protected function output_merge_success($merged_text, $template_info)
	{
		global $vbphrase;

		if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
		{
			echo '<div>' . construct_phrase($vbphrase['template_merged_x_y'], $template_info['title'], $template_info['styleid']) . '</div>';
			// Send some output to browser. See bug #34585
			vbflush();
		}
		else
		{
			return construct_phrase($vbphrase['template_merged_x_y'], $template_info['title'], $template_info['styleid']);
		}
	}

	/**
	* Called when a merge fails (conflicts).
	*
	* @param	array	Array of template info. Record returned by data method.
	*/
	protected function merge_conflict($template_info)
	{
		$db = $this->registry->db;

		$db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "templatemerge
				(templateid, template, version)
			VALUES
				($template_info[templateid],
				'" . $db->escape_string($template_info['oldmastertext']) . "',
				'" . $db->escape_string($template_info['oldmasterversion']) . "')
		");

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "template SET
				mergestatus = 'conflicted'
			WHERE templateid = $template_info[templateid]
		");

		if ($this->show_output)
		{
			return $this->output_merge_conflict($template_info);
		}
	}

	/**
	* If output is enabled, called to output info about a failed merge.
	*
	* @param	array	Array of template info. Record returned by data method.
	*/
	protected function output_merge_conflict($template_info)
	{
		global $vbphrase;

		if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
		{
			echo '<div>' . construct_phrase($vbphrase['template_conflict_x_y'], $template_info['title'], $template_info['styleid']) . '</div>';
			// Send some output to browser. See bug #34585
			vbflush();
		}
		else
		{
			return construct_phrase($vbphrase['template_conflict_x_y'], $template_info['title'], $template_info['styleid']);
		}
	}

	/**
	* Returns the number of templates processed in this execution.
	*
	* @return	int
	*/
	function fetch_processed_count()
	{
		return $this->processed_count;
	}
}

/**
* Class that separates the data retrival aspect from merging for easier variation
* and reduced dependencies.
*
* @package	vBulletin
*/
class vB_Template_Merge_Data
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	protected $registry;

	/**
	* Array of extra conditions to add to the data fetch
	*
	* @var	array
	*/
	protected $conditions = array();

	/**
	* Number of records to offset from the first match. Use this to start
	* the second (or later) page of a merge set.
	*
	* @var	int
	*/
	public $start_offset = 0;

	/**
	* Constructor.
	*
	* @param	vB_Registry	Main registry object
	*/
	public function __construct(vB_Registry $registry)
	{
		$this->registry = $registry;
	}

	/**
	* Adds an additional condition to the data fetch query.
	* The condition is just raw SQL put together with AND's.
	*
	* @param	string	Condition text
	*/
	public function add_condition($condition)
	{
		$this->conditions[] = $condition;
	}

	/**
	* Fetches the result set containing all (remaining) merge candidates.
	* Can be offset from the start via the start_offset member. Needs to return
	* information about the custom, origin (old), and new versions of a particular
	* template.
	*
	* @return	resource	DB result object
	*/
	public function fetch_merge_candidates()
	{
		// see top of adminfunctions_templates.php
		global $_query_special_templates;

		// there are some "templates" that aren't normally editable -- don't try to merge them
		$title_skip = ($_query_special_templates ?
			"AND tcustom.title NOT IN ('" . implode("','", $_query_special_templates) . "')" :
			''
		);

		$extra_conditions = ($this->conditions ?
			"AND (" . implode(') AND (', $this->conditions) . ")" :
			''
		);

		return $this->registry->db->query_read("
			SELECT tcustom.*,
				tcustom.template_un AS customtext,
				tnewmaster.version AS newmasterversion, tnewmaster.template_un AS newmastertext,
				toldmaster.version AS oldmasterversion, toldmaster.template_un AS oldmastertext
			FROM " . TABLE_PREFIX . "template AS tcustom
			INNER JOIN " . TABLE_PREFIX . "template AS tnewmaster ON
				(tcustom.title = tnewmaster.title AND tnewmaster.styleid = -1 AND tnewmaster.templatetype = 'template')
			INNER JOIN " . TABLE_PREFIX . "template AS toldmaster ON
				(tcustom.title = toldmaster.title AND toldmaster.styleid = -10 AND toldmaster.templatetype = 'template'
				AND toldmaster.version <> tnewmaster.version)
			WHERE tcustom.styleid > 0
				AND tcustom.templatetype = 'template'
				$title_skip
				$extra_conditions
			ORDER BY tcustom.styleid, tcustom.title
			LIMIT " . intval($this->start_offset) . ", 999999
		");
	}

	/**
	* Determines whether a merge should be attempted on a template.
	*
	* @param	array	Array of template info. Record returned by data method.
	*
	* @return	bool	True if a merge should be attempted.
	*/
	public function can_merge_template($template_info)
	{
		if ($template_info['mergestatus'] == 'conflicted')
		{
			return false;
		}

		return is_newer_version($template_info['newmasterversion'], $template_info['oldmasterversion']);
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27819 $
|| ####################################################################
\*======================================================================*/