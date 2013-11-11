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

if (!defined('VB_ENTRY'))
{
	die('Access denied.');
}

require_once DIR . '/includes/class_xml.php';
require_once DIR . '/includes/functions_misc.php';
require_once (DIR."/includes/functions_search.php");
require_once (DIR."/vb/search/core.php");
require_once (DIR."/vb/legacy/currentuser.php");
require_once (DIR."/includes/functions_socialgroup.php");

/**
 * vB_Search_Searchtools
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: searchtools.php 42666 2011-04-05 22:17:42Z michael.lavaveshkul $
 * @access public
 */
class vB_Search_Searchtools
{
	/**
	 * Constructor
	 * We're going to need an xml builder. Let's instantiate it now
	 */
	private function __construct()
	{
	}

/**
* vB_Search_Searchtools::listSearchable()
* This returns the html elements to select a content type. When you select
* one another call gets made to pull the correct user interface for that type.
*
* @param string $divname : name of the div where the generated UI will go
* @param string $url : The target URL for the form submit
* @param mixed $prefs : the user searchprefs object
* @param mixed $currentval
* @return html for the UI
*/
	public static function listSearchable($divname, $url, $prefs, $currentval = null)
	{
		global $vbphrase, $sessionhash;
		$post_select = self::makeSearchableSelectOptions($prefs, $currentval);
		$template = vB_Template::create('search_input_searchtypes');
		$template->register('type_select_options', $post_select);
		$template->register('url', $url);
		return $template->render();
	}

	/**
	 *	Get a list of searchable types for option display
	 *
	 *	Might be worth moving to vB_Search_Core
	 *
	 * @return array Of the form $contenttypeid -> "Type Display Name"
	 */
	public static function get_type_options()
	{
		global $vbphrase;
		$search = vB_Search_Core::get_instance();

		$options = array();
		foreach ($search->get_indexed_types() as $type => $data)
		{
			$options[$type] = (string)$search->get_search_type_from_id($type)->get_display_name();

			if (! isset($options["$type"]) AND isset($vbphrase[$data['class']]))
			{
				$options[$type] = $vbphrase[$data['class']];
			}
		}
		return $options;
	}

	/**
	 * Create option list from the type array returned by get_type_options
	 *
	 * Hopefully this can be replaced by a loop in the template once the
	 * new template code comes on line.
	 */
	public static function renderTypeOptions($types, $selected_types, $show_any = true)
	{
		global $vbphrase;

		if (!is_array($selected_types))
		{
			$selected_types = array();
		}

		if ($show_any)
		{
			$options .= render_option_template($vbphrase['any_type'], '', '');
		}

		foreach ($types as $type => $label)
		{
			$selected = in_array($type, $selected_types) ? 'selected="selected"' : '';
			$options .= render_option_template($label, $type, $selected);
		}

		return $options;
	}


// ###################### Start makeSearchableSelect ######################
/**
 * vB_Search_Searchtools::makeSearchableSelect()
 * This function displays the select list for the user to display what type of
 *  information they would like to search
 * @param string $currentval : the current value to be displayed on the select
 * @return : complete html for the search elements
 */
	private static function makeSearchableSelectOptions($prefs, $currentval = null)
	{
		global $vbulletin, $vbphrase;

		$types = self::get_type_options();

		if (!$currentval AND $prefs['type'])
		{
			$currentval = $prefs['type'];
		}

		if (is_array($currentval))
		{
			$currentval = array_slice($currentval, 0, 1);
		}
		else
		{
			$currentval = array($currentval);
		}

		return  self::renderTypeOptions($types, $currentval, false);
	}

// ###################### Start getUiXml ######################
/**
 * vb_Search_Searchtools::getUiXml()
 * This gets the xml which will be passed to the ajax function. It just wraps
 * get_ui in html
 *
 * @param integer $contenttypeid
 * @return the appropriate user interface wrapped in XML
 */
	public static function getUiXml($contenttypeid, $prefs)
	{
		global $vbulletin;
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('root');

		//todo handle prefs for xml types
		$xml->add_tag('html', self::getUi($contenttypeid, $prefs));

		$xml->close_group();
		$xml->print_xml();
	}

// ###################### Start getDefaultUiXml ######################
/**
 * vb_Search_Searchtools::getDefaultUiXml()
 * This gets the xml which will be passed to the ajax function. It just wraps
 * get_ui in html
 *
 * @param integer $contenttypeid
 * @return the appropriate user interface wrapped in XML
 */
	public static function getDefaultUiXml($contenttypeid, $prefs)
	{
		global $vbulletin;
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('root');

		$xml->add_tag('html', self::makeDefaultSearch($contenttypeid, $prefs));

		$xml->close_group();
		$xml->print_xml();
	}


// ###################### Start getUi ######################
/**
* vb_Search_Searchtools::getUi()
*This gets the html to create the appropriate user interface based on the
* content type.
*
* @param integer $contenttypeid
* @return html for the user interface
*/
	public static function getUi($contenttypeid, $prefs)
	{
		//probably not the right place for this, but we need it for every
		//item template and I don't want change every single type object.
		//we don't really have any other "common" location
		global $vbulletin, $show;
		if ($vbulletin->debug)
		{
			$show['nocache'] = true;
		}

		$type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttypeid);

