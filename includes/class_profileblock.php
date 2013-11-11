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
* Factory class to create blocks for the Profile Display
*
* @package	vBulletin
*/

class vB_ProfileBlockFactory
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry;

	/**
	* The UserProfile Object
	*
	* @var	vB_UserProfile
	*/
	var $profile;

	/**
	* Cache of the Profile Blocks already loaded
	*
	* @var	array
	*/
	var $cache = array();

	/**
	 * Cache of each block's privacy requirements
	 *
	 * @var	array integer
	 */
	var $privacy_requirements;

	/**
	* Constructor
	*
	* @param	vB_Registry
	* @param	vB_UserProfile
	*/
	function vB_ProfileBlockFactory(&$registry, &$profile)
	{
		$this->registry =& $registry;
		$this->profile =& $profile;
	}

	/**
	* Fetches a Profile Block object
	*
	* @param	string	The name of the class
	*/
	function &fetch($class)
	{
		if (!isset($this->cache["$class"]) OR !is_object($this->cache["$class"]))
		{
			$classname = "vB_ProfileBlock_$class";
			$this->cache["$class"] = new $classname($this->registry, $this->profile, $this);
		}

		return $this->cache["$class"];
	}
}
/**
* Abstract Class for Profile Blocks
*
* @package vBulletin
*/
class vB_ProfileBlock
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry;

	/**
	* User Profile Object
	*
	* @var	vB_UserProfile
	*/
	var $profile;

	/**
	* Factory Object
	*
	* @var	vB_ProfileBlockFactory
	*/
	var $factory;

	/**
	* Default Options for the block
	*
	* @var	array
	*/
	var $option_defaults = array();

	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = '';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array();

	/**
	* Data that is only used within the block itself
	*
	* @var array
	*/
	var $block_data = array();

	/**
	* Whether to skip privacy checking.
	*
	* @var boolean
	*/
	var $skip_privacy_check = false;

	/**
	 * The default privacy requirement to view the block if one was not set by the user
	 *
	 * @var integer
	 */
	var $default_privacy_requirement = 0;

	/**
	 * Whether to wrap output in standard block template.
	 *
	 * @var boolean
	 */
	var $nowrap;

	/**
	* Constructor - Prepares the block, and automatically prepares needed data
	*
	* @param	vB_Registry
	* @param	vB_UserProfile
	* @param	vB_ProfileBlockFactory
	*/
	function vB_ProfileBlock(&$registry, &$profile, &$factory)
	{
		$this->registry =& $registry;
		$this->profile =& $profile;
		$this->factory =& $factory;

		foreach ($this->auto_prepare AS $prepare)
		{
			$profile->prepare($prepare);
		}

		$this->fetch_default_options();
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return true;
	}

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled($id)
	{
		return true;
	}

	/**
	* Fetch the block
	*
	* @param	string	The title of the Block
	* @param	string	The id of the Block
	* @param	array	Options specific to the block
	* @param	array	Userinfo of the visiting user
	*
	* @return	string	The Block's output to be shown on the profile page
	*/
	function fetch($title, $id = '', $options = array(), $visitor)
	{
		if ($this->block_is_enabled($id))
		{
			if (!$this->visitor_can_view($id, $visitor))
			{
				return '';
			}

			$html = $this->fetch_unwrapped($title, $id, $options);

			if (trim($html) === '' AND !$this->confirm_empty_wrap())
			{
				return '';
			}
			else
			{
				if ($this->nowrap)
				{
					return $html;
				}
				else
				{
					return $this->wrap($title, $id, $html);
				}
			}
		}
		else
		{
			return '';
		}
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return true;
	}

	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
	}

	/**
	* Fetches the unwrapped (no box around it) version of the block
	*
	* @param	string	The title of the block
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*
	* @return	string
	*/
	function fetch_unwrapped($title, $id = '', $options = array())
	{
		global $show, $vbphrase, $vbcollapse, $selected_tab;

		$this->prepare_output($id, $options);

		if (!$this->confirm_display())
		{
			return '';
		}

		$prepared = $this->profile->prepared;
		$userinfo = $this->profile->userinfo;
		$block_data = $this->block_data;

		($hook = vBulletinHook::fetch_hook('member_profileblock_fetch_unwrapped')) ? eval($hook) : false;

		$templater = vB_Template::create($this->template_name);
			$templater->register('block_data', $block_data);
			$templater->register('id', $id);
			$templater->register('selected_tab', $selected_tab);
			$templater->register('prepared', $prepared);
			$templater->register('template_hook', $template_hook);
			$templater->register('userinfo', $userinfo);
			$templater->register('title', $title);
			$templater->register('nowrap', $this->nowrap);
		return $templater->render();
	}

	/**
	* Wraps the given HTML in it's containing block
	*
	* @param	string	The title of the block
	* @param	string	The id of the block
	* @param	string	The HTML to be wrapped
	*
	* @return	string
	*/
	function wrap($title, $id = '', $html = '')
	{
		global $show, $vbphrase, $vbcollapse, $selected_tab;


		$templater = vB_Template::create('memberinfo_block');
			$templater->register('html', $html);
			$templater->register('id', $id);
			$templater->register('title', $title);
			$templater->register('show', $show);
			$templater->register('selected_tab', $selected_tab);
		return $templater->render();
	}

	/**
	* Determines whether the visitor is allowed to view a block based on their
	* relationship to the subject user, and what the subject user has configured.
	*
	* @param	integer	Id of the block
	* @param	array	Userinfo of the visitor
	*/
	function visitor_can_view($id, $visitor)
	{
		// Some blocks should always be shown
		if ($this->skip_privacy_check)
		{
			return true;
		}

		if (!$this->registry->options['profileprivacy'] OR (!($this->profile->prepared['userperms']['usercsspermissions'] & $this->registry->bf_ugp_usercsspermissions['caneditprivacy'])))
		{
			$requirement = $this->default_privacy_requirement;
		}
		else
		{
			if (!isset($this->factory->privacy_requirements))
			{
				$this->fetch_privacy_requirements();
			}

			$requirement = (isset($this->factory->privacy_requirements[$id]) ? $this->factory->privacy_requirements[$id] : $this->default_privacy_requirement);
		}

		return (fetch_user_relationship($this->profile->userinfo['userid'], $this->registry->userinfo['userid']) >= $requirement);
	}

	/**
	* Fetches the privacy requirements for the current user.
	*/
	function fetch_privacy_requirements()
	{
		$this->factory->privacy_requirements = array();

		$requirements = $this->registry->db->query_read_slave("
			SELECT blockid, requirement
			FROM " . TABLE_PREFIX . "profileblockprivacy
			WHERE userid = " . intval($this->profile->userinfo['userid']) . "
		");

		while ($requirement = $this->registry->db->fetch_array($requirements))
		{
			$this->factory->privacy_requirements[$requirement['blockid']] = $requirement['requirement'];
		}
		$this->registry->db->free_result($requirements);
	}
}

