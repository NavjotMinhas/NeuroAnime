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
if (!VB_API) die;

global $methodsegments;

// $methodsegments[1] 'action'
if ($methodsegments[1] == 'view')
{
	// format switch
	if ($_REQUEST['apitextformat'])
	{
		foreach ($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'] as $k => $v)
		{
			switch ($_REQUEST['apitextformat'])
			{
				case '1': // plain
					if ($v == 'message' OR $v == 'message_bbcode')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '2': // html
					if ($v == 'message_plain' OR $v == 'message_bbcode')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // bbcode
					if ($v == 'message' OR $v == 'message_plain')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // plain & html
					if ($v == 'message_bbcode')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // bbcode & html
					if ($v == 'message_plain')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
				case '3': // bbcode & plain
					if ($v == 'message')
					{
						unset($VB_API_WHITELIST['response']['layout']['content']['comment_block']['node_comments']['cms_comments']['*']['postbit']['post'][$k]);
					}
					break;
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/