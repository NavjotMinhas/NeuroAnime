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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 42666 $');
define('NOZIP', true);

@ini_set('display_errors', true);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('style');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_stylevar.php');
require_once(DIR . '/includes/adminfunctions_template.php');
require_once(DIR . '/includes/class_stylevar.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminstyles'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'dostyleid'    => TYPE_INT,
));


// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['dostyleid'] != 0, "style id = " . $vbulletin->GPC['dostyleid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	// If not told to do anything, list the stylevars for edit
	$_REQUEST['do'] = 'modify';
}

if (empty($vbulletin->GPC['dostyleid']))
{
	$vbulletin->GPC['dostyleid'] = ($vbulletin->debug ? -1 : $vbulletin->options['styleid']);
}

if ($vbulletin->GPC['dostyleid'] == -1)
{
	$styleinfo = array(
		'styleid' => -1,
		'title'   => 'MASTER STYLE'
	);
}
else
{
	$styleinfo = $db->query_first("SELECT styleid, title FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']);
	if (empty($styleinfo))
	{
		print_stop_message('invalid_style_specified');
	}
}

$skip_wrappers = array(
	'fetchstylevareditor'
);

if (in_array($_REQUEST['do'], $skip_wrappers))
{
	define('NO_PAGE_TITLE', true);
}

print_cp_header($vbphrase['stylevareditor']);


function construct_stylevar_form($title, $stylevarid, $values, $styleid)
{
	global $vbulletin, $stylecache;

	cache_styles();

	$editstyleid = $styleid;

	if (isset($values[$stylevarid][$styleid]))
	{
		// customized or master
		if ($styleid == -1)
		{
			// master
			$hide_revert = true;
		}
	}
	else
	{
		// inherited
		while (!isset($values[$stylevarid][$styleid]))
		{
			$styleid = $stylecache[$styleid]['parentid'];
			if (!isset($stylecache[$styleid]) AND $styleid != -1)
			{
				trigger_error('Invalid style in tree: ' . $styleid, E_USER_ERROR);
				break;
			}
		}
		$hide_revert = true;
	}

	$stylevar = $values[$stylevarid][$styleid];

	$hide_revert = ($hide_revert ? 'hide_revert' : '');

	if ($stylevar['value'] == '')
	{
		// blank for value? use fall back
		$stylevar['value'] = $stylevar['failsafe'];
	}

	$svinstance = vB_StyleVar_factory::create($stylevar['datatype']);
	$svinstance->set_stylevarid($stylevarid);
	$svinstance->set_definition($stylevar);
	$svinstance->set_value(unserialize($stylevar['value']));	// remember, our value in db is ALWAYS serialized!

	if ($stylevar['stylevarstyleid'] == -1)
	{
		$svinstance->set_inherited(0);
	}
	else if ($stylevar['stylevarstyleid'] == $vbulletin->GPC['dostyleid'])
	{
		$svinstance->set_inherited(-1);
	}
	else
	{
		$svinstance->set_inherited(1);
	}

	$editor = $svinstance->print_editor();
	return $editor;
}

// ########################################################################
if ($_REQUEST['do'] == 'dfnadd' OR $_REQUEST['do'] == 'dfnedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'stylevarid' => TYPE_STR,
	));

	if ($vbulletin->GPC['stylevarid'])
	{
		// we have $vbulletin->GPC['stylevarid'] and $vbulletin->GPC['dostyleid'] from above
		$stylevar = $db->query_first("
			SELECT * FROM " .  TABLE_PREFIX . "stylevardfn
			WHERE
				stylevarid = '" . $db->escape_string($vbulletin->GPC['stylevarid']) . "'
					AND
				styleid = " . $vbulletin->GPC['dostyleid']
		);

		if (!empty($stylevar))
		{
			// select friendly name for current language
			$svname_result = $db->query_first("
				SELECT text
				FROM " . TABLE_PREFIX . "phrase
				WHERE
					varname = 'stylevar_" . $db->escape_string($vbulletin->GPC['stylevarid']) . "_name'
			");

			if (!empty($svname_result))
			{
				$stylevar['friendlyname'] = $svname_result['text'];
			}

			// select description for current language
			$svdesc_result = $db->query_first("
				SELECT text
				FROM " . TABLE_PREFIX . "phrase
				WHERE
					varname = 'stylevar_" . $db->escape_string($vbulletin->GPC['stylevarid']) . "_description'
			");

			if (!empty($svdesc_result))
			{
				$stylevar['description'] = $svdesc_result['text'];
			}

		}
	}

	// add / editing definition
	print_form_header('stylevar', 'dfn_dosave', 0, 1);
	print_table_header($vbphrase['add_new_stylevar']);
	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $stylevar['product']);
	print_input_row($vbphrase['group'], 'svgroup', $stylevar['stylevargroup']);
	print_input_row($vbphrase['stylevarid'], 'stylevarid', $stylevar['stylevarid']);
	print_input_row($vbphrase['friendly_name'], 'svfriendlyname', $stylevar['friendlyname']);
	print_input_row($vbphrase['description'], 'svdescription', $stylevar['description']);
	// keys match with enum entry that we have, value should be mapped to a vbphrase
	$svtypesarray = array(
		$vbphrase['simple_types'] => array(
			'string'   => $vbphrase['string'],
			'numeric'  => $vbphrase['numeric'],
			'url'      => $vbphrase['url'],
			'path'     => $vbphrase['path'],
			'color'    => $vbphrase['color'],
			'imagedir' => $vbphrase['imagedir'],
			'image'    => $vbphrase['image'],
			'fontlist' => $vbphrase['fontlist'],
			'size'     => $vbphrase['size'],
		),
		$vbphrase['complex_types'] => array(
			'background'     => $vbphrase['background'],
			'font'           => $vbphrase['font'],
			'textdecoration' => $vbphrase['text_decoration'],
			'dimension'      => $vbphrase['dimension'],
			'border'         => $vbphrase['border'],
			'padding'        => $vbphrase['padding'],
			'margin'         => $vbphrase['margin'],
		),
	);
	print_select_row($vbphrase['data_type'], 'svdatatype', $svtypesarray, $stylevar['datatype']);
	print_input_row($vbphrase['validation_regular_expression'] . '<br />' . $vbphrase['validation_re_optional'], 'svvalidation', $stylevar['validation']);
	$svunitsarray = array(
		''   => '',
		'%'  => '%',
		'px' => 'px',
		'pt' => 'pt',
		'em' => 'em',
		'ex' => 'ex',
		'pc' => 'pc',
		'in' => 'in',
		'cm' => 'cm',
		'mm' => 'mm'
	);
//not currently used by anything.
//	print_select_row($vbphrase['units'] . '<br />~~Optional, only used by numerics type, discarded by other datatypes~~', 'svunit', $svunitsarray, $stylevar['units']);
	construct_hidden_code('oldsvid', $stylevar['stylevarid']);
	print_submit_row($vbphrase['save']);
}