/**
* Profile Block for Mini Statistics
*
* @package vBulletin
*/
class vB_ProfileBlock_MiniStats extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_ministats';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array(
		'age',
		'avatarurl',
		'birthday',
		'joindate',
		'posts'
	);
}

/**
* Profile Block for Friends
*
* @package vBulletin
*/
class vB_ProfileBlock_Friends extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_friends_mini';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array(
		'friendcount'
	);
	
	var $nowrap = true;
	
	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'fetchamount'    => $this->registry->options['minifriends_amount'],
			'fetchstart'     => 0,
			'fetchorder'     => 'rand',
			'membertemplate' => 'memberinfo_tiny'
		);
	}

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled($id)
	{
		return ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_friends'] ? true : false);
	}

	/**
	* Fetches the unwrapped (no box around it) version of the block
	*
	* @param	string	The title of the block
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*
	* @return	string
	*/
	function fetch_unwrapped($title, $id = '', $options = array())
	{
		global $show, $vbphrase, $vbcollapse;

		$this->prepare_output($id, $options);

		if (!$this->confirm_display())
		{
			return '';
		}

		$prepared = $this->profile->prepared;
		$userinfo = $this->profile->userinfo;
		$block_data = $this->block_data;

		if (isset($options['template_override']))
		{
			$templatename = $options['template_override'];
		}
		else
		{
			$templatename = $this->template_name;
		}

		$templater = vB_Template::create($templatename);
			$templater->register('block_data', $block_data);
			$templater->register('prepared', $prepared);
			$templater->register('template_hook', $template_hook);
			$templater->register('userinfo', $userinfo);
		return $templater->render();
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return ($this->profile->prepared['friendcount'] OR $this->profile->prepared['myprofile'] OR $this->profile->prepared['canbefriend']);
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		if ($this->profile->userinfo['friendcount'] != 0)
		{
			require_once(DIR . '/includes/functions_bigthree.php');

			$this->block_data = array();

			if (is_array($options))
			{
				$options = array_merge($this->option_defaults, $options);
			}
			else
			{
				$options = $this->option_defaults;
			}

			switch ($options['fetchorder'])
			{
				case 'asc': $friendorder = 'user.username ASC'; break;
				case 'desc': $friendorder = 'user.username DESC'; break;

				case 'rand':
				default:
					$friendorder = 'RAND()';
			}

			$pagenumber = $options['pagenumber'];

			if (isset($options['perpage']))
			{
				$perpage = $options['perpage'];
			}
			else
			{
				$perpage = $options['fetchamount'];
			}

			if (!$pagenumber OR ($options['tab'] != $id) OR empty($options['tab']))
			{
				$pagenumber = 1;
			}
			else if ($pagenumber > ceil(($this->profile->userinfo['friendcount']) / $perpage))
			{
				$pagenumber = ceil(($this->profile->userinfo['friendcount']) / $perpage);
			}
			$limitstart = ($pagenumber - 1) * $perpage;
			$limitamount = max(1, min($perpage, ($this->profile->userinfo['friendcount'] - $limitstart)));

			$hook_query_fields = $hook_query_joins = $hook_query_where = '';
			($hook = vBulletinHook::fetch_hook('member_profileblock_friends_query')) ? eval($hook) : false;

			$friends_sql = $this->registry->db->query_read_slave("
				SELECT user.*, (user.options & " . $this->registry->bf_misc_useroptions['invisible'] . ") AS invisible "
					. ($this->registry->options['avatarenabled'] ?
						", avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustom, customavatar.dateline AS avatardateline, customavatar.filedata_thumb, customavatar.height AS avheight, customavatar.width AS avwidth, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb" : "") . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "userlist AS userlist
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = userlist.relationid)"
				. ($this->registry->options['avatarenabled'] ? "
				LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid)
				LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) " : '') . "
				$hook_query_joins
				WHERE userlist.userid = " . $this->profile->userinfo['userid'] . "
					AND userlist.type = 'buddy'
					AND userlist.friend = 'yes'
					$hook_query_where
				ORDER BY $friendorder
				LIMIT $limitstart, $limitamount
			");

			$friendbits = '';
			$alt = 'alt2';
			while ($user = $this->registry->db->fetch_array($friends_sql))
			{
				fetch_avatar_from_userinfo($user, true);
				fetch_musername($user);

				$alt = ($alt == 'alt1') ? 'alt2' : 'alt1';
				fetch_online_status($user, true);
				construct_im_icons($user, true);
				if ($user['userid'] == $this->registry->userinfo['userid'] OR $this->profile->userinfo['userid'] == $this->registry->userinfo['userid'])
				{
					if ($user['userid'] == $this->registry->userinfo['userid'])
					{
						$show['return'] = true;
						$remove = array(
							'userid' => $this->profile->userinfo['userid'],
							'return' => 1,
						);
					}
					else
					{
						$remove = array('userid' => $user['userid']);
					}
					$show['breakfriendship'] = true;
				}
				else
				{
					$show['breakfriendship'] = false;
				}

				($hook = vBulletinHook::fetch_hook('member_profileblock_friendbit')) ? eval($hook) : false;

				$templater = vB_Template::create($options['membertemplate']);
					$templater->register('remove', $remove);
					$templater->register('user', $user);
				$friendbits .= $templater->render();
			}

			$pageinfo = array(
				'tab' => $id,
			);

			if ($perpage != $this->registry->options['friends_perpage'])
			{
				$paginfo['pp'] = $perpage;
			}


			$this->block_data['start_friends'] = $limitstart + 1;
			$this->block_data['friendbits'] = $friendbits;
			$this->block_data['showtotal'] = vb_number_format($this->registry->db->num_rows($friends_sql));
			$this->block_data['end_friends'] = $limitstart + $this->block_data['showtotal'];

			sanitize_pageresults($this->profile->userinfo['friendcount'], $pagenumber, $perpage, 100, 5);

			$this->block_data['pagenav'] = construct_page_nav(
				$pagenumber,
				$perpage,
				$this->profile->userinfo['friendcount'],
				'',
				'',
				$id,
				'member',
				$this->profile->userinfo,
				$pageinfo
			);
		}
	}

	/**
	* Fudge to share value between 'friends' and 'friends_mini' blocks.
	*
	* @param	integer	Id of the block
	* @param	array	Userinfo of the visitor
	*/
	function visitor_can_view($id, $visitor)
	{
		if ('friends_mini' == $id)
		{
			return parent::visitor_can_view('friends', $visitor);
		}

		return parent::visitor_can_view($id, $visitor);
	}
}

