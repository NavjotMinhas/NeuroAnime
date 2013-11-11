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

error_reporting(E_ALL & ~E_NOTICE);

class vB_Block
{
	/**
	* Main registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	 * The id of a particular block being worked on.
	 *
	 * @var int
	 */
	protected $blockid;

	/**
	 * The blockinfo
	 *
	 * @var array
	 */
	protected $blockinfo;


	/**
	 * The block type
	 *
	 * @var vB_BlockType
	 */
	protected $blocktype = null;

	public function __construct(&$registry, &$blockinfo)
	{
		$this->registry =& $registry;
		$this->blockid = $blockinfo['blockid'];
		$this->blockinfo = $blockinfo;

		// We create new blocktype instance here
		$classfile = DIR . "/includes/block/" . $blockinfo['blocktypename'] .".php";
		if (file_exists($classfile))
		{
			require_once $classfile;
			$classname = "vB_BlockType_" . ucfirst(strtolower($blockinfo['blocktypename']));
			if (class_exists($classname))
			{
				if (is_subclass_of($classname, 'vB_BlockType'))
				{
					$this->blocktype = new $classname($registry, $this);
				}
			}
		}

		if (!$this->blocktype)
		{
			throw new Exception("Invalid block type or block type doesn't exist.");
		}

	}

	public static function create(&$registry, $blockid, &$blockscache = null)
	{
		static $instances = array();
		if ($instances[$blockid])
		{
			return $instances[$blockid];
		}

		if ($blockscache[$blockid]['blocktypename'])
		{
			$blockinfo =& $blockscache[$blockid];
		}
		else
		{
			// Fetch from db
			$block = $registry->db->query_first("SELECT block.*,
					blocktype.productid, blocktype.name AS blocktypename, blocktype.title AS blocktypetitle,
					blocktype.description as blocktypedescription, blocktype.allowcache,
					product.active as productacitve
				FROM " . TABLE_PREFIX . "block AS block
				LEFT JOIN " . TABLE_PREFIX . "blocktype AS blocktype ON (block.blocktypeid = blocktype.blocktypeid)
				LEFT JOIN " . TABLE_PREFIX . "product AS product ON (blocktype.productid = product.productid)
				WHERE block.blockid = " . intval($blockid));

			$blockinfo =& $block;
		}

		$blockinfo['configcache'] = @unserialize($blockinfo['configcache']);

		if (!$blockinfo['blocktypename'])
		{
			throw new Exception("Invalid blockid or blocks cache");
		}

		$instances[$blockid] = new vB_Block($registry, $blockinfo);
		return $instances[$blockid];
	}


	/**
	 * Get HTML
	 */
	public function getBlockHTML()
	{
		if (
			!$this->registry->options['disableblockcache']
				AND
			$this->blockinfo['allowcache']
				AND
			$this->blockinfo['cachettl'] > 0
		)
		{
			$cache_key = $this->blocktype->getHash();
			$data = vB_Cache::instance()->read($cache_key, true);
			if (!$data)
			{
				$data = $this->blocktype->getData();
				if (!$this->registry->options['disableblockcache'] AND $this->blockinfo['allowcache'] AND $this->blockinfo['cachettl'] > 0)
				{
					vB_Cache::instance()->write($cache_key,
					$data, $this->blockinfo['cachettl'], $this->event);
				}
			}
		}

		$html = $this->blocktype->getHTML($data);

		return $html;
	}

	/**
	 *
	 * Get block info
	 */
	public function getBlockInfo()
	{
		return $this->blockinfo;
	}

	public function getBlockConfig()
	{
		return $this->blockinfo['configcache'];
	}

	/**
	 * Get type name
	 */
	public function getBlockTypeName()
	{
		return $this->blockinfo['blocktypename'];
	}

	/**
	 * Get block type object
	 *
	 * @return vB_BlockType
	 */
	public function getBlockType()
	{
		return $this->blocktype;
	}

	public function getBlockId()
	{
		return $this->blockid;
	}
}


abstract class vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = '';

