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

if (VB_AREA != 'Install' AND !isset($GLOBALS['vbulletin']->db))
{
	exit;
}

class vB_Upgrade_vbcms extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'vbcms';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'vbcms';

	/**
	* Can we install the cms product?
	*
	* @var	bool
	*/
	private $caninstall = true;

	/**
	* Result of $product->verify_install
	*
	* @var	mixed
	*/
	private $productresult = null;

	/**
	* Product Obj
	*
	* @var	string
	*/
	private $product = null;

	/*Properties====================================================================*/

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	*/
	public function __construct(&$registry, $phrase, $maxversion)
	{
		parent::__construct($registry, $phrase, $maxversion);

		if (defined('SKIPDB'))
		{
			$this->caninstall = true;
			return;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		require_once(DIR . '/includes/class_upgrade_product.php');
		$this->product = new vB_Upgrade_Product($registry, $phrase, true, $this->caller);
		if ($this->caninstall = (($this->productresult =  $this->product->verify_install('vbcms')) === true))
		{
			if ($this->product->productinfo['version'] != $this->maxversion)
			{
				$this->caninstall = false;
				$this->productresult = sprintf($this->phrase['final']['mismatch_product_version'], $this->product->productinfo['version'], $this->maxversion);
			}
		}

		// Several steps do queries that rely on the following indices so make sure they exist before anything else
		$this->db->hide_errors();
			$this->db->query_write(
				"ALTER IGNORE TABLE " . TABLE_PREFIX . "cms_widgettype ADD UNIQUE INDEX class (class, packageid)"
			);
			$this->db->query_write(
				"ALTER IGNORE TABLE " . TABLE_PREFIX . "cms_permissions ADD UNIQUE INDEX usergroupid (usergroupid, nodeid)"
			);
		$this->db->show_errors();
	}

	/**
	*	Verify if product upgrade step needs to be executed
	*
	* @param	string	version string
	*/
	private function verify_product_version($version = null)
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return false;
		}

		if ($this->is_newer_version($this->product->installed_version, $version))
		{
			$this->skip_message();
			return false;
		}

		return true;
	}

	/**
	* Step #1 - Install New CMS
	* NOTE!! This step does not get updated with schema changes which differs from the Blog.
	*
	*/
	function step_1()
	{
		if (!$this->verify_product_version())
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_article"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_article (
				contentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				pagetext MEDIUMTEXT NOT NULL,
				threadid INT UNSIGNED,
				blogid INT UNSIGNED,
				posttitle VARCHAR(255),
				postauthor VARCHAR(100),
				poststarter INT UNSIGNED,
				blogpostid INT UNSIGNED,
				postid INT UNSIGNED,
				post_posted INT UNSIGNED,
				post_started INT UNSIGNED,
				previewtext VARCHAR(2048),
				previewimage VARCHAR(256),
				imagewidth INT UNSIGNED,
				imageheight INT UNSIGNED,
				PRIMARY KEY (contentid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_grid"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_grid (
				gridid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				gridhtml MEDIUMTEXT,
				title VARCHAR(255) NOT NULL,
				gridhtml_backup MEDIUMTEXT NOT NULL,
				auxheader SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				auxfooter SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				addcolumn SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				addcolumnsnap SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				addcolumnsize SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				`columns` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			 	flattened SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (gridid),
				UNIQUE KEY title (title)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_layout"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_layout (
				layoutid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				gridid INT UNSIGNED NOT NULL,
				template VARCHAR(100),
				status ENUM('draft', 'active', 'retired') NOT NULL DEFAULT 'active',
				contentcolumn INT UNSIGNED NOT NULL DEFAULT '0',
				contentindex INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (layoutid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_layoutwidget"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_layoutwidget (
				layoutid INT UNSIGNED NOT NULL,
				widgetid INT UNSIGNED NOT NULL,
				layoutcolumn INT UNSIGNED NOT NULL DEFAULT 0,
				layoutindex INT UNSIGNED NOT NULL DEFAULT 0,
				KEY layoutid (layoutid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_node"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_node (
				nodeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				nodeleft INT UNSIGNED NOT NULL,
				noderight INT UNSIGNED NOT NULL,
				parentnode INT UNSIGNED DEFAULT NULL,
				contenttypeid INT UNSIGNED NOT NULL,
				contentid INT UNSIGNED DEFAULT '0',
				url VARCHAR(255) NOT NULL,
				styleid INT UNSIGNED DEFAULT NULL,
				layoutid INT UNSIGNED DEFAULT NULL,
				userid INT UNSIGNED NOT NULL DEFAULT 0,
				publishdate INT UNSIGNED DEFAULT NULL,
				setpublish TINYINT UNSIGNED DEFAULT 0,
				issection TINYINT DEFAULT 0,
				onhomepage TINYINT DEFAULT 0,
				permissionsfrom INT UNSIGNED DEFAULT 0,
				lastupdated INT UNSIGNED DEFAULT NULL,
				publicpreview TINYINT DEFAULT 0,
				auto_displayorder TINYINT DEFAULT 0,
				comments_enabled TINYINT DEFAULT 0,
				PRIMARY KEY (nodeid),
				KEY contentid (contenttypeid, contentid),
				KEY nodeleft (nodeleft),
				KEY noderight (noderight),
				KEY parentnode (parentnode),
				KEY contenttype (contenttypeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_category"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_category(
				categoryid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				parentnode INTEGER,
				category VARCHAR(40),
				description VARCHAR(255),
				catleft INTEGER NOT NULL,
				catright INTEGER NOT NULL,
				parentcat INTEGER,
				enabled TINYINT UNSIGNED DEFAULT 0,
				contentcount INTEGER,
				KEY parentnode(parentnode)
				)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_navigation"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_navigation (
				nodeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				nodelist VARCHAR(255),
				PRIMARY KEY (nodeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_nodecategory"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_nodecategory (
				categoryid INT UNSIGNED NOT NULL,
				nodeid INT UNSIGNED NOT NULL,
				PRIMARY KEY (nodeid, categoryid),
				KEY categoryid(categoryid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_sectionorder"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_sectionorder(
				sectionid INTEGER NOT NULL,
				nodeid INTEGER NOT NULL,
				displayorder SMALLINT NOT NULL,
				UNIQUE KEY (sectionid, nodeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_nodeinfo"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_nodeinfo (
				nodeid INT UNSIGNED NOT NULL,
				description MEDIUMTEXT,
				title VARCHAR(255) NOT NULL,
				html_title VARCHAR(255) NOT NULL,
				viewcount INT UNSIGNED DEFAULT '0',
				creationdate INT UNSIGNED NOT NULL,
				workflowdate INT UNSIGNED DEFAULT NULL,
				workflowstatus ENUM('draft','parentpending','published','deleted') DEFAULT NULL,
				workflowcheckedout TINYINT,
				workflowpending TINYINT,
				workflowlevelid INT UNSIGNED DEFAULT '0',
				associatedthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				keywords TEXT,
				PRIMARY KEY (nodeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_nodeconfig"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_nodeconfig (
				nodeid INT UNSIGNED NOT NULL,
				name VARCHAR(255) NOT NULL,
				value MEDIUMTEXT,
				serialized TINYINT UNSIGNED DEFAULT '0',
				KEY nodeid (nodeid),
				UNIQUE KEY unique_1(nodeid, name)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_widget"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_widget (
				widgetid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				varname VARBINARY(50) NOT NULL,
				widgettypeid INT NOT NULL,
				title VARCHAR(255) NOT NULL,
				description MEDIUMTEXT DEFAULT '',
				PRIMARY KEY  (widgetid),
				UNIQUE KEY title(title),
				KEY widgettypeid (widgettypeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_widgetconfig"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_widgetconfig (
				widgetid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				nodeid INT UNSIGNED NOT NULL DEFAULT '0',
				name VARCHAR(255) NOT NULL DEFAULT '',
				value MEDIUMBLOB,
				serialized TINYINT,
				PRIMARY KEY (widgetid, nodeid, name)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_widgettype"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_widgettype (
				widgettypeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				class VARBINARY(50) NOT NULL,
				packageid INT UNSIGNED NOT NULL,
				PRIMARY KEY (widgettypeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_permissions"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_permissions (
				permissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				usergroupid INT UNSIGNED NOT NULL,
				nodeid INT UNSIGNED NOT NULL,
				permissions INT UNSIGNED NOT NULL,
				PRIMARY KEY (permissionid),
				KEY nodeid (nodeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			'usergroup',
			'vbcmspermissions',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'administrator', 1, 1),
			'administrator',
			'vbcmspermissions',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "package
				(productid, class)
			VALUES
				('vbcms', 'vBCms')
		");
		$package = $this->db->query_first("
			SELECT packageid FROM " . TABLE_PREFIX . "package
			WHERE productid = 'vbcms'
		");
		$packageid = $package['packageid'];

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "route
				(userrequest, packageid, class)
			VALUES
				('content', " . intval($packageid) . ", 'Content')
		");
		$route = $this->db->query_first("
			SELECT routeid FROM " . TABLE_PREFIX . "route
			WHERE userrequest = 'content'
		");
		$routeid = $route['routeid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_permissions"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_permissions
				(usergroupid, nodeid, permissions)
			VALUES
				(1, 1, 1),
				(2, 1, 1),
				(3, 1, 1),
				(4, 1, 1),
				(5, 1, 7),
				(6, 1, 15),
				(7, 1, 3)
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "action"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "action
				(routeid, packageid, controller, useraction, classaction)
			VALUES
				($routeid, $packageid, 'Content',	'view',					'View'),
				($routeid, $packageid, 'Content',	'edit',					'EditPage'),
				($routeid, $packageid, 'Content',	'editcontent',	'EditContent'),
				($routeid, $packageid, 'Content',	'configcontent','ConfigContent'),
				($routeid, $packageid, 'Content',	'addcontent',		'AddNode'),
				($routeid, $packageid, 'Content',	'deletecontent','DeleteNode'),
				($routeid, $packageid, 'Content',	'publish',			'PublishNode'),
				($routeid, $packageid, 'Content',	'options',			'NodeOptions'),
				($routeid, $packageid, 'Widget',	'viewwidget',		'View'),
				($routeid, $packageid, 'Widget',	'configwidget',	'Config'),
				($routeid, $packageid, 'Widget',	'configeditor',	'ConfigEditor'),
				($routeid, $packageid, 'Widget',	'editwidget',		'Edit')
		");

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "route
				(userrequest, packageid, class)
			VALUES
				('widget', " . intval($packageid) . ", 'Widget')
		");
		$route = $this->db->query_first("
			SELECT routeid FROM " . TABLE_PREFIX . "route
			WHERE userrequest = 'widget'
		");
		$widget_routeid = $route['routeid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "action"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "action
				(routeid, packageid, controller, useraction, classaction)
			VALUES
				($widget_routeid, $packageid, 'BaseWidget',	'config',	'Config'),
				($widget_routeid, $packageid, 'BaseWidget',	'edit',		'Edit')
		");

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "route
				(userrequest, packageid, class)
			VALUES
				('editor', " . intval($packageid) . ", 'Editor')
		");
		$route = $this->db->query_first("
			SELECT routeid FROM " . TABLE_PREFIX . "route
			WHERE userrequest = 'editor'
		");
		$editor_routeid = $route['routeid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "action"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "action
				(routeid, packageid, controller, useraction, classaction)
			VALUES
				($editor_routeid, $packageid, 'Editor', 'switch',       'SwitchMode'),
				($editor_routeid, $packageid, 'Editor', 'tableoverlay', 'TableOverlay')
		");

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "route
				(userrequest, packageid, class)
			VALUES
				('list', " . intval($packageid) . ", 'List')
		");
		$route = $this->db->query_first("
			SELECT routeid FROM " . TABLE_PREFIX . "route
			WHERE userrequest = 'list'
		");
		$list_routeid = $route['routeid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "action"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "action
				(routeid, packageid, controller, useraction, classaction)
			VALUES
				($list_routeid, $packageid, 'List', 'list', 'List')
		");

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
				(class, packageid, canplace, cansearch, cantag, canattach, isaggregator)
			VALUES
				('Section', $packageid, '1', '0', '0', '0', '1')
		");
		$section = $this->db->query_first("
			SELECT contenttypeid FROM " . TABLE_PREFIX . "contenttype
			WHERE class = 'Section' AND packageid = $packageid
		");
		$section_type_id = $section['contenttypeid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
				(class, packageid, canplace, cansearch, cantag, canattach)
			VALUES
				('Article', $packageid, '1', '1', '1', '1'),
				('ContentNode', $packageid, '0', '0', '0', '0')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
			SET vbcmspermissions = 1
			WHERE adminpermissions & " . intval($this->registry->bf_ugp_adminpermissions['cancontrolpanel']) . "
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgettype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "cms_widgettype
				(class, packageid)
			VALUES
				('SectionNavExt', $packageid),
				('CategoryNav', $packageid),
				('Static', $packageid),
				('Rss', $packageid),
				('myFriends', $packageid),
				('Searchwidget', $packageid),
				('Poll', $packageid),
				('StaticBB', $packageid),
				('Recent', $packageid)
		");

		########## Default Data ##################
		##########################################

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "widget"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_widget
				(widgetid, varname, widgettypeid, title, description)
			VALUES
				(1, 'site_navigation', 1, 'Menu', 'Displays site structure')
		");

$grid_html =
'<div id="doc3" class="yui-tvb-r3">
	<div id="bd">
		<div id="yui-main">
			<div class="yui-b">
				<div class="yui-gf">
					<div class="yui-u first yui-panel">
						<ul class="list_no_decoration widget_list" id="widgetlist_column1">$column[1]</ul>
					</div>
					<div class="yui-u yui-panel">
						<ul class="list_no_decoration widget_list" id="widgetlist_column2">$column[2]</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="yui-b yui-sidebar">
			<ul class="list_no_decoration widget_list" id="widgetlist_column3">$column[3]</ul>
		</div>
	</div>
</div>
';

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_grid"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_grid
				(gridid, gridhtml, title, addcolumn, addcolumnsnap, addcolumnsize, `columns`, flattened)
			VALUES
				(1, '" . $this->db->escape_string($grid_html) . "', 'Two Columns + Sidebar (25%, 75%, 240px)', 1, 1, 3, 8, 0)
		");

		require_once(DIR . '/includes/adminfunctions_template.php');
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "template"),
			"REPLACE INTO " . TABLE_PREFIX . "template
				(styleid, title, template, template_un, dateline, username, product, version)
			VALUES
			(
				0,
				'vbcms_grid_1',
				'" . $this->db->escape_string(compile_template($grid_html)) . "',
				'" . $this->db->escape_string($grid_html) . "',
				" . TIMENOW . ",
				'vBulletin',
				'vbcms',
				'" . $this->db->escape_string($this->registry->options['templateversion']) . "'
			)
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_layout"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_layout
				(layoutid, title, gridid, contentcolumn, contentindex)
			VALUES
				(1, 'Default Layout', 1, 2, 1)
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_layout"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_layout
				(layoutid, title, gridid, contentcolumn, contentindex)
			VALUES
				(1, 'Default Layout', 1, 2, 1)
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_nodeinfo"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_nodeinfo
				(nodeid, description, title, creationdate)
			VALUES
				(1, 'Home', 'Home', " . intval(TIMENOW) . ")
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_node"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_node
				(nodeid,
				nodeleft,
				noderight,
				contenttypeid,
				url,
				styleid,
				layoutid,
				setpublish,
				issection,
				permissionsfrom )
			VALUES
				(1,
				1,
				2,
				$section_type_id,
				'home',
				1,
				1,
				0,
				1,
				1)
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_navigation"),
			"REPLACE INTO " . TABLE_PREFIX . "cms_navigation
				(nodeid, nodelist)
			VALUES
				(1, 1)
		");
	}

	/**
	* Step #2 - Install New CMS
	*	Followup for step 1
	*
	*/
	function step_2()
	{
		if (!$this->verify_product_version())
		{
			return;
		}

		$this->show_message($this->phrase['core']['updating_permissions']);

		setup_default_admin_permissions('vbcmspermissions', 3);
		build_style(-1, '', array('docss' => 0, 'dostylevars' => 0, 'doreplacements' => 0, 'doposteditor' => 0));
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		if (!$this->verify_product_version('1.0.0 Alpha 2'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgetype"),
			"UPDATE IGNORE " . TABLE_PREFIX . "cms_widgettype
			SET class = 'SectionNavExt'
			WHERE class = 'SectionNav'
		");
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		if (!$this->verify_product_version('1.0.0 Alpha 6'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
			"UPDATE " . TABLE_PREFIX . "setting
			SET value = 'forum'
			WHERE varname = 'forumhome'
		");
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		if (!$this->verify_product_version('1.0.0 Beta 1'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 1, 1),
			'cms_node',
			'new',
			'tinyint',
			self::FIELD_DEFAULTS
		);

		// for previous step
		build_options();

		$this->add_cronjob(
			array(
				'varname'  => 'vbcms_dailycleanup',
				'nextrun'  => 1053533100,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => 0,
				'minute'   => 'a:1:{i:0;i:10;}',
				'filename' => './includes/cron/vbcms_dailycleanup.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbcms'
			)
		);

		// default comments forum
		$this->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "forum
				(styleid, title, options, displayorder, replycount, lastpost, lastposter,
				lastthread, lastthreadid, lasticonid, threadcount, daysprune, newpostemail, newthreademail,
				parentid, parentlist, password, link, childlist, title_clean)
			VALUES
				(0, 'vBCms Comments', '24262', '0', '0', '0', '', '', '0', '0', '0', '-1', '', '', '-1', '', '', '', '', 'vBCms Comments')
		");
		$forumid = $this->db->insert_id();

		//let's set the cms comments forum.
		$this->registry->options['vbcmsforumid'] = intval($forumid);
		$this->set_new_option('vbcmsforumid', 'vbcms', intval($forumid), 'posint' );

		$this->db->query_write("
			UPDATE " . TABLE_PREFIX . "forum
				SET parentlist = '" . intval($forumid) . ",-1',
					childlist = '" . intval($forumid) . ",-1'
			WHERE forumid = '" . intval($forumid) . "'
		");

	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		if (!$this->verify_product_version('4.0.0 Beta 1'))
		{
			return;
		}

		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_widget', 1, 2),
			'cms_widget',
			'title'
		);

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_widget', 2, 2),
			'cms_widget',
			'title',
			'title'
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		if (!$this->verify_product_version('4.0.0 Beta 2'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_article', 1, 1),
			'cms_article',
			'previewvideo',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$package = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package where productid = 'vbcms'");

		$packageid = $package['packageid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgettype"),
			"INSERT IGNORE INTO ". TABLE_PREFIX . "cms_widgettype
				(class, packageid)
  		VALUES
  			('CategoryNavBU', $packageid),
  			('RecentCmsComments', $packageid)
		");
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		if (!$this->verify_product_version('4.0.0 Beta 3'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_grid', 1, 1),
			"ALTER TABLE "  . TABLE_PREFIX . "cms_grid CHANGE COLUMN `columns` gridcolumns SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0"
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		if (!$this->verify_product_version('4.0.0 Beta 4'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 1, 10),
			'cms_node',
			'showtitle',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 2, 10),
			'cms_node',
			'showuser',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 3, 10),
			'cms_node',
			'showpreviewonly',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 4, 10),
			'cms_node',
			'showupdated',
			'smallint',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 5, 10),
			'cms_node',
			'showviewcount',
			'smallint',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 6, 10),
			'cms_node',
			'showpublishdate',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 7, 10),
			'cms_node',
			'settingsforboth',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 8, 10),
			'cms_node',
			'includechildren',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 9, 10),
			'cms_node',
			'showall',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 10, 10),
			'cms_node',
			'editshowchildren',
			'smallint',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		if (!$this->verify_product_version('4.0.0 Beta 5'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "cms_rate"),
			"CREATE TABLE " . TABLE_PREFIX . "cms_rate (
			  rateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			  nodeid INT UNSIGNED NOT NULL,
			  userid INT UNSIGNED NOT NULL,
			  vote INT UNSIGNED NOT NULL,
			  ipaddress VARCHAR(15) NOT NULL,
			  PRIMARY KEY (rateid),
			  KEY nodeid (nodeid, userid)
			 )",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_nodeinfo', 1, 3),
			'cms_nodeinfo',
			'ratingnum',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_nodeinfo', 2, 3),
			'cms_nodeinfo',
			'ratingtotal',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_nodeinfo', 3, 3),
			'cms_nodeinfo',
			'rating',
			'int',
			self::FIELD_DEFAULTS
		);

		$package = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package
			WHERE productid = 'vbcms'
		");

		$packageid = $package['packageid'];

		$route = $this->db->query_first("
			SELECT routeid
			FROM " . TABLE_PREFIX . "route
			WHERE packageid = $packageid AND class='Content'
		");
		$routeid = $route['routeid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "action"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "action
				(routeid, packageid, controller, useraction, classaction)
			VALUES
				($routeid, $packageid, 'Content', 'rate', 'Rate')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "widgettype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "cms_widgettype
				(class, packageid)
			VALUES
				('ExecPhp',  $packageid)
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "widgettype"),
			"INSERT INTO " . TABLE_PREFIX . "cms_widgettype
				(class, packageid)
			VALUES
				('Calendar',  $packageid)
		");
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		if (!$this->verify_product_version('4.0.0 Release Candidate 2'))
		{
			return;
		}

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 1, 2),
			'cms_node',
			'publishdate',
			'publishdate'
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 2, 20),
			'cms_node',
			'showrating',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		if (!$this->verify_product_version('4.0.0 Release Candidate 3'))
		{
			return;
		}

		if ($widgettype = $this->db->query_first("
			SELECT widgettypeid
			FROM " . TABLE_PREFIX . "cms_widgettype
			WHERE class = 'StaticBB'
		"))
		{
			$widgettypeid = $widgettype['widgettypeid'];
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "widgettype"),
				"DELETE FROM " . TABLE_PREFIX . "cms_layoutwidget
				WHERE widgetid IN (
         	SELECT widgetid
         	FROM " . TABLE_PREFIX . "cms_widget
         	WHERE widgettypeid = $widgettypeid
         )
			");

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "widgettype"),
				"DELETE FROM " . TABLE_PREFIX . "cms_widget
				WHERE widgettypeid = $widgettypeid
			");

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "widgettype"),
				"DELETE FROM " . TABLE_PREFIX . "cms_widgettype
				WHERE class = 'StaticBB'
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		if (!$this->verify_product_version('4.0.1'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 1, 3),
			'cms_node',
			'hidden',
			'smallint',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 2, 3),
			'cms_node',
			'shownav',
			'smallint',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 3, 3),
			'cms_node',
			'nosearch',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		if (!$this->verify_product_version('4.0.2'))
		{
			return;
		}

		$package = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package where productid = 'vbcms'
		");
		$packageid = $package['packageid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgettype"),
			"INSERT IGNORE INTO ". TABLE_PREFIX . "cms_widgettype
				(class, packageid)
  		VALUES
  			('RecentThreads', $packageid),
				('RecentPosts', $packageid),
				('CategoryNavAll', $packageid)
		");
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		if (!$this->verify_product_version('4.0.3'))
		{
			return;
		}

		$package = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package where productid = 'vbcms'
		");
		$packageid = $package['packageid'];

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_article', 1, 1),
			'cms_article',
			'htmlstate',
			'enum',
			array('attributes' => "('off', 'on', 'on_nl2br')", 'null' => false, 'default' => 'on_nl2br')
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgettype"),
			"INSERT IGNORE INTO ". TABLE_PREFIX . "cms_widgettype
				(class, packageid)
  		VALUES
  			('RecentArticle', $packageid),
  			('RecentBlog', $packageid),
  			('RecentBlogComments', $packageid)
		");

		// Anyone who has HTML permissions needs to have read and create
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_permissions"),
			"UPDATE " . TABLE_PREFIX . "cms_permissions
			SET permissions = permissions | 3
			WHERE (permissions & 16)
		");

		// Anyone who has read or create or publish permission needs to have read
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_permissions"),
			"UPDATE " . TABLE_PREFIX . "cms_permissions
			SET permissions = permissions | 1
			WHERE (permissions & 2)
				OR
			(permissions & 4)
				OR
			(permissions & 8)
		");

		// Set the new canDownload Permissions for everybody who can view
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_permissions"),
			"UPDATE " . TABLE_PREFIX . "cms_permissions
			SET permissions = permissions | 32
			WHERE (permissions & 1)
		");
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		if (!$this->verify_product_version('4.0.4'))
		{
			return;
		}

		$package = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package where productid = 'vbcms'
		");
		$packageid = $package['packageid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "template"),
			"DELETE FROM ". TABLE_PREFIX . "template
			WHERE title = 'vbcms_test_delete'
				AND
			styleid = -1
		");

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_nodeinfo', 1, 2),
			"ALTER TABLE "  . TABLE_PREFIX . "cms_nodeinfo CHANGE COLUMN title title MEDIUMTEXT"
		);

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_nodeinfo', 2, 2),
			"ALTER TABLE "  . TABLE_PREFIX . "cms_nodeinfo CHANGE COLUMN html_title html_title MEDIUMTEXT"
		);

		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_node', 1, 1),
			"ALTER TABLE "  . TABLE_PREFIX . "cms_node CHANGE COLUMN url url MEDIUMTEXT"
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrase"),
			"UPDATE " . TABLE_PREFIX . "phrase SET product = 'vbcms'
			WHERE varname IN
				('contenttype_vbcms_cmscomment','searchtype_vbcms_cmscomment', 'contenttype_vbcms_cmscomment',
				'stylevar_vbcms_widget_border_name','stylevar_vbcms_widget_shadow_color_name',
				'cms_articles_preview','cms_articles_preview_add','cms_priority_manager',
				'sitemap_cms_priority_desc','vbcms', 'sections',
				'cms_section','search_for_cms_comments','stylevar_content_msg_font_description',
				'stylevar_imgdir_cms_name','blocktype_cmsarticles','setting_cmsarticles_catids_desc',
				'setting_cmsarticles_catids_title','setting_cmsarticles_limit_title','setting_cmsarticles_messagemaxchars_desc',
				'setting_cmsarticles_messagemaxchars_title','setting_cmsarticles_sectionids_desc',
				'setting_cmsarticles_catids_title','setting_cmsarticles_limit_desc','setting_cmsarticles_limit_title',
				'setting_cmsarticles_messagemaxchars_desc','setting_cmsarticles_messagemaxchars_title',
				'setting_cmsarticles_sectionids_desc','setting_cmsarticles_sectionids_title','',
				'setting_cmsarticles_type_desc','setting_cmsarticles_type_title',
				'setting_default_route_segment_desc','widgettype_vbcms_friend_activity',
				'setting_default_route_segment_title')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrase"),
			"UPDATE " . TABLE_PREFIX . "phrase
			SET product = 'vbcms'
			WHERE
				varname = 'searchable'
					AND
				fieldname='cpcms'
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "template"),
			"UPDATE " . TABLE_PREFIX . "template SET product = 'vbcms' WHERE title IN (
				'search_input_cmscomment','search_results_cmscomment',
				'vbcms-layout.css','vbcms.css','vbcms_article_editor','vbcms_content_staticpage_inline',
				'vbcms-layout-ie6.css','vbcms_image_src','vbcms_postbit_legacy',
				'vbcms_widget_categorynavall_page','vbcms_widget_recentFriendsActivity_page',
				'vbcms_widget_recentthreads_page','vbcms_widget_searchwidget_pgblog1','vbcms_widget_searchwidget_pgblog2',
				'vbcms_widget_sectionnavext_config','vbcms_widget_sectionnavext_page','cms_userdef_site_summary_bit')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "template"),
			"UPDATE " . TABLE_PREFIX . "template SET product = 'vbcms'
			WHERE title IN ('vbcms_grid_1','vbcms_grid_2','vbcms_grid_3','vbcms_grid_4',
				'vbcms_grid_5','vbcms_grid_6','vbcms_grid_7','vbcms_grid_8',
				'vbcms_grid_9','vbcms_grid_15')
					AND
				styleid < 1
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrase"),
			"UPDATE " . TABLE_PREFIX . "phrase SET product = 'vbblog' WHERE varname IN ('recent_blogs',
				'view_blog_entries', 'search_for_blog_comments','search_for_blog_entries', 'remove_per_mo_since_comment',
				'remove_per_mo_since_post', 'sitemap_blog_priority_desc','after_upgrade_40_update_blog_attachment','blog_priority_manager',
				'vbblog_faq_general_customsidebar','vbblog_faq_general_what','vbblog_faq_groups_what',
				'vbblog_faq_general_customsidebar',
				'vbblog_faq_general_what','vbblog_faq_groups_what','stylevar_content_msg_font_description',
				'stylevar_profile_content_blog_head_description','stylevar_profile_content_blog_head_name',
				'stylevar_profile_content_blogentrybit_continue_reading_description',
				'stylevar_profile_content_blogentrybit_continue_reading_name','stylevar_vbblog_link_color_name',
				'stylevar_vbblog_pagetitle_border_name','stylevar_vbblog_pagetitle_color_name',
				'stylevar_vbblog_pagetitle_description_color_name','stylevar_vbblog_pagetitle_description_font_name',
				'stylevar_vbblog_pagetitle_font_name','stylevar_vbblog_sidebar_header_font_description',
				'stylevar_vbblog_sidebar_header_font_name','blocktype_blogentries','setting_blogentries_catids_desc',
				'setting_blogentries_catids_title','setting_blogentries_limit_desc','setting_blogentries_limit_title',
				'setting_blogentries_messagemaxchars_desc','setting_blogentries_messagemaxchars_title',
				'setting_blogentries_titlemaxchars_desc','setting_blogentries_titlemaxchars_title',
				'setting_blogentries_type_desc','setting_blogentries_type_title','setting_blogentries_userids_desc',
				'setting_blogentries_userids_title', 'adlocation_blogsidebar_end')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "template"),
			"UPDATE " . TABLE_PREFIX . "template
			SET product = 'vbblog'
			WHERE title IN ('blog_comment')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
				(class, packageid, canplace, cansearch, cantag, canattach, isaggregator)
			VALUES
				('StaticPage', $packageid,  '1', '1', '1', '0', '0'),
				('PhpEval', $packageid, '1', '0', '0', '0', '0')
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgettype"),
			"INSERT IGNORE INTO ". TABLE_PREFIX . "cms_widgettype
				(class, packageid)
  		VALUES
  			('CategoryNavCurrent', $packageid),
  			('RecentContent', $packageid)
		");
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		if (!$this->verify_product_version('4.0.5'))
		{
			return;
		}

		$package = $this->db->query_first("
			SELECT packageid
			FROM " . TABLE_PREFIX . "package where productid = 'vbcms'
		");
		$packageid = $package['packageid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "cms_widgettype"),
			"INSERT IGNORE INTO ". TABLE_PREFIX . "cms_widgettype
				(class, packageid)
  		VALUES
  			('TagCloud', $packageid)
		");
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		if (!$this->verify_product_version('4.1.2'))
	    {
	        return;
	    }
	    
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cms_nodeinfo', 1, 1),
			'cms_nodeinfo',
			'associatedthreadid',
			'associatedthreadid'
		);
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		if (!$this->verify_product_version('4.1.4'))
	    {
	        return;
	    }

		$this->add_contenttype('vbcms', 'vBCms', 'ArticleComment');
	}

	/**
	* Step #20 - Final Step
	*	This must always be the second to last numbered step. Just renumnber this step as more upgrade steps are added before
	*
	*/
	function step_20()
	{
		if ($this->caninstall)
		{
			// Clear cms cache objects
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			vB_Bootstrap_Framework::init();
			vB_Cache::instance()->event('vb_types.contenttype_updated');
			vB_Cache::instance()->event('vb_types.package_updated');
			vB_Cache::instance()->event('vb_types.type_updated');
			vB_Cache::instance()->event('vb_cmstypes.widgettype_updated');
			vB_Shutdown::instance()->shutdown();

			$result = $this->product->post_install();
			if (!is_array($result))
			{
				$this->add_error($error, self::PHP_TRIGGER_ERROR, true);
				return false;
			}
			$this->show_message($this->phrase['final']['product_installed']);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #21 - Final Step
	*	This must always be the last numbered step. Just renumber this step as more upgrade steps are added before
	*
	* @param	array	contains prompt results
	*/
	function step_21($data = null)
	{

		if ($this->caninstall)
		{
			if (!isset($data['response']))
			{
				$row = $this->db->query_first("
					SELECT COUNT(*) AS count
					FROM " . TABLE_PREFIX . "cms_node
					WHERE nodeid <> 1"
				);

				require_once(DIR . "/install/cmsdefaultdata/default_data_functions.php");

				if ($row['count'] == 0)
				{
					return $this->install_cms_data();
				}
				else
				{
					$this->skip_message();
				}
			}
			else if ($data['response'])
			{
				if (VB_AREA == 'Upgrade')
				{
					$row = $this->db->query_first("
						SELECT userid
						FROM " . TABLE_PREFIX . "user
						WHERE username = '" . $this->db->escape_string($data['response']) . "'"
					);
					if (!$row)
					{
						return $this->install_cms_data($data['response']);
					}
				}
				else
				{
					if ($data['response'] == 'no')
					{
						$this->show_message($this->phrase['final']['cms_data_import_not_installed']);
						return;
					}

					$row = $this->db->query_first("
						SELECT userid
						FROM " . TABLE_PREFIX . "user"
					);
				}
				$userid = $row['userid'];

				require_once(DIR . "/install/cmsdefaultdata/default_data_functions.php");

				add_default_data();
				if ($errors = add_default_attachments($row['userid']))
				{
					$this->show_message($this->phrase['final']['cms_data_import_attachment_errors']);
					$this->add_error(implode("", $errors), self::PHP_TRIGGER_ERROR, true);
				}
				else
				{
					$this->show_message($this->phrase['final']['cms_data_import_success']);
				}
			}
			else
			{
				$this->show_message($this->phrase['final']['cms_data_import_not_installed']);
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Show CMS Data install prompt
	*
	* @param	string	Username of previous failed lookup
	*
	* @param	array
	*/
	private function install_cms_data($username = '')
	{
		if ($username)
		{
			$phrase = sprintf($this->phrase['final']['user_must_exist_' . $this->caller . '_not_found'], $username);
		}
		else
		{
			$phrase = $this->phrase['final']['user_must_exist_' . $this->caller];
		}
		if ($this->caller == 'cli')
		{
			$this->show_message($phrase);
			return array(
				'prompt' => $this->phrase['final']['enter_username'],
				'confirm' => false
			);
		}
		else
		{
			if (VB_AREA == 'Upgrade')
			{
				return array(
					'prompt'  => $phrase,
					'confirm' => false,
					'cancel'  => $this->phrase['final']['skip']
				);
			}
			else
			{
				return array(
					'prompt'  => $this->phrase['final']['user_must_exist_ajax_install'],
					'confirm' => true,
					'ok'      => $this->phrase['vbphrase']['yes'],
					'cancel'  => $this->phrase['vbphrase']['no'],
				);
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/