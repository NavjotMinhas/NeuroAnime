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

/**
* Deletes all the associated data for a specific from the database.
* Only known master (volatile) data is removed. For example, customized versions
* of the templates are left.
*
* @param	string	Product ID to delete
* @param	string	Whether the deletion needs to work on a 3.5 DB (for vB upgrade scripts)
* @param	string	True if you are calling this for a product upgrade. Old master templates will be moved instead of removed.
*/
function delete_product($productid, $compliant_35 = false, $for_product_upgrade = false)
{
	global $vbulletin;

	$productid = $vbulletin->db->escape_string($productid);

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "product WHERE productid = '$productid'");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "productcode WHERE productid = '$productid'");
	if ($compliant_35 == false)
	{
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "productdependency WHERE productid = '$productid'");
	}
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "plugin WHERE product = '$productid'");

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "phrase WHERE product = '$productid' AND languageid = -1");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "phrasetype WHERE product = '$productid'");

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE product = '$productid' AND styleid = -10");
	if ($for_product_upgrade)
	{
		$vbulletin->db->query_write("
			DELETE t1
			FROM " . TABLE_PREFIX . "template AS t1
			INNER JOIN " . TABLE_PREFIX . "template AS t2 ON (t1.title = t2.title AND t2.product = '$productid' AND t2.styleid = -1)
			WHERE t1.styleid = -10
		");
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "template SET
				styleid = -10
			WHERE product = '$productid' AND styleid = -1
		");
	}
	else
	{
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE product = '$productid' AND styleid = -1");

		$ids = array();

		if (!$compliant_35)
		{
			$types = $vbulletin->db->query_read("
				SELECT contenttypeid
				FROM " . TABLE_PREFIX . "contenttype AS c
				INNER JOIN " . TABLE_PREFIX . "package AS p ON (c.packageid = p.packageid)
				WHERE
					p.productid = '$productid'
						AND
					c.canattach = 1
			");

			while ($type = $vbulletin->db->fetch_array($types))
			{
				$ids[] = $type['contenttypeid'];
			}

			if (!empty($ids))
			{
				$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_SILENT, 'attachment');
				$attachdata->set_condition("a.contenttypeid IN (" . implode(",", $ids) . ")");
				$attachdata->delete(true, false);
			}
		}
	}

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "setting WHERE product = '$productid' AND volatile = 1");
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "settinggroup WHERE product = '$productid' AND volatile = 1");

	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "adminhelp WHERE product = '$productid' AND volatile = 1");
	if ($compliant_35 == false)
	{
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "cron WHERE product = '$productid' AND volatile = 1");
		$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "faq WHERE product = '$productid' AND volatile = 1");
	}

	$vbulletin->db->hide_errors();
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "moderatorlog WHERE product = '$productid'");
	$vbulletin->db->show_errors();
}



