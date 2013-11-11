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
require_once DIR . '/includes/class_xml.php';
require_once DIR . '/includes/functions_misc.php';
require_once (DIR."/includes/functions_search.php");
require_once (DIR."/vb/search/core.php");

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

/**
 * Results class for a search item
 *
 * This interface must be defined for each type being registered for search.
 * It handles two operations:
 * The first is verifying that items of that type returned by
 * a search implementation can be displayed to the requesting user.
 *
 * The second is rendering the data for the type to be displayed as a search result.
 *
 * @package vBulletin
 * @subpackage Search
 */
/**
 * vB_Search_Type
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id$
 * @access public
 */
require_once DIR . '/includes/functions_misc.php';
require_once (DIR."/includes/functions_search.php");

abstract class vB_Search_Type
{

	/**
	 * vB_Search_Type::__construct()
	 * The only housekeeping we do at startup is to
	 * merge the content-specific form variables
	 * (in $type_globals) with the generic (in $globals)
	 */
	public function __construct()
	{
		$this->form_globals = array_merge($this->form_globals, $this->type_globals);
	}

	/**
	*	This checks any *type specific* enabled/disabled status such as vb options
	* The generic content type status (enabled and searchable) needs to checked
	* seperatately (and usually before this class is even instantiated).
	*/
	public function is_enabled()
	{
		return true;
	}

	/**
	 * vB_Search_Type::fetch_validated_list()
	 * 	Validate a list of items.
	 *
	 * @param mixed $user: user to validate against.
	 * @param array(int) $ids:  list of ids to validate
	 * @param array(int) $gids: list of gids corresponding to the ids.  That is gid[i] is the
	 * 	groupid form ids[i].  The parameters are structured this way to allow implementations
	 *		to ignore groupids with a minimum of effort.
	 * @return array('list' => $results, 'groups_rejected' => $groups_rejected)  'list' is a
	 * 	map with an entry for each id in $ids.  The value is either false if the item is
	 *		not viewable by the user or the result object if it is.  'groups_rejected' is a
	 * 	simple array of groups that have no items viewable by the user. This will be used
	 *		to filter future batches for this result set.  Rejected groups is optional and
	 *		can be returned as an empty array if desired or not applicable.
	 */
	public function fetch_validated_list($user, $ids, $gids)
	{
		//default code, should work for any subclass
		$list = array();
		foreach ($ids as $id)
		{
			$item = $this->create_item($id);

			if ($item->can_search($user))
			{
				$list[$id] = $item;
			}
			else
			{
				$list[$id] = false;
			}
		}
		return array('list' => $list, 'groups_rejected' => array());
	}


	/**
	 * vB_Search_Type::prepare_render()
	 *
	 * Do any set required to prepare to render search items of this type
	 *
	 * Called once for each distinct type in the result set, in case there
	 * is anything that type needs to do prior to rendering the individual
	 * types.  For example if preloading data that would be expensive to
	 * load for or handling display options that depend on the entire
	 * set of items.
	 *
	 * How data is communicated to the result items for rendering is
	 * left to the type/item implementation.
	 *
	 * By default this function does nothing.
	 *
	 * @param mixed $user: requesting the search
	 * @param mixed $results: the array of items of this type within the result set
	 *  will be same order that they appear in the result set.
	 * @return nothing
	 */
	public function prepare_render($user, $results) {}

	/**
	 * vB_Search_Type::additional_header_text()
	 *
	 * Return any additional text required to render items of this type
	 *
	 * @return string html to be injected into the html header
	 */
	public function additional_header_text()
	{
		return "";
	}

	/**
	 * vB_Search_Type::additional_pref_defaults()
	 *	Return any type specific savable preferences and their defaults
	 *
	 * @return array $key => $default_value
	 * The keys need to match the form field names.
	 */
	public function additional_pref_defaults()
	{
		return array();
	}


	/**
	 * vB_Search_Type::get_display_name()
	 *Returns the type name suitable for display to the user.
	 *
	 * @return string
	 */
	public function get_display_name()
	{
		return $this->class;
//	Not currently working, when it does we can potentially drop
//		return vB_Types::instance()->getContentTypeTitle($this->get_contenttypeid());
	}
	/**
	 * vB_Search_Type::create_item()
	 *Returns an item of this type based on the id.
	 *
	 * @param integer $id
	 * @return the type
	 */
	abstract public function create_item($id);