// ########################################################################
if ($_POST['do'] == 'dfn_dosave')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'product'        => TYPE_STR,
		'svgroup'        => TYPE_STR,
		'stylevarid'     => TYPE_NOHTML,
		'svfriendlyname' => TYPE_NOHTML,
		'svdescription'  => TYPE_NOHTML,
		'svdatatype'     => TYPE_STR,
		'svvalidation'   => TYPE_STR,
		'svunit'         => TYPE_STR,
		'oldsvid'        => TYPE_STR,
	));

	// MEMO: we are always working with styleid -1 for the definitions as of right now, some time later,
	// this should be removed so the dostyleid is properly respected.
	$vbulletin->GPC['dostyleid'] = -1;

	if (!$vbulletin->GPC['oldsvid'])
	{
		$stylevar_dfn = $db->query_first("
			SELECT * FROM " .  TABLE_PREFIX . "stylevardfn
			WHERE
				stylevarid = '" . $db->escape_string($vbulletin->GPC['stylevarid']) . "'
		");

		if (!empty($stylevar_dfn))
		{
			print_stop_message('stylevar_x_already_exists', $vbulletin->GPC['stylevarid']);
		}
	}

	// stylevars can only begin with a-z or _ as defined by the CSS spec
	if (!preg_match('#^[_a-z][a-z0-9_]*$#i', $vbulletin->GPC['stylevarid']))
	{
		print_stop_message('invalid_stylevar_id');
	}

	if (!preg_match('#^[a-z0-9_]+$#i', $vbulletin->GPC['svgroup']))
	{
		print_stop_message('invalid_group_name');
	}

	$validtypes = array('numeric', 'string', 'color', 'url', 'path', 'background', 'imagedir',
		'fontlist', 'textdecoration', 'dimension', 'border', 'padding', 'margin', 'font', 'size');
	if (!in_array($vbulletin->GPC['svdatatype'], $validtypes))
	{
		// invalid type, map to string type
		$vbulletin->GPC['svdatatype'] = 'string';
	}

	$validunits = array('', '%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');
	if (!in_array($vbulletin->GPC['svunit'], $validunits) OR $vbulletin->GPC['svdatatype'] != "numeric")
	{
		// invalid unit, or does not require unit, strip it
		$vbulletin->GPC['svunit'] = '';
	}

	// time to store it
	$svdfndata = datamanager_init('StyleVarDefn', $vbulletin, ERRTYPE_CP, 'stylevar');

	if ($vbulletin->GPC['oldsvid'])
	{
		$existing = array('stylevarid' => $vbulletin->GPC['oldsvid']);
		$svdfndata->set_existing($existing);

		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
		{
			$stylevar_dfn = $db->query_first("
				SELECT product FROM " .  TABLE_PREFIX . "stylevardfn
				WHERE
					stylevarid = '" . $db->escape_string($vbulletin->GPC['oldsvid']) . "'
			");
		}
	}

	$svdfndata->set('product', $vbulletin->GPC['product']);
	$svdfndata->set('stylevargroup', $vbulletin->GPC['svgroup']);
	$svdfndata->set('stylevarid', $vbulletin->GPC['stylevarid']);
	$svdfndata->set('styleid', $vbulletin->GPC['dostyleid']);

	$svdfndata->set('parentid', 0);			// change this later to match the parent style
	$svdfndata->set('parentlist', '0,-1');	// change this later to match the parent list
	$svdfndata->set('datatype', $vbulletin->GPC['svdatatype']);

	// check regular expression
	if (!empty($vbulletin->GPC['svvalidation']))
	{
		if (preg_match('#' . str_replace('#', '\#', $vbulletin->GPC['svvalidation']) . '#siU', '') === false)
		{
			print_stop_message('regular_expression_is_invalid');
		}
		$svdfndata->set('validation', $vbulletin->GPC['svvalidation']);
	}
	$svdfndata->set('units', $vbulletin->GPC['svunit']);
	$svdfndata->set('uneditable', false);
	$svdfndata->save();

	$dfnid = $vbulletin->GPC['stylevarid'];

	// insert the friendly name into phrase
	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, varname, text, product, fieldname, username, dateline, version)
		VALUES (
			-1,
			'stylevar_{$dfnid}_name',
			'" . $vbulletin->db->escape_string($vbulletin->GPC['svfriendlyname']) . "',
			'" . $vbulletin->db->escape_string($vbulletin->GPC['product']) . "',
			'style',
			'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
			" . TIMENOW . ",
			'" . $vbulletin->db->escape_string($vbulletin->options['templateversion']) . "'
		)
	");

	// insert the description into phrase
	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, varname, text, product, fieldname, username, dateline, version)
		VALUES (
			-1,
			'stylevar_{$dfnid}_description',
			'" . $vbulletin->db->escape_string($vbulletin->GPC['svdescription']) . "',
			'" . $vbulletin->db->escape_string($vbulletin->GPC['product']) . "',
			'style',
			'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
			" . TIMENOW . ",
			'" . $vbulletin->db->escape_string($vbulletin->options['templateversion']) . "'
		)
	");

	// rebuild languages
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language(-1);

	// a null value, so no need to customize or anything
	$stylevardata = datamanager_init('StyleVar', $vbulletin, ERRTYPE_CP, 'stylevar');
	if ($vbulletin->GPC['oldsvid'])
	{
		$existing = array('stylevarid' => $vbulletin->GPC['oldsvid']);
		$stylevardata->set_existing($existing);
	}
	$stylevardata->set('stylevarid', $vbulletin->GPC['stylevarid']);
	$stylevardata->set('styleid', $vbulletin->GPC['dostyleid']);
	$stylevardata->build();
	$stylevardata->save();

	if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
	{
		require_once(DIR . '/includes/functions_filesystemxml.php');
		autoexport_write_style_and_language($vbulletin->GPC['dostyleid'],
			array($stylevar_dfn['product'], $vbulletin->GPC['product']));
	}

	define('CP_REDIRECT', 'stylevar.php?do=fetchstylevareditor&dostyleid=' . $vbulletin->GPC['dostyleid'] . '&stylevarid[]=' . $vbulletin->GPC['stylevarid']);
	print_stop_message('saved_stylevardfn_x_successfully', $vbulletin->GPC['stylevarid']);
}

