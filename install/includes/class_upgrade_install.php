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

if (VB_AREA != 'Install' AND !isset($GLOBALS['vbulletin']->db))
{
	exit;
}

class vB_Upgrade_install extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'install';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'install';

	/*Properties====================================================================*/


	/**
	* Step #1
	*
	*/
	function step_1($data = null)
	{
		$havetables = false;
		$vbexists = false;

		$tables = $this->db->query("SHOW TABLES");
		while ($table = $this->db->fetch_array($tables))
		{
			$havetables = true;
			break;
		}

		if ($havetables)
		{
			// see if there's a user table already
			$this->db->hide_errors();
			$this->db->query_write("SHOW FIELDS FROM " . trim($this->registry->config['Database']['tableprefix']) . "user");
			$this->db->show_errors();
			$vbexists = $this->fetch_vbexists();
		}

		if ($data['htmlsubmit'] AND $data['response'] == 'yes')
		{
			$result = $this->db->query_write("SHOW TABLES");
			while ($currow = $this->db->fetch_array($result, DBARRAY_NUM))
			{
				if (in_array($currow[0], $data['htmldata']))
				{
					$this->db->query_write("DROP TABLE IF EXISTS $currow[0]");
					$this->show_message(sprintf($this->phrase['vbphrase']['remove_table'], $currow[0]));
				}
			}

			$vbexists = $this->fetch_vbexists();
			if (!$vbexists)
			{
				$havetables = false;
			}
			unset($data['response']);
		}

		if ($vbexists)
		{
			if ($data['response'])
			{
				if ($data['response'] == 'no')
				{
					$this->add_error($this->phrase['install']['new_install_cant_continue'], self::PHP_TRIGGER_ERROR, true);
					return;
				}
				else	// Data response = yes... fall down to schema load below
				{

				}
			}
			else
			{
				return array(
					'prompt'  => $this->phrase['install']['connect_success_vb_exists'],
					'confirm' => true,
					'ok'      => $this->phrase['vbphrase']['yes'],
					'cancel'  => $this->phrase['vbphrase']['no'],
				);
			}
		}

		if ($havetables)
		{
			if ($data['response'])
			{
				if ($data['response'] == 'no')
				{
					// fall down to below...
				}
				else
				{
					$schema =& $this->load_schema();

					$tables = array();
					$tables_result = $this->db->query_read("SHOW TABLES");
					while ($table = $this->db->fetch_array($tables_result, DBARRAY_NUM))
					{
						$tables["$table[0]"] = $table[0];
					}

					$default_tables = array_keys($schema['CREATE']['query']);

					if ($this->install_suite())
					{
						$default_tables = array_merge($default_tables, $this->fetch_product_tables('vbblog'));
						$default_tables = array_merge($default_tables, $this->fetch_product_tables('vbcms'));
					}

					$default_tables = array_merge($default_tables, $this->fetch_product_tables('skimlinks'));

					$html = '<div class="advancedconfirmbody">';
					$html .= $this->phrase['install']['delete_tables_instructions'];
					$html .= "<p><label><input type=\"checkbox\" id=\"allbox\" onclick=\"js_check_all(this.form)\" />{$this->phrase['install']['select_deselect_all_tables']}</label></p>";

					$options = '';
					foreach ($tables AS $table)
					{
						if (substr($table, 0, strlen(TABLE_PREFIX)) == TABLE_PREFIX)
						{
							$table_basename = substr($table, strlen(TABLE_PREFIX));

							if (in_array($table_basename, $default_tables))
							{
								$checked = ' checked="checked"';
								$class = 'alt2';
							}
							else
							{
								$checked = '';
								$class = 'alt1';
							}

							$html .= "<label class=\"$class\" style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . "; display:block; width:250px; margin:0px 6px 6px 0px\"><input type=\"checkbox\" name=\"htmldata[]\" value=\"$table\"$checked />" . TABLE_PREFIX . "<strong>$table_basename</strong></label>\n";
						}
						else
						{
							$html .= "<label class=\"alt1\" style=\"float:" . vB_Template_Runtime::fetchStyleVar('left') . "; display:block; width:250px; margin:0px 6px 6px 0px\"><input type=\"checkbox\" name=\"htmldata[]\" value=\"$table\" /><strong>$table</strong></label>\n";
						}
					}
					$html .= '</div>';

					return array(
						'html'  => $html,
						'width' => '640px',
						'ok'    => $this->phrase['install']['delete_selected_tables'],
						'title' => $this->phrase['install']['reset_database'],
					);
				}
			}
			else
			{
				return array(
					'prompt'  => $this->phrase['install']['connect_success_tables_exist'],
					'confirm' => true,
					'ok'      => $this->phrase['vbphrase']['yes'],
					'cancel'  => $this->phrase['vbphrase']['no'],
				);
			}
		}

		$this->show_message($this->phrase['install']['connect_success']);
	}

	/**
	* Step #2 - Create Tables
	*
	*/
	function step_2()
	{
		$schema =& $this->load_schema();
		$this->exec_queries($schema['CREATE']['query'], $schema['CREATE']['explain']);
	}

	/**
	* Step #3 - Alter Tables
	*
	*/
	function step_3()
	{
		$schema =& $this->load_schema();
		$this->exec_queries($schema['ALTER']['query'], $schema['ALTER']['explain']);
	}

	/**
	* Step #4 - Insert Data
	*
	*/
	function step_4()
	{
		$schema =& $this->load_schema();
		$this->exec_queries($schema['INSERT']['query'], $schema['INSERT']['explain']);

		//insert default notice for guests
		require_once(DIR . '/includes/adminfunctions_notice.php');

		$criteria = array();
		$criteria['in_usergroup_x'] = array('active' => 1, 'condition1' => 1);

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		try
		{
			save_notice(null, 'default_guest_message', $this->phrase['install']['default_guest_message'], 10, 1, 1, 1, $criteria, 'System', $this->maxversion);
		}
		catch(vB_Exception_AdminStopMessage $e)
		{
			$message = $this->stop_exception($e);
			$this->add_error($message, self::PHP_TRIGGER_ERROR, true);
		}
	}

	/**
	* Step #5 - Ask Options...
	*
	*/
	function step_5($data = null)
	{
		require_once(DIR . '/includes/adminfunctions_options.php');

		if ($data['response'])
		{
			$this->registry->options = array(
				'bbtitle'        => $data['htmldata']['bbtitle'],
				'hometitle'      => $data['htmldata']['hometitle'],
				'bburl'          => $data['htmldata']['bburl'],
				'homeurl'        => $data['htmldata']['homeurl'],
				'webmasteremail' => $data['htmldata']['webmasteremail'],
			);

			if (!($xml = file_read(DIR . '/install/vbulletin-settings.xml')))
			{
				$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-settings.xml'), self::PHP_TRIGGER_ERROR, true);
				return;
			}
			// Enable fulltext search for new installs
			$this->registry->options['fulltextsearch'] = 1;

			$gdinfo = fetch_gdinfo();
			if ($gdinfo['version'] >= 2)
			{
				if ($gdinfo['freetype'] == 'freetype')
				{
					$this->registry->options['regimagetype'] = 'GDttf';
				}
			}
			else
			{
				$this->registry->options['hv_type'] = '0';
				$this->registry->options['regimagetype'] = '';
			}

			if (empty($gdinfo['version']))
			{
				$this->registry->options['imagetype'] = 0;
			}

			//if ($this->install_suite())
			//{
				// Hooks are enabled now because of Skimlinks
				$this->registry->options['enablehooks'] = 1;
			//}

			// Need to set default language id!
			$languageinfo = $this->db->query_first("
				SELECT languageid
				FROM " . TABLE_PREFIX . "language
			");
			$this->registry->options['languageid'] = $languageinfo['languageid'];

			// Need to import settings here to get the custom settings inserted ..
			xml_import_settings($xml);

			$this->show_message($this->phrase['install']['general_settings_saved']);
			return;
		}

		$port = intval($_SERVER['SERVER_PORT']);
		$port = in_array($port, array(80, 443)) ? '' : ':' . $port;
		$scheme = (($port == ':443') OR (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] AND ($_SERVER['HTTPS'] != 'off'))) ? 'https://' : 'http://';

		$vboptions['bburl'] = $scheme . $_SERVER['SERVER_NAME'] . $port . substr(SCRIPTPATH,0, strpos(SCRIPTPATH, '/install/'));
		$vboptions['homeurl'] = $scheme . $_SERVER['SERVER_NAME'] . $port;

		$webmaster = 'webmaster@' . preg_replace('#^www\.#', '', $_SERVER['SERVER_NAME']);

		$html = '<table cellspacing="0" cellpadding="4" border="0" align="center" width="100%" id="cpform_table" class="" style="border-collapse: separate;">
<tbody>
<tr valign="top">
	<td class="alt1">' .
		$this->phrase['install']['bbtitle'] . '
		<span id="htmldata[bbtitle]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt1"><input type="text" tabindex="1" dir="ltr" size="40" value="Forums" name="htmldata[bbtitle]" class="bginput" vbrequire="1" /></td>
</tr>
<tr valign="top">
	<td class="alt2">' .
		$this->phrase['install']['hometitle'] . '
	</td>
	<td class="alt2"><input type="text" tabindex="1" dir="ltr" size="40" value="" name="htmldata[hometitle]" class="bginput" /></td>
</tr>
<tr valign="top">
	<td class="alt1">' .
		$this->phrase['install']['bburl'] . '
		<span id="htmldata[bburl]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt1"><input type="text" tabindex="1" dir="ltr" size="40" value="' . $vboptions['bburl'] . '" name="htmldata[bburl]" class="bginput" vbrequire="1" /></td>
</tr>
<tr valign="top">
	<td class="alt2">' .
		$this->phrase['install']['homeurl'] . '
		<span id="htmldata[homeurl]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt2"><input type="text" tabindex="1" dir="ltr" size="40" value="' . $vboptions['homeurl'] . '" name="htmldata[homeurl]" class="bginput" vbrequire="1" /></td>
</tr>
<tr valign="top">
	<td class="alt1">' .
		$this->phrase['install']['webmasteremail'] . '
		<span id="htmldata[webmasteremail]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt1"><input type="text" tabindex="1" dir="ltr" size="40" value="' . $webmaster . '" name="htmldata[webmasteremail]" class="bginput" vbrequire="1" /></td>
</tr>
</tbody></table>';

		return array(
			'html'       => $html,
			'width'      => '640px',
			'hidecancel' => true,
			'title'      => $this->phrase['install']['general_settings'],
			'reset'      => true,
		);
	}

	/**
	* Step #6 - Cookie Stuff
	*
	*/
	function step_6($data = null)
	{
		if ($data['response'])
		{
			if ($data['htmldata']['cookiepath_other'] AND $data['htmldata']['cookiepath_value'])
			{
				$cookiepath = $data['htmldata']['cookiepath_value'];
			}
			else
			{
				$cookiepath = $data['htmldata']['cookiepath'];
			}
			if ($data['htmldata']['cookiedomain_other'] AND $data['htmldata']['cookiedomain_value'])
			{
				$cookiedomain = $data['htmldata']['cookiedomain_value'];
			}
			else
			{
				$cookiedomain = $data['htmldata']['cookiedomain'];
			}

			$this->db->query("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '" . $this->db->escape_string($cookiepath) . "'
				WHERE varname = 'cookiepath'
			");

			$this->db->query("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '" . $this->db->escape_string($cookiedomain) . "'
				WHERE varname = 'cookiedomain'
			");

			$this->show_message($this->phrase['install']['cookie_settings_saved']);
			return;
		}

		require_once(DIR . '/includes/adminfunctions_options.php');

		$cookiepaths = construct_select_options(fetch_valid_cookiepaths($this->registry->script), '/');
		$cookiedomains = construct_select_options(fetch_valid_cookiedomains($_SERVER['HTTP_HOST'], $this->phrase['install']['blank']), '');

		$html = '<table cellspacing="0" cellpadding="4" border="0" align="center" width="100%" id="cpform_table" class="" style="border-collapse: separate;">
<tbody>
<tr valign="top">
	<td class="alt2">' . $this->phrase['install']['cookiepath'] . '</td>
	<td class="alt2">
		<fieldset>
			<legend>' . $this->phrase['install']['suggested_settings'] . '</legend>
			<div style="padding: 4px;">
				<select class="bginput lbinstallselect" tabindex="1" name="htmldata[cookiepath]">' . $cookiepaths . '</select>
			</div>
		</fieldset>
		<br>
		<fieldset>
			<legend>' . $this->phrase['install']['custom_setting'] . '</legend>
			<div style="padding: 4px;">
				<label for="cookiepatho"><input type="checkbox" value="1" tabindex="1" name="htmldata[cookiepath_other]" id="cookiepatho" />' . $this->phrase['install']['use_custom_setting'] . '
				</label><br>
				<input type="text" value="" name="htmldata[cookiepath_value]" tabindex="1" size="25" class="bginput" />
			</div>
		</fieldset>
	</td>
</tr>
<tr valign="top">
	<td class="alt1">' . $this->phrase['install']['cookiedomain'] . '</td>
	<td class="alt1">
		<fieldset>
			<legend>' . $this->phrase['install']['suggested_settings'] . '</legend>
			<div style="padding: 4px;">
				<select class="bginput lbinstallselect" tabindex="1" name="htmldata[cookiedomain]">' . $cookiedomains . '</select>
			</div>
		</fieldset>
		<br>
		<fieldset>
			<legend>' . $this->phrase['install']['custom_setting'] . '</legend>
			<div style="padding: 4px;">
				<label for="cookiedomaino"><input type="checkbox" value="1" tabindex="1" name="htmldata[cookiedomain_other]" id="cookiedomaino" />' . $this->phrase['install']['use_custom_setting'] . '
				</label><br>
				<input type="text" value="" name="htmldata[cookiedomain_value]" tabindex="1" size="25" class="bginput" />
			</div>
		</fieldset>
	</td>
</tr>
</tbody></table>';

		return array(
			'html'       => $html,
			'width'      => '640px',
			'hidecancel' => true,
			'title'      => $this->phrase['install']['cookie_settings'],
			'reset'      => true,
		);
	}

	/**
	* Step #7 - Default User Setup...
	*
	*/
	function step_7($data = null)
	{
		if ($data['response'])
		{
			array_map('trim', $data['htmldata']);

			$errors = array();
			if (empty($data['htmldata']['username']))
			{
				$errors['username'] = $this->phrase['install']['error_username'];
			}
			if (empty($data['htmldata']['email']) OR !is_valid_email($data['htmldata']['email']))
			{
				$errors['email'] = $this->phrase['install']['error_email'];
			}
			if (empty($data['htmldata']['password']) OR empty($data['htmldata']['confirmpassword']))
			{
				if (empty($data['htmldata']['password']))
				{
					$errors['password'] = $this->phrase['install']['error_password'];
				}
				else if (empty($data['htmldata']['confirmpassword']))
				{
					$errors['confirmpassword'] = $this->phrase['install']['error_confirmpassword'];
				}
			}
			else if ($data['htmldata']['password'] != $data['htmldata']['confirmpassword'])
			{
				$errors['mismatch'] = $this->phrase['install']['error_password_not_match'];
			}
			else if ($data['htmldata']['password'] == $data['htmldata']['username'] AND !defined('ALLOW_SAME_USERNAME_PASSWORD'))
			{
				$errors['samepasswordasusername'] = $this->phrase['install']['error_same_password_as_username'];
			}

			if (empty($errors))
			{
				require_once(DIR . '/includes/class_bitfield_builder.php');
				vB_Bitfield_Builder::save($this->db);

				$admin_defaults = array('vbasset_enable', 'showsignatures', 'showavatars', 'showimages', 'adminemail', 'dstauto' , 'receivepm', 'showusercss', 'receivefriendemailrequest', 'vm_enable');
				$admin_useroption = 0;
				foreach ($admin_defaults AS $bitfield)
				{
					$admin_useroption |= $this->registry->bf_misc_useroptions["$bitfield"];
				}

				require_once(DIR . '/includes/functions_user.php');
				//for now we'll just include these to get the define for the salt length.  Should investigate
				//using the DM to add the initial admin user, but there may be issues with doing that without
				//a proper user session (which we can't have until we require the user.
				require_once(DIR . '/includes/class_dm.php');
				require_once(DIR . '/includes/class_dm_user.php');


				$salt = fetch_user_salt(SALT_LENGTH);
				/*insert query*/
				$this->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "user
						(username, salt, password, email, usertitle, joindate, lastvisit, lastactivity, usergroupid, passworddate, options, showvbcode)
					VALUES (
						'" . $this->db->escape_string(htmlspecialchars_uni($data['htmldata']['username'])) . "',
						'" . $this->db->escape_string($salt) . "',
						'" . $this->db->escape_string(md5(md5($data['htmldata']['password']) . $salt)) . "',
						'" . $this->db->escape_string($data['htmldata']['email']) . "',
						'" . $this->db->escape_string($this->phrase['install']['usergroup_admin_usertitle']) . "',
						" . TIMENOW . ",
						" . TIMENOW . ",
						" . TIMENOW . ",
						6,
						FROM_UNIXTIME(" . TIMENOW . "),
						$admin_useroption,
						2
					)
				");
				$userid = $this->db->insert_id();
				/*insert query*/
				$this->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "usertextfield
						(userid)
					VALUES
						($userid)
				");
				/*insert query*/
				$this->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "userfield
						(userid)
					VALUES
						($userid)
				");
				/*insert query*/
				$this->db->query_write("INSERT INTO " . TABLE_PREFIX . "administrator
					(userid, adminpermissions)
				VALUES
					($userid, " . (array_sum($this->registry->bf_ugp_adminpermissions)-3) . ")
				");
				/*insert query*/
				$this->db->query_write("INSERT INTO " . TABLE_PREFIX . "moderator
					(userid, forumid, permissions, permissions2)
				VALUES
					(
						$userid,
						-1,
						" . (array_sum($this->registry->bf_misc_moderatorpermissions) - ($this->registry->bf_misc_moderatorpermissions['newthreademail'] + $this->registry->bf_misc_moderatorpermissions['newpostemail'])) . ",
						" . (array_sum($this->registry->bf_misc_moderatorpermissions2)) . "
					)
				");

				build_image_cache('smilie');
				build_image_cache('avatar');
				build_image_cache('icon');
				build_bbcode_cache();
				require_once(DIR . '/includes/functions_databuild.php');
				build_user_statistics();
				require_once(DIR . '/includes/adminfunctions_forums.php');
				build_forum_child_lists();
				build_forum_permissions();
				require_once(DIR . '/includes/functions_cron.php');
				build_cron_next_run();
				require_once(DIR . '/includes/adminfunctions_attachment.php');
				build_attachment_permissions();
				require_once(DIR . '/includes/class_block.php');
				$blockmanager = vB_BlockManager::create($this->registry);
				$blockmanager->reloadBlockTypes();

				$this->show_message($this->phrase['install']['administrator_account_created']);
				return;
			}
			else
			{
				foreach ($errors AS $key => $value)
				{
					$errors["$key"] = '<span class="usererror">' . $value . '</span>';
				}
			}
		}
		else
		{
			$data['htmldata'] = array();
		}

		$html = '<table cellspacing="0" cellpadding="4" border="0" align="center" width="100%" id="cpform_table" class="" style="border-collapse: separate;">
<tbody>
<tr valign="top">
	<td class="alt1">' .
	 	$this->phrase['install']['username'] . $errors['username'] . '
		<span id="htmldata[username]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt1">
		<div id="ctrl_username">
			<input type="text" tabindex="1" dir="ltr" size="35" value="' . htmlspecialchars_uni($data['htmldata']['username']) . '" id="it_username_1" name="htmldata[username]" class="bginput" vbrequire="1" />
		</div>
	</td>
</tr>
<tr valign="top">
	<td class="alt2">' .
		$this->phrase['install']['password'] . $errors['password'] . $errors['mismatch'] . $errors['samepasswordasusername'] . '
		<span id="htmldata[password]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt2">
		<div id="ctrl_password">
			<input type="password" tabindex="1" size="35" value="' . htmlspecialchars_uni($data['htmldata']['password']) . '" name="htmldata[password]" class="bginput" vbrequire="1" />
		</div>
	</td>
</tr>
<tr valign="top">
	<td class="alt1">' .
		$this->phrase['install']['confirm_password'] . $errors['confirmpassword'] . $errors['mismatch'] . '
		<span id="htmldata[confirmpassword]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt1">
		<div id="ctrl_confirmpassword">
			<input type="password" tabindex="1" size="35" value="' . htmlspecialchars_uni($data['htmldata']['confirmpassword']) . '" name="htmldata[confirmpassword]" class="bginput" vbrequire="1" />
		</div>
	</td>
</tr>
<tr valign="top">
	<td class="alt2">' .
		$this->phrase['install']['email_address'] . $errors['email'] . '
		<span id="htmldata[email]_error" class="usererror hidden">' . $this->phrase['install']['field_required'] . '</span>
	</td>
	<td class="alt2">
		<div id="ctrl_email">
			<input type="text" tabindex="1" dir="ltr" size="35" value="' . htmlspecialchars_uni($data['htmldata']['email']) . '" id="it_email_2" name="htmldata[email]" class="bginput" vbrequire="1" />
		</div>
	</td>
</tr>
</tbody></table>';

		return array(
			'html'       => $html,
			'width'      => '640px',
			'hidecancel' => true,
			'title'      => $this->phrase['install']['administrator_account_setup'],
			'reset'      => true,
		);
	}

	/**
	* Load MYSQL schema
	*
	* @return	array
	*/
	private function &load_schema()
	{
		$db =& $this->db;
		$vbphrase =& $this->phrase['vbphrase'];
		$install_phrases =& $this->phrase['install'];
		$phrasetype =& $this->phrase['phrasetype'];
		$customphrases =& $this->phrase['custom'];

		require_once(DIR . '/install/mysql-schema.php');
		return $schema;
	}

	/**
	* Verify if vB is insalled -- this function should check all tables in the schema, not just the user table
	*
	* @return	bool
	*/
	private function fetch_vbexists()
	{
		$this->db->hide_errors();
		$this->db->query_write("SHOW FIELDS FROM " . trim($this->registry->config['Database']['tableprefix']) . "user");
		$this->db->show_errors();

		return ($this->db->errno() == 0);
	}

	/**
	* Executes schema queries...
	*
	* @var	array	Queries to execute
	* @var	array Description of queries
	*
	* @return	bool
	*/
	private function exec_queries($query, $explain)
	{
		foreach ($query AS $key => $value)
		{
			$this->run_query(
				$explain["$key"],
				$value
			);
		}
	}

	/**
	* Parse out table creation steps
	*
	* @var	string	Productid
	*
	* @return	array
	*/
	private function fetch_product_tables($productid)
	{
		// Hackish temporary workaround until product schema is moved to a file similar to how vB stores its schema
		$data = file_get_contents(DIR . "/install/includes/class_upgrade_{$productid}.php");
		$tables = array();
		if (preg_match_all('#CREATE TABLE\s*"\s*\.\s*TABLE_PREFIX\s*\.\s*"([a-z0-9_-]+)#si', $data, $matches))
		{
			foreach($matches[1] AS $table)
			{
				$tables["$table"] = true;
			}
		}

		return array_keys($tables);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
