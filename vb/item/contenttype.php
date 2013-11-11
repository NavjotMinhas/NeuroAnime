<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * ContentType item class.
 * A content type constitutes it's id, package class identifier and content class
 * identifier.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Item_ContentType extends vB_Item
{
	/*ModelProperties===============================================================*/

	/**
	 * Array of all valid model properties.
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		/*INFO_BASIC==================*/
		'class',		'package',
		'enabled',		'packageid'
	);


	/*INFO_BASIC==================*/

	/**
	 * A class identifier.
	 * This is used with the package class to resolve related class names.
	 *
	 * @var string
	 */
	protected $class;

	/**
	 * A package class identifier.
	 * This is used with the contenttype class identifer to resolve related class
	 * names.
	 *
	 * @var string
	 */
	protected $package;

	/**
	 * Whether the content type is enabled.
	 * A content type is disabled if it's package is disabled.
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * The integer id of the package.
	 *
	 * @var int
	 */
	protected $packageid;



	/*LoadInfo======================================================================*/

	/**
	 * Applies info to the model object.
	 * ContentTypes always load themselves.
	 *
	 * @param array mixed $info					- Property => value
	 * @param int $info_flags					- The info being loaded.
	 */
	public function setInfo($info, $loaded_info = self::INFO_BASIC)
	{
		return;
	}


	/**
	 * Loads required info.
	 *
	 * @return array							- Returns the entire fetched collection
	 */
	protected function loadInfo()
	{
		if ($this->requireLoad(self::INFO_BASIC))
		{
			return $this->loadTypeInfo();
		}

		return true;
	}

	/**
	 * Loads the contenttype and package type info.
	 */
	protected function loadTypeInfo()
	{
		$this->class = vB_Types::instance()->getContentTypeClass($this->getId());
		$this->package = vB_Types::instance()->getContentTypePackage($this->getId());
		$this->packageid = vB_Types::instance()->getPackageID($this->package);
		$this->enabled = vB_Types::instance()->packageEnabled($this->packageid);
		$this->loaded_info |= self::INFO_BASIC;
		return true;
	}



	/*Accessors=====================================================================*/

	/**
	 * Fetches the class identifier of the content type.
	 *
	 * @return string
	 */
	public function getClass()
	{
		$this->Load();

		return $this->class;
	}


	/**
	 * Fetches the class identifier of the package that the content type belongs to.
	 *
	 * @return string
	 */
	public function getPackageClass()
	{
		$this->Load();

		return $this->package;
	}


	/**
	 * Fetches the id of the package that the content type belongs to.
	 *
	 * @return int
	 */
	public function getPackageId()
	{
		$this->Load();

		return $this->packageid;
	}


	/**
	 * Returns whether the contenttype's package is enabled.
	 * Note: This should never really return false as it should not have been
	 * instantiated if the package is not enabled.
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		$this->Load();

		return $this->enabled;
	}


	/**
	 * Returns the descriptive title of the contenttype.
	 * Note: The title is not defined as part of the stored contenttype but instead
	 * is evaluated as a phrase from the contenttype's package and class.
	 */
	public function getTitle()
	{
		return vB_Types::instance()->getContentTypeTitle($this->itemid);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