	/**
	 * vB_Search_Type::can_group()
	 *	Can this search type be grouped?
	 *
	 * @return boolean
	 */
	public function can_group()
	{
		return false;
	}

	/**
	 * vB_Search_Type::group_by_default()
	 * Should this item be grouped by default when no grouping is explicitly requested
	 *	Grouping defaults are always used for common search.
	 * @return boolean
	 */
	public function group_by_default()
	{
		return false;
	}
	// ###################### Start getDefaultUiXml ######################
	/**
	 * vB_Search_Type::getUiXml()
	 * This gets the xml which will be passed to the ajax function. It just wraps
	 * get_ui in html
	 *
	 * @param array $prefs : the stored prefs for this contenttype
	 * @return the appropriate user interface wrapped in XML
	 */
	public function getUiXml($prefs)
	{
		global $vbulletin;
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('root');

		$xml->add_tag('html', $this->listUi($prefs));

		$xml->close_group();
		$xml->print_xml();
	}


	// ###################### Start setPrefs ######################
	/**
	 * vB_Search_Type::setPrefs()
	 *
	 * @param object $template : The template for this search UI
	 * @param array $prefs : the user preferences array
	 * @param array $prefsettings : The valid settings for this UI.
	 * 	this is an array of arrays : ('select', 'cb', 'value)
	 * @return
	 */
	public function setPrefs($template, $prefs, $prefsettings)
	{
		//Let's set the prefs. There are three groups. We have either "selected",
		// "checked", or "value=". Those are the three arrays in
		// $prefsettings
		if (isset($prefs))
		{
			// We set the "selected" by calling
			//template->register("variableselected[index]", 'selected')
			foreach ($prefsettings['select'] as $key)
			{
				if (isset($prefs["$key"]) AND ($prefs["$key"] !== NULL) )
				{
					if (is_array($prefs["$key"]))
					{
						$val = array();
						foreach($prefs["$key"] as $prefkey)
						{
							$val[$prefkey] = 'selected="selected"';
						}

					}
					else
					{
						$val[$prefs["$key"]] = 'selected="selected"';
					}

					$template->register($key . 'selected' , $val);
					unset($val);
				}
			}

			//For the checkbox, we set template->register("variablechecked", 'checked')
			foreach ($prefsettings['cb'] as $key)
			{
				if (isset($prefs["$key"]) AND ($prefs["$key"]))
				{
					$template->register(($key . 'checked'), 'checked="checked"');
				}
			}

			//For the radio buttons, which we mostly don't have,
			// we handle like selects except they use 'checked' instead of
			// 'selected'
			if (isset($prefsettings['rb']))
			{
				foreach ($prefsettings['rb'] as $key)
				{
					if (isset($prefs["$key"]))
					{
						$val = array();
						$val[$prefs["$key"]] = 'checked="checked"';
						$template->register($key . 'checked' , $val);
						unset ($val);
					}
				}

			}
			// and for standard variables we just call
			// template->register('variable', 'value')
			foreach ($prefsettings['value'] as $key)
			{
				if (isset($prefs["$key"]) AND strlen($prefs["$key"]))
				{
					$template->register($key , htmlspecialchars_uni($prefs["$key"]));
				}
			}
		}
	}

	// ###################### Start listUi ######################
	/**
	 * vB_Search_Type::makeSearch()
	 * If we have a new search type this will create a search interface for it.
	 * This assumes we have written search and index controllers
	 *
	 * @param mixed $prefs : the array of user preferences
	 * @param mixed $contenttypeid : the content type for which we are going to
	 *    search
	 * @param array registers : any additional elements to be registered. These are
	 * 	just passed to the template
	 * @param string $template_name : name of the template to use for display. We have
	 *		a default template.
	 * @param boolean $groupable : a flag to tell whether the interface should display
	 * 	grouping option(s).
	 * @return the html for the user interfacae
	 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null, $template_name = null)
	{
		global $vbulletin, $vbphrase;

		if (is_null($contenttypeid))
		{
			$contenttypeid = vb_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Post');
		}

		if (isset($template_name))
		{
			$template = vB_Template::create($template_name);
		}
		else if ($this->can_group())
		{
			$template = vB_Template::create('search_input_default_groupable');
		}
		else
		{
			$template = vB_Template::create('search_input_default');
		}

		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('class', $this->class);
		$template->register('contenttypeid', $this->get_contenttypeid());

		$prefsettings = array(
			'select'=> array('searchdate', 'beforeafter', 'starteronly', 'sortby',
				'titleonly', 'order'),
			'cb' => array('nocache', 'exactname'),
		 	'value' => array('query', 'searchuser', 'tag'));

		$this->setPrefs($template, $prefs, $prefsettings);
		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);

		if (isset($registers) and is_array($registers) )
		{
			foreach($registers as $key => $value)
			{
				$template->register($key, htmlspecialchars_uni($value));
			}
		}
		return $template->render();
	}

	// ###################### Start listSearchGlobals ######################
	/**
	 * vB_Search_Type::list_SearchGlobals()
	 * The globals is a list of variables we'll try to pull from the input.
	 * They should be here because we want to use them in searchcommon and ajax,
	 * and probably elsewhere as we proceed.
	 *
	 * @return array
	 */
	public function listSearchGlobals()
	{
		return $this->form_globals;
	}

