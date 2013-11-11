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
if (!VB_API) die;

class vB_APIMethod_api_init extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin, $db, $show, $VB_API_REQUESTS;

		if (!$VB_API_REQUESTS['api_c'])
		{
			// The client doesn't have an ID yet. So we need to generate a new one.
			$vbulletin->input->clean_array_gpc('r', array(
				'clientname'      => TYPE_STR,
				'clientversion'   => TYPE_STR,
				'platformname'    => TYPE_STR,
				'platformversion' => TYPE_STR,
				'uniqueid'        => TYPE_STR,
			));
			
			// All params are required.
			// uniqueid is the best to be a permanent unique id such as hardware ID (CPU ID,
			// Harddisk ID or Mobile IMIE). Some client can not get a such a uniqueid,
			// so it needs to generate an unique ID and save it in its local storage. If it
			// requires the client ID and Secret again, pass the same unique ID.
			if (!$vbulletin->GPC['clientname'] OR !$vbulletin->GPC['clientversion']
					OR !$vbulletin->GPC['platformname'] OR!$vbulletin->GPC['platformversion']
					OR !$vbulletin->GPC['uniqueid'])
			{
				return $this->error('apiclientinfomissing', 'Miss required client information');
			}

			// Gererate clienthash.
			$clienthash = md5($vbulletin->GPC['clientname'] . $vbulletin->GPC['platformname']
				. $vbulletin->GPC['uniqueid']);

			// Generate a new secret
			$secret = fetch_random_password(32);

			// If the same clienthash exists, return secret back to the client.
			$client = $db->query_first("SELECT *
				FROM " . TABLE_PREFIX . "apiclient
				WHERE clienthash = '" . $db->escape_string($clienthash) . "'
			");

			$apiclientid = $client['apiclientid'];

			if ($apiclientid)
			{
				// Update secret
				// Also remove userid so it will logout previous loggedin and remembered user. (VBM-553)
				$db->query_write("UPDATE " . TABLE_PREFIX . "apiclient SET
					secret = '" . $db->escape_string($secret) . "',
					apiaccesstoken = '" . $db->escape_string($vbulletin->session->vars['apiaccesstoken']) . "',
					lastactivity = " . TIMENOW . ",
					clientversion = '" . $db->escape_string($vbulletin->GPC['clientversion']) . "',
					platformversion = '" . $db->escape_string($vbulletin->GPC['platformversion']) . "',
					userid = 0
					WHERE apiclientid = $apiclientid");
			}
			else
			{
				// Create a new client
				$db->query_write("
					INSERT INTO " . TABLE_PREFIX . "apiclient (
						secret, clienthash, clientname, clientversion, platformname,
						platformversion, uniqueid, initialipaddress, apiaccesstoken,
						dateline, lastactivity
					)
					VALUES (
						'" . $db->escape_string($secret) . "', " .
						"'" . $db->escape_string($clienthash) . "', " .
						"'" . $db->escape_string($vbulletin->GPC['clientname']) . "', " .
						"'" . $db->escape_string($vbulletin->GPC['clientversion']) . "', " .
						"'" . $db->escape_string($vbulletin->GPC['platformname']) . "', " .
						"'" . $db->escape_string($vbulletin->GPC['platformversion']) . "', " .
						"'" . $db->escape_string($vbulletin->GPC['uniqueid']) . "', " .
						"'" . $db->escape_string($vbulletin->alt_ip) . "', " .
						"'" . $db->escape_string($vbulletin->session->vars['apiaccesstoken']) . "', " .
						TIMENOW . ", " . TIMENOW . "
					)
				");

				$apiclientid = $db->insert_id();
				
			}

			// Set session client ID
			$vbulletin->session->set('apiclientid', $apiclientid);
		}
		else
		{
			// api_c and api_sig are verified in init.php so we don't need to verify here again.
			$apiclientid = intval($VB_API_REQUESTS['api_c']);
			
			// Update lastactivity
			$db->query_write("UPDATE " . TABLE_PREFIX . "apiclient SET
				lastactivity = " . TIMENOW . "
				WHERE apiclientid = $apiclientid");
		}
		

		bootstrap_framework();
		$contenttypescache = vB_Types::instance()->getContentTypes();

		foreach ($contenttypescache as $contenttype)
		{
			$contenttypes[$contenttype['class']] = $contenttype['id'];
		}

		// Check the status of CMS and Blog
		$blogenabled = ($vbulletin->products['vbblog'] == '1');
		$cmsenabled = ($vbulletin->products['vbcms'] == '1');

		$data = array(
			'apiversion' => VB_API_VERSION,
			'apiaccesstoken' => $vbulletin->session->vars['apiaccesstoken'],
			'bbtitle' => $vbulletin->options['bbtitle'],
			'bburl' => $vbulletin->options['bburl'],
			'bbactive' => $vbulletin->options['bbactive'],
			'forumhome' => $vbulletin->options['forumhome'],
			'vbulletinversion' => $vbulletin->options['templateversion'],
			'contenttypes' => $contenttypes,
			'features' => array(
				'blogenabled' => $blogenabled,
				'cmsenabled' => $cmsenabled,
				'pmsenabled' => (bool)$vbulletin->options['enablepms'],
				'searchesenabled' => (bool)$vbulletin->options['enablesearches'],
				'groupsenabled' => (bool)($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']),
				'albumsenabled' => (bool)($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']),
				'friendsenabled' => (bool)($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends']),
				'visitor_trackingenabled' => (bool)($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_tracking']),
				'visitor_messagingenabled' => (bool)($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']),
				'multitypesearch' => true,
				'taggingenabled' => (bool)$vbulletin->options['threadtagging'],
			),
			'permissions' => $vbulletin->userinfo['permissions'],
			'show' => $show
		);
		
		if (!$vbulletin->options['bbactive'])
		{
			$data['bbclosedreason'] = $vbulletin->options['bbclosedreason'];
		}

		$data['apiclientid'] = $apiclientid;
		if (!$VB_API_REQUESTS['api_c'])
		{
			$data['secret'] = $secret;
		}

		return $data;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/