// ########################################################################
if ($_REQUEST['do'] == 'confirmrevert')
{
	// confirm whether or not user wants to revert that particular stylevar
	$vbulletin->input->clean_array_gpc('r', array(
		'stylevarid' => TYPE_STR,
		'rootstyle'  => TYPE_INT,
	));

	$hidden = array();
	$hidden['dostyleid'] = $vbulletin->GPC['dostyleid'];

	print_delete_confirmation('stylevar', $vbulletin->GPC['stylevarid'], 'stylevar', 'dorevert', 'stylevar',
		$hidden, $vbphrase['please_be_aware_stylevar_is_inherited'], $vbulletin->GPC['rootstyle']);
}

// ########################################################################
if ($_POST['do'] == 'dorevert')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'stylevarid' => TYPE_STR,
		'dostyleid'  => TYPE_INT
	));

	if ($vbulletin->GPC['dostyleid'] == -1)
	{
		//Changing this to grab the dfn information.  We only grab this for the product
		//which isn't stored in the stylevar table in the first place.  Not strickly speaking
		//a filesystem xml change, but an obvious bug.
		$stylevarinfo = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "stylevardfn
			WHERE
				stylevarid = '" . $vbulletin->db->escape_string($vbulletin->GPC['stylevarid']) . "'
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "stylevar
			WHERE
				stylevarid = '" . $vbulletin->db->escape_string($vbulletin->GPC['stylevarid']) . "'
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "stylevardfn
			WHERE
				stylevarid = '" . $db->escape_string($vbulletin->GPC['stylevarid']) . "'
		");

		if (!$stylevarinfo['product'])
		{
			$product = array('', 'vbulletin');
		}
		else
		{
			$product = array($stylevarinfo['product']);
		}

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE
				varname IN ('stylevar_" . $vbulletin->db->escape_string($vbulletin->GPC['stylevarid']) . "_name', 'stylevar_" . $vbulletin->db->escape_string($vbulletin->GPC['stylevarid']) . "_description')
					AND
				fieldname = 'style'
					AND
				product IN ('" . implode("','", $product) . "')
		");

		// rebuild languages
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language(-1);

		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
		{
			require_once(DIR . '/includes/functions_filesystemxml.php');
			autoexport_write_style($vbulletin->GPC['dostyleid'], $stylevarinfo['product']);
		}

		print_rebuild_style($vbulletin->GPC['dostyleid']);
		define('CP_REDIRECT', 'stylevar.php?dostyleid=' . $vbulletin->GPC['dostyleid']);
		print_stop_message('reverted_stylevar_x_successfully', $vbulletin->GPC['stylevarid']);
	}
	else
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "stylevar
			WHERE
				stylevarid = '" . $vbulletin->db->escape_string($vbulletin->GPC['stylevarid']) . "'
					AND
				styleid = " . intval($vbulletin->GPC['dostyleid']) . "
		");

		print_rebuild_style($vbulletin->GPC['dostyleid']);
		define('CP_REDIRECT', 'stylevar.php?dostyleid=' . $vbulletin->GPC['dostyleid'] . '&do=fetchstylevareditor&stylevarid[]=' . $vbulletin->GPC['stylevarid']);
		print_stop_message('reverted_stylevar_x_successfully', $vbulletin->GPC['stylevarid']);
	}
}