		return $type->listUi($prefs);
	}


	// ###################### Start showPrefixes ######################
	/**
	 * vB_Search_Searchtools::showPrefixes()
	 *This displays a scrolling list of prefixes
	 *
	 * @param string $name : name for the select element
	 * @param string $style_string : something like "style=XXXX" or "class=XXX". Or empty
	 * @return $html: complete html for the select element
	 * @deprecated -- the select header should be in the template
	 */
/**/
	public static function showPrefixes($name, $style_string, $prefixchoice = array(), $rows = 5)
	{
		if ($name == null)
		{
			return self::getPrefixOptions($prefixchoice, false);
		}

		return "<select name=\"$name" . '[]'."\" class=\"bginput\" size=\"$rows\" multiple=\"multiple\">" .
			 self::getPrefixOptions($prefixchoice);
			"</select>";
	}


	/**
	 * vB_Search_Searchtools::getPrefixOptions()
	 * Get the list of prefix options for prefix filtering.
	 *
	 * @param array $prefixchoice -- Prefixes that have been selected.
	 * @param boolean $include_meta -- Include the special array values (use is deprecated
	 *	should be included in templates so that they can be altered).
	 * @return $html: complete html for the select element
	*/
	public static function getPrefixOptions($prefixchoice = array(), $include_meta=false)
	{
		global $vbulletin;
		global $vbphrase;

		$prefixsets = array();
		$gotPrefixes = false;

		if (! is_array($prefixchoice))
		{
			$prefixchoice = array($prefixchoice);
		}

		$prefixes_sql = $vbulletin->db->query_read("
			SELECT prefix.prefixsetid, prefix.prefixid, forumprefixset.forumid
			FROM " . TABLE_PREFIX . "prefix AS prefix
				INNER JOIN " . TABLE_PREFIX . "prefixset AS prefixset ON (prefixset.prefixsetid = prefix.prefixsetid)
				INNER JOIN " . TABLE_PREFIX . "forumprefixset AS forumprefixset ON
					(forumprefixset.prefixsetid = prefixset.prefixsetid)
			ORDER BY prefixset.displayorder, prefix.displayorder
		");

		while ($prefix = $vbulletin->db->fetch_array($prefixes_sql))
		{
			$forumperms =& $vbulletin->userinfo['forumpermissions']["$prefix[forumid]"];

			if (($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['cansearch'])
				AND verify_forum_password($prefix['forumid'], $vbulletin->forumcache["$prefix[forumid]"]['password'], false)
				)
			{
				$prefixsets["$prefix[prefixsetid]"]["$prefix[prefixid]"] = $prefix['prefixid'];
			}
		}

		$prefix_options = '';
		foreach ($prefixsets AS $prefixsetid => $prefixes)
		{
			$optgroup_options = '';
			foreach ($prefixes AS $prefixid)
			{
				$gotPrefixes = true;
				$optgroup_options .= 	render_option_template(
					htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]),
					$prefixid, in_array($prefixid, $prefixchoice) ? ' selected="selected"' : '');
			}

			// if there's only 1 prefix set available, we don't want to show the optgroup
			if (count($prefixsets) > 1)
			{
				$optgroup_template = vB_Template::create('optgroup');
				$optgroup_template->register('optgroup_label', htmlspecialchars_uni($vbphrase["prefixset_{$prefixsetid}_title"]));
				$optgroup_template->register('optgroup_options', $optgroup_options);
				$prefix_options .= $optgroup_template->render();
			}
			else if (! $gotPrefixes)
			{
				return $vbphrase['no_prefix_defined'];
			}
			else
			{
				$prefix_options = $optgroup_options;
			}
		}

		$anythread_selected = (empty($prefixchoice) OR in_array('', $prefixchoice) ) ? ' selected="selected"' : '';
		$anyprefix_selected = ($prefixchoice AND in_array('-1', $prefixchoice)) ? ' selected="selected"' : '';
		$none_selected = ($prefixchoice AND in_array('-1', $prefixchoice)) ? ' selected="selected"' : '';

		if ($include_meta)
		{
			$prefix_options =
				render_option_template($vbphrase['any_thread_meta'], '', $anythread_selected) .
				render_option_template($vbphrase['any_prefix_meta'], -2, $anyprefix_selected) .
				render_option_template($vbphrase['no_prefix_meta'], -1, $none_selected) .
				$prefix_options	;
		}

		return $prefix_options;
	}

