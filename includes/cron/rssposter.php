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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/class_rss_poster.php');
require_once(DIR . '/includes/class_wysiwygparser.php');

if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0)
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}
@set_time_limit(0);

// #############################################################################
// slurp all enabled feeds from the database

$feeds_result = $vbulletin->db->query_read("
	SELECT rssfeed.*, rssfeed.options AS rssoptions, user.*, forum.forumid
	FROM " . TABLE_PREFIX . "rssfeed AS rssfeed
	INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = rssfeed.userid)
	INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = rssfeed.forumid)
	WHERE rssfeed.options & " . $vbulletin->bf_misc_feedoptions['enabled'] . "
");
while ($feed = $vbulletin->db->fetch_array($feeds_result))
{
	// only process feeds that are due to be run (lastrun + TTL earlier than now)
	if ($feed['lastrun'] < TIMENOW - $feed['ttl'])
	{
		// counter for maxresults
		$feed['counter'] = 0;

		// add to $feeds slurp array
		$feeds["$feed[rssfeedid]"] = $feed;
	}
}
$vbulletin->db->free_result($feeds_result);

// #############################################################################
// extract items from feeds

if (!empty($feeds))
{
	// array of items to be potentially inserted into the database
	$items = array();

	// array to store rss item logs sql
	$rsslog_insert_sql = array();

	// array to store list of inserted items
	$cronlog_items = array();

	// array to store list of forums to be updated
	$update_forumids = array();

	$feedcount = 0;
	$itemstemp = array();

	foreach (array_keys($feeds) AS $rssfeedid)
	{
		$feed =& $feeds["$rssfeedid"];

		$feed['xml'] = new vB_RSS_Poster($vbulletin);
		$feed['xml']->fetch_xml($feed['url']);
		if (empty($feed['xml']->xml_string))
		{
			if (defined('IN_CONTROL_PANEL'))
			{
				echo construct_phrase($vbphrase['x_unable_to_open_url'], $feed['title']);
			}
			continue;
		}
		else if ($feed['xml']->parse_xml() === false)
		{
			if (defined('IN_CONTROL_PANEL'))
			{
				echo construct_phrase($vbphrase['x_xml_error_y_at_line_z'], $feed['title'], ($feed['xml']->feedtype == 'unknown' ? 'Unknown Feed Type' : $feed['xml']->xml_object->error_string()), $feed['xml']->xml_object->error_line());
			}
			continue;
		}

		// prepare search terms if there are any
		if ($feed['searchwords'] !== '')
		{
			$feed['searchterms'] = array();
			$feed['searchwords'] = preg_quote($feed['searchwords'], '#');
			$matches = false;

			// find quoted terms or single words
			if (preg_match_all('#(?:"(?P<phrase>.*?)"|(?P<word>[^ \r\n\t]+))#', $feed['searchwords'], $matches, PREG_SET_ORDER))
			{
				foreach ($matches AS $match)
				{
					$searchword = empty($match['phrase']) ? $match['word'] : $match['phrase'];

					// Ensure empty quotes were not used
					if (!($searchword))
					{
						continue;
					}

					// exact word match required
					if (substr($searchword, 0, 2) == '\\{' AND substr($searchword, -2, 2) == '\\}')
					{
						// don't match words nested in other words - the patterns here match targets that are not surrounded by ascii alphanums below 0128 \x7F
						$feed['searchterms']["$searchword"] = '#(?<=[\x00-\x40\x5b-\x60\x7b-\x7f]|^)' . substr($searchword, 2, -2) . '(?=[\x00-\x40\x5b-\x60\x7b-\x7f]|$)#si';
					}
					// string fragment match required
					else
					{
						$feed['searchterms']["$searchword"] = "#$searchword#si";
					}
				}
			}
		}

		foreach ($feed['xml']->fetch_items() AS $item)
		{
			// attach the rssfeedid to each item
			$item['rssfeedid'] = $rssfeedid;

			if (!empty($item['summary']))
			{
				// ATOM
				$description = get_item_value($item['summary']);
			}
			elseif (!empty($item['content:encoded']))
			{
				$description = get_item_value($item['content:encoded']);
			}
			elseif (!empty($item['content']))
			{
				$description = get_item_value($item['content']);
			}
			else
			{
				$description = get_item_value($item['description']);
			}

			// backward compatability to RSS
			if (!isset($item['description']))
			{
				$item['description'] = $description;
			}
			if (!isset($item['guid']) AND isset($item['id']))
			{
				$item['guid'] =& $item['id'];
			}
			if (!isset($item['pubDate']))
			{
				if (isset($item['published']))
				{
					$item['pubDate'] =& $item['published'];
				}
				elseif(isset($item['updated']))
				{
					$item['pubDate'] =& $item['updated'];
				}
			}

			switch($feed['xml']->feedtype)
			{
				case 'atom':
				{
					// attach a content hash to each item
					$item['contenthash'] = md5($item['title']['value'] . $description . $item['link']['href']);
					break;
				}
				case 'rss':
				default:
				{
					// attach a content hash to each item
					$item['contenthash'] = md5($item['title'] . $description . $item['link']);
				}
			}

			// generate unique id for each item
			if (is_array($item['guid']) AND !empty($item['guid']['value']))
			{
				$uniquehash = md5($item['guid']['value']);
			}
			else if (!is_array($item['guid']) AND !empty($item['guid']))
			{
				$uniquehash = md5($item['guid']);
			}
			else
			{
				$uniquehash = $item['contenthash'];
			}

			// check to see if there are search words defined for this feed
			if (is_array($feed['searchterms']))
			{
				$matched = false;

				foreach ($feed['searchterms'] AS $searchword => $searchterm)
				{
					// (search title only                     ) OR (search description if option is set..)
					if (preg_match($searchterm, $item['title']) OR ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['searchboth'] AND preg_match($searchterm, $description)))
					{
						$matched = true;

						if (!($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['matchall']))
						{
							break;
						}
					}
					else if ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['matchall'])
					{
						$matched = false;
						break;
					}
				}

				// add matched item to the potential insert array
				if ($matched AND ($feed['maxresults'] == 0 OR $feed['counter'] < $feed['maxresults']))
				{
					$feed['counter']++;
					$items["$uniquehash"] = $item;
					$itemstemp["$uniquehash"] = $item;
				}
			}
			// no search terms, insert item regardless
			else
			{
				// add item to the potential insert array
				if ($feed['maxresults'] == 0 OR $feed['counter'] < $feed['maxresults'])
				{
					$feed['counter']++;
					$items["$uniquehash"] = $item;
					$itemstemp["$uniquehash"] = $item;
				}
			}

			if (++$feedcount % 10 == 0 AND !empty($itemstemp))
			{
				$rsslogs_result = $vbulletin->db->query_read("
					SELECT * FROM " . TABLE_PREFIX . "rsslog
					WHERE uniquehash IN ('" . implode("', '", array_map(array(&$vbulletin->db, 'escape_string'), array_keys($itemstemp))) . "')
				");

				while ($rsslog = $vbulletin->db->fetch_array($rsslogs_result))
				{
					// remove any items which have this unique id from the list of potential inserts.
					unset($items["$rsslog[uniquehash]"]);
				}
				$vbulletin->db->free_result($rsslogs_result);

				$itemstemp = array();
			}

		}
	}

	if (!empty($itemstemp))
	{
		// query rss log table to find items that are already inserted
		$rsslogs_result = $vbulletin->db->query_read("
			SELECT * FROM " . TABLE_PREFIX . "rsslog
			WHERE uniquehash IN ('" . implode("', '", array_map(array(&$vbulletin->db, 'escape_string'), array_keys($itemstemp))) . "')
		");
		while ($rsslog = $vbulletin->db->fetch_array($rsslogs_result))
		{
			// remove any items with this unique id from the list of potential inserts
			unset($items["$rsslog[uniquehash]"]);
		}
		$vbulletin->db->free_result($rsslogs_result);
	}

	if (!empty($items))
	{
		$vbulletin->options['postminchars'] = 1; // try to avoid minimum character errors
		$error_type = (defined('IN_CONTROL_PANEL') ? ERRTYPE_CP : ERRTYPE_SILENT);
		$rss_logs_inserted = false;

		if (defined('IN_CONTROL_PANEL'))
		{
			echo "<ol>";
		}

		$html_parser = new vB_WysiwygHtmlParser($vbulletin);

		// process the remaining list of items to be inserted
		foreach ($items AS $uniquehash => $item)
		{
			$feed =& $feeds["$item[rssfeedid]"];
			$feed['rssoptions'] = intval($feed['rssoptions']);

			if ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['html2bbcode'])
			{
				$body_template = nl2br($feed['bodytemplate']);
			}
			else
			{
				$body_template = $feed['bodytemplate'];
			}

			$pagetext = $feed['xml']->parse_template($body_template, $item);
			if ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['html2bbcode'])
			{
				$pagetext = $html_parser->parse_wysiwyg_html_to_bbcode($pagetext, false, true);
				// disable for announcements
				$feed['rssoptions'] = $feed['rssoptions'] & ~$vbulletin->bf_misc_feedoptions['allowhtml'];
			}

			$pagetext = convert_url_to_bbcode($pagetext);

			// insert the forumid of this item into an array for the update_forum_counters() function later
			$update_forumids["$feed[forumid]"] = true;

			switch ($feed['itemtype'])
			{
				// insert item as announcement
				case 'announcement':
				{
					// init announcement datamanager
					$itemdata =& datamanager_init('Announcement', $vbulletin, $error_type);

					$itemdata->set_info('forum', fetch_foruminfo($feed['forumid']));
					$itemdata->set_info('user', $feed);

					$itemdata->set('userid', $feed['userid']);
					$itemdata->set('forumid', $feed['forumid']);
					$itemdata->set('title', strip_bbcode($html_parser->parse_wysiwyg_html_to_bbcode($feed['xml']->parse_template($feed['titletemplate'], $item))));
					$itemdata->set('pagetext', $pagetext);
					$itemdata->set('startdate', TIMENOW);
					$itemdata->set('enddate', (TIMENOW + (86400 * ($feed['endannouncement'] > 0 ? $feed['endannouncement'] : 7))) - 1);
					$itemdata->set_bitfield('announcementoptions', 'allowsmilies', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['allowsmilies']) ? 1 : 0);
					$itemdata->set_bitfield('announcementoptions', 'signature', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['showsignature']) ? 1 : 0);
					$itemdata->set_bitfield('announcementoptions', 'allowhtml', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['allowhtml']) ? 1 : 0);
					$itemdata->set_bitfield('announcementoptions', 'allowbbcode', true);
					$itemdata->set_bitfield('announcementoptions', 'parseurl', true);

					if ($itemid = $itemdata->save())
					{
						$itemtitle = $itemdata->fetch_field('title');
						$itemlink = "../announcement.php?a=$itemid";
						$itemtype = 'announcement';
						$threadactiontime = 0;

						if (defined('IN_CONTROL_PANEL'))
						{
							echo "<li><a href=\"$itemlink\" target=\"feed\">$itemtitle</a></li>";
						}

						$rsslog_insert_sql[] = "($item[rssfeedid], $itemid, '$itemtype', '" . $vbulletin->db->escape_string($uniquehash) . "', '" . $vbulletin->db->escape_string($item['contenthash']) . "', " . TIMENOW . ", $threadactiontime)";
						$cronlog_items["$item[rssfeedid]"][] = "\t<li>$vbphrase[$itemtype] <a href=\"$itemlink\" target=\"logview\"><em>$itemtitle</em></a></li>";
					}
					break;
				}

				// insert item as thread
				case 'thread':
				default:
				{
					// init thread/firstpost datamanager
					$itemdata =& datamanager_init('Thread_FirstPost', $vbulletin, $error_type, 'threadpost');
					$itemdata->set_info('forum', fetch_foruminfo($feed['forumid']));
					$itemdata->set_info('user', $feed);
					$itemdata->set_info('is_automated', 'rss');
					$itemdata->set_info('chop_title', true);
					$itemdata->set('iconid', $feed['iconid']);
					$itemdata->set('sticky', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['stickthread'] ? 1 : 0));
					$itemdata->set('forumid', $feed['forumid']);
					$itemdata->set('prefixid', $feed['prefixid']);
					$itemdata->set('userid', $feed['userid']);
					$itemdata->set('title', strip_bbcode($html_parser->parse_wysiwyg_html_to_bbcode($feed['xml']->parse_template($feed['titletemplate'], $item))));
					$itemdata->set('pagetext', $pagetext);
					$itemdata->set('visible', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['moderatethread'] ? 0 : 1));
					$itemdata->set('allowsmilie', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['allowsmilies']) ? 1 : 0);
					$itemdata->set('showsignature', ($feed['rssoptions'] & $vbulletin->bf_misc_feedoptions['showsignature']) ? 1 : 0);
					$itemdata->set('ipaddress', '');
					$threadactiontime = (($feed['threadactiondelay'] > 0) ? (TIMENOW + $feed['threadactiondelay']  * 3600) : 0);

					if ($itemid = $itemdata->save())
					{
						$itemtype = 'thread';
						$itemtitle = $itemdata->fetch_field('title');
						$itemlink = fetch_seo_url('thread|bburl', $itemdata->thread);

						if (defined('IN_CONTROL_PANEL'))
						{
							echo "<li><a href=\"$itemlink\" target=\"feed\">$itemtitle</a></li>";
						}

						$rsslog_insert_sql[] = "($item[rssfeedid], $itemid, '$itemtype', '" . $vbulletin->db->escape_string($uniquehash) . "', '" . $vbulletin->db->escape_string($item['contenthash']) . "', " . TIMENOW . ", $threadactiontime)";
						$cronlog_items["$item[rssfeedid]"][] = "\t<li>$vbphrase[$itemtype] <a href=\"$itemlink\" target=\"logview\"><em>$itemtitle</em></a></li>";
					}
					break;
				}
			}

			if (!empty($rsslog_insert_sql))
			{
				// insert logs
				$vbulletin->db->query_replace(
					TABLE_PREFIX . 'rsslog',
					'(rssfeedid, itemid, itemtype, uniquehash, contenthash, dateline, threadactiontime)',
					$rsslog_insert_sql
				);
				$rsslog_insert_sql = array();
				$rss_logs_inserted = true;
			}
		}

		if (defined('IN_CONTROL_PANEL'))
		{
			echo "</ol>";
		}

		if ($rss_logs_inserted)
		{
			// rebuild forum counters
			require_once(DIR . '/includes/functions_databuild.php');
			foreach (array_keys($update_forumids) AS $forumid)
			{
				build_forum_counters($forumid);
			}

			// build cron log
			$log_items = '<ul class="smallfont">';
			foreach ($cronlog_items AS $rssfeedid => $items)
			{
				$log_items .= "<li><strong>{$feeds[$rssfeedid][title]}</strong><ul class=\"smallfont\">\r\n";
				foreach ($items AS $item)
				{
					$log_items .= $item;
				}
				$log_items .= "</ul></li>\r\n";
			}
			$log_items .= '</ul>';
		}

		if (!empty($feeds))
		{
			// update lastrun time for feeds
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "rssfeed
				SET lastrun = " . TIMENOW . "
				WHERE rssfeedid IN(" . implode(', ', array_keys($feeds)) . ")
			");
		}
	}
}

