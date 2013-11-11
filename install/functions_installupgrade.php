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
*	This file is a bucket in which functions common to both the
* install and the upgrade can be located.
*/

if (!defined('VB_AREA') AND !defined('THIS_SCRIPT'))
{
	echo 'VB_AREA or THIS_SCRIPT must be defined to continue';
	exit;
}

// #####################################################################
// Determines which mysql engine to use for high concurrency tables
// Will use InnoDB if its available, otherwise MyISAM
function get_high_concurrency_table_engine($db)
{
	if (defined('SKIPDB'))
	{
		return 'MyISAM';
	}

	$set = $db->query('SHOW ENGINES');

	while ($row = $db->fetch_array($set))
	{
		if (
			strcasecmp($row['Engine'], 'innodb') == 0 AND
			(
				(strcasecmp($row['Support'], 'yes') == 0) OR
				(strcasecmp($row['Support'], 'default') == 0)
			)
		)
		{
			return 'InnoDB';
		}

	}
	return 'MyISAM';
}

function should_install_suite()
{
	$suite_products = array('vbblog', 'vbcms');

	foreach ($suite_products as $productid)
	{
		if (!file_exists(DIR . "/includes/xml/product-$productid.xml"))
		{
			return false;
		}
	}

	return true;
}

function print_admin_stop_exception($e)
{
		$args = $e->getParams();
		$message = fetch_phrase($args[0], 'error', '', false);

		if (sizeof($args) > 1)
		{
			$args[0] = $message;
			$message = call_user_func_array('construct_phrase', $args);
		}

		echo "<p>$message</p>\n";
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 29116 $
|| ####################################################################
\*======================================================================*/