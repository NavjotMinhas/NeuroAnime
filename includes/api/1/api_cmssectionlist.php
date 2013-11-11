<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

class vB_APIMethod_api_cmssectionlist extends vBI_APIMethod
{
	public function output()
	{
		global $vbulletin;

		bootstrap_framework();

		if (! isset($vbulletin->userinfo['permissions']['cms']))
		{
			vBCMS_Permissions::getUserPerms();
		}


		$publishlist = implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['canpublish']);
		$viewlist = implode(', ', vB::$vbulletin->userinfo['permissions']['cms']['allview']);
			$rst = vB::$vbulletin->db->query_read("SELECT node.nodeid, node.parentnode, node.url, node.permissionsfrom,
			node.setpublish, node.publishdate, node.noderight, info.title FROM " . TABLE_PREFIX .
			"cms_node AS node INNER JOIN " . TABLE_PREFIX . "cms_nodeinfo AS info ON info.nodeid = node.nodeid
			 WHERE node.contenttypeid = " .
		vB_Types::instance()->getContentTypeID("vBCms_Section") . "  AND
		((node.permissionsfrom IN ($viewlist)  AND node.hidden = 0 ) OR (node.permissionsfrom IN ($publishlist)))
			 ORDER BY node.nodeleft");
		$nodes = array();
		$noderight = 0;

		while($record = vB::$vbulletin->db->fetch_array($rst))
		{
			if (/** This user doesn have permissions to view this record **/
				(! in_array($record['permissionsfrom'],vB::$vbulletin->userinfo['permissions']['cms']['canedit'])
				AND !(in_array($record['permissionsfrom'], vB::$vbulletin->userinfo['permissions']['cms']['canview'] )
				AND $record['setpublish'] == '1' AND $record['publishdate'] < TIMENOW ))
				OR /** This user didn't have rights to a parent **/
				($record['noderight'] < $noderight))
			{
				//We need to skip this record and all its children
				$noderight = $record['permissionsfrom'];
				continue;
			}
			$nodes[] = $record;
		}

		if (count($nodes))
		{
			reset($nodes);
			$nodes = $this->setNavArray($nodes);
			return $nodes;
		}
		
	}
	
	public function setNavArray($nodes)
	{
		//We need to set the indent level and the url
		$indentlevel = array();
		//What is the current section
		$sectionid = 1;

		//because we're ordered by nodeleft, we'll always see parents before children
		foreach ($nodes as $key => $node)
		{
			//get the url
			$nodeurl = $node['nodeid'] . ($node['url'] ? '-'. $node['url'] : '');
			$segments = array('node' => $nodeurl, 'action' => 'view');
			$nodes[$key]['url'] = vBCms_Route_Content::getURL($segments);
			//get the indent
			if (isset($node['parentnode']))
			{
				if (array_key_exists($node['parentnode'], $indentlevel))
				{
					//This is the root node
					$indent = $indentlevel[$node['parentnode']] + 1;
					$indentlevel[$node['nodeid']] = $indent;
					$nodes[$key]['indent'] = $indent;
				}
				else
				{
					$nodes[$key]['indent'] = 1;
					$indentlevel[$node['nodeid']] = 1;
				}
			}
			else
			{
				//This is the root node
				unset($nodes[$key]);
				continue;
			}
			//Set a flag to tell the template if it's the current page.
			//In my experience with templates, 0/1 is more reliable than true-false
			$nodes[$key]['current_page'] = $sectionid == $node['nodeid'] ? 1 : 0;
		}
		return $nodes;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/