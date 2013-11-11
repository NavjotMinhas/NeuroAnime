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

function add_ajax_attachment_xml(&$xml, $contenttypeid, $posthash, $poststarttime, $values)
{
	global $vbulletin, $vbphrase;
	require_once(DIR . '/includes/functions_file.php');

	$xml->add_tag('contenttypeid', $contenttypeid);
	$xml->add_tag('auth_type', (
											empty($_SERVER['AUTH_USER'])
												AND
											empty($_SERVER['REMOTE_USER'])
										) ? 0 : 1);
	$xml->add_tag('asset_enable', $vbulletin->userinfo['vbasset_enable'] ? $vbulletin->options['vbasset_enable'] : 0);

	$xml->add_tag('userid', $vbulletin->userinfo['userid']);
	$xml->add_tag('max_file_size', fetch_max_upload_size());
	$xml->add_tag('attachlimit', $vbulletin->options['attachlimit']);
	$xml->add_tag('posthash', $posthash);
	$xml->add_tag('poststarttime', $poststarttime);
	if (!empty($values))
	{
		$xml->add_group('values');
		foreach($values AS $key => $value)
		{
			$xml->add_tag($key, $value);
		}
		$xml->close_group('values');
	}
	$xml->add_group('phrases');
		$xml->add_tag('upload_failed', $vbphrase['upload_failed']);
		$xml->add_tag('file_is_too_large', $vbphrase['file_is_too_large']);
		$xml->add_tag('invalid_file', $vbphrase['invalid_file']);
		$xml->add_tag('maximum_number_of_attachments_reached', $vbphrase['maximum_number_of_attachments_reached']);
		$xml->add_tag('unable_to_parse_attachmentid_from_image', $vbphrase['unable_to_parse_attachmentid_from_image']);
		$xml->add_tag('saving_of_settings_failed', $vbphrase['saving_of_settings_failed']);
	$xml->close_group('phrases');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27207 $
|| ####################################################################
\*======================================================================*/
?>
