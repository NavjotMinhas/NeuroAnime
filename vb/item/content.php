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
 * Content Item Model.
 * The content item model is responsible for providing an interface to access content
 * info that is both common to all content types and specific to a content type.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Item_Content extends vB_Item
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this content.
	 *
	 * @var string
	 */
	protected $package;

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this content.
	 * Note: The class should always be resolvable from a contenttypeid using the
	 * contenttype table.
	 *
	 * @var string
	 */
	protected $class;



	/*ModelProperties===============================================================*/

	/**
	 * Array of all valid model properties.
	 * This is used to check if a class property can be set as a property and allows
	 * automagic setting of properties from db info results.
	 * @see Load()
	 * @see setInfo()
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		'title',		'userid',
		'description'
	);


	/*INFO_BASIC==================*/

	/**
	 * The title of the content item.
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * The id of the user that created the content.
	 *
	 * @var int
	 */
	protected $userid;

	/**
	 * A description of the content.
	 *
	 * @var string
	 */
	protected $description;


	/*Initialisation================================================================*/

	/**
	 * Constructs the content item.
	 * The id passed will usually be the primary key of the model data in the
	 * database but as this is model specific it can be interpreted in other ways.
	 *
	 * @param mixed $itemid					- The id of the item
	 * @param int $load_flags				- Any required info prenotification
	 */
	public function __construct($itemid = false, $load_flags = false)
	{
		if (!$this->package OR !$this->class)
		{
			throw (new vB_Exception_Content('No package or contenttype class defined for content item '));
		}

		parent::__construct($itemid, $load_flags);
	}


	/**
	 * Factory method for creating a type specific content item.
	 * Note: The contentid is interpreted at each contenttype's descretion.
	 *
	 * @param string $package_class				- The class identifier for the package
	 * @param string $contenttype_class			- The class identifier for the contenttype
	 * @param int $contentid					- The primary key value of the content
	 * @param int $load_flags					- Notify flags for loading info
	 */
	public static function create($package_class, $contenttype_class, $contentid = false)
	{
		$class = $package_class . '_Item_Content_' . $contenttype_class;
		return new $class($contentid);
	}



	/*Accessors=====================================================================*/

	/**
	 * Returns the class identifier.
	 * This is used to resolve related classes.
	 *
	 * @return string
	 */
	public function getClass()
	{
		return $this->class;
	}


	/**
	 * Returns the package identifier.
	 * This is used to resolve related classes.
	 *
	 * @return string
	 */
	public function getPackage()
	{
		return $this->package;
	}


	/**
	 * Returns the creator userid.
	 *
	 * @return int
	 */
	public function getUserId()
	{
		$this->Load();

		return $this->userid;
	}


	/**
	 * Returns a human readable title for the content.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		$this->Load();

		return $this->title;
	}


	/**
	 * Returns a user friendly description of the content.
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return '';
	}


	/**
	 * Returns a user friendly title of the content's contenttype.
	 */
	public function getTypeTitle()
	{
		return vB_Types::instance()->getContentTypeTitle(array('package' => $this->getPackage(), 'class' => $this->getClass()));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/