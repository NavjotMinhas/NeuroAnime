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
class vB_dB_MYSQL_QueryDefs extends  vB_dB_QueryDefs
{
	/** This class is called by the new vB_dB_Assertor database class
	 * It does the actual execution. See the vB_dB_Assertor class for more information

	 * $queryid can be either the id of a query from the dbqueries table, or the
	 * name of a table.
	 *
	 * if it is the name of a table , $params MUST include 'type' of either update, insert, select, or delete.
	 *
	 * $params includes a list of parameters. Here's how it gets interpreted.
	 *
	 * If the queryid was the name of a table and type was "update", one of the params
	 * must be the primary key of the table. All the other parameters will be matched against
	 * the table field names, and appropriate fields will be updated. The return value will
	 * be false if an error is generated and true otherwise
	 *
	 * If the queryid was the name of a table and type was "delete", one of the params
	 * must be the primary key of the table. All the other parameters will be ignored
	 * The return value will be false if an error is generated and true otherwise
	 *
	 * If the queryid was the name of a table and type was "insert", all the parameters will be
	 * matched against the table field names, and appropriate fields will be set in the insert.
	 * The return value is the primary key of the inserted record.
	 *
	 * If the queryid was the name of a table and type was "select", all the parameters will be
	 * matched against the table field names, and appropriate fields will be part of the
	 * "where" clause of the select. The return value will be a vB_dB_Result object
	 * The return value is the primary key of the inserted record.
	 *
	 * If the queryid is the key of a record in the dbqueries table then each params
	 * value will be matched to the query. If there are missing parameters we will return false.
	 * If the query generates an error we return false, and otherwise we return either true,
	 * or an inserted id, or a recordset.
	 *
	 **/

	/*Properties====================================================================*/

	protected $db_type = 'MYSQL';

	/** This is the definition for tables we will process through.  It saves a
	* database query to put them here.
	* **/
	protected $table_data = array(
		'cms_node' => array('key'=> 'nodeid', 'structure' => array( 'nodeid','nodeleft',
		'noderight','parentnode','contenttypeid','contentid','url','styleid','layoutid',
		'userid','publishdate','setpublish','issection','onhomepage','permissionsfrom',
		'lastupdated','publicpreview','auto_displayorder','comments_enabled','new',
		'showtitle','showuser','showpreviewonly','showupdated','showviewcount','showpublishdate',
		'settingsforboth','includechildren','showall','editshowchildren','showrating',
		'hidden','shownav','nosearch')
		),
		'cms_article' => array('key'=> 'contentid', 'structure' => array( 'contentid',
		'pagetext', 'threadid', 'blogid', 'posttitle', 'postauthor', 'poststarter',
		'blogpostid', 'postid', 'post_posted', 'post_started', 'previewtext', 'previewimage',
		 'imagewidth', 'imageheight')
		),
		'cms_nodeinfo' => array('key'=> 'nodeid', 'structure' => array( 'nodeid','description',
		'title','html_title','viewcount','creationdate','workflowdate','workflowstatus',
		'workflowcheckedout','workflowpending','workflowlevelid','associatedthreadid',
		'ratingnum','ratingtotal','rating')
		),
		'cms_category' => array('key'=> 'categoryid', 'structure' => array( 'categoryid',
		'parentnode','category','description','catleft','catright','parentcat','enabled','contentcount')
		),
		'cms_nodecategory' => array('key'=> '', 'structure' => array( 'nodeid','categoryid')
		),
		'cms_nodeconfig' => array('key'=> 'nodeid', 'structure' => array( 'nodeid',
		'name','value','serialized')
		),
		'customprofile' => array('key' => 'customprofileid', 'structure' => array('customprofileid',
		'title','thumbnail','userid',	'themeid', 'font_family','title_text_color','page_background_color',
		'page_background_image','page_background_repeat','module_text_color','module_link_color',
		'module_background_color',	'module_background_image','module_background_repeat',
		'module_border','content_text_color','content_link_color','content_background_color',
		'content_background_image','content_background_repeat','content_border',
		'button_text_color','button_background_color','button_background_image',
		'button_background_repeat','button_border','fontsize','moduleinactive_text_color',
		'moduleinactive_link_color', 'moduleinactive_background_color', 'moduleinactive_background_image',
		'moduleinactive_background_repeat', 'moduleinactive_border', 'headers_text_color',
		'headers_link_color', 'headers_background_color', 'headers_background_image',
		'headers_background_repeat', 'headers_border', 'page_link_color')
		),
		'attachment' => array('key'=> 'attachmentid', 'structure' => array('attachmentid', 'contenttypeid',
		'contentid', 'userid', 'dateline', 'filedataid', 'state', 'counter', 'posthash', 'filename',
		'caption', 'reportthreadid', 'settings', 'displayorder')
		),
		'album' => array('key'=> 'albumid', 'structure' => array('albumid', 'userid',
		'createdate', 'lastpicturedate', 'visible', 'moderation', 'title', 'description', 'state',
		'coverattachmentid')
		),
	);