/**
* Profile Block for Profile Fields
*
* @package vBulletin
*/
class vB_ProfileBlock_ProfileFields extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_profilefield';

	/**
	* The categories to show in this block
	*
	* @var array
	*/
	var $categories = array(0 => array());

	/**
	* The Locations of the fields within the block
	*
	* @var array
	*/
	var $locations = array();

	/**
	* Whether the data has been built already
	*
	* @var bool
	*/
	var $data_built = false;

	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'category' => 'all'
		);
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return ($this->block_data['fields'] != '');
	}

	/**
	* Builds the custom Profile Field Data
	*
	* @param	boolean	Should we show hidden fields if we're allowed to view them?
	*/
	function build_field_data($showhidden)
	{
		if ($this->data_built)
		{
			return;
		}

		$this->categories = array(0 => array());
		$this->locations = array();

		if (!isset($this->factory->privacy_requirements))
		{
			$this->fetch_privacy_requirements();
		}

		$profilefields_result = $this->registry->db->query_read_slave("
			SELECT pf.profilefieldcategoryid, pfc.location, pf.*
			FROM " . TABLE_PREFIX . "profilefield AS pf
			LEFT JOIN " . TABLE_PREFIX . "profilefieldcategory AS pfc ON(pfc.profilefieldcategoryid = pf.profilefieldcategoryid)
			WHERE pf.form = 0 " . iif($showhidden OR !($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehiddencustomfields']), "
					AND pf.hidden = 0") . "
			ORDER BY pfc.displayorder, pf.displayorder
		");
		while ($profilefield = $this->registry->db->fetch_array($profilefields_result))
		{
			$requirement = (isset($this->factory->privacy_requirements["profile_cat$profilefield[profilefieldcategoryid]"])
				? $this->factory->privacy_requirements["profile_cat$profilefield[profilefieldcategoryid]"]
				: $this->default_privacy_requirement
			);

			if (fetch_user_relationship($this->profile->userinfo['userid'], $this->registry->userinfo['userid']) >= $requirement)
			{
				$this->categories["$profilefield[profilefieldcategoryid]"][] = $profilefield;
				$this->locations["$profilefield[profilefieldcategoryid]"] = $profilefield['location'];
			}
		}

		$this->data_built = true;
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		if (is_array($options))
		{
			$options = array_merge($this->option_defaults, $options);
		}
		else
		{
			$options = $this->option_defaults;
		}

		$options['simple'] = ($this->profile->prepared['myprofile'] ? $options['simple'] : false);

		$this->build_field_data($options['simple']);

		if ($options['category'] == 'all')
		{
			$categories = $this->categories;
			$show['profile_category_title'] = true;
			$enable_ajax_edit = true;
		}
		else
		{
			$categories = isset($this->categories["$options[category]"]) ?
				array($options['category'] => $this->categories["$options[category]"]) :
				array();
			$show['profile_category_title'] = false;
			$enable_ajax_edit = false;
		}

		$profilefields = '';

		foreach ($categories AS $profilefieldcategoryid => $profilefield_items)
		{
			$category = array(
				'title' => (
					$profilefieldcategoryid == 0 ?
					construct_phrase($vbphrase['about_x'], $this->profile->userinfo['username']) :
					$vbphrase["category{$profilefieldcategoryid}_title"]
				),
				'description' => $vbphrase["category{$profilefieldcategoryid}_desc"],
				'fields' => ''
			);

			foreach ($profilefield_items AS $profilefield)
			{
				$field_value = $this->profile->userinfo["field$profilefield[profilefieldid]"];
				fetch_profilefield_display($profilefield, $field_value);

				// can edit if viewing own profile and field is actually editable
				$show['profilefield_edit'] = (!$options['simple'] AND $enable_ajax_edit
					AND $this->registry->userinfo['userid'] == $this->profile->userinfo['userid']
					AND ($profilefield['editable'] == 1 OR ($profilefield['editable'] == 2 AND empty($field_value)))
					AND ($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canmodifyprofile'])
				);
				if ($show['profilefield_edit'] AND $profilefield['value'] == '')
				{
					// this field is to be editable but there's no value -- we need to show the field
					$profilefield['value'] = $vbphrase['n_a'];
				}

				($hook = vBulletinHook::fetch_hook('member_profileblock_profilefieldbit')) ? eval($hook) : false;

				if ($profilefield['value'] != '')
				{
					$show['extrainfo'] = true;

					$templater = vB_Template::create('memberinfo_profilefield');
						$templater->register('profilefield', $profilefield);
					$category['fields'] .= $templater->render();
				}
			}

			($hook = vBulletinHook::fetch_hook('member_profileblock_profilefield_category')) ? eval($hook) : false;

			if ($category['fields'])
			{
				$templater = vB_Template::create('memberinfo_profilefield_category');
					$templater->register('category', $category);
				$profilefields .= $templater->render();
			}
		}

		$this->block_data['fields'] = $profilefields;
	}
}

/**
* Profile Block for "About Me"
*
* @package vBulletin
*/
class vB_ProfileBlock_AboutMe extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_aboutme';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array(
		'signature',
		'profileurl'
	);
	
	var $nowrap = true;

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return true;
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show;

		$show['simple_link'] = (!$options['simple'] AND $this->registry->userinfo['userid'] == $this->profile->userinfo['userid']);
		$show['edit_link'] = ($options['simple'] AND $this->registry->userinfo['userid'] == $this->profile->userinfo['userid']
							AND ($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canmodifyprofile']));
		$blockobj =& $this->factory->fetch('ProfileFields');
		$blockobj->prepare_output($id, $options);
		$this->block_data['fields'] = $blockobj->block_data['fields'];
		$this->block_data['pageinfo_aboutme_view'] = array('tab' => 'aboutme', 'simple' => 1);
		$this->block_data['pageinfo_aboutme_edit'] = array('tab' => 'aboutme');
		$this->block_data['pageinfo_vcard'] = array('do' => 'vcard');
	}
}

