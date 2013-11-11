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

// ###################### Start getallforumsql #######################
function fetch_all_forums_sql()
{
	global $fpermscache, $vbulletin;

	foreach ($vbulletin->userinfo['forumpermissions'] AS $forumid => $ugperms)
	{
		if (!($ugperms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			$noforums[] = $forumid;
		}
	}
	if (sizeof($noforums) > 0)
	{
		return ' AND forumid NOT IN (' . implode(',', $noforums) . ')';
	}
	else
	{
		return '';
	}
}

// ###################### Start makejs #######################
function print_js($typename, $data, $dates)
{
	// make the javascript function definition
	global $vbulletin;

	// make the function

	if ($vbulletin->db->num_rows($data))
	{

		echo 'function ' . $typename . ' (';

		$firstline = $vbulletin->db->fetch_array($data);

		$firstitem = false;
		foreach ($firstline AS $name => $value)
		{

			if ($firstitem)
			{
				echo ', ';
			}
			$firstitem = true;

			echo $name;

		}

		echo ")
		{\n";

		foreach ($firstline AS $name => $value)
		{

			if (in_array($name, $dates))
			{ // handling for date type variables
				echo "\tthis." . $name . ' = new Date((' . $name . " - " . $vbulletin->options['hourdiff'] . ") * 1000);\n";
			}
			else

			{
				echo "\tthis." . $name . ' = ' . $name . ";\n";
			}

		}

		echo "}\n\n"; // end function

		echo 'var ' . $typename . 's = new Array(' . $vbulletin->db->num_rows($data) . ");\n\n";

		print_js_data($typename, $firstline, 1);

		$counter = 1;
		while ($datarow = $vbulletin->db->fetch_array($data))
		{
			$counter++;

			print_js_data($typename, $datarow, $counter);
		}

		echo "\n\n";

	}
}

// ###################### Start printdata #######################
function print_js_data($typename, $datarow, $number)
{
	echo $typename . 's[' . ($number - 1) . '] = new ' . $typename . '(';

	$firstitem = false;
	foreach ($datarow AS $name => $value)
	{

		if ($firstitem)
		{
			echo ', ';
		}
		$firstitem = true;

		echo "'" . addslashes_js($value) . "'";

	}

	echo ");\n";
}

// ###################### Start makejs_array #######################
function print_js_array($typename, $data, $dates)
{
	global $vbulletin;

	if (is_array($data))
	{
		// make the function

		echo 'function ' . $typename . ' (';

		reset($data);

		$firstline = current($data);
		$firstitem = false;
		foreach ($firstline AS $name => $value)
		{

			if ($firstitem)
			{
				echo ', ';
			}
			$firstitem = trues;

			echo $name;

		}

		echo ")
	{\n";

		reset ($firstline);

		foreach ($firstline AS $name => $value)
		{

			if (in_array($name, $dates))
			{ // handling for date type variables
				echo "\tthis." . $name . ' = new Date((' . $name . " - " . $vbulletin->options['hourdiff'] . ") * 1000);\n";
			}
			else

			{
				echo "\tthis." . $name . ' = ' . $name . ";\n";
			}

		}

		echo "}\n\n"; // end function

		echo 'var ' . $typename . 's = new Array(' . sizeof($data) . ");\n\n";

		print_js_data($typename, $firstline, 1);

		$counter = 1;
		while ($datarow = next($data))
		{
			$counter++;

			print_js_data($typename, $datarow, $counter);

		}
		echo "\n\n";

	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>