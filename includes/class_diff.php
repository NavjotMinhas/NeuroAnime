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

/**
* Finds the difference between two strings using a line as the atom.
*
* @package 		vBulletin
* @version		$Revision: 41161 $
* @date 		$Date: 2010-12-20 12:32:25 -0800 (Mon, 20 Dec 2010) $
*
*/
class vB_Text_Diff
{
	/**
	* Array of atoms for the old string.
	*
	* @var	array
	*/
	var $data_old = array();

	/**
	* Size of the atoms of the old string (elements in the array)
	*
	* @var	integer
	*/
	var $data_old_len = 0;

	/**
	*  Array of atoms for the new string.
	*
	* @var	array
	*/
	var $data_new = array();

	/**
	* Size of the atoms of the new string (elements in the array)
	*
	* @var	integer
	*/
	var $data_new_len = 0;

	/**
	 * Last new line since match state change
	 *
	 * @var integer
	 */
	var $new_end_line = false;

	/**
	 * Last old line since match state change
	 *
	 * @var integer
	 */
	var $old_end_line = false;

	/*
	* The old_len x new_len table that is used to find the LCS. Indexes
	* correspond to characters in the old data. Each value will contain
	* a pipe delimited string, that when decompressed, corresponds to
	* characters from the new data.
	*
	* @var	array
	*/
	var $table = array();

	/**
	 * Output progress to the browser? #34585
	 *
	 * @var bool
	 */
	var $output_progress = false;

	/**
	* Constructor. Sets up the data.
	*
	* @param	string	Old data in string format.
	* @param	string	New data in string format.
	* @param	bool	Whether or not to output progress to the browser. #34585
	*/
	function vB_Text_Diff($data_old, $data_new, $output_progress = false)
	{
		//this split produces a spurious blank line in the resulting array when there is a newline at the
		//end of the file.  The result is that there is a blank unchanged line at the end of the in the
		//diff results that shouldn't be there.  On the other hand, the threeway merge logic appears to
		//rely on this behavior for reasons that I'm not clear on.
		//Need to figure this out and fix it.
		$this->data_old = preg_split('#(\r\n|\n|\r)#', $data_old);
		$this->data_old_len = sizeof($this->data_old);

		$this->data_new = preg_split('#(\r\n|\n|\r)#', $data_new);
		$this->data_new_len = sizeof($this->data_new);

		$this->output_progress = $output_progress;
	}

	/**
	* Builds the table used to find the LCS of the old and new strings.
	* This is then used to build the diff.
	*/
	function populate_table()
	{
		$this->table = array();

		$prev_row = array();
		for ($i = -1; $i < $this->data_new_len; $i++)
		{
			$prev_row[$i] = 0;

			if ($this->output_progress AND !defined('SUPPRESS_KEEPALIVE_ECHO'))
			{
				// Send some output to browser #34585
				echo ' ';
				vbflush();
			}
		}

		for ($i = 0; $i < $this->data_old_len; $i++)
		{
			$this_row = array('-1' => 0);
			$data_old_value = $this->data_old[$i];

			for ($j = 0; $j < $this->data_new_len; $j++)
			{
				if ($data_old_value == $this->data_new[$j])
				{
					$this_row[$j] = $prev_row[$j - 1] + 1;
				}
				else if ($this_row[$j - 1] > $prev_row[$j])
				{
					$this_row[$j] = $this_row[$j - 1];
				}
				else
				{
					$this_row[$j] = $prev_row[$j];
				}
			}

			$this->table[$i - 1] = $this->compress_row($prev_row);
			$prev_row = $this_row;

			if ($this->output_progress AND !defined('SUPPRESS_KEEPALIVE_ECHO'))
			{
				// Send some output to browser #34585
				echo ' ';
				vbflush();
			}
		}
		unset($prev_row);
		$this->table[$this->data_old_len - 1] = $this->compress_row($this_row);
	}

	/**
	* Compresses a row of the LCS table in order to reduce memory usage of the table.
	*
	* @param	array	Array of entries in a row of the LCS table
	*
	* @return	string	A pipe delimited version of the row
	*/
	function compress_row($row)
	{
		return gzcompress(implode('|', $row), 9);
	}