	/**
	 * The title of the block type
	 *
	 * @var string
	 */
	protected $title = null;

	/**
	 * The description of the block type
	 *
	 * @var string
	 */
	protected $description = null;

	/**
	 * Whether the block cache is allowed.
	 * If set to false, people will not be able to set cache ttl in admincp
	 *
	 * @var bool
	 */
	protected $allowcache = true;

	/**
	 * The name of the blocktype
	 * Auto generated in constructor
	 *
	 * @var string
	 */
	protected $blocktypename = '';

	/**
	* Main registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;


	/**
	* Information about user
	*
	* @var	array
	*/
	protected $userinfo = array();

	/**
	 * Block instance
	 *
	 * @var vB_Block
	 */
	protected $block = null;

	/**
	 * Block info
	 *
	 * @var array
	 */
	protected $blockinfo = null;

	/**
	 * Block instance Config
	 *
	 * @var array
	 */
	protected $config = null;

	/**
	 * Cache purge event
	 *
	 * @var bool|string
	 */
	protected $event = false;

	/**
	 * The block settings
	 * It uses the same data structure as forum settings table
	 * e.g.:
	 * <code>
	 * $settings = array(
	 *     'varname' => array(
	 *         'defaultvalue' => 0,
	 *         'optioncode'   => 'yesno'
	 *         'displayorder' => 1,
	 *         'datatype'     => 'boolean'
	 *     ),
	 * );
	 * </code>
	 * @see print_setting_row()
	 *
	 * @var string
	 */
	protected $settings = null;

	public function  __construct(&$registry, vB_Block $block = null)
	{
		$this->registry =& $registry;
		$this->userinfo =& $this->registry->userinfo;
		// Get blocktype name
		$this->blocktypename = strtolower(str_replace('vB_BlockType_', '', get_class($this)));

		$this->title = 'blocktype_' . $this->blocktypename;

		if ($block)
		{
			// The block's type should be the same as $this
			if ($block->getBlockTypeName() !== $this->blocktypename)
			{
				throw new Exception("The type of block instance doesn't match this blocktype class.");
			}
			$this->block = $block;
			$this->blockinfo = $block->getBlockInfo();
			$this->config = $block->getBlockConfig();
		}
	}

	/**
	 * Fetches the standard page view for a block.
	 */
	public function getHTML()
	{
		throw (new Exception('Config view requested from block \'' . htmlspecialchars($this->title) . '\' but getHTML() is undefined'));
	}