// #############################################################################
// check for threads that need time-delay actions

$threads_result = $vbulletin->db->query_read("
	SELECT *, thread.title AS threadtitle
	FROM " . TABLE_PREFIX . "rsslog AS rsslog
	INNER JOIN " . TABLE_PREFIX . "rssfeed AS rssfeed ON(rssfeed.rssfeedid = rsslog.rssfeedid)
	INNER JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = rsslog.itemid)
	WHERE rsslog.threadactioncomplete = 0
		AND rsslog.itemtype = 'thread'
		AND rsslog.threadactiontime <> 0
		AND rsslog.threadactiontime < " . TIMENOW . "
");
$threads = array();
while ($thread = $vbulletin->db->fetch_array($threads_result))
{
	$threaddata =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
	$threaddata->set_existing($thread);

	if ($thread['options'] & $vbulletin->bf_misc_feedoptions['unstickthread'])
	{
		$threaddata->set('sticky', 0);
	}

	if ($thread['options'] & $vbulletin->bf_misc_feedoptions['closethread'])
	{
		$threaddata->set('open', 0);
	}

	$threaddata->save();

	$threads[] = $thread['itemid'];
}
$vbulletin->db->free_result($threads_result);

// don't work with those items again
if (!empty($threads))
{
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "rsslog
		SET threadactioncomplete = 1
		WHERE itemid IN(" . implode(', ', $threads) . ")
		AND itemtype = 'thread'
	");
}

// #############################################################################
// all done

if (defined('IN_CONTROL_PANEL'))
{
	echo "<p><a href=\"rssposter.php" . $vbulletin->session->vars['sessionurl_q'] . "\">$vbphrase[rss_feed_manager]</a></p>";
}

if ($log_items)
{
	log_cron_action($log_items, $nextitem, 1);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 43197 $
|| ####################################################################
\*======================================================================*/
?>
