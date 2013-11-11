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

class vB_APIMethod_api_mobilepublisher extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin, $db;

		// Smilies
		$result = $db->query_read_slave("
			SELECT smilietext AS text, smiliepath AS path, smilie.title, smilieid,
				imagecategory.title AS category
			FROM " . TABLE_PREFIX . "smilie AS smilie
			LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
			ORDER BY imagecategory.displayorder, imagecategory.title, smilie.displayorder
		");

		$categories = array();
		while ($smilie = $db->fetch_array($result))
		{
			$categories[$smilie['category']][] = $smilie;
		}

		

		$data = array(
			'bburl' => $vbulletin->options['bburl'],
			'smilies' => $categories,
			'logo' => vB_Template_Runtime::fetchStyleVar('titleimage'),
			'colors' => array(
				'titletext' => vB_Template_Runtime::fetchStyleVar('navbar_tab_color'),
				'primarytext' => vB_Template_Runtime::fetchStyleVar('link_color'),
				'secondarytext' => vB_Template_Runtime::fetchStyleVar('forumbits_text_color'),
				'bodytext' => vB_Template_Runtime::fetchStyleVar('postbit_color'),
				'highlighttext' => vB_Template_Runtime::fetchStyleVar('body_color'),
				'background' => vB_Template_Runtime::fetchStyleVar('forumhead_background.backgroundColor'),
				'foreground' => vB_Template_Runtime::fetchStyleVar('forumrow_background.backgroundColor'),
				'buttoncolor' => vB_Template_Runtime::fetchStyleVar('control_background.backgroundColor'),
				'highlightcolor' => vB_Template_Runtime::fetchStyleVar('notices_background.backgroundColor'),
			)
		);

		return $data;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/