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

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'HTML' => array(
			'block_data',
			'checked' => array(
				'invisible', 'showreputation', 'showvcard', 'adminemail', 'showemail',
				'receivefriendemailrequest', 'receivepm', 'receivepmbuddies',
				'emailonpm', 'pmpopup', 'pmdefaultsavecopy', 'vm_enable',
				'vm_contactonly', 'showsignatures', 'showavatars', 'showimages',
				'vbasset_enable'
			),
			'customfields' => array(
				'login' => array(
					'*' => $VB_API_WHITELIST_COMMON['customfield']
				)
			),
			'day1selected', 'day2selected', 'day3selected', 'day4selected',
			'day5selected', 'day6selected', 'day7selected', 'days1selected',
			'days2selected', 'days7selected', 'days10selected', 'days14selected',
			'days30selected', 'days45selected', 'days60selected', 'days75selected',
			'days100selected', 'days365selected', 'daysallselected', 'daysdefaultselected',
			'emailchecked', 'languagelist', 'maxpostsoptions', 'postsdefaultselected',
			'selectvbcode', 'checkvbcode', 'timezoneoptions'
		)
	),
	'show' => array(
		'invisibleoption', 'receiveemail', 'reputationoption', 'pmoptions',
		'friend_email_request', 'vmoptions', 'editormode_picker', 'editormode_wysiwyg',
		'maxpostsoptions', 'styleoption', 'languageoption', 'usercssoption'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/