	/**
	* Decompresses a row of an LCS table into an array of records. Note that the
	* indexing of the row begins at index -1 (for the sentinel values).
	*
	* @param	string	Compressed version of the row
	*
	* @return	array	Uncompressed version of the row; indexing begins at -1
	*/
	function decompress_row($row)
	{
		$row = @gzuncompress($row);
		$return = array();
		$i = -1;
		foreach (explode('|', $row) AS $value)
		{
			$return[$i] = $value;
			++$i;
		}
		return $return;
	}

	/**
	* Fetches the table created by populate_table().
	* Populates it first if necessary.
	*
	* @return	array	The table
	*/
	function &fetch_table()
	{
		if (sizeof($this->table) == 0)
		{
			$this->populate_table();
		}
		return $this->table;
	}

	/**
	* Fetches an array of objects that holds both strings split up into sections
	* such that a diff can be generated with it.
	*
	* @return	array	Array of vB_Text_Diff_Entry objects
	*/
	function &fetch_diff()
	{
		$table =& $this->fetch_table();
		$output = array();

		$match = array();
		$nonmatch1 = array();
		$nonmatch2 = array();

		$data_old_key = $this->data_old_len - 1;
		$data_new_key = $this->data_new_len - 1;

		$this_row = $this->decompress_row($table[$data_old_key]);
		$above_row = $this->decompress_row($table[$data_old_key - 1]);

		while ($data_old_key >= 0 AND $data_new_key >= 0)
		{
			if ($this_row[$data_new_key] != $above_row[$data_new_key - 1] AND $this->data_old[$data_old_key] == $this->data_new[$data_new_key])
			{
				// this is a non changed entry
				$this->process_nonmatches($output, $nonmatch1, $nonmatch2, $data_old_key, $data_new_key);
				array_unshift($match, $this->data_old[$data_old_key]);

				$data_old_key--;
				$data_new_key--;

				$this_row = $above_row;
				$above_row = $this->decompress_row($table[$data_old_key - 1]);
			}
			else if ($above_row[$data_new_key] > $this_row[$data_new_key - 1])
			{
				$this->process_matches($output, $match, $data_old_key, $data_new_key);
				array_unshift($nonmatch1, $this->data_old[$data_old_key]);

				$data_old_key--;

				$this_row = $above_row;
				$above_row = $this->decompress_row($table[$data_old_key - 1]);
			}
			else
			{
				$this->process_matches($output, $match, $data_old_key, $data_new_key);
				array_unshift($nonmatch2, $this->data_new[$data_new_key]);

				$data_new_key--;
			}

			if ($this->output_progress AND !defined('SUPPRESS_KEEPALIVE_ECHO'))
			{
				// Send some output to browser #34585
				echo ' ';
				vbflush();
			}

		}

		$this->process_matches($output, $match, $data_old_key, $data_new_key);
		if ($data_old_key > -1 OR $data_new_key > -1)
		{
			for (; $data_old_key > -1; $data_old_key--)
			{
				array_unshift($nonmatch1, $this->data_old[$data_old_key]);
			}
			for (; $data_new_key > -1; $data_new_key--)
			{
				array_unshift($nonmatch2, $this->data_new[$data_new_key]);
			}

			$this->process_nonmatches($output, $nonmatch1, $nonmatch2, $data_old_key, $data_new_key);
		}

		return $output;
	}