	/** This is the definition for queries we will process through.  We could also
	 * put them in the database, but this eliminates a query.
	 * **/
	protected $query_data = array(
		'select_section' => array('querytype'=> 's',
			'query_string' =>  'SELECT {sql_calc} node.nodeid AS itemid,
					(node.nodeleft = 1) AS isroot, node.nodeid, node.contenttypeid, node.contentid, node.url, node.parentnode, node.styleid, node.userid,
					node.layoutid, node.publishdate, node.setpublish, node.issection, parent.permissionsfrom as parentpermissions,
					node.permissionsfrom, node.publicpreview, node.showtitle, node.showuser, node.showpreviewonly, node.showall,
					node.showupdated, node.showviewcount, node.showpublishdate, node.settingsforboth, node.includechildren, node.editshowchildren,
					node.shownav, node.hidden, node.nosearch, node.nodeleft,
					info.description, info.title, info.html_title, info.viewcount, info.creationdate, info.workflowdate,
					info.workflowstatus, info.workflowcheckedout, info.workflowlevelid, info.associatedthreadid,
					user.username, sectionorder.displayorder, thread.replycount, parentinfo.title AS parenttitle
					{$hook_query_fields}
				FROM {TABLE_PREFIX}cms_node AS node
				INNER JOIN {TABLE_PREFIX}cms_nodeinfo AS info ON info.nodeid = node.nodeid
				{$hook_query_join}
				LEFT JOIN {TABLE_PREFIX}user AS user ON user.userid = node.userid
				LEFT JOIN {TABLE_PREFIX}thread AS thread ON thread.threadid = info.associatedthreadid
				LEFT JOIN {TABLE_PREFIX}cms_sectionorder AS sectionorder ON sectionorder.sectionid = {filter_node}
					AND sectionorder.nodeid = node.nodeid
				LEFT JOIN {TABLE_PREFIX}cms_node AS parent ON parent.nodeid = node.parentnode
				LEFT JOIN {TABLE_PREFIX}cms_nodeinfo AS parentinfo ON parentinfo.nodeid = parent.nodeid
				INNER JOIN {TABLE_PREFIX}cms_node AS rootnode
					ON rootnode.nodeid = {filter_node} AND (node.nodeleft >= rootnode.nodeleft AND node.nodeleft <= rootnode.noderight) AND node.nodeleft != rootnode.nodeleft
				  {$extrasql} AND node.contenttypeid <> {sectiontype} AND node.new != 1'),
		'updt_nodeconfig' => array('querytype'=> 'u',
			'query_string' =>  "UPDATE {TABLE_PREFIX}cms_nodeconfig SET value='{value}' WHERE nodeid={nodeid} AND name='{name}'"),
		'del_nodeconfig' => array('querytype'=> 'd',
			'query_string' =>  'DELETE FROM {TABLE_PREFIX}cms_nodeconfig WHERE nodeid={nodeid} AND name=\'{name}\''),
		'ins_nodeconfig' => array('querytype'=> 'i',
			'query_string' =>  "INSERT INTO {TABLE_PREFIX}cms_nodeconfig (nodeid, name, value, serialized)
			VALUES({nodeid}, '{name}','{value}', {serialized})"),
		'sel_nodeconfig' => array('querytype'=> 's',
			'query_string' =>  'SELECT * FROM {TABLE_PREFIX}cms_nodeconfig WHERE nodeid={nodeid} ORDER BY name'),
		'get_user_theme' => array('querytype'=> 's',
			'query_string' => 'SELECT prof2.* FROM {TABLE_PREFIX}customprofile prof1 INNER JOIN
			{TABLE_PREFIX}customprofile prof2 ON prof2.customprofileid = prof1.themeid WHERE prof1.userid
			= {userid}'),
		'findAttachmentIdFromFileData' => array('querytype'=> 's',
			'query_string' =>  "SELECT al.state, al.albumid,
				at.* FROM {TABLE_PREFIX}attachment AS at LEFT JOIN {TABLE_PREFIX}album AS al ON al.albumid = at.contentid
				WHERE at.filedataid = {filedataid} AND at.contenttypeid ={contenttypeid} AND (al.albumid IS NULL OR al.state='public')
				AND at.state = 'visible' ORDER BY albumid, posthash"),
		'firstPublicAlbum' => array('querytype'=> 's',
			'query_string' =>  "SELECT albumid FROM {TABLE_PREFIX}album WHERE state='public' AND userid={userid}
				ORDER BY moderation ASC LIMIT 1"),
		'PublicAlbums' => array('querytype'=> 's',
			'query_string' =>  "SELECT albumid, title, description FROM {TABLE_PREFIX}album WHERE state='public' AND userid={userid}
				ORDER BY moderation ASC"),
		'CustomProfileAlbums' => array('querytype'=> 's',
			'query_string' => "SELECT DISTINCT album.albumid, album.title, album.description
				FROM {TABLE_PREFIX}attachment AS attachment
				INNER JOIN {TABLE_PREFIX}album AS album ON (album.albumid = attachment.contentid)
				WHERE attachment.contenttypeid = {contenttypeid} AND album.state IN ('profile','public') AND album.userid = {userid}
				ORDER BY album.lastpicturedate DESC"),
		'GetAlbumContents' => array('querytype'=> 's',
			'query_string' =>  "	SELECT a.*, fd.thumbnail_dateline AS dateline,
				album.state AS albumstate,
				IF (thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.extension, fd.filesize
				FROM {TABLE_PREFIX}attachment AS a
				INNER JOIN {TABLE_PREFIX}filedata AS fd ON (a.filedataid = fd.filedataid)
				INNER JOIN {TABLE_PREFIX}album AS album ON (album.albumid = a.contentid)
				WHERE
					a.contentid = {albumid} and a.contenttypeid = {contenttypeid}	AND
					fd.extension IN ({extensions}) AND album.state in ('public', 'profile') ORDER BY album.title",
			'quotes_ok' => array('extensions')),
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded=> 01:57, Mon Sep 12th 2011
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/
