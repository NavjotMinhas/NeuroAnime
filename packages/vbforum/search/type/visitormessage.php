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
 * @package vBulletin
 * @subpackage Search
 * @author Ed Brown, vBulletin Development Team
 * @version $Id: visitormessage.php 29897 2009-03-16 18:40:14Z ebrown $
 * @since $Date: 2009-03-16 11:40:14 -0700 (Mon, 16 Mar 2009) $
 * @copyright vBulletin Solutions Inc.
 */


require_once (DIR . '/vb/search/type.php');
require_once (DIR . '/packages/vbforum/search/result/visitormessage.php');

/**
 * vBForum_Search_Type_VisitorMessage
 *
 * @package
 * @author Ed Brown
 * @copyright Copyright (c) 2009
 * @version $Id: visitormessage.php 29897 2009-03-16 18:40:14Z ebrown $
 * @access public
 */
class vBForum_Search_Type_VisitorMessage extends vB_Search_Type
{
	public function fetch_validated_list($user, $ids, $gids)
	{
		$list = array_fill_keys($ids, false);
		$items = vBForum_Search_Result_VisitorMessage::create_array($ids);
		foreach ($items as $id => $item)
		{
			if ($item->can_search($user))
			{
				$list[$id] = $item;
			}
		}
		return array('list' => $list, 'groups_rejected' => array());
	}

	public function is_enabled()
	{
		global $vbulletin;
		return ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']);
	}

	// ###################### Start prepare_render ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::prepare_render()
	 *
	 * @param object $user
	 * @param object $results
	 * @return
	 */
	public function prepare_render($user, $results)
	{
		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('user', 'search'));

		foreach ($results AS $result)
		{
			$privs = array();
			//if we have a right for any item in the result set we have that right

			foreach ($privs AS $key => $priv)
			{
				$this->mod_rights[$key] = ($this->mod_rights[$key] OR (bool) $priv);
			}
		}
	}

	// ###################### Start additional_header_text ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::additional_header_text()
	 *
	 * @return string
	 */
	public function additional_header_text()
	{
		return '';
	}

	// ###################### Start get_display_name ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::get_display_name()
	 *
	 * @return string
	 */
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_visitor_messages');
	}

	// ###################### Start listUi ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::listUi()
	 * This function composes the html to display the user interface for this
	 * search type
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
	 * @return $html: complete html for the search elements
	 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null,
		$template_name = null)
	{
		global $vbulletin, $vbphrase;


		if (! isset($template_name))
		{
			$template_name = 'search_input_visitormessage';
		}

		if (! isset($contenttypeid))
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'VisitorMessage');
		}

		$template = vB_Template::create($template_name);
		$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
		$template->register('class', $this->get_display_name());
		$template->register('contenttypeid',$contenttypeid);

		$prefsettings = array(
			'select'=> array('searchdate', 'beforeafter', 'starteronly', 'sortby'),
			'cb' => array('nocache', 'exactname'),
		 	'value' => array('query', 'searchuser'));
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

	// ###################### Start create_item ######################
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $id
	 */
	/**
	 * vBForum_Search_Type_VisitorMessage::create_item()
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_item($id)
	{
		return vBForum_Search_Result_VisitorMessage::create($id);
	}

	/**
	 * You can create from an array also
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_array($ids)
	{
		return vBForum_Search_Result_VisitorMessage::create_array($ids);
	}

// ###################### Start can_group ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::can_group()
	 *
	 * @return boolean
	 */
	public function can_group()
	{
		return false;
	}

	// ###################### Start group_by_default ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::group_by_default()
	 *
	 * @return boolean
	 */
	public function group_by_default()
	{
		return false;
	}
	// ###################### Start cansearch ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::cansearch()
	 *
	 * @return boolean
	 */
	public function cansearch()
	{
		return true;
	}

	// ###################### Start get_inlinemod_options ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::get_inlinemod_options()
	 *
	 * @return options array
	 *
	 * In general this doesn't get moderated. We should add
	 */
	public function get_inlinemod_options()
	{
		global $vbphrase, $show;

		return array();
	}


	// ###################### Start get_inlinemod_type ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::get_inlinemod_type()
	 *
	 * @return
	 */
	public function get_inlinemod_type()
	{
		return 'vmessage';
	}

	// ###################### Start get_inlinemod_action ######################
	/**
	 * vBForum_Search_Type_VisitorMessage::get_inlinemod_action()
	 *
	 * @return
	 */
	public function get_inlinemod_action()
	{
		return '';
	}

// ###################### Start additional_pref_defaults ######################
/**
 * vBForum_Search_Type_VisitorMessage::additional_pref_defaults()
 * Each search type has some responsibilities, one of which is to tell
 * what are its defaults
 *
 * @return array
 */
	public function additional_pref_defaults()
	{
		return array(
			'query'         => '',
			'exactname'     => 0,
			'searchuser'     => '',
			'nocache'    => 0,
			'searchdate'    => 0,
			'beforeafter'   => 'after',
			'sortby'		=> 'dateline'
		);
	}

	protected $package = "vBForum";
	protected $class = "VisitorMessage";
	protected $group_package = "vBForum";
	protected $group_class = "VisitorMessage";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29897 $
|| ####################################################################
\*======================================================================*/
