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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_406 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '406';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.6';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.5';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/**
	* Step #1
	*
	*/
	function step_1()
	{
		// fix imgdir_gradients stylevar in non-MASTER styles VBIV-8052
		$stylevar_result = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "stylevar
			WHERE stylevarid = 'imgdir_gradients'
		");

		$stylevars = array();

		while ($stylevar = $this->db->fetch_array($stylevar_result))
		{
			if ($stylevar['styleid'] == -1)
			{
				continue;
			}

			$value = unserialize($stylevar['value']);
			if (key($value) == 'string')
			{
				$stylevars[] = $stylevar;
			}
		}

		$total = count($stylevars);

		if ($total > 0)
		{
			$i = 1;
			foreach ($stylevars AS $stylevar)
			{
				$value = unserialize($stylevar['value']);
				$new_value = array('imagedir' => $value['string']);

				$this->run_query(
					sprintf($this->phrase['version']['406']['updating_stylevars_in_styleid_x_y_of_z'], $stylevar['styleid'], $i, $total),
					"UPDATE " . TABLE_PREFIX . "stylevar
					SET value = '" . $this->db->escape_string(serialize($new_value)) . "'
					WHERE
						stylevarid = 'imgdir_gradients'
							AND
						styleid = " . intval($stylevar['styleid']) . "
				");
				$i++;
			}
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
