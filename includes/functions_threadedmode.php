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

// ###################### Start findparents #######################
function fetch_post_parentlist($postid)
{
	global $postparent;

	$retlist = '';
	$postid = $postparent["$postid"];

	while ($postid != 0)
	{
		$retlist .= ",$postid";
		$postid = $postparent["$postid"];
	}

	return $retlist;
}

// ###################### Start fetch statusicon from child posts #######################
function fetch_statusicon_from_child_posts($postid)
{
	// looks through children to see if there are new posts or not
	global $postarray, $ipostarray, $vbulletin;
	global $threadinfo; // ugly!

	if ($postarray["$postid"]['dateline'] > $threadinfo['threadview'])
	{
		return 1;
	}
	else
	{
		if (is_array($ipostarray["$postid"]))
		{ //if it has children look in there
			foreach($ipostarray["$postid"] AS $postid)
			{
				if (fetch_statusicon_from_child_posts($postid))
				{
					return 1;
				}
			}
		}
		return 0;
	}
}

// ###################### Start showPostLink #######################
function construct_threaded_post_link($post, $imageString, $depth, $haschildren, $highlightpost = false)
{
	global $vbulletin, $bgclass, $curpostid, $parent_postids, $morereplies, $threadedmode, $vbphrase, $postattach;
	global $threadinfo; // ugly
	static $lasttitle;

	//print_array($post);

	if ($threadedmode == 2 AND $highlightpost)
	{
		$highlightpost = 1;
	}
	else
	{
		$highlightpost = 0;
	}

	$pageinfo = array(
		'p' => $post['postid'],
	);
	if ($vbulletin->GPC['highlight'])
	{
		$pageinfo['highlight'] = urlencode($vbulletin->GPC['highlight']);
	}

	// write 'more replies below' link
	if ($vbulletin->options['threaded_listdepth'] != 0 AND $depth == $vbulletin->options['threaded_listdepth'] AND $post['postid'] != $curpostid AND $haschildren AND ($vbulletin->options['threaded_listdepth'] != 0 AND $depth == $vbulletin->options['threaded_listdepth'] AND !strpos(' ,' . $curpostid . $parent_postids . ',' , ',' . $post['postid'] . ',' )))
	{
		$morereplies[$post['postid']] = 1;
		return "writeLink($post[postid], " . fetch_statusicon_from_child_posts($post['postid']) . ", 0, 0, \"$imageString\", \"\", \"more\", \"\", $highlightpost, \"" .	addslashes_js(fetch_seo_url('thread|js', $threadinfo, $pageinfo) . "#post$post[postid]") . "\");\n";
	}

	// get time fields
	$post['date'] = vbdate($vbulletin->options['dateformat'], $post['dateline'], 1);
	$post['time'] = vbdate($vbulletin->options['timeformat'], $post['dateline']);

	// get status icon and paperclip
	$post['statusicon'] = iif($post['dateline'] > $threadinfo['threadview'], 1, 0);

	// get paperclip
	$post['paperclip'] = 0;
	if (is_array($postattach["$post[postid]"]))
	{
		foreach ($postattach["$post[postid]"] AS $attachment)
		{
			if ($attachment['visible'])
			{
				$post['paperclip'] = 1;
				break;
			}
		}
	}

	// echo some text from the post if no title
	if ($post['isdeleted'])
	{
		$post['title'] = $vbphrase['post_deleted'];
	}
	else if (empty($post['title']))
	{
		$pagetext = htmlspecialchars_uni($post['pagetext']);

		$pagetext = strip_bbcode($pagetext, 1);
		if (trim($pagetext) == '')
		{
			$post['title'] = $vbphrase['reply_prefix'] . ' ' . fetch_trimmed_title($lasttitle, $vbulletin->options['threaded_trimtitle']);
		}
		else
		{
			$post['title'] = '<i>' . fetch_trimmed_title($pagetext, $vbulletin->options['threaded_trimtitle']) . '</i>';
		}
	}
	else
	{
		$lasttitle = $post['title'];
		$post['title'] = fetch_trimmed_title($post['title'], $vbulletin->options['threaded_trimtitle']);
	}

	($hook = vBulletinHook::fetch_hook('showthread_threaded_construct_link')) ? eval($hook) : false;

	return "writeLink($post[postid], $post[statusicon], $post[paperclip], " . intval($post['userid']) . ", \"$imageString\", \"" . addslashes_js($post['title'], '"') . "\", \"" . addslashes_js($post['date'], '"') . "\", \"" . addslashes_js($post['time'], '"') . "\", $highlightpost, \"" . addslashes_js(fetch_seo_url('thread|js', $threadinfo, $pageinfo) . "#post$post[postid]") . "\");\n";

}