/**
* Profile Block for Albums
*
* @package vBulletin
*/
class vB_ProfileBlock_Albums extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_albums';

	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'total'	=> $this->registry->options['profile_album_amount']
		);
	}

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled($id)
	{
		return (
			$this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums']
			AND $this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canviewalbum']
			AND ($this->profile->prepared['userperms']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canalbum'] OR can_moderate(0, 'canmoderatepictures'))
		);
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return ($this->block_data['albumbits'] OR ($this->profile->prepared['myprofile'] AND $this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canalbum']));
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		if (is_array($options))
		{
			$options = array_merge($this->option_defaults, $options);
		}
		else
		{
			$options = $this->option_defaults;
		}

		require_once(DIR . '/includes/functions_album.php');

		$state = array('public');
		if (can_view_private_albums($this->profile->userinfo['userid']))
		{
			$state[] = 'private';
		}
		if (can_view_profile_albums($this->profile->userinfo['userid']))
		{
			$state[] = 'profile';
		}

		if (!$this->profile->prepared['myprofile'])
		{
			if (!can_moderate(0, 'canmoderatepictures'))
			{
				$sql = "AND album.visible > 0";
			}
			else
			{
				$sql = "AND (album.visible > 0 OR album.moderation > 0)";
			}
		}
		else
		{
			$sql = "";
		}

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('member_profileblock_album_query')) ? eval($hook) : false;

		$albums = $this->registry->db->query_read_slave("
			SELECT album.*,
				a.attachmentid, album.moderation,
				fd.thumbnail_dateline, fd.thumbnail_width, fd.thumbnail_height, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail
				$hook_query_fields
			FROM " . TABLE_PREFIX . "album AS album
			LEFT JOIN " . TABLE_PREFIX . "attachment AS a ON (album.coverattachmentid = a.attachmentid)
			LEFT JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
			$hook_query_joins
			WHERE
				album.userid = ". $this->profile->userinfo['userid'] . "
					AND
				album.state IN ('" . implode("', '", $state) . "')
				$sql
				$hook_query_where
			ORDER BY album.lastpicturedate DESC
			LIMIT $options[total]
		");
		$albumbits = '';
		while ($album = $this->registry->db->fetch_array($albums))
		{
			$album['picturedate'] = vbdate($this->registry->options['dateformat'], $album['lastpicturedate'], true);
			$album['picturetime'] = vbdate($this->registry->options['timeformat'], $album['lastpicturedate']);
			$album['title_html'] = fetch_word_wrapped_string(fetch_censored_text($album['title']));
			$album['coverdimensions'] = ($album['thumbnail_width'] ? "width=\"$album[thumbnail_width]\" height=\"$album[thumbnail_height]\"" : '');

			if ($album['moderation'])
			{
				$album['moderatedcount'] = vb_number_format($album['moderation']);
				$show['moderated'] = true;
			}
			else
			{
				$show['moderated'] = false;
			}

			$templater = vB_Template::create('memberinfo_albumbit');
				$templater->register('album', $album);
			$albumbits .= $templater->render();
		}

		$this->block_data['albumbits'] = $albumbits;
	}
}

/**
* Profile Block for Visitor Tracking
*
* @package vBulletin
*/
class vB_ProfileBlock_RecentVisitors extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_recentvisitors';

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled($id)
	{
		return ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_visitor_tracking'] ? true : false);
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		// don't include yourself in the visit count
		if ($this->profile->userinfo['userid'] != $this->registry->userinfo['userid'])
		{
			// profile visits
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "user SET
					profilevisits = profilevisits + 1
				WHERE userid = " . $this->profile->userinfo['userid']
			);
			$this->profile->userinfo['profilevisits']++; // reflect this view
		}
		$this->profile->prepare('profilevisits');

		// We shouldn't show as a profile visitor
		if ($this->registry->userinfo['userid'] AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid'])
		{
			$this->registry->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "profilevisitor
					(userid, visitorid, dateline, visible)
				VALUES
					(
						" . $this->profile->userinfo['userid'] . ",
						". $this->registry->userinfo['userid'] . ",
						" . TIMENOW . ",
						" . ($this->registry->userinfo['options'] & $this->registry->bf_misc_useroptions['invisible'] ? 0 : 1) . "
					)
			");
		}

		// ensure this setting makes sense
		if ($options['profilemaxvisitors'] < 2)
		{
			$options['profilemaxvisitors'] = 2;
		}

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('member_profileblock_recentvisitors_query')) ? eval($hook) : false;

		$visitors_db = $this->registry->db->query_read_slave("
			SELECT user.userid, user.username, user.usergroupid, user.displaygroupid, profilevisitor.visible, user.infractiongroupid
				$hook_query_fields
			FROM " . TABLE_PREFIX . "profilevisitor AS profilevisitor
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = profilevisitor.visitorid)
			$hook_query_joins
			WHERE profilevisitor.userid = " . $this->profile->userinfo['userid'] . "
				" . (!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) ? " AND (visible = 1 OR profilevisitor.visitorid = " . $this->registry->userinfo['userid'] . ")" : "") . "
				$hook_query_where
			ORDER BY profilevisitor.dateline DESC
			LIMIT $options[profilemaxvisitors]
		");

		$visitors = array();
		while ($user = $this->registry->db->fetch_array($visitors_db))
		{
			$visitors["$user[username]"] = $user;
		}

		uksort($visitors, 'strnatcasecmp');

		if (!is_array($buddylist))
		{
			if ($this->registry->userinfo['buddylist'] = trim($this->registry->userinfo['buddylist']))
			{
				$buddylist = preg_split('/\s+/', $this->registry->userinfo['buddylist'], -1, PREG_SPLIT_NO_EMPTY);
			}
			else
			{
				$buddylist = array();
			}
		}

		$visitorbits = '';
		foreach ($visitors AS $user)
		{
			fetch_musername($user);
			$user['invisiblemark'] = !$user['visible'] ? '*' : '';
			$user['buddymark'] = in_array($user['userid'], $buddylist) ? '+' : '';
			$templater = vB_Template::create('memberinfo_visitorbit');
				$templater->register('user', $user);
			$visitorbits .= $templater->render();
		}

		$this->block_data['visitorbits'] = $visitorbits;
		$this->block_data['visitorcount'] = vb_number_format($this->registry->db->num_rows($visitors_db));
	}
}

