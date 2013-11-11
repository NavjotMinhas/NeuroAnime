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

/**
 * Class to handle mapping vBulletin 3 style data to vBulletin 4 stylevars
 *
 * @package	vBulletin
 * @author	David Grove, vBulletin Development Team
 * @version	$Revision$
 * @date	$Date$
 * @copyright	vBulletin Solutions Inc.
 */
class vB_StyleVarMap_vB3tovB4
{
	/*Properties====================================================================*/

	/**
	 * Reference to the vBulletin registry object
	 *
	 * @var vB_Registry
	 */
	protected $registry = null;

	/**
	 * Reference to the vBulletin database object for convenience
	 *
	 * @var object
	 */
	protected $db = null;

	/**
	 * Style ID for the style to be converted
	 *
	 * @var int
	 */
	protected $styleid = 0;

	/**
	 * Array of all vB3 stylevars and data to be read. Accessed by get_vb3_stylevar()
	 *
	 * @var	array
	 */
	protected $vb3_stylevar_cache = null;

	/**
	 * Array of all vB4 target stylevars that already exist. Accessed by stylevar_exists()
	 *
	 * @var	array
	 */
	protected $vb4_existing_stylevar_cache = null;

	/**
	 * Array of all vB4 stylevar definitions for target stylevars. Accessed by get_stylevar_definition()
	 *
	 * @var	array
	 */
	protected $vb4_stylevar_definition_cache = null;

	/**
	 * Array of default vB4 stylevar values for target stylevars. Accessed by get_default_stylevar_value()
	 *
	 * @var	array
	 */
	protected $vb4_default_stylevar_cache = null;

	/**
	 * Array of options to change behavior
	 *
	 * @var array
	 */
	protected $options = array(
		'output_progress'  => true, // Whether or not to print progress messages to the browser.
		'skip_image_paths' => true, // Whether or not to skip transferring the vB3 image path stylevars to vB4
		'create_new_style' => true, // Whether or not to save the converted style to a new style. If false, it saves in place to the current style.
		'revert_templates' => true, // Whether or not to revert all templates. Set to true for best results.
	);

