<?php

/**
 * Skimlinks vBulletin Plugin
 *
 * @author Skimlinks
 * @version 2.0.7
 * @copyright © 2011 Skimbit Ltd.
 */

$_skimEnabled = ($this->registry->options['skimlinks_enabled'] AND !empty($this->registry->options['skimlinks_pub_id']));

$_skimUserDisabled = ($this->registry->options['skimlinks_allow_user_disable'] AND empty($this->registry->userinfo['skimlinks']));

if ($_skimEnabled AND !$_skimUserDisabled)
{
	if (!function_exists('getSkimClass'))
	{
		function getSkimClass($userinfo)
		{
			global $vbulletin;
			static $skimLinksDisableUsergroups = null;
			static $skimWordsDisableUserGroups = null;

			if ($skimLinksDisableUsergroups === null)
			{
				$skimLinksDisableUsergroups = json_decode($vbulletin->options['skimlinks_disable_groups_parse']);
			}

			if ($skimWordsDisableUserGroups === null)
			{
				$skimWordsDisableUserGroups = json_decode($vbulletin->options['skimwords_disable_groups_parse']);
			}

			$skimLinks = false;
			$skimWords = false;

			if (empty($skimLinksDisableUsergroups) || !is_member_of($userinfo, $skimLinksDisableUsergroups))
			{
				$skimLinks = true;
			}

			if (empty($skimWordsDisableUserGroups) || !is_member_of($userinfo, $skimWordsDisableUserGroups))
			{
				$skimWords = true;
			}

			if ($skimLinks AND $skimWords)
			{
				return '';
			}
			else if ($skimLinks)
			{
				return 'noskimwords';
			}
			else if ($skimWords)
			{
				return 'noskimlinks';
			}
			else
			{
				return 'noskim';
			}
		}
	}

	if ($skimClass = getSkimClass($post))
	{
		if (version_compare($this->registry->options['templateversion'], 4, '>='))
		{
			$postIdName = 'post_' . $post['postid'];
		}
		else
		{
			$postIdName = 'post' . $post['postid'];
		}

		global $vbulletin;
		if (version_compare(@$vbulletin->versionnumber, '3.8.0', '>=')) {
			$template_hook['postbit_end'] .= "<script type=\"text/javascript\">
			vBulletin.events.SkimlinksActivate.subscribe(function()
			{
				YAHOO.util.Dom.addClass('{$postIdName}', '{$skimClass}');
			});
			</script>";
		} 
	}
}