/**
* Profile Block for Groups
*
* @package vBulletin
*/
class vB_ProfileBlock_Groups extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_groups';

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return ($this->block_data['membergroupcount'] OR $this->block_data['socialgroupcount'] OR ($this->profile->prepared['myprofile'] AND $this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups']));
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		$this->block_data = array();

		$membergroups = fetch_membergroupids_array($this->profile->userinfo);

		$this->block_data['membergroupcount'] = 0;

		$membergroupbits = '';
		foreach ($membergroups AS $usergroupid)
		{
			$usergroup = $this->registry->usergroupcache["$usergroupid"];
			if ($usergroup['ispublicgroup'])
			{
				$templater = vB_Template::create('memberinfo_publicgroupbit');
					$templater->register('usergroup', $usergroup);
				$membergroupbits .= $templater->render();
				$this->block_data['membergroupcount']++;
			}
		}

		$this->block_data['membergroupbits'] = $membergroupbits;

		if ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups'])
		{
			$socialgroups = $this->registry->db->query_read_slave("
				SELECT socialgroup.groupid, socialgroup.name, socialgroup.description, socialgroup.dateline, sgicon.dateline AS icondateline,
					sgicon.thumbnail_width AS iconthumb_width, sgicon.thumbnail_height AS iconthumb_height
				FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
				INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON
					(socialgroup.groupid = socialgroupmember.groupid)
				LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS sgicon ON sgicon.groupid = socialgroup.groupid
				WHERE
					socialgroupmember.userid = " . $this->profile->userinfo['userid'] . "
					AND socialgroupmember.type = 'member'
				ORDER BY socialgroup.name
			");

			$showgrouplink = ($this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['canviewgroups'] ? true : false);

			require_once(DIR . '/includes/functions_socialgroup.php');

			$socialgroupbits = '';
			$useicons = ($this->registry->db->num_rows($socialgroups) <= 12);

			while ($socialgroup = $this->registry->db->fetch_array($socialgroups))
			{
				$socialgroup = prepare_socialgroup($socialgroup);

				if (!$useicons)
				{
					$socialgroup['name_html'] = fetch_word_wrapped_string(fetch_censored_text($socialgroup['name']));
				}

				if ($useicons)
				{
					$templater = vB_Template::create('memberinfo_socialgroupbit');
				}
				else
				{
					$templater = vB_Template::create('memberinfo_socialgroupbit_text');
				}

				$templater->register('showgrouplink', $showgrouplink);
				$templater->register('socialgroup', $socialgroup);
				$socialgroupbits .= $templater->render();
			}

			$this->block_data['socialgroupbits'] = $socialgroupbits;
			$this->block_data['socialgroupcount'] = $this->registry->db->num_rows($socialgroups);
		}
		else
		{

			$this->block_data['socialgroupbits'] = '';
			$this->block_data['socialgroupcount'] = 0;
		}

		$this->block_data['show_join_link'] = (
			(
				!empty($this->block_data['socialgroupbits'])
				OR $this->profile->prepared['myprofile']
			)
			AND $this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['canjoingroups']
			AND $this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups']
		);
	}
}

/**
* Profile Block for Visitor Messaging
*
* @package vBulletin
*/
class vB_ProfileBlock_VisitorMessaging extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_visitormessaging';
	
	var $nowrap = true;
	
	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'pagenumber' => 1,
			'perpage'    => $this->registry->options['vm_perpage'],
			'vmid'       => 0,
		);
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

