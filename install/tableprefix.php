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
ignore_user_abort(true);
if (function_exists('set_time_limit') and ini_get('safe_mode') == 0)
{
	@set_time_limit(1200);
}

chdir('./../');
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
define('DIR', CWD);

// fetch the core classes
require_once(DIR . '/includes/class_core.php');

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'tableprefix.php');
define('NOZIP', 1);

$phrasegroups = array('maintenance');
$specialtemplates = array();

// initialize the data registry
$vbulletin = new vB_Registry();

// parse the configuration ini file
$vbulletin->fetch_config();

require_once(DIR . "/{$vbulletin->config['Misc']['admincpdir']}/global.php");

// #############################################################################

print_cp_header($vbphrase['vbulletin_table_prefix_rename_system']);

// #############################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'rename')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'newprefix' => TYPE_STR,
		'rename' => TYPE_ARRAY,
	));

	if ($vbulletin->GPC['newprefix'] == TABLE_PREFIX)
	{
		print_cp_message(construct_phrase($vbphrase['new_prefix_same_as_old'], TABLE_PREFIX, $vbulletin->GPC['newprefix']));
	}

	?>
	<div align="center">
	<div class="tborder" style="width:90%;">
	<div class="tcat" style="padding:4px"><b><?php echo($vbphrase['renaming_table_with_new_prefix']); ?></b></div>
	<div class="alt1" style="padding:4px; text-align:<?php echo $stylevar['left']; ?>">
	<b><?php echo($vbphrase['please_read_notes_after_completion']); ?></b>
	<ul class="smallfont">
	<?php

	flush();

	$valid = array();
	$tables = $db->query_write("SHOW TABLES");
	while ($table = $db->fetch_array($tables, MYSQL_NUM))
	{
		$valid["$table[0]"] = true;
	}
	unset($table);
	$db->free_result($tables);

	foreach(array_keys($vbulletin->GPC['rename']) as $table)
	{

		if (!isset($valid["$table"]))
		{
			echo '<li>' . construct_phrase($vbphrase['table_x_does_not_exist'], $table) . "</li>\n";
			flush();
		}
		else

		{
			$name = fetch_renamed_table_name($table, TABLE_PREFIX, $vbulletin->GPC['newprefix']);
			echo '<li>' . construct_phrase($vbphrase['renaming_x_to_y'], $name['old'], $name['new']) . "</li>\n";
			flush();
			$db->query_write("ALTER TABLE $name[old] RENAME $name[new]");
			echo "$vbphrase[okey]</li>\n";
		}

	}

	?>
	</ul>
	</div>
	<div class="tcat" style="padding:4px"><b><?php echo($vbphrase['renaming_process_complete']); ?></b></div>
	<div class="alt2" style="padding:4px; text-align:<?php echo $stylevar['left']; ?>">
	<?php
	echo(construct_phrase($vbphrase['tableprefix_change_completed'], TABLE_PREFIX, $vbulletin->GPC['newprefix']));
	?>
	</div>
	</div>
	</div>
	<?php

	// this is a small hack to prevent the shutdown function from causing database errors
	$vbulletin->db->shutdownqueries = array();
	$vbulletin->session = null;

}

// #############################################################################

