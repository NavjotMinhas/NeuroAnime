<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * ContentType Collection
 * Fetches a collection of contenttypes.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28696 $
 * @since $Date: 2008-12-04 16:24:20 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Collection_ContentType extends vB_Collection
{
	/*Item==========================================================================*/

	/**
	 * The class identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_class = 'ContentType';


	/*Filters=======================================================================*/

	/**
	 * Whether to only fetch content types from enabled products.
	 * On by default.
	 *
	 * @var bool
	 */
	protected $filter_enabled = true;

	/**
	 * Whether to only fetch placeable content types.
	 *
	 * @var bool
	 */
	protected $filter_placeable;

	/**
	 * Whether to only fetch searchable content types.
	 */
	protected $filter_searchable;

	/**
	 * Whether to only fetch taggable content types.
	 */
	protected $filter_taggable;

	/**
	 * Whether to only fetch attachable content types.
	 */
	protected $filter_attachable;


	/**
	 * Whether to only fetch aggregator types.
	 */
	protected $filter_aggregators;
	
	/**
	 * Whether to only fetch aggregator types.
	 */
	protected $filter_nonaggregators;
	/*Filters=======================================================================*/

	/**
	 * Sets whether to filter collection to contenttypes from enabled products.
	 *
	 * @param bool $filter
	 */
	public function filterEnabled($filter = true)
	{
		if ($filter != $this->filter_enabled)
		{
			$this->filter_enabled = $filter;
			$this->Reset();
		}
	}

	/**
	 * Sets whether to filter collection to contenttypes from aggregator types only.
	 *
	 * @param bool $filter
	 */
	public function filterAggregators($filter = true)
	{
		if ($filter != $this->filter_aggregators)
		{
			$this->filter_aggregators = $filter;
			$this->Reset();
		}
	}

	/**
	 * Sets whether to filter collection to contenttypes from aggregator types only.
	 *
	 * @param bool $filter
	 */
	public function filterNonAggregators($filter = true)
	{
		if ($filter != $this->filter_nonaggregators)
		{
			$this->filter_nonaggregators = $filter;
			$this->Reset();
		}
	}
		/**
	 * Sets whether to filter collection to only placeable content types.
	 *
	 * @param bool $canplace					- Whether to apply the filter
	 */
	public function filterPlaceable($filter = true)
	{
		if ($filter != $this->filter_placeable)
		{
			$this->filter_placeable = $filter;
			$this->Reset();
		}
	}


	/**
	 * Sets whether to filter collection to only searchable content types.
	 *
	 * @param bool $filter						- Whether to apply the filter
	 */
	public function filterSearchable($filter = true)
	{
		if ($filter != $this->filter_searchable)
		{
			$this->filter_searchable = $filter;
			$this->Reset();
		}
	}


	/**
	 * Sets whether to filter collection to only taggable content types.
	 *
	 * @param bool $filter						- Whether to apply the filter
	 */
	public function filterTaggable($filter = true)
	{
		if ($filter != $this->filter_taggable)
		{
			$this->filter_taggable = $filter;
			$this->Reset();
		}
	}


	/**
	 * Sets whether to filter collection to only attachable content types.
	 *
	 * @param bool $filter						- Whether to apply the filter
	 */
	public function filterAttachable($filter = true)
	{
		if ($filter != $this->filter_attachable)
		{
			$this->filter_attachable = $filter;
			$this->Reset();
		}
	}


	/*LoadInfo======================================================================*/

	/**
	 * Fetches the SQL for loading.
	 *
	 * @param int $required_query				- The required query
	 * @param bool $force_rebuild				- Whether to rebuild the string
	 *
	 * @return string
	 */
	protected function getLoadQuery($required_query = self::QUERY_BASIC, $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		if (self::QUERY_BASIC == $required_query)
		{
			$wheresql = array("1 = 1");
			if ($this->filter_enabled)
			{
				$hook_query_joins = strlen($hook_query_joins) ?
					$hook_query_joins . " AND " : "";
				$hook_query_joins .= "INNER JOIN " . TABLE_PREFIX . "package AS package
					ON package.packageid = contenttype.packageid LEFT JOIN "
					. TABLE_PREFIX . "product AS product
					ON product.productid = package.productid ";
				$wheresql[] = "(product.active = '1' OR package.productid = 'vbulletin') ";
			}
			if ($this->filter_placeable)
			{
				$wheresql[] = "contenttype.canplace = '1'";
			}
			if ($this->filter_searchable)
			{
				$wheresql[] = "contenttype.cansearch = '1'";
			}
			if ($this->filter_taggable)
			{
				$wheresql[] = "contenttype.cantag = '1'";
			}
			if ($this->filter_attachable)
			{
				$wheresql[] = "contenttype.canattach = '1'";
			}
			if ($this->itemid AND sizeof($this->itemid))
			{
				$wheresql[] = "contenttype.contenttypeid IN (" . implode(',', $this->itemid) . ")";
			}

			if ($this->filter_nonaggregators)
			{
				$wheresql[] = "contenttype.isaggregator = '0'";
			}
			else if ($this->filter_aggregators)
			{
				$wheresql[] = "contenttype.isaggregator = '1'";
			}

			return "
				SELECT contenttype.contenttypeid AS itemid
					$hook_query_fields
				FROM " . TABLE_PREFIX . "contenttype AS contenttype
					$hook_query_joins
				WHERE
					" . implode(" AND ", $wheresql) . "
					$hook_query_where
			";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars($required_query) . '\'specified for contenttype collection: ' . htmlspecialchars($query)));
	}


	/**
	 * Sets info on a single item.
	 * ContentType items can fetch their own info as there are no queries.
	 *
	 * @param array mixed $iteminfo				- Property => Value
	 */
	public function setInfo($iteminfo, $load_flags = false)
	{
		return;
	}


	/**
	 * Creates a contenttype to add to the collection.
	 *
	 * @param array mixed $iteminfo				- The known properties of the new item
	 * @return vB_Item							- The created item
	 */
	protected function createItem($iteminfo, $load_flags = false)
	{
		$item = parent::createItem($iteminfo, $load_flags);

		if ($this->filter_enabled AND !$item->isEnabled())
		{
			return false;
		}

		return $item;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28696 $
|| ####################################################################
\*======================================================================*/