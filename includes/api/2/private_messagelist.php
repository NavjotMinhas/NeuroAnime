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

$VB_API_WHITELIST = array(
	'response' => array(
		'HTML' => array(
			'folderid', 'foldername',
			'messagelist_periodgroups' => array(
				'*' => array(
					'groupid', 'groupname', 'messagesingroup',
					'messagelistbits' => array(
						'*' => array(
							'pm' => array(
								'pmid', 'sendtime', 'statusicon',
								'iconpath', 'icontitle', 'title'
							),
							'userbit',
							'show' => array(
								'pmicon', 'unread'
							)
						)
					)
				)
			),
			'pagenav',
			'pagenumber', 'perpage', 'pmquota', 'pmtotal',
			'receipts', 'sortfilter', 'totalmessages', 'startmessage',
			'endmessage'
		)
	),
	'show' => array(
		'thisfoldertotal', 'allfolderstotal', 'pmicons', 'messagelist', 'openfilter',
		'pagenav', 'sentto', 'movetofolder'
	)
);

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'pm_messagelistbit':
			$r['pm']['sendtime'] = $r['pm']['dateline'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_2', 2);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/