	public function fetch_annotated_lines() {
		$table = $this->fetch_table();
		$result = array();

		$data_old_key = $this->data_old_len - 1;
		$data_new_key = $this->data_new_len - 1;

		$old_line_num = $this->data_old_len;

		// start at bottom right of table
		$this_row = $this->decompress_row($table[$data_old_key]);
		$above_row = $this->decompress_row($table[$data_old_key - 1]);

		while ($data_old_key >= 0 AND $data_new_key >= 0)
		{
			// if up 1, left 1 is different and the position we're at has the same text...
			if ($this_row[$data_new_key] != $above_row[$data_new_key - 1] AND $this->data_old[$data_old_key] == $this->data_new[$data_new_key])
			{
				// ... this is an unchanged line. Add then move up 1, left 1.
				array_unshift($result, array(
					'data' => $this->data_old[$data_old_key],
					'type' => 'unchanged',
					'line' => $old_line_num
				));
				$old_line_num--;

				$data_old_key--;
				$data_new_key--;

				//we'll exit the loop on the next iteration anyway and otherwise we'll reference an invalid
				//row in $table
				if($data_old_key >= 0) {
					$this_row = $above_row;
					$above_row = $this->decompress_row($table[$data_old_key - 1]);
				}
			}
			else if ($above_row[$data_new_key] > $this_row[$data_new_key - 1])
			{
				// try to move up before left. This indicates removal.
				array_unshift($result, array(
					'data' => $this->data_old[$data_old_key],
					'type' => 'removed',
					'line' => $old_line_num
				));
				$old_line_num--;

				$data_old_key--;

				//we'll exit the loop on the next iteration anyway and otherwise we'll reference an invalid
				//row in $table
				if($data_old_key >= 0) {
					$this_row = $above_row;
					$above_row = $this->decompress_row($table[$data_old_key - 1]);
				}
			}
			else
			{
				// if it's preferable to move left, then lines were added
				array_unshift($result, array(
					'data' => $this->data_new[$data_new_key],
					'type' => 'added'
				));

				$data_new_key--;
			}

			if ($this->output_progress AND !defined('SUPPRESS_KEEPALIVE_ECHO'))
			{
				// Send some output to browser #34585
				echo ' ';
				vbflush();
			}
		}

//		if ($data_old_key > -1 OR $data_new_key > -1)
//		{
		for (; $data_old_key > -1; $data_old_key--)
		{
			array_unshift($result, array(
				'data' => $this->data_old[$data_old_key],
				'type' => 'removed',
				'line' => $old_line_num
			));
			$old_line_num--;
		}

		for (; $data_new_key > -1; $data_new_key--)
		{
			array_unshift($result, array(
				'data' => $this->data_new[$data_new_key],
				'type' => 'added'
			));
		}
//		}

		return $result;
	}


	/**
	* Processes an array of matching lines. Resets the $match array afterwards.
	*
	* @param array $output						Array of vB_Text_Diff_Entry objects which will be returned by fetch_diff()
	* @param array $match						Array of text that matches between the two strings
	* @param integer $old_line					The start line of the old string
	* @param integer $new_line					The start line of the new string
	*/
	function process_matches(&$output, &$match, $old_start_line, $new_start_line)
	{
		if (sizeof($match) > 0)
		{
			$entry = new vB_Text_Diff_Entry($match, $match);
			$entry->set_line_numbers($old_start_line, $new_start_line);

			array_unshift($output, $entry);
		}

		$match = array();
	}

	/**
	* Processes an array of nonmatching lines. Resets text arrays afterwards.
	*
	* @param array $output						Array of vB_Text_Diff_Entry objects which will be returned by fetch_diff()
	* @param array $text_old					Array of text from the old string which doesn't match the new string
	* @param array $text_new					Array of text from the new string which doesn't match the old string
	* @param int $old_line						The start line of the old string
	* @param int $new_line						The start line of the new string
	*/
	function process_nonmatches(&$output, &$text_old, &$text_new, $old_start_line, $new_start_line)
	{
		$s1 = sizeof($text_old);
		$s2 = sizeof($text_new);

		$entry = false;

		if ($s1 > 0 AND $s2 == 0)
		{
			// lines deleted
			$entry = new vB_Text_Diff_Entry($text_old, array());
		}
		else if ($s2 > 0 AND $s1 == 0)
		{
			// lines added
			$entry = new vB_Text_Diff_Entry(array(), $text_new);
		}
		else if ($s1 > 0 AND $s2 > 0)
		{
			// substitution
			$entry = new vB_Text_Diff_Entry($text_old, $text_new);
		}

		if ($entry)
		{
			$entry->set_line_numbers($old_start_line, $new_start_line);

			array_unshift($output, $entry);
		}

		$text_old = array();
		$text_new = array();
	}
}