// ###################### Start getImageString #######################
function fetch_threaded_post_image_string($post, $depth)
{
	global $ipostarray, $vbulletin;
	static $depthbits;

	$imgstring = array();
	$blanks = 0;

	for ($i = 1; $i < $depth; $i ++) // get initial images
	{
		if ($depthbits["$i"] == '-')
		{
			$blanks++;
		}
		else if ($blanks != 0)
		{
			$imgstring[] = $blanks;
			$imgstring[] = $depthbits["$i"];
			$blanks = 0;
		}
		else
		{
			$imgstring[] = $depthbits["$i"];
		}
	}

	if ($blanks != 0) // return blanks if there are any left over
	{
		$imgstring[] = $blanks;
	}

	// find out if current post is last at this level of the tree
	$lastElm = sizeof($ipostarray["$post[parentid]"]) - 1;
	if ($ipostarray["$post[parentid]"]["$lastElm"] == $post['postid'])
	{
		$islast = 1;
	}
	else
	{
		$islast = 0;
	}

	if ($islast == 1) // if post is not last in tree, use L graphic...
	{
		$depthbits["$depth"] = '-';
		$imgstring[] = 'L';

	}
	else // ... otherwise use T graphic
	{
		$depthbits["$depth"] = 'I';
		$imgstring[] = 'T';
	}

	return implode(',', $imgstring);

}

// ###################### Start orderPosts #######################
function sort_threaded_posts($parentid = 0, $depth = 0, $showpost = false)
{
	global $vbulletin, $ipostarray, $postarray, $links, $bgclass, $hybridposts;
	global $postorder, $parent_postids, $currentdepth, $curpostid, $cache_postids, $curpostidkey;

	// make an indent for pretty HTML
	$indent = str_repeat('  ', $depth);

	foreach($ipostarray["$parentid"] AS $id)
	{

		if ($showpost OR $id == $vbulletin->GPC['postid'])
		{
			$doshowpost = 1;
			$hybridposts[] = $id;
		}
		else
		{
			$doshowpost = 0;
		}

		if ($id == $curpostid)
		{
			// if we have reached the post that we're meant to be displaying
			// go $vbulletin->options['threaded_listdepth'] deeper
			$vbulletin->options['threaded_listdepth'] += $currentdepth;

			$curpostidkey = sizeof($postorder);
		}

		$haschildren = is_array($ipostarray["$id"]);

		// add this post to the postorder array
		$postorder[] = $id;

		// get post information from the $postarray
		$post = $postarray["$id"];

		// call the javascript-writing function for this link
		if (empty($links))
		{
			$links .= $indent . construct_threaded_post_link($post, '', $depth, $haschildren, $doshowpost);
		}
		else
		{
			$links .= $indent . construct_threaded_post_link($post, fetch_threaded_post_image_string($post, $depth), $depth, $haschildren, $doshowpost);
		}

		// if post has children
		// and we've not reached the maximum depth
		// and we're not on the tree that contains $curpostid (ie the postid to be displayed)
		// then print children
		if ($haschildren AND !($vbulletin->options['threaded_listdepth'] != 0 AND $depth == $vbulletin->options['threaded_listdepth'] AND !strpos(' ,' . $curpostid . $parent_postids . ',', ',' . $id . ',' )))
		{
			sort_threaded_posts($id, $depth +1, $doshowpost);
		}

		if ($id == $curpostid)
		{
			// undo the above
			$vbulletin->options['threaded_listdepth'] -= $currentdepth;
		}

	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>