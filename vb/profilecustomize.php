<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * The vB core class.
 * Everything required at the core level should be accessible through this.
 *
 * The core class performs initialisation for error handling, exception handling,
 * application instatiation and optionally debug handling.
 *
 * @TODO: Much of what goes on in global.php and init.php will be handled, or at
 * least called here during the initialisation process.  This will be moved over as
 * global.php is refactored.
 *
 * @package vBulletin
 * @version $Revision: 28823 $
 * @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_ProfileCustomize
{
	/*Properties====================================================================*/

	/*** This provides the interface to the user profile customization options.
	* It handles setting the initial style variables for loading the theme list, responding to ajax
	*
	*
	***/

	/** The database connection **/
	private static $db = false;

	/** The user info ***/
	private static $userinfo = false;

	/** The standard theme information**/
	private static $themes = false;

	/** The  stylevar information**/
	private static $stylevars = false;

	/*this user's permissions */
	private static $permissions = false;

	/*Session Url  */
	private static $session_url;

	/**fields which can be empty**/
	protected static $nullOK = array('themeid', 'title', 'thumbnail');

	/**the list of variables. These need to be consistent in three places: Here,
	* in getDefaultTheme() below, and in /clientscript/vbulletin_userprofile.js **/
	private static $themevars = array('font_family',
		'fontsize',
		'title_text_color',
		'page_background_color',
		'page_background_image',
		'page_background_repeat',
		'module_text_color',
		'module_link_color',
		'module_background_color',
		'module_background_image',
		'module_background_repeat',
		'module_border',
		'moduleinactive_text_color',
		'moduleinactive_link_color',
		'moduleinactive_background_color',
		'moduleinactive_background_image',
		'moduleinactive_background_repeat',
		'moduleinactive_border' ,
		'headers_text_color',
		'headers_link_color',
		'headers_background_color',
		'headers_background_image',
		'headers_background_repeat',
		'headers_border',
		'content_text_color',
		'content_link_color',
		'content_background_color',
		'content_background_image',
		'content_background_repeat',
		'content_border',
		'button_text_color',
		'button_background_color',
		'button_background_image' ,
		'button_background_repeat',
		'button_border',
		'page_link_color');

	/*Initialisation================================================================*/

	/** prevent instantiation **/
	private function __construct()
	{
	}

	/***	This loads all the non-user-specific themes
	 *
	 ***/
	public static function getThemes()
	{
		if (!self::$themes)
		{
			$theme_data = vB_dB_Assertor::getInstance()->assertQuery('customprofile',
				array('type' => 's', 'userid' => '0'), 'title');
			self::$themes = array();
			while($theme_data->valid())
			{
				$theme = $theme_data->current();
				self::$themes[$theme['customprofileid']] = $theme;
				$theme_data->next();
			}
		}
		return self::$themes;
	}

	/*** This gets a specific theme. It falls back to the default them so there will always be something usable
	 *
	 *	@param int	theme id
	 *
	 * @format	char 	format in which we want the result
	 ***/
	public static function getTheme($themeid, $format = 'p')
	{
		self::getThemes();
		if ($themeid == 0)
		{
			$theme = self::getUserTheme(vB::$vbulletin->userinfo['userid']);
		}
		else if (array_key_exists($themeid, self::$themes))
		{
			$theme = self::$themes[$themeid];
			$theme['themeid'] = $themeid;
		}
		else
		{
			$theme = self::getDefaultTheme();
		}

		switch($format){
		 	case 'j': //json requested
		 		$values = array();

		 		foreach ($theme as $varname => $value)
		 		{
		 			if (!in_array($varname, self::$nullOK) AND !(strpos($varname, 'image')))
		 			{
		 				//make sure there's some rational value.
		 				if (empty($value))
		 				{
		 					if ($varname == 'font_family')
		 					{
		 						$value = "default";
		 					}
		 					else if ($varname == 'fontsize')
		 					{
		 						$value = "small";
		 					}
		 					else if (strpos($varname, 'background'))
		 					{
		 						$value = "#bbbbbb";
		 					}
		 					else
		 					{
		 						$value = "#222222";
		 					}
		 				}
		 			}
		 			$values[] = "\"$varname\":\"" . str_replace('"', '\"',  str_replace("\\", "\\\\", $value)) . '"';
		 		}
		 		return "{\n" . implode($values, ",\n" ) . "}\n";
		 		break;
		 	default:
		 		return self::$themes[$themeid];
		 }

	}

	/** We have some invalid "repeat" settings sneaking in. This function ensures they are valid.
	*
	* 	@param	 string
	*
	* 	@return	string
	***/
	public static function cleanRepeat($repeat_in)
	{
		return (($repeat_in == 'inherit') OR ($repeat_in == 'repeat-x')
			OR ($repeat_in == 'repeat-y') OR ($repeat_in == 'no-repeat')) ?
		$repeat_in : 'repeat';
	}

	public static function getDefaultTheme()
	{
		return self::getUserTheme(-1);
	}

	/*** This builds the default theme from the stylevars. This gives a look that matches the site's colors
	 *
	 ***/
	public static function getSiteDefaultTheme()
	{
		//try pulling a site customization
		$theme_data = vB_dB_Assertor::getInstance()->assertQuery('customprofile',
				array('type' => 's', 'userid' => -1) );
		if ($theme_data->valid())
		{
			$theme = $theme_data->current();
			if ($theme_data->valid())
			{
				return $theme;
			}
		}

		//We set from stylevars.
		if (!self::$stylevars)
		{
			self::$stylevars = vB::$vbulletin->stylevars;
		}

		$theme = array('font_family' => 'default',
		'fontsize' => self::$stylevars['font']['size'] . self::$stylevars['font']['units'],
		'title_text_color' => self::$stylevars['body_color']['color'],
		'page_background_color' => self::$stylevars['body_background']['color'],
		'page_background_image' => self::$stylevars['body_background']['image'],
		'page_background_repeat' => self::cleanRepeat(self::$stylevars['body_background']['repeat']),
		'module_text_color' => self::$stylevars['blockhead_color']['color'],
		'module_link_color' => self::$stylevars['blockhead_link_color']['color'],
		'module_background_color' => self::$stylevars['blockhead_background']['color'],
		'module_background_image' => self::$stylevars['blockhead_background']['image'],
		'module_background_repeat' => self::cleanRepeat(self::$stylevars['blockhead_background']['repeat']),
		'module_border' => self::$stylevars['blockhead_border']['color'],
		'moduleinactive_text_color' => self::$stylevars['sidebar_header_color']['color'],
		'moduleinactive_link_color' => self::$stylevars['sidebar_header_link_color']['color'],
		'moduleinactive_background_color' => self::$stylevars['sidebar_background']['color'],
		'moduleinactive_background_image' => self::$stylevars['sidebar_background']['image'],
		'moduleinactive_background_repeat' => self::cleanRepeat(self::$stylevars['sidebar_background']['repeat']),
		'moduleinactive_border' => self::$stylevars['sidebar_border']['color'],
		'headers_text_color' => self::$stylevars['postbitlite_header_color']['color'],
		'headers_link_color' => self::$stylevars['postbitlite_header_link_color']['color'],
		'headers_background_color' => self::$stylevars['postbitlite_header_background']['color'],
		'headers_background_image' => self::$stylevars['postbitlite_header_background']['image'],
		'headers_background_repeat' => self::cleanRepeat(self::$stylevars['postbitlite_header_background']['repeat']),
		'headers_border' => self::$stylevars['postbitlite_header_border']['color'],
		'content_text_color' => self::$stylevars['postbit_color']['color'],
		'content_link_color' => self::$stylevars['link_color']['color'],
		'content_background_color' => self::$stylevars['postbit_background']['color'],
		'content_background_image' => self::$stylevars['postbit_background']['image'],
		'content_background_repeat' => self::cleanRepeat(self::$stylevars['postbit_background']['repeat']),
		'content_border' => self::$stylevars['postbit_background']['color'],
		'button_text_color' => self::$stylevars['control_color']['color'],
		'button_background_color' => self::$stylevars['control_background']['color'],
		'button_background_image' => self::$stylevars['control_background']['image'],
		'button_background_repeat' => self::cleanRepeat(self::$stylevars['control_background']['repeat']),
		'button_border' => self::$stylevars['control_border']['color'],
		'page_link_color' => self::$stylevars['link_color']['color']);

		foreach ($theme as $varname => $value)
		{
			//most fields should not be empty
			if (!in_array($varname, self::$nullOK) AND !(strpos($varname, 'image')))
			{
				//make sure there's some rational value.
				if (empty($value))
				{
					if ($varname == 'font_family')
					{
						$value = "default";
					}
					else if ($varname == 'fontsize')
					{
						$value = "small";
					}
					else if (strpos($varname, 'background'))
					{
						$value = "#bbbbbb";
					}
					else
					{
						$value = "#222222";
					}
				}
				$theme[$varname] = $value;
			}
		}
		return $theme;
	}

	/** This sets the style, which matters if the user hasn't customized their profile
	* or if some variables are not set.
	*
	* @param	integer
	**/
	public static function setStylevars($stylevars)
	{
		self::$stylevars = $stylevars;
	}



	/*** This loads the permission variables
	 *
	 *	@param	mixed	permissions array, optional
	 *
	 ***/

	public static function setPermissions($permissions = false)
	{
		if (!$permissions)
		{
			$permissions = vB::$vbulletin->userinfo['permissions']['usercsspermissions'];
		}
		//Initially we are commenting out "theme" permissions.
		if (!isset($vbulletin->bf_ugp_usercsspermissions['canusetheme']))
		{
				self::$permissions['canusetheme'] = false;
		}
		else
		{
			self::$permissions['canusetheme'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['canusetheme'];
		}


		self::$permissions['cancustomize'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['cancustomize'];
		self::$permissions['caneditfontfamily'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['caneditfontfamily'];
		self::$permissions['caneditfontsize'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['caneditfontsize'];
		self::$permissions['caneditbgimage'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['caneditbgimage'];
		self::$permissions['caneditcolors'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['caneditcolors'];
		self::$permissions['caneditborders'] = $permissions & vB::$vbulletin->bf_ugp_usercsspermissions['caneditborders'];
	}


	/*** This funtion returns a merge of the default css settings merged with the user's theme
	*
	*	@param int	the user id
	*
	*	@return mixed	array of css values
	*
	***/
	public static function getUserTheme($userid)
	{
		//First, if either the user is not logged in or doesn't have permission
		// to customize his/her theme they don't get a custom theme.

		if (!self::$permissions)
		{
			self::setPermissions();
		}

		if (!(vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_profile_styling']) OR
			!isset($userid) OR (!self::$permissions['canusetheme'] AND !self::$permissions['cancustomize']))
		{
			return self::getSiteDefaultTheme();
		}

		if (self::$permissions['canusetheme'])
		{
			//If this user has set a theme, return that
			$result = vB_dB_Assertor::getInstance()->assertQuery('get_user_theme',
				array('userid' => $userid));
			if ($result->valid())
			{
				return $result->current();
			}

		}

		//If this user only has theme permissions and we got here, return the default
		if (!self::$permissions['cancustomize'])
		{
			return self::getDefaultTheme();
		}

		//If we got here, they have customize permission and haven't set a default
		// theme. Let's see if they have actually customized.

		$theme_data = vB_dB_Assertor::getInstance()->assertQuery('customprofile',
			array('type' => 's', 'userid' => $userid));
		if (!$theme_data->valid())
		{
			$theme_data = vB_dB_Assertor::getInstance()->assertQuery('customprofile',
				array('type' => 's', 'userid' => -1));
			if (!$theme_data->valid())
			{
				return self::getSiteDefaultTheme();
			}
		}

		$current = $theme_data->current();

		foreach($current as $field => $value)
		{
			//no checking for font and repeat
			if ((stripos($field, 'font') !== false) OR (stripos($field, 'repeat' ) !== false))
			{
				continue;
			}

			//make sure it's a clean value
			if (stripos($field, 'background_image') > -1)
			{
				//this might be a color, or might be an image.
				$current[$field] =  self::getBGValue($value);
			}
			else //it's a
			{
				$current[$field] =  self::getValidColor($value);
			}
		}
		//Now we start with the default settings, and override the ones this
		//user can override.


		$css = ($userid == -1) ? self::getSiteDefaultTheme() : self::getUserTheme(-1);

		if (self::$permissions['caneditfontfamily'] AND !empty($current['font_family']))
		{
			$css['font_family'] = $current['font_family'];
		}

		if (self::$permissions['caneditfontsize'] AND !empty($current['fontsize']))
		{
			$css['fontsize'] = $current['fontsize'];
		}

		if (self::$permissions['caneditcolors'])
		{

			if (!empty($current['title_text_color']))
			{
				$css['title_text_color'] = $current['title_text_color'];
			}

			if (!empty($current['module_text_color']))
			{
				$css['module_text_color'] = $current['module_text_color'];
			}

			if (!empty($current['module_link_color']))
			{
				$css['module_link_color'] = $current['module_link_color'];
			}

			if (!empty($current['moduleinactive_text_color']))
			{
				$css['moduleinactive_text_color'] = $current['moduleinactive_text_color'];
			}

			if (!empty($current['moduleinactive_link_color']))
			{
				$css['moduleinactive_link_color'] = $current['moduleinactive_link_color'];
			}

			if (!empty($current['headers_text_color']))
			{
				$css['headers_text_color'] = $current['headers_text_color'];
			}

			if (!empty($current['headers_link_color']))
			{
				$css['headers_link_color'] = $current['headers_link_color'];
			}

			if (!empty($current['content_text_color']))
			{
				$css['content_text_color'] = $current['content_text_color'];
			}

			if (!empty($current['content_link_color']))
			{
				$css['content_link_color'] = $current['content_link_color'];
			}

			if (!empty($current['button_text_color']))
			{
				$css['button_text_color'] = $current['button_text_color'];
			}

			if (!empty($current['page_link_color']))
			{
				$css['page_link_color'] = $current['page_link_color'];
			}
		}

		if (self::$permissions['caneditbgimage'])
		{
			if (!empty($current['page_background_color']))
			{
				$css['page_background_color'] = $current['page_background_color'];
			}

			if (!empty($current['page_background_image']))
			{
				$css['page_background_image'] = self::getBGValue($current['page_background_image']);
			}

			if (!empty($current['page_background_repeat']))
			{
				$css['page_background_repeat'] = $current['page_background_repeat'];
			}

			if (!empty($current['module_background_color']))
			{
				$css['module_background_color'] = $current['module_background_color'];
			}

			if (!empty($current['module_background_image']))
			{
				$css['module_background_image'] = self::getBGValue($current['module_background_image']);
			}

			if (!empty($current['module_background_repeat']))
			{
				$css['module_background_repeat'] = $current['module_background_repeat'];
			}

			if (!empty($current['moduleinactive_background_color']))
			{
				$css['moduleinactive_background_color'] = $current['moduleinactive_background_color'];
			}

			if (!empty($current['moduleinactive_background_image']))
			{
				$css['moduleinactive_background_image'] = self::getBGValue($current['moduleinactive_background_image']);
			}

			if (!empty($current['moduleinactive_background_repeat']))
			{
				$css['moduleinactive_background_repeat'] = $current['moduleinactive_background_repeat'];
			}

			if (!empty($current['content_background_color']))
			{
				$css['content_background_color'] = $current['content_background_color'];
			}

			if (!empty($current['content_background_image']))
			{
				$css['content_background_image'] = self::getBGValue($current['content_background_image']);
			}

			if (!empty($current['content_background_repeat']))
			{
				$css['content_background_repeat'] = $current['content_background_repeat'];
			}

			if (!empty($current['button_background_color']))
			{
				$css['button_background_color'] = $current['button_background_color'];
			}

			if (!empty($current['button_background_image']))
			{
				$css['button_background_image'] = self::getBGValue($current['button_background_image']);
			}

			if (!empty($current['button_background_repeat']))
			{
				$css['button_background_repeat'] = $current['button_background_repeat'];
			}

			if (!empty($current['headers_background_image']))
			{
				$css['headers_background_image'] = self::getBGValue($current['headers_background_image']);
			}

			if (!empty($current['headers_background_repeat']))
			{
				$css['headers_background_repeat'] = $current['headers_background_repeat'];
			}

			if (!empty($current['headers_background_color']))
			{
				$css['headers_background_color'] = $current['headers_background_color'];
			}
		}

		if (self::$permissions['caneditborders'])
		{

			if (!empty($current['module_border']))
			{
				$css['module_border'] = $current['module_border'];
			}

			if (!empty($current['moduleinactive_border']))
			{
				$css['moduleinactive_border'] = $current['moduleinactive_border'];
			}

			if (!empty($current['headers_border']))
			{
				$css['headers_border'] = $current['headers_border'];
			}

			if (!empty($current['content_border']))
			{
				$css['content_border'] = $current['content_border'];
			}

			if (!empty($current['button_border']))
			{
				$css['button_border'] = $current['button_border'];
			}
		}

		return $css;

		//If we got here then this user doesn't have a custom profile.
		return self::getSiteDefaultTheme();
	}

	/*** This function validates an url
	 *
	 *	@param	string
	 *
	 *	@return	bool
	 *
	 ***/

	/** This makes sure something passed as a image url, is
	 *
	 *	@param string
	 *
	 * 	@return mixed	false or a string
	 ***/
	public static function getValidColor($colorval)
	{
		//here's how we can determine what this is:
		// if it starts with # or rgb( then it's trying to be a valid color )
		// if it's in color_strings then it's a valid color.
		//if it's an array of three appropriate hex values, it's valid
		// otherwise it's trying to be an url
		$colorval = trim($colorval);

		//It might be the word "transparent"
		if (strtolower($colorval) == 'transparent')
		{
			return 'transparent';
		}

		//see if it's a valid color in # format
		$valid_colors = "!^#?([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?$!iU";
		$matches = array();

		if (preg_match($valid_colors, $colorval, $matches))
		{
			$valid_color = $matches[0];
			if ($valid_color[0] != '#')
			{
				$valid_color = '#' . $valid_color;
			}
			return $valid_color;
		}

		//let's strip out any spaces.
		$colorval = str_replace(' ', '',$colorval);
		$colorval = ($colorval);

		//maybe it's an 'rgb(red,green,blue)' value
		if (substr(strtolower($colorval),0,4) == 'rgb(')
		{
			$tmpcolor = substr(strtolower($colorval),4);
			//remove any trailing spaces
			//is there a ')' on the right? If so, remove it.
			if (substr($tmpcolor, -1,1) != ')')
			{
				return false;
			}
			$tmpcolor = substr($tmpcolor, -1,1);
			$tmpcolor = explode(',',$tmpcolor);
			if ((count($tmpcolor) == 3) &&
				(intval($tmpcolor[0]) >= 0) && (intval($tmpcolor[0]) <= 256) &&
				(intval($tmpcolor[1]) >= 0) && (intval($tmpcolor[1]) <= 256) &&
				(intval($tmpcolor[2]) >= 0) && (intval($tmpcolor[2]) <= 256))
			{
				return '#' . dechex($tmpcolor[0]) . dechex($tmpcolor[1]) . dechex($tmpcolor[2]);
			}
		}
		return false;

	}

	protected function cleanImageLoc($imageLoc)
	{
		$filter = "!<\s*script.*>?!iU";
		$matches = array();

		if (preg_match($filter, $imageLoc, $matches))
		{
			return false;
		}
		return $imageLoc;

	}

	public static function getBGValue($image_url)
	{
		//sometimes we have the word 'none'
		if (strtolower($image_url) == 'none')
		{
			return 'none';
		}

		//it might be a color;
		$result = self::getValidColor($colorval);
		if ($result)
		{
			return $result;
		}

		//Let's see if it's an image. First clean it.
		$image_url = self::cleanImageLoc($image_url);
		if (!$image_url)
		{
			return false;
		}

		//if it's in the form url(<something) then we just return.
		if (strtolower(substr($image_url,0,4)) == 'url(' )
		{
			return $image_url;
		}

		//If it's attachment.php..., we return that.
		if (strtolower(substr($image_url,0,10)) == 'attachment' )
		{
			return 'url(' .$image_url . ')';
		}

		//If it starts with ./ or http:, we wrap that in url(.
		if ((strtolower(substr($image_url,0,1)) == './')
			OR (strtolower(substr($image_url,0,7)) == 'http://' ) )
		{
			return 'url(' .$image_url . ')';
		}

		// If we have in the form integer, integer we turn that into an URL
		// that's a v386 setting meaning albumid, pictureid
		$result = preg_match("/^([0-9]+),([0-9]+)$/", $image_url, $picture);

		if ($result)
		{
			if (!self::$session_url AND class_exists('vB', false))
			{
				self::$session_url = vB::$vbulletin->session->vars['sessionurl'];
			}
			return "url(picture.php?albumid=$picture[1]&pictureid=$picture[2])";
		}
		return 'none';
	}

	/*** This function does a save from the profile page, responding to an ajax call
	 *
	 *	@param mixed	the theme info
	 *	@param mixed	the user object
	 *
	 *	@return	string	a success or failure notice
	 ***/

	public static function saveUserTheme($usertheme, $userinfo)
	{
		$vars = array(
			'font_family' => TYPE_STR,
			'fontsize' => TYPE_STR,
			'title_text_color' => TYPE_STR,
			'page_background_color' => TYPE_STR,
			'page_background_image' => TYPE_STR,
			'page_background_image' => TYPE_STR,
			'page_background_repeat' => TYPE_STR,
			'module_text_color' => TYPE_STR,
			'module_link_color' => TYPE_STR,
			'module_background_color' => TYPE_STR,
			'module_background_image' => TYPE_STR,
			'module_background_repeat' => TYPE_STR,
			'module_border' => TYPE_STR,
			'moduleinactive_text_color' => TYPE_STR,
			'moduleinactive_link_color' => TYPE_STR,
			'moduleinactive_background_color' => TYPE_STR,
			'moduleinactive_background_image' => TYPE_STR,
			'moduleinactive_background_repeat' => TYPE_STR,
			'moduleinactive_border' => TYPE_STR,
			'headers_text_color' => TYPE_STR,
			'headers_link_color' => TYPE_STR,
			'headers_background_color' => TYPE_STR,
			'headers_background_image' => TYPE_STR,
			'headers_background_repeat' => TYPE_STR,
			'headers_border' => TYPE_STR,
			'content_text_color' => TYPE_STR,
			'content_link_color' => TYPE_STR,
			'content_background_color' => TYPE_STR,
			'content_background_image' => TYPE_STR,
			'content_background_repeat' => TYPE_STR,
			'content_border' => TYPE_STR,
			'button_text_color' => TYPE_STR,
			'button_background_color' => TYPE_STR,
			'button_background_image' => TYPE_STR,
			'button_background_repeat' => TYPE_STR,
			'button_border' => TYPE_STR,
			'page_link_color' => TYPE_STR);

		//We only do this if we're logged in as a user.
		if (!intval($userinfo['userid']))
		{
			return 'profile_save_permission_failed_desc';
		}

		//We need the array to pass to the GPC cleaner
		$vars = array('themeid' => TYPE_UINT,
			'deletetheme' => TYPE_UINT,
			'saveasdefault' => TYPE_UINT);
		//Since we're here, we need to know which vars are controlled by which permission
		//might as well do that since we're scanning the array.

		$bg_vars = $color_vars = $border_vars = array();
		foreach(self::$themevars as $varname)
		{
			$vars[$varname] = TYPE_STR;
			if (strpos(varname, 'border') !== false)
			{
				$border_vars[] = $varname;
			}
			else if (strpos(varname, 'background') !== false)
			{
				$bg_vars[] = $varname;
			}
			else
			{
				$color_vars[] = $varname;
			}
		}

		if (!self::$permissions)
		{
			self::setPermissions();
		}

		vB::$vbulletin->input->clean_array_gpc('r', $vars);

		$userid = $userinfo['userid'];
		//see if the user is trying to save as default
		if (vB::$vbulletin->GPC_exists['saveasdefault'] AND (vB::$vbulletin->GPC['saveasdefault'] == 1))
		{
			require_once DIR . '/includes/adminfunctions.php';

			if (can_administer('cansetdefaultprofile'))
			{
				$userid = -1;
			}
		}


		$savedprofile = vB_dB_Assertor::getInstance()->assertQuery('customprofile',
			array('type' => 's', 'userid' => $userid));
		//We need to know whether we're updating or saving
		if ($savedprofile)
		{
			$current = $savedprofile->current();
		}

		//If the user has passed theme = 0 or theme = -1, that means they want the default.
		//So we delete their record if it exists.
		if (vB::$vbulletin->GPC_exists['deletetheme'] AND (intval(vB::$vbulletin->GPC['deletetheme']) )
			AND $current)
		{
			//We just clear the settings;
			$response =  vB_dB_Assertor::getInstance()->assertQuery('customprofile',
				array('type' => 'd', 'customprofileid' => $current['customprofileid']));
			return 'user_profile_reset_to_default';

		}

		//if we have a themeid and they have permissions, we skip all the individual
		// settings and just save the themeid.
		//if we got a themeid we handle that directly.
		if (vB::$vbulletin->GPC_exists['themeid'] AND intval(vB::$vbulletin->GPC['themeid'])
			AND self::$permissions['canusetheme'])
		{
			//let's trim all the non-theme settings. Otherwise we get strange effects later
			// if we currently have background settings and later someone edits their css
			$settings = array('themeid' => vB::$vbulletin->GPC['themeid']);
			foreach (self::$themevars as $themevar)
			{
				$settings[$themevar] = '';
			}
			if ($current)
			{
				$settings['type'] = 'u';
				$settings['customprofileid'] = $current['customprofileid'];
			}
			else
			{
				$settings['type'] = 'i';
				$settings['userid'] = $userinfo['userid'];
			}
			$response =  vB_dB_Assertor::getInstance()->assertQuery('customprofile', $settings);

		}
		else
		{
			//Now we confirm permissions. We unset every variable for which
			// they don't have permission.

			if ($userid != -1)
			{
				if (!self::$permissions['caneditfontfamily'] )
				{
					unset($vars['font_family']);
				}

				if (!self::$permissions['caneditfontsize'])
				{
					unset($vars['fontsize']);
				}

				if (!self::$permissions['caneditcolors'])
				{
					foreach($color_vars as $varname)
					{
						unset($vars[$varname]);
					}
				}

				if (!self::$permissions['caneditbgimage'])
				{
					foreach($bg_vars as $varname)
					{
						unset($vars[$varname]);
					}
				}

				if (!self::$permissions['caneditborders'])
				{
					foreach($border_vars as $varname)
					{
						unset($vars[$varname]);
					}
				}
			}

			//let's set the submitted variables
			foreach ($vars as $varname => $value)
			{
				if (vB::$vbulletin->GPC_exists[$varname])
				{
					if (vB::$vbulletin->GPC[$varname] == 'null')
					{
						if (strpos($varname, 'image'))
						{
							$vars[$varname] =	'none';
						}
						else if (strpos($varname, 'repeat'))
						{
							$vars[$varname] =	'no-repeat';
						}
						else
						{
							$vars[$varname] =	'inherit';
						}
					}
					else
					{
						if (strpos($varname, 'repeat'))
						{
							$vars[$varname] =	self::cleanRepeat(vB::$vbulletin->GPC[$varname]);
						}
						else
						{
							$vars[$varname] = vB::$vbulletin->GPC[$varname];
						}
					}
				}
				else
				{
					unset($vars[$varname]);
				}
			}

			$vars['themeid'] = 0;

			//If we are setting as site default, we're ready to save
			if ($userid == -1)
			{
				$savedprofile = vB_dB_Assertor::getInstance()->assertQuery('customprofile',
				array('type' => 's', 'userid' => -1));

				//We need to know whether we're updating or saving
				if ($savedprofile)
				{
					$current = $savedprofile->current();
				}
				$vars['userid'] = -1;

				if ($current)
				{
					$vars['customprofileid'] = $current['customprofileid'];
					$vars['type'] =  'u';
				}
				else
				{
					unset($vars['customprofileid']);
					$vars['type'] =  'i';
				}
			}
			else
			{
				//These are what were passed to the page via ajax load,
				// and they are probably wrong. Certainly untrustworthy
				unset($vars['userid']);
				unset($vars['customprofileid']);
				if ($current)
				{
					$vars['customprofileid'] = $current['customprofileid'];
					$vars['type'] =  'u';
				}
				else
				{
					$vars[userid] = $userinfo['userid'];
					$vars['type'] =  'i';
				}
			}
			$response =  vB_dB_Assertor::getInstance()->assertQuery('customprofile', $vars);
		}

		if ($response)
		{
			return 'user_profile_saved';
		}
		else
		{
			return 'update_failed';
		}


	}

	/*** This function gets a list of the user's public albums
	 *
	 *	@param int		the submitting user id
	 *
	 *	@return	string	a JSON-formatted associative array of the records.
	 ***/
	public static function getAlbums($userinfo)
	{
		if (!$userinfo )
		{
			return false;
		}
		//execute the query
		$albums = vB_dB_Assertor::getInstance()->assertQuery('CustomProfileAlbums',
			array('type' => 's',
			'contenttypeid' => vB_Types::instance()->getContentTypeID('vBForum_Album') ,
			'userid' => $userinfo['userid']),
			'title ASC');

		//if we have no results, there's nothing we can do.
		if (!$albums OR !$albums->valid())
		{
			return false;
		}
		//the format of the returned array is
		//{albums => {album records}, {content => string(rendered template for content}}
		//first let's get the albums

		$response = '{"albums":{' . "\n";
		while($albums->valid())
		{
			$album = $albums->current();
			$fieldvals = array();
			foreach ($album as $field => $value)
			{
				$fieldvals[] = "\"$field\":\"" . vB_Template_Runtime::escapeJS($value) .'"';
			}
			$response .= '"' . $album['albumid'] . '":{' . implode(",\n", $fieldvals) . "}";
		}
		$response = "}\n";

		//Now let's get the page contents.



	}
	/*** This function gets the contents of a public album
	 *
	 *	@param int		the requested albumid
	 *
	 *	@return	string	a JSON-formatted associative array of the records.
	 ***/
	public static function getAlbumContents($albumid, $userinfo)
	{
		if (!$albumid OR !$userinfo OR !$userinfo['userid'])
		{
			return false;
		}
		//Let's compose and execute the query.

		//get the user's type permissions.
		$extensions = array();
		$album_typeid = vB_Types::instance()->getContentTypeID('vBForum_Album');

		foreach($userinfo['attachmentpermissions'] AS $filetype => $extension)
		{
			if (
					!empty($extension['permissions'])
					AND
					(
					!$extension['contenttypes'][$album_typeid]
					OR
					!isset($extension['contenttypes'][$album_typeid]['e'])
					OR
					$extension['contenttypes'][$album_typeid]['e']
					)
				)
			{
				$extensions[] = "'" . $filetype . "'" ;
			}
		}

		//do the query
		$attachments = vB_dB_Assertor::getInstance()->assertQuery('GetAlbumContents',
		array('type' => 's',
		'contenttypeid' => $album_typeid ,
		'extensions' => implode(',', $extensions), 'albumid' => $albumid),
		'title');

		//if we have no results, there's nothing we can do.
		if (!$attachments OR !$attachments->valid())
		{
			return false;
		}
		$attachment = $attachments->current();

		//$results = array();
		$results = '';
		//build the array of results
		$results = array();
		while($attachments->valid())
		{
			//$results[$attachment['attachmentid']] = $attachment;
			$attachment['date_string'] = vbdate(vB::$vbulletin->options['dateformat'], $attachment['dateline']);
			$attachment['time_string'] = vbdate(vB::$vbulletin->options['timeformat'], $attachment['dateline']);
			$attachment['filesize_formatted'] = vb_number_format($attachment['filesize'], 1, true);
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
			$results[$attachment['attachmentid']] = $attachment;
			$attachment = $attachments->next();
		}

		if (empty($results))
		{
			return false;
		}

		$templater = vB_Template::create('memberinfo_images');
		$templater->register('attachments', $results);
		$results = $templater->render();

		//render the template
		return $results;
	}

	/*** This function responds to an AJAX call for an asset picker.
	 *
	 *	@param mixed	userinfo array
	 * @param mixed	the vbulletin object
	 *
	 *	@return	string	a XML associative array of the data.
	 ***/
	public static function getAssetPicker($userinfo, $registry)
	{
		//see if this user has an album we can use for background images.
		$albums = vB_dB_Assertor::getInstance()->assertQuery('CustomProfileAlbums',
		array('contenttypeid' => vB_Types::instance()->getContentTypeID('vBForum_Album') ,
		'userid' => $userinfo['userid']));
		$album = $albums->current();
		
		$album_select = '';
		while ($albums->valid())
		{
			$album_select .= "<option value=\"" . $album['albumid'] . "\">" .
				$album['title'] . "</option>\n";
			$album = $albums->next();
		}
		require_once DIR . '/includes/class_xml.php';
		$xml = new vB_AJAX_XML_Builder($registry, 'text/xml');

		if (empty($album_select))
		{
			$xml->add_group('error');
			$xml->add_tag('phrase', 'need_public_album_text');
		}
		else
		{
			$xml->add_group('content');
			$template = vB_Template::create('memberinfo_assetpicker');
			$template->register('album_select', $album_select);
			$phrase = new vB_Phrase('profilefield', 'select_album_to_view');
			$template->register('select_album_to_view', $phrase);

			$body = $template->render();
			$template = vB_Template::create('memberinfo_assetpicker_footer');
			$footer = $template->render();
			$header = new vB_Phrase('profilefield', 'asset_picker');
			//Now format this as an xml array.
			$xml->add_tag('body', $body);
			$xml->add_tag('header', $header );
			$xml->add_tag('footer', $footer);
		}

		$xml->close_group();
		$xml->print_xml();
	}

	/*** This function responds to an AJAX call for a confirm close box.
	 *
	 *
	 *	@return	string	a XML associative array of the data.
	 ***/
	public static function getConfirmCloseBox()
	{

		//see if this user has an album we can use for background images.
		$template = vB_Template::create('memberinfo_confirmclose');

		return $template->render();

	}

	/*** This function responds to an AJAX call for a confirm close box.
	 *
	 *
	 *	@return	string	a XML associative array of the data.
	 ***/
	public static function getProfileDialog($phrasekey)
	{


		//see if this user has an album we can use for background images.
		$template = vB_Template::create('memberinfo_dialog');
		$message = new vB_Phrase('profilefield', $phrasekey);

		if (substr($message, 0, 2) == '~~')
		{
			//this is probably an error. If it's a database error we just display the message.
			$message = $phrasekey;
		}
		$template->register('content',$message);
		return $template->render();

	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded=> 01:57, Mon Sep 12th 2011
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/