	/**
	 * vB_Search_Type::get_inlinemod_options()
	 *
	 * @return array of options
	 */
	public function get_inlinemod_options()
	{
		return array();
	}

	/**
	 * vB_Search_Type::get_inlinemod_type()
	 *
	 * @return string
	 */
	public function get_inlinemod_type()
	{
		return '';
	}

	/**
	 * vB_Search_Type::get_inlinemod_action()
	 *
	 * @return string
	 */
	public function get_inlinemod_action()
	{
		return '';
	}

	/**
	 * vB_Search_Type::get_contenttypeid()
	 *	Get the content type id
	 *
	 * @return int the content id for this type
	 */
	public function get_contenttypeid()
	{
		return vB_Types::instance()->getContentTypeId($this->package . "_" . $this->class);
	}


	/**
	* vB_Search_Type::get_groupcontenttypeid()
	*	Get the group content type id for this types group type
	*
	*	This is only valid if can_group returns true
	*
	* @return the group content type id
	*/
	public function get_groupcontenttypeid()
	{
		return vB_Types::instance()->getContentTypeId($this->group_package . "_" . $this->group_class);
	}

	/**
	 * vB_Search_Type::add_advanced_search_filters()
	 * This registers any form variables that this type will handle.
	 * It's always the same as the $search_global array, so we
	 * can just register ourselves with those variables.
	 *
	 * @param mixed $criteria
	 * @param mixed $registry
	 * @return no return
	 */
	public function add_advanced_search_filters($criteria, $registry)
	{
	}

	/**
	*	Get the database information for a given field.
	*
	* This function is specific to the vbdbsearch implementation.  Should not be
	* called from outside of that package (and may not work if that package is not
	* the active search implementation).
	*
	* @return array -- map with the following fields:
	* 	'join'  array of 'joinid' => join clause.  Joins from searchcore table to the
	*			field.  These will be added to the search query to join in the field to search.
	*			Multiple clauses with the same joinid will only be added once (the last one added
	*			wins, however they should be identical).
	*		'table' string -- table alias for the field
	*		'field' string -- the field name
	*/
	public function get_db_query_info($fieldname)
	{
		return false;
	}

	protected $package = "";
	protected $class = "";
	protected $group_package = "";
	protected $group_class = "";

	//protected $form_vars = array();
	//protected $criteria;

	protected $form_globals = array (
		'query'          => TYPE_STR,
		'searchuser'     => TYPE_STR,
		'exactname'      => TYPE_BOOL,
		'titleonly'		  => TYPE_BOOL,
		'searchdate'	  => TYPE_NOHTML,
		'beforeafter'	  => TYPE_NOHTML,
		'contenttypeid'  => TYPE_UINT,
		'ajax'           => TYPE_BOOL,
		'tag'            => TYPE_STR,
		'type'           => TYPE_ARRAY,
		'humanverify'    => TYPE_ARRAY,
		'sortby'         => TYPE_NOHTML,
		'order'          => TYPE_NOHTML,
		'sortorder'      => TYPE_NOHTML,
		'saveprefs'      => TYPE_BOOL,
		'quicksearch'    => TYPE_BOOL,
		'search_type'    => TYPE_BOOL,
		'searchfromtype' => TYPE_STR,
		'showposts'      => TYPE_UINT,
		'userid'         => TYPE_UINT,
		'starteronly'    => TYPE_UINT,
		'nocache'        => TYPE_BOOL,
		'natural'        => TYPE_BOOL
	);
	protected $type_globals = array();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