if ($_POST['do'] == 'confirm')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'newprefix' => TYPE_STR,
		'rename' => TYPE_ARRAY,
	));

	if (array_sum($vbulletin->GPC['rename']) == 0)
	{
		print_stop_message('sorry_no_tables_were_submitted_to_be_renamed');
	}
	else
	{
		if ($vbulletin->GPC['newprefix'] == TABLE_PREFIX)
		{
			print_cp_message(construct_phrase($vbphrase['new_prefix_same_as_old'], TABLE_PREFIX, $vbulletin->GPC['newprefix']));
		}

		$prefixlength = strlen(TABLE_PREFIX);
		$dorename = array();
		$warn = array();
		$dotables = '';
		$warntables = '';

		print_form_header('tableprefix', 'rename');
		print_table_header(construct_phrase($vbphrase['confirm_prefix_change_x_to_y'], TABLE_PREFIX, $vbulletin->GPC['newprefix']));
		construct_hidden_code('newprefix', $vbulletin->GPC['newprefix']);

		foreach($vbulletin->GPC['rename'] as $table => $yesno)
		{
			if ($yesno)
			{
				construct_hidden_code("rename[$table]", 1);
				$dorename[] = $table;
				$tablename = fetch_renamed_table_name($table, TABLE_PREFIX, $vbulletin->GPC['newprefix'], true);
				$dotables .= "<tr class=\"alt2\"><td class=\"smallfont\">$tablename[old]</td><td class=\"smallfont\">$tablename[new]</td></tr>";
				if (substr($table, 0, $prefixlength) != TABLE_PREFIX)
				{
					$warn[] = $table;
				}
			}
		}

		if (!empty($warn))
		{
			foreach($warn as $table)
			{
				$warntables .= "<li>$table</li>";
			}
			print_description_row(construct_phrase($vbphrase['other_prefix_warning'], $warntables));
			print_description_row('<div align="center"><input type="button" class="button" value="' . $vbphrase['go_back'] . '" onclick="history.back(1);" /></div>', 0, 2, 'tfoot');
		}
		else

		{
			$bgcounter++;
		}

		if (!empty($dorename))
		{
			print_description_row("
				<p>$vbphrase[you_have_chosen_to_rename_the_following_tables]:</p>
				<table cellpadding=\"1\" cellspacing=\"0\" border=\"0\" class=\"alt1\" width=\"90%\" align=\"center\"><tr><td>
				<table cellpadding=\"3\" cellspacing=\"1\" border=\"0\" width=\"100%\">
				<tr><td><b>$vbphrase[original_table_name]</b></td><td><b>$vbphrase[new_table_name]</b></td></tr>
				$dotables
				</table></td></tr></table>
			");
		}

		print_description_row($vbphrase['prefix_change_confirm']);

		print_submit_row($vbphrase['rename_sql_tables'], 0, 2, $vbphrase['go_back']);

	}

}

// #############################################################################

if ($_REQUEST['do'] == 'choose')
{
	print_form_header('tableprefix', 'confirm');
	print_table_header($vbphrase['rename_sql_tables']);
	print_label_row($vbphrase['old_table_prefix'], '<div class="bginput" style="margin-top:2px; width:230px">' . TABLE_PREFIX . '</div>');
	print_input_row($vbphrase['new_table_prefix'], 'newprefix', TABLE_PREFIX);
	print_label_row($vbphrase['table_name'], '<input type="button" class="button" value=" ' .  $vbphrase['set_all_yes']  . ' " onclick="js_check_all_option(this.form, 1);" /> <input type="button" class="button" value="  ' . $vbphrase['set_all_no']  . ' " onclick="js_check_all_option(this.form, 0);" />', 'tfoot');
	print_description_row($vbphrase['list_of_tables_in_db_below']);
	$prefixlength = strlen(TABLE_PREFIX);
	$tables = $db->query_write("SHOW TABLES");
	while($table = $db->fetch_array($tables, MYSQL_NUM))
	{
		print_yes_no_row(construct_phrase($vbphrase['rename_table_x'], $table[0]), "rename[$table[0]]", iif(substr($table[0], 0, $prefixlength) == TABLE_PREFIX, 1, 0));
	}
	print_submit_row($vbphrase['rename_tables']);

}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{

	echo '<p>&nbsp;</p><p>&nbsp;</p>';

	print_form_header('tableprefix', 'choose');
	print_table_header($vbphrase['vbulletin_table_prefix_rename_system']);
	print_description_row(construct_phrase($vbphrase['this_script_will_allow_you_to_rename_your_vb_tables'], TABLE_PREFIX));
	print_submit_row($vbphrase['continue'], 0);

}

// #############################################################################

print_cp_footer();

// #############################################################################

function fetch_renamed_table_name($table, $oldprefix, $newprefix, $dobold = false)
{
	static $prefixlength;

	if (!isset($prefixlength))
	{
		$prefixlength = strlen($oldprefix);
	}

	$name = array('old' => $table);

	if (substr($table, 0, $prefixlength) == $oldprefix)
	{
		$table = substr($table, $prefixlength);
		if ($dobold)
		{
			$name['old'] = "<b>$oldprefix</b>$table";
		}
	}

	$name['new'] = iif($dobold, "<b>$newprefix</b>", $newprefix) . $table;

	return $name;

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34135 $
|| ####################################################################
\*======================================================================*/
?>