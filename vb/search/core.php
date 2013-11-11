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
 * Classes comprising the Core of the vbulletin search system
 *
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

/**
 * Main entry point to the search system including factory methods and other items.
 *
 * Search works on the basis of registering various objects with the search core.
 * Modules that expose search types need to register objects to handle indexing, permissions,
 * grouping, and display.
 *
 * Search implementations need to implement modules that handles generic indexing and core search.
 * Implementations my register objects to override the default
 *
 * This is a singleton object
 * @package vBulletin
 * @subpackage Search
 */
class vB_Search_Core
{
	//we still need this one for the time being.
	const TYPE_COMMON = 'common';
	const SEARCH_COMMON = 'common';
	const SEARCH_ADVANCED = 'advanced';
	const SEARCH_NEW = 'new';
	const SEARCH_TAG = 'tag';

	//group types.
	//const TYPE_THREAD = 'thread';
  // const TYPE_SOCIAL_GROUP = 'SocialGroup';
	//group types
	const GROUP_YES = 1;
	const GROUP_NO = 2;
	const GROUP_DEFAULT = 3;

	//search comparison operators
	const OP_EQ = 'eq';
	const OP_NEQ = 'neq';
	const OP_LT = 'lt';
	const OP_GT = 'gt';

	/**
	*	Map of contenttype ids to package/class string
	* Hopefully temporary (should initialize from package system)
	*
	*	This represents all searchable types
	* @var
	*/
	private static $instance = null;

	/**
	 * Returns the singleton instance for the search core
	 *
	 * @return vB_Search_Core
	 */
	public static function get_instance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new vB_Search_Core();

