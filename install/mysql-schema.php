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

define('SCHEMA', 'mysql');

if (!is_object($db))
{
	die('<strong>MySQL Schema</strong>: $db is not an instance of the vB Database class. This script requires the escape_string() method from the vB Database class.');
}

require_once(DIR . '/install/functions_installupgrade.php');

$tabletype = 'MEMORY';
$hightrafficengine = get_high_concurrency_table_engine($db);

$phrasegroups = array();
$specialtemplates = array();

// Check userfield table is still used and how long the default length should be

$schema['CREATE']['query']['access'] = "
CREATE TABLE " . TABLE_PREFIX . "access (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	accessmask SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY userid (userid, forumid)
)
";
$schema['CREATE']['explain']['access'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "access");



$schema['CREATE']['query']['ad'] = "
CREATE TABLE " . TABLE_PREFIX . "ad (
	adid INT UNSIGNED NOT NULL auto_increment,
	title VARCHAR(250) NOT NULL DEFAULT '',
	adlocation VARCHAR(250) NOT NULL DEFAULT '',
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	snippet MEDIUMTEXT,
	PRIMARY KEY (adid),
	KEY active (active)
)
";
$schema['CREATE']['explain']['ad'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "ad");



$schema['CREATE']['query']['adcriteria'] = "
CREATE TABLE " . TABLE_PREFIX . "adcriteria (
	adid INT UNSIGNED NOT NULL DEFAULT '0',
	criteriaid VARCHAR(250) NOT NULL DEFAULT '',
	condition1 VARCHAR(250) NOT NULL DEFAULT '',
	condition2 VARCHAR(250) NOT NULL DEFAULT '',
	condition3 VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (adid,criteriaid)
)
";
$schema['CREATE']['explain']['adcriteria'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adcriteria");



$schema['CREATE']['query']['adminhelp'] = "
CREATE TABLE " . TABLE_PREFIX . "adminhelp (
	adminhelpid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	script VARCHAR(50) NOT NULL DEFAULT '',
	action VARCHAR(25) NOT NULL DEFAULT '',
	optionname VARCHAR(100) NOT NULL DEFAULT '',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	product VARCHAR(25) NOT NULL DEFAULT '',
	PRIMARY KEY (adminhelpid),
	UNIQUE KEY phraseunique (script, action, optionname)
)
";
$schema['CREATE']['explain']['adminhelp'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminhelp");



$schema['CREATE']['query']['administrator'] = "
CREATE TABLE " . TABLE_PREFIX . "administrator (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	adminpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	navprefs MEDIUMTEXT,
	cssprefs VARCHAR(250) NOT NULL DEFAULT '',
	notes MEDIUMTEXT,
	dismissednews TEXT,
	languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['administrator'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "administrator");



$schema['CREATE']['query']['adminlog'] = "
CREATE TABLE " . TABLE_PREFIX . "adminlog (
	adminlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	script VARCHAR(50) NOT NULL DEFAULT '',
	action VARCHAR(20) NOT NULL DEFAULT '',
	extrainfo VARCHAR(200) NOT NULL DEFAULT '',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	PRIMARY KEY (adminlogid),
	KEY script_action (script, action)
)
";
$schema['CREATE']['explain']['adminlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminlog");



$schema['CREATE']['query']['adminmessage'] = "
CREATE TABLE " . TABLE_PREFIX . "adminmessage (
	adminmessageid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	varname varchar(250) NOT NULL DEFAULT '',
	dismissable SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	script varchar(50) NOT NULL DEFAULT '',
	action varchar(20) NOT NULL DEFAULT '',
	execurl MEDIUMTEXT,
	method enum('get','post') NOT NULL DEFAULT 'post',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	status enum('undone','done','dismissed') NOT NULL default 'undone',
	statususerid INT UNSIGNED NOT NULL DEFAULT '0',
	args MEDIUMTEXT,
	PRIMARY KEY (adminmessageid),
	KEY script_action (script, action),
	KEY varname (varname)
)
";
$schema['CREATE']['explain']['adminmessage'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminmessage");



$schema['CREATE']['query']['adminutil'] = "
CREATE TABLE " . TABLE_PREFIX . "adminutil (
	title VARCHAR(50) NOT NULL DEFAULT '',
	text MEDIUMTEXT,
	PRIMARY KEY (title)
)
";
$schema['CREATE']['explain']['adminutil'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminutil");



$schema['CREATE']['query']['album'] = "
CREATE TABLE " . TABLE_PREFIX . "album (
	albumid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	createdate INT UNSIGNED NOT NULL DEFAULT '0',
	lastpicturedate INT UNSIGNED NOT NULL DEFAULT '0',
	visible INT UNSIGNED NOT NULL DEFAULT '0',
	moderation INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	description MEDIUMTEXT,
	state ENUM('public', 'private', 'profile') NOT NULL DEFAULT 'public',
	coverattachmentid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (albumid),
	KEY userid (userid, lastpicturedate)
)
";
$schema['CREATE']['explain']['album'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "album");



$schema['CREATE']['query']['albumupdate'] = "
CREATE TABLE " . TABLE_PREFIX . "albumupdate (
	albumid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (albumid)
)
";
$schema['CREATE']['explain']['albumupdate'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "albumupdate");



$schema['CREATE']['query']['announcement'] = "
CREATE TABLE " . TABLE_PREFIX . "announcement (
	announcementid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	startdate INT UNSIGNED NOT NULL DEFAULT '0',
	enddate INT UNSIGNED NOT NULL DEFAULT '0',
	pagetext MEDIUMTEXT,
	forumid SMALLINT NOT NULL DEFAULT '0',
	views INT UNSIGNED NOT NULL DEFAULT '0',
	announcementoptions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (announcementid),
	KEY forumid (forumid),
	KEY startdate (enddate, forumid, startdate)
)
";
$schema['CREATE']['explain']['announcement'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "announcement");



$schema['CREATE']['query']['announcementread'] = "
CREATE TABLE " . TABLE_PREFIX . "announcementread (
	announcementid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY  (announcementid,userid),
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['announcementread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "announcementread");



$schema['CREATE']['query']['attachment'] = "
CREATE TABLE " . TABLE_PREFIX . "attachment (
	attachmentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	contenttypeid INT UNSIGNED NOT NULL DEFAULT '0',
	contentid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filedataid INT UNSIGNED NOT NULL DEFAULT '0',
	state ENUM('visible', 'moderation') NOT NULL DEFAULT 'visible',
	counter INT UNSIGNED NOT NULL DEFAULT '0',
	posthash VARCHAR(32) NOT NULL DEFAULT '',
	filename VARCHAR(255) NOT NULL DEFAULT '',
	caption TEXT,
	reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	settings MEDIUMTEXT,
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (attachmentid),
	KEY contenttypeid (contenttypeid, contentid, attachmentid),
	KEY contentid (contentid),
	KEY userid (userid, contenttypeid),
	KEY posthash (posthash, userid),
	KEY filedataid (filedataid, userid)
)
";
$schema['CREATE']['explain']['attachment'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachment");



$schema['CREATE']['query']['apiclient'] = "
CREATE TABLE " . TABLE_PREFIX . "apiclient (
	apiclientid INT UNSIGNED NOT NULL auto_increment,
	secret VARCHAR(32) NOT NULL DEFAULT '',
	apiaccesstoken VARCHAR(32) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	clienthash VARCHAR(32) NOT NULL DEFAULT '',
	clientname VARCHAR(250) NOT NULL DEFAULT '',
	clientversion VARCHAR(50) NOT NULL DEFAULT '',
	platformname VARCHAR(250) NOT NULL DEFAULT '',
	platformversion VARCHAR(50) NOT NULL DEFAULT '',
	uniqueid VARCHAR(250) NOT NULL DEFAULT '',
	initialipaddress VARCHAR(15) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL,
	lastactivity INT UNSIGNED NOT NULL,
	PRIMARY KEY  (apiclientid),
	KEY clienthash (uniqueid)
)
";
$schema['CREATE']['explain']['apiclient'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "apiclient");



$schema['CREATE']['query']['apilog'] = "
CREATE TABLE " . TABLE_PREFIX . "apilog (
	apilogid INT UNSIGNED NOT NULL auto_increment,
	apiclientid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	method VARCHAR(32) NOT NULL DEFAULT '',
	paramget MEDIUMTEXT,
	parampost MEDIUMTEXT,
	ipaddress VARCHAR(15) NOT NULL DEFAULT '',
	PRIMARY KEY  (apilogid),
	KEY apiclientid (apiclientid, method, dateline)
)
";
$schema['CREATE']['explain']['apilog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "apilog");



$schema['CREATE']['query']['apipost'] = "
CREATE TABLE " . TABLE_PREFIX . "apipost (
  apipostid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  userid INT UNSIGNED NOT NULL DEFAULT '0',
  contenttypeid INT UNSIGNED NOT NULL DEFAULT '0',
  contentid INT UNSIGNED NOT NULL DEFAULT '0',
  clientname VARCHAR(250) NOT NULL DEFAULT '',
  clientversion VARCHAR(50) NOT NULL DEFAULT '',
  platformname VARCHAR(250) NOT NULL DEFAULT '',
  platformversion VARCHAR(50) NOT NULL DEFAULT '',
  PRIMARY KEY (apipostid),
  KEY contenttypeid (contenttypeid, contentid)
)
";
$schema['CREATE']['explain']['apipost'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "apipost");



$schema['CREATE']['query']['attachmentcategory'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmentcategory (
	categoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(255) NOT NULL DEFAULT '',
	parentid INT UNSIGNED NOT NULL DEFAULT '0',
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (categoryid),
	KEY userid (userid, parentid, displayorder)
)
";
$schema['CREATE']['explain']['attachmentcategory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentcategory");



$schema['CREATE']['query']['attachmentcategoryuser'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmentcategoryuser (
	filedataid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	categoryid INT UNSIGNED NOT NULL DEFAULT '0',
	filename VARCHAR(255) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (filedataid, userid),
	KEY categoryid (categoryid, userid, filedataid),
	KEY userid (userid, categoryid, dateline)
)
";
$schema['CREATE']['explain']['attachmentcategoryuser'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentcategoryuser");



$schema['CREATE']['query']['attachmentpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmentpermission (
	attachmentpermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	extension VARCHAR(20) BINARY NOT NULL DEFAULT '',
	usergroupid INT UNSIGNED NOT NULL DEFAULT '0',
	size INT UNSIGNED NOT NULL DEFAULT '0',
	width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	attachmentpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY  (attachmentpermissionid),
	UNIQUE KEY extension (extension, usergroupid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['attachmentpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentpermission");



$schema['CREATE']['query']['attachmenttype'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmenttype (
	extension CHAR(20) BINARY NOT NULL DEFAULT '',
	mimetype VARCHAR(255) NOT NULL DEFAULT '',
	size INT UNSIGNED NOT NULL DEFAULT '0',
	width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	display SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	contenttypes MEDIUMTEXT,
	PRIMARY KEY (extension)
)
";
$schema['CREATE']['explain']['attachmenttype'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmenttype");



$schema['CREATE']['query']['attachmentviews'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmentviews (
	attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
	KEY postid (attachmentid)
)
";
$schema['CREATE']['explain']['attachmentviews'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentviews");



$schema['CREATE']['query']['avatar'] = "
CREATE TABLE " . TABLE_PREFIX . "avatar (
	avatarid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(100) NOT NULL DEFAULT '',
	minimumposts INT UNSIGNED NOT NULL DEFAULT '0',
	avatarpath VARCHAR(100) NOT NULL DEFAULT '',
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (avatarid)
)
";
$schema['CREATE']['explain']['avatar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "avatar");



$schema['CREATE']['query']['autosave'] = "
CREATE TABLE " . TABLE_PREFIX . "autosave (
	contenttypeid VARBINARY(100) NOT NULL DEFAULT '',
	parentcontentid INT UNSIGNED NOT NULL DEFAULT '0',
	contentid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	pagetext MEDIUMTEXT,
	title MEDIUMTEXT,
	posthash CHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (contentid, parentcontentid, contenttypeid, userid),
	KEY userid (userid),
	KEY contenttypeid (contenttypeid, userid)
)
";
$schema['CREATE']['explain']['autosave'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "autosave");



$schema['CREATE']['query']['bbcode'] = "
CREATE TABLE " . TABLE_PREFIX . "bbcode (
	bbcodeid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	bbcodetag VARCHAR(200) NOT NULL DEFAULT '',
	bbcodereplacement MEDIUMTEXT,
	bbcodeexample VARCHAR(200) NOT NULL DEFAULT '',
	bbcodeexplanation MEDIUMTEXT,
	twoparams SMALLINT NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	buttonimage VARCHAR(250) NOT NULL DEFAULT '',
	options INT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (bbcodeid),
	UNIQUE KEY uniquetag (bbcodetag, twoparams)
)
";
$schema['CREATE']['explain']['bbcode'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "bbcode");



$schema['CREATE']['query']['bbcode_video'] = "
CREATE TABLE " . TABLE_PREFIX . "bbcode_video (
  providerid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tagoption VARCHAR(50) NOT NULL DEFAULT '',
  provider VARCHAR(50) NOT NULL DEFAULT '',
  url VARCHAR(100) NOT NULL DEFAULT '',
  regex_url VARCHAR(254) NOT NULL DEFAULT '',
  regex_scrape VARCHAR(254) NOT NULL DEFAULT '',
  embed MEDIUMTEXT,
  priority INT UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (providerid),
  UNIQUE KEY tagoption (tagoption),
  KEY priority (priority),
  KEY provider (provider)
)
";
$schema['CREATE']['explain']['bbcode_video'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "bbcode_video");



$schema['CREATE']['query']['block'] = "
CREATE TABLE " . TABLE_PREFIX . "block (
  blockid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  blocktypeid INT NOT NULL DEFAULT '0',
  title VARCHAR(255) NOT NULL DEFAULT '',
  description MEDIUMTEXT,
  url VARCHAR(100) NOT NULL DEFAULT '',
  cachettl INT NOT NULL DEFAULT '0',
  displayorder SMALLINT NOT NULL DEFAULT '0',
  active SMALLINT NOT NULL DEFAULT '0',
  configcache MEDIUMBLOB,
  PRIMARY KEY (blockid),
  KEY blocktypeid (blocktypeid)
)
";
$schema['CREATE']['explain']['block'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "block");



$schema['CREATE']['query']['blockconfig'] = "
CREATE TABLE " . TABLE_PREFIX . "blockconfig (
  blockid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL DEFAULT '',
  value MEDIUMTEXT,
  serialized TINYINT NOT NULL DEFAULT '0',
  PRIMARY KEY (blockid, name)
)
";
$schema['CREATE']['explain']['blockconfig'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "blockconfig");



$schema['CREATE']['query']['blocktype'] = "
CREATE TABLE " . TABLE_PREFIX . "blocktype (
  blocktypeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  productid VARCHAR(25) NOT NULL DEFAULT '',
  name VARCHAR(50) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL DEFAULT '',
  description MEDIUMTEXT,
  allowcache TINYINT NOT NULL DEFAULT '0',
  PRIMARY KEY (blocktypeid),
  UNIQUE KEY (name),
  KEY productid (productid)
)
";
$schema['CREATE']['explain']['blocktype'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "blocktype");



$schema['CREATE']['query']['bookmarksite'] = "
CREATE TABLE " . TABLE_PREFIX . "bookmarksite (
	bookmarksiteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	iconpath VARCHAR(250) NOT NULL DEFAULT '',
	active  SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	url VARCHAR(250) NOT NULL DEFAULT '',
	utf8encode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (bookmarksiteid)
)
";
$schema['CREATE']['explain']['bookmarksite'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "bookmarksite");



$schema['CREATE']['query']['cache'] = "
CREATE TABLE " . TABLE_PREFIX . "cache (
	cacheid VARBINARY(64) NOT NULL,
	expires INT UNSIGNED NOT NULL,
	created INT UNSIGNED NOT NULL,
	locktime INT UNSIGNED NOT NULL,
	serialized ENUM('0','1') NOT NULL DEFAULT '0',
	data MEDIUMTEXT,
	PRIMARY KEY (cacheid)
)
";
$schema['CREATE']['explain']['cache'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cache");



$schema['CREATE']['query']['cacheevent'] = "
CREATE TABLE " . TABLE_PREFIX . "cacheevent (
	cacheid VARBINARY(64) NOT NULL,
	event VARBINARY(50) NOT NULL,
	PRIMARY KEY (cacheid, event)
)
";
$schema['CREATE']['explain']['cacheevent'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cacheevent");



$schema['CREATE']['query']['calendar'] = "
CREATE TABLE " . TABLE_PREFIX . "calendar (
	calendarid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(255) NOT NULL DEFAULT '',
	description VARCHAR(100) NOT NULL DEFAULT '',
	displayorder SMALLINT NOT NULL DEFAULT '0',
	neweventemail TEXT,
	moderatenew SMALLINT NOT NULL DEFAULT '0',
	startofweek SMALLINT NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	cutoff SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	eventcount SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	birthdaycount SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	startyear SMALLINT UNSIGNED NOT NULL DEFAULT '2000',
	endyear SMALLINT UNSIGNED NOT NULL DEFAULT '2006',
	holidays INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarid),
	KEY displayorder (displayorder)
)
";
$schema['CREATE']['explain']['calendar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendar");



$schema['CREATE']['query']['calendarcustomfield'] = "
CREATE TABLE " . TABLE_PREFIX . "calendarcustomfield (
	calendarcustomfieldid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(255) NOT NULL DEFAULT '',
	description MEDIUMTEXT,
	options MEDIUMTEXT,
	allowentry SMALLINT NOT NULL DEFAULT '1',
	required SMALLINT NOT NULL DEFAULT '0',
	length SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarcustomfieldid),
	KEY calendarid (calendarid)
)
";
$schema['CREATE']['explain']['calendarcustomfield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarcustomfield");



$schema['CREATE']['query']['calendarmoderator'] = "
CREATE TABLE " . TABLE_PREFIX . "calendarmoderator (
	calendarmoderatorid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	neweventemail SMALLINT NOT NULL DEFAULT '0',
	permissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarmoderatorid),
	KEY userid (userid, calendarid)
)
";
$schema['CREATE']['explain']['calendarmoderator'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarmoderator");



$schema['CREATE']['query']['calendarpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "calendarpermission (
	calendarpermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	calendarpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarpermissionid),
	KEY calendarid (calendarid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['calendarpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarpermission");



$schema['CREATE']['query']['action'] = "
CREATE TABLE " . TABLE_PREFIX . "action (
	actionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	routeid INT UNSIGNED NOT NULL,
	packageid INT UNSIGNED NOT NULL,
	controller VARBINARY(50) NOT NULL,
	useraction VARCHAR(50) NOT NULL,
	classaction VARBINARY(50) NOT NULL,
	PRIMARY KEY (actionid),
	UNIQUE KEY useraction (routeid, useraction),
	UNIQUE KEY classaction (packageid, controller, classaction)
)
";
$schema['CREATE']['explain']['action'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "action");



$schema['CREATE']['query']['contentpriority'] = "
CREATE TABLE " . TABLE_PREFIX . "contentpriority (
	contenttypeid VARCHAR(20) NOT NULL,
	sourceid INT(10) UNSIGNED NOT NULL,
	prioritylevel DOUBLE(2,1) UNSIGNED NOT NULL,
	PRIMARY KEY (contenttypeid, sourceid)
)
";
$schema['CREATE']['explain']['contentpriority'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "contentpriority");



$schema['CREATE']['query']['contenttype'] = "
CREATE TABLE " . TABLE_PREFIX . "contenttype (
	contenttypeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	class VARBINARY(50) NOT NULL,
	packageid INT UNSIGNED NOT NULL,
	canplace ENUM('0','1') NOT NULL DEFAULT '0',
	cansearch ENUM('0','1') NOT NULL DEFAULT '0',
	cantag ENUM('0','1') DEFAULT '0',
	canattach ENUM('0','1') DEFAULT '0',
	isaggregator ENUM('0', '1') NOT NULL DEFAULT '0',
	PRIMARY KEY (contenttypeid),
	UNIQUE KEY packageclass (packageid, class)
)	ENGINE = $hightrafficengine
";
$schema['CREATE']['explain']['contenttype'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "contenttype");



$schema['CREATE']['query']['cpsession'] = "
CREATE TABLE " . TABLE_PREFIX . "cpsession (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	hash VARCHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid, hash)
)
";
$schema['CREATE']['explain']['cpsession'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cpsession");



$schema['CREATE']['query']['cron'] = "
CREATE TABLE " . TABLE_PREFIX . "cron (
	cronid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	nextrun INT UNSIGNED NOT NULL DEFAULT '0',
	weekday SMALLINT NOT NULL DEFAULT '0',
	day SMALLINT NOT NULL DEFAULT '0',
	hour SMALLINT NOT NULL DEFAULT '0',
	minute VARCHAR(100) NOT NULL DEFAULT '',
	filename CHAR(50) NOT NULL DEFAULT '',
	loglevel SMALLINT NOT NULL DEFAULT '0',
	active SMALLINT NOT NULL DEFAULT '1',
	varname VARCHAR(100) NOT NULL DEFAULT '',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	product VARCHAR(25) NOT NULL DEFAULT '',
	PRIMARY KEY (cronid),
	KEY nextrun (nextrun),
	UNIQUE KEY (varname)
)
";
$schema['CREATE']['explain']['cron'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cron");



$schema['CREATE']['query']['cronlog'] = "
CREATE TABLE " . TABLE_PREFIX . "cronlog (
	cronlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	varname VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	description MEDIUMTEXT,
	type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (cronlogid),
	KEY (varname)
)
";
$schema['CREATE']['explain']['cronlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cronlog");



$schema['CREATE']['query']['customavatar'] = "
CREATE TABLE " . TABLE_PREFIX . "customavatar (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	filedata MEDIUMBLOB,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filename VARCHAR(100) NOT NULL DEFAULT '',
	visible SMALLINT NOT NULL DEFAULT '1',
	filesize INT UNSIGNED NOT NULL DEFAULT '0',
	width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	filedata_thumb MEDIUMBLOB,
	width_thumb INT UNSIGNED NOT NULL DEFAULT '0',
	height_thumb INT UNSIGNED NOT NULl DEFAULT '0',
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['customavatar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "customavatar");

$schema['CREATE']['query']['customprofile'] = "
CREATE TABLE " . TABLE_PREFIX . "customprofile (
	customprofileid integer AUTO_INCREMENT,
	title VARCHAR(100),
	thumbnail VARCHAR(255),
	userid INT NOT NULL,
	themeid INT,
	font_family VARCHAR(255),
	fontsize VARCHAR(20),
	title_text_color VARCHAR(20),
	page_background_color VARCHAR(20),
	page_background_image VARCHAR(255),
	page_background_repeat  VARCHAR(20),
	module_text_color VARCHAR(20),
	module_link_color VARCHAR(20),
	module_background_color VARCHAR(20),
	module_background_image VARCHAR(255),
	module_background_repeat VARCHAR(20),
	module_border VARCHAR(20),
	content_text_color VARCHAR(20),
	content_link_color VARCHAR(20),
	content_background_color VARCHAR(20),
	content_background_image VARCHAR(255),
	content_background_repeat VARCHAR(20),
	content_border VARCHAR(20),
	button_text_color VARCHAR(20),
	button_background_color VARCHAR(20),
	button_background_image VARCHAR(255),
	button_background_repeat VARCHAR(20),
	button_border VARCHAR(20),
	moduleinactive_text_color varchar(20),
	moduleinactive_link_color varchar(20),
	moduleinactive_background_color varchar(20),
	moduleinactive_background_image varchar(255),
	moduleinactive_background_repeat varchar(20),
	moduleinactive_border varchar(20),
	headers_text_color varchar(20),
	headers_link_color varchar(20),
	headers_background_color varchar(20),
	headers_background_image varchar(255),
	headers_background_repeat varchar(20),
	headers_border varchar(20),
	page_link_color varchar(20),
	PRIMARY KEY  (customprofileid),
	KEY(userid)
	)
";
$schema['CREATE']['explain']['customprofile'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "customprofile");


$schema['CREATE']['query']['customprofilepic'] = "
CREATE TABLE " . TABLE_PREFIX . "customprofilepic (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	filedata MEDIUMBLOB,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filename VARCHAR(100) NOT NULL DEFAULT '',
	visible SMALLINT NOT NULL DEFAULT '1',
	filesize INT UNSIGNED NOT NULL DEFAULT '0',
	width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['customprofilepic'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "customprofilepic");



$schema['CREATE']['query']['datastore'] = "
CREATE TABLE " . TABLE_PREFIX . "datastore (
	title CHAR(50) NOT NULL DEFAULT '',
	data MEDIUMTEXT,
	unserialize SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (title)
)
";
$schema['CREATE']['explain']['datastore'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "datastore");


$schema['CREATE']['query']['dbquery'] = "
CREATE TABLE " . TABLE_PREFIX . "dbquery (
	dbqueryid varchar(32) NOT NULL,
	querytype enum('u','d', 'i', 's') NOT NULL,
	query_string text,
	PRIMARY KEY (dbqueryid)
)
";
$schema['CREATE']['explain']['dbquery'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "dbquery");


$schema['CREATE']['query']['deletionlog'] = "
CREATE TABLE " . TABLE_PREFIX . "deletionlog (
	primaryid INT UNSIGNED NOT NULL DEFAULT '0',
	type ENUM('post', 'thread', 'visitormessage', 'groupmessage', 'picturecomment') NOT NULL DEFAULT 'post',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	reason VARCHAR(125) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (primaryid, type),
	KEY type (type, dateline)
)
";
$schema['CREATE']['explain']['deletionlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "deletionlog");



$schema['CREATE']['query']['discussion'] = "
CREATE TABLE " . TABLE_PREFIX . "discussion (
	discussionid INT unsigned NOT NULL auto_increment,
	groupid INT unsigned NOT NULL,
	firstpostid INT unsigned NOT NULL,
	lastpostid INT unsigned NOT NULL,
	lastpost INT unsigned NOT NULL,
	lastposter VARCHAR(255) NOT NULL,
	lastposterid INT unsigned NOT NULL,
	visible INT unsigned NOT NULL default '0',
	deleted INT unsigned NOT NULL default '0',
	moderation INT unsigned NOT NULL default '0',
	subscribers ENUM('0', '1') default '0',
	PRIMARY KEY  (discussionid),
	KEY groupid (groupid, lastpost)
)
";
$schema['CREATE']['explain']['discussion'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "discussion");



$schema['CREATE']['query']['discussionread'] = "
CREATE TABLE " . TABLE_PREFIX . "discussionread (
	userid INT unsigned NOT NULL,
	discussionid INT unsigned NOT NULL,
	readtime INT unsigned NOT NULL,
	PRIMARY KEY (userid, discussionid),
	KEY readtime (readtime)
)
";
$schema['CREATE']['explain']['discussionread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "discussionread");



$schema['CREATE']['query']['editlog'] = "
CREATE TABLE " . TABLE_PREFIX . "editlog (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(200) NOT NULL DEFAULT '',
	hashistory SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (postid)
)
";
$schema['CREATE']['explain']['editlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "editlog");



$schema['CREATE']['query']['event'] = "
CREATE TABLE " . TABLE_PREFIX . "event (
	eventid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	event MEDIUMTEXT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	allowsmilies SMALLINT NOT NULL DEFAULT '1',
	recurring SMALLINT NOT NULL DEFAULT '0',
	recuroption CHAR(6) NOT NULL DEFAULT '',
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	customfields MEDIUMTEXT,
	visible SMALLINT NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	utc DECIMAL(4,2) NOT NULL DEFAULT '0.0',
	dst SMALLINT NOT NULL DEFAULT '1',
	dateline_from INT UNSIGNED NOT NULL DEFAULT '0',
	dateline_to INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (eventid),
	KEY userid (userid),
	KEY calendarid (calendarid),
	KEY (visible),
	KEY daterange (dateline_to, dateline_from, visible, calendarid)
)
";
$schema['CREATE']['explain']['event'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "event");



$schema['CREATE']['query']['externalcache'] = "
CREATE TABLE " . TABLE_PREFIX . "externalcache (
	cachehash CHAR(32) NOT NULL DEFAULT '',
	text MEDIUMTEXT,
	headers MEDIUMTEXT,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	forumid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (cachehash),
	KEY dateline (dateline, cachehash),
	KEY forumid (forumid)
)
";
$schema['CREATE']['explain']['externalcache'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "externalcache");



$schema['CREATE']['query']['faq'] = "
CREATE TABLE " . TABLE_PREFIX . "faq (
	faqname VARCHAR(250) BINARY NOT NULL DEFAULT '',
	faqparent VARCHAR(50) NOT NULL DEFAULT '',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	product VARCHAR(25) NOT NULL DEFAULT '',
	PRIMARY KEY (faqname),
	KEY faqparent (faqparent)
)
";
$schema['CREATE']['explain']['faq'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "faq");



$schema['CREATE']['query']['filedata'] = "
CREATE TABLE " . TABLE_PREFIX . "filedata (
	filedataid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	thumbnail_dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filedata MEDIUMBLOB,
	filesize INT UNSIGNED NOT NULL DEFAULT '0',
	filehash CHAR(32) NOT NULL DEFAULT '',
	thumbnail MEDIUMBLOB,
	thumbnail_filesize INT UNSIGNED NOT NULL DEFAULT '0',
	extension VARCHAR(20) BINARY NOT NULL DEFAULT '',
	refcount INT UNSIGNED NOT NULL DEFAULT '0',
	width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	thumbnail_width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	thumbnail_height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (filedataid),
	KEY filesize (filesize),
	KEY filehash (filehash),
	KEY userid (userid),
	KEY refcount (refcount, dateline)
)
";
$schema['CREATE']['explain']['filedata'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "filedata");



$schema['CREATE']['query']['forum'] = "
CREATE TABLE " . TABLE_PREFIX . "forum (
	forumid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	title_clean VARCHAR(100) NOT NULL DEFAULT '',
	description TEXT,
	description_clean TEXT,
	options INT UNSIGNED NOT NULL DEFAULT '0',
	showprivate TINYINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT NOT NULL DEFAULT '0',
	replycount INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT NOT NULL DEFAULT '0',
	lastposter VARCHAR(100) NOT NULL DEFAULT '',
	lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpostid INT UNSIGNED NOT NULL DEFAULT '0',
	lastthread VARCHAR(250) NOT NULL DEFAULT '',
	lastthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	lasticonid SMALLINT NOT NULL DEFAULT '0',
	lastprefixid VARCHAR(25) NOT NULL DEFAULT '',
	threadcount mediumint UNSIGNED NOT NULL DEFAULT '0',
	daysprune SMALLINT NOT NULL DEFAULT '0',
	newpostemail TEXT,
	newthreademail TEXT,
	parentid SMALLINT NOT NULL DEFAULT '0',
	parentlist VARCHAR(250) NOT NULL DEFAULT '',
	password VARCHAR(50) NOT NULL DEFAULT '',
	link VARCHAR(200) NOT NULL DEFAULT '',
	childlist TEXT,
	defaultsortfield VARCHAR(50) NOT NULL DEFAULT 'lastpost',
	defaultsortorder ENUM('asc', 'desc') NOT NULL DEFAULT 'desc',
 	imageprefix VARCHAR(100) NOT NULL DEFAULT '',
	PRIMARY KEY (forumid)
)
";
$schema['CREATE']['explain']['forum'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forum");



$schema['CREATE']['query']['forumread'] = "
CREATE TABLE " . TABLE_PREFIX . "forumread (
	userid int(10) unsigned NOT NULL default '0',
	forumid smallint(5) unsigned NOT NULL default '0',
	readtime int(10) unsigned NOT NULL default '0',
	PRIMARY KEY (forumid, userid),
	KEY readtime (readtime)
)
";
$schema['CREATE']['explain']['forumread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumread");



$schema['CREATE']['query']['forumpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "forumpermission (
	forumpermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	forumpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (forumpermissionid),
	UNIQUE KEY ugid_fid (usergroupid, forumid)
)
";
$schema['CREATE']['explain']['forumpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumpermission");



$schema['CREATE']['query']['forumprefixset'] = "
CREATE TABLE " . TABLE_PREFIX . "forumprefixset (
	forumid INT UNSIGNED NOT NULL DEFAULT '0',
	prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
	PRIMARY KEY (forumid, prefixsetid)
)
";
$schema['CREATE']['explain']['forumprefixset'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumprefixset");



$schema['CREATE']['query']['groupmessage'] = "
CREATE TABLE " . TABLE_PREFIX . "groupmessage (
	gmid INT UNSIGNED NOT NULL auto_increment,
	discussionid INT UNSIGNED NOT NULL DEFAULT '0',
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	postusername VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	state ENUM('visible','moderation','deleted') NOT NULL default 'visible',
	title VARCHAR(255) NOT NULL DEFAULT '',
	pagetext MEDIUMTEXT,
	ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
	allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (gmid),
	KEY postuserid (postuserid, discussionid, state),
	KEY discussionid (discussionid, dateline, state)
)
";
$schema['CREATE']['explain']['groupmessage'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "groupmessage");



$schema['CREATE']['query']['groupmessage_hash'] = "
CREATE TABLE " . TABLE_PREFIX . "groupmessage_hash (
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	groupid INT UNSIGNED NOT NULL DEFAULT '0',
	dupehash VARCHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY postuserid (postuserid, dupehash),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['groupmessage_hash'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "groupmessage_hash");



$schema['CREATE']['query']['groupread'] = "
CREATE TABLE " . TABLE_PREFIX . "groupread (
	userid INT unsigned NOT NULL,
	groupid INT unsigned NOT NULL,
	readtime INT unsigned NOT NULL,
	PRIMARY KEY  (userid, groupid),
	KEY readtime (readtime)
)
";
$schema['CREATE']['explain']['groupread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "groupread");



$schema['CREATE']['query']['holiday'] = "
CREATE TABLE " . TABLE_PREFIX . "holiday (
	holidayid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	varname VARCHAR(100) NOT NULL DEFAULT '',
	recurring SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	recuroption CHAR(6) NOT NULL DEFAULT '',
	allowsmilies SMALLINT NOT NULL DEFAULT '1',
	PRIMARY KEY (holidayid),
	KEY varname (varname)
)
";
$schema['CREATE']['explain']['holiday'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "holiday");



$schema['CREATE']['query']['humanverify'] = "
CREATE TABLE " . TABLE_PREFIX . "humanverify (
	hash CHAR(32) NOT NULL DEFAULT '',
	answer MEDIUMTEXT,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	viewed SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	KEY hash (hash),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['humanverify'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "humanverify");



$schema['CREATE']['query']['hvanswer'] = "
CREATE TABLE " . TABLE_PREFIX . "hvanswer (
	answerid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	questionid INT NOT NULL DEFAULT '0',
	answer VARCHAR(255) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	INDEX (questionid)
)
";
$schema['CREATE']['explain']['hvanswer'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "hvanswer");



$schema['CREATE']['query']['hvquestion'] = "
CREATE TABLE " . TABLE_PREFIX . "hvquestion (
	questionid INT  UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	regex VARCHAR(255) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0'
)
";
$schema['CREATE']['explain']['hvquestion'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "hvquestion");



$schema['CREATE']['query']['icon'] = "
CREATE TABLE " . TABLE_PREFIX . "icon (
	iconid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(100) NOT NULL DEFAULT '',
	iconpath VARCHAR(100) NOT NULL DEFAULT '',
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (iconid)
)
";
$schema['CREATE']['explain']['icon'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "icon");



$schema['CREATE']['query']['imagecategory'] = "
CREATE TABLE " . TABLE_PREFIX . "imagecategory (
	imagecategoryid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(255) NOT NULL DEFAULT '',
	imagetype SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (imagecategoryid)
)
";
$schema['CREATE']['explain']['imagecategory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "imagecategory");



$schema['CREATE']['query']['imagecategorypermission'] = "
CREATE TABLE " . TABLE_PREFIX . "imagecategorypermission (
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	KEY imagecategoryid (imagecategoryid, usergroupid)
)
";
$schema['CREATE']['explain']['imagecategorypermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "imagecategorypermission");



$schema['CREATE']['query']['indexqueue'] = "
CREATE TABLE " . TABLE_PREFIX . "indexqueue (
	queueid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
	contenttype VARCHAR(45) NOT NULL,
	newid INTEGER UNSIGNED NOT NULL,
	id2 INTEGER UNSIGNED NOT NULL,
	package VARCHAR(64) NOT NULL,
	operation VARCHAR(64) NOT NULL,
	data TEXT NOT NULL,
	PRIMARY KEY (queueid)
)
";
$schema['CREATE']['explain']['indexqueue'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "indexqueue");



$schema['CREATE']['query']['infraction'] = "
CREATE TABLE " . TABLE_PREFIX . "infraction (
	infractionid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
	infractionlevelid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	whoadded INT UNSIGNED NOT NULL DEFAULT '0',
	points INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	note varchar(255) NOT NULL DEFAULT '',
	action SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	actiondateline INT UNSIGNED NOT NULL DEFAULT '0',
	actionuserid INT UNSIGNED NOT NULL DEFAULT '0',
	actionreason VARCHAR(255) NOT NULL DEFAULT '0',
	expires INT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	customreason VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (infractionid),
	KEY expires (expires, action),
	KEY userid (userid, action),
	KEY infractonlevelid (infractionlevelid),
	KEY postid (postid),
	KEY threadid (threadid)
)
";
$schema['CREATE']['explain']['infraction'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "infraction");



$schema['CREATE']['query']['infractionban'] = "
CREATE TABLE " . TABLE_PREFIX . "infractionban (
	infractionbanid int unsigned NOT NULL auto_increment,
	usergroupid int NOT NULL DEFAULT '0',
	banusergroupid int unsigned NOT NULL DEFAULT '0',
	amount int unsigned NOT NULL DEFAULT '0',
	period char(5) NOT NULL DEFAULT '',
	method enum('points','infractions') NOT NULL default 'infractions',
	PRIMARY KEY (infractionbanid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['infractionban'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "infractionban");



$schema['CREATE']['query']['infractiongroup'] = "
CREATE TABLE " . TABLE_PREFIX . "infractiongroup (
	infractiongroupid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	usergroupid INT NOT NULL DEFAULT '0',
	orusergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pointlevel INT UNSIGNED NOT NULL DEFAULT '0',
	override SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (infractiongroupid),
	KEY usergroupid (usergroupid, pointlevel)
)
";
$schema['CREATE']['explain']['infractiongroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "infractiongroup");



$schema['CREATE']['query']['infractionlevel'] = "
CREATE TABLE " . TABLE_PREFIX . "infractionlevel (
	infractionlevelid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
	points INT UNSIGNED NOT NULL DEFAULT '0',
	expires INT UNSIGNED NOT NULL DEFAULT '0',
	period ENUM('H','D','M','N') DEFAULT 'H' NOT NULL,
	warning SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	extend SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (infractionlevelid)
)
";
$schema['CREATE']['explain']['infractionlevel'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "infractionlevel");



$schema['CREATE']['query']['language'] = "
CREATE TABLE " . TABLE_PREFIX . "language (
	languageid smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(50) NOT NULL default '',
	userselect smallint(5) UNSIGNED NOT NULL default '1',
	options smallint(5) UNSIGNED NOT NULL default '1',
	languagecode VARCHAR(12) NOT NULL default '',
	charset VARCHAR(15) NOT NULL default '',
	imagesoverride VARCHAR(150) NOT NULL default '',
	dateoverride VARCHAR(50) NOT NULL default '',
	timeoverride VARCHAR(50) NOT NULL default '',
	registereddateoverride VARCHAR(50) NOT NULL default '',
	calformat1override VARCHAR(50) NOT NULL default '',
	calformat2override VARCHAR(50) NOT NULL default '',
	logdateoverride VARCHAR(50) NOT NULL default '',
	locale VARCHAR(20) NOT NULL default '',
	decimalsep CHAR(1) NOT NULL default '.',
	thousandsep CHAR(1) NOT NULL default ',',
	phrasegroup_global MEDIUMTEXT,
	phrasegroup_cpglobal MEDIUMTEXT,
	phrasegroup_cppermission MEDIUMTEXT,
	phrasegroup_forum MEDIUMTEXT,
	phrasegroup_calendar MEDIUMTEXT,
	phrasegroup_attachment_image MEDIUMTEXT,
	phrasegroup_style MEDIUMTEXT,
	phrasegroup_logging MEDIUMTEXT,
	phrasegroup_cphome MEDIUMTEXT,
	phrasegroup_promotion MEDIUMTEXT,
	phrasegroup_user MEDIUMTEXT,
	phrasegroup_help_faq MEDIUMTEXT,
	phrasegroup_sql MEDIUMTEXT,
	phrasegroup_subscription MEDIUMTEXT,
	phrasegroup_language MEDIUMTEXT,
	phrasegroup_bbcode MEDIUMTEXT,
	phrasegroup_stats MEDIUMTEXT,
	phrasegroup_diagnostic MEDIUMTEXT,
	phrasegroup_maintenance MEDIUMTEXT,
	phrasegroup_profilefield MEDIUMTEXT,
	phrasegroup_thread MEDIUMTEXT,
	phrasegroup_timezone MEDIUMTEXT,
	phrasegroup_banning MEDIUMTEXT,
	phrasegroup_reputation MEDIUMTEXT,
	phrasegroup_wol MEDIUMTEXT,
	phrasegroup_threadmanage MEDIUMTEXT,
	phrasegroup_pm MEDIUMTEXT,
	phrasegroup_cpuser MEDIUMTEXT,
	phrasegroup_accessmask MEDIUMTEXT,
	phrasegroup_cron MEDIUMTEXT,
	phrasegroup_moderator MEDIUMTEXT,
	phrasegroup_cpoption MEDIUMTEXT,
	phrasegroup_cprank MEDIUMTEXT,
	phrasegroup_cpusergroup MEDIUMTEXT,
	phrasegroup_holiday MEDIUMTEXT,
	phrasegroup_posting MEDIUMTEXT,
	phrasegroup_poll MEDIUMTEXT,
	phrasegroup_fronthelp MEDIUMTEXT,
	phrasegroup_register MEDIUMTEXT,
	phrasegroup_search MEDIUMTEXT,
	phrasegroup_showthread MEDIUMTEXT,
	phrasegroup_postbit MEDIUMTEXT,
	phrasegroup_forumdisplay MEDIUMTEXT,
	phrasegroup_messaging MEDIUMTEXT,
	phrasegroup_inlinemod MEDIUMTEXT,
	phrasegroup_plugins MEDIUMTEXT,
	phrasegroup_cprofilefield MEDIUMTEXT,
	phrasegroup_reputationlevel MEDIUMTEXT,
	phrasegroup_infraction MEDIUMTEXT,
	phrasegroup_infractionlevel MEDIUMTEXT,
	phrasegroup_notice MEDIUMTEXT,
	phrasegroup_prefix MEDIUMTEXT,
	phrasegroup_prefixadmin MEDIUMTEXT,
	phrasegroup_album MEDIUMTEXT,
	phrasegroup_socialgroups MEDIUMTEXT,
	phrasegroup_advertising MEDIUMTEXT,
	phrasegroup_tagscategories MEDIUMTEXT,
	phrasegroup_contenttypes MEDIUMTEXT,
	phrasegroup_vbblock MEDIUMTEXT,
	phrasegroup_vbblocksettings MEDIUMTEXT,
	phrasegroup_ckeditor MEDIUMTEXT,
	phrasegroupinfo MEDIUMTEXT,
	PRIMARY KEY  (languageid)
)
";
$schema['CREATE']['explain']['language'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "language");



$schema['CREATE']['query']['mailqueue'] = "
CREATE TABLE " . TABLE_PREFIX . "mailqueue (
	mailqueueid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	toemail MEDIUMTEXT,
	fromemail MEDIUMTEXT,
	subject MEDIUMTEXT,
	message MEDIUMTEXT,
	header MEDIUMTEXT,
	PRIMARY KEY (mailqueueid)
)
";
$schema['CREATE']['explain']['mailqueue'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "mailqueue");



$schema['CREATE']['query']['moderation'] = "
CREATE TABLE " . TABLE_PREFIX . "moderation (
	primaryid INT UNSIGNED NOT NULL DEFAULT '0',
	type ENUM('thread', 'reply', 'visitormessage', 'groupmessage', 'picturecomment') NOT NULL DEFAULT 'thread',
	dateline INT UNSIGNED NOT NULl DEFAULT '0',
	PRIMARY KEY (primaryid, type),
	KEY type (type, dateline)
)
";
$schema['CREATE']['explain']['moderation'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderation");



$schema['CREATE']['query']['moderator'] = "
CREATE TABLE " . TABLE_PREFIX . "moderator (
	moderatorid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT NOT NULL DEFAULT '0',
	permissions INT UNSIGNED NOT NULL DEFAULT '0',
	permissions2 INT UNSIGNED NOT NULl DEFAULT '0',
	PRIMARY KEY (moderatorid),
	UNIQUE KEY userid_forumid (userid, forumid)
)
";
$schema['CREATE']['explain']['moderator'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderator");



$schema['CREATE']['query']['moderatorlog'] = "
CREATE TABLE " . TABLE_PREFIX . "moderatorlog (
	moderatorlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	pollid INT UNSIGNED NOT NULL DEFAULT '0',
	attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
	action VARCHAR(250) NOT NULL DEFAULT '',
	type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	threadtitle VARCHAR(250) NOT NULL DEFAULT '',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	product VARCHAR(25) NOT NULL DEFAULT '',
	id1 INT UNSIGNED NOT NULL DEFAULT '0',
	id2 INT UNSIGNED NOT NULL DEFAULT '0',
	id3 INT UNSIGNED NOT NULL DEFAULT '0',
	id4 INT UNSIGNED NOT NULL DEFAULT '0',
	id5 INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (moderatorlogid),
	KEY threadid (threadid),
	KEY product (product),
	KEY id1 (id1),
	KEY id2 (id2)
)
";
$schema['CREATE']['explain']['moderatorlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderatorlog");



$schema['CREATE']['query']['notice'] = "
CREATE TABLE " . TABLE_PREFIX . "notice (
	noticeid INT UNSIGNED NOT NULL auto_increment,
	title VARCHAR(250) NOT NULL DEFAULT '',
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	persistent SMALLINT UNSIGNED NOT NULL default '0',
	active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	dismissible SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (noticeid),
	KEY active (active)
)
";
$schema['CREATE']['explain']['notice'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "notice");



$schema['CREATE']['query']['noticecriteria'] = "
CREATE TABLE " . TABLE_PREFIX . "noticecriteria (
	noticeid INT UNSIGNED NOT NULL DEFAULT '0',
	criteriaid VARCHAR(250) NOT NULL DEFAULT '',
	condition1 VARCHAR(250) NOT NULL DEFAULT '',
	condition2 VARCHAR(250) NOT NULL DEFAULT '',
	condition3 VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (noticeid,criteriaid)
)
";
$schema['CREATE']['explain']['noticecriteria'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "noticecriteria");



$schema['CREATE']['query']['noticedismissed'] = "
CREATE TABLE " . TABLE_PREFIX . "noticedismissed (
	noticeid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (noticeid,userid),
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['noticedismissed'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "noticedismissed");



$schema['CREATE']['query']['package'] = "
CREATE TABLE " . TABLE_PREFIX . "package (
	packageid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	productid VARCHAR(25) NOT NULL,
	class VARBINARY(50) NOT NULL,
	PRIMARY KEY  (packageid),
	UNIQUE KEY class (class)
)
";
$schema['CREATE']['explain']['package'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "package");



$schema['CREATE']['query']['passwordhistory'] = "
CREATE TABLE " . TABLE_PREFIX . "passwordhistory (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	password VARCHAR(50) NOT NULL DEFAULT '',
	passworddate date NOT NULL DEFAULT '0000-00-00',
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['passwordhistory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "passwordhistory");



$schema['CREATE']['query']['paymentapi'] = "
CREATE TABLE " . TABLE_PREFIX . "paymentapi (
	paymentapiid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	currency VARCHAR(250) NOT NULL DEFAULT '',
	recurring SMALLINT NOT NULL DEFAULT '0',
	classname VARCHAR(250) NOT NULL DEFAULT '',
	active SMALLINT NOT NULL DEFAULT '0',
	settings MEDIUMTEXT,
	PRIMARY KEY (paymentapiid)
)
";
$schema['CREATE']['explain']['paymentapi'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymentapi");



$schema['CREATE']['query']['paymentinfo'] = "
CREATE TABLE " . TABLE_PREFIX . "paymentinfo (
	paymentinfoid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	hash VARCHAR(32) NOT NULL DEFAULT '',
	subscriptionid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	subscriptionsubid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	completed SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (paymentinfoid),
	KEY hash (hash)
)
";
$schema['CREATE']['explain']['paymentinfo'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymentinfo");



$schema['CREATE']['query']['paymenttransaction'] = "
CREATE TABLE " . TABLE_PREFIX . "paymenttransaction (
	paymenttransactionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	paymentinfoid INT UNSIGNED NOT NULL DEFAULT '0',
	transactionid VARCHAR(250) NOT NULL DEFAULT '',
	state SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	amount DOUBLE UNSIGNED NOT NULL DEFAULT '0',
	currency VARCHAR(5) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	paymentapiid INT UNSIGNED NOT NULL DEFAULT '0',
	request MEDIUMTEXT,
	reversed INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (paymenttransactionid),
	KEY dateline (dateline),
	KEY transactionid (transactionid),
	KEY paymentapiid (paymentapiid)
)
";
$schema['CREATE']['explain']['paymenttransaction'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "paymenttransaction");



$schema['CREATE']['query']['phrase'] = "
CREATE TABLE " . TABLE_PREFIX . "phrase (
	phraseid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	languageid SMALLINT NOT NULL DEFAULT '0',
	varname VARCHAR(250) BINARY NOT NULL DEFAULT '',
	fieldname VARCHAR(20) NOT NULL DEFAULT '',
	text MEDIUMTEXT,
	product VARCHAR(25) NOT NULL DEFAULT '',
	username VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	version VARCHAR(30) NOT NULL DEFAULT '',
	PRIMARY KEY  (phraseid),
	UNIQUE KEY name_lang_type (varname, languageid, fieldname),
	KEY languageid (languageid, fieldname, dateline)
)
";
$schema['CREATE']['explain']['phrase'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrase");



$schema['CREATE']['query']['phrasetype'] = "
CREATE TABLE " . TABLE_PREFIX . "phrasetype (
	fieldname CHAR(20) NOT NULL default '',
	title CHAR(50) NOT NULL DEFAULT '',
	editrows SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	product VARCHAR(25) NOT NULL DEFAULT '',
	special SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (fieldname)
)
";
$schema['CREATE']['explain']['phrasetype'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrasetype");



$schema['CREATE']['query']['picturelegacy'] = "
CREATE TABLE " . TABLE_PREFIX . "picturelegacy (
	type ENUM('album', 'group') NOT NULL DEFAULT 'album',
	primaryid INT UNSIGNED NOT NULL DEFAULT '0',
	pictureid INT UNSIGNED NOT NULL DEFAULT '0',
	attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (type, primaryid, pictureid)
)
";
$schema['CREATE']['explain']['picturelegacy'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "picturelegacy");



$schema['CREATE']['query']['picturecomment'] = "
CREATE TABLE " . TABLE_PREFIX . "picturecomment (
	commentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	filedataid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	postusername varchar(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	state ENUM('visible','moderation','deleted') NOT NULL DEFAULT 'visible',
	title VARCHAR(255) NOT NULL DEFAULT '',
	pagetext MEDIUMTEXT,
	ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
	allowsmilie SMALLINT NOT NULL DEFAULT '1',
	reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (commentid),
	KEY filedataid (filedataid, userid, dateline, state),
	KEY postuserid (postuserid, filedataid, userid, state),
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['picturecomment'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "picturecomment");



$schema['CREATE']['query']['picturecomment_hash'] = "
CREATE TABLE " . TABLE_PREFIX . "picturecomment_hash (
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	filedataid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dupehash VARCHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY postuserid (postuserid, dupehash),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['picturecomment_hash'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "picturecomment_hash");



$schema['CREATE']['query']['plugin'] = "
CREATE TABLE " . TABLE_PREFIX . "plugin (
	pluginid INT unsigned NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	hookname VARCHAR(250) NOT NULL DEFAULT '',
	phpcode TEXT,
	product VARCHAR(25) NOT NULL DEFAULT '',
	devkey VARCHAR(25) NOT NULL DEFAULT '',
	active SMALLINT(6) NOT NULL DEFAULT '0',
	executionorder SMALLINT UNSIGNED NOT NULL DEFAULT '5',
	PRIMARY KEY (pluginid),
	KEY active (active)
)
";
$schema['CREATE']['explain']['plugin'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "plugin");



$schema['CREATE']['query']['pm'] = "
CREATE TABLE " . TABLE_PREFIX . "pm (
	pmid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	pmtextid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	folderid SMALLINT NOT NULL DEFAULT '0',
	messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	parentpmid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pmid),
	KEY pmtextid (pmtextid),
	KEY userid (userid, folderid)
)
";
$schema['CREATE']['explain']['pm'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pm");



$schema['CREATE']['query']['pmthrottle'] = "
CREATE TABLE " . TABLE_PREFIX . "pmthrottle (
	userid INT unsigned NOT NULL,
	dateline INT unsigned NOT NULL,
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['pmthrottle'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmthrottle");



$schema['CREATE']['query']['pmreceipt'] = "
CREATE TABLE " . TABLE_PREFIX . "pmreceipt (
	pmid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	touserid INT UNSIGNED NOT NULL DEFAULT '0',
	tousername VARCHAR(100) NOT NULL DEFAULT '',
	title VARCHAR(250) NOT NULL DEFAULT '',
	sendtime INT UNSIGNED NOT NULL DEFAULT '0',
	readtime INT UNSIGNED NOT NULL DEFAULT '0',
	denied SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pmid),
	KEY userid (userid, readtime),
	KEY touserid (touserid)
)
";
$schema['CREATE']['explain']['pmreceipt'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmreceipt");



$schema['CREATE']['query']['pmtext'] = "
CREATE TABLE " . TABLE_PREFIX . "pmtext (
	pmtextid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	fromuserid INT UNSIGNED NOT NULL DEFAULT '0',
	fromusername VARCHAR(100) NOT NULL DEFAULT '',
	title VARCHAR(250) NOT NULL DEFAULT '',
	message MEDIUMTEXT,
	touserarray MEDIUMTEXT,
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	showsignature SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pmtextid),
	KEY fromuserid (fromuserid, dateline)
)
";
$schema['CREATE']['explain']['pmtext'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmtext");


$schema['CREATE']['query']['podcast'] = "
CREATE TABLE " . TABLE_PREFIX . "podcast (
	forumid INT UNSIGNED NOT NULL DEFAULT '0',
	author VARCHAR(255) NOT NULL DEFAULT '',
	category VARCHAR(255) NOT NULL DEFAULT '',
	image VARCHAR(255) NOT NULL DEFAULT '',
	explicit SMALLINT NOT NULL DEFAULT '0',
	enabled SMALLINT NOT NULL DEFAULT '1',
	keywords VARCHAR(255) NOT NULL DEFAULT '',
	owneremail VARCHAR(255) NOT NULL DEFAULT '',
	ownername VARCHAR(255) NOT NULL DEFAULT '',
	subtitle VARCHAR(255) NOT NULL DEFAULT '',
	summary MEDIUMTEXT,
	categoryid SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY  (forumid)
)
";
$schema['CREATE']['explain']['podcast'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "podcast");


$schema['CREATE']['query']['podcastitem'] = "
CREATE TABLE " . TABLE_PREFIX . "podcastitem (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	url VARCHAR(255) NOT NULL DEFAULT '',
	length INT UNSIGNED NOT NULL DEFAULT '0',
	explicit SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	keywords VARCHAR(255) NOT NULL DEFAULT '',
	subtitle VARCHAR(255) NOT NULL DEFAULT '',
	author VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY  (postid)
)
";
$schema['CREATE']['explain']['podcastitem'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "podcastitem");


$schema['CREATE']['query']['poll'] = "
CREATE TABLE " . TABLE_PREFIX . "poll (
	pollid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	question VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	options TEXT,
	votes TEXT,
	active SMALLINT NOT NULL DEFAULT '1',
	numberoptions SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	timeout SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	multiple SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	voters SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	public SMALLINT NOT NULL DEFAULT '0',
	lastvote INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pollid)
)
";
$schema['CREATE']['explain']['poll'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "poll");



$schema['CREATE']['query']['pollvote'] = "
CREATE TABLE " . TABLE_PREFIX . "pollvote (
	pollvoteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	pollid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NULL DEFAULT NULL,
	votedate INT UNSIGNED NOT NULL DEFAULT '0',
	voteoption INT UNSIGNED NOT NULL DEFAULT '0',
	votetype INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pollvoteid),
	UNIQUE KEY pollid (pollid, userid, votetype),
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['pollvote'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pollvote");



$schema['CREATE']['query']['post'] = "
CREATE TABLE " . TABLE_PREFIX . "post (
	postid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	parentid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(250) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	pagetext MEDIUMTEXT,
	allowsmilie SMALLINT NOT NULL DEFAULT '0',
	showsignature SMALLINT NOT NULL DEFAULT '0',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	visible SMALLINT NOT NULL DEFAULT '0',
	attach SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	infraction SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	htmlstate ENUM('off', 'on', 'on_nl2br') NOT NULL DEFAULT 'on_nl2br',
	PRIMARY KEY (postid),
	KEY userid (userid),
	KEY threadid (threadid, userid),
  KEY threadid_visible_dateline (threadid, visible, dateline, userid, postid),
	KEY dateline (dateline),
	KEY ipaddress (ipaddress)
)
";
$schema['CREATE']['explain']['post'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "post");



$schema['CREATE']['query']['postedithistory'] = "
CREATE TABLE " . TABLE_PREFIX . "postedithistory (
	postedithistoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	title VARCHAR(250) NOT NULL DEFAULT '',
	iconid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(200) NOT NULL DEFAULT '',
	original SMALLINT NOT NULL DEFAULT '0',
	pagetext MEDIUMTEXT,
	PRIMARY KEY  (postedithistoryid),
	KEY postid (postid,userid)
)
";
$schema['CREATE']['explain']['postedithistory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "postedithistory");

$schema['CREATE']['query']['postlog'] = "
CREATE TABLE " . TABLE_PREFIX . "postlog (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	useragent CHAR(100) NOT NULL DEFAULT '',
	ip INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (postid),
	KEY dateline (dateline),
	KEY ip (ip)
)
";
$schema['CREATE']['explain']['postlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "postlog");


$schema['CREATE']['query']['postparsed'] = "
CREATE TABLE " . TABLE_PREFIX . "postparsed (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	hasimages SMALLINT NOT NULL DEFAULT '0',
	pagetext_html MEDIUMTEXT,
	PRIMARY KEY (postid, styleid, languageid),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['postparsed'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "postparsed");



$schema['CREATE']['query']['prefix'] = "
CREATE TABLE " . TABLE_PREFIX . "prefix (
	prefixid VARCHAR(25) NOT NULL DEFAULT '',
	prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (prefixid),
	KEY prefixsetid (prefixsetid, displayorder)
)
";
$schema['CREATE']['explain']['prefix'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "prefix");




$schema['CREATE']['query']['prefixpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "prefixpermission (
	prefixid VARCHAR(25) NOT NULL DEFAULT '',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	KEY prefixsetid (prefixid, usergroupid)
)
";
$schema['CREATE']['explain']['prefixpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "prefixpermission");



$schema['CREATE']['query']['prefixset'] = "
CREATE TABLE " . TABLE_PREFIX . "prefixset (
	prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
	displayorder INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (prefixsetid)
)
";
$schema['CREATE']['explain']['prefixset'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "prefixset");



$schema['CREATE']['query']['posthash'] = "
CREATE TABLE " . TABLE_PREFIX . "posthash (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	dupehash CHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY userid (userid, dupehash),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['posthash'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "posthash");



$schema['CREATE']['query']['product'] = "
CREATE TABLE " . TABLE_PREFIX . "product (
	productid VARCHAR(25) NOT NULL DEFAULT '',
	title VARCHAR(50) NOT NULL DEFAULT '',
	description VARCHAR(250) NOT NULL DEFAULT '',
	version VARCHAR(25) NOT NULL DEFAULT '',
	active SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	url VARCHAR(250) NOT NULL DEFAULT '',
	versioncheckurl VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (productid),
	INDEX (active)
)
";
$schema['CREATE']['explain']['product'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "product");



$schema['CREATE']['query']['productcode'] = "
CREATE TABLE " . TABLE_PREFIX . "productcode (
	productcodeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	productid VARCHAR(25) NOT NULL DEFAULT '',
	version VARCHAR(25) NOT NULL DEFAULT '',
	installcode MEDIUMTEXT,
	uninstallcode MEDIUMTEXT,
	PRIMARY KEY (productcodeid),
	KEY (productid)
)
";
$schema['CREATE']['explain']['productcode'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "productcode");



$schema['CREATE']['query']['productdependency'] = "
CREATE TABLE " . TABLE_PREFIX . "productdependency (
	productdependencyid INT NOT NULL AUTO_INCREMENT,
	productid varchar(25) NOT NULL DEFAULT '',
	dependencytype varchar(25) NOT NULL DEFAULT '',
	parentproductid varchar(25) NOT NULL DEFAULT '',
	minversion varchar(50) NOT NULL DEFAULT '',
	maxversion varchar(50) NOT NULL DEFAULT '',
	PRIMARY KEY (productdependencyid)
)
";
$schema['CREATE']['explain']['productdependency'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "productdependency");


$schema['CREATE']['query']['profileblockprivacy'] = "
CREATE TABLE " . TABLE_PREFIX . "profileblockprivacy (
	userid INT UNSIGNED NOT NULL,
	blockid varchar(255) NOT NULL,
	requirement SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid, blockid)
)
";
$schema['CREATE']['explain']['profileblockprivacy'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "profileblockprivacy");


$schema['CREATE']['query']['profilefield'] = "
CREATE TABLE " . TABLE_PREFIX . "profilefield (
	profilefieldid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	profilefieldcategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	required SMALLINT NOT NULL DEFAULT '0',
	hidden SMALLINT NOT NULL DEFAULT '0',
	maxlength SMALLINT NOT NULL DEFAULT '250',
	size SMALLINT NOT NULL DEFAULT '25',
	displayorder SMALLINT NOT NULL DEFAULT '0',
	editable SMALLINT NOT NULL DEFAULT '1',
	type ENUM('input','select','radio','textarea','checkbox','select_multiple') NOT NULL DEFAULT 'input',
	data MEDIUMTEXT,
	height SMALLINT NOT NULL DEFAULT '0',
	def SMALLINT NOT NULL DEFAULT '0',
	optional SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	searchable SMALLINT NOT NULL DEFAULT '0',
	memberlist SMALLINT NOT NULL DEFAULT '0',
	regex VARCHAR(255) NOT NULL DEFAULT '',
	form SMALLINT NOT NULL DEFAULT '0',
	html SMALLINT NOT NULL DEFAULT '0',
	perline SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (profilefieldid),
	KEY editable (editable),
	KEY profilefieldcategoryid (profilefieldcategoryid)
)
";
$schema['CREATE']['explain']['profilefield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "profilefield");



$schema['CREATE']['query']['profilefieldcategory'] = "
CREATE TABLE " . TABLE_PREFIX . "profilefieldcategory (
	profilefieldcategoryid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	displayorder SMALLINT NOT NULL DEFAULT '0',
	location VARCHAR(25) NOT NULL DEFAULT '',
	allowprivacy SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (profilefieldcategoryid)
)
";
$schema['CREATE']['explain']['profilefieldcategory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "profilefieldcategory");



$schema['CREATE']['query']['profilevisitor'] = "
CREATE TABLE " . TABLE_PREFIX . "profilevisitor (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	visitorid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	visible SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (visitorid, userid),
	KEY userid (userid, visible, dateline)
)
";
$schema['CREATE']['explain']['profilevisitor'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "profilevisitor");



$schema['CREATE']['query']['ranks'] = "
CREATE TABLE " . TABLE_PREFIX . "ranks (
	rankid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	minposts INT UNSIGNED NOT NULL DEFAULT '0',
	ranklevel SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	rankimg MEDIUMTEXT,
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	stack SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	display SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (rankid),
	KEY grouprank (usergroupid, minposts)
)
";
$schema['CREATE']['explain']['ranks'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "ranks");



$schema['CREATE']['query']['visitormessage'] = "
CREATE TABLE " . TABLE_PREFIX . "visitormessage (
	vmid INT UNSIGNED NOT NULL auto_increment,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	postusername VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	state ENUM('visible','moderation','deleted') NOT NULL default 'visible',
	title VARCHAR(255) NOT NULL DEFAULT '',
	pagetext MEDIUMTEXT,
	ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
	allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (vmid),
	KEY postuserid (postuserid, userid, state),
	KEY userid (userid, dateline, state)
)
";
$schema['CREATE']['explain']['visitormessage'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "visitormessage");



$schema['CREATE']['query']['visitormessage_hash'] = "
CREATE TABLE " . TABLE_PREFIX . "visitormessage_hash
(
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dupehash VARCHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY postuserid (postuserid, dupehash),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['visitormessage_hash'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "visitormessage_hash");



$schema['CREATE']['query']['reminder'] = "
CREATE TABLE " . TABLE_PREFIX . "reminder (
	reminderid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(50) NOT NULL DEFAULT '',
	text MEDIUMTEXT,
	duedate INT UNSIGNED NOT NULL DEFAULT '0',
	adminonly SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	completedby INT UNSIGNED NOT NULL DEFAULT '0',
	completedtime INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (reminderid)
)
";
$schema['CREATE']['explain']['reminder'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reminder");



$schema['CREATE']['query']['reputation'] = "
CREATE TABLE " . TABLE_PREFIX . "reputation (
	reputationid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	postid INT UNSIGNED NOT NULL DEFAULT '1',
	userid INT UNSIGNED NOT NULL DEFAULT '1',
	reputation INT NOT NULL DEFAULT '0',
	whoadded INT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(250) DEFAULT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (reputationid),
	KEY userid (userid),
	UNIQUE KEY whoadded_postid (whoadded, postid),
	KEY multi (postid, userid),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['reputation'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reputation");



$schema['CREATE']['query']['reputationlevel'] = "
CREATE TABLE " . TABLE_PREFIX . "reputationlevel (
	reputationlevelid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	minimumreputation INT NOT NULL DEFAULT '0',
	PRIMARY KEY (reputationlevelid),
	KEY reputationlevel (minimumreputation)
)
";
$schema['CREATE']['explain']['reputationlevel'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reputationlevel");



$schema['CREATE']['query']['route'] = "
CREATE TABLE " . TABLE_PREFIX . "route (
	routeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userrequest VARCHAR(50) NOT NULL,
	packageid INT UNSIGNED NOT NULL,
	class VARBINARY(50) NOT NULL,
	PRIMARY KEY (routeid),
	UNIQUE KEY (userrequest),
	UNIQUE KEY(packageid, class)
)
";
$schema['CREATE']['explain']['route'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "route");



$schema['CREATE']['query']['rssfeed'] = "
CREATE TABLE " . TABLE_PREFIX . "rssfeed (
	rssfeedid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	url TEXT,
	port SMALLINT UNSIGNED NOT NULL DEFAULT '80',
	ttl SMALLINT UNSIGNED NOT NULL DEFAULT '1500',
	maxresults SMALLINT NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	prefixid VARCHAR(25) NOT NULL DEFAULT '',
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	titletemplate MEDIUMTEXT,
	bodytemplate MEDIUMTEXT,
	searchwords MEDIUMTEXT,
	itemtype ENUM('thread','announcement') NOT NULL DEFAULT 'thread',
	threadactiondelay SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	endannouncement INT UNSIGNED NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	lastrun INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (rssfeedid),
	KEY lastrun (lastrun)
)
";
$schema['CREATE']['explain']['rssfeed'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "rssfeed");



$schema['CREATE']['query']['rsslog'] = "
CREATE TABLE " . TABLE_PREFIX . "rsslog (
	rssfeedid INT UNSIGNED NOT NULL DEFAULT '0',
	itemid INT UNSIGNED NOT NULL DEFAULT '0',
	itemtype ENUM('thread','announcement') NOT NULL DEFAULT 'thread',
	uniquehash CHAR(32) NOT NULL DEFAULT '',
	contenthash CHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	threadactiontime INT UNSIGNED NOT NULL DEFAULT '0',
	threadactioncomplete TINYINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (rssfeedid, itemid, itemtype),
	UNIQUE KEY uniquehash (uniquehash)
)
";
$schema['CREATE']['explain']['rsslog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "rsslog");


$schema['CREATE']['query']['searchcore'] = "
CREATE TABLE " . TABLE_PREFIX . "searchcore (
	searchcoreid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	contenttypeid INT UNSIGNED NOT NULL,
	primaryid INT UNSIGNED  NOT NULL,
	groupcontenttypeid INT UNSIGNED NOT NULL,
	groupid INT UNSIGNED NOT NULL DEFAULT 0,
	dateline INT UNSIGNED NOT NULL DEFAULT 0,
	userid INT UNSIGNED NOT NULL DEFAULT 0,
	username VARCHAR(100) NOT NULL,
	ipaddress INT UNSIGNED NOT NULL,
	searchgroupid INT UNSIGNED NOT NULL,
	PRIMARY KEY (searchcoreid),
	UNIQUE KEY contentunique (contenttypeid, primaryid),
	KEY groupid (groupcontenttypeid, groupid),
	KEY ipaddress (ipaddress),
	KEY dateline (dateline),
	KEY userid (userid),
	KEY searchgroupid (searchgroupid)
)	ENGINE = $hightrafficengine
";
$schema['CREATE']['explain']['searchcore'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "searchcore");


$schema['CREATE']['query']['searchcore_text'] = "
CREATE TABLE " . TABLE_PREFIX . "searchcore_text (
	searchcoreid INT UNSIGNED NOT NULL,
	keywordtext MEDIUMTEXT,
	title VARCHAR(254) NOT NULL DEFAULT '',
	PRIMARY KEY (searchcoreid),
	FULLTEXT KEY text (title, keywordtext)
)	ENGINE = MyISAM
";
$schema['CREATE']['explain']['searchcore_text'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "searchcore_text");


$schema['CREATE']['query']['searchgroup'] = "
CREATE TABLE " . TABLE_PREFIX . "searchgroup (
	searchgroupid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	contenttypeid INT UNSIGNED NOT NULL,
	groupid INT UNSIGNED  NOT NULL,
	dateline INT UNSIGNED NOT NULL DEFAULT 0,
	userid INT UNSIGNED NOT NULL DEFAULT 0,
	username VARCHAR(100) NOT NULL,
	PRIMARY KEY (searchgroupid),
	UNIQUE KEY groupunique (contenttypeid, groupid),
	KEY dateline (dateline),
	KEY userid (userid)
)	ENGINE = $hightrafficengine
";
$schema['CREATE']['explain']['searchgroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "searchgroup");


$schema['CREATE']['query']['searchgroup_text'] = "
CREATE TABLE " . TABLE_PREFIX . "searchgroup_text (
	searchgroupid INT UNSIGNED NOT NULL,
	title VARCHAR(254) NOT NULL,
	PRIMARY KEY (searchgroupid),
	FULLTEXT KEY grouptitle (title)
)	ENGINE = MyISAM
";
$schema['CREATE']['explain']['searchgroup_text'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "searchgroup_text");


$schema['CREATE']['query']['searchlog'] = "
CREATE TABLE " . TABLE_PREFIX . "searchlog (
	searchlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	ipaddress VARCHAR(15) NOT NULL DEFAULT '',
	searchhash VARCHAR(32) NOT NULL,
	sortby VARCHAR(15) NOT NULL DEFAULT '',
	sortorder ENUM('asc','desc') NOT NULL DEFAULT 'asc',
	searchtime FLOAT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	completed SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	criteria MEDIUMTEXT NOT NULL,
	results MEDIUMBLOB,
	PRIMARY KEY (searchlogid),
	KEY search (userid, searchhash, sortby, sortorder),
	KEY userfloodcheck (userid, dateline),
	KEY ipfloodcheck (ipaddress, dateline)
)	ENGINE = $hightrafficengine
";
$schema['CREATE']['explain']['searchlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "searchlog");


$schema['CREATE']['query']['session'] = "
CREATE TABLE " . TABLE_PREFIX . "session (
	sessionhash CHAR(32) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	host CHAR(15) NOT NULL DEFAULT '',
	idhash CHAR(32) NOT NULL DEFAULT '',
	lastactivity INT UNSIGNED NOT NULL DEFAULT '0',
	location CHAR(255) NOT NULL DEFAULT '',
	useragent CHAR(100) NOT NULL DEFAULT '',
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	loggedin SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	inforum SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	inthread INT UNSIGNED NOT NULL DEFAULT '0',
	incalendar SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	badlocation SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	bypass TINYINT NOT NULL DEFAULT '0',
	profileupdate SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	apiclientid INT UNSIGNED NOT NULL DEFAULT '0',
	apiaccesstoken VARCHAR(32) NOT NULL DEFAULT '',
	isbot TINYINT NOT NULL DEFAULT '0',
	PRIMARY KEY (sessionhash),
	KEY user_activity (userid, lastactivity),
	KEY guest_lookup (idhash, host, userid),
	KEY apiaccesstoken (apiaccesstoken)
)
";
$schema['CREATE']['explain']['session'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "session");



$schema['CREATE']['query']['setting'] = "
CREATE TABLE " . TABLE_PREFIX . "setting (
	varname VARCHAR(100) NOT NULL DEFAULT '',
	grouptitle VARCHAR(50) NOT NULL DEFAULT '',
	value MEDIUMTEXT,
	defaultvalue MEDIUMTEXT,
	optioncode MEDIUMTEXT,
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	advanced SMALLINT NOT NULL DEFAULT '0',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username', 'integer', 'posint') NOT NULL DEFAULT 'free',
	product VARCHAR(25) NOT NULL DEFAULT '',
	validationcode TEXT,
	blacklist SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (varname)
)
";
$schema['CREATE']['explain']['setting'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "setting");



$schema['CREATE']['query']['settinggroup'] = "
CREATE TABLE " . TABLE_PREFIX . "settinggroup (
	grouptitle CHAR(50) NOT NULL DEFAULT '',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	product VARCHAR(25) NOT NULL DEFAULT '',
	PRIMARY KEY (grouptitle)
)
";
$schema['CREATE']['explain']['settinggroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "settinggroup");



$schema['CREATE']['query']['sigparsed'] = "
CREATE TABLE " . TABLE_PREFIX . "sigparsed (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	signatureparsed MEDIUMTEXT,
	hasimages SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid, styleid, languageid)
)
";
$schema['CREATE']['explain']['sigparsed'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "sigparsed");



$schema['CREATE']['query']['smilie'] = "
CREATE TABLE " . TABLE_PREFIX . "smilie (
	smilieid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title CHAR(100) NOT NULL DEFAULT '',
	smilietext CHAR(20) NOT NULL DEFAULT '',
	smiliepath CHAR(100) NOT NULL DEFAULT '',
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (smilieid)
)
";
$schema['CREATE']['explain']['smilie'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "smilie");



$schema['CREATE']['query']['spamlog'] = "
CREATE TABLE " . TABLE_PREFIX . "spamlog (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (postid)
)
";
$schema['CREATE']['explain']['spamlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "spamlog");



$schema['CREATE']['query']['socialgroup'] = "
CREATE TABLE " . TABLE_PREFIX . "socialgroup (
	groupid INT unsigned NOT NULL auto_increment,
	socialgroupcategoryid INT unsigned NOT NULL,
	name VARCHAR(255) NOT NULL DEFAULT '',
	description TEXT,
	creatoruserid INT unsigned NOT NULL DEFAULT '0',
	dateline INT unsigned NOT NULL DEFAULT '0',
	members INT unsigned NOT NULL DEFAULT '0',
	picturecount INT unsigned NOT NULL DEFAULT '0',
	lastpost INT unsigned NOT NULL DEFAULT '0',
	lastposter VARCHAR(255) NOT NULL DEFAULT '',
	lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
	lastgmid INT UNSIGNED NOT NULL DEFAULT '0',
	visible INT UNSIGNED NOT NULL DEFAULT '0',
	deleted INT UNSIGNED NOT NULL DEFAULT '0',
	moderation INT UNSIGNED NOT NULL DEFAULT '0',
	type ENUM('public', 'moderated', 'inviteonly') NOT NULL default 'public',
	moderatedmembers INT UNSIGNED NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	lastdiscussionid INT UNSIGNED NOT NULL DEFAULT '0',
	discussions INT UNSIGNED DEFAULT NULL DEFAULT '0',
	lastdiscussion VARCHAR(255) NOT NULL DEFAULT '',
	lastupdate INT UNSIGNED NOT NULL DEFAULT '0',
	transferowner INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (groupid),
	KEY creatoruserid (creatoruserid),
	KEY dateline (dateline),
	KEY members (members),
	KEY picturecount (picturecount),
	KEY visible (visible),
	KEY lastpost (lastpost),
	KEY socialgroupcategoryid (socialgroupcategoryid)
)
";
$schema['CREATE']['explain']['socialgroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgroup");



$schema['CREATE']['query']['socialgroupcategory'] = "
CREATE TABLE " . TABLE_PREFIX . "socialgroupcategory (
	socialgroupcategoryid INT unsigned NOT NULL auto_increment,
	creatoruserid INT unsigned NOT NULL,
	title VARCHAR(250) NOT NULL,
	description TEXT NOT NULL,
	displayorder INT unsigned NOT NULL,
	lastupdate INT unsigned NOT NULL,
	groups INT unsigned DEFAULT '0',
	PRIMARY KEY  (socialgroupcategoryid),
	KEY displayorder (displayorder)
)
";
$schema['CREATE']['explain']['socialgroupcategory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgroupcategory");



$schema['CREATE']['query']['socialgroupicon'] = "
CREATE TABLE " . TABLE_PREFIX . "socialgroupicon (
	groupid INT unsigned NOT NULL default '0',
	userid INT unsigned default '0',
	filedata mediumblob,
	extension VARCHAR(20) NOT NULL default '',
	dateline INT unsigned NOT NULL default '0',
	width INT unsigned NOT NULL default '0',
	height INT unsigned NOT NULL default '0',
	thumbnail_filedata mediumblob,
	thumbnail_width INT unsigned NOT NULL default '0',
	thumbnail_height INT unsigned NOT NULL default '0',
	PRIMARY KEY  (groupid))
";
$schema['CREATE']['explain']['socialgroupicon'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgroupicon");



$schema['CREATE']['query']['socialgroupmember'] = "
CREATE TABLE " . TABLE_PREFIX . "socialgroupmember (
	userid INT unsigned NOT NULL DEFAULT '0',
	groupid INT unsigned NOT NULL DEFAULT '0',
	dateline INT unsigned NOT NULL DEFAULT '0',
	type ENUM('member', 'moderated', 'invited') NOT NULL default 'member',
	PRIMARY KEY (groupid, userid),
	KEY groupid (groupid, type),
	KEY userid (userid, type)
)
";
$schema['CREATE']['explain']['socialgroupmember'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "socialgroupmember");



$schema['CREATE']['query']['stats'] = "
CREATE TABLE " . TABLE_PREFIX . "stats (
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	nuser mediumint UNSIGNED NOT NULL DEFAULT '0',
	nthread mediumint UNSIGNED NOT NULL DEFAULT '0',
	npost mediumint UNSIGNED NOT NULL DEFAULT '0',
	ausers mediumint UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (dateline)
)
";
$schema['CREATE']['explain']['stats'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "stats");



$schema['CREATE']['query']['strikes'] = "
CREATE TABLE " . TABLE_PREFIX . "strikes (
	striketime INT UNSIGNED NOT NULL DEFAULT '0',
	strikeip CHAR(15) NOT NULL DEFAULT '',
	username VARCHAR(100) NOT NULL DEFAULT '',
	KEY striketime (striketime),
	KEY strikeip (strikeip)
)
";
$schema['CREATE']['explain']['strikes'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "strikes");



$schema['CREATE']['query']['style'] = "
CREATE TABLE " . TABLE_PREFIX . "style (
	styleid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	parentid SMALLINT NOT NULL DEFAULT '0',
	parentlist VARCHAR(250) NOT NULL DEFAULT '',
	templatelist MEDIUMTEXT,
	csscolors MEDIUMTEXT,
	css MEDIUMTEXT,
	stylevars MEDIUMTEXT,
	newstylevars MEDIUMTEXT,
	replacements MEDIUMTEXT,
	editorstyles MEDIUMTEXT,
	userselect SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (styleid)
)
";
$schema['CREATE']['explain']['style'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "style");



$schema['CREATE']['query']['stylevar'] = "
CREATE TABLE " . TABLE_PREFIX . "stylevar (
	stylevarid varchar(250) NOT NULL,
	styleid SMALLINT NOT NULL DEFAULT '-1',
	value MEDIUMBLOB NOT NULL,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	UNIQUE KEY stylevarinstance (stylevarid, styleid)
)
";
$schema['CREATE']['explain']['stylevar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "stylevar");



$schema['CREATE']['query']['stylevardfn'] = "
CREATE TABLE " . TABLE_PREFIX . "stylevardfn (
	stylevarid varchar(250) NOT NULL,
	styleid SMALLINT NOT NULL DEFAULT '-1',
	parentid SMALLINT NOT NULL,
	parentlist varchar(250) NOT NULL DEFAULT '0',
	stylevargroup varchar(250) NOT NULL,
	product varchar(25) NOT NULL default 'vbulletin',
	datatype varchar(25) NOT NULL default 'string',
	validation varchar(250) NOT NULL,
	failsafe MEDIUMBLOB NOT NULL,
	units enum('','%','px','pt','em','ex','pc','in','cm','mm') NOT NULL default '',
	uneditable tinyint(3) unsigned NOT NULL default '0',
	PRIMARY KEY (stylevarid)
)
";
$schema['CREATE']['explain']['stylevardfn'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "stylevardfn");




$schema['CREATE']['query']['subscribediscussion'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribediscussion (
	subscribediscussionid INT unsigned NOT NULL auto_increment,
	userid INT unsigned NOT NULL,
	discussionid INT unsigned NOT NULL,
	emailupdate SMALLINT unsigned NOT NULL default '0',
	PRIMARY KEY (subscribediscussionid),
	UNIQUE KEY userdiscussion (userid, discussionid),
	KEY discussionid (discussionid)
)
";
$schema['CREATE']['explain']['subscribediscussion'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribediscussion");



$schema['CREATE']['query']['subscribeevent'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribeevent (
	subscribeeventid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	eventid INT UNSIGNED NOT NULL DEFAULT '0',
	lastreminder INT UNSIGNED NOT NULL DEFAULT '0',
	reminder INT UNSIGNED NOT NULL DEFAULT '3600',
	PRIMARY KEY (subscribeeventid),
	UNIQUE KEY subindex (userid, eventid),
	KEY eventid (eventid)
)
";
$schema['CREATE']['explain']['subscribeevent'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribeevent");



$schema['CREATE']['query']['subscribegroup'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribegroup (
	subscribegroupid INT unsigned NOT NULL auto_increment,
	userid INT unsigned NOT NULL,
	groupid INT unsigned NOT NULL,
	emailupdate ENUM('daily', 'weekly', 'none') NOT NULL DEFAULT 'none',
	PRIMARY KEY  (subscribegroupid),
	UNIQUE KEY usergroup (userid, groupid),
	KEY groupid (groupid)
)
";
$schema['CREATE']['explain']['subscribegroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribegroup");



$schema['CREATE']['query']['subscribeforum'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribeforum (
	subscribeforumid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	emailupdate SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscribeforumid),
	UNIQUE KEY subindex (userid, forumid),
	KEY forumid (forumid)
)
";
$schema['CREATE']['explain']['subscribeforum'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribeforum");



$schema['CREATE']['query']['subscribethread'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribethread (
	subscribethreadid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	emailupdate SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	folderid INT UNSIGNED NOT NULL DEFAULT '0',
	canview SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (subscribethreadid),
	UNIQUE KEY threadid (threadid, userid),
	KEY userid (userid, folderid)
)
";
$schema['CREATE']['explain']['subscribethread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribethread");



$schema['CREATE']['query']['subscription'] = "
CREATE TABLE " . TABLE_PREFIX . "subscription (
	subscriptionid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	varname VARCHAR(100) NOT NULL DEFAULT '',
	cost MEDIUMTEXT,
	forums MEDIUMTEXT,
	nusergroupid SMALLINT NOT NULL DEFAULT '0',
	membergroupids VARCHAR(255) NOT NULL DEFAULT '',
	active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	adminoptions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscriptionid)
)
";
$schema['CREATE']['explain']['subscription'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscription");



$schema['CREATE']['query']['subscriptionlog'] = "
CREATE TABLE " . TABLE_PREFIX . "subscriptionlog (
	subscriptionlogid MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	subscriptionid SMALLINT NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	pusergroupid SMALLINT NOT NULL DEFAULT '0',
	status SMALLINT NOT NULL DEFAULT '0',
	regdate INT UNSIGNED NOT NULL DEFAULT '0',
	expirydate INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscriptionlogid),
	KEY userid (userid, subscriptionid),
	KEY subscriptionid (subscriptionid)
)
";
$schema['CREATE']['explain']['subscriptionlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscriptionlog");



$schema['CREATE']['query']['subscriptionpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "subscriptionpermission (
	subscriptionpermissionid INT UNSIGNED NOT NULL auto_increment,
	subscriptionid INT UNSIGNED NOT NULL default '0',
	usergroupid INT UNSIGNED NOT NULL default '0',
	PRIMARY KEY  (subscriptionpermissionid),
	UNIQUE KEY subscriptionid (subscriptionid,usergroupid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['subscriptionpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscriptionpermission");



$schema['CREATE']['query']['tachyforumpost'] = "
CREATE TABLE " . TABLE_PREFIX . "tachyforumpost (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT UNSIGNED NOT NULL DEFAULT '0',
	lastposter VARCHAR(100) NOT NULL DEFAULT '',
	lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpostid INT UNSIGNED NOT NULL DEFAULT '0',
	lastthread VARCHAR(250) NOT NULL DEFAULT '',
	lastthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	lasticonid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	lastprefixid VARCHAR(25) NOT NULL DEFAULT '',
	PRIMARY KEY (userid, forumid),
	KEY (forumid)
)
";
$schema['CREATE']['explain']['tachyforumpost'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachyforumpost");



$schema['CREATE']['query']['tachyforumcounter'] = "
CREATE TABLE " . TABLE_PREFIX . "tachyforumcounter (
  userid int(10) unsigned NOT NULL default '0',
  forumid smallint(5) unsigned NOT NULL default '0',
  threadcount mediumint(8) unsigned NOT NULL default '0',
  replycount int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (userid,forumid)
)
";
$schema['CREATE']['explain']['tachyforumcounter'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachyforumcounter");



$schema['CREATE']['query']['tachythreadpost'] = "
CREATE TABLE " . TABLE_PREFIX . "tachythreadpost (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT UNSIGNED NOT NULL DEFAULT '0',
	lastposter VARCHAR(100) NOT NULL DEFAULT '',
	lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpostid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid, threadid),
	KEY (threadid)
)
";
$schema['CREATE']['explain']['tachythreadpost'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachythreadpost");



$schema['CREATE']['query']['tachythreadcounter'] = "
CREATE TABLE " . TABLE_PREFIX . "tachythreadcounter (
  userid int(10) unsigned NOT NULL default '0',
  threadid int(10) unsigned NOT NULL default '0',
  replycount int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (userid,threadid)
)
";
$schema['CREATE']['explain']['tachythreadcounter'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tachythreadcounter");



$schema['CREATE']['query']['tag'] = "
CREATE TABLE " . TABLE_PREFIX . "tag (
	tagid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	tagtext VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	canonicaltagid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (tagid),
	UNIQUE KEY tagtext (tagtext),
	KEY canonicaltagid (canonicaltagid)
)	ENGINE = $hightrafficengine
";
$schema['CREATE']['explain']['tag'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tag");

$schema['CREATE']['query']['tagcontent'] = "
CREATE TABLE " . TABLE_PREFIX . "tagcontent (
	tagid INT UNSIGNED NOT NULL DEFAULT 0,
	contenttypeid INT UNSIGNED NOT NULL,
	contentid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY tag_type_cid (tagid, contenttypeid, contentid),
	KEY id_type_user (contentid, contenttypeid, userid),
	KEY user (userid),
	KEY dateline (dateline)
)	ENGINE = $hightrafficengine
";
$schema['CREATE']['explain']['tagcontent'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tagcontent");

$schema['CREATE']['query']['tagsearch'] = "
CREATE TABLE " . TABLE_PREFIX . "tagsearch (
	tagid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY (tagid)
)
";
$schema['CREATE']['explain']['tagsearch'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "tagsearch");



// IMPORTANT!!!! Update the template_temp table in adminfunctions_template.php whenever this table is altered
$schema['CREATE']['query']['template'] = "
CREATE TABLE " . TABLE_PREFIX . "template (
	templateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	styleid SMALLINT NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	template MEDIUMTEXT,
	template_un MEDIUMTEXT,
	templatetype ENUM('template','stylevar','css','replacement') NOT NULL DEFAULT 'template',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	version VARCHAR(30) NOT NULL DEFAULT '',
	product VARCHAR(25) NOT NULL DEFAULT '',
	mergestatus ENUM('none', 'merged', 'conflicted') NOT NULL DEFAULT 'none',
	PRIMARY KEY (templateid),
	UNIQUE KEY title (title, styleid, templatetype),
	KEY styleid (styleid)
)
";
$schema['CREATE']['explain']['template'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "template");



$schema['CREATE']['query']['templatehistory'] = "
CREATE TABLE " . TABLE_PREFIX . "templatehistory (
	templatehistoryid int(10) unsigned NOT NULL auto_increment,
	styleid smallint NOT NULL default '0',
	title varchar(100) NOT NULL default '',
	template MEDIUMTEXT,
	dateline int(10) unsigned NOT NULL default '0',
	username varchar(100) NOT NULL default '',
	version varchar(30) NOT NULL default '',
	comment varchar(255) NOT NULL default '',
	PRIMARY KEY (templatehistoryid),
	KEY title (title, styleid)
)
";
$schema['CREATE']['explain']['templatehistory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "templatehistory");



$schema['CREATE']['query']['templatemerge'] = "
CREATE TABLE " . TABLE_PREFIX . "templatemerge (
	templateid INT UNSIGNED NOT NULL DEFAULT '0',
	template MEDIUMTEXT NOT NULL,
	version VARCHAR(30) NOT NULL DEFAULT '',
	savedtemplateid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (templateid)
)
";
$schema['CREATE']['explain']['templatemerge'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "templatemerge");



$schema['CREATE']['query']['thread'] = "
CREATE TABLE " . TABLE_PREFIX . "thread (
	threadid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	prefixid VARCHAR(25) NOT NULL DEFAULT '',
	firstpostid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpostid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pollid INT UNSIGNED NOT NULL DEFAULT '0',
	open SMALLINT NOT NULL DEFAULT '0',
	replycount INT UNSIGNED NOT NULL DEFAULT '0',
	hiddencount INT UNSIGNED NOT NULL DEFAULT '0',
	deletedcount INT UNSIGNED NOT NULL DEFAULT '0',
	postusername VARCHAR(100) NOT NULL DEFAULT '',
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	lastposter VARCHAR(100) NOT NULL DEFAULT '',
	lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	views INT UNSIGNED NOT NULL DEFAULT '0',
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	notes VARCHAR(250) NOT NULL DEFAULT '',
	visible SMALLINT NOT NULL DEFAULT '0',
	sticky SMALLINT NOT NULL DEFAULT '0',
	votenum SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	votetotal SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	attach SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	similar VARCHAR(55) NOT NULL DEFAULT '',
	taglist MEDIUMTEXT,
	keywords MEDIUMTEXT,
	PRIMARY KEY (threadid),
	KEY postuserid (postuserid),
	KEY pollid (pollid),
	KEY forumid (forumid, visible, sticky, lastpost),
	KEY forumid_lastpost(forumid, lastpost),
	KEY lastpost (lastpost),
	KEY dateline (dateline),
	KEY prefixid (prefixid, forumid)
)
";
$schema['CREATE']['explain']['thread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "thread");



$schema['CREATE']['query']['threadrate'] = "
CREATE TABLE " . TABLE_PREFIX . "threadrate (
	threadrateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	vote SMALLINT NOT NULL DEFAULT '0',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	PRIMARY KEY (threadrateid),
	KEY threadid (threadid, userid)
)
";
$schema['CREATE']['explain']['threadrate'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadrate");



$schema['CREATE']['query']['threadread'] = "
CREATE TABLE " . TABLE_PREFIX . "threadread (
	userid int(10) unsigned NOT NULL default '0',
	threadid int(10) unsigned NOT NULL default '0',
	readtime int(10) unsigned NOT NULL default '0',
	PRIMARY KEY  (userid, threadid),
	KEY readtime (readtime)
)
";
$schema['CREATE']['explain']['threadread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadread");



$schema['CREATE']['query']['threadredirect'] = "
CREATE TABLE " . TABLE_PREFIX . "threadredirect (
	threadid INT UNSIGNED NOT NULL default '0',
	expires INT UNSIGNED NOT NULL default '0',
	PRIMARY KEY (threadid),
	KEY expires (expires)
)
";
$schema['CREATE']['explain']['threadredirect'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadredirect");



$schema['CREATE']['query']['threadviews'] = "
CREATE TABLE " . TABLE_PREFIX . "threadviews (
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	KEY threadid (threadid)
)
";
$schema['CREATE']['explain']['threadviews'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadviews");



$schema['CREATE']['query']['upgradelog'] = "
CREATE TABLE " . TABLE_PREFIX . "upgradelog (
	upgradelogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	script VARCHAR(50) NOT NULL DEFAULT '',
	steptitle VARCHAR(250) NOT NULL DEFAULT '',
	step smallint(5) UNSIGNED NOT NULL DEFAULT '0',
	startat INT UNSIGNED NOT NULL DEFAULT '0',
	perpage SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	only TINYINT NOT NULL DEFAULT '0',
	PRIMARY KEY (upgradelogid)
)
";
$schema['CREATE']['explain']['upgradelog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "upgradelog");



$schema['CREATE']['query']['user'] = "
CREATE TABLE " . TABLE_PREFIX . "user (
	userid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	membergroupids CHAR(250) NOT NULL DEFAULT '',
	displaygroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	password CHAR(32) NOT NULL DEFAULT '',
	passworddate date NOT NULL DEFAULT '0000-00-00',
	email CHAR(100) NOT NULL DEFAULT '',
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	parentemail CHAR(50) NOT NULL DEFAULT '',
	homepage CHAR(100) NOT NULL DEFAULT '',
	icq CHAR(20) NOT NULL DEFAULT '',
	aim CHAR(20) NOT NULL DEFAULT '',
	yahoo CHAR(32) NOT NULL DEFAULT '',
	msn CHAR(100) NOT NULL DEFAULT '',
	skype CHAR(32) NOT NULL DEFAULT '',
	showvbcode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	showbirthday SMALLINT UNSIGNED NOT NULL DEFAULT '2',
	usertitle CHAR(250) NOT NULL DEFAULT '',
	customtitle SMALLINT NOT NULL DEFAULT '0',
	joindate INT UNSIGNED NOT NULL DEFAULT '0',
	daysprune SMALLINT NOT NULL DEFAULT '0',
	lastvisit INT UNSIGNED NOT NULL DEFAULT '0',
	lastactivity INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT UNSIGNED NOT NULL DEFAULT '0',
	lastpostid INT UNSIGNED NOT NULL DEFAULT '0',
	posts INT UNSIGNED NOT NULL DEFAULT '0',
	reputation INT NOT NULL DEFAULT '10',
	reputationlevelid INT UNSIGNED NOT NULL DEFAULT '1',
	timezoneoffset CHAR(4) NOT NULL DEFAULT '',
	pmpopup SMALLINT NOT NULL DEFAULT '0',
	avatarid SMALLINT NOT NULL DEFAULT '0',
	avatarrevision INT UNSIGNED NOT NULL DEFAULT '0',
	profilepicrevision INT UNSIGNED NOT NULL DEFAULT '0',
	sigpicrevision INT UNSIGNED NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '33570831',
	birthday CHAR(10) NOT NULL DEFAULT '',
	birthday_search DATE NOT NULL DEFAULT '0000-00-00',
	maxposts SMALLINT NOT NULL DEFAULT '-1',
	startofweek SMALLINT NOT NULL DEFAULT '1',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	referrerid INT UNSIGNED NOT NULL DEFAULT '0',
	languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	emailstamp INT UNSIGNED NOT NULL DEFAULT '0',
	threadedmode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	autosubscribe SMALLINT NOT NULL DEFAULT '-1',
	pmtotal SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pmunread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	salt CHAR(30) NOT NULL DEFAULT '',
	ipoints INT UNSIGNED NOT NULL DEFAULT '0',
	infractions INT UNSIGNED NOT NULL DEFAULT '0',
	warnings INT UNSIGNED NOT NULL DEFAULT '0',
	infractiongroupids VARCHAR (255) NOT NULL DEFAULT '',
	infractiongroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	adminoptions INT UNSIGNED NOT NULL DEFAULT '0',
	profilevisits INT UNSIGNED NOT NULL DEFAULT '0',
	friendcount INT UNSIGNED NOT NULL DEFAULT '0',
	friendreqcount INT UNSIGNED NOT NULL DEFAULT '0',
	vmunreadcount INT UNSIGNED NOT NULL DEFAULT '0',
	vmmoderatedcount INT UNSIGNED NOT NULL DEFAULT '0',
	socgroupinvitecount INT UNSIGNED NOT NULL DEFAULT '0',
	socgroupreqcount INT UNSIGNED NOT NULL DEFAULT '0',
	pcunreadcount INT UNSIGNED NOT NULL DEFAULT '0',
	pcmoderatedcount INT UNSIGNED NOT NULL DEFAULT '0',
	gmmoderatedcount INT UNSIGNED NOT NULL DEFAULT '0',
	assetposthash VARCHAR(32) NOT NULL DEFAULT '',
	fbuserid VARCHAR(255) NOT NULL DEFAULT '',
	fbjoindate INT UNSIGNED NOT NULL DEFAULT '0',
	fbname VARCHAR(255) NOT NULL DEFAULT '',
	logintype ENUM('vb', 'fb') NOT NULL DEFAULT 'vb',
	fbaccesstoken VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (userid),
	KEY usergroupid (usergroupid),
	KEY username (username),
	KEY birthday (birthday, showbirthday),
	KEY birthday_search (birthday_search),
	KEY referrerid (referrerid),
	INDEX (fbuserid),
	INDEX (email)
)
";
$schema['CREATE']['explain']['user'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "user");



$schema['CREATE']['query']['useractivation'] = "
CREATE TABLE " . TABLE_PREFIX . "useractivation (
	useractivationid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	activationid VARCHAR(40) NOT NULL DEFAULT '',
	type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	emailchange SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (useractivationid),
	UNIQUE KEY userid (userid, type)
)
";
$schema['CREATE']['explain']['useractivation'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "useractivation");



$schema['CREATE']['query']['userban'] = "
CREATE TABLE " . TABLE_PREFIX . "userban (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displaygroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usertitle VARCHAR(250) NOT NULL DEFAULT '',
	customtitle SMALLINT NOT NULL DEFAULT '0',
	adminid INT UNSIGNED NOT NULL DEFAULT '0',
	bandate INT UNSIGNED NOT NULL DEFAULT '0',
	liftdate INT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (userid),
	KEY liftdate (liftdate)
)
";
$schema['CREATE']['explain']['userban'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userban");



$schema['CREATE']['query']['usercss'] = "
CREATE TABLE " . TABLE_PREFIX . "usercss (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	selector VARCHAR(30) NOT NULL DEFAULT '',
	property VARCHAR(30) NOT NULL DEFAULT '',
	value VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (userid, selector, property),
	KEY property (property, userid, value(20))
)
";
$schema['CREATE']['explain']['usercss'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usercss");



$schema['CREATE']['query']['usercsscache'] = "
CREATE TABLE " . TABLE_PREFIX . "usercsscache (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	cachedcss TEXT,
	buildpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['usercsscache'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usercsscache");



$schema['CREATE']['query']['userchangelog'] = "
CREATE TABLE " . TABLE_PREFIX . "userchangelog (
	changeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	fieldname VARCHAR(250) NOT NULL DEFAULT '',
	newvalue VARCHAR(250) NOT NULL DEFAULT '',
	oldvalue VARCHAR(250) NOT NULL DEFAULT '',
	adminid INT UNSIGNED NOT NULL DEFAULT '0',
	change_time INT UNSIGNED NOT NULL DEFAULT '0',
	change_uniq VARCHAR(32) NOT NULL DEFAULT '',
	ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY  (changeid),
	KEY userid (userid,change_time),
	KEY change_time (change_time),
	KEY change_uniq (change_uniq),
	KEY fieldname (fieldname,change_time),
	KEY adminid (adminid,change_time)
)
";
$schema['CREATE']['explain']['userchangelog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userchangelog");



$schema['CREATE']['query']['userfield'] = "
CREATE TABLE " . TABLE_PREFIX . "userfield (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	temp MEDIUMTEXT,
	field1 MEDIUMTEXT,
	field2 MEDIUMTEXT,
	field3 MEDIUMTEXT,
	field4 MEDIUMTEXT,
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['userfield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userfield");



$schema['CREATE']['query']['usergroup'] = "
CREATE TABLE " . TABLE_PREFIX . "usergroup (
	usergroupid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title CHAR(100) NOT NULL DEFAULT '',
	description VARCHAR(250) NOT NULL DEFAULT '',
	usertitle CHAR(100) NOT NULL DEFAULT '',
	passwordexpires SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	passwordhistory SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pmquota SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pmsendmax SMALLINT UNSIGNED NOT NULL DEFAULT '5',
	opentag CHAR(100) NOT NULL DEFAULT '',
	closetag CHAR(100) NOT NULL DEFAULT '',
	canoverride SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	ispublicgroup SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	forumpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	pmpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	calendarpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	wolpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	adminpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	genericpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	genericpermissions2 INT UNSIGNED NOT NULL DEFAULT '0',
	genericoptions INT UNSIGNED NOT NULL DEFAULT '0',
	signaturepermissions INT UNSIGNED NOT NULL DEFAULT '0',
	visitormessagepermissions INT UNSIGNED NOT NULL DEFAULT '0',
	attachlimit INT UNSIGNED NOT NULL DEFAULT '0',
	avatarmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	avatarmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	avatarmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	profilepicmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	profilepicmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	profilepicmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	sigpicmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	sigpicmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	sigpicmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	sigmaximages SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	sigmaxsizebbcode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	sigmaxchars SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	sigmaxrawchars SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	sigmaxlines SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usercsspermissions INT UNSIGNED NOT NULL DEFAULT '0',
	albumpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	albumpicmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	albumpicmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	albummaxpics INT UNSIGNED NOT NULL DEFAULT '0',
	albummaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	socialgrouppermissions INT UNSIGNED NOT NULL DEFAULT '0',
	pmthrottlequantity INT UNSIGNED NOT NULL DEFAULT '0',
	groupiconmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	maximumsocialgroups INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (usergroupid)
)
";
$schema['CREATE']['explain']['usergroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergroup");



$schema['CREATE']['query']['usergroupleader'] = "
CREATE TABLE " . TABLE_PREFIX . "usergroupleader (
	usergroupleaderid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (usergroupleaderid),
	KEY ugl (userid, usergroupid)
)
";
$schema['CREATE']['explain']['usergroupleader'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergroupleader");



$schema['CREATE']['query']['usergrouprequest'] = "
CREATE TABLE " . TABLE_PREFIX . "usergrouprequest (
	usergrouprequestid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(250) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (usergrouprequestid),
	KEY usergroupid (usergroupid),
	UNIQUE KEY (userid, usergroupid)
)
";
$schema['CREATE']['explain']['usergrouprequest'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergrouprequest");



$schema['CREATE']['query']['userlist'] = "
CREATE TABLE " . TABLE_PREFIX . "userlist (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	relationid INT UNSIGNED NOT NULL DEFAULT '0',
	type ENUM('buddy', 'ignore') NOT NULL DEFAULT 'buddy',
	friend ENUM('yes', 'no', 'pending', 'denied') NOT NULL DEFAULT 'no',
	PRIMARY KEY (userid, relationid, type),
	KEY relationid (relationid, type, friend),
	KEY userid (userid, type, friend)
)
";
$schema['CREATE']['explain']['userlist'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userlist");



$schema['CREATE']['query']['usernote'] = "
CREATE TABLE " . TABLE_PREFIX . "usernote (
	usernoteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	posterid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	message MEDIUMTEXT,
	title VARCHAR(255) NOT NULL DEFAULT '',
	allowsmilies SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (usernoteid),
	KEY userid (userid),
	KEY posterid (posterid)
)
";
$schema['CREATE']['explain']['usernote'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usernote");



$schema['CREATE']['query']['userpromotion'] = "
CREATE TABLE " . TABLE_PREFIX . "userpromotion (
	userpromotionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	usergroupid INT UNSIGNED NOT NULL DEFAULT '0',
	joinusergroupid INT UNSIGNED NOT NULL DEFAULT '0',
	reputation INT NOT NULL DEFAULT '0',
	date INT UNSIGNED NOT NULL DEFAULT '0',
	posts INT UNSIGNED NOT NULL DEFAULT '0',
	strategy SMALLINT NOT NULL DEFAULT '0',
	type SMALLINT NOT NULL DEFAULT '2',
	PRIMARY KEY (userpromotionid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['userpromotion'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userpromotion");



$schema['CREATE']['query']['usertextfield'] = "
CREATE TABLE " . TABLE_PREFIX . "usertextfield (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	subfolders MEDIUMTEXT,
	pmfolders MEDIUMTEXT,
	buddylist MEDIUMTEXT,
	ignorelist MEDIUMTEXT,
	signature MEDIUMTEXT,
	searchprefs MEDIUMTEXT,
	rank MEDIUMTEXT,
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['usertextfield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usertextfield");



$schema['CREATE']['query']['usertitle'] = "
CREATE TABLE " . TABLE_PREFIX . "usertitle (
	usertitleid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	minposts INT UNSIGNED NOT NULL DEFAULT '0',
	title CHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (usertitleid)
)
";
$schema['CREATE']['explain']['usertitle'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usertitle");


$schema['CREATE']['query']['sigpic'] = "
CREATE TABLE " . TABLE_PREFIX . "sigpic (
	userid int(10) unsigned NOT NULL default '0',
	filedata mediumblob,
	dateline int(10) unsigned NOT NULL default '0',
	filename varchar(100) NOT NULL default '',
	visible smallint(6) NOT NULL default '1',
	filesize int(10) unsigned NOT NULL default '0',
	width smallint(5) unsigned NOT NULL default '0',
	height smallint(5) unsigned NOT NULL default '0',
	PRIMARY KEY  (userid)
)
";
$schema['CREATE']['explain']['sigpic'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "sigpic");



// ***************************************************************************************************************************

$altertabletype = array(
	'session'   => 'MEMORY',
	'cpsession' => 'MEMORY',

	// innodb tables are limited to about 10 varchar/blob/text fields http://bugs.mysql.com/bug.php?id=10035
	'language'  => 'MYISAM',
	'userfield' => 'MYISAM',
);

foreach ($altertabletype AS $table => $type)
{
	$schema['ALTER']['query']["$table"] = "ALTER TABLE " . TABLE_PREFIX . "$table ENGINE = $type";
	$schema['ALTER']['explain']["$table"] = sprintf($install_phrases['alter_table_type_x'], TABLE_PREFIX . $table, $type);
}

// ***************************************************************************************************************************


$schema['INSERT']['query']['adminutil'] = "
REPLACE INTO " . TABLE_PREFIX . "adminutil
	(title, text)
VALUES
	('datastorelock', '0')";
$schema['INSERT']['explain']['adminutil'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "adminutil");


// Attachment types
$contenttype_post = array(
	1 => array(	// 1 signifies vBulletin Post as the contenttype
		'n' => 0,	// Open New Window on Click
		'e' => 1, // Enabled
	)
);

$contenttype_album_enabled = array(
	7 => array(	// 7 signifies vBulletin SocialGroup as the contenttype
		'n' => 0,	// Open New Window on Click
		'e' => 1, // Enabled
	)
);

$contenttype_album_disabled = array(
	7 => array(	// 2 signifies vBulletin SocialGroup as the contenttype
		'n' => 0,	// Open New Window on Click
		'e' => 0, // Enabled
	)
);

$contenttype_group_enabled = array(
	8 => array(	// 8 signifies vBulletin Album as the contenttype
		'n' => 0,	// Open New Window on Click
		'e' => 1, // Enabled
	)
);

$contenttype_group_disabled = array(
	8 => array(	// 2 signifies vBulletin Album as the contenttype
		'n' => 0,	// Open New Window on Click
		'e' => 0, // Enabled
	)
);

$schema['INSERT']['query']['attachmenttype'] = "
INSERT INTO " . TABLE_PREFIX . "attachmenttype
	(extension, mimetype, size, width, height, display, contenttypes)
VALUES
	('gif', '" . $db->escape_string(serialize(array('Content-type: image/gif'))) . "', '20000', '620', '280', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_enabled + $contenttype_group_enabled)) . "'),
	('jpeg', '" . $db->escape_string(serialize(array('Content-type: image/jpeg'))) . "', '20000', '620', '280', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_enabled + $contenttype_group_enabled)) . "'),
	('jpg', '" . $db->escape_string(serialize(array('Content-type: image/jpeg'))) . "', '100000', '0', '0', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_enabled + $contenttype_group_enabled)) . "'),
	('jpe', '" . $db->escape_string(serialize(array('Content-type: image/jpeg'))) . "', '20000', '620', '280', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_enabled + $contenttype_group_enabled)) . "'),
	('txt', '" . $db->escape_string(serialize(array('Content-type: plain/text'))) . "', '20000', '0', '0', '2', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_disabled + $contenttype_group_disabled)) . "'),
	('png', '" . $db->escape_string(serialize(array('Content-type: image/png'))) . "', '20000', '620', '280', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_enabled + $contenttype_group_enabled)) . "'),
	('doc', '" . $db->escape_string(serialize(array('Content-type: application/msword'))) . "', '20000', '0', '0', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_disabled + $contenttype_group_disabled)) . "'),
	('pdf', '" . $db->escape_string(serialize(array('Content-type: application/pdf'))) . "', '20000', '0', '0', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_disabled + $contenttype_group_disabled)) . "'),
	('bmp', '" . $db->escape_string(serialize(array('Content-type: image/bitmap'))) . "', '20000', '620', '280', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_enabled + $contenttype_group_enabled)) . "'),
	('psd', '" . $db->escape_string(serialize(array('Content-type: unknown/unknown'))) . "', '20000', '0', '0', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_disabled + $contenttype_group_disabled)) . "'),
	('zip', '" . $db->escape_string(serialize(array('Content-type: application/zip'))) . "', '100000', '0', '0', '0', '" . $db->escape_string(serialize($contenttype_post + $contenttype_album_disabled + $contenttype_group_disabled)) . "')
";
$schema['INSERT']['explain']['attachmenttype'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "attachmenttype");



$schema['INSERT']['query']['attachmentcache'] = "
INSERT INTO " . TABLE_PREFIX . "datastore
	(title, data, unserialize)
VALUES
	('attachmentcache', '" . $db->escape_string(serialize(array())) . "', 1)
";
$schema['INSERT']['explain']['attachmentcache'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "datastore");



$schema['INSERT']['query']['bookmarksite'] = "
INSERT INTO " . TABLE_PREFIX . "bookmarksite
	(title, active, displayorder, iconpath, url)
VALUES
	('Digg',        1, 10, 'bookmarksite_digg.gif',        'http://digg.com/submit?phase=2&amp;url={URL}&amp;title={TITLE}'),
	('del.icio.us', 1, 20, 'bookmarksite_delicious.gif',   'http://del.icio.us/post?url={URL}&amp;title={TITLE}'),
	('StumbleUpon', 1, 30, 'bookmarksite_stumbleupon.gif', 'http://www.stumbleupon.com/submit?url={URL}&amp;title={TITLE}'),
	('Google',      1, 40, 'bookmarksite_google.gif',      'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;title={TITLE}')
";
$schema['INSERT']['explain']['bookmarksite'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "bookmarksite");



$schema['INSERT']['query']['calendar'] = "
INSERT INTO " . TABLE_PREFIX . "calendar
	(title, description, displayorder, neweventemail, moderatenew, startofweek, options, cutoff, eventcount, birthdaycount, startyear, endyear)
VALUES
	('" . $db->escape_string($install_phrases['default_calendar']) . "', '', 1, '" . serialize(array()) . "', 0, 1, 631, 40, 4, 4, " . (date('Y') - 3) . ", " . (date('Y') + 3) . ")
";
$schema['INSERT']['explain']['calendar'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "calendar");



$schema['INSERT']['query']['contenttype'] = "
	INSERT INTO " . TABLE_PREFIX . "contenttype
		(contenttypeid, class, packageid, canplace, cansearch, cantag, canattach)
	VALUES
		(1, 'Post', 1, '0', '1', '0', '1'),
		(2, 'Thread', 1, '0', '0', '1', '0'),
		(3, 'Forum', 1, '0', '1', '0', '0'),
		(4, 'Announcement', 1, '0', '0', '0', '0'),
		(5, 'SocialGroupMessage', 1, '0', '1', '0', '0'),
		(6, 'SocialGroupDiscussion', 1, '0', '0', '0', '0'),
		(7, 'SocialGroup', 1, '0', '1', '0', '1'),
		(8, 'Album', 1, '0', '0', '0', '1'),
		(9, 'Picture', 1, '0', '0', '0', '0'),
		(10, 'PictureComment', 1, '0', '0', '0', '0'),
		(11, 'VisitorMessage', 1, '0', '1', '0', '0'),
		(12, 'User', 1, '0', '0', '0', '0'),
		(13, 'Event', 1, '0', '0', '0', '0'),
		(14, 'Calendar', 1, '0', '0', '0', '0'),
		(15, 'PrivateMessage', 1, '0', '0', '0', '0'),
		(16, 'Infraction', 1, '0', '0', '0', '0'),
		(17, 'Signature', 1, '0', '0', '0', '0'),
		(18, 'UserNote', 1, '0', '0', '0', '0')
";
$schema['INSERT']['explain']['contenttype'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "contenttype");



$schema['INSERT']['query']['cron'] = "
INSERT INTO " . TABLE_PREFIX . "cron
	(nextrun, weekday, day, hour, minute, filename, loglevel, varname, volatile, product)
VALUES
	(1053271660, -1, -1,  0, 'a:1:{i:0;i:1;}',           './includes/cron/birthday.php',        1, 'birthday',        1, 'vbulletin'),
	(1053532560, -1, -1, -1, 'a:1:{i:0;i:56;}',          './includes/cron/threadviews.php',     0, 'threadviews',     1, 'vbulletin'),
	(1053531900, -1, -1, -1, 'a:1:{i:0;i:25;}',          './includes/cron/promotion.php',       1, 'promotion',       1, 'vbulletin'),
	(1053271720, -1, -1,  0, 'a:1:{i:0;i:2;}',           './includes/cron/digestdaily.php',     1, 'digestdaily',     1, 'vbulletin'),
	(1053991800,  1, -1,  0, 'a:1:{i:0;i:30;}',          './includes/cron/digestweekly.php',    1, 'digestweekly',    1, 'vbulletin'),
	(1053271820, -1, -1,  0, 'a:1:{i:0;i:2;}',           './includes/cron/subscriptions.php',   1, 'subscriptions',   1, 'vbulletin'),
	(1053533100, -1, -1, -1, 'a:1:{i:0;i:5;}',           './includes/cron/cleanup.php',         0, 'cleanup',         1, 'vbulletin'),
	(1053533200, -1, -1, -1, 'a:1:{i:0;i:10;}',          './includes/cron/attachmentviews.php', 0, 'attachmentviews', 1, 'vbulletin'),
	(1053990180, -1, -1,  0, 'a:1:{i:0;i:3;}',           './includes/cron/activate.php',        1, 'activate',        1, 'vbulletin'),
	(1053271600, -1, -1, -1, 'a:1:{i:0;i:15;}',          './includes/cron/removebans.php',      1, 'removebans',      1, 'vbulletin'),
	(1053531600, -1, -1, -1, 'a:1:{i:0;i:20;}',          './includes/cron/cleanup2.php',        0, 'cleanup2',        1, 'vbulletin'),
	(1053271600, -1, -1,  0, 'a:1:{i:0;i:0;}',           './includes/cron/stats.php',           0, 'stats',           1, 'vbulletin'),
	(1053271600, -1, -1, -1, 'a:2:{i:0;i:25;i:1;i:55;}', './includes/cron/reminder.php',        0, 'reminder',        1, 'vbulletin'),
	(1053533100, -1, -1,  0, 'a:1:{i:0;i:10;}',          './includes/cron/dailycleanup.php',    0, 'dailycleanup',    1, 'vbulletin'),
	(1053271600, -1, -1, -1, 'a:2:{i:0;i:20;i:1;i:50;}', './includes/cron/infractions.php',     1, 'infractions',     1, 'vbulletin'),
	(1053271600, -1, -1, -1, 'a:1:{i:0;i:10;}',          './includes/cron/ccbill.php',          1, 'ccbill',          1, 'vbulletin'),
	(1053271600, -1, -1, -1, 'a:6:{i:0;i:0;i:1;i:10;i:2;i:20;i:3;i:30;i:4;i:40;i:5;i:50;}', './includes/cron/rssposter.php', 1, 'rssposter',1, 'vbulletin'),
	(1232082000, -1, -1,  5, 'a:1:{i:0;i:0;}',           './includes/cron/sitemap.php',         1, 'sitemap',         1, 'vbulletin'),
	(1232082000, -1, -1,  5, 'a:6:{i:0;i:0;i:1;i:10;i:2;i:20;i:3;i:30;i:4;i:40;i:5;i:50;}',           './includes/cron/queueprocessor.php',         1, 'searchqueueupdates',         1, 'vbulletin')
";
$schema['INSERT']['explain']['cron'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "cron");



$schema['INSERT']['query']['datastore'] = "
INSERT INTO " . TABLE_PREFIX . "datastore
	(title, data, unserialize)
VALUES
	('products', '" . $db->escape_string(serialize(array('vbulletin' => '1'))) . "', 1)
";
$schema['INSERT']['explain']['datastore'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "datastore");

// this query is used by the 370b6 upgrade script, so the REPLACE avoids errors
$schema['INSERT']['query']['faq'] = "
REPLACE INTO " . TABLE_PREFIX . "faq
	(faqname, faqparent, displayorder, volatile)
VALUES
	('vb3_board_faq','faqroot',200,1),
	('vb3_board_usage','vb3_board_faq',10,1),
	('vb3_forums_threads_posts','vb3_board_usage',1,1),
	('vb3_register','vb3_board_usage',2,1),
	('vb3_search','vb3_board_usage',3,1),
	('vb3_announcements','vb3_board_usage',4,1),
	('vb3_thread_display','vb3_board_usage',5,1),
	('vb3_new_posts','vb3_board_usage',6,1),
	('vb3_rating_threads','vb3_board_usage',7,1),
	('vb3_thread_tools','vb3_board_usage',8,1),
	('vb3_tags','vb3_board_usage',9,1),
	('vb3_cookies','vb3_board_usage',10,1),
	('vb3_lost_passwords','vb3_board_usage',11,1),
	('vb3_calendar','vb3_board_usage',12,1),
	('vb3_members_list','vb3_board_usage',13,1),
	('vb3_notifications','vb3_board_usage',14,1),
	('vb3_quick_links','vb3_board_usage',15,1),
	('vb3_contact_members','vb3_board_usage',16,1),
	('vb3_rss_podcasting','vb3_board_usage',18,1),
	('vb3_user_profile','vb3_board_faq',20,1),
	('vb3_public_profile','vb3_user_profile',1,1),
	('vb3_user_cp','vb3_user_profile',2,1),
	('vb3_changing_details','vb3_user_profile',3,1),
	('vb3_other_settings','vb3_user_profile',5,1),
	('vb3_profile_custom','vb3_user_profile',6,1),
	('vb3_social_groups','vb3_user_profile',7,1),
	('vb3_friends_contacts','vb3_user_profile',8,1),
	('vb3_albums','vb3_user_profile',9,1),
	('vb3_private_messages','vb3_user_profile',10,1),
	('vb3_subscriptions','vb3_user_profile',11,1),
	('vb3_reputation','vb3_user_profile',12,1),
	('vb3_reading_posting','vb3_board_faq',30,1),
	('vb3_posting','vb3_reading_posting',1,1),
	('vb3_replying','vb3_reading_posting',2,1),
	('vb3_editing_deleting','vb3_reading_posting',3,1),
	('vb3_polls','vb3_reading_posting',4,1),
	('vb3_attachments','vb3_reading_posting',5,1),
	('vb3_smilies','vb3_reading_posting',6,1),
	('vb3_mods_admins','vb3_reading_posting',8,1),
	('vb3_troublesome_users','vb3_board_usage',17,1),
	('vb3_message_icons','vb3_reading_posting',7,1),
	('vb3_signatures_avatars','vb3_user_profile',4,1)
";
$schema['INSERT']['explain']['faq'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "faq");



$schema['INSERT']['query']['forum'] = "
INSERT INTO " . TABLE_PREFIX . "forum
	(forumid, styleid, title, description, options, displayorder, replycount, lastpost, lastposter,
	lastthread, lastthreadid, lasticonid, threadcount, daysprune, newpostemail, newthreademail,
	parentid, parentlist, password, link, childlist, title_clean, description_clean)
VALUES
	(1, 0, '" . $db->escape_string($install_phrases['category_title']) . "', '" . $db->escape_string($install_phrases['category_desc']) . "',
	'86017', '1', '0', '0', '', '', '0', '0', '0', '-1', '', '', '-1', '1,-1', '', '', '1,2,-1',
	'" . $db->escape_string($install_phrases['category_title']) . "', '" . $db->escape_string($install_phrases['category_desc']) . "'),

	(2, 0, '" . $db->escape_string($install_phrases['forum_title']) . "', '" . $db->escape_string($install_phrases['forum_desc']) . "',
	'89799', '1', '0', '0', '', '', '0', '0', '0', '-1', '', '', '1', '2,1,-1', '', '', '2,-1',
	'" . $db->escape_string($install_phrases['forum_title']) . "', '" . $db->escape_string($install_phrases['forum_desc']) . "')
";
$schema['INSERT']['explain']['forum'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "forum");



$schema['INSERT']['query']['icon'] = "
INSERT INTO " . TABLE_PREFIX . "icon
	(title, iconpath, imagecategoryid, displayorder)
VALUES
	('{$install_phrases['posticon_1']}', 'images/icons/icon1.png', '2', '1'),
	('{$install_phrases['posticon_2']}', 'images/icons/icon2.png', '2', '1'),
	('{$install_phrases['posticon_3']}', 'images/icons/icon3.png', '2', '1'),
	('{$install_phrases['posticon_4']}', 'images/icons/icon4.png', '2', '1'),
	('{$install_phrases['posticon_5']}', 'images/icons/icon5.png', '2', '1'),
	('{$install_phrases['posticon_6']}', 'images/icons/icon6.png', '2', '1'),
	('{$install_phrases['posticon_7']}', 'images/icons/icon7.png', '2', '1'),
	('{$install_phrases['posticon_8']}', 'images/icons/icon8.png', '2', '1'),
	('{$install_phrases['posticon_9']}', 'images/icons/icon9.png', '2', '1'),
	('{$install_phrases['posticon_10']}', 'images/icons/icon10.png', '2', '1'),
	('{$install_phrases['posticon_11']}', 'images/icons/icon11.png', '2', '1'),
	('{$install_phrases['posticon_12']}', 'images/icons/icon12.png', '2', '1'),
	('{$install_phrases['posticon_13']}', 'images/icons/icon13.png', '2', '1'),
	('{$install_phrases['posticon_14']}', 'images/icons/icon14.png', '2', '1')
";
$schema['INSERT']['explain']['icon'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "icon");



$schema['INSERT']['query']['imagecategory'] = "
INSERT INTO " . TABLE_PREFIX . "imagecategory
	(title, imagetype, displayorder)
VALUES
	('{$install_phrases['generic_smilies']}', 3, 1),
	('{$install_phrases['generic_icons']}', 2, 1),
	('{$install_phrases['generic_avatars']}', 1, 1)
";
$schema['INSERT']['explain']['imagecategory'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "imagecategory");



$schema['INSERT']['query']['language'] = "
INSERT INTO " . TABLE_PREFIX . "language
	(title, languagecode, charset, decimalsep, thousandsep)
VALUES
	('{$install_phrases['master_language_title']}', '{$install_phrases['master_language_langcode']}', '{$install_phrases['master_language_charset']}', '{$install_phrases['master_language_decimalsep']}', '{$install_phrases['master_language_thousandsep']}')";
$schema['INSERT']['explain']['language'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "language");



$schema['INSERT']['query']['package'] = "
INSERT INTO " . TABLE_PREFIX . "package
	(packageid, productid, class)
VALUES
	(1, 'vbulletin', 'vBForum')
";
$schema['INSERT']['explain']['package'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "package");



$schema['INSERT']['query']['paymentapi'] = "
INSERT INTO " . TABLE_PREFIX . "paymentapi
	(title, currency, recurring, classname, active, settings)
VALUES
	('Paypal', 'usd,gbp,eur,aud,cad', 1, 'paypal', 0, '" . $db->escape_string(serialize(array(
		'ppemail' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'primaryemail' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "'),
	('NOCHEX', 'gbp', 0, 'nochex', 0, '" . $db->escape_string(serialize(array(
		'ncxemail' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "'),
	('Worldpay', 'usd,gbp,eur', 0, 'worldpay', 0, '" . $db->escape_string(serialize(array(
		'worldpay_instid' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'worldpay_password' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "'),
	('Authorize.Net', 'usd,gbp,eur', 0, 'authorizenet', 0, '" . $db->escape_string(serialize(array(
		'authorize_loginid' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'txnkey' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'authorize_md5secret' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "'),
	('2Checkout', 'usd', 0, '2checkout', 0, '" . $db->escape_string(serialize(array(
		'twocheckout_id' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'number'
		),
		'secret_word' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "'),
	('Moneybookers', 'usd,gbp,eur,aud,cad', 0, 'moneybookers', 0, '" . $db->escape_string(serialize(array(
		'mbemail' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'mbsecret' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "'),
	('CCBill', 'usd', 0, 'ccbill', 0, '" . $db->escape_string(serialize(array(
		'clientAccnum' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'clientSubacc' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'formName' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'secretword' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'username' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		),
		'password' => array(
			'type' => 'text',
			'value' => '',
			'validate' => 'string'
		)
	))) . "')
";
$schema['INSERT']['explain']['paymentapi'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "paymentapi");



$schema['INSERT']['query']['profilefield'] = "
INSERT INTO " . TABLE_PREFIX . "profilefield
	(profilefieldid, required, hidden, maxlength, size, displayorder, editable, type, data, height, def, optional, searchable, memberlist, regex, form)
VALUES
	('1', '0', '0', '16384', '50', '1', '1', 'textarea', '', '0', '0', '0', '1', '1', '', '0'),
	('2', '0', '0', '100', '25', '2', '1', 'input', '', '0', '0', '0', '1', '1', '', '0'),
	('3', '0', '0', '100', '25', '3', '1', 'input', '', '0', '0', '0', '1', '1', '', '0'),
	('4', '0', '0', '100', '25', '4', '1', 'input', '', '0', '0', '0', '1', '1', '', '0')
";
$schema['INSERT']['explain']['profilefield'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "profilefield");



// Phrases
if (!empty($customphrases) AND is_array($customphrases))
{
	foreach ($customphrases AS $fieldname => $phrase)
	{
		foreach ($phrase AS $varname => $text)
		{
			$schema['INSERT']['query']["$varname"] = "
			INSERT INTO " . TABLE_PREFIX . "phrase (languageid, fieldname, varname, text, product) VALUES
			(0, '$fieldname', '$varname', '" . $db->escape_string($text) . "', 'vbulletin')
			";
			$schema['INSERT']['explain']["$varname"] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "phrase");
		}
	}
}



// Phrasetypes TODO: MAKE THIS NICER
$schema['INSERT']['query']['phrasetype'] = "
INSERT INTO " . TABLE_PREFIX . "phrasetype
	(fieldname, title, editrows, special)
VALUES
	('global',           '" . $db->escape_string($phrasetype['global']) . "', 3, 0),
	('cpglobal',         '" . $db->escape_string($phrasetype['cpglobal']) . "', 3, 0),
	('cppermission',     '" . $db->escape_string($phrasetype['cppermission']) . "', 3, 0),
	('forum',            '" . $db->escape_string($phrasetype['forum']) . "', 3, 0),
	('calendar',         '" . $db->escape_string($phrasetype['calendar']) . "', 3, 0),
	('attachment_image', '" . $db->escape_string($phrasetype['attachment_image']) . "', 3, 0),
	('style',            '" . $db->escape_string($phrasetype['style']) . "', 3, 0),
	('logging',          '" . $db->escape_string($phrasetype['logging']) . "', 3, 0),
	('cphome',           '" . $db->escape_string($phrasetype['cphome']) . "', 3, 0),
	('promotion',        '" . $db->escape_string($phrasetype['promotion']) . "', 3, 0),
	('user',             '" . $db->escape_string($phrasetype['user']) . "', 3, 0),
	('help_faq',         '" . $db->escape_string($phrasetype['help_faq']) . "', 3, 0),
	('sql',              '" . $db->escape_string($phrasetype['sql']) . "', 3, 0),
	('subscription',     '" . $db->escape_string($phrasetype['subscription']) . "', 3, 0),
	('language',         '" . $db->escape_string($phrasetype['language']) . "', 3, 0),
	('bbcode',           '" . $db->escape_string($phrasetype['bbcode']) . "', 3, 0),
	('stats',            '" . $db->escape_string($phrasetype['stats']) . "', 3, 0),
	('diagnostic',       '" . $db->escape_string($phrasetype['diagnostics']) . "', 3, 0),
	('maintenance',      '" . $db->escape_string($phrasetype['maintenance']) . "', 3, 0),
	('cprofilefield',    '" . $db->escape_string($phrasetype['cprofilefield']) . "', 3, 0),
	('profilefield',     '" . $db->escape_string($phrasetype['profile']) . "', 3, 0),
	('thread',           '" . $db->escape_string($phrasetype['thread']) . "', 3, 0),
	('timezone',         '" . $db->escape_string($phrasetype['timezone']) . "', 3, 0),
	('banning',          '" . $db->escape_string($phrasetype['banning']) . "', 3, 0),
	('reputation',       '" . $db->escape_string($phrasetype['reputation']) . "', 3, 0),
	('wol',              '" . $db->escape_string($phrasetype['wol']) . "', 3, 0),
	('threadmanage',     '" . $db->escape_string($phrasetype['threadmanage']) . "', 3, 0),
	('pm',               '" . $db->escape_string($phrasetype['pm']) . "', 3, 0),
	('cpuser',           '" . $db->escape_string($phrasetype['cpuser']) . "', 3, 0),
	('accessmask',       '" . $db->escape_string($phrasetype['accessmask']) . "', 3, 0),
	('cron',             '" . $db->escape_string($phrasetype['cron']) . "', 3, 0),
	('moderator',        '" . $db->escape_string($phrasetype['moderator']) . "', 3, 0),
	('cpoption',         '" . $db->escape_string($phrasetype['cpoption']) . "', 3, 0),
	('cprank',           '" . $db->escape_string($phrasetype['cprank']) . "', 3, 0),
	('cpusergroup',      '" . $db->escape_string($phrasetype['cpusergroup']) . "', 3, 0),
	('holiday',          '" . $db->escape_string($phrasetype['holiday']) . "', 3, 0),
	('posting',          '" . $db->escape_string($phrasetype['posting']) . "', 3, 0),
	('poll',             '" . $db->escape_string($phrasetype['poll']) . "', 3, 0),
	('fronthelp',        '" . $db->escape_string($phrasetype['fronthelp']) . "', 3, 0),
	('register',         '" . $db->escape_string($phrasetype['register']) . "', 3, 0),
	('search',           '" . $db->escape_string($phrasetype['search']) . "', 3, 0),
	('showthread',       '" . $db->escape_string($phrasetype['showthread']) . "', 3, 0),
	('postbit',          '" . $db->escape_string($phrasetype['postbit']) . "', 3, 0),
	('forumdisplay',     '" . $db->escape_string($phrasetype['forumdisplay']) . "', 3, 0),
	('messaging',        '" . $db->escape_string($phrasetype['messaging']) . "', 3, 0),
	('plugins',          '" . $db->escape_string($phrasetype['plugins']) . "', 3, 0),
	('inlinemod',        '" . $db->escape_string($phrasetype['inlinemod']) . "', 3, 0),
	('reputationlevel',  '" . $db->escape_string($phrasetype['reputationlevel']) . "', 3, 0),
	('infraction',       '" . $db->escape_string($phrasetype['infraction']) . "', 3, 0),
	('infractionlevel',  '" . $db->escape_string($phrasetype['infractionlevel']) . "', 3, 0),
	('notice',           '" . $db->escape_string($phrasetype['notice']) . "', 3, 0),
	('prefix',           '" . $db->escape_string($phrasetype['prefix']) . "', 3, 0),
	('prefixadmin',      '" . $db->escape_string($phrasetype['prefixadmin']) . "', 3, 0),
	('album',            '" . $db->escape_string($phrasetype['album']) . "', 3, 0),
	('error',            '" . $db->escape_string($phrasetype['front_end_error']) . "', 8, 1),
	('frontredirect',    '" . $db->escape_string($phrasetype['front_end_redirect']) . "', 8, 1),
	('emailbody',        '" . $db->escape_string($phrasetype['email_body']) . "', 10, 1),
	('emailsubject',     '" . $db->escape_string($phrasetype['email_subj']) . "', 3, 1),
	('vbsettings',       '" . $db->escape_string($phrasetype['vbulletin_settings']) . "', 4, 1),
	('cphelptext',       '" . $db->escape_string($phrasetype['cp_help']) . "', 8, 1),
	('faqtitle',         '" . $db->escape_string($phrasetype['faq_title']) . "', 3, 1),
	('faqtext',          '" . $db->escape_string($phrasetype['faq_text']) . "', 10, 1),
	('cpstopmsg',        '" . $db->escape_string($phrasetype['stop_message']) . "', 8, 1),
	('hvquestion',       '" . $db->escape_string($phrasetype['hvquestion']) . "', 3, 1),
	('socialgroups',     '" . $db->escape_string($phrasetype['socialgroups']) . "', 3, 0),
	('tagscategories',   '" . $db->escape_string($phrasetype['tagscategories']) . "', 3, 0),
	('advertising',      '" . $db->escape_string($phrasetype['advertising']) . "', 3, 0),
	('contenttypes',     '" . $db->escape_string($phrasetype['contenttypes']) . "', 3, 0),
	('vbblock',	         '" . $db->escape_string($phrasetype['vbblock']) . "', 3, 0),
	('vbblocksettings',  '" . $db->escape_string($phrasetype['vbblocksettings']) . "', 3, 0),
	('ckeditor',         '" . $db->escape_string($phrasetype['ckeditor']) . "', 3, 0)
";
$schema['INSERT']['explain']['phrasetype'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "phrasetype");


$emptyarray = @serialize(array());
$schema['INSERT']['query']['style'] = "
INSERT INTO " . TABLE_PREFIX . "style
	(styleid, title, parentid, parentlist, templatelist, css, replacements, userselect, displayorder, csscolors, stylevars)
VALUES
	(1, '{$install_phrases['default_style']}', -1, '1,-1', '1,-1', '', '', 1, 1, '" . $db->escape_string($emptyarray) . "', '" . $db->escape_string($emptyarray) . "')
";
$schema['INSERT']['explain']['style'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "style");



$schema['INSERT']['query']['infractionlevel'] = "
INSERT INTO " . TABLE_PREFIX . "infractionlevel
	(infractionlevelid, points, expires, period, warning)
VALUES
	(1, 1, 10, 'D', 1),
	(2, 1, 10, 'D', 1),
	(3, 1, 10, 'D', 1),
	(4, 1, 10, 'D', 1)
";
$schema['INSERT']['explain']['infractionlevel'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "infractionlevel");



$schema['INSERT']['query']['reputationlevel'] = "
INSERT INTO " . TABLE_PREFIX . "reputationlevel
	(reputationlevelid, minimumreputation)
VALUES
	(1, -999999),
	(2, -50),
	(3, -10),
	(4, 0),
	(5, 10),
	(6, 50),
	(7, 150),
	(8, 250),
	(9, 350),
	(10, 450),
	(11, 550),
	(12, 650),
	(13, 1000),
	(14, 1500),
	(15, 2000)
";
$schema['INSERT']['explain']['reputationlevel'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "reputationlevel");



$schema['INSERT']['query']['smilie'] = "
INSERT INTO " . TABLE_PREFIX . "smilie
	(title, smilietext, smiliepath, imagecategoryid, displayorder)
VALUES
	('{$install_phrases['smilie_smile']}', ':)', 'images/smilies/smile.png', '1', '1'),
	('{$install_phrases['smilie_embarrass']}', ':o', 'images/smilies/redface.png', '1', '1'),
	('{$install_phrases['smilie_grin']}', ':D', 'images/smilies/biggrin.png', '1', '1'),
	('{$install_phrases['smilie_wink']}', ';)', 'images/smilies/wink.png', '1', '1'),
	('{$install_phrases['smilie_tongue']}', ':p', 'images/smilies/tongue.png', '1', '1'),
	('{$install_phrases['smilie_cool']}', ':cool:', 'images/smilies/cool.png', '1', '5'),
	('{$install_phrases['smilie_roll']}', ':rolleyes:', 'images/smilies/rolleyes.png', '1', '3'),
	('{$install_phrases['smilie_mad']}', ':mad:', 'images/smilies/mad.png', '1', '1'),
	('{$install_phrases['smilie_eek']}', ':eek:', 'images/smilies/eek.png', '1', '7'),
	('{$install_phrases['smilie_confused']}', ':confused:', 'images/smilies/confused.png', '1', '1'),
	('{$install_phrases['smilie_frown']}', ':(', 'images/smilies/frown.png', '1', '1')
";
$schema['INSERT']['explain']['smilie'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "smilie");



$schema['INSERT']['query']['socialgroupcategory'] = "
REPLACE INTO " . TABLE_PREFIX . "socialgroupcategory
	(socialgroupcategoryid, creatoruserid, title, description, displayorder, lastupdate)
VALUES
	(1, 1, '$install_phrases[socialgroups_uncategorized]', '$install_phrases[socialgroups_uncategorized_description]', 1, " . TIMENOW . ")
";
$schema['INSERT']['explain']['socialgroupcategory'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "socialgroupcategory");



// Load usergroup permissions to see what is given on new installs
require_once(DIR . '/includes/class_bitfield_builder.php');
if (vB_Bitfield_Builder::build(false) !== false)
{
	$myobj =& vB_Bitfield_Builder::init();
}
else
{
	echo "<strong>error</strong>\n";
	print_r(vB_Bitfield_Builder::fetch_errors());
}

$groupinfo = array();
foreach ($myobj->data['ugp'] AS $grouptitle => $perms)
{
	for ($x = 1; $x < 9; $x++)
	{
		$groupinfo["$x"]["$grouptitle"] = 0;
	}

	foreach ($perms AS $permtitle => $permvalue)
	{
		if (empty($permvalue['group']))
		{
			continue;
		}

		if (!empty($permvalue['install']))
		{
			foreach ($permvalue['install'] AS $gid)
			{
				$groupinfo["$gid"]["$grouptitle"] += $permvalue['value'];
			}
		}
	}
}

$schema['INSERT']['query']['usergroup'] = "
INSERT INTO " . TABLE_PREFIX . "usergroup
	(	usergroupid, title, description, usertitle,
		passwordexpires, passwordhistory, pmquota, pmsendmax, opentag, closetag, canoverride, ispublicgroup,
		forumpermissions, pmpermissions, calendarpermissions,
		wolpermissions, adminpermissions, genericpermissions, genericpermissions2,
		signaturepermissions, genericoptions,
		usercsspermissions, visitormessagepermissions, socialgrouppermissions,
		albumpermissions,
		attachlimit, avatarmaxwidth, avatarmaxheight, avatarmaxsize,
		profilepicmaxwidth, profilepicmaxheight, profilepicmaxsize,
		sigmaxrawchars, sigmaxchars, sigmaxlines, sigmaxsizebbcode, sigmaximages,
		sigpicmaxwidth, sigpicmaxheight, sigpicmaxsize,
		albumpicmaxwidth, albumpicmaxheight, albummaxpics, albummaxsize,
		pmthrottlequantity, groupiconmaxsize, maximumsocialgroups
	)
VALUES
	(	1, '{$install_phrases['usergroup_guest_title']}', '', '{$install_phrases['usergroup_guest_usertitle']}',
		0, 0, 50, 0, '', '', 0, 0,
		{$groupinfo[1]['forumpermissions']}, {$groupinfo[1]['pmpermissions']}, {$groupinfo[1]['calendarpermissions']},
		{$groupinfo[1]['wolpermissions']}, {$groupinfo[1]['adminpermissions']}, {$groupinfo[1]['genericpermissions']}, {$groupinfo[1]['genericpermissions2']},
		{$groupinfo[1]['signaturepermissions']}, {$groupinfo[1]['genericoptions']},
		{$groupinfo[1]['usercsspermissions']}, {$groupinfo[1]['visitormessagepermissions']}, {$groupinfo[1]['socialgrouppermissions']},
		{$groupinfo[1]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 0
	),
	(	2, '{$install_phrases['usergroup_registered_title']}', '', '',
		0, 0, 50, 5, '', '', 0, 0,
		{$groupinfo[2]['forumpermissions']}, {$groupinfo[2]['pmpermissions']}, {$groupinfo[2]['calendarpermissions']},
		{$groupinfo[2]['wolpermissions']}, {$groupinfo[2]['adminpermissions']}, {$groupinfo[2]['genericpermissions']}, {$groupinfo[2]['genericpermissions2']},
		{$groupinfo[2]['signaturepermissions']}, {$groupinfo[2]['genericoptions']},
		{$groupinfo[2]['usercsspermissions']}, {$groupinfo[2]['visitormessagepermissions']}, {$groupinfo[2]['socialgrouppermissions']},
		{$groupinfo[2]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	),
	(	3, '{$install_phrases['usergroup_activation_title']}', '', '',
		0, 0, 50, 1, '', '', 0, 0,
		{$groupinfo[3]['forumpermissions']}, {$groupinfo[3]['pmpermissions']}, {$groupinfo[3]['calendarpermissions']},
		{$groupinfo[3]['wolpermissions']}, {$groupinfo[3]['adminpermissions']}, {$groupinfo[3]['genericpermissions']}, {$groupinfo[3]['genericpermissions2']},
		{$groupinfo[3]['signaturepermissions']}, {$groupinfo[3]['genericoptions']},
		{$groupinfo[3]['usercsspermissions']}, {$groupinfo[3]['visitormessagepermissions']}, {$groupinfo[3]['socialgrouppermissions']},
		{$groupinfo[3]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	),
	(	4, '{$install_phrases['usergroup_coppa_title']}', '', '',
		0, 0, 50, 1, '', '', 0, 0,
		{$groupinfo[4]['forumpermissions']}, {$groupinfo[4]['pmpermissions']}, {$groupinfo[4]['calendarpermissions']},
		{$groupinfo[4]['wolpermissions']}, {$groupinfo[4]['adminpermissions']}, {$groupinfo[4]['genericpermissions']}, {$groupinfo[4]['genericpermissions2']},
		{$groupinfo[4]['signaturepermissions']}, {$groupinfo[4]['genericoptions']},
		{$groupinfo[4]['usercsspermissions']}, {$groupinfo[4]['visitormessagepermissions']}, {$groupinfo[4]['socialgrouppermissions']},
		{$groupinfo[4]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	),
	(	5, '{$install_phrases['usergroup_super_title']}', '', '{$install_phrases['usergroup_super_usertitle']}',
		0, 0, 50, 0, '', '', 0, 0,
		{$groupinfo[5]['forumpermissions']}, {$groupinfo[5]['pmpermissions']}, {$groupinfo[5]['calendarpermissions']},
		{$groupinfo[5]['wolpermissions']}, {$groupinfo[5]['adminpermissions']}, {$groupinfo[5]['genericpermissions']}, {$groupinfo[5]['genericpermissions2']},
		{$groupinfo[5]['signaturepermissions']}, {$groupinfo[5]['genericoptions']},
		{$groupinfo[5]['usercsspermissions']}, {$groupinfo[5]['visitormessagepermissions']}, {$groupinfo[5]['socialgrouppermissions']},
		{$groupinfo[5]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	),
	(	6, '{$install_phrases['usergroup_admin_title']}', '', '{$install_phrases['usergroup_admin_usertitle']}',
		180, 360, 50, 5, '', '', 0, 0,
		{$groupinfo[6]['forumpermissions']}, {$groupinfo[6]['pmpermissions']}, {$groupinfo[6]['calendarpermissions']},
		{$groupinfo[6]['wolpermissions']}, {$groupinfo[6]['adminpermissions']}, {$groupinfo[6]['genericpermissions']}, {$groupinfo[5]['genericpermissions2']},
		{$groupinfo[6]['signaturepermissions']}, {$groupinfo[6]['genericoptions']},
		{$groupinfo[6]['usercsspermissions']}, {$groupinfo[6]['visitormessagepermissions']}, {$groupinfo[6]['socialgrouppermissions']},
		{$groupinfo[6]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		0, 0, 0, 7, 0,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	),
	(	7, '{$install_phrases['usergroup_mod_title']}', '', '{$install_phrases['usergroup_mod_usertitle']}',
		0, 0, 50, 5, '', '', 0, 0,
		{$groupinfo[7]['forumpermissions']}, {$groupinfo[7]['pmpermissions']}, {$groupinfo[7]['calendarpermissions']},
		{$groupinfo[7]['wolpermissions']}, {$groupinfo[7]['adminpermissions']}, {$groupinfo[7]['genericpermissions']}, {$groupinfo[7]['genericpermissions2']},
		{$groupinfo[7]['signaturepermissions']}, {$groupinfo[7]['genericoptions']},
		{$groupinfo[7]['usercsspermissions']}, {$groupinfo[7]['visitormessagepermissions']}, {$groupinfo[7]['socialgrouppermissions']},
		{$groupinfo[7]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	),
	(	8, '{$install_phrases['usergroup_banned_title']}', '', '{$install_phrases['usergroup_banned_usertitle']}',
		0, 0, 0, 0, '', '', 0, 0,
		{$groupinfo[8]['forumpermissions']}, {$groupinfo[8]['pmpermissions']}, {$groupinfo[8]['calendarpermissions']},
		{$groupinfo[8]['wolpermissions']}, {$groupinfo[8]['adminpermissions']}, {$groupinfo[8]['genericpermissions']}, {$groupinfo[8]['genericpermissions2']},
		{$groupinfo[8]['signaturepermissions']}, {$groupinfo[8]['genericoptions']},
		{$groupinfo[8]['usercsspermissions']}, {$groupinfo[8]['visitormessagepermissions']}, {$groupinfo[8]['socialgrouppermissions']},
		{$groupinfo[8]['albumpermissions']},
		0, 80, 80, 20000,
		100, 100, 65535,
		1000, 500, 0, 7, 4,
		500, 100, 10000,
		600, 600, 100, 0,
		0, 65535, 5
	)
";
$schema['INSERT']['explain']['usergroup'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "usergroup");



$schema['INSERT']['query']['usertitle'] = "
INSERT INTO " . TABLE_PREFIX . "usertitle
	(minposts, title)
VALUES
	('0', '{$install_phrases['usertitle_jnr']}'),
	('30', '{$install_phrases['usertitle_mbr']}'),
	('100', '{$install_phrases['usertitle_snr']}')
";
$schema['INSERT']['explain']['usertitle'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "usertitle");



/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 39181 $
|| ####################################################################
\*======================================================================*/
