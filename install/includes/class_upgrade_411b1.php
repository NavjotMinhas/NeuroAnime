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

class vB_Upgrade_411b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '411b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.1 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.1 Alpha 1';

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
		//delete any orphaned cms_article records. These are created by deleting articles from
		// the admincp content manager, fixed in this release.
		$contentinfo = $this->db->query_first("SELECT c.contenttypeid FROM " . TABLE_PREFIX .
		"contenttype c INNER JOIN " . TABLE_PREFIX . "package AS p ON p.packageid = c.packageid
		WHERE c.class='Article' AND p.productid = 'vbcms' ;");

		if ($contentinfo AND $contentinfo['contenttypeid'])
		{
			$this->run_query(
			$this->phrase['version']['411']['delete_orphan_articles'],
			"DELETE a FROM " . TABLE_PREFIX . "cms_article AS a LEFT JOIN " . TABLE_PREFIX .
			"cms_node AS n ON (n.contentid = a.contentid AND n.contenttypeid = " . $contentinfo['contenttypeid'] .")
			WHERE n.contentid IS NULL;");
		}
		else
		{
			$this->skip_message();
		}
	}

	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cache', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "cache CHANGE data data MEDIUMTEXT"
		);
	}

	/**
	 * Step #3
	 *
	 */
	function step_3()
	{
		//From 4.1.0 we could have a setting record with volatile = 0. That would be bad in finalupgrade.
			$this->run_query(
			sprintf($this->phrase['version']['411']['setting_volatile_flag'], 'socnet'),
			"UPDATE " . TABLE_PREFIX . "setting SET volatile=1 where varname='socnet';");

	}
 }

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/