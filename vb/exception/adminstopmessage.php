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
 * AdminStopMessage Exception
 * Exception thrown when the Admin should not continue.
 * Created to be able to interface with the existing print_stop_message function
 * but to allow other behavior if desired.
 *
 * @package vBulletin
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28674 $
 * @since $Date: 2008-12-03 12:56:57 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Exception_AdminStopMessage extends vB_Exception
{
	public function __construct($params, $code = false, $file = false, $line = false)
	{
		$this->params = $params;
		if (!is_array($this->params))
		{
			$this->params = array($this->params);
		}
	
		//I can't override getMessage because its final. I don't want to fetch the 
		//message prematurely because we might not use it directly.  I don't think vBPhrase 
		//accepts parameters as an array and even so the exception may do a string cast
		//on the message which won't defer the lookup anyway. Given that this exception is 
		//intended to be caught and dealt with it doesn't bear the level of thought
		//required to fix it.
		parent::__construct("internal error", $code, $file, $line);
	}

	public function getParams()
	{
		return $this->params;
	}

	protected $params = array();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28674 $
|| ####################################################################
\*======================================================================*/
?>