function get_product_export_xml($productid)
{
	global $vbulletin;

	//	Set up the parent tag
	$product_details = $vbulletin->db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $vbulletin->db->escape_string($productid) . "'
	");

	if (!$product_details)
	{
		throw new vB_Exception_AdminStopMessage('invalid_product_specified');
	}

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder($vbulletin);

	$export_styleid = -1;
	$export_languageids = array(-1, 0);

	// ############## main product info
	$xml->add_group(
		'product', array(
		'productid' => strtolower($product_details['productid']),
		'active' => $product_details['active']
	)); // Parent for product

	$xml->add_tag('title', $product_details['title']);
	$xml->add_tag('description', $product_details['description']);
	$xml->add_tag('version', $product_details['version']);
	$xml->add_tag('url', $product_details['url']);
	$xml->add_tag('versioncheckurl', $product_details['versioncheckurl']);

	($hook = vBulletinHook::fetch_hook('admin_product_export')) ? eval($hook) : false;
	// ############## dependencies
	$product_dependencies = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "productdependency
		WHERE productid = '" . $vbulletin->db->escape_string($productid) . "'
		ORDER BY dependencytype, parentproductid, minversion
	");

	$xml->add_group('dependencies');

	while ($product_dependency = $vbulletin->db->fetch_array($product_dependencies))
	{
		$deps = array('dependencytype' => $product_dependency['dependencytype']);
		if ($product_dependency['dependencytype'] == 'product')
		{
			$deps['parentproductid'] = $product_dependency['parentproductid'];
		}
		$deps['minversion'] = $product_dependency['minversion'];
		$deps['maxversion'] = $product_dependency['maxversion'];

		$xml->add_tag('dependency', '', $deps);
	}

	$xml->close_group();

	// ############## install / uninstall codes
	$productcodes = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "productcode
		WHERE productid = '" . $vbulletin->db->escape_string($productid) . "'
	");

	$xml->add_group('codes');

	$productcodes_grouped = array();
	$productcodes_versions = array();

	while ($productcode = $vbulletin->db->fetch_array($productcodes))
	{
		// have to be careful here, as version numbers are not necessarily unique
		$productcodes_versions["$productcode[version]"] = 1;
		$productcodes_grouped["$productcode[version]"][] = $productcode;
	}

	$productcodes_versions = array_keys($productcodes_versions);
	usort($productcodes_versions, 'version_sort');

	foreach ($productcodes_versions AS $version)
	{
		foreach ($productcodes_grouped["$version"] AS $productcode)
		{
			$xml->add_group('code', array('version' => $productcode['version']));
				$xml->add_tag('installcode', $productcode['installcode']);
				$xml->add_tag('uninstallcode', $productcode['uninstallcode']);
			$xml->close_group();
		}
	}

	$xml->close_group();

	//hack in the ability to handle styles other than the master in a sane fashion.
	//We can set it via a hook for the moment, it should be a fairly temporary need.
	//There was some logic that look like you might be able to export more than one
	//style, but it didn't make much sense -- if you selected multiple styles then
	//you'd end up with templates from multiple styles with no attempt to handle duplicates
	//and no requirement that the styles in question were parents/children of each other.
	if ($export_styleid == -1)
	{
		$sqlcondition = "styleid = -1";
		$parentlist = "-1";
	}
	else
	{
		// query everything from the specified style
		$style = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "style
			WHERE styleid = " . $export_styleid
		);
		$sqlcondition = "templateid IN(" . implode(',', unserialize($style['templatelist'])) . ")";
		$parentlist = $style['parentlist'];
	}

	// ############## templates
	$gettemplates = $vbulletin->db->query_read("
		SELECT title, templatetype, username, dateline, version, product,
			IF(templatetype = 'template', template_un, template) AS template
		FROM " . TABLE_PREFIX . "template
		WHERE product = '" . $vbulletin->db->escape_string($productid) . "'
			AND $sqlcondition
		ORDER BY title
	");

	$xml->add_group('templates');

	while ($template = $vbulletin->db->fetch_array($gettemplates))
	{
		if (is_newer_version($template['version'], $product_details['version']))
		{
			// version in the template is newer than the version of the product,
			// which probably means it's using the vB version
			$template['version'] = $product_details['version'];
		}

		$xml->add_tag('template', $template['template'], array(
			'name' => htmlspecialchars($template['title']),
			'templatetype' => $template['templatetype'],
			'date' => $template['dateline'],
			'username' => $template['username'],
			'version' => htmlspecialchars_uni($template['version'])
		), true);
	}

	$xml->close_group();

	// ############## Stylevars
	$stylevarinfo = get_stylevars_for_export($productid, $parentlist, true);
	$stylevar_cache = $stylevarinfo['stylevars'];
	$stylevar_dfn_cache = $stylevarinfo['stylevardfns'];


	$xml->add_group('stylevardfns');
	foreach ($stylevar_dfn_cache AS $stylevargroupname => $stylevargroup)
	{
		$xml->add_group('stylevargroup', array('name' => $stylevargroupname));
		foreach($stylevargroup AS $stylevar)
		{
			$xml->add_tag('stylevar', '',
				array(
					'name' => htmlspecialchars($stylevar['stylevarid']),
					'datatype' => $stylevar['datatype'],
					'validation' => base64_encode($stylevar['validation']),
					'failsafe' => base64_encode($stylevar['failsafe'])
				)
			);
		}
		$xml->close_group();
	}
	$xml->close_group();
	unset($stylevar_dfn_cache);


	$xml->add_group('stylevars');
	foreach ($stylevar_cache AS $stylevarid => $stylevar)
	{
		$xml->add_tag('stylevar', '',
			array(
				'name' => htmlspecialchars($stylevar['stylevarid']),
				'value' => base64_encode($stylevar['value'])
			)
		);
	}
	$xml->close_group();
	unset($stylevar_dfn_cache);

	// ############## plugins

	$xml->add_group('plugins');

	$plugins = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "plugin
		WHERE product = '" . $vbulletin->db->escape_string($productid) . "'
		ORDER BY hookname
	");
	while ($plugin = $vbulletin->db->fetch_array($plugins))
	{
		$params = array('active' => $plugin['active'], 'executionorder' => $plugin['executionorder']);

		$xml->add_group('plugin', $params);
			$xml->add_tag('title', $plugin['title']);
			$xml->add_tag('hookname', $plugin['hookname']);
			$xml->add_tag('phpcode', $plugin['phpcode']);
		$xml->close_group();
	}

	unset($plugin);
	$vbulletin->db->free_result($plugins);
	$xml->close_group();

	// ############## phrases
	require_once(DIR . '/includes/adminfunctions_language.php');

	$phrasetypes = fetch_phrasetypes_array(false);

	$phrases = array();
	$getphrases = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (" . implode(',', $export_languageids) . ")
			AND product = '" . $vbulletin->db->escape_string($productid) . "'
		ORDER BY languageid, fieldname, varname
	");
	while ($getphrase = $vbulletin->db->fetch_array($getphrases))
	{
		$phrases["$getphrase[fieldname]"]["$getphrase[varname]"] = $getphrase;
	}
	unset($getphrase);
	$vbulletin->db->free_result($getphrases);

	$xml->add_group('phrases');

	// make sure the phrasegroups are in a reliable order
	ksort($phrases);

	foreach ($phrases AS $_fieldname => $typephrases)
	{
		// create a group for each phrase type that we have phrases for
		// then insert the phrases

		$xml->add_group('phrasetype', array('name' => $phrasetypes["$_fieldname"]['title'], 'fieldname' => $_fieldname));

		// make sure the phrases are in a reliable order
		ksort($typephrases);

		foreach ($typephrases AS $phrase)
		{
			$xml->add_tag('phrase', $phrase['text'], array(
				'name' => $phrase['varname'],
				'date' => $phrase['dateline'],
				'username' => $phrase['username'],
				'version' => htmlspecialchars_uni($phrase['version'])
			), true);
		}

		$xml->close_group();
	}

	$xml->close_group();

	// ############## options
	$setting = array();
	$settinggroup = array();

	$groups = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "settinggroup
		WHERE volatile = 1
		ORDER BY displayorder, grouptitle
	");
	while ($group = $vbulletin->db->fetch_array($groups))
	{
		$settinggroup["$group[grouptitle]"] = $group;
	}

	ksort($settinggroup);

	$options = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "setting
		WHERE product = '" . $vbulletin->db->escape_string($productid) . "'
			AND volatile = 1
		ORDER BY displayorder, varname
	");
	while ($row = $vbulletin->db->fetch_array($options))
	{
		$setting["$row[grouptitle]"][] = $row;
	}
	unset($row);

	$vbulletin->db->free_result($options);

	$xml->add_group('options');

	foreach ($settinggroup AS $grouptitle => $group)
	{
		if (empty($setting["$grouptitle"]))
		{
			continue;
		}

		// add a group for each setting group we have settings for

		$xml->add_group('settinggroup', array('name' => htmlspecialchars($group['grouptitle']), 'displayorder' => $group['displayorder']));

		ksort($setting["$grouptitle"]);

		foreach($setting["$grouptitle"] AS $set)
		{
			$arr = array('varname' => $set['varname'], 'displayorder' => $set['displayorder']);
			if ($set['advanced'])
			{
				$arr['advanced'] = 1;
			}
			$xml->add_group('setting', $arr);

			if ($set['datatype'])
			{
				$xml->add_tag('datatype', $set['datatype']);
			}
			if ($set['optioncode'] != '')
			{
				$xml->add_tag('optioncode', $set['optioncode']);
			}
			if ($set['validationcode'])
			{
				$xml->add_tag('validationcode', $set['validationcode']);
			}
			if ($set['defaultvalue'] !== '')
			{
				$xml->add_tag('defaultvalue', $set['defaultvalue']);
			}
			if ($set['blacklist'])
			{
				$xml->add_tag('blacklist', 1);
			}

			$xml->close_group();
		}

		$xml->close_group();
	}

	$xml->close_group();

	// ############## admin help
	$help_topics_results = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "adminhelp
		WHERE product = '" . $vbulletin->db->escape_string($productid) . "'
			AND volatile = 1
		ORDER BY script, action, displayorder, optionname
	");
	$help_topics = array();
	while ($help_topic = $vbulletin->db->fetch_array($help_topics_results))
	{
		$help_topics["$help_topic[script]"][] = $help_topic;
	}
	$vbulletin->db->free_result($help_topics_results);
	ksort($help_topics);

	$xml->add_group('helptopics');

	foreach ($help_topics AS $script => $script_topics)
	{
		$xml->add_group('helpscript', array('name' => $script));
		foreach ($script_topics AS $topic)
		{
			$attr = array('disp' => $topic['displayorder']);
			if ($topic['action'])
			{
				$attr['act'] = $topic['action'];
			}
			if ($topic['optionname'])
			{
				$attr['opt'] = $topic['optionname'];
			}
			$xml->add_tag('helptopic', '', $attr);
		}
		$xml->close_group();
	}

	$xml->close_group();

	// ############## Cron entries
	$cron_results = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "cron
		WHERE product = '" . $vbulletin->db->escape_string($productid) . "'
			AND volatile = 1
			AND varname <> ''
		ORDER BY varname
	");

	$xml->add_group('cronentries');

	while ($cron = $vbulletin->db->fetch_array($cron_results))
	{
		$minutes = unserialize($cron['minute']);
		if (!is_array($minutes))
		{
			$minutes = array();
		}

		$xml->add_group('cron', array(
			'varname' => $cron['varname'],
			'active' => $cron['active'],
			'loglevel' => $cron['loglevel']
		));
		$xml->add_tag('filename', $cron['filename']);
		$xml->add_tag('scheduling', '', array(
			'weekday' => $cron['weekday'],
			'day' => $cron['day'],
			'hour' => $cron['hour'],
			'minute' => implode(',', $minutes)
		));
		$xml->close_group();
	}

	$xml->close_group();

	$vbulletin->db->free_result($cron_results);

	// ############## FAQ entries

	$faq_results = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "faq
		WHERE product = '" . $vbulletin->db->escape_string($productid) . "'
			AND volatile = 1
		ORDER BY faqname
	");

	$xml->add_group('faqentries');

	while ($faq = $vbulletin->db->fetch_array($faq_results))
	{
		$xml->add_tag('faq', '', array(
			'faqname' => $faq['faqname'],
			'faqparent' => $faq['faqparent'],
			'displayorder' => $faq['displayorder'],
		));
	}

	$xml->close_group();

	$vbulletin->db->free_result($faq_results);


	// ############## Finish up
	$xml->close_group();
	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n" . $xml->output();

	unset($xml);
	return $doc;
}