// ########################################################################
if ($_POST['do'] == 'savestylevar')
{
	// $_POST['stylevar'] is an array of one or more stylevars to save. The key is the
	// stylevarid (a string) and the value is either an array of stylevar data or a
	// string of stylevar data. Since we can save multiple stylevars at the same time,
	// we can have a mix of array and string data, thus we cannot clean this
	// as TYPE_ARRAY_ARRAY or as TYPE_ARRAY_STR.
	// TODO: Explore Refactoring the stylevar system to store all stylevar data as
	// arrays and never as strings, as is the case with the imagedir, string, numeric,
	// url, path, and fontlist stylevars.

	$vbulletin->input->clean_array_gpc('p', array(
		'stylevar' => TYPE_ARRAY,
	));

	// ensure that the data contained in $vbulletin->GPC['stylevar'] is the expected format </paranoia>
	$stylevar_data = $vbulletin->GPC['stylevar'];
	$vbulletin->GPC['stylevar'] = array();
	foreach ($stylevar_data AS $stylevar_data_key => $stylevar_data_value)
	{
		$stylevar_data_key = strval($stylevar_data_key);

		if (is_array($stylevar_data_value))
		{
			// stylevars whose data is stored as an array
			$stylevar_data_value_new = array();
			foreach ($stylevar_data_value AS $stylevar_data_value_k => $stylevar_data_value_v)
			{
				$stylevar_data_value_new[strval($stylevar_data_value_k)] = strval($stylevar_data_value_v);
			}
			$stylevar_data_value = $stylevar_data_value_new;
		}
		else
		{
			// stylevars whose data is stored as a string
			$stylevar_data_value = strval($stylevar_data_value);
		}

		$vbulletin->GPC['stylevar'][$stylevar_data_key] = $stylevar_data_value;
	}
	unset($stylevar_data, $stylevar_data_key, $stylevar_data_value, $stylevar_data_value_k, $stylevar_data_value_v, $stylevar_data_value_new);

	// get the submitted stylevars
	$stylevarids = array_keys($vbulletin->GPC['stylevar']);
	$stylevarids_sql = "'" . implode("', '", array_map(array(&$db, 'escape_string'), $stylevarids)) . "'";

	// get the existing stylevar values
	$stylevars_result = $db->query_read("
		SELECT stylevardfn.*, stylevar.styleid AS stylevarstyleid, stylevar.value
		FROM " . TABLE_PREFIX . "stylevardfn AS stylevardfn
		LEFT JOIN " . TABLE_PREFIX . "stylevar AS stylevar ON(stylevardfn.stylevarid = stylevar.stylevarid)
		WHERE stylevardfn.stylevarid IN (" . $stylevarids_sql . ")
		ORDER BY stylevardfn.stylevargroup, stylevardfn.stylevarid
	");

	$stylevars = array();
	while ($sv = $vbulletin->db->fetch_array($stylevars_result))
	{
		$stylevars[$sv['stylevarid']][$sv['stylevarstyleid']] = $sv;
	}
	$vbulletin->db->free_result($stylevars_result);

	print_form_header('stylevar', 'savestylevar');
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);

	// check if the stylevar was changed
	$updated_stylevars = array();
	foreach ($vbulletin->GPC['stylevar'] AS $stylevarid => $value)
	{
		$styleid = $vbulletin->GPC['dostyleid'];

		if (isset($stylevars[$stylevarid][$styleid]))
		{
			$original_value = unserialize($stylevars[$stylevarid][$styleid]['value']);
		}
		else
		{
			// get inherited value
			while (!isset($stylevars[$stylevarid][$styleid]))
			{
				$styleid = $stylecache[$styleid]['parentid'];
				if (!isset($stylecache[$styleid]))
				{
					$styleid = -1;
					break;
				}
			}

			if (!isset($stylevars[$stylevarid][$styleid]))
			{
				$updated_stylevars[] = $stylevarid;
				continue;
			}

			$original_value = unserialize($stylevars[$stylevarid][$styleid]['value']);
		}

		if (is_array($value))
		{
			// submitted value may have keys that are undefined in the original value
			foreach ($value AS $key => $element)
			{
				if (!isset($original_value[$key]))
				{
					if ($element !== '')
					{
						// we already know the value is different
						$updated_stylevars[] = $stylevarid;
						break;
					}

					// set the key on the original for fair comparison
					$original_value[$key] = $element;
				}
			}

			// submitted value may be missing keys from the original value
			//$value = array_merge($original_value, $value);

			// ksort values for fair comparison
			ksort($original_value);
			ksort($value);
		}
		else
		{
			// convert original value to string for fair comparison
			$original_value = current($original_value);
		}

		// if value has changed, mark for saving
		if ($original_value != $value AND !in_array($stylevarid, $updated_stylevars))
		{
			$updated_stylevars[] = $stylevarid;
		}
	}

	// save changes
	if (count($updated_stylevars))
	{
		$stylevarid_list = "'" . implode("', '", array_map(array(&$db, 'escape_string'), $updated_stylevars)) . "'";

		$existing_result = $db->query_read("
			SELECT stylevarid FROM " . TABLE_PREFIX . "stylevar
			WHERE
				styleid = " . intval($vbulletin->GPC['dostyleid']) . "
					AND
				stylevarid IN (" . $stylevarid_list . ")
		");

		$updating = array();
		while($existing = $db->fetch_array($existing_result))
		{
			$updating[] = $existing['stylevarid'];
		}

		$existing_dfns = $db->query_read("
			SELECT * FROM " . TABLE_PREFIX . "stylevardfn
			WHERE
				stylevarid IN (" . $stylevarid_list . ")
		");

		$dfns = array();
		while($dfn = $db->fetch_array($existing_dfns))
		{
			$dfns[$dfn['stylevarid']] = $dfn;
		}

		// actually manage the data
		foreach ($updated_stylevars AS $stylevarid)
		{
			$svinstance = datamanager_init('StyleVar' . $dfns[$stylevarid]['datatype'], $vbulletin, ERRTYPE_CP, 'stylevar');

			if (in_array($stylevarid, $updating))
			{
				$svexisting = array('stylevarid' => $stylevarid, 'styleid' => $vbulletin->GPC['dostyleid']);
				$svinstance->set_existing($svexisting);
			}
			else
			{
				$svinstance->set('stylevarid', $stylevarid);
				$svinstance->set('styleid', $vbulletin->GPC['dostyleid']);
			}
			$svinstance->set('username', $vbulletin->userinfo['username']);

			if (is_array($vbulletin->GPC['stylevar'][$stylevarid]) AND isset($vbulletin->GPC['stylevar'][$stylevarid]['units']))
			{
				$svinstance->set_child('units', $vbulletin->GPC['stylevar'][$stylevarid]['units']);
			}

			switch ($dfns[$stylevarid]['datatype'])
			{
				case 'background':
					$svinstance->set_child('color', $vbulletin->GPC['stylevar'][$stylevarid]['color']);
					$svinstance->set_child('image', $vbulletin->GPC['stylevar'][$stylevarid]['image']);
					$svinstance->set_child('repeat', $vbulletin->GPC['stylevar'][$stylevarid]['repeat']);
					$svinstance->set_child('units', $vbulletin->GPC['stylevar'][$stylevarid]['units']);
					$svinstance->set_child('x', $vbulletin->GPC['stylevar'][$stylevarid]['x']);
					$svinstance->set_child('y', $vbulletin->GPC['stylevar'][$stylevarid]['y']);
					break;

				case 'textdecoration':
					$svinstance->set_child('none', $vbulletin->GPC['stylevar'][$stylevarid]['none']);
					$svinstance->set_child('underline', $vbulletin->GPC['stylevar'][$stylevarid]['underline']);
					$svinstance->set_child('overline', $vbulletin->GPC['stylevar'][$stylevarid]['overline']);
					$svinstance->set_child('line-through', $vbulletin->GPC['stylevar'][$stylevarid]['line-through']);
					$svinstance->set_child('blink', $vbulletin->GPC['stylevar'][$stylevarid]['blink']);
					break;

				case 'font':
					$svinstance->set_child('family', $vbulletin->GPC['stylevar'][$stylevarid]['family']);
					$svinstance->set_child('size', $vbulletin->GPC['stylevar'][$stylevarid]['size']);
					$svinstance->set_child('weight', $vbulletin->GPC['stylevar'][$stylevarid]['weight']);
					$svinstance->set_child('style', $vbulletin->GPC['stylevar'][$stylevarid]['style']);
					$svinstance->set_child('variant', $vbulletin->GPC['stylevar'][$stylevarid]['variant']);
					break;

				case 'imagedir':
					$svinstance->set_child('imagedir', $vbulletin->GPC['stylevar'][$stylevarid]);
					break;

				case 'string':
					$svinstance->set_child('string', $vbulletin->GPC['stylevar'][$stylevarid]);
					break;

				case 'numeric':
					$svinstance->set_child('numeric', $vbulletin->GPC['stylevar'][$stylevarid]);
					break;

				case 'url':
					$svinstance->set_child('url', $vbulletin->GPC['stylevar'][$stylevarid]);
					break;

				case 'path':
					$svinstance->set_child('path', $vbulletin->GPC['stylevar'][$stylevarid]);
					break;

				case 'fontlist':
					$svinstance->set_child('fontlist', $vbulletin->GPC['stylevar'][$stylevarid]);
					break;

				case 'color':
					$svinstance->set_child('color', $vbulletin->GPC['stylevar'][$stylevarid]['color']);
					break;

				case 'size':
					$svinstance->set_child('size', $vbulletin->GPC['stylevar'][$stylevarid]['size']);
					break;

				case 'border':
					$svinstance->set_child('width', $vbulletin->GPC['stylevar'][$stylevarid]['width']);
					$svinstance->set_child('style', $vbulletin->GPC['stylevar'][$stylevarid]['style']);
					$svinstance->set_child('color', $vbulletin->GPC['stylevar'][$stylevarid]['color']);
					break;

				case 'dimension':
					$svinstance->set_child('width', $vbulletin->GPC['stylevar'][$stylevarid]['width']);
					$svinstance->set_child('height', $vbulletin->GPC['stylevar'][$stylevarid]['height']);
					break;

				case 'padding':
				case 'margin':
					$svinstance->set_child('top', $vbulletin->GPC['stylevar'][$stylevarid]['top']);
					$svinstance->set_child('right', $vbulletin->GPC['stylevar'][$stylevarid]['right']);
					$svinstance->set_child('bottom', $vbulletin->GPC['stylevar'][$stylevarid]['bottom']);
					$svinstance->set_child('left', $vbulletin->GPC['stylevar'][$stylevarid]['left']);
					$svinstance->set_child('same', $vbulletin->GPC['stylevar'][$stylevarid]['same']);
					break;

				default:
					die("Failed to find " . $dfns[$stylevarid]['datatype']);
					// attempt to set the simple types as is, might be glitchy...
					$svinstance->set_child($dfns[$stylevarid]['datatype'], $vbulletin->GPC['stylevar'][$stylevarid]);
					break;
			}
			$svinstance->build();
			$svinstance->save();
		}

		if (defined('DEV_AUTOEXPORT') AND DEV_AUTOEXPORT)
		{
			//we might have done something strange and selected stylvars from different products.
			$products = array();
			foreach ($dfns AS $dfn)
			{
				$products[$dfn['product']] = 1;
			}
			$products = array_keys($products);
			foreach($products AS $product)
			{
				require_once(DIR . '/includes/functions_filesystemxml.php');
				autoexport_write_style($vbulletin->GPC['dostyleid'], $product);
			}
		}
	}

	foreach (array_keys($vbulletin->GPC['stylevar']) AS $stylevar)
	{
		$stylevars[] = 'stylevarid[]=' . $stylevar;
	}

	print_rebuild_style($vbulletin->GPC['dostyleid']);

	define('CP_REDIRECT', 'stylevar.php?do=fetchstylevareditor&dostyleid=' . $vbulletin->GPC['dostyleid'] . '&' . implode('&', $stylevars));
	print_stop_message('stylevar_saved_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'fetchstylevareditor')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'stylevarid' => TYPE_ARRAY_NOHTML
	));

	if (count($vbulletin->GPC['stylevarid']) == 0)
	{
		print_stop_message('invalidid', 'stylevarid');
	}
	else
	{
		cache_styles();
		$stylevarids = $vbulletin->GPC['stylevarid'];
	}

	$stylevarids_sql = "'" . implode("', '", array_map(array(&$db, 'escape_string'), $stylevarids)) . "'";

	$stylevars_result = $db->query_read("
		SELECT stylevardfn.*, stylevar.styleid AS stylevarstyleid, stylevar.value
		FROM " . TABLE_PREFIX . "stylevardfn AS stylevardfn
		LEFT JOIN " . TABLE_PREFIX . "stylevar AS stylevar ON(stylevardfn.stylevarid = stylevar.stylevarid)
		WHERE stylevardfn.stylevarid IN (" . $stylevarids_sql . ")
		ORDER BY stylevardfn.stylevargroup, stylevardfn.stylevarid
	");

	while ($sv = $vbulletin->db->fetch_array($stylevars_result))
	{
		$stylevars[$sv['stylevargroup']][$sv['stylevarid']][$sv['stylevarstyleid']] = $sv;
	}
	$vbulletin->db->free_result($stylevars_result);

	print_form_header('stylevar', 'savestylevar', false, true, 'cpform', '95%');
	construct_hidden_code('dostyleid', $vbulletin->GPC['dostyleid']);

	// for each result record
	foreach($stylevars AS $stylevargroup_name => $stylevargroup)
	{
		$editor .= "<h2>$stylevargroup_name</h2>";

		foreach($stylevargroup AS $stylevarid => $stylevar_style)
		{
			$editor .= construct_stylevar_form($stylevarid, $stylevarid, $stylevargroup, $vbulletin->GPC['dostyleid']);
		}
	}

	echo $editor;
	print_submit_row($vbphrase['save']);

	echo '<script type="text/javascript">
		<!--
		vBulletin_init();
		-->
		</script>
	';

}

// ########################################################################
if ($_POST['do'] == 'doconvertvb3tovb4')
{
	$style = $db->query_first("
		SELECT styleid
		FROM " . TABLE_PREFIX . "style
		WHERE styleid = " . intval($vbulletin->GPC['dostyleid'])
	);
	if (!$style)
	{
		print_stop_message('invalid_style_specified');
	}

	if (function_exists('set_time_limit') AND !SAFEMODE)
	{
		@set_time_limit(1200);
	}

	require_once(DIR . '/includes/class_stylevarmap_vb3tovb4.php');

	$stylevarmap = new vB_StyleVarMap_vB3tovB4($vbulletin);
	$stylevarmap->set_option('output_progress', true);
	$stylevarmap->set_option('create_new_style', true);

	// set these based on user input?
	$stylevarmap->set_option('skip_image_paths', true);
	$stylevarmap->set_option('revert_templates', true);

	$stylevarmap->convert($style['styleid']);
}

// ########################################################################
if ($_REQUEST['do'] == 'convertvb3tovb4')
{
	$style = $db->query_first("
		SELECT styleid, title
		FROM " . TABLE_PREFIX . "style
		WHERE styleid = " . intval($vbulletin->GPC['dostyleid'])
	);
	if (!$style)
	{
		print_stop_message('invalid_style_specified');
	}

	echo '<br /><br />';
	print_form_header('stylevar', 'doconvertvb3tovb4');
	print_table_header($vbphrase['generate_vbulletin_4_style']);
	print_description_row(construct_phrase($vbphrase['generate_vbulletin_4_style_disclaimer'], htmlspecialchars_uni($style['title'])));
	construct_hidden_code('dostyleid', $style['styleid']);
	print_submit_row($vbphrase['generate_vbulletin_4_style'], 0, 2, $vbphrase['cancel']);
}

// ########################################################################
if ($_REQUEST['do'] == 'modify')
{
	// prepend some JS & CSS
$prepend = '<script type="text/javascript">
<!--
function fetch_vars(currentlist)
{
	var hv = YAHOO.util.Dom.get("hide_vars");
	if (hv.checked && currentlist != true)
	{
		// get the vars from the cloned list
		return YAHOO.util.Dom.get("varlistclone").getElementsByTagName("option");
	}
	else
	{
		// get the vars from the current list
		return YAHOO.util.Dom.get("varlist").getElementsByTagName("option");
	}
}

function init(e)
{
	// store text from each variable <option>
	var vars = fetch_vars();
	for (var i = 0; i < vars.length; i++)
	{
		vars[i].title = vars[i].value;
		vars[i].oText = vars[i].firstChild.nodeValue;
	}

	// handle clickable optgroups
	init_optgroup_click();

	// handle option changes
	init_stylevar_click();

	// activate special controls
	init_text_decoration_handler();
	init_margin_padding_handler();

	// create a clone of the stylevar list if it does not exist already
	if (YAHOO.util.Dom.inDocument(YAHOO.util.Dom.get("varlistclone")) == false)
	{
		var vClone = YAHOO.util.Dom.get("varlist").cloneNode(true);
		// change the id of the cloned list
		vClone.id = "varlistclone";
		var cloneplaceholder = YAHOO.util.Dom.get("cloneplaceholder");
		cloneplaceholder.parentNode.appendChild(vClone);
	}
}

function init_stylevar_click()
{
	var selector = YAHOO.util.Dom.get("varlist");
	YAHOO.util.Event.on(selector, "change", handle_stylevar_click);
}


function handle_stylevar_delete(e)
{
	var selector = YAHOO.util.Dom.get("varlist");
	var selected = Array();
	// get selected stylevars
	for (i=0; i<selector.length; i++)
	{
		if (selector.options[i].selected == true)
		{
			selected.push(selector.options[i].value);
		}
	}

	// build request string
	if (selected.length != 0)
	{
		var request_string = "";
		for (i=0; i<selected.length; i++)
		{
			request_string = request_string + "stylevarid=" + selected[i] + "&";
		}
		var url = "stylevar.php?" + SESSIONURL + "securitytoken=" + SECURITYTOKEN + "&adminhash=" + ADMINHASH + "&do=confirmrevert&" + request_string + "dostyleid=" + ' . $vbulletin->GPC['dostyleid'] . ';
		//var editorpane = YAHOO.util.Dom.get("edit_scroller");
		location.href = url;
	}
}

function handle_stylevar_click(e)
{
	var hv = YAHOO.util.Dom.get("hide_vars");
	if (hv.checked)
	{
		// we have to use the cloned list as the real list
		// has no entries when "Hide Variables" is enabled.
		var selector = YAHOO.util.Dom.get("varlistclone");
	}
	else
	{
		var selector = YAHOO.util.Dom.get("varlist");
	}
	var selected = Array();
	// get selected stylevars
	for (var i = 0; i < selector.length; i++)
	{
		if (selector.options[i].selected == true)
		{
			selected.push(selector.options[i].value);
		}
	}
	// build request string
	if (selected.length != 0)
	{
		var request_string = "";
		for (i=0; i<selected.length; i++)
		{
			request_string = request_string + "stylevarid[]=" + selected[i] + "&";
		}
		var url = "stylevar.php?" + SESSIONURL + "securitytoken=" + SECURITYTOKEN + "&adminhash=" + ADMINHASH + "&do=fetchstylevareditor&" + request_string + "dostyleid=" + ' . $vbulletin->GPC['dostyleid'] . ';
		var editorpane = YAHOO.util.Dom.get("edit_scroller");
		editorpane.src = url;
	}
}

function handle_ajax_request(ajax)
{
	// display the form
	var editorpane = YAHOO.util.Dom.get("editor");
	editorpane.innerHTML = ajax.responseText;
}

function handle_ajax_error(ajax)
{
	// notify user
}

function init_optgroup_click()
{
	var optgroups = YAHOO.util.Dom.get("varlist").getElementsByTagName("optgroup");
	for (var i = 0; i < optgroups.length; i++)
	{
		YAHOO.util.Event.on(optgroups[i], "click", handle_optgroup_click);
	}
}

function handle_optgroup_click(e)
{
	var optgroup = YAHOO.util.Event.getTarget(e);
	var hv = YAHOO.util.Dom.get("hide_vars");
	var vars = fetch_vars();
	if (hv.checked)
	{
		// we have to use the cloned list as the real list
		// has no entries when "Hide Variables" is enabled.
		var clonedoptgroups = YAHOO.util.Dom.get("varlistclone").getElementsByTagName("optgroup");
		for (var j = 0; j < clonedoptgroups.length; j++)
		{
			if (clonedoptgroups[j].label == optgroup.label)
			{
				if (clonedoptgroups[j].tagName == "OPTGROUP")
				{
					for (var i = 0; i < vars.length; i++)
					{
						vars[i].selected = (vars[i].parentNode == clonedoptgroups[j] ? "selected" : false);
					}
					handle_stylevar_click(e);
				}
			}
		}
	}
	else
	{
		if (optgroup.tagName == "OPTGROUP")
		{
			for (var i = 0; i < vars.length; i++)
			{
				vars[i].selected = (vars[i].parentNode == optgroup ? "selected" : false);
			}
			handle_stylevar_click(e);
		}
	}
}


function toggle_hide_vars(e)
{
	var hv = YAHOO.util.Dom.get("hide_vars");
	var item;
	var vars = fetch_vars(true);
	if (hv.checked)
	{
		for (var i = 0; i < vars.length; i++)
		{
			item = YAHOO.util.Dom.get(vars[i]);
			if (item != null)
			{
				item.parentNode.removeChild(item);
				i--;
			}
		}
	}
	YAHOO.util.Dom.get("show_customized_vars").disabled = (hv.checked ? "disabled" : "");
	YAHOO.util.Dom.get("show_var_names").disabled = (hv.checked ? "disabled" : "");
}

function toggle_customized_vars(e)
{
	var scv = YAHOO.util.Dom.get("show_customized_vars");
	var item;
	var vars = fetch_vars(true);

	if (scv.checked)
	{
		for (var i = 0; i < vars.length; i++)
		{
			if (YAHOO.util.Dom.hasClass(vars[i], "col-c") == false && YAHOO.util.Dom.hasClass(vars[i], "col-i") == false)
			{
				// hide all style vars that are unchanged in
				// the selected style and in all parent styles
				item = YAHOO.util.Dom.get(vars[i]);
				if (item != null)
				{
					item.parentNode.removeChild(item);
					i--;
				}
			}
		}
	}
}

function toggle_var_names(e)
{
	var property = (YAHOO.util.Dom.get("show_var_names").checked ? "value" : "oText");

	var vars = fetch_vars();
	for (var i = 0; i < vars.length; i++)
	{
		vars[i].firstChild.nodeValue = vars[i][property];
	}
}

function init_text_decoration_handler()
{
	var text_decs = YAHOO.util.Dom.getElementsByClassName("text-decoration", "fieldset", "editor");

	for (var i = 0; i < text_decs.length; i++)
	{
		if (typeof(txtdec_ctrls[text_decs[i].id]) != "object")
		{
			txtdec_ctrls[text_decs[i].id] = new TextDecorationControl(text_decs[i]);
		}
	}
}

function TextDecorationControl(element)
{
	this.id = element.id;
	this.controls = element.getElementsByTagName("input");

	for (var i = 0; i < this.controls.length; i++)
	{
		YAHOO.util.Event.on(this.controls[i], "click", this.handle_click, this, true);
	}
}

TextDecorationControl.prototype.handle_click = function(e)
{
	var target = YAHOO.util.Event.getTarget(e);

	if (target.id == this.id + ".none")
	{
		console.info("Text-Decoration:none");
		for (var i = 0; i < this.controls.length; i++)
		{
			if (this.controls[i].id != this.id + ".none")
			{
				this.controls[i].checked = false;
			}
		}
	}
	else
	{
		console.log("Text-Decoration:(not none)");
		YAHOO.util.Dom.get(this.id + ".none").checked = false;
	}
}

function init_margin_padding_handler()
{
	var bps = YAHOO.util.Dom.getElementsByClassName("margin-padding", "fieldset", "editor");

	for (var i = 0; i < bps.length; i++)
	{
		if (typeof(bp_ctrls[bps[i].id]) != "object")
		{
			bp_ctrls[bps[i].id] = new MarginPaddingControl(bps[i]);
			console.log(bps[i].id);
		}
	}
}

function MarginPaddingControl(element)
{
	this.id = element.id;
	this.same = YAHOO.util.Dom.get(this.id + ".same");
	this.dynamic_elements = new Array("right", "bottom", "left");

	YAHOO.util.Event.on(this.same, "click", this.set_state, this, true);
	YAHOO.util.Event.on(this.id + ".top", "keyup", this.set_state, this, true);

	this.set_state();
}

MarginPaddingControl.prototype.set_state = function()
{
	var value, current_element, i = null;

	value = YAHOO.util.Dom.get(this.id + ".top").value;

	for (i = 0; i < this.dynamic_elements.length; i++)
	{
		current_element = YAHOO.util.Dom.get(this.id + "." + this.dynamic_elements[i]);

		current_element.disabled = (this.same.checked ? "disabled" : "");

		if (this.same.checked)
		{
			current_element.value = value;
		}
	}
}

var txtdec_ctrls = new Object();
var bp_ctrls = new Object();

YAHOO.util.Event.on(window, "load", init);
//-->
</script>
<style type="text/css">
.container {
	background:#DDDDDD;
}
.leftcontrol {
	width:325px;
}

#varlistclone  {
	display:none;
}

#varlist option {
	padding-left:20px;
}

#varlist option.optgroup {
	padding-left:0;
	font-weight:bold;
}