// ###################### Start searchIntroFetchPrefs ######################
/**
* vB_Search_Searchtools::searchIntroFetchPrefs()
*
* @param mixed $current_user : the current user
* @param mixed $typeid :  The content type id for the search.  Accepts the special value
*   vB_Search_Core::TYPE_COMMON
* @return array Map of keys => values Used to initialize the search interface
*/
	public static function searchIntroFetchPrefs($current_user, $typeid)
	{
		global $vbulletin;

		$fields = array();
		$prefs = $current_user->getSearchPrefs();

		$defaults = self::getDefaultPrefs();

		if (is_array($typeid))
		{
			require_once DIR . '/packages/vbforum/search/type/common.php';
			$fields = isset($prefs[vB_Search_Core::TYPE_COMMON]) ?
				$prefs[vB_Search_Core::TYPE_COMMON] : array();
			$defaults = self::getDefaultPrefs();
			$type = vBForum_Search_Type_Common::create_item(null);
		}
		else if (isset($prefs[$typeid]))
		{
			$fields = $prefs[$typeid];
			$defaults = self::getDefaultPrefs($typeid);
			$type = vB_Search_Core::get_instance()->get_search_type_from_id($typeid);
		}
		else if ($typeid == 'common')
		{
			$fields = $prefs[vB_Search_Core::TYPE_COMMON];
			$defaults = self::getDefaultPrefs();
			$type = vBForum_Search_Type_Common::create_item(null);
		}
		else
		{
			$type = vB_Search_Core::get_instance()->get_search_type_from_id($typeid);
			$defaults = self::getDefaultPrefs($typeid);
		}

		if (!is_array($defaults))
		{
			$defaults = array();
		}

		if (!is_array($fields))
		{
			$fields = array();
		}

		$prefs = array_merge($defaults, $fields);

		// if search conditions are specified in the URI, use them

		foreach (array_keys($type->listSearchGlobals()) AS $varname)
		{
			if ($vbulletin->GPC_exists["$varname"] AND !in_array($varname, array('humanverify')))
			{
				$prefs["$varname"] = $vbulletin->GPC["$varname"];
			}
		}

		//I have no idea what the purpose of this is.  We change the defaults if we hit process and
		//the defaults aren't set?  At a guess its because of a search like function that backs on
		//user
		if (isset($_POST['do']) AND $_POST['do'] == 'process')
		{
			if (empty($vbulletin->GPC['exactname']))
			{
				$prefs['exactname'] = 0;
			}

			if (empty($vbulletin->GPC['childforums']))
			{
				$prefs['childforums'] = 0;
			}
		}

		return $prefs;
	}

	// ###################### Start getDefaultPrefs ######################
	/**
	 * vB_Search_Searchtools::get_default_prefs()
	* Get the list of savable prefs and their default values
	*
	* @param mixed $contenttypeid : The id for the content type being searched.  Also
	*  permits the special value vB_Search_Core::TYPE_COMMON
	* @return array
	*/
	public static function getDefaultPrefs($contenttypeid = vB_Search_Core::TYPE_COMMON)
	{
		$prefs  = array(
			'exactname'     => 1,
			'titleonly'     => 0,
			'searchuser'    => '',
			'tag'           => '',
			'searchdate'    => 0,
			'contenttypeid' => 1,
			'beforeafter'   => 'after',
			'sortby'        => 'relevance',
			'sortorder'     => 'descending',
			'type'          => 0
		);

		if ($contenttypeid != vB_Search_Core::TYPE_COMMON)
		{
			$search = vB_Search_Core::get_instance();
			$type = $search->get_search_type_from_id($contenttypeid);

			//add grouping options
			if ($type->can_group())
			{
				$prefs['starteronly'] = 0;
				$prefs['showposts'] = 0;
			}

			//add any type specific prefs.
			$prefs = array_merge($prefs, $type->additional_pref_defaults());
		}

		return $prefs;
	}

	// ###################### Start searchIntroRegisterHumanVerify ######################
	/**
	 * vB_Search_Searchtools::searchIntroRegisterHumanVerify()
	 * Handle registration of the human verify components
	 *  If necesary, display the human verify form.
	 *
	 * @param mixed $template
	 * @return nothing
	 */
	public static function searchIntroRegisterHumanVerify($template)
	{
		global $vbulletin;
		// image verification
		$human_verify = '';

		if (fetch_require_hvcheck('search'))
		{
			require_once(DIR . '/includes/class_humanverify.php');
			$verification =& vB_HumanVerify::fetch_library($vbulletin);
			$human_verify = $verification->output_token();
		}
		$template->register('human_verify', $human_verify);
	}

	// ###################### Start getPrefs ######################

	/**
	 * vB_Search_Searchtools::get_default_prefs()
	 * Get the list of savable prefs and their default values
	 *
	 * @param mixed $contenttypeid : The id for the content type being searched.  Also
	 *  permits the special value vB_Search_Core::TYPE_COMMON
	 * @return array
	 */
	public static function getPrefs($contenttypeid = vB_Search_Core::TYPE_COMMON)
	{
		$prefs  = array(
			'exactname'   => 1,
			'titleonly'   => 0,
			'searchuser'  => '',
			'searchdate'  => 0,
			'beforeafter' => 'after',
			'sortby'      => 'date',
			'sortorder'   => 'descending',
			'tag'   => '',
			'type'=> 0
		);

		if ($contenttypeid != vB_Search_Core::TYPE_COMMON)
		{
			$search = vB_Search_Core::get_instance();
			$type = $search->get_search_type_from_id($contenttypeid);

			//add grouping options
			if ($type->can_group())
			{
				$prefs['starteronly'] = 0;
				$prefs['showposts'] = 0;
			}

			//add any type specific prefs.
			$prefs = array_merge($prefs, $type->additional_pref_defaults());
		}

		return $prefs;
	}

