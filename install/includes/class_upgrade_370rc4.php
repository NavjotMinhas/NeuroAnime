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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_370rc4 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370rc4';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Release Candidate 4';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Release Candidate 3';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/**
	* Step #1
	* special case: memberlist, modifyattachments, reputationbit
	* add missing sessionhash. Let later query add security tokens
	* special case for headinclude: the JS variable
	*
	*/
	function step_1()
	{
		require_once(DIR . '/includes/adminfunctions_template.php');

		$skip = true;
		$templates = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "template
			WHERE styleid > 0
				AND title IN ('memberlist', 'modifyattachments', 'reputationbit')
			ORDER BY title
		");

		while ($template = $this->db->fetch_array($templates))
		{
			if (strpos($template['template_un'], '$bbuserinfo[securitytoken]') !== false)
			{
				continue;
			}

			switch ($template['title'])
			{
				case 'memberlist':
					$find_text = '<input type="hidden" name="do" value="deleteusergroups" />';
					break;

				case 'modifyattachments':
					$find_text = '<input type="hidden" name="do" value="deleteattachments" />';
					break;

				case 'reputationbit':
					$find_text = '<input type="hidden" name="do" value="addreputation" />';
					break;

				default:
					$find_text = '';
			}

			if (!$find_text)
			{
				continue;
			}

			$template['template_un'] = str_replace(
				$find_text,
				$find_text . "\n\t" . '<input type="hidden" name="s" value="$session[sessionhash]" />',
				$template['template_un']
			);

			$compiled_template = compile_template($template['template_un']);

			$skip = false;
			$this->db->query_write(
				"UPDATE " . TABLE_PREFIX . "template SET
					template = '" . $this->db->escape_string($compiled_template) . "',
					template_un = '" . $this->db->escape_string($template['template_un']) . "'
				WHERE templateid = $template[templateid]"
			);

			$this->show_message(
				sprintf($this->phrase['vbphrase']['apply_critical_template_change_to_x'], $template['title'], $template['styleid'])
			);
		}

		if ($skip)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #2 - add the security token to all forms
	*
	*/
	function step_2()
	{
		$session_var = 'value="$session[sessionhash]"';
		$dbsession_var = 'value="$session[dbsessionhash]"';
		$get_forms_only = array(
			'calendarjump',
			'FAQ',
			'forumjump',
			'smiliepopup'
		);

		$templates = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "template
			WHERE styleid > 0
				AND (
					template_un LIKE '%" . $this->db->escape_string_like($session_var) . "%'
					OR
					template_un LIKE '%" . $this->db->escape_string_like($dbsession_var) . "%'
				)
			ORDER BY title ASC
		");
		$templates_updated = 0;

		while ($template = $this->db->fetch_array($templates))
		{
			if (strpos($template['template_un'], '$bbuserinfo[securitytoken]') !== false)
			{
				continue;
			}

			if (in_array($template['title'], $get_forms_only))
			{
				continue;
			}

			$new_template = preg_replace(
				'#((' . preg_quote($session_var, '#') . '|' . preg_quote($dbsession_var, '#') . ').*>)#siU',
				"\\1\n\t" . '<input type="hidden" name="securitytoken" value="$bbuserinfo[securitytoken]" />',
				$template['template_un']
			);

			require_once(DIR . '/includes/adminfunctions_template.php');
			$compiled_template = compile_template($new_template);

			$this->db->query_write("
				UPDATE " . TABLE_PREFIX . "template SET
					template = '" . $this->db->escape_string($compiled_template) . "',
					template_un = '" . $this->db->escape_string($new_template) . "'
				WHERE templateid = $template[templateid]
			");

			$templates_updated++;
		}

		$this->show_message(
			sprintf($this->phrase['version']['370rc4']['token_added_x_templates'], $templates_updated)
		);
	}

	/**
	* Step #3 - special case for headinclude: the JS variable
	*
	*/
	function step_3()
	{
		require_once(DIR . '/includes/adminfunctions_template.php');
		$skip = true;
		$templates = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "template
			WHERE styleid > 0
				AND title IN ('headinclude')
		");
		while ($template = $this->db->fetch_array($templates))
		{
			if (strpos($template['template_un'], '$bbuserinfo[securitytoken]') !== false)
			{
				continue;
			}

			$template['template_un'] = str_replace(
				'var SESSIONURL = "$session[sessionurl_js]";',
				'var SESSIONURL = "$session[sessionurl_js]";' . "\n" . 'var SECURITYTOKEN = "$bbuserinfo[securitytoken]";',
				$template['template_un']
			);

			$template['template_un'] = str_replace(
				'var SESSIONURL = "$session[sessionurl]";',
				'var SESSIONURL = "$session[sessionurl]";' . "\n" . 'var SECURITYTOKEN = "$bbuserinfo[securitytoken]";',
				$template['template_un']
			);

			$compiled_template = compile_template($template['template_un']);

			$skip = false;
			$this->run_query(
				sprintf($this->phrase['vbphrase']['apply_critical_template_change_to_x'], $template['title'], $template['styleid']),
				"UPDATE " . TABLE_PREFIX . "template SET
					template = '" . $this->db->escape_string($compiled_template) . "',
					template_un = '" . $this->db->escape_string($template['template_un']) . "'
				WHERE templateid = $template[templateid]
			");
		}

		if ($skip)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #4 - special case for who's online: a form that should be get
	*
	*/
	function step_4()
	{
		require_once(DIR . '/includes/adminfunctions_template.php');
		$skip = true;
		$templates = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "template
			WHERE styleid > 0
				AND title IN ('WHOSONLINE')
		");
		while ($template = $this->db->fetch_array($templates))
		{
			if (strpos($template['template_un'], '<form action="online.php" method="get">') !== false)
			{
				continue;
			}

			$template['template_un'] = str_replace(
				'<form action="online.php" method="post">',
				'<form action="online.php" method="get">',
				$template['template_un']
			);

			$compiled_template = compile_template($template['template_un']);
			$skip = false;
			$this->run_query(
				sprintf($this->phrase['vbphrase']['apply_critical_template_change_to_x'], $template['title'], $template['styleid']),
				"UPDATE " . TABLE_PREFIX . "template SET
					template = '" . $this->db->escape_string($compiled_template) . "',
					template_un = '" . $this->db->escape_string($template['template_un']) . "'
				WHERE templateid = $template[templateid]
			");
		}

		if ($skip)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "setting CHANGE datatype datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username', 'integer', 'posint') NOT NULL DEFAULT 'free'"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