/**
* Installs a product from the xml text
*
* This function depends on the vb class loader, which requires that the
* framework init is called.
*
* @return bool True if the product requires a template merge, false otherwise
*/
function install_product($xml, $allow_overwrite)
{
	global $vbphrase;
	global $vbulletin;
	global $db;

	/*
		// Preferred code replacement - surely to break many existing product installs

		require_once(DIR . '/includes/class_upgrade_product.php');
		$product = new vB_Upgrade_Product($vbulletin, $vbphrase, $allow_overwrite);
		$product->parse($xml);
		$product->import_dependencies();
		$product->install();
		return $product->post_install();
	*/

	require_once(DIR . '/includes/class_bitfield_builder.php');
	require_once(DIR . '/includes/class_xml.php');
	require_once(DIR . '/includes/class_block.php');

	//share some code with the main xml style import
	require_once(DIR . '/includes/adminfunctions_template.php');

	print_dots_start('<b>' . $vbphrase['importing_product'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	$xmlobj = new vB_XML_Parser($xml);
	if ($xmlobj->error_no == 1)
	{
		print_dots_stop();
		throw new vB_Exception_AdminStopMessage('no_xml_and_no_path');
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		throw new vB_Exception_AdminStopMessage(
			array('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line()));
	}

	// ############## general product information
	$info = array(
		'productid'       => substr(preg_replace('#[^a-z0-9_]#', '', strtolower($arr['productid'])), 0, 25),
		'title'           => $arr['title'],
		'description'     => $arr['description'],
		'version'         => $arr['version'],
		'active'          => $arr['active'],
		'url'             => $arr['url'],
		'versioncheckurl' => $arr['versioncheckurl']
	);

	if (!$info['productid'])
	{
		print_dots_stop();
		if (!empty($arr['plugin']))
		{
			throw new vB_Exception_AdminStopMessage('this_file_appears_to_be_a_plugin');
		}
		else
		{
			throw new vB_Exception_AdminStopMessage('invalid_file_specified');
		}
	}

	if (strtolower($info['productid']) == 'vbulletin')
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

	// get system version info
	$system_versions = array(
		'php'       => PHP_VERSION,
		'vbulletin' => $vbulletin->options['templateversion'],
		'products'  => fetch_product_list(true)
	);
	$mysql_version = $db->query_first("SELECT VERSION() AS version");
	$system_versions['mysql'] = $mysql_version['version'];

	// ############## import dependencies
	if (is_array($arr['dependencies']['dependency']))
	{
		$dependencies =& $arr['dependencies']['dependency'];
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
					$vbphrase['compatible_starting_with_x'],
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
					$vbphrase['incompatible_with_x_and_greater'],
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
						$vbphrase['product_x_must_be_installed'],
						htmlspecialchars_uni($parent_product_title),
						$required_version_info
					);
					continue; // can't do version checks if the product isn't installed
				}
				else if ($parent_product['active'] == 0)
				{
					// product is installed, but inactive
					$dependency_errors["{$dependency_type_key}-inactive"] = construct_phrase(
						$vbphrase['product_x_must_be_activated'],
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
							$vbphrase["$version_incompatible_phrase"],
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
							$vbphrase["$version_incompatible_phrase"],
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
						$vbphrase["$version_incompatible_phrase"],
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
			$dependency_errors = '<ol><li>' . implode('</li><li>', $dependency_errors) . '</li></ol>';

			print_dots_stop();
			throw new vB_Exception_AdminStopMessage(
				array('dependencies_not_met_x', $dependency_errors));
		}
	}

	// look to see if we already have this product installed
	if ($existingprod = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($info['productid']) . "'"
	))
	{
		if (!$allow_overwrite)
		{
			print_dots_stop();
			throw new vB_Exception_AdminStopMessage(
				array('product_x_installed_no_overwrite', $info['title']));
		}

		$active = $existingprod['active'];

		// not sure what we're deleting, so rebuild everything
		$rebuild = array(
			'templates' => true,
			'plugins'   => true,
			'phrases'   => true,
			'options'   => true,
			'cron'      => true
		);

		$installed_version = $existingprod['version'];
	}
	else
	{
		$active = ($info['active'] ? 1 : 0);

		$rebuild = array(
			'templates' => false,
			'plugins'   => false,
			'phrases'   => false,
			'options'   => false,
			'cron'      => false
		);

		$installed_version = null;
	}

	// ############## import install/uninstall code
	if (is_array($arr['codes']['code']))
	{
		$codes =& $arr['codes']['code'];
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
			if ($code['version'] == '*' OR $installed_version === null OR is_newer_version($code['version'], $installed_version))
			{
				eval($code['installcode']);
			}
		}

		// Clear routes from datastore
		build_datastore('routes', serialize(array()), 1);

		//assume that the product may have installed content types and purge the content type cache
		vB_Cache::instance()->purge('vb_types.types');
	}

	// dependencies checked, install code run. Now clear out the old product info;
	// settings should be retained in memory already
	delete_product($info['productid'], false, true);

	if (is_array($codes))
	{
		// we've now run all the codes, if execution is still going
		// then it's going to complete fully, so insert the codes
		foreach ($codes AS $code)
		{
			/* insert query */
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "productcode
					(productid, version, installcode, uninstallcode)
				VALUES
					('" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($code['version']) . "',
					'" . $db->escape_string($code['installcode']) . "',
					'" . $db->escape_string($code['uninstallcode']) . "')
			");
		}
	}

	if (is_array($dependencies))
	{
		// dependencies met, codes run -- now we can insert the dependencies into the DB
		foreach ($dependencies AS $dependency)
		{
			/* insert query */
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "productdependency
					(productid, dependencytype, parentproductid, minversion, maxversion)
				VALUES
					('" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($dependency['dependencytype']) . "',
					'" . $db->escape_string($dependency['parentproductid']) . "',
					'" . $db->escape_string($dependency['minversion']) . "',
					'" . $db->escape_string($dependency['maxversion']) . "')
			");
		}
	}

	/* insert query */
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "product
			(productid, title, description, version, active, url, versioncheckurl)
		VALUES
			('" . $db->escape_string($info['productid']) . "',
			'" . $db->escape_string($info['title']) . "',
			'" . $db->escape_string($info['description']) . "',
			'" . $db->escape_string($info['version']) . "',
			" . intval($active) . ",
			'" . $db->escape_string($info['url']) . "',
			'" . $db->escape_string($info['versioncheckurl']) . "')
	");

	// ############## import templates
	if (is_array($arr['templates']['template']))
	{
		$querybits = array();
		$querytemplates = 0;

		$templates =& $arr['templates']['template'];
		if (!isset($templates[0]))
		{
			$templates = array($templates);
		}

		foreach ($templates AS $template)
		{
			$title = $db->escape_string($template['name']);
			$template['template'] = $db->escape_string($template['value']);
			$template['username'] = $db->escape_string($template['username']);
			$template['templatetype'] = $db->escape_string($template['templatetype']);
			$template['date'] = intval($template['date']);

			if ($template['templatetype'] != 'template')
			{
				// template is a special template
				$querybits[] = "(-1, '$template[templatetype]', '$title', '$template[template]', '', $template[date], '$template[username]', '" . $db->escape_string($template['version']) . "', '" . $db->escape_string($info['productid']) . "')";
			}
			else
			{
				// template is a standard template
				$querybits[] = "(-1, '$template[templatetype]', '$title', '" . $db->escape_string(compile_template($template['value'])) . "', '$template[template]', $template[date], '$template[username]', '" . $db->escape_string($template['version']) . "', '" . $db->escape_string($info['productid']) . "')";
			}

			if (++$querytemplates % 20 == 0)
			{
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "template
						(styleid, templatetype, title, template, template_un, dateline, username, version, product)
					VALUES
						" . implode(',', $querybits) . "
				");
				$querybits = array();
			}

			// Send some output to the browser inside this loop so certain hosts
			// don't artificially kill the script. See bug #34585
			if (VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
			{
				echo ' ';
				vbflush();
			}
		}

		// insert any remaining templates
		if (!empty($querybits))
		{
			/*insert query*/
			$db->query_write("
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
	if (is_array($arr['stylevardfns']['stylevargroup']))
	{
		xml_import_stylevar_definitions($arr['stylevardfns'], $info['productid']);
	}

	if (is_array($arr['stylevars']['stylevar']))
	{
		xml_import_stylevars($arr['stylevars'], -1);
	}

	// ############## import hooks/plugins
	if (is_array($arr['plugins']['plugin']))
	{
		$plugins =& $arr['plugins']['plugin'];
		if (!isset($plugins[0]))
		{
			$plugins = array($plugins);
		}

		foreach ($plugins AS $plugin)
		{
			$plugin['product'] = $info['productid'];
			unset($plugin['devkey']);

			$db->query_write(fetch_query_sql($plugin, 'plugin'));
		}

		$rebuild['plugins'] = true;
	}

	// ############## import phrases
	if (is_array($arr['phrases']['phrasetype']))
	{
		require_once(DIR . '/includes/adminfunctions_language.php');

		$master_phrasetypes = array();
		$master_phrasefields = array();
		foreach(fetch_phrasetypes_array(false) as $phrasetype)
		{
			$master_phrasefields["$phrasetype[fieldname]"] = true;
		}

		$phrasetypes =& $arr['phrases']['phrasetype'];
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
				$db->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
						(fieldname, title, editrows, product)
					VALUES
						('" . $db->escape_string($phrasetype['fieldname']) . "',
						'" . $db->escape_string($phrasetype['name']) . "',
						3,
						'" . $db->escape_string($info['productid']) . "')
				");

				// need to add the column to the language table as well
				require_once(DIR . '/includes/class_dbalter.php');

				$db_alter = new vB_Database_Alter_MySQL($db);
				if ($db_alter->fetch_table_info('language'))
				{
					$db_alter->add_field(array(
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
					'" . $db->escape_string($phrasetype['fieldname']) . "',
					'" . $db->escape_string($phrase['name']) . "',
					'" . $db->escape_string($phrase['value']) . "',
					'" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($phrase['username']) . "',
					" . intval($phrase['date']) . ",
					'" . $db->escape_string($phrase['version']) . "')
				";
			}

			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					" . implode(',', $sql)
			);
		}

		$rebuild['phrases'] = true;
	}

	// ############## import settings
	if (is_array($arr['options']['settinggroup']))
	{
		$settinggroups =& $arr['options']['settinggroup'];
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
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "settinggroup
					(grouptitle, displayorder, volatile, product)
				VALUES
					('" . $db->escape_string($group['name']) . "',
					" . intval($group['displayorder']) . ",
					1,
					'" . $db->escape_string($info['productid']) . "')
			");

			$settings =& $group['setting'];
			if (!isset($settings[0]))
			{
				$settings = array($settings);
			}

			$setting_bits = array();

			foreach ($settings AS $setting)
			{
				if (isset($vbulletin->options["$setting[varname]"]))
				{
					$newvalue = $vbulletin->options["$setting[varname]"];
				}
				else
				{
					$newvalue = $setting['defaultvalue'];
				}

				$setting_bits[] = "(
					'" . $db->escape_string($setting['varname']) . "',
					'" . $db->escape_string($group['name']) . "',
					'" . $db->escape_string(trim($newvalue)) . "',
					'" . $db->escape_string(trim($setting['defaultvalue'])) . "',
					'" . $db->escape_string(trim($setting['datatype'])) . "',
					'" . $db->escape_string($setting['optioncode']) . "',
					" . intval($setting['displayorder']) . ",
					" . intval($setting['advanced']) . ",
					1,
					'" . $db->escape_string($setting['validationcode']) . "',
					" . intval($setting['blacklist']) . ",
					'" . $db->escape_string($info['productid']) . "'\n\t)";
			}

			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "setting
					(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile, validationcode, blacklist, product)
				VALUES
					" . implode(",\n\t", $setting_bits)
			);
		}

		$rebuild['options'] = true;
	}

	// ############## import admin help
	if (is_array($arr['helptopics']['helpscript']))
	{
		$help_scripts =& $arr['helptopics']['helpscript'];
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
					('" . $db->escape_string($help_script['name']) . "',
					'" . $db->escape_string($topic['act']) . "',
					'" . $db->escape_string($topic['opt']) . "',
					" . intval($topic['disp']) . ",
					1,
					'" . $db->escape_string($info['productid']) . "')
				";
			}

			if (!empty($helpsql))
			{
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "adminhelp
						(script, action, optionname, displayorder, volatile, product)
					VALUES
						" . implode(",\n\t", $helpsql)
				);
			}
		}
	}

	// ############## import cron
	if (is_array($arr['cronentries']['cron']))
	{
		require_once(DIR . '/includes/functions_cron.php');

		$cron_entries =& $arr['cronentries']['cron'];
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
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "cron
					(weekday, day, hour, minute, filename, loglevel, active, varname, volatile, product)
				VALUES
					($scheduling[weekday],
					$scheduling[day],
					$scheduling[hour],
					'" . $db->escape_string(serialize($scheduling['minute'])) . "',
					'" . $db->escape_string($cron['filename']) . "',
					$cron[loglevel],
					$cron[active],
					'" . $db->escape_string($cron['varname']) . "',
					1,
					'" . $db->escape_string($info['productid']) . "')
			");
			$cronid = $db->insert_id(); // replace either inserts, or deletes+inserts
			if ($cronid)
			{
				build_cron_item($cronid);
			}

			$rebuild['cron'] = true;
		}
	}

	// ############## import faq
	if (is_array($arr['faqentries']['faq']))
	{
		$faq_entries =& $arr['faqentries']['faq'];
		if (!isset($faq_entries[0]))
		{
			$faq_entries = array($faq_entries);
		}

		$sql = array();
		foreach ($faq_entries AS $faq)
		{
			$sql[] = "
				('" . $db->escape_string($faq['faqname']) . "',
				'" . $db->escape_string($faq['faqparent']) . "',
				" . intval($faq['displayorder']) . ",
				1,
				'" . $db->escape_string($info['productid']) . "')
			";
		}

		if ($sql)
		{
			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "faq
					(faqname, faqparent, displayorder, volatile, product)
				VALUES
					" . implode(',', $sql) . "
			");
		}
	}

	// Check if the plugin system is disabled. If it is, enable it.
	if (!$vbulletin->options['enablehooks'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting
			SET value = '1'
			WHERE varname = 'enablehooks'
		");

		$rebuild['options'] = true;
	}

	// Now rebuild everything we need...
	if ($rebuild['plugins'])
	{
		vBulletinHook::build_datastore($db);

		if ($active)
		{
			$plugin_data = $db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "datastore
				WHERE title IN ('pluginlist', 'pluginlistadmin')
			");
			while ($plugin_info = $db->fetch_array($plugin_data))
			{
				if ($plugin_info['title'] == 'pluginlist')
				{
					$vbulletin->pluginlist = unserialize($plugin_info['data']);
				}
				else if ($plugin_info['title'] == 'pluginlistadmin')
				{
					$vbulletin->pluginlistadmin = unserialize($plugin_info['data']);
				}
			}

			// enable any hooks -- this is mainly necessary for importing templates (template_safe_functions hook)
			if (!defined('DISABLE_HOOKS'))
			{
				if (!empty($vbulletin->pluginlistadmin) AND is_array($vbulletin->pluginlistadmin))
				{
					$vbulletin->pluginlist = array_merge($vbulletin->pluginlist, $vbulletin->pluginlistadmin);
					unset($vbulletin->pluginlistadmin);
				}
				vBulletinHook::set_pluginlist($vbulletin->pluginlist);
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
	vB_Bitfield_Builder::save($db);

	// reload block types
	$blockmanager = vB_BlockManager::create($vbulletin);
	$blockmanager->reloadBlockTypes();

	print_dots_stop();

	$info['need_merge'] = ($rebuild['templates'] AND $installed_version);
	return $info;
}

/**
* Used to do sorting of version number strings via usort().
* adminfunctions_template.php must be required before you use this!
*
* @param	string	Version number string 1
* @param	string	Version number string 2
*
* @return	integer	0 if the same, -1 if #1 < #2, 1 if #1 > #2
*/
function version_sort($a, $b)
{
	if ($a == $b)
	{
		return 0;
	}
	else if ($a == '*')
	{
		// * < non-*
		return -1;
	}
	else if ($b == '*')
	{
		// any non-* > *
		return 1;
	}

	return (is_newer_version($a, $b) ? 1 : -1);
}

/**
* Fetches an array of products dependent on a specific product, though whether
* this is a parent-child or child-parent relationship is determined based on
* the construction of the dependency list.
* If the parent is the key, this function will recursively find a list of children.
* If the child is the key, the function will recursively find a list of parents.
*
* @param	string	Product to find parents/children for
* @param	array	An array of dependencies to pull from in form [pid][] => pid
*
* @return	array	Array of children/parents
*/
function fetch_product_dependencies($productid, &$dependency_list)
{
	if (!is_array($dependency_list["$productid"]))
	{
		return array();
	}

	$list = array();
	foreach ($dependency_list["$productid"] AS $subproductid)
	{
		// only traverse this branch if we haven't done it before -- prevent infinte recursion
		if (!isset($list["$subproductid"]))
		{
			$list["$subproductid"] = $subproductid;
			$list = array_merge(
				$list,
				fetch_product_dependencies($subproductid, $dependency_list)
			);
		}
	}

	return $list;
}

/**
*	Setup the default permissions for the product.
*
*	This is primarily intended for vbsuite products and should be called from the
* the package install code.  It sets the default admincp permissions for the
* package.
*
*	@permission_field The field in the administrator table that stores the product permissions
* @permissions The permission bit field for the default.
* @userid The id of the logged in user -- if given the user will be granted access in addtion
* 	to administrators with plugin access.
*
*/
function setup_default_admin_permissions($permission_field, $permissions, $userid = null)
{
	global $vbulletin;

	$user = array();
	if ($userid)
	{
		$user = $vbulletin->db->query_first("
			SELECT administrator.*, IF(administrator.userid IS NULL, 0, 1) AS isadministrator,
				user.userid, user.username
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON(administrator.userid = user.userid)
			WHERE user.userid = " . intval($userid)
		);
	}

	//if we have a user logged in on the install, then give them admin rights to the product.
	if ($user)
	{
		if (!$user['isadministrator'])
		{
			// should this user have an administrator record??
			$userinfo = fetch_userinfo($user['userid']);
			cache_permissions($userinfo);
			if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$admindm =& datamanager_init('Admin', $vbulletin, ERRTYPE_SILENT);
				$admindm->set('userid', $userinfo['userid']);
				$admindm->save();
				unset($admindm);
			}
		}

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "administrator SET
				$permission_field = " . intval($permissions) . "
			WHERE userid = $user[userid]
		");
	}

	//otherwise, lets give access to anybody set up so they can install it.
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "administrator SET
			$permission_field = " . intval($permissions) . "
		WHERE adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['canadminplugins']
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 40651 $
|| ####################################################################
\*======================================================================*/