#edit_container {
	position:relative;
}
#edit_scroller {
	width:100%;
	min-height:573px;
	overflow:auto;
	border:inset 2px;
	background:white;
}
#editor {
	padding:0px 10px;
}

td {
	font:11px Verdana, Geneva, sans-serif;
}

fieldset {
	font:11px Verdana, Geneva, sans-serif;
	margin-bottom:10px;
}

legend {
	font:10pt Verdana, Geneva, sans-serif;
}

fieldset > div {
	float:left;
	margin-right:10px;
	margin-bottom:10px;
}

input, select {
	font:11px Verdana, Geneva, sans-serif;
}

label {
	display:block;
}

label:after {
	content:"";
}

input[type="text"], select {
	margin-top:2px;
}

input[type="text"] {
	width:150px;
}

.color input[type="text"] {
	width:100px;
}
.color input[type="button"] {
	background-color:#09F;
	width:25px;
}

.font-size input[type="text"],
.position input[type="text"],
.size input[type="text"],
.margin-padding input[type="text"] {
	width:50px;
	text-align:right;
}

.margin-padding .same {
	clear:both;
	float:none;
}
.margin-padding .same label:after {
	content:none;
}

.text-decoration {
	clear:both;
	float:none;
}

.text-decoration .label:after {
	content:":";
}

