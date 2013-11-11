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
 * @version $Revision: 29659 $
 * @since $Date: 2009-02-26 09:15:00 +0000 (Thur Feb 26, 2009) $
 * @copyright vBulletin Solutions Inc.
 */

class vb_Search_Resultsview
{
	/**
	 * Constructor
	 * @param $results : a results object, normally from vb/search/results
	 */
	public function __construct( $results, $template = null)
	{
		$this->results = $results;

		if (!$template)
		{
			$this->template = vB_Template::create('search_resultlist');
		}
		else
		{
			$this->template = vB_Template::create($template);
		}
	}

	/*******
	* @function render_searchbits() does the search element rendering
	* @param $page
	***/
	private function render_searchbits($page)
	{
		//until we manage to figure out how to handle this
		global $show;
		$show['inlinemod'] = false;

		//prepare types for render
		$items_by_type = array();
		foreach ($page as $item)
		{
			$typeid = $item->get_contenttype();

			if ($typeid)
			{
				$items_by_type[$typeid][] =  $item;
			}
		}

		$headinclude_extra = array();
		$inline_mod_type = false;
		$inline_mod_set = false;
		foreach ($items_by_type as $contenttype => $items)
		{
			$type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttype);

			$type->prepare_render($this->results->get_user(), $items);
			$headinclude_extra[] = $type->additional_header_text();

			//If the type supports inline mod operations *and* we haven't seen another
			//type that does, select it as the inline mod type.  If we have seen another,
			//then select nothing.  Right now we can't easily support inline mod for multiple
			//types on a single result set.  
			//
			//The previous logic handled this by not allowing inline mod on *any* mixed result set.
			//This caused problems with the "get new threads" because we also display (in some cases)
			//annoucements on that result set.  Announcements do not have inline mod, so they don't
			//really conflict and not allowing inline mod on those results bugs people
			//
			//We still exclude common searches (below) to avoid confusing people about why some resultsets
			//allow inline mod and others don't.  It is now safe for the system to remove that restriction
			//if we decide that allowing inline mod where we can on common searchs outweighs the confusion 
			//caused by it.
			if ($type->get_inlinemod_type())
			{
				if(!$inline_mod_set)
				{
					$inline_mod_type = $type;
					$inline_mod_set = true;
				}
				else 
				{
					$inline_mod_type = false;
				}
			}
		}

		$criteria = $this->results->get_criteria();
		if (!$criteria->is_common() AND $inline_mod_type)
		{
			$options = $inline_mod_type->get_inlinemod_options();

			if ($options)
			{
				$show['inlinemod'] = true;
				$this->template->register('mod_options', $this->render_mod_options($options));
				$this->template->register('mod_type', $inline_mod_type->get_inlinemod_type());
				$this->template->register('mod_action', $inline_mod_type->get_inlinemod_action());
				$this->template->register('url', SCRIPTPATH);
			}
		}

		//perform render
		$searchbits = '';
		foreach ($page as $item)
		{
			$searchbits .= $item->render($this->results->get_user(), $this->results->get_criteria());
		}

		$this->template->register('headinclude_extra', implode("\n", $headinclude_extra));
		$this->template->register('searchbits', $searchbits);
	}

	//hoping this can be moved back to the template once we have loop logic.
	private function render_mod_options($options)
	{
		$rendered_options = array();
		foreach ($options as $name => $value)
		{
			if (is_array($value))
			{
				
				foreach ($value as $name2 => $value2)
				{
					$rendered_options[] = '<li><label><input type="radio" name="do" value="' . $value2 . '" /> ' . $name2 . '</label></li>'; 
				}
				$rendered_options[] = '<li>&nbsp;</li>';
			}
			else
			{
				$rendered_options[] = '<li><label><input type="radio" name="do" value="' . $value . '" /> ' . $name . '</label></li>'; 
			}
		}

		return implode("\n", $rendered_options);
	}

	/******************************
	* @function showpage
	* @param $pagenumber : page we're preparing.
	* @param $perpage: number of entries per page
	* @param $base: the base url
	* @param $navbits: breadcrumbs for the navigation
	* **/
	public function showpage($pagenumber, $perpage, $base, $navbits, $print_output = true)
	{
		global $vbulletin;
		global $show;
		$errors = array();
		($hook = vBulletinHook::fetch_hook('search_before_process')) ? eval($hook) : false;

		$perpage = fetch_perpage($perpage, $this->maxpagesize, $vbulletin->options['searchperpage']);
		$pagenumber = max(intval($pagenumber), 1);

		if (!$this->results)
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}
		$page_results = $this->results->get_page($pagenumber, $perpage, $this->window);

		if (!$page_results)
		{
			eval(standard_error(fetch_error('searchnoresults', $displayCommon), '', false));
		}
		$criteria = $this->results->get_criteria();
		//When we have better template features, the display strings should be passed as
		//an array to the template and handled there.
		$this->template->register('criteriaDisplay', implode('; ', $criteria->get_display_strings()));
		$this->template->register('displayCommon', $criteria->get_common_words_string());

		$this->render_searchbits($page_results);
		$show['results'] = (bool) count($page_results);

		($hook = vBulletinHook::fetch_hook('search_complete')) ? eval($hook) : false;

		//figure searchtime -- time it took to run the search in seconds
		$this->template->register('searchtime', $this->results->get_searchtime());

		//"searchminutes" in the template is minutes since the search was generated.
		$sincegenerated = floor((TIMENOW - $this->results->get_dateline()) / 60);

		if ($sincegenerated >= 1)
		{
			$this->template->register('searchminutes', $sincegenerated);
			$show['generated'] = true;
		}

		$search['showposts'] = true;

		$pagenav = construct_window_page_nav($pagenumber, $this->window, $perpage,
			$this->results->get_confirmed_count(),	$base);

		$this->template->register('navbar', render_navbar_template(construct_navbits($navbits)));
		$this->template->register('base', $base);
		$startat = ($pagenumber-1) * $perpage;
		$this->template->register('first', $startat + 1);
		$this->template->register('last', $startat + count($page_results));
		$this->template->register('total', $this->results->get_confirmed_count());
		$this->template->register('search', $search);
		$this->template->register('pagenav', $pagenav);
		$this->template->register('show', $show);
		$this->template->register('search_back_url', $criteria->get_url());
		$this->template->register_page_templates();
		
		if ($print_output)
		{
			print_output($this->template->render());
		}
		else
		{
			return $this->template->render();
		}
	}

	public function settemplate($template)
	{
		if ($this->template != null)
		{
			$this->template = $template;
		}
	}

	public function gettemplate()
	{
		return $this->template;
	}

	private $results;
	private $template;
	private $window = 3;
	private $maxpagesize = 200;
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
