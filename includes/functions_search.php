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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_databuild.php');

// #############################################################################
// checks if word is goodword / badword / too long / too short
function verify_word_allowed(&$word)
{
	global $vbulletin, $phrasequery;

	$wordlower = strtolower($word);

	// check if the word contains wildcards
	if (strpos($wordlower, '*') !== false)
	{
		// check if wildcards are allowed
		if ($vbulletin->options['allowwildcards'])
		{
			// check the length of the word with all * characters removed
			// and make sure it's at least (minsearchlength - 1) characters long
			// in order to prevent searches like *a**... which would be bad
			if (vbstrlen(str_replace('*', '', $wordlower)) < ($vbulletin->options['minsearchlength'] - 1))
			{
				// word is too short
				$word = htmlspecialchars_uni($word);
				eval(standard_error(fetch_error('searchinvalidterm', $word, $vbulletin->options['minsearchlength'])));
			}
			else
			{
				// word is of valid length
				return true;
			}
		}
		else
		{
			// wildcards are not allowed - error
			$word = htmlspecialchars_uni($word);
			eval(standard_error(fetch_error('searchinvalidterm', $word, $vbulletin->options['minsearchlength'])));
		}
	}
	// check if this is a word that would be indexed
	else if ($wordokay = is_index_word($word))
	{
		return true;
	}
	// something was wrong with the word... find out what
	else
	{
		// word is a bad word (common, too long, or too short; don't search on it)
		return false;
	}
}

// #############################################################################
// makes a word or phrase safe to put into a LIKE sql condition
function sanitize_word_for_sql($word)
{
	global $vbulletin;
	static $find, $replace;

	if (!is_array($find))
	{
		$find = array(
			'\\\*',	// remove escaped wildcard
			'%'	// escape % symbols
			//'_' 	// escape _ symbols
		);
		$replace = array(
			'*',	// remove escaped wildcard
			'\%'	// escape % symbols
			//'\_' 	// escape _ symbols
		);
	}

	// replace MySQL wildcards
	$word = str_replace($find, $replace, $vbulletin->db->escape_string($word));

	return $word;
}

// #############################################################################
// gets a list of forums from the user's selection
function fetch_search_forumids(&$forumchoice, $childforums = 0)
{
	global $vbulletin, $display;

	// make sure that $forumchoice is an array
	if (!is_array($forumchoice))
	{
		$forumchoice = array($forumchoice);
	}

	// initialize the $forumids for return by this function
	$forumids = array();

	foreach ($forumchoice AS $forumid)
	{
		// get subscribed forumids
		if ($forumid === 'subscribed' AND $vbulletin->userinfo['userid'] != 0)
		{
			DEVDEBUG("Querying subscribed forums for " . $vbulletin->userinfo['username']);
			$sforums = $vbulletin->db->query_read_slave("
				SELECT forumid FROM " . TABLE_PREFIX . "subscribeforum
				WHERE userid = " . $vbulletin->userinfo['userid']
			);
			if ($vbulletin->db->num_rows($sforums) == 0)
			{
				// no subscribed forums
				eval(standard_error(fetch_error('not_subscribed_to_any_forums')));
			}
			while ($sforum = $vbulletin->db->fetch_array($sforums))
			{
				$forumids["$sforum[forumid]"] .= $sforum['forumid'];
			}
			unset($sforum);
			$vbulletin->db->free_result($sforums);
		}
		// get a single forumid or no forumid at all
		else
		{
			$forumid = intval($forumid);
			if (isset($vbulletin->forumcache["$forumid"]) AND $vbulletin->forumcache["$forumid"]['link'] == '')
			{
				$forumids["$forumid"] = $forumid;
			}
		}
	}

	// now if there are any forumids we have to query, work out their child forums
	if (empty($forumids))
	{
		$forumchoice = array();
		$display['forums'] = array();
	}
	else
	{
		// set $forumchoice to show the returned forumids
		#$forumchoice = implode(',', $forumids);

		// put current forumids into the display table
		$display['forums'] = $forumids;

		// get child forums of selected forums
		if ($childforums)
		{
			require_once(DIR . '/includes/functions_misc.php');
			foreach ($forumids AS $forumid)
			{
				$children = fetch_child_forums($forumid, 'ARRAY');
				if (!empty($children))
				{
					foreach ($children AS $childid)
					{
						$forumids["$childid"] = $childid;
					}
				}
				unset($children);
			}
		}
	}

	// return the array of forumids
	return $forumids;
}