.text-decoration label {
	display:inline;
}

.text-decoration label:after {
	content:none;
}
</style>';

	echo $prepend;
	// table wrapper
	echo '
		<table width="100%" align="center" class="container">
			<tr>
				<th colspan="2">
					' . $vbphrase['stylevareditor'] . ' - ' . $styleinfo['title'] . '
				</th>
			</tr>
			<tr valign="top">
				<td>
	';
	// show the search field and the checkboxes
	//TODO redisplay var checkbox when Friendly names are working.  "display:none" allows the element
	//to still exist for js purposes -- otherwise we'll need to remove the references to it in the js
	//to avoid errors.  That's more work now and more work later when we want to reenable it.  The
	//functionality is harmess even in its present state, so it doesn't hurt much to leave it in
	//like this.
	echo '
					<div style="display:none" id="cloneplaceholder"></div>
					<div><input type="text" name="filterbox" id="stylevar_filter" class="filterbox_inactive bginput smallfont" size="20" value="' . $vbphrase['search_stylevar'] . '" title="' . $vbphrase['search_stylevar'] . '" /></div>
					<div><label><input type="checkbox" id="hide_vars" />' . $vbphrase['hide_variables'] . '</label></div>
					<div><label><input type="checkbox" id="show_customized_vars" />' . $vbphrase['show_customized_variables'] . '</label></div>
					<div style="display:none"><label><input type="checkbox" id="show_var_names" />' . $vbphrase['show_variable_names'] . '</label></div>
		';

	// show the form for the $vbulletin->GPC['dostyleid']
	$stylevars = fetch_stylevars_array();
	// $stylevars['group']['stylevarid']['styleid'] = $stylevar (record array from db);

	echo "
					<div><select size='31' multiple='multiple' class='leftcontrol' id='varlist'>
	";
	$groups = array_keys($stylevars);
	$js_stylevarlist_array = Array();
	foreach($groups AS $group)
	{
		//TODO use friendly name once we figure that out.
		echo "
						<optgroup label='$group'>
		";
		$stylevarids = array_keys($stylevars[$group]);
		foreach ($stylevarids AS $stylevarid)
		{
			if ($stylevarid)
			{
				// build JS stylevar array
				$js_stylevarlist_array[] = "\"$stylevarid\" : \"$stylevarid\"";
				//TODO use friendly name once we figure that out.
				$color = fetch_inherited_color($stylevars["$group"]["$stylevarid"]['styleid'], $vbulletin->GPC['dostyleid']);
				echo "
					<option id='varlist_stylevar$stylevarid' class=\"$color\" value='" . $stylevarid . "'>$stylevarid</option>
				";
			}
		}

		echo "</optgroup>";
	}
	$js_stylevarlist_array = implode(",\n\t", $js_stylevarlist_array);
	echo '
					</select></div>
					<script type="text/javascript" src="../clientscript/vbulletin_list_filter.js?v=' . SIMPLE_VERSION . '"></script>
					<script type="text/javascript">
						vBulletin.register_control("vB_List_Filter", "stylevar_filter", Array("varlist"), {
							' . $js_stylevarlist_array . '
						}, "_stylevar", init);
						vBulletin_init();
					</script>
	';
	if ($vbulletin->debug AND ($vbulletin->GPC['dostyleid'] == -1))
	{
		// show the add stylevardfn button
		echo '
					<input type="button" value="' . $vbphrase['add_new_stylevar'] . '" onclick="location.href=\'stylevar.php?do=dfnadd\'" />
					<input type="button" value="' . $vbphrase['delete_stylevar'] . '" onclick="handle_stylevar_delete()" />
		';
	}
	// table wrapper
	echo '
					<table cellpadding="4" cellspacing="1" border="0" class="tborder" width="100%">
					<tr align="center">
						<td class="tcat"><b>' . $vbphrase['color_key'] . '</b></td>
					</tr>
					<tr>
						<td class="alt2">
						<div class="darkbg" style="margin: 4px; padding: 4px; border: 2px inset; text-align: ' . vB_Template_Runtime::fetchStyleVar('left') . '">
						<span class="col-g">' . $vbphrase['stylevar_is_unchanged_from_the_default_style'] . '</span><br />
						<span class="col-i">' . $vbphrase['stylevar_is_inherited_from_a_parent_style'] . '</span><br />
						<span class="col-c">' . $vbphrase['stylevar_is_customized_in_this_style'] . '</span>
						</div>
						</td>
					</tr>
					</table>
				</td>
				<td width="100%">
	';
	// show the editor pane
	echo '
					<iframe id="edit_scroller">
					</iframe>
	';
	// table wrapper
	echo '
				</td>
			</tr>
		</table>
	';

	$return_url = 'stylevar.php?' . $vbulletin->session->vars['sessionurl'] . '&dostyleid=' . $vbulletin->GPC['dostyleid'];
	//echo construct_link_code($vbphrase['rebuild_all_styles'],
	//	'template.php?' . $vbulletin->session->vars['sessionurl'] . 'do=rebuild&amp;goto=' . urlencode($return_url));
}