	/**
	 * Returns the config view for the block.
	 *
	 * @param vB_Registry
	 * @param setting values
	 *
	 * @return boolean
	 */
	public function getConfigHTML($settingvalues = array())
	{
		if ($this->settings)
		{
			// query settings phrases
			global $settingphrase;
			$settingphrase = array();
			$phrases = $this->registry->db->query_read("
				SELECT varname, text
				FROM " . TABLE_PREFIX . "phrase
				WHERE fieldname = 'vbblocksettings' AND
					languageid IN(-1, 0, " . LANGUAGEID . ")
				ORDER BY languageid ASC
			");
			while($phrase = $this->registry->db->fetch_array($phrases))
			{
				$settingphrase["$phrase[varname]"] = $phrase['text'];
			}

			require_once(DIR . '/includes/adminfunctions_options.php');
			foreach ($this->settings AS $settingid => $setting)
			{
				if (isset($settingvalues[$settingid]))
				{
					$setting['value'] = $settingvalues[$settingid];
				}
				elseif (isset($setting['defaultvalue']))
				{
					$setting['value'] = $setting['defaultvalue'];
				}
				$setting['varname'] = $settingid;
				if (!$setting['advanced'])
				{
					print_setting_row($setting, $settingphrase, false);
				}
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get hash for cache
	 */
	public function getHash()
	{
//		require_once(DIR . '/vb/context.php');
		$context = new vB_Context('forumblock' ,
			array(
				'blockid' => $this->blockinfo['blockid'],
				'permissions' => $this->userinfo['forumpermissions'],
				THIS_SCRIPT)
			);

		return strval($context);
	}

	public function getClassname()
	{
		return get_class($this);
	}

	public function getSettings()
	{
		return $this->settings;
	}

	public function getTitle()
	{
		return $this->title;
	}

	public function getDescription()
	{
		return $this->description;
	}

	public function getProductId()
	{
		return $this->productid;
	}

	public function isAllowCache()
	{
		return $this->allowcache;
	}

	// ##### Util methods ####################################################

	protected function get_summary($pagetext, $length)
	{
		$strip_quotes = true;
		$page_text = $this->get_pagetext_noquote($pagetext);

		// Deal with the case that quote was the only content of the post
		if (trim($page_text) == '')
		{
			$page_text = $pagetext;
			$strip_quotes = false;
		}

		return htmlspecialchars_uni(fetch_censored_text(
			trim(fetch_trimmed_title(strip_bbcode($page_text, $strip_quotes, false, false, true), $length))));
	}

	protected function get_pagetext_noquote($pagetext)
	{
		require_once(DIR . '/includes/functions_search.php');

		//figure out how to handle the 'cancelwords'
		$display['highlight'] = array();
		return preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe',
			"process_quote_removal('\\3', \$display['highlight'])", $pagetext);
	}

	protected function fetch_avatarinfo(&$userinfo)
	{
		$userinfo = array_merge($userinfo , convert_bits_to_array($userinfo['adminoptions'] , $this->registry->bf_misc_adminoptions));

		// get avatar
		if ($userinfo['avatarid'])
		{
			$userinfo['avatarurl'] = $userinfo['avatarpath'];
		}
		else
		{
			if ($userinfo['hascustomavatar'] AND $this->registry->options['avatarenabled'])
			{
				if ($this->registry->options['usefileavatar'])
				{
					$userinfo['avatarurl'] = $this->registry->options['avatarurl'] . '/avatar' . $userinfo['userid'] . '_' . $userinfo['avatarrevision'] . '.gif';
				}
				else
				{
					$userinfo['avatarurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . '&amp;dateline=' . $userinfo['avatardateline'];
				}

				$userinfo['avwidthpx'] = intval($userinfo['avwidth']);
				$userinfo['avheightpx'] = intval($userinfo['avheight']);

				if ($userinfo['avwidth'] AND $userinfo['avheight'])
				{
					$userinfo['avwidth'] = 'width="' . $userinfo['avwidth'] . '"';
					$userinfo['avheight'] = 'height="' . $userinfo['avheight'] . '"';
				}
				else
				{
					$userinfo['avwidth'] = '';
					$userinfo['avheight'] = '';
				}
			}
			else
			{
				$userinfo['avatarurl'] = '';
			}
		}

		if (empty($userinfo['permissions']))
		{
			cache_permissions($userinfo, false);
		}

		if ( // no avatar defined for this user
			empty($userinfo['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$userinfo['avatarid'] AND !($userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$userinfo['adminavatar']) //
		)
		{
			$userinfo['showavatar'] = false;
		}
		else
		{
			$userinfo['showavatar'] = true;
		}
	}
}

/**
* Class that provides methods to manage, config and output blocks
*
* @package	vBulletin
* @version	$Revision: 33135
* @date		$Date: 2009-11-04 23:55:33 +0800 (Wednesday, 04 November 2009)
*/
class vB_BlockManager
{
	/**
	* Main registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Information about user
	*
	* @var	array
	*/
	protected $userinfo = array();

	/**
	 * Block types cache. Only present when getBlockTypes called
	 *
	 * @var bool|array
	 */
	protected $blocktypes = false;

	/**
	 * Block cache. Only present when getBlocks called
	 *
	 * @var bool|array
	 */
	protected $blocks = false;

	/**
	 * Block instances cache
	 *
	 * @var array
	 */
	protected $blockinstances = array();

	/**
	 * Active block cache. Only present when getBlocks called
	 *
	 * @var mixed
	 */
	protected $activeblocks = false;

	protected function __construct(&$registry)
	{
		$this->registry =& $registry;
		$this->userinfo =& $this->registry->userinfo;
	}

	/**
	 * Singleton instance.
	 *
	 * @param vB_Registry $registry
	 * @return vB_BlockManager
	 */
	public static function create(&$registry)
	{
		static $instance = null;

		if ($instance)
		{
			return $instance;
		}
		else
		{
			$instance = new vB_BlockManager($registry);
			return $instance;
		}
	}

	/**
	 * Create vB_Block Instance
	 *
	 * @param int $blockid Block ID
	 * @param array $blockscache
	 * @return vB_Block
	 */
	public function createBlock($blockid, &$blockscache = null)
	{
		if ($this->blockinstances[$blockid])
		{
			return $this->blockinstances[$blockid];
		}

		$this->blockinstances[$blockid] = vB_Block::create($this->registry, $blockid, $blockscache);
		return $this->blockinstances[$blockid];
	}

	/**
	 * Reload block types from file system
	 *
	 * @param bool If true, the function will remove missing blocks from db
	 */
	public function reloadBlockTypes($removemissing = false)
	{
		$db = &$this->registry->db;

		// Get the active product cache
		$productcache = $this->getActiveProducts();		
		// Get exists blocktypes
		$blocktypes = $db->query_read("SELECT blocktypeid, name FROM " . TABLE_PREFIX . "blocktype");
		$existing_blocktypes = array();
		while ($blocktype = $db->fetch_array($blocktypes))
		{
			$existing_blocktypes[$blocktype['name']] = $blocktype['blocktypeid'];
		}
		$blocktypestokeep = array();
		// we scan block dir now
		$files = scandir(DIR . '/includes/block');
		foreach ($files as $file)
		{
			if ($file !== '.' AND $file !== '..' AND substr($file, -strlen('.php')) === '.php')
			{
				// Guess block type name
				$blocktypename = substr($file, 0, strrpos($file, "."));
				if (strpos($blocktypename, '.') === false AND strpos($blocktypename, ' ') === false)
				{
					// try to include the file
					include_once(DIR . '/includes/block/' . $file);
					// Check if block class exists and is subclass of vB_Block
					$classname = 'vB_BlockType_' . ucfirst(strtolower($blocktypename));
					if (class_exists($classname) AND is_subclass_of($classname, 'vB_BlockType'))
					{
						$obj = new $classname($this->registry);
						// Let's get block type info and insert into db
						$title = trim($obj->getTitle());
						$description = trim($obj->getDescription());
						$productid = trim($obj->getProductId());
						if ($obj->isAllowCache() === true)
						{
							$allowcache = 1;
						}
						else
						{
							$allowcache = 0;
						}
						if ($title AND in_array($productid, $productcache))
						{
							if (!array_key_exists($blocktypename, $existing_blocktypes))
							{
								// Insert new block type into db
								$db->query("INSERT INTO " . TABLE_PREFIX . "blocktype (name, title, description, productid, allowcache)
									VALUES
									('" . $db->escape_string($blocktypename) . "', '" . $db->escape_string($title) . "', '" . $db->escape_string($description) . "', '" . $db->escape_string($productid) . "', $allowcache)");
								$blocktypestokeep[] = $db->insert_id();
							}
							else
							{
								$db->query("UPDATE " . TABLE_PREFIX . "blocktype SET
										title = '" . $db->escape_string($title) . "',
										description = '" . $db->escape_string($description) . "',
										productid = '" . $db->escape_string($productid) . "',
										allowcache = $allowcache
									WHERE name = '" . $db->escape_string($blocktypename) . "'
								");
								$blocktypestokeep[] = $existing_blocktypes[$blocktypename];
							}
						}
					}
				}
			}
		}
		if ($removemissing)
		{
			// Remove the blocks that no longer exist any more
			$sql = '-1';
			foreach ($blocktypestokeep as $w)
			{
				$sql .= ", $w";
			}
			$db->query("DELETE FROM  " . TABLE_PREFIX . "blocktype WHERE blocktypeid NOT IN ($sql)");

			$blockstoremove = $db->query_read("SELECT blockid FROM " . TABLE_PREFIX . "block WHERE blocktypeid NOT IN ($sql)");

			$blocksql = '-1';
			while ($block = $db->fetch_array($blockstoremove))
			{
				$blocksql .= ", $block[blockid]";
			}
			$db->query("DELETE FROM " . TABLE_PREFIX . "blockconfig WHERE blockid IN ($blocksql)");
			$db->query("DELETE FROM " . TABLE_PREFIX . "block WHERE blockid IN ($blocksql)");
		}

		$blocks = $this->getBlocks(true, true);
		foreach ($blocks as $block)
		{
			// Set block new settings to their default value
			$this->saveBlockConfig($block['blockid'], @unserialize($block['configcache']));
		}

		return true;
	}

	/**
	 * Get block types
	 *
	 * @return array Block types
	 */
	public function getBlockTypes()
	{
		if ($this->blocktypes !== false)
		{
			return $this->blocktypes;
		}

		$blocktypes = $this->registry->db->query_read("SELECT * FROM " . TABLE_PREFIX . "blocktype ORDER BY title");
		$this->blocktypes = array();

		while ($row = $this->registry->db->fetch_array($blocktypes))
		{
			// do not use product specific blocks when the product is not installed/enabled
			if ( !empty($row['productid']) AND !$this->registry->products[$row['productid']] )
			{
				continue;
			}
			$this->blocktypes[$row['blocktypeid']] = $row;
		}
		return $this->blocktypes;
	}

	/**
	 * Get Active Products
	 * Returns the active products, tries to use the cache whenever possible
	 * @return array productcache
	 */
	
	private function getActiveProducts()
	{
		$db = &$this->registry->db;
		//the cache is not set during install
        if (!isset($this->registry->products))
        {
            $productcache = array("");
        	$products = $db->query_read("SELECT productid FROM " . TABLE_PREFIX . "product WHERE active = 1");
            while ($product = $db->fetch_array($products))
            {
                $productcache[] = $product['productid'];
            }
            $db->free_result($products);
        }
        else
        {
			$productcache =  array_keys(array_filter($this->registry->products));
			$productcache[] = '';
		}
		return $productcache;		
	}
	/**
	 * Get blocks
	 * The return of this function can be used by vB_Block::create
	 *
	 * @param bool return active blocks only
	 * @param bool whether to force rebuild activeblocks datastore
	 *
	 * @return array Blocks
	 */
	public function getBlocks($activeonly = true, $rebuildcache = false)
	{
		if ($activeonly)
		{
			$varname = 'activeblocks';
			if ($this->registry->activeblocks AND !$rebuildcache)
			{
				return $this->registry->activeblocks;
			}
			// Active product cache
			$productcache =  $this->getActiveProducts();
			$sql = " AND block.active = 1 AND blocktype.productid IN ('".implode("','",$productcache)."')";
		}
		else
		{
			$varname = 'blocks';
			if ($this->$varname !== false AND !$rebuildcache)
			{
				return $this->$varname;
			}
			$sql = "";
		}

		$blocks = $this->registry->db->query_read("SELECT block.*,
					blocktype.productid, blocktype.name AS blocktypename, blocktype.title AS blocktypetitle,
					blocktype.description as blocktypedescription, blocktype.allowcache
				FROM " . TABLE_PREFIX . "block AS block
				LEFT JOIN " . TABLE_PREFIX . "blocktype AS blocktype ON (block.blocktypeid = blocktype.blocktypeid)
				WHERE 1$sql
				ORDER BY block.displayorder, block.title
		");

		$this->$varname = array();

		while ($row = $this->registry->db->fetch_array($blocks))
		{
			//no need to skip over the blocks for inactive products as we fetch only the right ones
			$this->$varname += array($row['blockid'] => $row);
		}

		// If activeblock, we need to build datastore
		if ($activeonly)
		{
			build_datastore('activeblocks', serialize($this->activeblocks), 1);
		}

		return $this->$varname;
	}

	public function getBlockTypeById($blocktypeid)
	{
		$blocktype = $this->registry->db->query_first("SELECT * FROM  " . TABLE_PREFIX . "blocktype WHERE blocktypeid = " . intval($blocktypeid));
		if (!$blocktype)
		{
			throw new Exception("Invalid block type id");
		}
		return $blocktype;
	}

	/**
	 * Load blocktype class and return the classname
	 * This function should only be used to load and call blocktype static properties/functions
	 * To create instance of BlockType, always use vB_Block constructor
	 *
	 * @param string Block type name
	 * @return string Block type class name
	 */
	public function loadBlockTypeClassByName($name)
	{
		$classfile = DIR . "/includes/block/" . $name .".php";
		if (file_exists($classfile))
		{
			require_once $classfile;
			$classname = 'vB_BlockType_' . ucfirst(strtolower($name));
			if (class_exists($classname))
			{
				if (is_subclass_of($classname, 'vB_BlockType'))
				{
					return $classname;
				}
			}
		}

		throw new Exception('Invalid block type name');
	}

	public function saveNewBlock($blocktypeid, $title, $description, $cachettl, $settings)
	{
		$title = trim($title);
		$description = trim($description);
		$cachettl = intval($cachettl);
		if (empty($title))
		{
			throw new Exception("Block title is required");
		}

		$blocktype = $this->getBlockTypeById($blocktypeid);

		$this->registry->db->query("INSERT INTO " . TABLE_PREFIX . "block (blocktypeid, title, description, cachettl, active)
			VALUES (
				$blocktype[blocktypeid],
				'" . $this->registry->db->escape_string($title) . "',
				'" . $this->registry->db->escape_string($description) . "',
				$cachettl,
				1
			)
		");

		$blockid = $this->registry->db->insert_id();

		$this->saveBlockConfig($blockid, $settings);

		// Rebuild activeblocks datastore
		$this->getBlocks(true, true);
	}

	public function updateBlock($blockid, $title, $description, $cachettl, $active, $settings)
	{
		$blockid = intval($blockid);
		$title = trim($title);
		$description = trim($description);
		$cachettl = intval($cachettl);
		$active = intval($active);

		if (empty($title))
		{
			throw new Exception("Block title is required");
		}

		$block = $this->createBlock($blockid);

		$this->registry->db->query("
			UPDATE " . TABLE_PREFIX . "block SET
				title = '" . $this->registry->db->escape_string($title) . "',
				description = '" . $this->registry->db->escape_string($description) . "',
				cachettl = $cachettl,
				active = $active
			WHERE blockid = $blockid
		");


		$this->saveBlockConfig($blockid, $settings);

		// Purge Block Cache
		$this->purgeBlockCache();

		// Rebuild activeblocks datastore
		$this->getBlocks(true, true);

	}

	public function updateBlockOrder($blockid, $displayorder)
	{
		$blockid = intval($blockid);
		$displayorder = intval($displayorder);

		$this->registry->db->query("
			UPDATE " . TABLE_PREFIX . "block SET
				displayorder = $displayorder
			WHERE blockid = $blockid
		");
	}

	/**
	 * Save Block Config
	 *
	 * @param int Block ID
	 * @param array Settings value
	 * @param string Optional blocktype name. If provided, one query will be saved
	 */
	public function saveBlockConfig($blockid, $settings)
	{
		$blockid = intval($blockid);
		// try to load blocktypeinfo from $blockid
		$block = $this->createBlock($blockid);

		// Save settings
		require_once(DIR . '/includes/adminfunctions_options.php');

		$options = $block->getBlockType()->getSettings();

		foreach($settings AS $varname => $value)
		{
			if ($options[$varname])
			{
				if (!$options[$varname]['datatype'])
				{
					$options[$varname]['datatype'] = 'free';
				}
				$value = validate_setting_value($value, $options[$varname]['datatype']);

				if (is_object($value) OR is_array($value))
				{
					$serialized = 1;
					$value = serialize($value);
				}
				else
				{
					$serialized = 0;
				}

				// Do save
				$this->registry->db->query("REPLACE INTO " . TABLE_PREFIX . "blockconfig (blockid, name, value, serialized)
					VALUES (
						$blockid,
						'". $this->registry->db->escape_string($varname) . "',
						'". $this->registry->db->escape_string($value) . "',
						$serialized
				)");
			}
		}

		// There may be new options that doesn't exist in $settings array, if so we create the blockconfig records with their defaultvalue
		foreach ($options as $varname => $v)
		{
			if (!in_array($varname, array_keys($settings)))
			{
				$value = $v['defaultvalue'];
				if (is_object($value) OR is_array($value))
				{
					$serialized = 1;
					$value = serialize($value);
				}
				else
				{
					$serialized = 0;
				}

				// Do save
				$this->registry->db->query("REPLACE INTO " . TABLE_PREFIX . "blockconfig (blockid, name, value, serialized)
					VALUES (
						$blockid,
						'". $this->registry->db->escape_string($varname) . "',
						'". $this->registry->db->escape_string($value) . "',
						$serialized
				)");
			}
		}

		$this->rebuildBlockConfigCache($blockid);
	}

	public function rebuildBlockConfigCache($blockid)
	{
		$cache = array();
		$configs = $this->registry->db->query_read("SELECT * FROM " . TABLE_PREFIX . "blockconfig WHERE blockid = $blockid");
		if ($this->registry->db->num_rows($configs) > 0)
		{
			while ($config = $this->registry->db->fetch_array($configs))
			{
				if ($config['serialized'])
				{
					$value = @unserialize($config['value']);
				}
				else
				{
					$value = $config['value'];
				}
				$cache[$config['name']] = $value;
			}
		}

		if ($cache)
		{
			$cache = $this->registry->db->escape_string(serialize($cache));
			$this->registry->db->query("UPDATE " . TABLE_PREFIX . "block SET configcache = '$cache' WHERE blockid = $blockid");
			return true;
		}

		return false;
	}

	public function purgeBlockCache()
	{
		$this->registry->db->query("DELETE FROM " . TABLE_PREFIX . "cache WHERE cacheid LIKE 'forumblock.%'");
		return true;
	}

	public function deleteBlock($blockid)
	{
		$blockid = intval($blockid);

		// Remove config
		$this->registry->db->query("DELETE FROM " . TABLE_PREFIX . "blockconfig WHERE blockid = $blockid");
		// Remove block
		$this->registry->db->query("DELETE FROM " . TABLE_PREFIX . "block WHERE blockid = $blockid");
		// Unset blockmanager instance cache
		unset ($this->blockinstances[$blockid]);

		// Rebuild datastore
		$this->getBlocks(true, true);
	}

	public function getBlockHTML($blockid, &$blockscache = null)
	{
		$block = $this->createBlock($blockid, $blockscache);
		$blockinfo = $block->getBlockInfo();

		if (!$this->registry->options['disableblockcache'] AND $blockinfo['allowcache'] AND $blockinfo['cachettl'] > 0)
		{
			// We need vB_Cache so we need to bootstrap framework
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			vB_Bootstrap_Framework::init();
		}

		return $block->getBlockHTML();
	}

	public function getSidebarHTML()
	{
		try
		{
			$blocks = $this->getBlocks();
			$html = '';
			foreach ($blocks as $blockid => $block)
			{
				try
				{
					$html .= $this->getBlockHTML($blockid, $blocks);
				}
				catch (Exception $e)
				{
					if ($this->registry->debug)
					{
						throw $e;
					}
				}
			}
		}
		catch (Exception $e)
		{
			if ($this->registry->debug)
			{
				throw $e;
			}
		}
		return $html;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35105 $
|| ####################################################################
\*======================================================================*/