			//initialize the search implementation
			global $vbulletin;
			if (!empty($vbulletin->options['searchimplementation']))
			{
				call_user_func(array($vbulletin->options['searchimplementation'], 'init'));
			}
		//	self::$instance->register_search_controller('vb', 'Tag', new vb_Search_SearchController_Tag());
		}
		return self::$instance;
	}

	/**
	 * Create the core search object
	 *
	 * Registers default indexers and search result classes for core searchable items
	 */
	public function __construct()
	{
		//ensure that the framework is initialized.
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
	}

	/**
	*	@deprecated -- should use the vB_Types class directly
	*/
	public function get_contenttypeid($package, $class)
	{
		return vB_Types::instance()->getContentTypeID(array('package' => $package, 'class' => $class));
		}

	/**
	*/
	private function get_contenttypestrings($contenttypeid)
	{
		$instance = vB_Types::instance();

		if (! $contenttypeid)
		{
			return array(false,false);
		}
		try
		{
			$package = $instance->getContentTypePackage($contenttypeid);
			$class = $instance->getContentTypeClass($contenttypeid);
			return array($package, $class);
		}
		catch (vB_Exception_Warning $e)
		{
			return array(false,false);
		}
	}

	public function get_indexed_types()
	{
		if (is_null($this->indexed_types))
		{
			$collection = new vB_Collection_ContentType();
			$collection->filterSearchable(true);

			$this->indexed_types = array();
			foreach ($collection AS $type)
			{
				$search_type = $this->get_search_type_from_id($type->getID());
				if ($search_type->is_enabled())
				{
					$value = array();
					$value['contenttypeid'] = $type->getID();
					$value['package'] = $type->getPackageClass();
					$value['class'] = $type->getClass();
					$this->indexed_types[$type->getID()] = $value;
				}
			}
		}
		return $this->indexed_types;
	}

	//*************************************************************************
	//Registration/Factory methods

	/**
	 * Factory Method -- Get the index controller.
	 *
	 * This is going to be a bit awkward because, for the most part, a bit of code is
	 * going to need to know what kind of contoller its dealing with -- there are going
	 * to be too many content specific functions needed to deal with.  We'll keep it
	 * as generic as possible though just is case.  Despite this, all access to the
	 * search system should go through this class.
	 *
	 * @param string The content type identifier
	 * @return vB_Index_Controller_Base
	 */
	public function get_index_controller($package, $contenttype)
	{
		$key = "$package:$contenttype";
		if (array_key_exists($key, $this->index_controllers))
		{
			return $this->index_controllers[$key];
		}
		else
		{
			$indexer = $this->load_object($package, $contenttype, 'IndexController');
			if ($indexer)
			{
				$this->register_index_controller($package, $contenttype, $indexer);
				return $this->index_controllers[$key];
			}
			else
			{
				//We have redone this completely. If we request a
				// controller that we don't have available, we have
				// one available that always returns false. That
				// allows us at least to fail gracefully.
				$path = DIR . strtolower("/vb/search/indexcontroller/null.php");
				return(new vb_Search_Indexcontroller_Null);
			}
		}
	}

	public function get_index_controller_by_id($contenttypeid)
	{
		list($package, $contenttype) = $this->get_contenttypestrings($contenttypeid);
		if ($package)
		{
			return $this->get_index_controller($package, $contenttype);
		}
		else
		{
			//We have redone this completely. If we request a
			// controller that we don't have available, we have
			// one available that always returns false. That
			// allows us at least to fail gracefully.
			$path = DIR . strtolower("/vb/search/indexcontroller/null.php");
			return(new vb_Search_Indexcontroller_Null);
		}
	}

	private function load_object($package, $contenttype, $type)
	{
		//rely on the autoloader to check the class validity
		$class = $package . '_Search_' . $type . '_' . $contenttype;
		if (class_exists($class))
		{
			return new $class;
		}
		else
		{
			return null;
		}
	}

	/**
	*	Glue code to handle existing single field contenttype calls
	*/
	private function get_package_and_type($package, $contenttype)
	{
		return array($package, $contenttype);
	}

	public function get_tag_search_controller()
	{
		return new vb_Search_SearchController_Tag();
	}

	public function get_newitem_search_controller($package, $contenttype)
	{
		$searchcontroller = $this->load_object($package, "New$contenttype", 'SearchController');
		if (!$searchcontroller)
		{
			throw new Exception("No search controller defined for $contenttype");
		}
		return $searchcontroller;
	}

	public function get_newitem_search_controller_by_id($contenttypeid)
	{
		list($package, $contenttype) = $this->get_contenttypestrings($contenttypeid);
		if ($package)
		{
			return $this->get_newitem_search_controller($package, $contenttype);
		}
		else
		{
			throw new Exception("No search controller defined for $contenttype");
		}
	}


	/**
	 * Factory Method -- Get the search controller
	 *
	 * @return vB_Search_Controller
	 */
	public function get_search_controller($package = null, $contenttype = null)
	{
		//if we find a specific searchcontroller, then return it.
 		if ($package AND $contenttype)
		{
			$key = "$package:$contenttype";
			if (array_key_exists($key, $this->search_controllers))
			{
				return $this->search_controllers[$key];
			}

			else
			{
				$searchcontroller = $this->load_object($package, $contenttype, 'SearchController');
				if ($searchcontroller)
				{
					$this->register_search_controller($package, $contenttype, $searchcontroller);
					return $searchcontroller;
				}
			}
		}

		//if we fall through the above, use the default search controller.
		if ($this->default_controller)
		{
			return $this->default_controller;
		}
		else
		{
			//todo proper error handling.
			//this is an internal error, but we probalby need a better message
			//in case a product that registers a search intexer is improperly
			//installed.
			throw new Exception("No search controller defined for $contenttype");
		}
	}

	public function get_search_controller_by_id($contenttypeid)
	{
		list($package, $contenttype) = $this->get_contenttypestrings($contenttypeid);
		if ($package)
		{
			return $this->get_search_controller($package, $contenttype);
		}
		else
		{
			//if we fall through the above, use the default search controller.
			if ($this->default_controller)
			{
				return $this->default_controller;
			}
			return $this->get_search_controller();
		}
	}

	/**
	*	This doesn't demand a factory method really, but it feels weird
	* directly instantiating search objects
	*/
	public function create_criteria($search_type = null)
	{
		return new vB_Search_Criteria($search_type);
	}

	/**
	*	Get the type object registered for a given search result type
	*
	*	If no type is registered for that type vB_Search_Type_Null is returned
	* instead (which will quietly exclude all items of the type from the
	* result set)
	*
	* @param string The content type identifier
	* @return vB_Search_Type The type object registered for the type.
	*/
	public function get_search_type($package, $contenttype=null)
	{
		list($package, $contenttype) = $this->get_package_and_type($package, $contenttype);
		$key = "$package:$contenttype";

		if (array_key_exists($key, $this->result_types))
		{
			return $this->result_types[$key];
		}
		else
		{
			$type = $this->load_object($package, $contenttype, 'type');
			if ($type)
			{
				$this->register_search_type($package, $contenttype, $type);
				return $type;
			}
			else
			{
				require_once(DIR . '/vb/search/type/null.php');
				return new vB_Search_Type_Null();
			}
		}
	}

	public function get_search_type_from_id($contenttypeid)
	{
		global $vbulletin;

		list($package, $contenttype) = $this->get_contenttypestrings($contenttypeid);
		if ($package)
		{
			return $this->get_search_type($package, $contenttype);
		}
		else
		{
			require_once(DIR . '/vb/search/type/null.php');
			return new vB_Search_Type_Null();
		}
	}

	public function get_cansearch_from_id($contenttypeid)
	{
		if (is_null($this->indexed_types))
		{
			$this->get_indexed_types();
		}

		if (array_key_exists($contenttypeid, $this->indexed_types))
		{
			return true;
		}
		return false;
	}

	public function get_cansearch($package, $contenttype)
	{
		if (is_null($this->indexed_types_by_key))
		{
			if (is_null($this->indexed_types))
			{
				$this->get_indexed_types();
			}

		}
		foreach ($this->indexed_types as $type => $data)
		{
			$key = $data['package'] . ':' . $data['class'];
			$this->indexed_types_by_key[$key] = $type;
		}
		return array_key_exists($package. ':' . $contenttype, $this->indexed_types_by_key);
	}

	/**
	 * Return the core indexer
	 *
	 * @return vB_Item_Indexer_Base
	 */
	public function get_core_indexer()
	{
		return $this->core_indexer;
	}

	/**
	 *	Register an index controller
	 *
	 * Each item type to be searched needs a controller to handling indexing.  The
	 * products defining the item are responsible for creating and index interface
	 * to handle all of the change events that can affect searchable fields.
	 *	The indexer should be written to use the functionality of the core indexer
	 * interface (which, among other things allows search implementations to
	 * index unknown content types).  Any cascade logic (such as changes to threads
	 * requiring updates to posts) should be placed here rather than the product
	 * code, which will allow search implementations to extend and customize
	 * the item indexers for efficiency.
	 *
	 * Note that since items have different potential change events and the indexer
	 * iterfaces need to reflect those events, each indexer will have its own
	 * unique interface overall.  Implementations that choose to override an indexer
	 * must take care to implement the full interface for each indexer so extended.
	 * Use this with extreme care.
	 *
	 *	@param string The content type identifier
	 *	@param vB_Search_IndexController The index controller for the content type
	 */
	public function register_index_controller($package, $contenttype, vB_Search_IndexController $index_controller)
	{
		$this->index_controllers["$package:$contenttype"] = $index_controller;
	}

	/**
	 * Register a search controller
	 *
	 * Registers the controller for searching for a particular content type.  The implementation
	 * must register a controller for TYPE_COMMON which should return results for all indexed
	 * types (based on common fields).  It may also register controllers for individual types
	 * which will return results of that type only (to support type specific advanced search
	 * features)
	 *
	 * @param package The package name for the search controller
	 *	@param string The content type identifier
	 *	@param vB_Search_SearchController The search controller for the content type
	 */
	public function register_search_controller($package, $contenttype, vB_Search_SearchController $search_controller)
	{
		$this->search_controllers["$package:$contenttype"] = $search_controller;
	}


	/**
	 * Register a default search controller
	 *
	 * Registers a default controller.
	 *
	 * @param package The package name for the search controller
	 *	@param string The content type identifier
	 *	@param vB_Search_SearchController The search controller for the content type
	 */
	public function register_default_controller(vB_Search_SearchController $search_controller)
	{
		$this->default_controller = $search_controller;
	}

	/**
	 * Register the type object to use to handle results of that type.
	 *
	 * Class to handle permissions and display for a search result.
	 * (These should generally be registered by VB Products rather than search implementations)
	 *
	 * @param string The content type identifier
	 * @param vB_Search_Result_Type The type object to register
	 */
	public function register_search_type($package, $contenttype, $type)
	{
		$this->result_types["$package:$contenttype"] = $type;
	}

	/**
	 *	Register the core indexer.
	 *
	 *	This is the core of the (index side) of a search implementation.
	 *	It handles indexing an object from an array of fields.
	 *
	 * @param vB_Search_ItemIndexer $core_indexer
	 */
	public function register_core_indexer(vB_Search_ItemIndexer $core_indexer)
	{
		$this->core_indexer = $core_indexer;
	}

	/**
	*	@deprecated.  Another bad idea.
	*/
	public function get_db()
	{
		return $GLOBALS['vbulletin']->db;
	}

	//*************************************************************************
	//Utility methods
	public function flood_check($user, $ipaddress)
	{
		global $vbulletin;
		//if we don't have a search limit then skip check
		if ($vbulletin->options['searchfloodtime'] == 0)
		{
			return true;
		}

		//if the user is an admin or a moderater, skip the check
		if ($user->hasPermission('adminpermissions', 'cancontrolpanel') OR $user->isModerator())
		{
			return true;
		}

		$db = $this->get_db();
		// get last search for this user and check floodcheck
		if ($user->isGuest())
		{
			$filter =  "ipaddress ='" . $db->escape_string(IPADDRESS) . "'";
		}
		else
		{
			$filter = "userid = " . intval($user->get_field('userid'));
		}

		$prevsearch = $db->query_first("
			SELECT searchlogid, dateline
			FROM " . TABLE_PREFIX . "searchlog AS searchlog
			WHERE $filter
			ORDER BY dateline DESC LIMIT 1
		");
		if ($prevsearch)
		{
			$timepassed = TIMENOW - $prevsearch['dateline'];
			if ($timepassed < $vbulletin->options['searchfloodtime'])
			{
				return array('searchfloodcheck', $vbulletin->options['searchfloodtime'], ($vbulletin->options['searchfloodtime'] - $timepassed));
			}
		}

		return true;
	}

	//storage for registration information.
	private $index_controllers = array();
	private $search_controllers = array();
	private $default_controller = null;
	private $core_indexer = null;

	private $result_item_classes = array();
	private $result_types = array();

	private $indexed_types = null;
	private $indexed_types_by_key = null;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