// ###################### Start registerPrefs ######################
/**
*  vB_Search_Searchtools::registerPrefs()
*	Handle registration of search prefs
*
* Handles registration of default values for most form elements based
* on the prefs array (a combination of defaults, saved user prefs, and
* any posted form values we might have).
*
* The elements that are handled are singleton elements and any
* static option lists in the html.  Lists generated from a DB query are
* handled when the list html is created.
*
* @param vB_Template $template The main search display template
* @param array $prefs The array of prefs to process.
*/
	public static function registerPrefs($template, $prefs)
	{
		// now check appropriate boxes, select menus etc...
		$formdata = array();

		if ($prefs)
		{
			foreach ($prefs AS $varname => $value)
			{
				//skip array types.  Assume they are handled when the picklist is generated.
				if (is_array($value))
				{
					continue;
				}

				$formdata["$varname"] = htmlspecialchars_uni($value);
				$formdata[$varname . 'checked'] = array($value => 'checked="checked"');
				$formdata[$varname . 'selected'] = array($value => 'selected="selected"');
			}

			//we should clean up the template so we don't have to register the individual names
			foreach ($formdata as $varname => $value)
			{
				$template->register($varname, $value);
			}
			$template->register('formdata', $formdata);

		}
	}

	/**
	 * vB_Search_Searchtools::getCompareString()
	 * For search we get a field name, a value and  a compare constant, like OP_EQ.
	 * We need to display a string like 'name is Kier'
	 * This function maps the constant to a language-specific compare string.
	 *
	 * @param string $compare
	 * @param bool $is_date
	 * @return string
	 */
	public static function getCompareString($compare, $is_date = false, $is_array = false)
	{
		global $vbphrase;
		switch((string)$compare)
		{
			case vB_Search_Core::OP_EQ:

				if ($is_date)
				{
					return ' ' . $vbphrase['on'] . ' ';
				}
				else if ($is_array)
				{
					return ': ';
				}
				else
				{
					return ' = ';
				}
				;
				break;
			case vB_Search_Core::OP_NEQ:

				if ($is_date)
				{
					return ' ' . $vbphrase['not'] . ' ';
				}
				else
				{
					return ' ' . $vbphrase['not'] . ' ' . $vbphrase['on']. ' ';
				}
				;
				break;
				//we use a lot of terms like messageless and discussionless,
				// where 0 means "at least" and 1 means "at most"
			case vB_Search_Core::OP_LT:
			case '1':

				if ($is_date)
				{
					return ' ' . $vbphrase['is_before'] . ' ';
				}
				else
				{
					return ' ' . $vbphrase['at_most'] . ' ';
				}
				break;
			case vB_Search_Core::OP_GT:
			case '0':

				if ($is_date)
				{
					return ' ' . $vbphrase['is_after'] . ' ';
				}
				else
				{
					return ' ' . $vbphrase['at_least'] . ' ';
				}
				break;

			default:
				return ' ';
				;
		} // switch

	}

	/**
	 * vB_Search_Searchtools::getCompare()
	 * For search we get a field name, a value and  a compare constant, like OP_EQ.
	 * We need to create a string like 'name <= Kier'
	 * This function creates it
	 *
	 * @param string $compare
	 * @return string
	 */
	public static function getCompare($compare, $is_array = false)
	{
		switch((string)$compare)
		{
			case vB_Search_Core::OP_EQ:

				return ' = ';
				break;
			case vB_Search_Core::OP_NEQ:

				return ' != ';
				break;
				//we use a lot of terms like messageless and discussionless,
				// where 0 means "at least" and 1 means "at most"
			case vB_Search_Core::OP_LT:
			case '1':

				return ' <= ';
				break;
			case vB_Search_Core::OP_GT:
			case '0':

				return ' >= ';
				break;
			default:
				return ' ';
			;
		} // switch

	}

	/**
	 * vB_Search_Searchtools::getDisplayString()
	 * There are a lot of places where we need to get a display string
	 *  for the search results tab. It's straightforward but takes a dozen lines or
	 *  so. Might as well just do it once.
	 *
	 * @param string $table : name of the table
	 * @param string $table_display : the display name
	 * @param string $fieldname : the field in the table that holds a nice display value
	 * @param string $key : the primary key of the table
	 * @param mixed $id : this can be an array of ids or a single value.
	 * @param int $comparator : either a vB_Search_Core:: enum value, or 0 or 1
	 *		(0 means "at most", and one means "at least"), or "before" and "after"
	 * @param bool $is_date : Whether this is a text field. In English and most
	 * languages that makes a difference in the display string
	 * @return string;
	 */
	public static function getDisplayString($table, $table_display, $fieldname, $key, $id, $comparator, $is_date)
	{
		global $vbulletin, $vbphrase;
		$names = array();

		$id = $vbulletin->db->sql_prepare($id);
		if (is_array($id))
		{
			//If we have an array, we have to use equals.
			$sql = "SELECT DISTINCT $table.$fieldname from " . TABLE_PREFIX . "$table AS
				$table WHERE $key IN (" . implode(', ', $id) . ")";

			if ($rst = $vbulletin->db->query_read($sql))
			{
				while($row = $vbulletin->db->fetch_row($rst))
				{
					$names[] = $row[0];
				}
			}

			if (count($names) > 0)
			{
				return $table_display . ': ' . implode(', ', $names);
			}
		}
		else
		{
			//If we got here, we have a single value
			if ($row = $vbulletin->db->query_first("SELECT $table.$fieldname from " . TABLE_PREFIX . "$table AS
				$table WHERE $key = $id"))
			{
				return  $table_display . ' ' . self::getCompareString($comparator, $is_date)
					. ' ' . $row[0];
			}
		}
		return "";
	}

	/**
	 * vB_Search_Searchtools::get_summary()
	 * Given a string which may be long and may have some combination of
	 * BBCode and html, we need a way to show the first n characters in a
	 * way that doesn't break the page.
	 *
	 * @param string $text
	 * @param int $length
	 * @return string
	 */
	public static function getSummary($text, $length, $stripimg = false)
	{
		$display['highlight'] = array();
		$text = preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe',
			"process_quote_removal('\\3', \$display['highlight'])", $text);
		$strip_quotes = true;

		// Deal with the case that quote was the only content of the post
		if (trim($text == ''))
		{
			return '';
		}

		return htmlspecialchars_uni(fetch_censored_text(
			trim(fetch_trimmed_title(strip_bbcode($text, $strip_quotes, false, true, $stripimg), $length))));
	}
	/**
	 * Remove HTML tags, including invisible text such as style and
	 * script code, and embedded objects.  Add line breaks around
	 * block-level tags to prevent word joining after tag removal.
	 */
	public static function stripHtmlTags( $text )
	{
		$text = preg_replace(
		    array(
		      // Remove invisible content
		        '@<head[^>]*?>.*?</head>@siU',
		        '@<style[^>]*?>.*?</style>@siU',
		        '@<script[^>]*?.*?</script>@siU',
		        '@<object[^>]*?.*?</object>@siU',
		        '@<embed[^>]*?.*?</embed>@siU',
		        '@<applet[^>]*?.*?</applet>@siU',
		        '@<noframes[^>]*?.*?</noframes>@siU',
		        '@<noscript[^>]*?.*?</noscript>@siU',
		        '@<noembed[^>]*?.*?</noembed>@siU',
		      // Add line breaks before and after blocks
		        '@</?((address)|(blockquote)|(center)|(del))@iU',
		        '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iU',
		        '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iU',
		        '@</?((table)|(th)|(td)|(caption))@iu',
		        '@</?((form)|(button)|(fieldset)|(legend)|(input))@iU',
		        '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iU',
		        '@</?((frameset)|(frame)|(iframe))@iU',
			     // font isn't begin pulled
			     '@<font[^>]*?.*?</font>@siU',
			    // for some reason [INDENT] is not begin handled
			    '@\[quote\]@siU',
			    '@\[quote?(=;)\]@siU',
			    '@\[\/quote\]@siU',
			    '@\[indent\]@siU',
			    '@\[\/indent\]@siU',
		    ),
		    array(
		        ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
		        "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
		        "\n\$0", "\n\$0",
		        ' ',' ',' ',' ',' ',
		    ),
		    $text );
		return strip_tags( $text );
	}

	private $xmlbuilder;

}
/*======================================================================*\
   || ####################################################################
   || # Downloaded: 01:57, Mon Sep 12th 2011
   || # SVN: $Revision: 42666 $
   || ####################################################################
   \*======================================================================*/
