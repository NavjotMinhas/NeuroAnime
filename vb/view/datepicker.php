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
 * Date Picker View
 * View for a date picker control
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View_DatePicker extends vB_View
{
	/*Properties====================================================================*/

	/**
	 * The unique string id for the view result.
	 *
	 * @var string
	 */
	protected $_resultid = 'datepicker';

	/**
	 * The initial date.
	 *
	 * @var int
	 */
	protected $_date;

	/**
	 * The var to post the selected value as.
	 *
	 * @var string
	 */
	protected $_var = 'date';

	/**
	 * The label to use for display.
	 *
	 * @var vB_Phrase | string
	 */
	protected $_label;



	/*Accessors=====================================================================*/

	/**
	 * Sets the initial date.
	 *
	 * @param int $timestamp
	 */
	public function setDate($timestamp)
	{
		$this->_date = ($timestamp ? $timestamp : TIMENOW);
	}


	/**
	 * Sets the input var for the date.
	 *
	 * @param string $date_var
	 */
	public function setDateVar($date_var)
	{
		$this->_var = $date_var;
	}


	/**
	 * Sets the label to use for display.
	 *
	 * @param vB_Phrase | string $label
	 */
	public function setLabel($label)
	{
		$this->_label = $label;
	}



	/*Render========================================================================*/

	/**
	 * Prepares properties for rendering.
	 * Child classes should override this method to perform all necessary changes to
	 * the raw data to prepare it for output.
	 */
	protected function prepareProperties()
	{
		if (!is_numeric($this->_date))
		{
			throw (new vB_Exception_View('Invalid date given to datepicker'));
		}

		$this->day = date('j', $this->_date);
		$this->month = date('n', $this->_date);
		$this->year = date('Y', $this->_date);

		$this->var = $this->_var;
		$this->label = $this->_label;

		$this->months = array(
			1	=> new vB_Phrase('global', 'january'),
			2	=> new vB_Phrase('global', 'february'),
			3	=> new vB_Phrase('global', 'march'),
			4	=> new vB_Phrase('global', 'april'),
			5	=> new vB_Phrase('global', 'may'),
			6	=> new vB_Phrase('global', 'june'),
			7	=> new vB_Phrase('global', 'july'),
			8	=> new vB_Phrase('global', 'august'),
			9	=> new vB_Phrase('global', 'september'),
			10	=> new vB_Phrase('global', 'october'),
			11	=> new vB_Phrase('global', 'november'),
			12	=> new vB_Phrase('global', 'december')
		);

		$this->mselected = $this->month;
	}
}
?>