// #############################################################################
// do revert all StyleVars in a style
if ($_POST['do'] == 'dorevertall')
{
	if ($vbulletin->GPC['dostyleid'] != -1 AND $style = $db->query_first("SELECT styleid, parentid, parentlist, title FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']))
	{
		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}

		$stylevars = $db->query_read("
			SELECT DISTINCT s1.stylevarid
			FROM " . TABLE_PREFIX . "stylevar AS s1
			INNER JOIN " . TABLE_PREFIX . "stylevar AS s2 ON
				(s2.styleid IN ($style[parentlist]) AND s2.styleid <> $style[styleid] AND s2.stylevarid = s1.stylevarid)
			WHERE s1.styleid = $style[styleid]
		");
		if ($db->num_rows($stylevars) == 0)
		{
			print_stop_message('nothing_to_do');
		}
		else
		{
			$deletestylevars = array();

			while ($stylevar = $db->fetch_array($stylevars))
			{
				$deletestylevars[] = "'" . $stylevar['stylevarid'] . "'";
			}
			$db->free_result($stylevars);

			if (!empty($deletestylevars))
			{
				$db->query_write("DELETE FROM " . TABLE_PREFIX . "stylevar WHERE styleid = $style[styleid] AND stylevarid IN(" . implode(',', $deletestylevars) . ")");
				print_rebuild_style($style['styleid']);
			}

			print_cp_redirect("stylevar.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;dostyleid=$style[styleid]", 1);
		}
	}
	else
	{
		print_stop_message('invalid_style_specified');
	}
}

// #############################################################################
// revert all StyleVars in a style
if ($_REQUEST['do'] == 'revertall')
{
	if ($vbulletin->GPC['dostyleid'] != -1 AND $style = $db->query_first("SELECT styleid, title, parentlist FROM " . TABLE_PREFIX . "style WHERE styleid = " . $vbulletin->GPC['dostyleid']))
	{
		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}

		$stylevars = $db->query_read("
			SELECT DISTINCT s1.stylevarid
			FROM " . TABLE_PREFIX . "stylevar AS s1
			INNER JOIN " . TABLE_PREFIX . "stylevar AS s2 ON
				(s2.styleid IN ($style[parentlist]) AND s2.styleid <> $style[styleid] AND s2.stylevarid = s1.stylevarid)
			WHERE s1.styleid = $style[styleid]
		");
		if ($db->num_rows($stylevars) == 0)
		{
			print_stop_message('nothing_to_do');
		}
		else
		{
			$stylevarlist = '';
			while ($stylevar = $db->fetch_array($stylevars))
			{
				$stylevarlist .= "<li>$stylevar[stylevarid]</li>\n";
			}
			$db->free_result($stylevars);

			echo "<br /><br />";

			print_form_header('stylevar', 'dorevertall');
			print_table_header($vbphrase['revert_all_stylevars']);
			print_description_row("
				<blockquote><br />
				" . construct_phrase($vbphrase["revert_all_stylevars_from_style_x"], $style['title'], $stylevarlist) . "
				<br /></blockquote>
			");
			construct_hidden_code('dostyleid', $style['styleid']);
			print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
		}
	}
	else
	{
		print_stop_message('invalid_style_specified');
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42666 $
|| ####################################################################
\*======================================================================*/
