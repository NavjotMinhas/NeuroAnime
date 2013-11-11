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
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "session
	WHERE lastactivity < " . intval(TIMENOW - $vbulletin->options['cookietimeout']) . "
");

// posthashes are only valid for 5 minutes
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "posthash
	WHERE dateline < " . (TIMENOW - 300)
);

$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "visitormessage_hash
	WHERE dateline < " . (TIMENOW - 300)
);

// expired registration images after 1 hour
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "humanverify
	WHERE dateline < " . (TIMENOW - 3600)
);

//expire groupmessage_hash after 5 minutes
$vbulletin->db->query_write("
 DELETE FROM " . TABLE_PREFIX . "groupmessage_hash
 WHERE dateline < " . (TIMENOW - 300)
);

//expire picturecomment_hash after 5 minutes
$vbulletin->db->query_write("
 DELETE FROM " . TABLE_PREFIX . "picturecomment_hash
 WHERE dateline < " . (TIMENOW - 300)
);

// expired cached posts
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "postparsed
	WHERE dateline < " . (TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24))
);

// Orphaned Attachments are removed after one hour
$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_SILENT, 'attachment');
$attachdata->set_condition("a.contentid = 0 AND a.dateline < " . (TIMENOW - 3600));
$attachdata->delete(true, false);

// Unused filedata is removed after one hour
$attachdata =& datamanager_init('Filedata', $vbulletin, ERRTYPE_SILENT, 'attachment');
$attachdata->set_condition("fd.refcount = 0 AND fd.dateline < " . (TIMENOW - 3600));
$attachdata->delete();

// Orphaned pmtext records are removed after one hour.
// When we delete PMs we only delete the pm record, leaving
// the pmtext record alone for this script to clean up
// this is kind of an expensive query (needs to scan the entire pmtext table)
// Moving it to the slave won't cause any real problems (might cause a delete
// to be defered to a future cleanup)
$pmtexts = $vbulletin->db->query_read_slave("
	SELECT pmtext.pmtextid
	FROM " . TABLE_PREFIX . "pmtext AS pmtext
	LEFT JOIN " . TABLE_PREFIX . "pm AS pm USING(pmtextid)
	WHERE pm.pmid IS NULL
");
if ($vbulletin->db->num_rows($pmtexts))
{
	$pmtextids = '0';
	while ($pmtext = $vbulletin->db->fetch_array($pmtexts))
	{
		$pmtextids .= ",$pmtext[pmtextid]";
	}
	$vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "pmtext WHERE pmtextid IN($pmtextids)");
}
$vbulletin->db->free_result($pmtexts);

// Expired externalcache data
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "externalcache
	WHERE dateline  < " . (TIMENOW - $vbulletin->options['externalcache'] * 60) . "
");

// Stale pm throttle data
if ($vbulletin->options['pmthrottleperiod'])
{
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pmthrottle
		WHERE dateline < " . (TIMENOW - $vbulletin->options['pmthrottleperiod'] * 60) . "
	");
}

// Out of date album updates
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "albumupdate
	WHERE dateline < " . (TIMENOW - $vbulletin->options['album_recentalbumdays'] * 86400) . "
");

($hook = vBulletinHook::fetch_hook('cron_script_cleanup_hourly2')) ? eval($hook) : false;

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 38549 $
|| ####################################################################
\*======================================================================*/
?>