/**
	* Fetch the block
	*
	* @param	string	The title of the Block
	* @param	string	The id of the Block
	* @param	array	Options specific to the block
	* @param	array	Userinfo of the visiting user
	*
	* @return	string	The Block's output to be shown on the profile page
	*/
	function fetch($title, $id = '', $options = array(), $visitor)
	{
		global $show;

		$output = parent::fetch($title, $id, $options, $visitor);

		if (!$output)
		{
			$show['post_visitor_message'] = false;
		}

		return $output;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		global $show;

		return ($this->profile->prepared['vm_total'] OR $show['quickcomment']);
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase, $messagearea, $vBeditTemplate;

		require_once(DIR . '/includes/functions_visitormessage.php');
		require_once(DIR . '/includes/class_bbcode.php');
		require_once(DIR . '/includes/class_visitormessage.php');

		if (is_array($options))
		{
			$options = array_merge($this->option_defaults, $options);
		}
		else
		{
			$options = $this->option_defaults;
		}

		if ($options['vmid'])
		{
			$messageinfo = verify_visitormessage($options['vmid'], false);
		}

		$state = array('visible');
		if (fetch_visitor_message_perm('canmoderatevisitormessages', $this->profile->userinfo))
		{
			$state[] = 'moderation';
		}

		if (can_moderate(0,'canmoderatevisitormessages') OR ($this->registry->userinfo['userid'] == $this->profile->userinfo['userid'] AND $this->registry->userinfo['permissions']['visitormessagepermissions'] & $this->registry->bf_ugp_visitormessagepermissions['canmanageownprofile']))
		{
			$state[] = 'deleted';
			$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (visitormessage.vmid = deletionlog.primaryid AND deletionlog.type = 'visitormessage')";
		}
		else
		{
			$deljoinsql = '';
		}

		$state_or = array(
			"visitormessage.state IN ('" . implode("','", $state) . "')"
		);
		// Get the viewing user's moderated posts
		if ($this->registry->userinfo['userid'] AND !fetch_visitor_message_perm('canmoderatevisitormessages', $this->profile->userinfo))
		{
			$state_or[] = "(visitormessage.postuserid = " . $this->registry->userinfo['userid'] . " AND state = 'moderation')";
		}

		$perpage = (!$options['perpage'] OR $options['perpage'] > $this->registry->options['vm_maxperpage']) ? $this->registry->options['vm_perpage'] : $options['perpage'];

		if ($messageinfo['vmid'])
		{
			$getpagenum = $this->registry->db->query_first("
				SELECT COUNT(*) AS comments
				FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
				WHERE userid = " . $this->profile->userinfo['userid'] . "
					AND (" . implode(" OR ", $state_or) . ")
					AND dateline >= $messageinfo[dateline]
			");
			$options['pagenumber'] = ceil($getpagenum['comments'] / $perpage);
		}

		$pagenumber = $options['pagenumber'];

		do
		{
			if (!$pagenumber OR ($options['tab'] != $id AND $options['tab'] != ''))
			{
				$pagenumber = 1;
			}
			$start = ($pagenumber - 1) * $perpage;

			$hook_query_fields = $hook_query_joins = $hook_query_where = '';
			($hook = vBulletinHook::fetch_hook('member_profileblock_visitormessage_query')) ? eval($hook) : false;

			if ($this->registry->options['globalignore'] != '')
			{
				if (!can_moderate(0, 'candeletevisitormessages') AND !can_moderate(0, 'canremovevisitormessages'))
				{
					require_once(DIR . '/includes/functions_bigthree.php');

					$coventry = fetch_coventry('string');
				}
			}

			$messagebits = '';
			$messages = $this->registry->db->query_read("
				SELECT " . (!isset($this->profile->prepared['vm_total']) ? "SQL_CALC_FOUND_ROWS" : "") . "
					visitormessage.*, user.*, visitormessage.ipaddress AS messageipaddress
					" . ($this->registry->userinfo['userid'] ? ",IF(userlist.userid IS NOT NULL, 1, 0) AS bbuser_iscontact_of_user" : "") . "
					" . ($deljoinsql ? ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
					" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, filedata_thumb, NOT ISNULL(customavatar.userid) AS hascustom" : "") . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (visitormessage.postuserid = user.userid)
				" . ($this->registry->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON (userlist.userid = user.userid AND userlist.type = 'buddy' AND userlist.relationid = " . $this->registry->userinfo['userid'] . ")" : "") . "
				" . ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
				$deljoinsql
				$hook_query_joins
				WHERE visitormessage.userid = " . $this->profile->userinfo['userid'] . "
					AND (" . implode(" OR ", $state_or) . ")
				" . ($coventry ? "AND visitormessage.postuserid NOT IN (" . $coventry . ")" : '') . "
					$hook_query_where
				ORDER BY visitormessage.dateline DESC
				LIMIT $start, $perpage
			");

			if (!isset($this->profile->prepared['vm_total']))
			{
				list($messagetotal) = $this->registry->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);
			}
			else
			{
				$messagetotal = $this->profile->prepared['vm_total'];
			}

			if ($start >= $messagetotal)
			{
				$pagenumber = ceil($messagetotal / $perpage);
			}
		}
		while ($start >= $messagetotal AND $messagetotal);

		$this->block_data['messagestart'] = $start + 1;
		$this->block_data['messageend'] = min($start + $perpage, $messagetotal);

		$bbcode = new vB_BbCodeParser($this->registry, fetch_tag_list());
		$factory = new vB_Visitor_MessageFactory($this->registry, $bbcode, $this->profile->userinfo);

		$messagebits = '';

		if ($this->registry->userinfo['userid'] AND empty($options['showignored']))
		{
			$ignorelist = preg_split('/( )+/', trim($this->registry->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$ignorelist = array();
		}

		$firstrecord = array();
		$read_ids = array();
		while ($message = $this->registry->db->fetch_array($messages))
		{
			// Process user.options
			$message = array_merge($message, convert_bits_to_array($message['options'], $this->registry->bf_misc_useroptions));

			if (!$firstrecord)
			{
				$firstrecord = $message;
			}

			if ($ignorelist AND in_array($message['postuserid'], $ignorelist))
			{
				$message['ignored'] = true;
			}

			if (empty($options['showignored']) AND in_coventry($message['postuserid']))
			{
				$message['ignored'] = true;
			}

			$response_handler =& $factory->create($message);
			$response_handler->converse = true;
			if (
				(
					!$message['vm_enable']
						AND
					(
						!can_moderate(0,'canmoderatevisitormessages')
							OR
						$this->registry->userinfo['userid'] == $message['postuserid']
					)
				)
				OR
				(
					$message['vm_contactonly']
						AND
					!can_moderate(0,'canmoderatevisitormessages')
						AND
					$message['postuserid'] != $this->registry->userinfo['userid']
						AND
					!$message['bbuser_iscontact_of_user']
				)
			)
			{
				$response_handler->converse = false;
			}
			$response_handler->cachable = false;
			$messagebits .= $response_handler->construct();

			if (!$message['messageread'] AND $message['state'] == 'visible')
			{
				$read_ids[] = $message['vmid'];
			}

			$lastcomment = !$lastcomment ? $message['dateline'] : $lastcomment;
		}

		$readvms = 0;

		// If it's our profile and the visible or default tab then we hope they've read it
		// if ($this->profile->userinfo['userid'] == $this->registry->userinfo['userid'] AND ($options['tab'] == $id OR $options['tab'] == '') AND !empty($read_ids))
		if ($this->profile->userinfo['userid'] == $this->registry->userinfo['userid'])
		{
			if (!empty($read_ids))
			{
				$readvms = sizeof($read_ids);
				$this->registry->db->query_write("UPDATE " . TABLE_PREFIX . "visitormessage SET messageread = 1 WHERE vmid IN (" . implode(',', $read_ids) . ")");
			}

			if (($this->profile->userinfo['vmunreadcount'] - $readvms) > 0 AND $this->registry->options['globalignore'] != '')
			{ // We still have unread VMs somewhere, and Tachy is enabled
				build_visitor_message_counters($this->profile->userinfo['userid']);
			}
			else if ($readvms)
			{ // This is more than likely on the second page
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "user
					SET vmunreadcount = IF(vmunreadcount >= $readvms, vmunreadcount - $readvms, 0)
					WHERE userid = " . $this->registry->userinfo['userid']
				);
			}
		}

		if ($pagenumber == 1 AND (!isset($this->profile->prepared['vm_total']) OR !isset($this->profile->prepared['lastvm_time']) OR !isset($this->profile->prepared['lastvm_date'])))
		{
			$pminfo = array(
				'dateline' => $firstrecord['dateline'],
				'messages' => $messagetotal,
			);
		}
		else
		{
			$pminfo = null;
		}
		$this->profile->prepare('vm_total', $pminfo);

		$this->block_data['messagebits'] = $messagebits;
		$this->block_data['lastcomment'] = $lastcomment;

		$show['delete'] = fetch_visitor_message_perm('candeletevisitormessages', $this->profile->userinfo);
		$show['undelete'] = fetch_visitor_message_perm('canundeletevisitormessages', $this->profile->userinfo);
		$show['approve'] = fetch_visitor_message_perm('canmoderatevisitormessages', $this->profile->userinfo);
		$show['inlinemod'] = ($show['delete'] OR $show['undelete'] OR $show['approve']);

		// Only allow AJAX QC on the first page
		$show['quickcomment'] = $show['post_visitor_message'];
		$show['allow_ajax_qc'] = ($pagenumber == 1 AND $messagetotal) ? 1 : 0;

		$pageinfo = array(
			'tab' => $id
		);
		if ($options['perpage'] != $this->registry->options['vm_perpage'])
		{
			$pageindo['pp'] = $options['perpage'];
		}

		if (!empty($options['showignored']))
		{
			$pageinfo['showignored'] = 1;
		}

		$this->block_data['pagenav'] = construct_page_nav(
			$pagenumber,
			$perpage,
			$messagetotal,
			'',
			'',
			$id,
			'member',
			$this->profile->userinfo,
			$pageinfo
		);
		$this->block_data['messagetotal'] = $messagetotal;

		$show['view_conversation'] = (!$this->profile->prepared['myprofile'] AND THIS_SCRIPT != 'converse' AND $this->registry->userinfo['vm_enable']);

		if ($show['quickcomment'])
		{
			require_once(DIR . '/includes/functions_editor.php');

			$this->block_data['editorid'] = construct_edit_toolbar(
				'',
				false,
				'visitormessage',
				$this->registry->options['allowsmilies'],
				true,
				false,
				'qr_small',
				'',
				array(),
				'content',
				'vBForum_VisitorMessage',
				0,
				$this->profile->userinfo['userid']
			);
			$this->block_data['messagearea'] = $messagearea;
			$this->block_data['clientscript'] = $vBeditTemplate['clientscript'];
		}
	}

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled($id)
	{
		return (
			(
				$this->profile->userinfo['vm_enable']
					OR
				(
					can_moderate(0,'canmoderatevisitormessages')
						AND
					$this->registry->userinfo['userid'] != $this->profile->userinfo['userid']
				)
			)
				AND
			$this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_visitor_messaging']
				AND
			(
				!$this->profile->userinfo['vm_contactonly']
					OR
				can_moderate(0,'canmoderatevisitormessages')
					OR
				$this->profile->userinfo['userid'] == $this->registry->userinfo['userid']
					OR
				$this->profile->userinfo['bbuser_iscontact_of_user']
			)
		)
		? true : false;
	}
}

