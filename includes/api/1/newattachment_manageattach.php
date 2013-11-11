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
if (!VB_API) die;

define('VB_API_LOADLANG', true);

$VB_API_WHITELIST = array(
	'response' => array(
		'attachkeybits' => array(
			'*' => array(
				'extension' => array(
					'extension', 'size', 'width', 'height'
				)
			)
		),
		'attachlimit',
		'attachments' => array(
			'*' => array(
				'attach' => array(
					'extension', 'attachmentid', 'dateline', 'filename',
					'filesize'
				)
			)
		),
		'attachsize',
		'attachsum', 'attach_username', 'contenttypeid',
		'editpost', 'errorlist', 'inimaxattach',
		'totallimit', 'totalsize', 'values'
	),
	'show' => array(
		'errors', 'attachmentlimits', 'currentsize', 'totalsize', 'attachoption',
		'attachfile', 'attachurl', 'attachmentlist'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/