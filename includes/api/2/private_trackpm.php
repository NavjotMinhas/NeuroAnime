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
			'confirmedreceipts' => array(
				'startreceipt',
				'endreceipt',
				'numreceipts',
				'receiptbits' => array(
					'*' => array(
						'receipt' => array(
							'receiptid', 'send_time',
							'read_time', 'title', 'tousername'
						)
					)
				),
				'counter'
			),
			'unconfirmedreceipts' => array(
				'startreceipt',
				'endreceipt',
				'numreceipts',
				'receiptbits' => array(
					'*' => array(
						'receipt' => array(
							'receiptid', 'send_time',
							'read_time', 'title', 'tousername'
						)
					)
				),
				'counter'
			)
		)
	),
	'show' => array(
		'readpm', 'receipts', 'pagenav'
	)
);

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'pm_receiptsbit':
		case 'private_trackpm_receiptbit':
			$r['receipt']['send_time'] = $r['receipt']['sendtime'];
			$r['receipt']['read_time'] = $r['receipt']['readtime'];
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