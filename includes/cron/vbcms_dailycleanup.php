<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

$vbulletin->db->query_write("
	DELETE 
	" . TABLE_PREFIX . "cms_node, 
	" . TABLE_PREFIX . "cms_nodeinfo
	FROM 
	" . TABLE_PREFIX . "cms_node 
	LEFT JOIN " . TABLE_PREFIX . "cms_nodeinfo ON " . TABLE_PREFIX . "cms_nodeinfo.nodeid = " . TABLE_PREFIX . "cms_node.nodeid   
	WHERE " . TABLE_PREFIX . "cms_node.new = 1 
	AND " . TABLE_PREFIX . "cms_node.lastupdated < " . (TIMENOW - 3600)
);

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $Revision: 25612 $
|| ####################################################################
\*======================================================================*/