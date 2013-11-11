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

// ## Function takes an array from fetch_userinfo and an array from cache_permissions()
// ## Returns the user's reputation altering power (for positive)
function fetch_reppower(&$userinfo, &$perms, $reputation = 'pos')
{
	global $vbulletin;

	// User does not have permission to leave negative reputation
	if (!($perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cannegativerep']))
	{
		$reputation = 'pos';
	}

	if (!($perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuserep']))
	{
		$reppower = 0;
	}
	else if ($perms['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] AND $vbulletin->options['adminpower'])
	{
		$reppower = iif($reputation != 'pos', $vbulletin->options['adminpower'] * -1, $vbulletin->options['adminpower']);
	}
	else if (($userinfo['posts'] < $vbulletin->options['minreputationpost']) OR ($userinfo['reputation'] < $vbulletin->options['minreputationcount']))
	{
		$reppower = 0;
	}
	else
	{
		$reppower = 1;

		if ($vbulletin->options['pcpower'])
		{
			$reppower += intval($userinfo['posts'] / $vbulletin->options['pcpower']);
		}
		if ($vbulletin->options['kppower'])
		{
			$reppower += intval($userinfo['reputation'] / $vbulletin->options['kppower']);
		}
		if ($vbulletin->options['rdpower'])
		{
			$reppower += intval(intval((TIMENOW - $userinfo['joindate']) / 86400) / $vbulletin->options['rdpower']);
		}

		if ($reputation != 'pos')
		{
			// make negative reputation worth half of positive, but at least 1
			$reppower = intval($reppower / 2);
			if ($reppower < 1)
			{
				$reppower = 1;
			}
			$reppower *= -1;
		}
	}

	($hook = vBulletinHook::fetch_hook('reputation_power')) ? eval($hook) : false;

	return $reppower;
}

// ###################### Start getreputationimage #######################
function fetch_reputation_image(&$post, &$perms)
{
	global $vbphrase, $vbulletin;

	if (!$vbulletin->options['reputationenable'])
	{
		return true;
	}

	$reputation_value = $post['reputation'];
	if ($post['reputation'] == 0)
	{
		$reputationgif = 'balance';
		$reputation_value = 0;
	}
	else if ($post['reputation'] < 0)
	{
		$reputationgif = 'neg';
		$reputationhighgif = 'highneg';
		$reputation_value = $post['reputation'] * -1;
	}
	else
	{
		$reputationgif = 'pos';
		$reputationhighgif = 'highpos';
	}

	if ($reputation_value > 500)
	{  // bright green bars take 200 pts not the normal 100
		$reputation_value = ($reputation_value / 2) + 250;
	}

	$reputationbars = intval($reputation_value / 100); // award 1 reputation bar for every 100 points
	if ($reputationbars > 10)
	{
		$reputationbars = 10;
	}

	if (!$post['showreputation'] AND $perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canhiderep'])
	{
		$posneg = 'off';
		$post['level'] = $vbphrase['reputation_disabled'];

		$templater = vB_Template::create('postbit_reputation');
			$templater->register('posneg', $posneg);
			$templater->register('post', $post);
		$post['reputationdisplay'] = $templater->render();
	}
	else
	{
		if (!$post['reputationlevelid'])
		{
			$post['level'] = $vbulletin->options['reputationundefined'];
		}
		for ($i = 0; $i <= $reputationbars; $i++)
		{
			if ($i >= 5)
			{
				$posneg = $reputationhighgif;
			}
			else
			{
				$posneg = $reputationgif;
			}

			$post['level'] = $vbphrase['reputation' . $post['reputationlevelid']];
			$templater = vB_Template::create('postbit_reputation');
				$templater->register('posneg', $posneg);
				$templater->register('post', $post);
			$post['reputationdisplay'] .= $templater->render();
		}
	}

	($hook = vBulletinHook::fetch_hook('reputation_image')) ? eval($hook) : false;

	return true;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>