/**
* Profile Block for Infractions
*
* @package vBulletin
*/
class vB_ProfileBlock_Infractions extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_infractions';

	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'pagenumber'	=> 1,
		);
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled()
	{
		if (
			!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canreverseinfraction'])
		AND
			!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction'])
		AND
			$this->profile->userinfo['userid'] != $this->registry->userinfo['userid']
		)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		global $show;
		return ($this->block_data['infractionbits'] OR $show['giveinfraction']);
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		$show['infractions'] = false;

		($hook = vBulletinHook::fetch_hook('member_infraction_start')) ? eval($hook) : false;

		$perpage = $options['perpage'];
		$pagenumber = $options['pagenumber'];

		$totalinfractions = $this->registry->db->query_first_slave("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "infraction AS infraction
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (infraction.postid = post.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			WHERE infraction.userid = " . $this->profile->userinfo['userid'] . "
		");

		if ($totalinfractions['count'])
		{
			if (!$pagenumber OR ($options['tab'] != $id))
			{
				$pagenumber = 1;
			}

			// set defaults
			sanitize_pageresults($totalinfractions['count'], $pagenumber, $perpage, 100, 5);
			$limitlower = ($pagenumber - 1) * $perpage + 1;
			$limitupper = $pagenumber * $perpage;
			if ($limitupper > $totalinfractions['count'])
			{
				$limitupper = $totalinfractions['count'];
				if ($limitlower > $totalinfractions['count'])
				{
					$limitlower = $totalinfractions['count'] - $perpage;
				}
			}
			if ($limitlower <= 0)
			{
				$limitlower = 1;
			}

			if ($this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canreverseinfraction'])
			{
				$show['reverse'] = true;
			}

			require_once(DIR . '/includes/class_bbcode.php');
			$bbcode_parser = new vB_BbCodeParser($this->registry, fetch_tag_list());

			$infractions = $this->registry->db->query_read_slave("
				SELECT infraction.*, thread.title, thread.threadid, user.username, thread.visible AS thread_visible, post.visible,
					forumid, postuserid, IF(ISNULL(post.postid) AND infraction.postid != 0, 1, 0) AS postdeleted
				FROM " . TABLE_PREFIX . "infraction AS infraction
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (infraction.postid = post.postid)
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (infraction.whoadded = user.userid)
				WHERE infraction.userid = " . $this->profile->userinfo['userid'] . "
				ORDER BY infraction.dateline DESC
				LIMIT " . ($limitlower - 1) . ", $perpage
			");
			while ($infraction = $this->registry->db->fetch_array($infractions))
			{
				$show['expired'] = $show['reversed'] = $show['neverexpires'] = false;
				$card = ($infraction['points'] > 0) ? 'redcard' : 'yellowcard';
				$infraction['timeline'] = vbdate($this->registry->options['timeformat'], $infraction['dateline']);
				$infraction['dateline'] = vbdate($this->registry->options['dateformat'], $infraction['dateline']);
				switch($infraction['action'])
				{
					case 0:
						if ($infraction['expires'] != 0)
						{
							$infraction['expires_timeline'] = vbdate($this->registry->options['timeformat'], $infraction['expires']);
							$infraction['expires_dateline'] = vbdate($this->registry->options['dateformat'], $infraction['expires']);
							$show['neverexpires'] = false;
						}
						else
						{
							$show['neverexpires'] = true;
						}
						break;
					case 1:
						$show['expired'] = true;
						break;
					case 2:
						$show['reversed'] = true;
						break;
				}

				$infraction['threadtitle'] = vbstrlen($infraction['title']) > 25 ? fetch_trimmed_title($infraction['title'], 24) : $infraction['title'];
				$infraction['reason'] = !empty($vbphrase['infractionlevel' . $infraction['infractionlevelid'] . '_title']) ? $vbphrase['infractionlevel' . $infraction['infractionlevelid'] . '_title'] : ($infraction['customreason'] ? $infraction['customreason'] : $vbphrase['n_a']);

				$show['threadtitle'] = true;
				$show['postdeleted'] = false;
				if ($infraction['postid'] != 0)
				{
					if ($infraction['postdeleted'])
					{
						$show['postdeleted'] = true;
					}
					else if ((!$infraction['visible'] OR !$infraction['thread_visible']) AND !can_moderate($infraction['forumid'], 'canmoderateposts'))
					{
						$show['threadtitle'] = false;
					}
					else if (($infraction['visible'] == 2 OR $infraction['thread_visible'] == 2) AND !can_moderate($infraction['forumid'], 'candeleteposts'))
					{
						$show['threadtitle'] = false;
					}
					else
					{
						$forumperms = fetch_permissions($infraction['forumid']);
						if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canview']))
						{
							$show['threadtitle'] = false;
						}
						if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers']) AND ($infraction['postuserid'] != $this->registry->userinfo['userid'] OR $this->registry->userinfo['userid'] == 0))
						{
							$show['threadtitle'] = false;
						}
					}
				}

				($hook = vBulletinHook::fetch_hook('member_infractionbit')) ? eval($hook) : false;

				$threadinfo = array(
					'threadid' => $infraction['threadid'],
					'title'    => $infraction['title'],
				);
				$pageinfo = array('p' => $infraction['postid']);
				$memberinfo = array('userid' => $infraction['whoadded'], 'username' => $infraction['username']);

				$templater = vB_Template::create('memberinfo_infractionbit');
					$templater->register('card', $card);
					$templater->register('infraction', $infraction);
					$templater->register('memberinfo', $memberinfo);
					$templater->register('pageinfo', $pageinfo);
					$templater->register('threadinfo', $threadinfo);
				$infractionbits .= $templater->render();
			}
			unset($bbcode_parser);

			$pageinfo_pagenav = array(
				'tab' => $id
			);
			if ($options['perpage'])
			{
				$pageinfo_pagenav['pp'] = $options['perpage'];
			}

			$this->block_data['pagenav'] = construct_page_nav(
				$pagenumber,
				$perpage,
				$totalinfractions['count'],
				'',
				'',
				$id,
				'member',
				$this->profile->userinfo,
				$pageinfo_pagenav
			);

			$this->block_data['infractionbits'] = $infractionbits;
		}

		$show['giveinfraction'] = (
				// Must have 'cangiveinfraction' permission. Branch dies right here majority of the time
				$this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction']
				// Can not give yourself an infraction
				AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid']
				// Can not give an infraction to a post that already has one
				// Can not give an admin an infraction
				AND !($this->profile->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				// Only Admins can give a supermod an infraction
				AND (
					!($this->profile->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
					OR $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
				)
			);

		($hook = vBulletinHook::fetch_hook('member_infraction_complete')) ? eval($hook) : false;
	}
}

/**
* Profile Block for Statistics
*
* @package vBulletin
*/
class vB_ProfileBlock_Statistics extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_statistics';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array(
		'posts',
		'postsperday',
		'joindate',
		'referrals',
		'lastpost'
	);

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		$this->block_data = array();

		$show['vm_block'] = (
			$this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_visitor_messaging']
				AND
			(
				!$this->profile->userinfo['vm_contactonly']
					OR
				can_moderate(0,'canmoderatevisitormessages')
					OR
				$this->profile->userinfo['userid'] == $this->registry->userinfo['userid']
					OR
				$this->profile->userinfo['bbuser_iscontact_of_user']
			)
				AND
			(
				$this->profile->userinfo['vm_enable']
					OR
				(
					can_moderate(0,'canmoderatevisitormessages')
						AND
					$this->registry->userinfo['userid'] != $this->profile->userinfo['userid']
				)
			)
		)
		? true : false;

		if ($show['vm_block'])
		{
			$this->profile->prepare('vm_total');
		}

		if (!$this->profile->prepared['vm_total'] AND !$show['quickcomment'])
		{
			$show['vm_block'] = false;
		}

		// check user note info
		if ($this->profile->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canbeusernoted'])
		{
			$show['usernote_block'] = false;
			$show['usernote_post'] = false;

			if
				(
						($this->profile->userinfo['userid'] == $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canviewownusernotes'])
					OR 	($this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canviewothersusernotes'])
				)
			{
				$this->profile->prepare('usernotecount');

				$show['usernote_block'] = ($this->profile->prepared['usernotecount'] > 0);
				$show['usernote_data'] = ($this->profile->prepared['usernotecount'] > 0);

				$this->block_data['note_lastdate'] = $this->profile->prepared['usernoteinfo']['lastpostdate'];
				$this->block_data['note_lasttime'] = $this->profile->prepared['usernoteinfo']['lastposttime'];
			}

			if
				(
						($this->profile->userinfo['userid'] == $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canpostownusernotes'])
					OR 	($this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canpostothersusernotes'])
				)
			{
				$show['usernote_block'] = true;
				$show['usernote_post'] = true;
			}
		}
		else
		{
			$show['usernote_block'] = false;
		}

		// check album info
		if ($this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canviewalbum']
			AND $this->profile->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canalbum'] AND $this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums'])
		{
			$this->profile->prepare('albuminfo');

			$this->block_data['albumcount'] = $this->profile->prepared['albuminfo']['albumcount'];
			$this->block_data['picturecount'] = $this->profile->prepared['albuminfo']['picturecount'];

			$show['album_block'] = ($this->block_data['albumcount'] > 0);
		}
		else
		{
			$show['album_block'] = false;
		}
	}
}

/**
* Profile Block for Contact Information
*
* @package vBulletin
*/
class vB_ProfileBlock_ContactInfo extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_contactinfo';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array(
		'displayemail',
		'homepage',
		'imicons',
		'profileurl'
	);

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		global $show;

		return (
			$show['messagelinks'] OR $this->profile->prepared['hasimdetails']
			OR $this->profile->prepared['displayemail'] OR $this->profile->prepared['homepage']
		);
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase;

		$this->block_data = array();

		$imservices = array(
			'msn'   => array(
				'width'  => 400,
				'height' => 200,
			),
			'aim'   => array(
				'width'  => 400,
				'height' => 200,
			),
			'icq'   => array(
				'width'  => 500,
				'height' => 450,
			),
			'yahoo' => array(
				'width'  => 400,
				'height' => 200,
			),
			'skype' => array(
				'width'  => 400,
				'height' => 285,
			),
		);

		$imbits = '';

		$userinfo = $this->profile->userinfo;
		$prepared = $this->profile->prepared;

		foreach ($imservices AS $imserviceid => $iminfo)
		{
			if ($this->profile->prepared["$imserviceid"])
			{
				$imusername = $this->profile->prepared["$imserviceid"];
				$imicon = $this->profile->prepared["{$imserviceid}icon"];
				$imtitle = $vbphrase["$imserviceid"];

				$templater = vB_Template::create('memberinfo_imbit');
					$templater->register('imicon', $imicon);
					$templater->register('imserviceid', $imserviceid);
					$templater->register('imtitle', $imtitle);
					$templater->register('imusername', $imusername);
					$templater->register('userinfo', $userinfo);
				$imbits .= $templater->render();
			}
		}

		$this->block_data['imbits'] = $imbits;
	}
}

/**
 * Profile Block for Profile Picture
 *
 * @package vBulletin
 */
class vB_ProfileBlock_ProfilePicture extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_profilepicture';

	/**
	* Variables to automatically prepare
	*
	* @var array
	*/
	var $auto_prepare = array(
		'profilepicurl',
		'profilepicsize',
		'username'
	);

	/**
	 * Whether to wrap output in standard block template.
	 *
	 * @var boolean
	 */
	var $nowrap = true;

/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled($id)
	{
		return $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseeprofilepic'];
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return ($this->profile->prepared['profilepicurl']);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42552 $
|| ####################################################################
\*======================================================================*/
?>
