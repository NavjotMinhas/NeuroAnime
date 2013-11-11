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

// to call a hook:
//	require_once(DIR . '/includes/class_hook.php');
//	($hook = vBulletinHook::fetch_hook('unique_hook_name')) ? eval($hook) : false;

/**
* Works the vBulletin Plugin Hook System
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @author		Kier & Mike
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vBulletinHook
{
	/**
	* Instance of this class, since we only want one
	*
	*/
	private static $instance = null;

	/**
	* This holds the plugin data
	*
	* @var    array
	*/
	private static $pluginlist = array();

	/**
	* This keeps track of which hooks have been used
	*
	* @var	array
	*/
	private static $hookusage = array();

	/**
	* Constructor - unserializes the plugin data from the datastore when class is initiated
	*
	* @return	none
	*/
	function vBulletinHook()
	{
	}

	/**
	* Sets the plugin list array
	*/
	public static function set_pluginlist($pluginlist)
	{
		self::$pluginlist = $pluginlist;
	}

	/**
	* Returns any code attached to a hook with a specific name
	*
	* @param	string	hookname	The name of the hook (location) to be executed
	*
	* @return	string
	*/
	private static function fetch_hook_object($hookname)
	{
		if (!empty(self::$pluginlist["$hookname"]))
		{
			if (!isset(self::$hookusage["$hookname"]))
			{
				self::$hookusage["$hookname"] = true;
			}
			return self::$pluginlist["$hookname"];
		}
		else
		{
			if (!isset(self::$hookusage["$hookname"]))
			{
				self::$hookusage["$hookname"] = false;
			}
			return '';
		}
	}

	/**
	* Returns any code attached to a hook with a specific name. Used when the object is not in scope already.
	*
	* @param	string	hookname	The name of the hook (location) to be executed
	*
	* @return	string
	*/
	public static function fetch_hook($hookname = false)
	{
		if (!$hookname)
		{
			return false;
		}

		return self::fetch_hook_object($hookname);
	}

	/**
	* Builds the datastore for the hooks into the database.
	*/
	public static function build_datastore(&$dbobject)
	{
		$code = array();
		$admincode = array();

		$adminlocations = array();

		require_once(DIR . '/includes/class_xml.php');
		$handle = opendir(DIR . '/includes/xml/');
		while (($file = readdir($handle)) !== false)
		{
			if (!preg_match('#^hooks_(.*).xml$#i', $file, $matches))
			{
				continue;
			}

			$xmlobj = new vB_XML_Parser(false, DIR . "/includes/xml/$file");
			$xml = $xmlobj->parse();

			if (!is_array($xml['hooktype'][0]))
			{
				$xml['hooktype'] = array($xml['hooktype']);
			}

			foreach ($xml['hooktype'] AS $key => $hooktype)
			{
				if (!is_numeric($key))
				{
					continue;
				}

				if (!is_array($hooktype['hook']))
				{
					$hooktype['hook'] = array($hooktype['hook']);
				}

				foreach ($hooktype['hook'] AS $hook)
				{
					if ((is_array($hook) AND !empty($hook['admin'])) OR !empty($hooktype['admin']))
					{
						$adminlocations[(is_string($hook) ? $hook : $hook['value'])] = true;
					}
				}
			}
		}

		$plugins = $dbobject->query_read("
			SELECT plugin.*,
				IF(product.productid IS NULL, 0, 1) AS foundproduct,
				IF(plugin.product = 'vbulletin', 1, product.active) AS productactive
			FROM " . TABLE_PREFIX . "plugin AS plugin
			LEFT JOIN " . TABLE_PREFIX . "product AS product ON(product.productid = plugin.product)
			WHERE plugin.active = 1
				AND plugin." . "phpcode <> ''
			ORDER BY plugin.executionorder ASC
		");
		while ($plugin = $dbobject->fetch_array($plugins))
		{
			if ($plugin['foundproduct'] AND !$plugin['productactive'])
			{
				continue;
			}
			else if (!empty($adminlocations["$plugin[hookname]"]))
			{
				$admincode["$plugin[hookname]"] .= "$plugin[phpcode]\r\n";
			}
			else
			{
				$code["$plugin[hookname]"] .= "$plugin[phpcode]\r\n";
			}
		}
		$dbobject->free_result($plugins);

		build_datastore('pluginlist', serialize($code), 1);
		build_datastore('pluginlistadmin', serialize($admincode), 1);

		return true;
	}

	/**
	* Fetches the array of hooks that have been used.
	*/
	public static function fetch_hookusage()
	{
		return self::$hookusage;
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>