	/**
	 * This holds the mapping from vB3 stylevars to vB4 stylevars, along with
	 * vB3 datatype information to assist in cleaning the stylevar data and
	 * setting the vB4 stylevar datatype. vB3 stylevars are divided into three
	 * sections-- csscolors, stylevars, and editorstyles. This map is divided
	 * into three arrays, indexed by the three section names. Each of these
	 * three arrays contains an array of key/value pairs where the key is the
	 * vB3 stylevar name and the value is an array containing the source data
	 * type and and array of target vB4 stylevars. The target vB4 stylevars are
	 * either a string with the name of the target stylevar or an array containing
	 * various elements-- 'stylevar' with the name of the stylevar, then any of
	 * the following elements that say how the data is to be manipulated before
	 * applying it to the target stylevar--
	 *
	 * 	apply_to	which sub-parts of the target stylevar to apply to
	 * 	multiply_by	multiply the source value by this, then apply it
	 * 	condition	only apply if this condition is me
	 * 	value		override the source value with this value (based on condition)
	 * 	units		override the units from the source value with this value
	 *
	 * Source datatypes:
	 *
	 * 	int_pixel_or_percentage
	 * 	int_pixel
	 * 	url
	 * 	path
	 * 	string (used for htmldoctype)
	 * 	color
	 *
	 * @var array	Mapping of how vBulletin 3 style data corresponds to vBulletin 4 stylevars.
	 */
	protected $map = array(

		// ----------------------------------------------------
		'csscolors' => array(


			// ***** body *****
			'body_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'doc_background',
						'apply_to' => array('color'),
					),
				),
			),
			'body_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'footer_copyright_color',
					'footer_time_color',
				),
			),
			// TODO: set these
			'body_link_n_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					//'navlinks_color',
					'link_color',
					'pagetitle_link_color',
				),
			),
			'body_link_v_fgcolor' => array(),
			'body_link_m_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'linkhover_color',
					'pagetitle_linkhover_color',
				),
			),


			// ***** page *****
			'page_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'body_background',
						'apply_to' => array('color'),
					),
				),
			),
			'page_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'body_color',
					'pagetitle_color',
					'pagetitle_description_color',
					'vbblog_pagetitle_color',
					'vbcms_header_color',
					//'navlinks_color',
				),
			),
			// TODO: set these
			'page_link_n_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					//'navlinks_color',
				),
			),
			'page_link_v_fgcolor' => array(),
			'page_link_m_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),


			// ***** table (tborder) *****
			'tborder_bgcolor' => array(),
			'tborder_fgcolor' => array(),
			'tborder_border_color' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'blockhead_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'blocksubhead_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'forumhead_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'secondarycontent_header_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadlisthead_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'toolsmenu_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'usercp_forum_icon_legend_top_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'usercp_forum_icon_legend_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbblog_bloglist_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbblog_featured_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbblog_navigation_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbblog_pagetitle_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbblog_sidebar_avatar_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbblog_sidebar_tabs_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'calendar_events_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'calendar_th_blocksubhead_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'calendar_mini_othermonth_a_border',
						'apply_to' => array('color'),
					),
					'calendar_today_border_color',
					array(
						'stylevar' => 'calendar_week_eventlist_birthdays_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'calendarwidget_weekdays_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbitlite_header_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'formrow_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'forumbits_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'foruminfo_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'foruminfo_header_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'forummenu_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'wgo_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'wgoheader_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'popupmenu_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_boxed_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_userinfo_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbithead_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbitlegacy_avatar_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'signature_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'profile_content_friends_list_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'profile_content_infractions_li_hover_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'profile_content_subsection_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'secondarycontent_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'sidebar_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'sidebar_content_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadbit_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'usercp_nav_blockbody_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'bbcode_quote_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbcms_editor_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbcms_header_borderBottom',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbcms_header_borderTop',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbcms_navwidget_menuitem_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbcms_navwidget_menuitem_hover_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'vbcms_wysiwyg_table_borderColor',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'lightweightbox_border',
						'apply_to' => array('color'),
					),
				),
			),


			// ***** table (tcat) *****
			'tcat_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'blockhead_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'forumhead_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'secondarycontent_header_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadlisthead_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'toolsmenu_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'navbar_tab_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'control_content_hover_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'usercp_forum_icon_legend_background',
						'apply_to' => array('color'),
					),
				),
			),
			'tcat_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blockhead_color',
					'forumhead_color',
					'secondarycontent_header_color',
					'threadlisthead_color',
					'toolsmenu_color',
					'control_content_hover_color',
				),
			),
			'tcat_link_n_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blockhead_link_color',
					//'forumhead_link_color',
					//'secondarycontent_header_link_color',
					//'threadlisthead_link_color',
					'toolsmenu_link_color',
					'navbar_tab_color',
				),
			),
			'tcat_link_v_fgcolor' => array(), // not used in vB4
			'tcat_link_m_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blockhead_linkhover_color',
					//'forumhead_linkhover_color',
					//'secondarycontent_header_linkhover_color',
					//'threadlisthead_linkhover_color',
					'toolsmenu_linkhover_color',
					//'navbar_tab_linkhover_color',
					//'navbar_linkhover_color', // using thead_fgcolor instead
				),
			),


			// ***** table (thead) *****
			'thead_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'blocksubhead_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'header_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'navbar_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'navbar_tab_border',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbithead_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'control_content_background',
						'apply_to' => array('color'),
					),
				),
			),
			'thead_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blocksubhead_color',
					'header_color',
					'header_link_color',
					'header_linkhover_color',
					'toplinks_link_color',
					'toplinks_linkhover_color',
					'navbar_color',
					'navbar_linkhover_color',
					'postbithead_color',
					'control_content_color',
				),
			),
			'thead_link_n_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blocksubhead_link_color',
					'navlinks_color',
					'navbar_link_color',
				),
			),
			'thead_link_v_fgcolor' => array(), // not used in vB4
			'thead_link_m_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blocksubhead_linkhover_color',
				),
			),


			// ***** table (tfoot) *****
			'tfoot_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'footer_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'footer_border',
						'apply_to' => array('color'),
					),
				),
			),
			'tfoot_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array('footer_color'),
			),
			'tfoot_link_n_fgcolor' => array( // n = normal
				'source_datatype'  => 'color',
				'target_stylevars' => array('footer_link_color'),
			),
			'tfoot_link_v_fgcolor' => array(), // v = visited (not used in vB4)
			'tfoot_link_m_fgcolor' => array( // m = hover
				'source_datatype'  => 'color',
				'target_stylevars' => array('footer_linkhover_color'),
			),


			// ***** alternating color #1 *****
			'alt1_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'navbar_tab_bevel',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'blockbody_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'blockrow_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'formrow_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'forumrow_firstentry_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'forumrow_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadbit_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'sidebar_content_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'usercp_nav_blockbody_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'lightweightbox_background',
						'apply_to' => array('color'),
					),
				),
			),
			'alt1_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'blockrow_color',
					'sidebar_content_color',
				),
			),
			'alt1_link_n_fgcolor' => array( // normal
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'block_link_color',
					'blockrow_link_color',
					'forum_sidebar_link_color',
				),
			),
			'alt1_link_v_fgcolor' => array(), // visited (not used in vB4)
			'alt1_link_m_fgcolor' => array( // hover
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'block_linkhover_color',
					'blockrow_linkhover_color',
					'forum_sidebar_linkhover_color',
				),
			),


			// ***** alternating color #2 *****
			'alt2_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'navbar_tab_selected_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'secondarycontent_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadbit_alt_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadbit_deleted_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'threadbit_hilite_background',
						'apply_to' => array('color'),
					),
					//array(
					//	'stylevar' => 'postbit_boxed_background',
					//	'apply_to' => array('color'),
					//),
					array(
						'stylevar' => 'postbit_control_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_deleted_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_userinfo_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'postbit_foot_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'sidebar_background',
						'apply_to' => array('color'),
					),
				),
			),
			'alt2_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					'secondarycontent_color',
					'postbit_control_color',
					'sidebar_header_color',
				),
			),
			'alt2_link_n_fgcolor' => array( // normal
				'source_datatype'  => 'color',
				'target_stylevars' => array(),
			),
			'alt2_link_v_fgcolor' => array(), // visited
			'alt2_link_m_fgcolor' => array( // hover
				'source_datatype'  => 'color',
				'target_stylevars' => array(),
			),

			// ***** inline mod *****
			'inlinemod_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
					array(
						'stylevar' => 'imodhilite_backgroundColor',
						'apply_to' => array('color'),
					),
				),
			),
			'inlinemod_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),


			// ***** editor *****
			'wysiwyg_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'wysiwyg_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'wysiwyg_link_n_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'wysiwyg_link_v_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'wysiwyg_link_m_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),


			// ***** forum jump menu *****
			'fjsel_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'fjsel_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'fjdpth0_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'fjdpth0_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),


			// ***** forms *****
			// maps to the background of forms, if we can specify that
			'panel_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'panel_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			// border around forms.
			'panel_border_color' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			// maps to nothing? (the bg color for the padding around the form blocks)
			'panelsurround_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'panelsurround_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'legend_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),


			// ***** popup menus *****
			'vbmenu_control_bgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
				),
			),
			'vbmenu_control_fgcolor' => array(
				'source_datatype'  => 'color',
				'target_stylevars' => array(
				),
			),
			'vbmenu_popup_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_popup_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_option_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_option_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_hilite_bgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_hilite_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_control_link_n_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_control_link_v_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_control_link_m_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_popup_border_color' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_option_link_n_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_option_link_v_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_option_link_m_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_hilite_link_n_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_hilite_link_v_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'vbmenu_hilite_link_m_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),


			// ***** misc *****
			'time_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'highlight_fgcolor' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),

		),

		// ----------------------------------------------------
		'stylevars' => array(

			// outertablewidth -> doc_width and doc_maxWidth
			'outertablewidth' => array(
				'source_datatype'  => 'int_pixel_or_percentage',
				'target_stylevars' => array(
					array(
						'stylevar' => 'doc_width',
						'condition' => 'if_source_not_100_percent',
					),
					array(
						'stylevar' => 'doc_maxWidth',
						'condition' => 'if_source_not_100_percent',
					),
					array(
						'stylevar' => 'doc_margin',
						'apply_to' => array('left', 'right'),
						'condition' => 'if_source_not_100_percent',
						'value' => 'auto',
						'units' => false,
					),
				),
			),

			// note: this should only apply to the left and right parts of body_padding
			// not top and bottom, since spacersize only affects the right and left margins
			'spacersize' => array(
				'source_datatype'  => 'int_pixel',
				'target_stylevars' => array(
					array(
						'stylevar' => 'body_padding',
						'apply_to' => array('left', 'right'),
					),
				),
			),

			// not used in vB3 except in styles imported from vB2
			'outerborderwidth' => array(),

			// does not correspond to anything in the vB4 style
			'cellspacing' => array(),

			// padding inside table cells
			'cellpadding' => array(
				'source_datatype'  => 'int_pixel',
				'target_stylevars' => array('padding', 'blockhead_padding', /*'blocksubhead_padding',*/ 'footer_padding'),
			),

			// this does not map to any vB4 stylevars (this is the padding
			// around each item on a form)
			'formspacer' => array(),

			// In addition to mapping this to form_maxWidth, we need to calculate sensible values for
			// form_columnleft_width and form_columnright_width. Additionally, the value of this stylevar
			// should probably not be mapped directly to form_maxWidth, since the vB4 standard form
			// has a two column layout which doesn't work as well for small values of formwidth.
			'formwidth' => array(
				'source_datatype'  => 'int_pixel',
				'target_stylevars' => array(

					// will this stylevar be removed from 4.1.3?
					'form_maxWidth',

					array(
						'stylevar' => 'form_columnleft_width',
						//'apply_to' => array('width'),
						'multiply_by' => 0.3,
					),
					array(
						'stylevar' => 'form_columnright_width',
						//'apply_to' => array('width'),
						'multiply_by' => 0.7,
					),
				),
			),

			// not used. The vB4 user cp (settings) form uses the full width of the page
			'formwidth_usercp' => array(),

			// there is no stylevar to support these in vB4
			'messagewidth' => array(),
			'messagewidth_usercp' => array(),
			'messagewidth_blog' => array(),

			// direct correlation for title image
			'titleimage' => array(
				'source_datatype'  => 'url',
				'target_stylevars' => array(
					array(
						'stylevar' => 'titleimage',
						'condition' => 'if_source_file_exists',
					),
				),
			),

			// not used in vB4 (width for code/html/php blocks is automatic)
			'codeblockwidth' => array(),

			// Image directories
			/*
			Transferring image directories often gives unwanted results, due to
			all the new vB4 images in use that are not in vB3 styles, and
			the fact that images are used very differently in vB4 as
			compared to vB3-- for example, Post new thread is no longer an
			image, and all the postbit buttons/controls no longer use
			images for the button, but they do have an image beside each
			text button.
			*/
			'imgdir_reputation' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_reputation'),
			),
			'imgdir_statusicon' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_statusicon'),
			),
			'imgdir_rating' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_rating'),
			),
			'imgdir_misc' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_misc'),
			),
			'imgdir_editor' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_editor'),
			),
			'imgdir_button' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_button'),
			),
			'imgdir_attach' => array(
				'source_datatype'  => 'path',
				'target_stylevars' => array('imgdir_attach'),
			),


			// not used (images are no longer used for poll percentage bars)
			'imgdir_poll' => array(),

			// the html doctype
			// don't transfer this, since vB4 template markup is not changed
			// and should thus use the default vB4 doctype
			//'htmldoctype' => array(
			//	'source_datatype'  => 'string',
			//	'target_stylevars' => array('htmldoctype'),
			//),

			// not used
			'imagesfolder' => array(),
			'htmlextrasfolder' => array(),
			'textareacols_ie4' => array(),
			'textareacols_ns4' => array(),
			'textareacols_ns6' => array(),
			'tablewidth' => array(),
		),

		// ----------------------------------------------------
		// do nothing with these, since the upcoming ckeditor
		// does not implement customizability
		'editorstyles' => array(
			'pi_popup_down' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_button_down' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_button_hover' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_button_normal' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_button_selected' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_menu_down' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_menu_hover' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
			'pi_menu_normal' => array(
				'source_datatype'  => '',
				'target_stylevars' => array(),
			),
		),

		// ----------------------------------------------------
		// set a few vB4 stylevars to hardcoded values to
		// work around problems in the vB4 stylevar system
		'manual_override' => array(
			array(
				'source_datatype'  => 'color',
				'source_value'     => 'transparent',
				'target_stylevars' => array(
					array(
						'stylevar' => 'navlinks_background',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'navlinks_border_top',
						'apply_to' => array('color'),
					),
					array(
						'stylevar' => 'blockfoot_background',
						'apply_to' => array('color'),
					),
				),
			),

		),
	);


	/*Construction====================================================================*/


	/**
	 * Constructor
	 *
	 * @param	vB_Registry	Reference to the vBulletin registry
	 */
	public function __construct(vB_Registry &$vbulletin)
	{
		$this->registry =& $vbulletin;
		$this->db =& $this->registry->db;
	}


	/*Public Methods====================================================================*/


	/**
	 * Sets an option
	 *
	 * @param	string	Name of the option to set
	 * @param	mixed	Value to set the option to
	 */
	public function set_option($name, $value)
	{
		if (isset($this->options[$name]))
		{
			$this->options[$name] = $value;
		}
		else
		{
			throw new Exception('~~Trying to set a non-existent option: ' . htmlspecialchars_uni($name) . '.~~');
		}
	}

	/**
	 * Converts vBulletin 3 style information to vBulletin 4 Stylevars for the given style.
	 *
	 * @param	int	The styleid to convert
	 */
	public function convert($styleid)
	{
		global $vbphrase;

		$this->styleid = intval($styleid);

		if ($this->styleid < 1)
		{
			print_stop_message('invalid_style_specified');
		}

		if ($this->options['create_new_style'])
		{
			// Make a copy of the style and change our styleid to the new one
			$this->styleid = $this->duplicate_style($this->styleid);
		}

		$this->display_begin();

		$this->display("<b>" . $vbphrase['mapping_stylevars'] . "</b>\n<ul>");
		foreach ($this->map AS $vb3_stylevar_type => $values)
		{
			foreach ($values AS $source_stylevar_name => $target_mapping_data)
			{
				$this->convert_vb3_stylevar($vb3_stylevar_type, $source_stylevar_name, $target_mapping_data);
			}
		}
		$this->display('</ul>');

		if ($this->options['revert_templates'])
		{
			$this->revert_templates();
		}

		$this->display_end();

		require_once(DIR . '/includes/adminfunctions_template.php');
		cache_styles();
		build_all_styles();
		build_style_datastore();

		if ($this->options['output_progress'])
		{
			print_form_header('template', 'modify', false, true, 'cpform', '90%', '', false, 'get');
			construct_hidden_code('expandset', $this->styleid);
			print_submit_row($vbphrase['done'], false);
		}
	}


	/*Internal Methods====================================================================*/


	/**
	 * Converts one vB3 stylevar to its vB4 counterpart(s) and saves it to the database
	 * in the given styleid.
	 *
	 * @param	string	The vB3 stylevar type [csscolors|stylevars|editorstyles]
	 * @param	string	The vB3 source stylevar name
	 * @param	array	The mapping data for the target vB4 stylevar(s)
	 */
	protected function convert_vb3_stylevar($vb3_stylevar_type, $source_stylevar_name, array $target_mapping_data)
	{
		global $vbphrase;

		// sanity check
		if (empty($target_mapping_data) OR empty($target_mapping_data['source_datatype']) OR empty($target_mapping_data['target_stylevars']))
		{
			return;
		}

		// int_pixel_or_percentage, int_pixel, url, path, string, color, etc.
		$source_datatype = $target_mapping_data['source_datatype'];

		// each stylevar is a string or an array of data
		$target_stylevars = $target_mapping_data['target_stylevars'];

		// check for manual override stylevars
		if ($vb3_stylevar_type == 'manual_override')
		{
			if (isset($target_mapping_data['source_value']) AND $target_mapping_data['source_value'] != '')
			{
				$source_value = $target_mapping_data['source_value'];
			}
			else
			{
				throw new Exception('~~Invalid or missing source_value for a manual_override stylevar.~~');
			}
		}
		else // the other 3 stylevar types
		{
			$source_value = $this->get_vb3_stylevar($vb3_stylevar_type, $source_stylevar_name);
			if ($source_value === null)
			{
				return; // non-existent / non-set source stylevar
			}
		}

		$units = false;

		switch($source_datatype)
		{
			case 'int_pixel_or_percentage':
				$units = (substr($source_value, -1, 1) == '%') ? '%' : 'px';
				$source_value = intval($source_value);
				break;

			case 'int_pixel':
				$units = 'px';
				$source_value = intval($source_value);
				break;

			case 'color':
				$source_value = strval($source_value);
				if (strlen($source_value) > 7 AND preg_match('/(#[a-f0-9]{6})/i', $source_value, $match))
				{
					// extract "#XXXXXX" from "1px solid #XXXXXX"
					$source_value = $match[1];
				}
				break;

			case 'url':
			case 'path':
			case 'string':
				$source_value = strval($source_value);
				break;

			default:
				throw new Exception('~~Invalid source datatype: "' . htmlspecialchars_uni($source_datatype) . '"~~');
				break;
		}


		$stylevarids = array();
		foreach ($target_stylevars AS $target_stylevar)
		{
			$stylevarids[] = $this->write_vb4_stylevar($target_stylevar, $source_value, $units);
		}

		if ($vb3_stylevar_type == 'manual_override')
		{
			++$source_stylevar_name; // friendlier display of manual_override index
		}

		$this->display('<li>' . htmlspecialchars_uni("$vb3_stylevar_type:$source_stylevar_name") .
			" <i>" . $vbphrase['mapped_to'] . "</i>\n\t<ul>\n\t\t<li>" . implode("</li>\n\t\t<li>", $stylevarids) .
			"</li>\n\t</ul>\n</li>\n");
	}

	/**
	 * Creates and saves a vB4 stylevar based on vB3 style information
	 *
	 * @param	string	The target vB4 stylevar to create/update
	 * @param	mixed	The new value for the stylevar
	 * @param	mixed	The new value for the units sub-item in the stylevar (where applicable)
	 *
	 * @return	string	The stylevarid of the vB4 stylevar that was created/updated
	 */
	protected function write_vb4_stylevar($target_stylevar, $new_value, $units)
	{
		global $vbphrase;

		if (is_string($target_stylevar))
		{
			// simple target stylevar
			$stylevarid = $target_stylevar;
		}
		else
		{
			// complex target stylevar
			// handle any special cases where the stylevar has to be manipulated

			$stylevarid = $target_stylevar['stylevar'];

			if (isset($target_stylevar['condition']))
			{
				// any new conditions added to $map need to be handled here
				switch($target_stylevar['condition'])
				{
					case 'if_source_not_100_percent':
						if ($new_value == 100 AND $units == '%')
						{
							return construct_phrase($vbphrase['x_skipped'], htmlspecialchars_uni($stylevarid));
						}
						break;

					case 'if_source_file_exists':
						$tmp_img_path = preg_replace('/[^a-z0-9.\/_-]/i', '', $new_value);
						if ($tmp_img_path == $new_value AND !file_exists($tmp_img_path))
						{
							return construct_phrase($vbphrase['x_skipped'], htmlspecialchars_uni($stylevarid));
						}
						break;

					default:
						throw new Exception('~~Unhandled target stylevar condition: "' . htmlspecialchars_uni($target_stylevar['condition']) . '"~~');
						break;
				}
			}

			if (isset($target_stylevar['multiply_by']))
			{
				$new_value = intval($new_value * $target_stylevar['multiply_by']);
			}

			if (isset($target_stylevar['value']))
			{
				$new_value = $target_stylevar['value'];
			}

			if (isset($target_stylevar['units']))
			{
				$units = $target_stylevar['units'];
			}
		}

		// skip img paths?
		if (substr($stylevarid, 0, 7) == 'imgdir_' AND $this->options['skip_image_paths'])
		{
			return construct_phrase($vbphrase['x_skipped'], htmlspecialchars_uni($stylevarid));
		}

		$stylevar_definition = $this->get_stylevar_definition($stylevarid);
		$stylevar_datatype = $stylevar_definition['datatype'];

		// sanity check for a valid target stylevarid
		if (!$stylevar_definition)
		{
			if ($this->registry->debug)
			{
				return construct_phrase($vbphrase['x_skipped'], htmlspecialchars_uni($stylevarid)) . ' <span style="color:red">Non-existent target Stylevar.</span>';
			}
			else
			{
				return construct_phrase($vbphrase['x_skipped'], htmlspecialchars_uni($stylevarid));
			}
		}

		// set up stylevar data manager
		$svinstance = datamanager_init('StyleVar' . $stylevar_datatype, $this->registry, ERRTYPE_CP, 'stylevar');
		if ($this->stylevar_exists($stylevarid))
		{
			// this happens if we run the conversion on the same style more than once
			// or a style that already has one or more target vB4 stylevars set
			$svexisting = array('stylevarid' => $stylevarid, 'styleid' => $this->styleid);
			$svinstance->set_existing($svexisting);
		}
		else
		{
			$svinstance->set('stylevarid', $stylevarid);
			$svinstance->set('styleid', $this->styleid);
		}
		$svinstance->set('username', $this->registry->userinfo['username']);

		// set units when desired, this is overridden by the default when using 'apply_to'
		if ($units)
		{
			$svinstance->set_child('units', $units);
		}

		$handle_apply_to = (is_array($target_stylevar) AND isset($target_stylevar['apply_to']) AND !empty($target_stylevar['apply_to']));
		$default = $this->get_default_stylevar_value($stylevarid);

		switch ($stylevar_datatype)
		{
			// simple stylevar datatypes
			case 'url':
			case 'imagedir':
			case 'string':
			case 'color':
			case 'size':
				$svinstance->set_child($stylevar_datatype, $new_value);
				break;

			// complex stylevar datatypes
			case 'padding':
			case 'margin':
				if ($handle_apply_to)
				{
					// set defaults
					foreach (array('top', 'right', 'bottom', 'left', 'units') AS $one)
					{
						if (isset($default[$one]))
						{
							$svinstance->set_child($one, $default[$one]);
						}
					}

					foreach ($target_stylevar['apply_to'] AS $apply_to)
					{
						$svinstance->set_child($apply_to, $new_value);
					}
					$svinstance->set_child('same', 0);
				}
				else
				{
					$svinstance->set_child('top', $new_value);
					$svinstance->set_child('same', 1);
				}
				break;

			case 'border':
				if ($handle_apply_to)
				{
					// set defaults
					foreach (array('width', 'style', 'color', 'units') AS $one)
					{
						if (isset($default[$one]))
						{
							$svinstance->set_child($one, $default[$one]);
						}
					}

					foreach ($target_stylevar['apply_to'] AS $apply_to)
					{
						$svinstance->set_child($apply_to, $new_value);
					}
				}
				else
				{
					throw new Exception('~~Trying to handle a "border" stylevar that has no "apply_to" set: "' . htmlspecialchars_uni($stylevarid) . '"~~');
				}
				break;

			case 'background':
				if ($handle_apply_to)
				{
					// set defaults
					foreach (array('color', 'image', 'repeat', 'units', 'x', 'y') AS $one)
					{
						if (isset($default[$one]))
						{
							$svinstance->set_child($one, $default[$one]);
						}
					}
					// backgrounds always use 'apply_to', since we can never
					// transfer a 'background' from vB3 to vB4
					foreach ($target_stylevar['apply_to'] AS $apply_to)
					{
						$svinstance->set_child($apply_to, $new_value);
					}
				}
				else
				{
					throw new Exception('~~Trying to handle a "background" stylevar that has no "apply_to" set: "' . htmlspecialchars_uni($stylevarid) . '"~~');
				}
				break;

			default:
				throw new Exception('~~Unhandled target stylevar datatype: "' . htmlspecialchars_uni($stylevar_datatype) . '"~~');
				break;
		}

		$svinstance->build();
		$svinstance->save();

		return $stylevarid;
	}

	/**
	 * Returns the ids for all the vB4 stylevars that are slated to be changed by the conversion process.
	 *
	 * @return	array	An array of all target stylevarids that will be changed
	 */
	protected function get_target_stylevar_ids()
	{
		// memoization
		static $target_stylevar_ids = array();

		if (empty($target_stylevar_ids))
		{
			foreach ($this->map AS $section)
			{
				foreach ($section AS $sectiondata)
				{
					if (!empty($sectiondata))
					{
						foreach ($sectiondata['target_stylevars'] AS $target_stylevar)
						{
							if (is_string($target_stylevar))
							{
								$target_stylevar_ids[] = $target_stylevar;
							}
							else
							{
								$target_stylevar_ids[] = $target_stylevar['stylevar'];
							}
						}
					}
				}
			}
		}

		return $target_stylevar_ids;
	}

	/**
	 * Returns a vB3 stylevar value
	 *
	 * @param	string	The vB3 stylevar type [csscolors|stylevars|editorstyles]
	 * @param	string	The vB3 stylevar name
	 *
	 * @return	mixed	The vB3 stylevar value or null if the stylevar doesn't exist/is not set
	 */
	protected function get_vb3_stylevar($stylevar_type, $stylevar_name)
	{
		if ($this->vb3_stylevar_cache === null)
		{
			$style = $this->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "style
				WHERE styleid = " . intval($this->styleid) . "
			");
			$this->vb3_stylevar_cache = array(
				'csscolors'    => unserialize($style['csscolors']),
				'stylevars'    => unserialize($style['stylevars']),
				'editorstyles' => unserialize($style['editorstyles']),
			);

			// Add empty values for items that have been customized as empty
			// by checking to see if they are defined in the master style.
			// Ignore editorstyles as they will not be customizable in vB4.
			$master = $this->db->query_read("
				SELECT title, templatetype, template
				FROM " . TABLE_PREFIX . "template
				WHERE
					styleid = -1
						AND
					templatetype IN('stylevar', 'css')
			");
			$css = array();
			$stylevars = array();
			while ($template = $this->db->fetch_array($master))
			{
				if ($template['templatetype'] == 'css')
				{
					$css[$template['title']] = unserialize($template['template']);
				}
				else
				{
					$stylevars[$template['title']] = $template['template'];
				}
			}

			require_once(DIR . '/includes/adminfunctions_template.php');
			$csscolors = array();
			construct_css($css, '', '', $csscolors);

			foreach ($csscolors AS $k => $v)
			{
				if (!isset($this->vb3_stylevar_cache['csscolors'][$k]))
				{
					$this->vb3_stylevar_cache['csscolors'][$k] = '';
				}
			}
			foreach ($stylevars AS $k => $v)
			{
				if (!isset($this->vb3_stylevar_cache['stylevars'][$k]))
				{
					$this->vb3_stylevar_cache['stylevars'][$k] = '';
				}
			}
		}

		if (isset($this->vb3_stylevar_cache[$stylevar_type][$stylevar_name]))
		{
			return $this->vb3_stylevar_cache[$stylevar_type][$stylevar_name];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Returns the stylevar values for the given stylevar ids and styleid
	 *
	 * @param	int	The styleid
	 * @param	array	The stylevar ids
	 *
	 * @return	array	The stylevar values
	 */
	protected function get_stylevars($styleid, array $stylevar_ids)
	{
		$result = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "stylevar
			WHERE
				stylevarid IN ('" . implode("', '", $stylevar_ids) . "')
				AND
				styleid = " . intval($styleid) . "
		");

		$values = array();
		while($row = $this->db->fetch_array($result))
		{
			$row['value'] = unserialize($row['value']);
			$values[$row['stylevarid']] = $row;
		}
		return $values;
	}

	/**
	 * Indicates whether or not the target vB4 stylevar already exists (or if it needs to be created new).
	 *
	 * @param	string	Stylevar ID
	 *
	 * @return	bool	Whether or not the stylevar already exists
	 */
	protected function stylevar_exists($stylevarid)
	{
		$this->cache_vb4_existing_stylevars();

		return isset($this->vb4_existing_stylevar_cache[$stylevarid]);
	}

	/**
	 * Caches any vB4 stylevars that we are going to write to, that already exist
	 */
	protected function cache_vb4_existing_stylevars()
	{
		if ($this->vb4_existing_stylevar_cache === null)
		{
			$target_stylevar_ids = $this->get_target_stylevar_ids();
			$this->vb4_existing_stylevar_cache = $this->get_stylevars($this->styleid, $target_stylevar_ids);
		}
	}

	/**
	 * Returns a vB4 stylevar definition
	 *
	 * @param	string	The stylevar id
	 *
	 * @return	array	The stylevar definition
	 */
	protected function get_stylevar_definition($stylevarid)
	{
		if ($this->vb4_stylevar_definition_cache === null)
		{
			$target_stylevar_ids = $this->get_target_stylevar_ids();
			$this->vb4_stylevar_definition_cache = $this->get_stylevar_definitions($target_stylevar_ids);
		}

		if (isset($this->vb4_stylevar_definition_cache[$stylevarid]))
		{
			return $this->vb4_stylevar_definition_cache[$stylevarid];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns an array of stylevar definitions
	 *
	 * @param	array	An array of stylevar ids
	 *
	 * @return	array	The stylevar definitions
	 */
	protected function get_stylevar_definitions(array $stylevar_ids)
	{
		$dfns_result = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "stylevardfn
			WHERE stylevarid IN ('" . implode("', '", $stylevar_ids) . "')
		");

		$dfns = array();
		while($dfn = $this->db->fetch_array($dfns_result))
		{
			$dfns[$dfn['stylevarid']] = $dfn;
		}
		return $dfns;
	}

	/**
	 * Returns the default value (from styleid -1) for the given stylevar
	 *
	 * @param	string	Stylevarid
	 *
	 * @return	array	Default stylevar value
	 */
	protected function get_default_stylevar_value($stylevarid)
	{
		if ($this->vb4_default_stylevar_cache === null)
		{
			$target_stylevar_ids = $this->get_target_stylevar_ids();
			$default = $this->get_stylevars(-1, $target_stylevar_ids);

			// overwrite defaults with any existing stylevars
			$this->cache_vb4_existing_stylevars();
			$default = array_merge($default, $this->vb4_existing_stylevar_cache);

			$this->vb4_default_stylevar_cache = $default;
		}

		return $this->vb4_default_stylevar_cache[$stylevarid]['value'];
	}

	/**
	 * Conditionally displays begining output for displaying progress
	 */
	protected function display_begin()
	{
		global $vbphrase;

		if ($this->options['output_progress'])
		{
			print_table_start();
			print_table_header($vbphrase['generate_vbulletin_4_style'], 1);
			echo '<tr><td class="alt1"><div style="padding:4px;">';
		}
	}

	/**
	 * Conditionally displays the conversion progress
	 *
	 * @param	string	HTML to display
	 */
	protected function display($html)
	{
		if ($this->options['output_progress'])
		{
			echo $html;
		}
	}

	/**
	 * Conditionally displays ending output for displaying progress
	 */
	protected function display_end()
	{
		if ($this->options['output_progress'])
		{
			echo '</div></td></tr>';
			print_table_footer(1);
		}
	}

	/**
	 * Reverts all templates in this style
	 *
	 * @return	bool	Whether or not any templates were reverted
	 */
	protected function revert_templates()
	{
		global $vbphrase;

		if ($this->styleid < 0)
		{
			$this->display('<p><b>' . $vbphrase['no_templates_to_revert'] . '</b></p>');
			return false;
		}

		$style = $this->db->query_first("
			SELECT styleid, parentid, parentlist, title
			FROM " . TABLE_PREFIX . "style
			WHERE styleid = " . intval($this->styleid)
		);

		if (!$style)
		{
			$this->display('<p><b>' . $vbphrase['no_templates_to_revert'] . '</b></p>');
			return false;
		}

		if (!$style['parentlist'])
		{
			$style['parentlist'] = '-1';
		}

		$templates = $this->db->query_read("
			SELECT DISTINCT t1.templateid, t1.title
			FROM " . TABLE_PREFIX . "template AS t1
			INNER JOIN " . TABLE_PREFIX . "template AS t2 ON
				(t2.styleid IN ($style[parentlist]) AND t2.styleid <> $style[styleid] AND t2.title = t1.title)
			WHERE t1.templatetype = 'template'
				AND t1.styleid = $style[styleid]
		");

		if ($this->db->num_rows($templates) > 0)
		{
			$deletetemplates = array();

			while ($template = $this->db->fetch_array($templates))
			{
				$deletetemplates["$template[title]"] = $template['templateid'];
			}
			$this->db->free_result($templates);

			if (!empty($deletetemplates))
			{
				$this->db->query_write("DELETE FROM " . TABLE_PREFIX . "template WHERE templateid IN(" . implode(',', $deletetemplates) . ")");
				$this->db->query_write("DELETE FROM " . TABLE_PREFIX . "templatemerge WHERE templateid IN(" . implode(',', $deletetemplates) . ")");

				//print_rebuild_style($style['styleid'], '', 0, 0, 0, 0);
				$this->display('<p><b>' . $vbphrase['templates_reverted'] . '</b></p>');
				return true;
			}
		}

		$this->display('<p><b>' . $vbphrase['no_templates_to_revert'] . '</b></p>');
		return false;
	}

	/**
	 * Makes a copy of a style
	 *
	 * @param	int	Source styleid
	 *
	 * @return	int	New styleid
	 */
	protected function duplicate_style($styleid)
	{
		global $vbphrase;

		// copy style record
		$this->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "style
			(title, parentid, parentlist, templatelist, csscolors, css, stylevars, newstylevars,
			 replacements, editorstyles, userselect, displayorder, dateline)
				SELECT CONCAT(title, '" . $this->db->escape_string($vbphrase['copy_parens']) . "'), parentid, parentlist, templatelist, csscolors, css, stylevars, newstylevars,
				       replacements, editorstyles, 0, displayorder, UNIX_TIMESTAMP()
				FROM " . TABLE_PREFIX . "style
				WHERE styleid = " . intval($styleid) . "
		");
		$newstyleid = $this->db->insert_id();

		// copy template records - this takes care of old stylevars, template replacements, etc
		$this->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "template
			(styleid, title, template, template_un, templatetype, dateline, username, version, product)
				SELECT " . intval($newstyleid) . ", title, template, template_un, templatetype, dateline, username, version, product
				FROM " . TABLE_PREFIX . "template
				WHERE styleid = " . intval($styleid) . "
		");

		// copy new stylevar records
		$this->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "stylevar
			(stylevarid, styleid, value, dateline, username)
				SELECT stylevarid, " . intval($newstyleid) . ", value, dateline, username
				FROM " . TABLE_PREFIX . "stylevar
				WHERE styleid = " . intval($styleid) . "
		");

		// skip template merge and template history. Copying a style is not expected to preserve these.

		return $newstyleid;
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision$
|| ####################################################################
\*======================================================================*/