/**
* Represents a single entry in a diff.
* Can be a group of unchanged, added, deleted, or changed lines.
*
* @package 		vBulletin
* @version		$Revision: 41161 $
* @date 		$Date: 2010-12-20 12:32:25 -0800 (Mon, 20 Dec 2010) $
*
*/
class vB_Text_Diff_Entry
{
	/**
	* Text from the old string.
	*
	* @var	string
	*/
	var $data_old = '';

	/**
	* Text from the new string
	*
	* @var	string
	*/
	var $data_new = '';

	/**
	 * The size of the diff
	 *
	 * @var integer
	 */
	var $size = 0;

	/**
	 * The first line index of the old data
	 *
	 * @var integer
	 */
	var $old_start_line;

	/**
	 * The first line index of the new data
	 *
	 * @var integer
	 */
	var $new_start_line;

	/**
	 * Representative name of the diff state of the old data, relative to the new.
	 *
	 * @var string
	 */
	var $old_class;

	/**
	 * Representative name of the diff state of the new data, relative to the old.
	 *
	 * @var string
	 */
	var $new_class;

	/**
	* Constructor. Sets up data.
	*
	* @param	string	Text from the old string
	* @param	string	Text from the new string
	*/
	function vB_Text_Diff_Entry($data_old, $data_new)
	{
		$this->data_old = $data_old;
		$this->data_new = $data_new;
		$this->size = max(sizeof($data_old), sizeof($data_new));

		$this->old_class = $this->fetch_data_old_class();
		$this->new_class = $this->fetch_data_new_class();
	}

	function set_line_numbers($old_start_line, $new_start_line)
	{
		$this->old_start_line = intval($old_start_line);
		$this->new_start_line = intval($new_start_line);
	}

	/**
	* Fetches data from the old string
	*
	* @return array
	*/
	function fetch_data_old()
	{
		return $this->prep_data($this->data_old, $this->old_start_line);
	}

	/**
	 * Fetches data from new string
	 *
	 * @return array
	 */
	function fetch_data_new()
	{
		return $this->prep_data($this->data_new, $this->new_start_line);
	}

	function prep_data($data, $start_line)
	{
		$start_line = $start_line ? $start_line : 1;

		// Reindex data array with the specific line numbers
		$prep = array();

		if (is_array($data))
		{
			foreach ($data AS $line)
			{
				$prep[++$start_line] = $line;
			}
		}

		return $prep;
	}

	/**
	* Fetches the name of the CSS class that should be used for the data from the old string
	*
	* @return	string
	*/
	function fetch_data_old_class()
	{
		if ($this->data_old == $this->data_new)
		{
			return 'unchanged';
		}
		else if ($this->data_old AND empty($this->data_new))
		{
			return 'deleted';
		}
		else if (trim(implode('', $this->data_old)) === '')
		{
			return 'notext';
		}
		else
		{
			return 'changed';
		}
	}

	/**
	* Fetches the name of the CSS class that should be used for the data from the new string
	*
	* @return	string
	*/
	function fetch_data_new_class()
	{
		if ($this->data_old == $this->data_new)
		{
			return 'unchanged';
		}
		else if ($this->data_new AND empty($this->data_old))
		{
			return 'added';
		}
		else if (trim(implode('', $this->data_new)) === '')
		{
			return 'notext';
		}
		else
		{
			return 'changed';
		}
	}

	/**
	* Prepares a section of text to be displayed in a diff
	* by making it display closer to normal in a browser
	*
	* @param	string	Text to prepare
	* @param	boolean	Whether to allow the text to wrap on its own (false uses code tags, true uses pre tags)
	*
	* @return	string	Prepared text
	*/
	function prep_diff_text($string, $wrap = true)
	{
		if (trim($string) === '')
		{
			return '&nbsp;';
		}
		else
		{
			if ($wrap)
			{
				$string = nl2br(htmlspecialchars_uni($string));
				$string = preg_replace('#( ){2}#', '&nbsp; ', $string);
				$string = str_replace("\t", '&nbsp; &nbsp; ', $string);
				return "<code>$string</code>";
			}
			else
			{
				return '<pre style="display:inline">' . "\n" . htmlspecialchars_uni($string) . '</pre>';
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 41161 $
|| ####################################################################
\*======================================================================*/
?>