// #############################################################################
// sort search results
function sort_search_items($searchclause, $showposts, $sortby, $sortorder)
{
	global $vbulletin;

	$itemids = array();

	// order threads
	if ($showposts == 0)
	{
		$items = $vbulletin->db->query_read_slave("
			SELECT threadid FROM " . TABLE_PREFIX . "thread AS thread" . iif($sortby == 'forum.title', "
			INNER JOIN " . TABLE_PREFIX . "forum AS forum USING(forumid)") . "
			WHERE $searchclause
			ORDER BY $sortby $sortorder
		");
		while ($item = $vbulletin->db->fetch_array($items))
		{
			$itemids[] = $item['threadid'];
		}
	}
	// order posts
	else
	{
		$jointhread = in_array($sortby, array('thread.title', 'replycount', 'views', 'thread.dateline', 'forum.title'));
		$items = $vbulletin->db->query_read_slave("
			SELECT postid FROM " . TABLE_PREFIX . "post AS post"
			. ($jointhread ? " INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)" : "")
			. ($sortby == 'forum.title' ? " INNER JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)" : "") . "
			WHERE $searchclause
			ORDER BY $sortby $sortorder
		");
		while ($item = $vbulletin->db->fetch_array($items))
		{
			$itemids[] = $item['postid'];
		}
	}

	// free SQL result
	unset($item);
	$vbulletin->db->free_result($items);

	return $itemids;

}

// #############################################################################
// remove common syntax errors in search query string
function sanitize_search_query($query, &$errors)
{
	$qu_find = array(
		'/\s+(\s*OR\s+)+/si',	// remove multiple OR strings
		'/^\s*(OR|AND|NOT|-)\s+/siU', 		// remove 'OR/AND/NOT/-' from beginning of query
		'/\s+(OR|AND|NOT|-)\s*$/siU', 		// remove 'OR/AND/NOT/-' from end of query
		'/\s+(-|NOT)\s+/si',	// remove trailing whitespace on '-' controls and translate 'not'
		'/\s+OR\s+/siU',		// capitalize ' or '
		'/\s+AND\s+/siU',		// remove ' and '
		'/\s+(-)+/s',			// remove ----word
		'/\s+/s',				// whitespace to single space
	);
	$qu_replace = array(
		' OR ',			// remove multiple OR strings
		'', 			// remove 'OR/AND/NOT/-' from beginning of query
		'',				// remove 'OR/AND/NOT/-' from end of query
		' -',			// remove trailing whitespace on '-' controls and translate 'not'
		' OR ',			// capitalize 'or '
		' ',			// remove ' and '
		' -',			// remove ----word
		' ',			// whitespace to single space
	);
	$query = trim(preg_replace($qu_find, $qu_replace, " $query "));

	// show error if query logic contains (apple OR -pear) or (-apple OR pear)
	if (strpos($query, ' OR -') !== false OR preg_match('/ -\w+ OR /siU', $query, $syntaxcheck))
	{
		$errors[] = 'invalid_search_syntax';
		return $query;
	}
	else if (!empty($query))
	{
		// check that we have some words that are NOT boolean controls
		$boolwords = array('AND', 'OR', 'NOT', '-AND', '-OR', '-NOT');
		foreach (explode(' ', strtoupper($query)) AS $key => $word)
		{
			if (!in_array($word, $boolwords))
			{
				// word is good - return the query
				return $query;
			}
		}
	}

	// no good words found - show no search terms error
	$errors[] = 'searchspecifyterms';
	return $query;

}

// #############################################################################
// fetch the score for a search result
/*
function fetch_search_item_score(&$item, $currentscore)
{
	global $vbulletin;
	global $replyscore, $viewscore, $ratescore, $searchtype;

	// for fulltext NL search, just use the score set by MySQL
	if ($vbulletin->options['fulltextsearch'] AND !$searchtype)
	{
		return $currentscore;
	}

	// don't prejudice un-rated threads!
	if ($item['votenum'] == 0)
	{
		$item['rating'] = 3;
	}
	else
	{
		$item['rating'] = $item['votetotal'] / $item['votenum'];
	}

	$replyscore = $vbulletin->options['replyfunc']($item['replycount']) * $vbulletin->options['replyscore'];
	$viewscore = $vbulletin->options['viewfunc']($item['views']) * $vbulletin->options['viewscore'];
	$ratescore = $vbulletin->options['ratefunc']($item['rating']) * $vbulletin->options['ratescore'];

	return $currentscore + $replyscore + $viewscore + $ratescore;
}
*/

// #############################################################################
// fetch the date scores for search results
/*
function fetch_search_date_scores(&$datescores, &$itemscores, $mindate, $maxdate)
{
	global $vbulletin, $searchtype;

	// for fulltext NL search, just use the score set by MySQL
	if ($vbulletin->options['fulltextsearch'] AND !$searchtype)
	{
		unset($datescores);
		return;
	}

	$datespread = $maxdate - $mindate;
	if ($datespread > 0 AND $vbulletin->options['datescore'] != 0)
	{
		foreach ($datescores AS $itemid => $dateline)
		{
			$datescore = ($dateline - $mindate) / $datespread * $vbulletin->options['datescore'];
			$itemscores["$itemid"] += $datescore;
		}
	}
	unset($datescores);
}
*/

// #############################################################################
// fetch array of IDs of forums to display in the search form
function fetch_search_forumids_array($parentid = -1, $depthmark = '')
{
	global $searchforumids, $vbulletin;
	static $indexed_forum_cache;

	if ($parentid == -1)
	{
		$searchforumids = array();
		$indexed_forum_cache = array();
		foreach ($vbulletin->forumcache AS $forumid => $forum)
		{
			$indexed_forum_cache["$forum[parentid]"]["$forumid"] =& $vbulletin->forumcache["$forumid"];
		}
	}

	if (isset($indexed_forum_cache["$parentid"]) AND is_array($indexed_forum_cache["$parentid"]))
	{
		foreach ($indexed_forum_cache["$parentid"] AS $forumid => $forum)
		{
			$forumperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
			if ($forum['displayorder'] != 0
				AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
				AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['cansearch'])
				AND ($forum['options'] & $vbulletin->bf_misc_forumoptions['active'])
				AND verify_forum_password($forum['forumid'], $forum['password'], false)
			)
			{
				$vbulletin->forumcache["$forumid"]['depthmark'] = $depthmark;
				$searchforumids[] = $forumid;
				fetch_search_forumids_array($forumid, $depthmark);
			}
		}
	}
}

// ###################### Start process_quote_removal #######################
function process_quote_removal($text, $cancelwords)
{
	$lowertext = strtolower($text);
	foreach ($cancelwords AS $word)
	{
		$word = str_replace('*', '', strtolower($word));
		if (strpos($lowertext, $word) !== false)
		{
			// we found a highlight word -- keep the quote
			return "\n" . str_replace('\"', '"', $text) . "\n";
		}
	}
	return '';
}

// #############################################################################
// used in ranking system:
function none($v)
{
	return $v;
}

function safelog($v)
{
	return log(abs($v)+1);
}

// #############################################################################

/**
*	Return a url to repeat a text search as title only if needed
*
* What this function does is to check to see if search the user requested has
* any forums excluded because they queried post text, but only have permission
* to view thread headers in one or more forums.  It appears to produce a
* url to return a titleonly search for specifically those forums excluded
* from the initial search.
*
* If no forums were excluded (a text query filter wasn't specified, titleonly was already
* selected, or the user has does not have search permissions on a forum he can't views
* posts in) then the function returns false.
*
* This is used both to generate the url and to check if the situation that causes the
* url to be generated exists.
*
*	@param array $searchterms The requested terms for search.
* @return string|false url for title only search
*/
function fetch_titleonly_url($searchterms)
{
	global $vbulletin;

	$url = array();
	if (!$searchterms['titleonly'] AND !empty($searchterms['query']))
	{
		if ($forumchoice = implode(',', fetch_search_forumids($searchterms['forumchoice'], $searchterms['childforums'])))
		{
			$searchforums = array_flip(explode(',', $forumchoice));
		}
		else
		{
			$searchforums =& $vbulletin->forumcache;
		}

		foreach ($searchforums AS $forumid => $foo)
		{
			if ($vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['canview'] AND
				$vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['cansearch'] AND
				!($vbulletin->userinfo['forumpermissions']["$forumid"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$url[] = 'forumchoice[]=' . intval($forumid);
			}
		}
	}
	if (!empty($url))
	{
		$url[] = 'do=process';
		$url[] = 'query=' . urlencode($searchterms['query']);
		$url[] = 'titleonly=1';
		if ($searchterms['searchuser'])
		{
			$url[] = 'searchuser=' . urlencode($searchterms['searchuser']);
		}
		if ($searchterms['exactname'])
		{
			$url[] = 'exactname=1';
		}
		if ($searchterms['searchdate'])
		{
			$url[] = 'searchdate=' . urlencode($searchterms['searchdate']);
		}
		if ($searchterms['beforeafter'] == 'before')
		{
			$url[] = 'beforeafter=before';
		}
		if ($searchterms['replyless'])
		{
			$url[] = 'replyless=1';
		}
		if ($searchterms['replylimit'])
		{
			$url[] = 'replylimit=' . intval($searchterms['replylimit']);
		}
		if ($searchterms['sortorder'] != 'descending')
		{
			$url[] = 'order=ascending';
		}
		if ($searchterms['sortby'] != 'lastpost')
		{
			$url[] = 'sortby=' . urlencode($searchterms['sortby']);
		}
		if ($searchterms['starteronly'])
		{
			$url[] = 'starteronly=1';
		}
		if ($searchterms['nocache'])
		{
			$url[] = 'nocache=1';
		}

		return 'search.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $url);
	}
	else
	{
		return false;
	}
}

/** Prepares the tag cloud data for rendering
 *
 * @param	string	Type of cloud. Supports search, usage
 *
 * @return	string	Tag cloud HTML (nothing if no cloud)
 ***/
function prepare_tagcloud($type = 'usage')
{
	global $vbulletin;
	$tags = array();

	if ($vbulletin->options['tagcloud_usergroup'] > 0 AND !isset($vbulletin->usergroupcache[$vbulletin->options['tagcloud_usergroup']]))
	{
		// handle a usergroup being deleted: default to live permission checking
		$vbulletin->options['tagcloud_usergroup'] = -1;
	}

	require_once(DIR . '/includes/class_taggablecontent.php');
	$collection = new vB_Collection_ContentType();
	$collection->filterTaggable(true);

	//create dummy content item objects.  We use these to call a couple of (what? - Darren)
	$type_objects = array();
	foreach ($collection AS $contenttype)
	{
		$type_objects[$contenttype->getID()] = vB_Taggable_Content_Item::create($vbulletin, $contenttype->getID(), null);
	}
	unset($collection, $contenttype);

	$cacheable = true;
	foreach ($type_objects AS $content)
	{
		if (!$content->is_cloud_cachable())
		{
			$cacheable = false;
			break;
		}
	}

	if (!$cacheable)
	{
		$cloud = null;
	}
	else
	{
		switch ($type)
		{
			case 'search':
				if (isset($vbulletin->searchcloud)) {
					$cloud = $vbulletin->searchcloud;
				}
				break;

			case 'usage':
			default:
				$cloud = $vbulletin->tagcloud;
				break;
		}
	}

	$cloud = null;
	if (!is_array($cloud) OR $cloud['dateline'] < (TIMENOW - (60 * $vbulletin->options['tagcloud_cachetime'])))
	{
		if ($type == 'search')
		{
			$tags_result = $vbulletin->db->query_read_slave("
				SELECT tagsearch.tagid, tag.tagtext, COUNT(*) AS searchcount
				FROM " . TABLE_PREFIX . "tagsearch AS tagsearch
				INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tagsearch.tagid = tag.tagid)
				" . ($vbulletin->options['tagcloud_searchhistory'] ?
			"WHERE tagsearch.dateline > " . (TIMENOW - (60 * 60 * 24 * $vbulletin->options['tagcloud_searchhistory'])) :
			'') . "
				GROUP BY tagsearch.tagid, tag.tagtext
				ORDER BY searchcount DESC
				LIMIT " . $vbulletin->options['tagcloud_tags']
			);
		}
		else
		{
			//get the query bits from the type objects.  If two objects return the same exact join/where information
			//we can collapse the subqueries.  This is particularly useful for the cms content types which are
			//largely the same under the hood.
			$bit_ids = array();
			$bit_values = array();
			foreach ($type_objects AS $type => $content)
			{
				$contenttypeid = vB_Types::instance()->getContentTypeID($type);
				$bits = $content->fetch_tag_cloud_query_bits();
				if ($bits)
				{
					$pos = array_search($bits, $bit_values);
					if ($pos === false)
					{
						$bit_ids[] = array($contenttypeid);
						$bit_values[] = $bits;
					}
					else
					{
						$bit_ids[$pos][] = $contenttypeid;
					}
				}
			}

			//build the subqueries from the bits.
			$subqueries = array();
			foreach ($bit_values AS $key => $bits)
			{
				$timelimit = (TIMENOW - (60 * 60 * 24 * $vbulletin->options['tagcloud_usagehistory']));
				$query = 	"
					SELECT tagcontent.tagid, tag.tagtext, COUNT(*) AS searchcount
					FROM " . TABLE_PREFIX . "tagcontent AS tagcontent
					INNER JOIN " . TABLE_PREFIX . "tag AS tag ON (tagcontent.tagid = tag.tagid) " .
					implode("\n", $bits['join']) . "
					WHERE tagcontent.contenttypeid IN (" . implode(",", $bit_ids[$key]) . ") AND
						tagcontent.dateline > $timelimit AND " .
						implode(" AND ", $bits['where']) . "
					GROUP BY tagcontent.tagid, tag.tagtext
				";
				$subqueries[] = $query;
			}

			if (count($subqueries))
			{
				$query = "
					SELECT data.tagid, data.tagtext, SUM(data.searchcount) AS searchcount
					FROM
						(" . implode(" UNION ALL ", $subqueries) . ") AS data
					GROUP BY data.tagid, data.tagtext
					ORDER BY searchcount DESC
					LIMIT " . $vbulletin->options['tagcloud_tags'];

				$tags_result = $vbulletin->db->query_read_slave($query);
				while ($currenttag = $vbulletin->db->fetch_array($tags_result))
				{
					$tags["$currenttag[tagtext]"] = $currenttag;
					$totals[$currenttag['tagid']] = $currenttag['searchcount'];
				}
			}
		}

		while ($currenttag = $vbulletin->db->fetch_array($tags_result))
		{
			$tags["$currenttag[tagtext]"] = $currenttag;
			$totals[$currenttag['tagid']] = $currenttag['searchcount'];
		}

		// fetch the stddev levels
		$levels = fetch_standard_deviated_levels($totals, $vbulletin->options['tagcloud_levels']);

		// assign the levels back to the tags
		foreach ($tags AS $tagtext => $tag)
		{
			$tags[$tagtext]['level'] = $levels[$tag['tagid']];
			$tags[$tagtext]['tagtext_url'] = urlencode(unhtmlspecialchars($tag['tagtext']));
		}

		// sort the categories by title
		uksort($tags, 'strnatcasecmp');

		$cloud = array(
			'tags' => $tags,
			'count' => sizeof($tags),
			'dateline' => TIMENOW
		);

		if ($cacheable)
		{
			if ($type == 'search' OR $type == 'selectlist')
			{
				$vbulletin->searchcloud = $cloud;
				build_datastore('searchcloud', serialize($cloud), 1);
			}
			else
			{
				$vbulletin->tagcloud = $cloud;
				build_datastore('tagcloud', serialize($cloud), 1);
			}
		}
	}
	return $cloud;
	
}


function prepare_tagcloudlinks($cloud)
{
	if (!$cloud OR !isset($cloud['tags']) OR !is_array($cloud['tags']) OR empty($cloud['tags']))
	{
		return $cloud;
	}
	$cloud['links'] = '';

	foreach ($cloud['tags'] AS $thistag)
	{
		($hook = vBulletinHook::fetch_hook('tag_cloud_bit')) ? eval($hook) : false;

		$templater = vB_Template::create('tag_cloud_link');
		$templater->register('thistag', $thistag);
		$cloud['links'] .= $templater->render();
	}
	return $cloud;
}



/**
 * Fetches the HTML for the tag cloud.
 *
 * @param	string	Type of cloud. Supports search, usage
 *
 * @return	string	Tag cloud HTML (nothing if no cloud)
 */
function fetch_tagcloud($type = 'usage', $cloud = false)
{
	global $vbulletin, $vbphrase, $show, $template_hook;

	if (! $cloud)
	{
		$cloud = prepare_tagcloud($type);
	}

	if (empty($cloud['tags']))
	{
		return '';
	}

	$cloud= prepare_tagcloudlinks($cloud);

	if ($type == 'search')
	{
		$templater = vB_Template::create('tag_cloud_box_search');
			$templater->register('cloud', $cloud);
		$cloud_html .= $templater->render();
	}
	else
	{
		$templater = vB_Template::create('tag_cloud_box');
			$templater->register('cloud', $cloud);
		$cloud_html .= $templater->render();
	}

	return $cloud_html;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 45163 $
|| ####################################################################
\*======================================================================*/