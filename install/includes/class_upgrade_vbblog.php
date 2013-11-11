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

class vB_Upgrade_vbblog extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'vbblog';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'vbblog';

	/**
	* Can we install the blog product?
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
		$this->product = new vB_Upgrade_Product($registry, $phrase['vbphrase'], true, $this->caller);
		if ($this->caninstall = (($this->productresult =  $this->product->verify_install('vbblog')) === true))
		{
			if ($this->product->productinfo['version'] != $this->maxversion)
			{
				$this->caninstall = false;
				$this->productresult = sprintf($this->phrase['final']['mismatch_product_version'], $this->product->productinfo['version'], $this->maxversion);
			}
		}
	}

	/**
	 *	Verify if product upgrade step needs to be executed
	 *
	 * @param	string	version string
	 * @param	bool	Allow check to succeed if version is the same, only used to force a step to run even if that version is already installed
	 */
	private function verify_product_version($version, $allow_same_version = false)
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return false;
		}

		if (
			!$this->product->installed_version
				OR
			$this->is_newer_version($this->product->installed_version, $version)
		)
		{
			if ($allow_same_version AND $this->product->installed_version == $version)
			{
				return true;
			}
			
			$this->skip_message();
			return false;
		}

		return true;
	}

	/**
	* Step #1 - Install New Blog
	* All schema changes need to be reflected here as a new install only executes this step
	*
	*/
	function step_1()
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return;
		}

		if ($this->product->installed_version)
		{
			$this->skip_message();
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog"),
			"CREATE TABLE " . TABLE_PREFIX . "blog (
				blogid INT UNSIGNED NOT NULL auto_increment,
				firstblogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				comments_visible INT UNSIGNED NOT NULL DEFAULT '0',
				comments_moderation INT UNSIGNED NOT NULL DEFAULT '0',
				comments_deleted INT UNSIGNED NOT NULL DEFAULT '0',
				attach INT UNSIGNED NOT NULL DEFAULT '0',
				pending INT UNSIGNED NOT NULL DEFAULT '0',
				state ENUM('moderation','draft','visible','deleted') NOT NULL DEFAULT 'visible',
				views INT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				title VARCHAR(255) NOT NULL DEFAULT '',
				trackback_visible INT UNSIGNED NOT NULL DEFAULT '0',
				trackback_moderation INT UNSIGNED NOT NULL DEFAULT '0',
				options INT UNSIGNED NOT NULL DEFAULT '0',
				lastcomment INT UNSIGNED NOT NULL DEFAULT '0',
				lastblogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				lastcommenter VARCHAR(100) NOT NULL DEFAULT '',
				ratingnum INT UNSIGNED NOT NULL DEFAULT '0',
				ratingtotal INT UNSIGNED NOT NULL DEFAULT '0',
				rating FLOAT UNSIGNED NOT NULL DEFAULT '0',
				categories MEDIUMTEXT,
				taglist MEDIUMTEXT,
				postedby_userid INT UNSIGNED NOT NULL DEFAULT '0',
				postedby_username VARCHAR(100) NOT NULL DEFAULT '',
				PRIMARY KEY  (blogid),
				KEY dateline (dateline, userid, state),
				KEY state (state,dateline),
				KEY userid (userid, dateline, state),
				KEY rating (ratingnum, rating),
				KEY pending (pending, dateline)
			) ENGINE=MyISAM",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_attachmentlegacy"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_attachmentlegacy (
				oldattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				newattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (oldattachmentid, newattachmentid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_category"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_category (
				blogcategoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '',
				description VARCHAR(255) NOT NULL DEFAULT '',
				parentlist MEDIUMTEXT,
				childlist MEDIUMTEXT,
				parentid INT UNSIGNED NOT NULL DEFAULT '0',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				entrycount INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (blogcategoryid),
				KEY userid (userid, displayorder)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_categorypermission"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_categorypermission (
			  categorypermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			  blogcategoryid INT UNSIGNED NOT NULL DEFAULT '0',
			  usergroupid INT UNSIGNED NOT NULL DEFAULT '0',
			  categorypermissions INT UNSIGNED NOT NULL DEFAULT '0',
			  PRIMARY KEY (categorypermissionid),
			  UNIQUE KEY usergroupid (usergroupid, blogcategoryid),
			  KEY blogcategoryid (blogcategoryid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_categoryuser"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_categoryuser (
				blogcategoryid INT UNSIGNED NOT NULL DEFAULT '0',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (blogid,userid,blogcategoryid),
				KEY userid (userid,blogcategoryid),
				KEY blogcategoryid (blogcategoryid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_deletionlog"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_deletionlog (
				primaryid INT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('blogid','blogtextid') NOT NULL DEFAULT 'blogid',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				reason VARCHAR(125) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				moddelete SMALLINT UNSIGNED NOT NULL DEFAULT '1',
				PRIMARY KEY  (primaryid,type),
				KEY type (type,dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_editlog"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_editlog (
				blogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				reason VARCHAR(200) NOT NULL DEFAULT '',
				PRIMARY KEY (blogtextid)
		   )",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_featured"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_featured (
				featureid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				type ENUM( 'random', 'latest', 'specific' ) NOT NULL DEFAULT 'random',
				pusergroupid INT UNSIGNED NOT NULL DEFAULT '0',
				susergroupid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				start INT UNSIGNED NOT NULL DEFAULT '0',
				end INT UNSIGNED NOT NULL DEFAULT '0',
				timespan ENUM( 'day', 'week', 'month', 'year', 'all' ) NOT NULL DEFAULT 'all',
				refresh ENUM('60', '600', '1800', '3600', '21600', '43200', '86400' ) NOT NULL DEFAULT '3600',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				bbcode SMALLINT UNSIGNED NOT NULL DEFAULT '1',
				KEY displayorder (displayorder)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_groupmembership"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_groupmembership (
			  bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
			  userid INT UNSIGNED NOT NULL DEFAULT '0',
			  permissions INT UNSIGNED NOT NULL DEFAULT '0',
			  state enum('active','pending','ignored') NOT NULL DEFAULT 'pending',
			  dateline INT UNSIGNED NOT NULL DEFAULT '0',
			  PRIMARY KEY  (bloguserid,userid),
			  KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_grouppermission"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_grouppermission (
				bloguserid INT UNSIGNED NOT NULL,
				userid INT UNSIGNED NOT NULL,
				permissions INT UNSIGNED NOT NULL,
				state ENUM('active','pending','ignored') NOT NULL DEFAULT 'pending',
				dateline INT UNSIGNED NOT NULL,
				PRIMARY KEY (bloguserid,userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_hash"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_hash (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				blogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				dupehash VARCHAR(32) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				KEY userid (userid,dupehash),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_moderation"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_moderation (
				primaryid INT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('blogid','blogtextid','blogtrackbackid') NOT NULL DEFAULT 'blogid',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (primaryid,type),
				KEY type (type,dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_moderator"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_moderator (
				blogmoderatorid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				permissions INT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('normal', 'super') NOT NULL DEFAULT 'normal',
				PRIMARY KEY  (blogmoderatorid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_pinghistory"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_pinghistory (
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				sourcemd5 CHAR(32) NOT NULL DEFAULT '',
				sourceurl VARCHAR(255) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (blogid, sourcemd5)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_rate"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_rate (
				blograteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				vote INT UNSIGNED NOT NULL DEFAULT '0',
				ipaddress VARCHAR(15) NOT NULL DEFAULT '',
				PRIMARY KEY (blograteid),
				KEY blogid (blogid, userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_read"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_read (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				readtime INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (userid,blogid),
				KEY readtime (readtime)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_relationship"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_relationship (
				userid INT UNSIGNED NOT NULL,
				relationshipid INT UNSIGNED NOT NULL,
				type enum('buddy','ignore') NOT NULL DEFAULT 'buddy',
				PRIMARY KEY  (userid, relationshipid, type),
				KEY relationshipid (relationshipid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_search"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_search (
				blogsearchid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				searchuserid INT UNSIGNED NOT NULL DEFAULT '0',
				ipaddress VARCHAR(15) NOT NULL DEFAULT '',
				criteria TEXT NOT NULL DEFAULT '',
				sortby VARCHAR(15) NOT NULL DEFAULT '',
				sortorder ENUM('asc','desc') NOT NULL DEFAULT 'asc',
				searchtime INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				resultcount SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				completed SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (blogsearchid, completed),
				KEY userid (userid),
				KEY ipaddress (ipaddress)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_searchresult"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_searchresult (
				blogsearchid INT UNSIGNED NOT NULL DEFAULT '0',
				id INT UNSIGNED NOT NULL DEFAULT '0',
				offset INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (blogsearchid,offset)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_sitemapconf"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_sitemapconf (
				name VARCHAR(50) NOT NULL DEFAULT '',
				value VARCHAR(100) NOT NULL DEFAULT '',
				weight DECIMAL(5,3) NOT NULL DEFAULT 0,
				UNIQUE KEY (name, value)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_custom_block"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_custom_block (
				customblockid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '0',
				pagetext MEDIUMTEXT,
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('block', 'page') NOT NULL DEFAULT 'block',
				location ENUM('none', 'side', 'top') NOT NULL DEFAULT 'none',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (customblockid),
				KEY userid (userid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_custom_block_parsed"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_custom_block_parsed (
		  	userid INT UNSIGNED NOT NULL DEFAULT '0',
		  	styleid INT UNSIGNED NOT NULL DEFAULT '0',
		  	languageid INT UNSIGNED NOT NULL DEFAULT '0',
		  	blocktext MEDIUMTEXT,
		  	PRIMARY KEY  (userid, styleid, languageid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_summarystats"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_summarystats (
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				users INT UNSIGNED NOT NULL DEFAULT '0',
				comments INT UNSIGNED NOT NULL DEFAULT '0',
				entries INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_subscribeentry"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_subscribeentry (
				blogsubscribeentryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('usercp', 'email') NOT NULL DEFAULT 'usercp',
				PRIMARY KEY  (blogsubscribeentryid),
				UNIQUE KEY blogid (blogid, userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_subscribeuser"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_subscribeuser (
				blogsubscribeuserid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('usercp', 'email') NOT NULL DEFAULT 'usercp',
				PRIMARY KEY  (blogsubscribeuserid),
				UNIQUE KEY bloguserid (bloguserid, userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_tachyentry"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_tachyentry (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				lastcomment INT UNSIGNED NOT NULL DEFAULT '0',
				lastcommenter VARCHAR(100) NOT NULL DEFAULT '',
				lastblogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (userid,blogid),
				KEY blogid (blogid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_text"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_text (
				blogtextid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				pagetext MEDIUMTEXT NOT NULL,
				title VARCHAR(255) NOT NULL DEFAULT '',
				state ENUM('moderation','visible','deleted') NOT NULL DEFAULT 'visible',
				allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				htmlstate ENUM('off', 'on', 'on_nl2br') NOT NULL DEFAULT 'on_nl2br',
				PRIMARY KEY  (blogtextid),
				KEY blogid (blogid,dateline),
				KEY userid (userid),
				KEY dateline (dateline),
				KEY bloguserid (bloguserid, dateline),
				FULLTEXT KEY title (title,pagetext),
				FULLTEXT KEY title_2 (title)
			) ENGINE=MyISAM",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_textparsed"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_textparsed (
				blogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				hasimages SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				pagetexthtml MEDIUMTEXT NOT NULL,
				styleid INT UNSIGNED NOT NULL DEFAULT '0',
				languageid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (blogtextid,styleid,languageid),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_trackback"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_trackback (
				blogtrackbackid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '',
				snippet VARCHAR(255) NOT NULL DEFAULT '',
				url VARCHAR(255) NOT NULL DEFAULT '',
				state ENUM('moderation','visible') NOT NULL DEFAULT 'visible',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (blogtrackbackid),
				KEY blogid (blogid,state,dateline),
				KEY userid (userid,state,dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_trackbacklog"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_trackbacklog (
				trackbacklogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				system ENUM('none','trackback','pingback') DEFAULT 'none' NOT NULL,
				type ENUM('in','out') NOT NULL DEFAULT 'in',
				status SMALLINT UNSIGNED NOT NULL default '0',
				message MEDIUMTEXT NOT NULL,
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
				url VARCHAR(255) NOT NULL,
				PRIMARY KEY (trackbacklogid),
				KEY blogid (blogid),
				KEY userid (userid),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_user"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_user (
				bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '',
				description MEDIUMTEXT NOT NULL,
				allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '1',
				options INT UNSIGNED NOT NULL DEFAULT '0',
				viewoption ENUM('all','only','except') NOT NULL DEFAULT 'all',
				comments INT UNSIGNED NOT NULL DEFAULT '0',
				lastblog INT UNSIGNED NOT NULL DEFAULT '0',
				lastblogid INT UNSIGNED NOT NULL DEFAULT '0',
				lastblogtitle VARCHAR(255) NOT NULL DEFAULT '',
				lastcomment INT UNSIGNED NOT NULL DEFAULT '0',
				lastcommenter VARCHAR(100) NOT NULL DEFAULT '',
				lastblogtextid INT UNSIGNED NOT NULL DEFAULT '0',
				entries INT UNSIGNED NOT NULL DEFAULT '0',
				deleted INT UNSIGNED NOT NULL DEFAULT '0',
				moderation INT UNSIGNED NOT NULL DEFAULT '0',
				draft INT UNSIGNED NOT NULL DEFAULT '0',
				pending INT UNSIGNED NOT NULL DEFAULT '0',
				ratingnum INT UNSIGNED NOT NULL DEFAULT '0',
				ratingtotal INT UNSIGNED NOT NULL DEFAULT '0',
				rating FLOAT UNSIGNED NOT NULL DEFAULT '0',
				subscribeown ENUM('none', 'usercp', 'email') NOT NULL DEFAULT 'none',
				subscribeothers ENUM('none', 'usercp', 'email') NOT NULL DEFAULT 'none',
				uncatentries INT UNSIGNED NOT NULL DEFAULT '0',
				akismet_key VARCHAR(15) NOT NULL DEFAULT '',
				options_member INT UNSIGNED NOT NULL DEFAULT '0',
				options_guest INT UNSIGNED NOT NULL DEFAULT '0',
				options_buddy INT UNSIGNED NOT NULL DEFAULT '0',
				options_ignore INT UNSIGNED NOT NULL DEFAULT '0',
				isblogmoderator SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				comments_moderation INT UNSIGNED NOT NULL DEFAULT '0',
				comments_deleted INT UNSIGNED NOT NULL DEFAULT '0',
				categorycache MEDIUMTEXT,
				tagcloud MEDIUMTEXT,
				sidebar MEDIUMTEXT,
				custompages MEDIUMTEXT,
				customblocks INT UNSIGNED NOT NULL DEFAULT '0',
				memberids MEDIUMTEXT,
				memberblogids MEDIUMTEXT,
				PRIMARY KEY  (bloguserid),
				KEY lastblog (lastblog, entries),
				KEY ratingnum (ratingnum, rating)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_usercss"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_usercss (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				selector VARCHAR(30) NOT NULL DEFAULT '',
				property VARCHAR(30) NOT NULL DEFAULT '',
				value VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (userid, selector, property),
				KEY property (property, userid, value(20))
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_usercsscache"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_usercsscache (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				cachedcss MEDIUMTEXT,
				csscolors MEDIUMTEXT,
				buildpermissions INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_userread"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_userread (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
				readtime INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (userid,bloguserid),
				KEY readtime (readtime)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_userstats"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_userstats (
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				users INT UNSIGNED NOT NULL DEFAULT '0',
				comments INT UNSIGNED NOT NULL DEFAULT '0',
				entries INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid, dateline),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_views"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_views (
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				KEY blogid (blogid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_visitor"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_visitor (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				visitorid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				visible INT UNSIGNED NOT NULL DEFAULT '0',
				day SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (visitorid, userid, day),
				KEY userid (userid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->add_cronjob(
			array(
				'varname'  => 'blog_pending',
				'nextrun'  => 1178750700,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:4:{i:0;i:0;i:1;i:15;i:2;i:30;i:3;i:45;}',
				'filename' => './includes/cron/blog_pending.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbblog'
			)
		);

		$this->add_cronjob(
			array(
				'varname'  => 'blog_cleanup',
				'nextrun'  => 1178753400,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:1:{i:0;i:30;}',
				'filename' => './includes/cron/blog_cleanup.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbblog'
			)
		);

		$this->add_cronjob(
			array(
				'varname'  => 'blog_views',
				'nextrun'  => 1178753700,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:1:{i:0;i:35;}',
				'filename' => './includes/cron/blog_views.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbblog'
			)
		);

		$this->add_cronjob(
			array(
				'varname'  => 'blog_dailycleanup',
				'nextrun'  => 1178754000,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => 3,
				'minute'   => 'a:1:{i:0;i:0;}',
				'filename' => './includes/cron/blog_dailycleanup.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbblog'
			)
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "datastore"),
			"DELETE FROM " . TABLE_PREFIX . "datastore WHERE title IN ('blogstats', 'blogfeatured')"
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 2),
			'user',
			'bloggroupreqcount',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
			'user',
			'showblogcss',
			'int',
			array('null' => false, 'default' => 1)
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 5),
			'usergroup',
			'vbblog_general_permissions',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 2, 5),
			'usergroup',
			'vbblog_entry_permissions',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 3, 5),
			'usergroup',
			'vbblog_comment_permissions',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 4, 5),
			'usergroup',
			'vbblog_customblocks',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 5, 5),
			'usergroup',
			'vbblog_custompages',
			'int',
			self::FIELD_DEFAULTS
		);

		for ($x = 1; $x < 6; $x++)
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', $x, 9),
				'moderatorlog',
				"id$x",
				'int',
				self::FIELD_DEFAULTS
			);
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 6, 9),
			'moderatorlog',
			'product',
			'varchar',
			array(
				'length'     => 25,
				'attributes' => self::FIELD_DEFAULTS
		));

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 7, 9),
			'moderatorlog',
			'product',
			'product'
		);

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 8, 9),
			'moderatorlog',
			'id1',
			'id1'
		);

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 9, 9),
			'moderatorlog',
			'id2',
			'id2'
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'administrator', 1, 1),
			'administrator',
			'blogpermissions',
			'int',
			self::FIELD_DEFAULTS
		);

		// now lets setup decent user group permissions. Come up with a default set to apply
		// given the group's specific settings:
		// Admins and smods = adminmod; new thread posters = poster; can view = viewer
		$perm_set = array(
			'adminmod' => array(
				'vbblog_general_permissions' => 262142,
				'vbblog_entry_permissions'   => 24575,
				'vbblog_comment_permissions' => 990,
				'vbblog_customblocks'        => 10,
				'vbblog_custompages'         => 10,
			),
			'poster' => array(
				'vbblog_general_permissions' => 257022,
				'vbblog_entry_permissions'   => 8191,
				'vbblog_comment_permissions' => 974,
				'vbblog_customblocks'        => 5,
				'vbblog_custompages'         => 5,
			),
			'viewer' => array(
				'vbblog_general_permissions' => 368,
				'vbblog_entry_permissions'   => 834,
				'vbblog_comment_permissions' => 960,
				'vbblog_customblocks'        => 0,
				'vbblog_custompages'         => 0,
			),
			'none' => array(
				'vbblog_general_permissions' => 0,
				'vbblog_entry_permissions'   => 0,
				'vbblog_comment_permissions' => 0,
				'vbblog_customblocks'        => 0,
				'vbblog_custompages'         => 0,
			)
		);

		$usergroups = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "usergroup
		");
		while ($usergroup = $this->db->fetch_array($usergroups))
		{
			if (intval($usergroup['adminpermissions']) & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				// admin
				$selected_set = $perm_set['adminmod'];
			}
			else if (intval($usergroup['adminpermissions']) & $this->registry->bf_ugp_adminpermissions['ismoderator'])
			{
				// super mod
				$selected_set = $perm_set['adminmod'];
			}
			else if (intval($usergroup['forumpermissions']) & $this->registry->bf_ugp_forumpermissions['canpostnew'])

			{
				// poster
				$selected_set = $perm_set['poster'];
			}
			else if (intval($usergroup['forumpermissions']) & $this->registry->bf_ugp_forumpermissions['canview'])
			{
				// viewer
				$selected_set = $perm_set['viewer'];
			}
			else
			{
				// no perms
				$selected_set = $perm_set['none'];
			}

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup SET
					vbblog_general_permissions = $selected_set[vbblog_general_permissions],
					vbblog_entry_permissions   = $selected_set[vbblog_entry_permissions],
					vbblog_comment_permissions = $selected_set[vbblog_comment_permissions],
					vbblog_customblocks        = $selected_set[vbblog_customblocks],
					vbblog_custompages         = $selected_set[vbblog_custompages]
				WHERE usergroupid = $usergroup[usergroupid]
			");

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET vbblog_general_permissions = vbblog_general_permissions | 262144
				WHERE forumpermissions & 8
			");
		}

		$this->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "package
				(productid, class)
			VALUES
				('vbblog', 'vBBlog')
		");

		$package = $this->db->query_first("
			SELECT package.packageid FROM " . TABLE_PREFIX . "package AS package
			WHERE package.productid = 'vbblog'
		");
		$packageid = $package['packageid'];

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
				 (class, packageid, canplace, cansearch, canattach)
			VALUES
				('BlogEntry', $packageid, '0', '1', '1'),
				('BlogComment', $packageid, '0', '1', '0'),
				('BlogDescription', $packageid, '0', '0', '0'),
				('BlogCustomBlock', $packageid, '0', '0', '0')
		");
	}

	/**
	* Step #2 - Install New Blog
	* Followup for step #1
	*
	*/
	function step_2()
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return;
		}

		if ($this->product->installed_version)
		{
			$this->skip_message();
			return;
		}

		$this->show_message($this->phrase['core']['updating_permissions']);

		require_once(DIR . '/includes/adminfunctions_plugin.php');
		setup_default_admin_permissions('blogpermissions', 3);
	}

	/**
	* Step #3 - Upgrade to Blog 1.0.1
	*
	*/
	function step_3()
	{
		if (!$this->verify_product_version('1.0.1'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_moderator', 1, 1),
			'blog_moderator',
			'type',
			'enum',
			array('attributes' => "('normal', 'super')", 'null' => false, 'default' => 'normal')
		);
	}

	/**
	* Step #4 - Upgrade to Blog 2.0.0 Beta 1
	*
	*/
	function step_4()
	{
		if (!$this->verify_product_version('2.0.0 Beta 1'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_categorypermission"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_categorypermission (
				categorypermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				blogcategoryid INT UNSIGNED NOT NULL DEFAULT '0',
				usergroupid INT UNSIGNED NOT NULL DEFAULT '0',
				categorypermissions INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (categorypermissionid),
				UNIQUE KEY usergroupid (usergroupid, blogcategoryid),
				KEY blogcategoryid (blogcategoryid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_featured"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_featured (
				featureid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				type ENUM( 'random', 'latest', 'specific' ) NOT NULL DEFAULT 'random',
				pusergroupid INT UNSIGNED NOT NULL DEFAULT '0',
				susergroupid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				start INT UNSIGNED NOT NULL DEFAULT '0',
				end INT UNSIGNED NOT NULL DEFAULT '0',
				timespan ENUM( 'day', 'week', 'month', 'year', 'all' ) NOT NULL DEFAULT 'all',
				refresh ENUM('60', '600', '1800', '3600', '21600', '43200', '86400' ) NOT NULL DEFAULT '3600',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				bbcode SMALLINT UNSIGNED NOT NULL DEFAULT '1',
				KEY displayorder (displayorder)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_custom_block"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_custom_block (
				customblockid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '0',
				pagetext MEDIUMTEXT,
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('block', 'page') NOT NULL DEFAULT 'block',
				location ENUM('none', 'side', 'top') NOT NULL DEFAULT 'none',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (customblockid),
				KEY userid (userid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_custom_block_parsed"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_custom_block_parsed (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				styleid INT UNSIGNED NOT NULL DEFAULT '0',
				languageid INT UNSIGNED NOT NULL DEFAULT '0',
				blocktext MEDIUMTEXT,
				PRIMARY KEY  (userid, styleid, languageid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_summarystats"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_summarystats (
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				users INT UNSIGNED NOT NULL DEFAULT '0',
				comments INT UNSIGNED NOT NULL DEFAULT '0',
				entries INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_tag"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_tag (
				tagid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				tagtext VARCHAR(100) NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (tagid),
				UNIQUE KEY (tagtext)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_tagentry"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_tagentry (
				tagid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (tagid, blogid),
				KEY dateline (dateline),
				KEY blogid (blogid, userid),
				KEY bloguserid (bloguserid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_tagsearch"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_tagsearch (
				tagid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				KEY tagid (tagid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_userstats"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_userstats (
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				users INT UNSIGNED NOT NULL DEFAULT '0',
				comments INT UNSIGNED NOT NULL DEFAULT '0',
				entries INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid, dateline),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_visitor"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_visitor (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				visitorid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				visible INT UNSIGNED NOT NULL DEFAULT '0',
				day SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (visitorid, userid, day),
				KEY userid (userid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_visitor"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_visitor (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				visitorid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				visible INT UNSIGNED NOT NULL DEFAULT '0',
				day SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (visitorid, userid, day),
				KEY userid (userid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 1, 9),
			'blog_user',
			'categorycache',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 2, 9),
			'blog_user',
			'tagcloud',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 3, 9),
			'blog_user',
			'sidebar',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 4, 9),
			'blog_user',
			'custompages',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 5, 9),
			'blog_user',
			'options_guest',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 6, 9),
			'blog_user',
			'customblocks',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 7, 9),
			'blog_user',
			'memberids',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 8, 9),
			'blog_user',
			'memberblogids',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		if ($this->field_exists('blog_user', 'options_everyone'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 9, 9),
				"ALTER TABLE " . TABLE_PREFIX . "blog_user CHANGE options_everyone options_member INT UNSIGNED NOT NULL DEFAULT '0'"
			);
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_grouppermission"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_grouppermission (
				bloguserid INT UNSIGNED NOT NULL,
				userid INT UNSIGNED NOT NULL,
				permissions INT UNSIGNED NOT NULL,
				state ENUM('active','pending','ignored') NOT NULL DEFAULT 'pending',
				dateline INT UNSIGNED NOT NULL,
				PRIMARY KEY (bloguserid,userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_groupmembership"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_groupmembership (
				bloguserid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				permissions INT UNSIGNED NOT NULL DEFAULT '0',
				state enum('active','pending','ignored') NOT NULL DEFAULT 'pending',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (bloguserid,userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_usercss"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_usercss (
			userid INT UNSIGNED NOT NULL DEFAULT '0',
			selector VARCHAR(30) NOT NULL DEFAULT '',
			property VARCHAR(30) NOT NULL DEFAULT '',
			value VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY (userid, selector, property),
			KEY property (property, userid, value(20))
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_usercsscache"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_usercsscache (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				cachedcss MEDIUMTEXT,
				buildpermissions INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		if (!$this->field_exists('usergroup', 'vbblog_customblocks'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'blog_user', 1, 1),
				"UPDATE " . TABLE_PREFIX . "blog_user
				SET
					options_guest = options_member,
					memberids = bloguserid,
					memberblogids = bloguserid
			");
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 2),
			'usergroup',
			'vbblog_customblocks',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 2, 2),
			'usergroup',
			'vbblog_custompages',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 1, 6),
			'blog',
			'categories',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 2, 6),
			'blog',
			'taglist',
			'mediumtext',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 3, 6),
			'blog',
			'postedby_userid',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 4, 6),
			'blog',
			'postedby_username',
			'varchar',
			array('length' => 100, 'attributes' => self::FIELD_DEFAULTS)
		);

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 5, 6),
			'blog',
			'postedby_userid',
			'postedby_userid'
		);

		if (!$this->field_exists('blog_deletionlog', 'moddelete'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'blog', 6, 6),
				"UPDATE " . TABLE_PREFIX . "blog
				SET
					postedby_userid = userid,
					postedby_username = username
			");
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_deletionlog', 1, 1),
			'blog_deletionlog',
			'moddelete',
			'smallint',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 2),
			'user',
			'bloggroupreqcount',
			'int',
			self::FIELD_DEFAULTS
		);

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
			'user',
			'showblogcss',
			'int',
			self::FIELD_DEFAULTS
		);

		$featured = @unserialize($this->registry->options['vbblog_featured']);

		$values = array(
			'type'    => 'random',
			'userid'  => 0,
			'start'   => 0,
			'end'     => 0,
			'refresh' => 60,
			'blogid'  => 0,
		);
		$insert = false;

		if ($featured['type'] == 'manual')
		{
			if ($featured['blogid'])
			{
				$values['blogid'] = $featured['blogid'];
				$values['type'] = 'specific';
				$insert = true;
			}
		}
		else if ($featured['type'] == 'random')
		{
			$values['type'] = 'random';
			if ($featured['userid'])
			{
				$values['userid'] = $featured['userid'];
			}
			if ($featured['startstamp'])
			{
				$values['start'] = $featured['startstamp'];
			}
			if ($featured['endstamp'])
			{
				$values['end'] = $featured['endstamp'];
			}
			$values['refresh'] = intval($featured['refresh']);
		}

		if ($insert)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "blog_featured"),
				"INSERT INTO " . TABLE_PREFIX . "blog_featured
				(type, userid, start, end, refresh, bbcode, displayorder, blogid)
				VALUES
				(
					'$values[type]',
					$values[userid],
					$values[start],
					$values[end],
					$values[refresh],
					0,
					1,
					$values[blogid]
				)
			");
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET vbblog_general_permissions = vbblog_general_permissions | 896,
					vbblog_customblocks = 5,
					vbblog_custompages = 5
				WHERE vbblog_entry_permissions & 4
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET vbblog_general_permissions = vbblog_general_permissions | 254208
				WHERE vbblog_general_permissions & 32 OR vbblog_general_permissions & 64
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET vbblog_general_permissions = vbblog_general_permissions | 261120
				WHERE adminpermissions & 1
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET vbblog_entry_permissions = vbblog_entry_permissions & ~387
		");

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET vbblog_entry_permissions = vbblog_entry_permissions |
				IF(vbblog_entry_permissions & 4, 1, 0) |
				IF(vbblog_comment_permissions & 4, 2, 0) |
				IF(vbblog_entry_permissions & 16, 128, 0) |
				IF(vbblog_comment_permissions & 4
					OR vbblog_entry_permissions & 4, 256, 0)
		");

		$this->add_cronjob(
			array(
				'varname'  => 'blog_dailycleanup',
				'nextrun'  => 1178754000,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => 3,
				'minute'   => 'a:1:{i:0;i:0;}',
				'filename' => './includes/cron/blog_dailycleanup.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbblog'
			)
		);
	}

	/**
	* Step #5 - Upgrade to Blog 2.0.0 Beta 1
	*	Followup Step
	*
	*/
	function step_5()
	{
		if (!$this->verify_product_version('2.0.0 Beta 1'))
		{
			return;
		}

		require_once(DIR . '/includes/blog_adminfunctions.php');
		build_featured_entry_datastore();
	}

	/**
	* Step #6 - Upgrade to Blog 2.0.0 Release Candidate 1
	*
	*/
	function step_6()
	{
		if (!$this->verify_product_version('2.0.0 Release Candidate 1'))
		{
			return;
		}

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_text', 1, 1),
			'blog_text',
			'title',
			'title',
			'fulltext'
		);

		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 1, 1),
			'blog',
			'title'
		);
	}

	/**
	* Step #7 - Upgrade to Blog 2.0.1
	*
	*/
	function step_7()
	{
		if (!$this->verify_product_version('2.0.1'))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_usercsscache', 1, 1),
			'blog_usercsscache',
			'csscolors',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #8 - Upgrade to Blog 2.0.2
	*
	*/
	function step_8()
	{
		if (!$this->verify_product_version('2.0.2'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
			"UPDATE " . TABLE_PREFIX . "usergroup
				SET vbblog_general_permissions = vbblog_general_permissions | 262144
				WHERE forumpermissions & 8
		");
	}

	/**
	* Step #9 - Upgrade to Blog 2.1.0
	*
	*/
	function step_9()
	{
		if (!$this->verify_product_version('2.1.0'))
		{
			return;
		}

		$this->registry->db->query_write(
			"INSERT IGNORE INTO " . TABLE_PREFIX . "package
					(productid, class)
				VALUES
					('vbblog', 'vBBlog')
		");

		$packageid = $this->registry->db->query_first("
			SELECT package.packageid FROM " . TABLE_PREFIX . "package AS package
			WHERE package.productid = 'vbblog'
		");
		$packageid = $packageid['packageid'];

		$this->registry->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
				 (class, packageid, canplace, cansearch, canattach)
			VALUES
				('BlogEntry', $packageid, '0', '1', '1'),
				('BlogComment', $packageid, '0', '1', '0')
		");

		if ($this->field_exists('blog_tag', 'tagtext'))
		{
			$contenttypeid = $this->registry->db->query_first("
				SELECT contenttype.contenttypeid FROM "  . TABLE_PREFIX . "contenttype AS contenttype
				WHERE contenttype.class = 'BlogEntry'");
			$contenttypeid = $contenttypeid['contenttypeid'];

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "tag"),
				"INSERT IGNORE INTO " . TABLE_PREFIX . "tag
					(tagtext, dateline)
					SELECT blog_tag.tagtext, blog_tag.dateline
					FROM " . TABLE_PREFIX . "blog_tag AS blog_tag
			");

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "tagcontent"),
				"INSERT IGNORE INTO " . TABLE_PREFIX . "tagcontent
					(tagid, contenttypeid, contentid, userid, dateline)
				SELECT ifnull(canonical.tagid, tag.tagid), $contenttypeid, blog_tagentry.blogid, blog_tagentry.userid, blog_tagentry.dateline
				FROM " . TABLE_PREFIX . "blog_tagentry AS blog_tagentry JOIN " .
					TABLE_PREFIX . "blog_tag AS blog_tag ON blog_tagentry.tagid = blog_tag.tagid JOIN " .
					TABLE_PREFIX . "tag AS tag ON blog_tag.tagtext = tag.tagtext LEFT JOIN " .
		     TABLE_PREFIX . "tag AS canonical ON tag.canonicaltagid = canonical.tagid
			");
		}

		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "blog_tag"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "blog_tag"
		);

		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "blog_tagentry"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "blog_tagentry"
		);

		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "blog_tagsearch"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "blog_tagsearch"
		);

		$this->add_adminmessage(
			'after_upgrade_40_update_blog_attachment',
			array(
				'dismissable' => 1,
				'script'      => 'blog_admin.php',
				'action'      => 'updateattachments',
				'execurl'     => 'blog_admin.php?do=updateattachments&pp=25',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}

	/**
	* Step #10 - Upgrade to Blog 4.0.0 Release Candidate 2
	*
	*/
	function step_10()
	{
		if (!$this->verify_product_version('4.0.0 Release Candidate 2'))
		{
			return;
		}

		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'blog', 1, 1),
			'blog',
			'lastcomment',
			'lastcomment'
		);
	}

	/**
	* Step #11 - Upgrade to Blog 4.0.2
	*
	*/
	function step_11()
	{
		if (!$this->verify_product_version('4.0.2'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_attachmentlegacy"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_attachmentlegacy (
				oldattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				newattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (oldattachmentid, newattachmentid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #12 - Upgrade to Blog 4.0.3
	*
	*/
	function step_12()
	{
		if (!$this->verify_product_version('4.0.3'))
		{
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_attachmentlegacy"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_attachmentlegacy (
				oldattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				newattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (oldattachmentid, newattachmentid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #13 - Upgrade to Blog 4.1.0
	*
	*/
	function step_13()
	{
		if (!$this->verify_product_version('4.1.0', true))
		{
			return;
		}

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'blog_text', 1, 1),
			'blog_text',
			'htmlstate',
			'enum',
			array('attributes' => "('off', 'on', 'on_nl2br')", 'null' => false, 'default' => 'on_nl2br')
		);

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "blog_sitemapconf"),
			"CREATE TABLE " . TABLE_PREFIX . "blog_sitemapconf (
				name VARCHAR(50) NOT NULL DEFAULT '',
				value VARCHAR(100) NOT NULL DEFAULT '',
				weight DECIMAL(5,3) NOT NULL DEFAULT 0,
				UNIQUE KEY (name, value)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #14 - Upgrade to Blog 4.1.4
	*
	*/
	function step_14()
	{
		if (!$this->verify_product_version('4.1.4'))
		{
			return;
		}

		$this->add_contenttype('vbblog', 'vBBlog', 'BlogDescription');
		$this->add_contenttype('vbblog', 'vBBlog', 'BlogCustomBlock');
	}

	/**
	* Step #15 - Final Step
	*	This must always be the last step. Just renumber this step as more upgrade steps are added before
	*
	*/
	function step_15()
	{
		if ($this->caninstall)
		{
			$result = $this->product->post_install();
			if (!is_array($result))
			{
				$this->add_error($result, self::PHP_TRIGGER_ERROR, true);
				return false;
			}
			$this->show_message($this->phrase['final']['product_installed']);
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
