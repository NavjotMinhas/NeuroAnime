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

function build_ad_template($location)
{
	global $ad_cache, $vbulletin;
	$template = "";
	foreach($ad_cache AS $adid => $ad)
	{
		// active ads on the same location only
		if ($ad['active'] AND $ad['adlocation'] == $location)
		{
			$criterion = $vbulletin->db->query_read("
				SELECT * FROM " . TABLE_PREFIX . "adcriteria
				WHERE adid = " . $adid . "
			");

			// create the template conditionals
			$conditional_prefix = "";
			$conditional_postfix = "";

			while($criteria = $vbulletin->db->fetch_array($criterion))
			{
				switch($criteria['criteriaid'])
				{
					case "in_usergroup_x":
						$conditional_prefix .= '<vb:if condition="is_member_of($' . 'bbuserinfo, ' . $criteria['condition1'] . ')">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "not_in_usergroup_x":
						$conditional_prefix .= '<vb:if condition="!is_member_of($' . 'bbuserinfo, ' . $criteria['condition1'] . ')">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "browsing_content_page":
						$conditional_prefix .= '<vb:if condition="CONTENT_PAGE == ' . $criteria['condition1'] . '">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "browsing_forum_x":
						$conditional_prefix .= '<vb:if condition="$' . 'vbulletin->GPC[\'forumid\'] == ' . $criteria['condition1'] . '">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "browsing_forum_x_and_children":
						// find out who the children are:
						$forum = $vbulletin->db->query_first("SELECT childlist FROM " . TABLE_PREFIX . "forum WHERE forumid = " . intval($criteria['condition1']));
						$conditional_prefix .= '<vb:if condition="in_array($' . 'vbulletin->GPC[\'forumid\'], array(' . $forum['childlist'] . '))">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "style_is_x":
						$conditional_prefix .= '<vb:if condition="STYLEID == ' . intval($criteria['condition1']) . '">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "no_visit_in_x_days":
						$conditional_prefix .= '<vb:if condition="$' . 'bbuserinfo[\'lastactivity\'] < TIMENOW - (86400*' . intval($criteria['condition1']) . ')">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "no_posts_in_x_days":
						$conditional_prefix .= '<vb:if condition="$' . 'bbuserinfo[\'lastpost\'] < TIMENOW - (86400*' . intval($criteria['condition1']) . ')">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "has_x_postcount":
						$conditional_prefix .= '<vb:if condition="$' . 'bbuserinfo[\'posts\'] > ' . intval($criteria['condition1']) . ' AND $' . 'bbuserinfo[\'posts\'] < ' . intval($criteria['condition2']) . '">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "has_never_posted":
						$conditional_prefix .= '<vb:if condition="$' . 'bbuserinfo[\'lastpost\'] == 0">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "has_x_reputation":
						$conditional_prefix .= '<vb:if condition="$' . 'bbuserinfo[\'reputation\'] > ' . intval($criteria['condition1']) . ' AND $' . 'bbuserinfo[\'reputation\'] < ' . intval($criteria['condition2']) . '">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "pm_storage_x_percent_full":
						$conditional_prefix .= '<vb:if condition="$' . 'pmboxpercentage = $' . 'bbuserinfo[\'pmtotal\'] / $' . 'bbuserinfo[\'permissions\'][\'pmquota\'] * 100"></vb:if>';
						$conditional_prefix .= '<vb:if condition="$' . 'pmboxpercentage > ' . intval($criteria['condition1']) . ' AND $' . 'pmboxpercentage < ' . intval($criteria['condition2']) . '">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "came_from_search_engine":
						$conditional_prefix .= '<vb:if condition="is_came_from_search_engine()">';
						$conditional_postfix .= "</vb:if>";
						break;
					case "is_date":
						if ($criteria['condition2'])
						{
							$conditional_prefix .= '<vb:if condition="gmdate(\'d-m-Y\', TIMENOW) == \'' . str_replace("'", "\'", $criteria['condition1']) .'\'">';
							$conditional_postfix .= "</vb:if>";
						}
						else
						{
							$conditional_prefix .= '<vb:if condition="vbdate(\'d-m-Y\', TIMENOW, false, false) == \'' . str_replace("'", "\'", $criteria['condition1']) .'\'">';
							$conditional_postfix .= "</vb:if>";
						}
						break;
					case "is_time":
						if (preg_match('#^(\d{1,2}):(\d{2})$#', $criteria[1], $start_time) AND preg_match('#^(\d{1,2}):(\d{2})$#', $criteria[2], $end_time))
						{
							if ($criteria['condition3'])
							{
								$start = gmmktime($start_time[1], $start_time[2]);
								$end   = gmmktime($end_time[1], $end_time[2]);
								// $now   = gmmktime();
								$conditional_prefix .= '<vb:if condition="$' . 'now = gmmktime()"></vb:if>';
							}
							else
							{
								$start = mktime($start_time[1], $start_time[2]) + $vbulletin->options['hourdiff'];
								$end   = mktime($end_time[1], $end_time[2]) + $vbulletin->options['hourdiff'];
								// $now   = mktime() + $vbulletin->options['hourdiff'];
								$conditional_prefix .= '<vb:if condition="$' . 'now = mktime() + ' . $vbulletin->options['hourdiff'] . '"></vb:if>';
							}
							$conditional_prefix .= '<vb:if condition="$' . 'now > ' . $start . ' OR $' . 'now < ' . $end . '">';
							$conditional_postfix .= '</vb:if>';
						}
						break;
					case "ad_x_not_displayed":
						// no ad shown? make note of it, and create the array for us
						$conditional_prefix .= '<vb:if condition="$noadshown = !isset($' . 'adsshown)"></vb:if>';
						$conditional_prefix .= '<vb:if condition="$noadshown"><vb:if condition="$' . 'adsshown = array()"></vb:if></vb:if>';
						// if no ads shown, OR ad x have not been shown, show the ad
						$conditional_prefix .= '<vb:if condition="$noadshown OR !in_array(' . intval($criteria['condition1']) . ', $' . 'adsshown)">';
						$conditional_postfix .= '</vb:if>';
						break;
					default:
						($hook = vBulletinHook::fetch_hook('ad_check_criteria')) ? eval($hook) : false;
						break;
				}
			}
			// add a faux conditional before all the closing conditions to mark that we've shown certain ad already
			$conditional_postfix = '<vb:if condition="$' . 'adsshown[] = ' . $adid . '"></vb:if>' . $conditional_postfix;

			// wrap the conditionals around their ad snippet / template
			$template .= $conditional_prefix . $ad['snippet'] . $conditional_postfix;
		}
	}

	return $template;
}

// #############################################################################
/**
* Function to replace ad code into correct template
*
* @param	string	Style for template
* @param	string	Ad location
* @param	string	Template compiled
* @param	string	Template uncompiled
* @param	string	Username for the edit
* @param	string	Version of the template
* @param	string	Product that uses this template
*/
function replace_ad_template($styleid, $location, $template, $template_un, $username, $templateversion, $product='vbulletin')
{
	global $db;
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "template SET
			styleid = '".$db->escape_string($styleid)."',
			title = 'ad_" . $db->escape_string($location) . "',
			template = '" . $db->escape_string($template) . "',
			template_un = '" . $db->escape_string($template_un) . "',
			templatetype = 'template',
			dateline = " . TIMENOW . ",
			username = '" . $db->escape_string($username) . "',
			version = '" . $db->escape_string($templateversion) . "',
			product = '".$db->escape_string($product)."'
	");
}

// #############################################################################
/**
* Function to wrap ad template in a div with the correct id
*
* @param	string	Template String
* @param	string	Ad location (global_header1)
* @param	string	ID Prefix (Default: 'ad_')
*/
function wrap_ad_template($template, $id_name, $id_prefix='ad_')
{
	if (!$template)
	{
		return '';
	}

	// wrap the template in a div with the correct id
	$template_wrapped = '<div id="' . $id_prefix . $id_name . '">' . $template . '</div>';

	return $template_wrapped;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 41161 $
|| ####################################################################
\*======================================================================*/
?>