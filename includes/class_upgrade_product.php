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

/**
* Fetch upgrade lib based on PHP environment
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_Upgrade_Product
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* The object that will be used to execute queries
	*
	* @var	vB_Database
	*/
	protected $db = null;

	/**
	* vbphrase array
	*
	* @var	array
	*/
	protected $vbphrase = array();

	/**
	* XML Object
	*
	* @var	vB_XML_Parser
	*/
	protected $xmlobj = null;

	/**
	* Product info
	*
	* @var	Array
	*/
	public $productinfo = array();

	/**
	* Product Object
	*
	* @var	Array
	*/
	protected $productobj = array();

	/**
	* Rebuild directives
	*
	* @var	Array
	*/
	protected $rebuild = array();

	/**
	* Installed Version
	*
	* @var	string Installed version;
	*/
	public $installed_version = null;

	/**
	* Active
	*
	* @var	boolean	product active
	*/
	protected $active = true;

	/* Output Type
	*
	* @var	string	Outputtype, HTML or command line
	*/
	protected $outputtype = 'html';

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	*/
	public function __construct(&$registry, &$vbphrase, $allow_overwrite, $outputtype = 'ajax')
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
			$this->db =& $this->registry->db;
			$this->vbphrase =& $vbphrase;
		}
		else
		{
			trigger_error('vB_Upgrade: $this->registry is not an object.', E_USER_ERROR);
		}

		$this->outputtype = ($outputtype == 'ajax' OR $outputtype == 'html') ? 'html' : 'text';
		$this->productinfo['allow_overwrite'] = $allow_overwrite;

		require_once(DIR . '/includes/adminfunctions_plugin.php');
		require_once(DIR . '/includes/class_bitfield_builder.php');
		require_once(DIR . '/includes/class_xml.php');
		require_once(DIR . '/includes/class_block.php');
		//share some code with the main xml style import
		require_once(DIR . '/includes/adminfunctions_template.php');
	}

	/**
	* Parse XML
	*
	* @param	string	location of XML
	*/
	public function parse($xml)
	{
		print_dots_start('<b>' . $this->vbphrase['importing_product'] . "</b>, {$this->vbphrase[please_wait]}", ':', 'dspan');

		$this->xmlobj = new vB_XML_Parser($xml);
		if ($this->xmlobj->error_no == 1)
		{
			print_dots_stop();
			throw new vB_Exception_AdminStopMessage('no_xml_and_no_path');
		}

		if(!$this->productobj = $this->xmlobj->parse())
		{
			print_dots_stop();
			throw new vB_Exception_AdminStopMessage(
				array('xml_error_x_at_line_y', $this->xmlobj->error_string(), $this->xmlobj->error_line()));
		}

		// ############## general product information
		$this->productinfo['productid'] = substr(preg_replace('#[^a-z0-9_]#', '', strtolower($this->productobj['productid'])), 0, 25);
		$this->productinfo['title'] = $this->productobj['title'];
		$this->productinfo['description'] = $this->productobj['description'];
		$this->productinfo['version'] = $this->productobj['version'];
		$this->productinfo['active'] = $this->productobj['active'];
		$this->productinfo['url'] = $this->productobj['url'];
		$this->productinfo['versioncheckurl'] = $this->productobj['versioncheckurl'];

		if (!$this->productinfo['productid'])
		{
			print_dots_stop();
			if (!empty($this->productobj['plugin']))
			{
				throw new vB_Exception_AdminStopMessage('this_file_appears_to_be_a_plugin');
			}
			else
			{
				throw new vB_Exception_AdminStopMessage('invalid_file_specified');
			}
		}

		if (strtolower($this->productinfo['productid']) == 'vbulletin')
		{
			print_dots_stop();
			throw new vB_Exception_AdminStopMessage(array('product_x_installed_no_overwrite', 'vBulletin'));
		}

		// check for bitfield conflicts on install
		$bitfields = vB_Bitfield_Builder::return_data();
		if (!$bitfields)
		{
			$bfobj =& vB_Bitfield_Builder::init();
			if ($bfobj->errors)
			{
				print_dots_stop();
				throw new vB_Exception_AdminStopMessage(array(
					'bitfield_conflicts_x',
					'<li>' . implode('</li><li>', $bfobj->errors) . '</li>'
				));
			}
		}

		return true;
	}

	/**
	* Import System Dependencies
	*
	*/
	public function import_dependencies($dependencylist = null)
	{
		// get system version info
		$system_versions = array(
			'php'       => PHP_VERSION,
			'vbulletin' => $this->registry->options['templateversion'],
			'products'  => fetch_product_list(true)
		);

		$mysql_version = $this->db->query_first("SELECT VERSION() AS version");
		$system_versions['mysql'] = $mysql_version['version'];

		if ($dependencylist)
		{
			$this->productobj['dependencies']['dependency'] = $dependencylist;
		}

		// ############## import dependencies
		if (is_array($this->productobj['dependencies']['dependency']))
		{
			$dependencies =& $this->productobj['dependencies']['dependency'];
			if (!isset($dependencies[0]))
			{
				$dependencies = array($dependencies);
			}

			$dependency_errors = array();
			$ignore_dependency_errors = array();

			// let's check the dependencies
			foreach ($dependencies AS $dependency)
			{
				// if we get an error, we haven't met this dependency
				// if we go through without a problem, we have automatically met
				// all dependencies for this "class" (mysql, php, vb, a specific product, etc)
				$this_dependency_met = true;

				// build a phrase for the version compats -- will look like (minver / maxver)
				if ($dependency['minversion'])
				{
					$compatible_phrase = construct_phrase(
						$this->vbphrase['compatible_starting_with_x'],
						htmlspecialchars_uni($dependency['minversion'])
					);
				}
				else
				{
					$compatible_phrase = '';
				}

				if ($dependency['maxversion'])
				{
					$incompatible_phrase = construct_phrase(
						$this->vbphrase['incompatible_with_x_and_greater'],
						htmlspecialchars_uni($dependency['maxversion'])
					);
				}
				else
				{
					$incompatible_phrase = '';
				}

				if ($compatible_phrase OR $incompatible_phrase)
				{
					$required_version_info = "($compatible_phrase";
					if ($compatible_phrase AND $incompatible_phrase)
					{
						$required_version_info .= ' / ';
					}
					$required_version_info .= "$incompatible_phrase)";
				}

				// grab the appropriate installed version string
				if ($dependency['dependencytype'] == 'product')
				{
					// group dependencies into types -- individual products get their own group
					$dependency_type_key = "product-$dependency[parentproductid]";

					// undocumented feature -- you can put a producttitle attribute in a dependency so the id isn't displayed
					$parent_product_title = (!empty($dependency['producttitle']) ? $dependency['producttitle'] : $dependency['parentproductid']);

					$parent_product = $system_versions['products']["$dependency[parentproductid]"];
					if (!$parent_product)
					{
						// required product is not installed
						$dependency_errors["$dependency_type_key"] = construct_phrase(
							$this->vbphrase['product_x_must_be_installed'],
							htmlspecialchars_uni($parent_product_title),
							$required_version_info
						);
						continue; // can't do version checks if the product isn't installed
					}
					else if ($parent_product['active'] == 0)
					{
						// product is installed, but inactive
						$dependency_errors["{$dependency_type_key}-inactive"] = construct_phrase(
							$this->vbphrase['product_x_must_be_activated'],
							htmlspecialchars_uni($parent_product_title)
						);
						$this_dependency_met = false;
						// allow version checks to continue
					}

					$sys_version_str = $parent_product['version'];
					$version_incompatible_phrase = 'product_incompatible_version_x_product_y';
				}
				else
				{
					$dependency_type_key = $dependency['dependencytype'];
					$parent_product_title = '';
					$sys_version_str = $system_versions["$dependency[dependencytype]"];
					$version_incompatible_phrase = 'product_incompatible_version_x_' . $dependency['dependencytype'];
				}

				// if no version string, we are trying to do an unsupported dep check
				if ($sys_version_str == '')
				{
					continue;
				}

				$sys_version = fetch_version_array($sys_version_str);


				// error if installed version < minversion
				if ($dependency['minversion'])
				{
					$dep_version = fetch_version_array($dependency['minversion']);

					for ($i = 0; $i <= 5; $i++)
					{
						if ($sys_version["$i"] < $dep_version["$i"])
						{
							// installed version is too old
							$dependency_errors["$dependency_type_key"] = construct_phrase(
								$this->vbphrase["$version_incompatible_phrase"],
								htmlspecialchars_uni($sys_version_str),
								$required_version_info,
								$parent_product_title
							);
							$this_dependency_met = false;
							break;
						}
						else if ($sys_version["$i"] > $dep_version["$i"])
						{
							break;
						}
					}
				}

				// error if installed version >= maxversion
				if ($dependency['maxversion'])
				{
					$dep_version = fetch_version_array($dependency['maxversion']);

					$all_equal = true;

					for ($i = 0; $i <= 5; $i++)
					{
						if ($sys_version["$i"] > $dep_version["$i"])
						{
							// installed version is newer than the maxversion
							$dependency_errors["$dependency_type_key"] = construct_phrase(
								$this->vbphrase["$version_incompatible_phrase"],
								htmlspecialchars_uni($sys_version_str),
								$required_version_info,
								$parent_product_title
							);
							$this_dependency_met = false;
							break;
						}
						else if ($sys_version["$i"] < $dep_version["$i"])
						{
							// not every part is the same and since we've got less we can exit
							$all_equal = false;
							break;
						}
						else if ($sys_version["$i"] != $dep_version["$i"])
						{
							// not every part is the same
							$all_equal = false;
						}
					}

					if ($all_equal == true)
					{
						// installed version is same as the max version, which is the first incompat version
						$dependency_errors["$dependency_type_key"] = construct_phrase(
							$this->vbphrase["$version_incompatible_phrase"],
							htmlspecialchars_uni($sys_version_str),
							$required_version_info,
							$parent_product_title
						);
						$this_dependency_met = false;
					}
				}

				if ($this_dependency_met)
				{
					// we met 1 dependency for this type -- this emulates or'ing together groups
					$ignore_dependency_errors["$dependency_type_key"] = true;
				}
			}

			// for any group we met a dependency for, ignore any errors we might
			// have gotten for the group
			foreach ($ignore_dependency_errors AS $dependency_type_key => $devnull)
			{
				unset($dependency_errors["$dependency_type_key"]);
			}

			if ($dependency_errors)
			{
				$dependency_errors = array_unique($dependency_errors);
				if ($this->outputtype == 'html')
				{
					$dependency_errors = '<ol class="deperrors"><li>' . implode('</li><li>', $dependency_errors) . '</li></ol>';
				}
				else
				{
					$dependency_errors = implode("\r\n", $dependency_errors);
				}

				print_dots_stop();
				throw new vB_Exception_AdminStopMessage(
					array('dependencies_not_met_x', $dependency_errors));
			}
		}

		// look to see if we already have this product installed
		if ($existingprod = $this->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "product
			WHERE productid = '" . $this->db->escape_string($this->productinfo['productid']) . "'"
		))
		{
			if (!$this->productinfo['allow_overwrite'])
			{
				print_dots_stop();
				throw new vB_Exception_AdminStopMessage(
					array('product_x_installed_no_overwrite', $this->productinfo['title']));
			}

			$this->active = $existingprod['active'];

			// not sure what we're deleting, so rebuild everything
			$this->rebuild = array(
				'templates' => true,
				'plugins'   => true,
				'phrases'   => true,
				'options'   => true,
				'cron'      => true
			);

			$this->installed_version = $existingprod['version'];
		}
		else
		{
			$this->active = ($this->productinfo['active'] ? 1 : 0);

			$this->rebuild = array(
				'templates' => false,
				'plugins'   => false,
				'phrases'   => false,
				'options'   => false,
				'cron'      => false
			);

			$this->installed_version = null;
		}
	}

	/**
	* Verify that product can be installed
	*
	* @param	string	XML file to parse
	*
	* @return	mixed true on success, error message on failure
	*/
	public function verify_install($productid)
	{
		$product_file = DIR . "/includes/xml/product-$productid.xml";
		$xml = file_read($product_file);
		try
		{
			$this->parse($xml);
			$this->import_dependencies();
		}
		catch(vB_Exception_AdminStopMessage $e)
		{
			$args = $e->getParams();

			require_once(DIR . '/includes/functions_misc.php');
			$message = fetch_phrase($args[0], 'error', '', false, false, -1, false);

			if (sizeof($args) > 1)
			{
				$args[0] = $message;
				$message = call_user_func_array('construct_phrase', $args);
			}

			return $message;
		}

		return true;
	}

	/**
	* Execute install code for product
	*
	*/
	public function install()
	{
		$vbulletin =& $this->registry;
		$vbphrase =& $this->vbphrase;
		$db =& $vbulletin->db;

		// ############## import install/uninstall code
		if (is_array($this->productobj['codes']['code']))
		{
			$codes =& $this->productobj['codes']['code'];
			if (!isset($codes[0]))
			{
				$codes = array($codes);
			}

			// run each of the codes
			foreach ($codes AS $code)
			{
				// Run if: code version is * (meaning always run), no version
				//		previously installed, or if the code is for a newer version
				//		than is currently installed
				if ($code['version'] == '*' OR $this->installed_version === null OR is_newer_version($code['version'], $this->installed_version))
				{
					eval($code['installcode']);
				}
			}

			// Clear routes from datastore
			build_datastore('routes', serialize(array()), 1);

			//assume that the product may have installed content types and purge the content type cache
			vB_Cache::instance()->purge('vb_types.types');
		}
	}

	/**
	* Everything that comes after the install - no reason to break this up into chunks at present
	*
	*/
	public function post_install()
	{
		// dependencies checked, install code run. Now clear out the old product info;
		// settings should be retained in memory already
		delete_product($this->productinfo['productid'], false, true);

		$codes =& $this->productobj['codes']['code'];
		if (!isset($codes[0]))
		{
			$codes = array($codes);
		}
		if (is_array($codes))
		{
			// we've now run all the codes, if execution is still going
			// then it's going to complete fully, so insert the codes
			foreach ($codes AS $code)
			{
				/* insert query */
				$this->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "productcode
						(productid, version, installcode, uninstallcode)
					VALUES
						('" . $this->db->escape_string($this->productinfo['productid']) . "',
						'" . $this->db->escape_string($code['version']) . "',
						'" . $this->db->escape_string($code['installcode']) . "',
						'" . $this->db->escape_string($code['uninstallcode']) . "')
				");
			}
		}

		if (is_array($this->productobj['dependencies']['dependency']))
		{
			$dependencies =& $this->productobj['dependencies']['dependency'];
			if (!isset($dependencies[0]))
			{
				$dependencies = array($dependencies);
			}

			// dependencies met, codes run -- now we can insert the dependencies into the DB
			foreach ($dependencies AS $dependency)
			{
				/* insert query */
				$this->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "productdependency
						(productid, dependencytype, parentproductid, minversion, maxversion)
					VALUES
						('" . $this->db->escape_string($this->productinfo['productid']) . "',
						'" . $this->db->escape_string($dependency['dependencytype']) . "',
						'" . $this->db->escape_string($dependency['parentproductid']) . "',
						'" . $this->db->escape_string($dependency['minversion']) . "',
						'" . $this->db->escape_string($dependency['maxversion']) . "')
				");
			}
		}

		/* insert query */
		$this->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "product
				(productid, title, description, version, active, url, versioncheckurl)
			VALUES
				('" . $this->db->escape_string($this->productinfo['productid']) . "',
				'" . $this->db->escape_string($this->productinfo['title']) . "',
				'" . $this->db->escape_string($this->productinfo['description']) . "',
				'" . $this->db->escape_string($this->productinfo['version']) . "',
				" . intval($this->active) . ",
				'" . $this->db->escape_string($this->productinfo['url']) . "',
				'" . $this->db->escape_string($this->productinfo['versioncheckurl']) . "')
		");

		// ############## import templates
		if (is_array($this->productobj['templates']['template']))
		{
			$querybits = array();
			$querytemplates = 0;

			$templates =& $this->productobj['templates']['template'];
			if (!isset($templates[0]))
			{
				$templates = array($templates);
			}

			foreach ($templates AS $template)
			{
				$title = $this->db->escape_string($template['name']);
				$template['template'] = $this->db->escape_string($template['value']);
				$template['username'] = $this->db->escape_string($template['username']);
				$template['templatetype'] = $this->db->escape_string($template['templatetype']);
				$template['date'] = intval($template['date']);

				if ($template['templatetype'] != 'template')
				{
					// template is a special template
					$querybits[] = "(-1, '$template[templatetype]', '$title', '$template[template]', '', $template[date], '$template[username]', '" . $this->db->escape_string($template['version']) . "', '" . $this->db->escape_string($this->productinfo['productid']) . "')";
				}
				else
				{
					// template is a standard template
					$querybits[] = "(-1, '$template[templatetype]', '$title', '" . $this->db->escape_string(compile_template($template['value'])) . "', '$template[template]', $template[date], '$template[username]', '" . $this->db->escape_string($template['version']) . "', '" . $this->db->escape_string($this->productinfo['productid']) . "')";
				}

				if (++$querytemplates % 20 == 0)
				{
					/*insert query*/
					$this->db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "template
							(styleid, templatetype, title, template, template_un, dateline, username, version, product)
						VALUES
							" . implode(',', $querybits) . "
					");
					$querybits = array();
				}

				if (!defined('SUPPRESS_KEEPALIVE_ECHO'))
				{
					echo ' ';
					vbflush();
				}
			}

			// insert any remaining templates
			if (!empty($querybits))
			{
				/*insert query*/
				$this->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "template
						(styleid, templatetype, title, template, template_un, dateline, username, version, product)
					VALUES
						" . implode(',', $querybits) . "
				");
			}
			unset($querybits);

			$rebuild['templates'] = true;
		}

		// ############## import stylevars
		if (is_array($this->productobj['stylevardfns']['stylevargroup']))
		{
			xml_import_stylevar_definitions($this->productobj['stylevardfns'], $this->productinfo['productid']);
		}

		if (is_array($this->productobj['stylevars']['stylevar']))
		{
			xml_import_stylevars($this->productobj['stylevars'], -1);
		}

		// ############## import hooks/plugins
		if (is_array($this->productobj['plugins']['plugin']))
		{
			$plugins =& $this->productobj['plugins']['plugin'];
			if (!isset($plugins[0]))
			{
				$plugins = array($plugins);
			}

			foreach ($plugins AS $plugin)
			{
				$plugin['product'] = $this->productinfo['productid'];
				unset($plugin['devkey']);

				$this->db->query_write(fetch_query_sql($plugin, 'plugin'));
			}

			$rebuild['plugins'] = true;
		}

		// ############## import phrases
		if (is_array($this->productobj['phrases']['phrasetype']))
		{
			require_once(DIR . '/includes/adminfunctions_language.php');

			$master_phrasetypes = array();
			$master_phrasefields = array();
			foreach(fetch_phrasetypes_array(false) as $phrasetype)
			{
				$master_phrasefields["$phrasetype[fieldname]"] = true;
			}

			$phrasetypes =& $this->productobj['phrases']['phrasetype'];
			if (!isset($phrasetypes[0]))
			{
				$phrasetypes = array($phrasetypes);
			}

			foreach ($phrasetypes AS $phrasetype)
			{
				if (empty($phrasetype['phrase']))
				{
					continue;
				}

				if ($phrasetype['fieldname'] == '' OR !preg_match('#^[a-z0-9_]+$#i', $phrasetype['fieldname'])) // match a-z, A-Z, 0-9,_ only
				{
					continue;
				}

				$fieldname = $master_phrasefields["$phrasetype[fieldname]"];

				if (!$fieldname)
				{
					$this->db->query_write("
						INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
							(fieldname, title, editrows, product)
						VALUES
							('" . $this->db->escape_string($phrasetype['fieldname']) . "',
							'" . $this->db->escape_string($phrasetype['name']) . "',
							3,
							'" . $this->db->escape_string($this->productinfo['productid']) . "')
					");

					// need to add the column to the language table as well
					require_once(DIR . '/includes/class_dbalter.php');

					$this->db_alter = new vB_Database_Alter_MySQL($this->db);
					if ($this->db_alter->fetch_table_info('language'))
					{
						$this->db_alter->add_field(array(
							'name' => "phrasegroup_$phrasetype[fieldname]",
							'type' => 'mediumtext'
						));
					}
				}

				$phrases =& $phrasetype['phrase'];
				if (!isset($phrases[0]))
				{
					$phrases = array($phrases);
				}

				$sql = array();

				foreach ($phrases AS $phrase)
				{
					$sql[] = "
						(-1,
						'" . $this->db->escape_string($phrasetype['fieldname']) . "',
						'" . $this->db->escape_string($phrase['name']) . "',
						'" . $this->db->escape_string($phrase['value']) . "',
						'" . $this->db->escape_string($this->productinfo['productid']) . "',
						'" . $this->db->escape_string($phrase['username']) . "',
						" . intval($phrase['date']) . ",
						'" . $this->db->escape_string($phrase['version']) . "')
					";
				}

				/*insert query*/
				$this->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product, username, dateline, version)
					VALUES
						" . implode(',', $sql)
				);
			}

			$rebuild['phrases'] = true;
		}

		// ############## import settings
		if (is_array($this->productobj['options']['settinggroup']))
		{
			$settinggroups =& $this->productobj['options']['settinggroup'];
			if (!isset($settinggroups[0]))
			{
				$settinggroups = array($settinggroups);
			}

			foreach ($settinggroups AS $group)
			{
				if (empty($group['setting']))
				{
					continue;
				}

				// create the setting group if it doesn't already exist
				/*insert query*/
				$this->db->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "settinggroup
						(grouptitle, displayorder, volatile, product)
					VALUES
						('" . $this->db->escape_string($group['name']) . "',
						" . intval($group['displayorder']) . ",
						1,
						'" . $this->db->escape_string($this->productinfo['productid']) . "')
				");

				$settings =& $group['setting'];
				if (!isset($settings[0]))
				{
					$settings = array($settings);
				}

				$setting_bits = array();

				foreach ($settings AS $setting)
				{
					if (isset($this->registry->options["$setting[varname]"]))
					{
						$newvalue = $this->registry->options["$setting[varname]"];
					}
					else
					{
						$newvalue = $setting['defaultvalue'];
					}

					$setting_bits[] = "(
						'" . $this->db->escape_string($setting['varname']) . "',
						'" . $this->db->escape_string($group['name']) . "',
						'" . $this->db->escape_string(trim($newvalue)) . "',
						'" . $this->db->escape_string(trim($setting['defaultvalue'])) . "',
						'" . $this->db->escape_string(trim($setting['datatype'])) . "',
						'" . $this->db->escape_string($setting['optioncode']) . "',
						" . intval($setting['displayorder']) . ",
						" . intval($setting['advanced']) . ",
						1,
						'" . $this->db->escape_string($setting['validationcode']) . "',
						" . intval($setting['blacklist']) . ",
						'" . $this->db->escape_string($this->productinfo['productid']) . "'\n\t)";
				}

				/*insert query*/
				$this->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "setting
						(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile, validationcode, blacklist, product)
					VALUES
						" . implode(",\n\t", $setting_bits)
				);
			}

			$rebuild['options'] = true;
		}

		// ############## import admin help
		if (is_array($this->productobj['helptopics']['helpscript']))
		{
			$help_scripts =& $this->productobj['helptopics']['helpscript'];
			if (!isset($help_scripts[0]))
			{
				$help_scripts = array($help_scripts);
			}

			foreach ($help_scripts AS $help_script)
			{
				// Deal with single entry
				if (!is_array($help_script['helptopic'][0]))
				{
					$help_script['helptopic'] = array($help_script['helptopic']);
				}

				$help_sql = array();
				foreach ($help_script['helptopic'] AS $topic)
				{
					$helpsql[] = "
						('" . $this->db->escape_string($help_script['name']) . "',
						'" . $this->db->escape_string($topic['act']) . "',
						'" . $this->db->escape_string($topic['opt']) . "',
						" . intval($topic['disp']) . ",
						1,
						'" . $this->db->escape_string($this->productinfo['productid']) . "')
					";
				}

				if (!empty($helpsql))
				{
					/*insert query*/
					$this->db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "adminhelp
							(script, action, optionname, displayorder, volatile, product)
						VALUES
							" . implode(",\n\t", $helpsql)
					);
				}
			}
		}

		// ############## import cron
		if (is_array($this->productobj['cronentries']['cron']))
		{
			require_once(DIR . '/includes/functions_cron.php');

			$cron_entries =& $this->productobj['cronentries']['cron'];
			if (!isset($cron_entries[0]))
			{
				$cron_entries = array($cron_entries);
			}

			foreach ($cron_entries AS $cron)
			{
				$cron['varname'] = preg_replace('#[^a-z0-9_]#i', '', $cron['varname']);
				if (!$cron['varname'])
				{
					continue;
				}

				$cron['active'] = ($cron['active'] ? 1 : 0);
				$cron['loglevel'] = ($cron['loglevel'] ? 1 : 0);

				$scheduling = $cron['scheduling'];
				$scheduling['weekday'] = intval($scheduling['weekday']);
				$scheduling['day'] = intval($scheduling['day']);
				$scheduling['hour'] = intval($scheduling['hour']);
				$scheduling['minute'] = explode(',', preg_replace('#[^0-9,-]#i', '', $scheduling['minute']));
				if (count($scheduling['minute']) == 0)
				{
					$scheduling['minute'] = array(0);
				}
				else
				{
					$scheduling['minute'] = array_map('intval', $scheduling['minute']);
				}

				/*insert query*/
				$this->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "cron
						(weekday, day, hour, minute, filename, loglevel, active, varname, volatile, product)
					VALUES
						($scheduling[weekday],
						$scheduling[day],
						$scheduling[hour],
						'" . $this->db->escape_string(serialize($scheduling['minute'])) . "',
						'" . $this->db->escape_string($cron['filename']) . "',
						$cron[loglevel],
						$cron[active],
						'" . $this->db->escape_string($cron['varname']) . "',
						1,
						'" . $this->db->escape_string($this->productinfo['productid']) . "')
				");
				$cronid = $this->db->insert_id(); // replace either inserts, or deletes+inserts
				if ($cronid)
				{
					build_cron_item($cronid);
				}

				$rebuild['cron'] = true;
			}
		}

		// ############## import faq
		if (is_array($this->productobj['faqentries']['faq']))
		{
			$faq_entries =& $this->productobj['faqentries']['faq'];
			if (!isset($faq_entries[0]))
			{
				$faq_entries = array($faq_entries);
			}

			$sql = array();
			foreach ($faq_entries AS $faq)
			{
				$sql[] = "
					('" . $this->db->escape_string($faq['faqname']) . "',
					'" . $this->db->escape_string($faq['faqparent']) . "',
					" . intval($faq['displayorder']) . ",
					1,
					'" . $this->db->escape_string($this->productinfo['productid']) . "')
				";
			}

			if ($sql)
			{
				/*insert query*/
				$this->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "faq
						(faqname, faqparent, displayorder, volatile, product)
					VALUES
						" . implode(',', $sql) . "
				");
			}
		}

		$products = fetch_product_list(true);
		// Check if the plugin system is disabled. If it is, enable it if this product isn't installed.
		if (!$this->registry->options['enablehooks'] AND !$products[$this->productinfo['productid']])
		{
			$this->db->query_write("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '1'
				WHERE varname = 'enablehooks'
			");

			$rebuild['options'] = true;
		}

		// Now rebuild everything we need...
		if ($rebuild['plugins'])
		{
			vBulletinHook::build_datastore($this->db);

			if ($this->active)
			{
				$plugin_data = $this->db->query_read("
					SELECT *
					FROM " . TABLE_PREFIX . "datastore
					WHERE title IN ('pluginlist', 'pluginlistadmin')
				");
				while ($plugin_info = $this->db->fetch_array($plugin_data))
				{
					if ($plugin_info['title'] == 'pluginlist')
					{
						$this->registry->pluginlist = unserialize($plugin_info['data']);
					}
					else if ($plugin_info['title'] == 'pluginlistadmin')
					{
						$this->registry->pluginlistadmin = unserialize($plugin_info['data']);
					}
				}

				// enable any hooks -- this is mainly necessary for importing templates (template_safe_functions hook)
				if (!defined('DISABLE_HOOKS') AND VB_AREA != 'Upgrade')
				{
					if (!empty($this->registry->pluginlistadmin) AND is_array($this->registry->pluginlistadmin))
					{
						$this->registry->pluginlist = array_merge($this->registry->pluginlist, $this->registry->pluginlistadmin);
						unset($this->registry->pluginlistadmin);
					}
					vBulletinHook::set_pluginlist($this->registry->pluginlist);
				}
			}
		}
		if ($rebuild['templates'])
		{
			if ($error = build_all_styles(0, 0, ''))
			{
				return $error;
			}
		}
		if ($rebuild['phrases'])
		{
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();
		}
		if ($rebuild['options'])
		{
			build_options();
		}
		if ($rebuild['cron'])
		{
			require_once(DIR . '/includes/functions_cron.php');
			build_cron_next_run();
		}

		build_product_datastore();

		// build bitfields to remove/add this products bitfields
		vB_Bitfield_Builder::save($this->db);

		// reload block types
		$blockmanager = vB_BlockManager::create($this->registry);
		$blockmanager->reloadBlockTypes();

		print_dots_stop();

		$this->productinfo['need_merge'] = ($rebuild['templates'] AND $installed_version);
		return $this->productinfo;
	}

	/**
	* Disable a product, not delete
	*
	*/
	public function disable($productid = null)
	{
		$productid = $productid ? $productid : $this->productinfo['productid'];
		$this->db->query_write("
			UPDATE " . TABLE_PREFIX . "product
			SET active = 0
			WHERE productid = '" . $this->db->escape_string($productid) . "'
		");

		vBulletinHook::build_datastore($this->db);
		build_product_datastore();

		// build bitfields to remove/add this products bitfields
		require_once(DIR . '/includes/class_bitfield_builder.php');
		vB_Bitfield_Builder::save($this->db);

		// Products can enable a cron entries, so we need to rebuild that as well
		require_once(DIR . '/includes/functions_cron.php');
		build_cron_next_run();

		// Purge cache -- doesn't apply to pre-vB4 versions
		if (class_exists('vB_Cache'))
		{
			vB_Cache::instance()->purge('vb_types.types');
		}

		// Reload blocks and block types -- doesn't apply to pre-vB4 versions
		if (class_exists('vB_BlockManager'))
		{
			$blockmanager = vB_BlockManager::create($this->registry);
			$blockmanager->reloadBlockTypes();
			$blockmanager->getBlocks(true, true);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/