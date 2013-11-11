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

class vB_BlockType_Tagcloud extends vB_BlockType
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
	 * We use it only when reload block types in admincp.
	 * Automatically set in the vB_BlockType constructor.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * The description of the block type
	 * We use it only when reload block types in admincp. So it's static.
	 *
	 * @var string
	 */
	protected $description = '';

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
	protected $settings = array(
		'tagcloud_type' => array(
			'defaultvalue' => 'usage',
			'optioncode'   => 'radio:piped
usage|by_usage
search|by_search',
			'displayorder' => 1,
			'datatype'     => 'string'
		),
		'tagcloud_limit' => array(
			'defaultvalue' => 30,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
	);

	public function getHTML($tag_cloud = false)
	{
		if (!$tag_cloud)
		{
			$tag_cloud = $this->getData();
		}

		if ($tag_cloud)
		{
		
			foreach ($tag_cloud['tags'] AS $thistag)
			{
				$templater = vB_Template::create('tag_cloud_link');
				$templater->register('thistag', $thistag);
				$tag_cloud['links'] .= $templater->render();
			}
			$templater = vB_Template::create('block_tagcloud');
			$templater->register('blockinfo', $this->blockinfo);
			$templater->register('tagcloud', $tag_cloud['links']);
			return $templater->render();
		}

	}

	public function getData()
	{
		// Overwrite $vbulletin->options['tagcloud_tags']
		$this->registry->options['tagcloud_tags'] = $this->config['tagcloud_limit'];

		$tag_cloud = $this->fetch_tagcloud($this->config['tagcloud_type']);
		
		
		return $tag_cloud;
	}

	private function fetch_tagcloud($type = 'usage')
	{
		$vbulletin = &$this->registry;

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
				}
				else
				{
					$vbulletin->tagcloud = $cloud;
				}
			}
		}

		if (empty($cloud['tags']))
		{
			return '';
		}

		$cloud['links'] = '';


		return $cloud;
	}
}