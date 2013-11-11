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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_408 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '408';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.8';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.7';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/**
	* Step #1
	*
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'customprofile') ,
			"CREATE TABLE " . TABLE_PREFIX . "customprofile (
			customprofileid integer AUTO_INCREMENT,
			title VARCHAR(100),
			thumbnail VARCHAR(255),
			userid INT NOT NULL,
			themeid INT,
			font_family VARCHAR(255),
			fontsize VARCHAR(20),
			title_text_color VARCHAR(20),
			page_background_color VARCHAR(20),
			page_background_image VARCHAR(255),
			page_background_repeat  VARCHAR(20),
			module_text_color VARCHAR(20),
			module_link_color VARCHAR(20),
			module_background_color VARCHAR(20),
			module_background_image VARCHAR(255),
			module_background_repeat VARCHAR(20),
			module_border VARCHAR(20),
			content_text_color VARCHAR(20),
			content_link_color VARCHAR(20),
			content_background_color VARCHAR(20),
			content_background_image VARCHAR(255),
			content_background_repeat VARCHAR(20),
			content_border VARCHAR(20),
			button_text_color VARCHAR(20),
			button_background_color VARCHAR(20),
			button_background_image VARCHAR(255),
			button_background_repeat VARCHAR(20),
			button_border VARCHAR(20),
			moduleinactive_text_color varchar(20),
			moduleinactive_link_color varchar(20),
			moduleinactive_background_color varchar(20),
			moduleinactive_background_image varchar(255),
			moduleinactive_background_repeat varchar(20),
			moduleinactive_border varchar(20),
			headers_text_color varchar(20),
			headers_link_color varchar(20),
			headers_background_color varchar(20),
			headers_background_image varchar(255),
			headers_background_repeat varchar(20),
			headers_border varchar(20),
			page_link_color varchar(20),
			PRIMARY KEY  (customprofileid),
			KEY(userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'dbquery'),
			"CREATE TABLE " . TABLE_PREFIX . "dbquery (	dbqueryid varchar(32) NOT NULL,
				querytype enum('u','d', 'i', 's') NOT NULL,
				query_string text,
				PRIMARY KEY (dbqueryid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->run_query(
		sprintf($this->phrase['version']['408']['granting_profile_customization_permission']),
		"UPDATE " . TABLE_PREFIX . "usergroup set usercsspermissions  = (usercsspermissions | 192) where usercsspermissions > 0;"
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$items = array(
			'text' => array(
				'font_family'      => 'font_family',
			),
			'main' => array(
				'background_color' => 'page_background_color',
				'background_image' => 'page_background_image',
				'background_repeat'=> 'page_background_repeat',
			),
			'tableheader' => array(
				'color'            => 'module_text_color',
				'background_color' => 'module_background_color',
				'background_image' => 'module_background_image',
				'background_repeat'=> 'module_background_repeat',
			),
			'alternating' => array(
				'color'            => 'content_text_color',
				'linkcolor'        => 'content_link_color',
				'background_color' => 'content_background_color',
				'background_image' => 'content_background_image',
				'background_repeat'=> 'content_background_repeat',
			),
			'inputs'      => array(
				'color'            => 'button_text_color',
				'background_color' => 'button_background_color',
				'background_image' => 'button_background_image',
				'background_repeat'=> 'button_background_repeat',
				'border_color'     => 'button_border',
			)
		);
		
		$total = 1;
		foreach ($items AS $selector => $properties)
		{
			foreach ($properties AS $property => $pr)
			{
				$total++;
			}
		}

		$this->run_query(
		sprintf($this->phrase['version']['408']['converting_3x_customization'], 1, $total),
			"INSERT into " . TABLE_PREFIX . "customprofile(userid)
			SELECT distinct u.userid FROM " . TABLE_PREFIX . "user u INNER JOIN " . TABLE_PREFIX . "usercss css
			ON css.userid = u.userid
			LEFT JOIN " . TABLE_PREFIX . "customprofile cp ON cp.userid = u.userid
			WHERE cp.customprofileid IS NULL;"
		);

		$count = 2;
		foreach ($items AS $selector => $properties)
		{
			foreach ($properties AS $property => $pr)
			{
				$this->run_query(
				sprintf($this->phrase['version']['408']['converting_3x_customization'], $count, $total),
				"UPDATE " . TABLE_PREFIX . "customprofile pr
					INNER JOIN " . TABLE_PREFIX . "usercss c ON c.userid = pr.userid AND c.selector = '$selector' AND property = '$property'
					SET pr.$pr = c.value;"
				);
				$count++;
			}
		}

		$this->long_next_step();
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'post', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "post DROP INDEX userid, ADD INDEX userid (userid, parentid)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "profilefield"),
			"UPDATE " . TABLE_PREFIX . "profilefield
			SET
				maxlength=16384, size=50, type='textarea'
			WHERE
				profilefieldid = 1
		");
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'postparsed'),
			"TRUNCATE TABLE " . TABLE_PREFIX . "postparsed"
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'sigparsed'),
			"TRUNCATE TABLE " . TABLE_PREFIX . "sigparsed"
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		if ($this->field_exists('blog_textparsed', 'blogtextid')) // table exists
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], 'blog_textparsed'),
				"TRUNCATE TABLE " . TABLE_PREFIX . "blog_textparsed"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #10 - Update default post icon since gif version is removed in 4.0.8
	*
	*/
	function step_10()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
			"UPDATE " . TABLE_PREFIX . "setting
			SET value = REPLACE(value, 'images/icons/icon1.gif', 'images/icons/icon1.png')
			WHERE varname = 'showdeficon'
		");
	}

	/**
	* Step #11 - Change the gif smilies to the new png images
	*
	*/
	function step_11()
	{
		$smilies_to_change = array (
			'smile', 'redface', 'biggrin', 'wink', 'tongue', 'cool',
			'rolleyes', 'mad', 'eek', 'confused', 'frown'
		);
		$i = 0;
		foreach ($smilies_to_change as $smilie)
		{
			$i++;
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table_x'], 'smilie', $i, count($smilies_to_change)),
				"UPDATE " . TABLE_PREFIX . "smilie
				SET smiliepath = REPLACE(smiliepath, 'images/smilies/$smilie.gif', 'images/smilies/$smilie.png')
				WHERE imagecategoryid = 1 AND
					smiliepath LIKE '%images/smilies/$smilie.gif'"
			);
		}
	}

	/**
	* Step #12 - Change the gif post icons to the new png images
	*
	*/
	function step_12()
	{
		for ($i = 1; $i < 15; ++$i)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table_x'], 'icon', $i, 15),
				"UPDATE " . TABLE_PREFIX . "icon
				SET iconpath = REPLACE(iconpath, 'images/icons/icon$i.gif', 'images/icons/icon$i.png')
				WHERE imagecategoryid = 2 AND
					iconpath LIKE '%images/icons/icon$i.gif'"
			);
		}
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'product', 1, 1),
			"UPDATE " . TABLE_PREFIX . "product SET versioncheckurl = '', url= '' WHERE productid = 'vbblog'"
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'searchgroup_text'),
			"DELETE " . TABLE_PREFIX . "searchgroup_text
			FROM " . TABLE_PREFIX . "searchgroup_text
			LEFT JOIN " . TABLE_PREFIX . "searchgroup ON (" . TABLE_PREFIX . "searchgroup.searchgroupid = " . TABLE_PREFIX . "searchgroup_text.searchgroupid)
			WHERE " . TABLE_PREFIX . "searchgroup.searchgroupid IS NULL"
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], 'searchcore_text'),
			"DELETE " . TABLE_PREFIX . "searchcore_text
			FROM " . TABLE_PREFIX . "searchcore_text
			LEFT JOIN " . TABLE_PREFIX . "searchcore ON (" . TABLE_PREFIX . "searchcore.searchcoreid = " . TABLE_PREFIX . "searchcore_text.searchcoreid)
			WHERE " . TABLE_PREFIX . "searchcore.searchcoreid IS NULL"
		);

		// We have changed smilies and post icons
		require_once(DIR . '/includes/adminfunctions.php');
		build_image_cache('smilie');
		build_image_cache('icon');
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$stylevarbase = implode(',', array(
			"'forummenu_background'",
			"'body_background'",
			"'forummenu_border'",
			"'forummenu_color'",
			"'forummenu_font'",
			"'albumtop_list_a_color'",
			"'albumtop_list_a_hover_color'",
			"'forum_sidebar_background'",
			"'forum_sidebar_border'",
			"'forum_sidebar_content_background'",
			"'forum_sidebar_content_border'",
			"'forum_sidebar_content_color'",
			"'mid_fontSize'",
			"'forum_sidebar_contentavatar_width'",
			"'forum_sidebar_content_separator_background'",
			"'forum_sidebar_content_separator_height'",
			"'usercp_forum_icon_legend_top_border'",
			"'forum_sidebar_header_color'",
			"'forum_sidebar_header_font'",
			"'wgo_background'",
			"'wgo_border'",
			"'body_color'",
			"'wgo_fontSize'",
			"'wgoheader_background'",
			"'wgoheader_border'",
			"'wgoheader_color'",
			"'wgoheader_font'",
			"'wgosubheader_font'",
		));

		$this->show_message($this->phrase['version']['408']['retrieving_customized_stylevar_values']);
		$style_result = $this->db->query_read("
			SELECT stylevarid, styleid, value
			FROM " . TABLE_PREFIX . "stylevar
			WHERE
				stylevarid IN (" . $stylevarbase . ")
				AND
				styleid > 0
		");
		$stylevar = array();
		while ($style_row = $this->db->fetch_array($style_result))
		{
			if (!isset($stylevar[$style_row['stylevarid']]))
			{
				$stylevar[$style_row['stylevarid']] = array();
			}
			$stylevar[$style_row['stylevarid']][$style_row['styleid']] = $style_row['value'];
		}

		$this->show_message($this->phrase['version']['408']['mapping_customized_stylevars']);
		$stylevarmap = array(
			array('new' => 'toolsmenu_background', 			'old' => 'forummenu_background', 			'part' => ''),
			array('new' => 'toolsmenu_bevel', 			'old' => 'body_background', 				'part' => 'color'),
			array('new' => 'toolsmenu_border', 			'old' => 'forummenu_border', 				'part' => ''),
			array('new' => 'toolsmenu_color', 			'old' => 'forummenu_color', 				'part' => ''),
			array('new' => 'toolsmenu_fontSize', 			'old' => 'forummenu_font', 				'part' => 'units,size'),
			array('new' => 'toolsmenu_link_color', 			'old' => 'albumtop_list_a_color', 			'part' => ''),
			array('new' => 'toolsmenu_linkhover_color', 		'old' => 'albumtop_list_a_hover_color', 		'part' => ''),
			array('new' => 'sidebar_background', 			'old' => 'forum_sidebar_background', 			'part' => ''),
			array('new' => 'sidebar_border', 			'old' => 'fourm_sidebar_border', 			'part' => ''),
			array('new' => 'sidebar_content_background', 		'old' => 'forum_sidebar_content_background', 		'part' => ''),
			array('new' => 'sidebar_content_border', 		'old' => 'forum_sidebar_content_border',		'part' => ''),
			array('new' => 'sidebar_content_bevel', 		'old' => 'forum_sidebar_content_background', 		'part' => 'color'),
			array('new' => 'sidebar_content_color',			'old' => 'forum_sidebar_content_color', 		'part' => ''),
			array('new' => 'sidebar_content_fontSize',		'old' => 'mid_fontSize', 				'part' => ''),
			array('new' => 'sidebar_contentavatar_width',		'old' => 'forum_sidebar_contentavatar_width', 		'part' => ''),
			array('new' => 'sidebar_contentseparator_background',	'old' => 'forum_sidebar_content_separator_background',	'part' => ''),
			array('new' => 'sidebar_contentseparator_height', 	'old' => 'forum_sidebar_content_separator_height', 	'part' => ''),
			array('new' => 'sidebar_contentlist_separator', 	'old' => 'usercp_forum_icon_legend_top_border',		'part' => ''),
			array('new' => 'sidebar_header_color',			'old' => 'forum_sidebar_header_color',			'part' => ''),
			array('new' => 'sidebar_header_fontSize',		'old' => 'forum_sidebar_header_font',			'part' => 'units,size'),
			array('new' => 'secondarycontent_background',		'old' => 'wgo_background',				'part' => ''),
			array('new' => 'secondarycontent_border',		'old' => 'wgo_border',					'part' => ''),
			array('new' => 'secondarycontent_color',		'old' => 'body_color',					'part' => ''),
			array('new' => 'secondarycontent_fontSize', 		'old' => 'wgo_fontSize',				'part' => ''),
			array('new' => 'secondarycontent_header_background',	'old' => 'wgoheader_background', 			'part' => ''),
			array('new' => 'secondarycontent_header_border',	'old' => 'wgoheader_border',				'part' => ''),
			array('new' => 'secondarycontent_header_color',		'old' => 'wgoheader_color',				'part' => ''),
			array('new' => 'secondarycontent_header_fontSize', 	'old' => 'wgoheader_font',				'part' => ''),
			array('new' => 'secondarycontent_subheader_fontSize',	'old' => 'wgosubheader_font', 				'part' => 'units,size'),
		);

		// check for existing target stylevars so we know whether to insert or update
		$target_stylevars = array();
		foreach ($stylevarmap AS $map)
		{
			$target_stylevars[] = $this->db->escape_string($map['new']);
		}
		$existing_result = $this->db->query_read("
			SELECT stylevarid
			FROM " . TABLE_PREFIX . "stylevar
			WHERE
				stylevarid IN ('" . implode("', '", $target_stylevars) . "')
				AND
				styleid > 0
		");
		$existing_target_stylevars = array();
		while($row = $this->db->fetch_array($existing_result))
		{
			$existing_target_stylevars[$row['stylevarid']] = true;
		}

		// map the stylevars
		foreach ($stylevarmap as $map)
		{
			if (!is_array($stylevar[$map['old']]))
			{
				continue; // source stylevar hasn't been customized
			}

			foreach ($stylevar[$map['old']] AS $styleid => $source_stylevar)
			{
				if (!empty($map['part']))
				{
					$values = array();
					$parts = array();
					$parts = explode(',', $map['part']);
					$v = unserialize($source_stylevar);
					for ($i = 0; $i < count($parts); $i++)
					{
						$values[$parts[$i]] = $v[$parts[$i]];
					}
					$newvalue = serialize($values);
				}
				else
				{
					$newvalue = $source_stylevar;
				}

				// we never want to UPDATE, as that would overwrite stylevar customizations
				if (!isset($existing_target_stylevars[$map['new']]))
				{
					$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'stylevar'),
						"INSERT INTO " . TABLE_PREFIX . "stylevar
						(stylevarid, styleid, value)
						VALUES(
							'" . $this->db->escape_string($map['new']) . "',
							" . intval($styleid) . ",
							'" . $this->db->escape_string($newvalue) . "'
						)
					");
				}
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/