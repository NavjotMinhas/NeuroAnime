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

require_once(DIR . '/includes/class_diff.php');

class vB_Text_Merge_Threeway
{
	private $origin = '';
	private $new = '';
	private $custom = '';

	/**
	 * Output progress to the browser? #34585
	 *
	 * @var bool
	 */
	protected $output_progress = false;

	public function __construct($origin, $new, $custom, $output_progress = false)
	{
		$this->origin = $origin;
		$this->new = $new;
		$this->custom = $custom;

		$this->output_progress = $output_progress;
	}

	public function get_merged()
	{
		return $this->attempt_merge($this->get_chunks());
	}

	public function get_chunks()
	{
		$left = $this->fetch_origin_new_diff();
		$right = $this->fetch_origin_custom_diff();
		reset($left);
		reset($right);
		$chunks = array();
		$last_ey = 0;

		//start "before" the orginal file
		$current_line = -1;
		do
		{
			$left_line = $this->find_next_numbered($left, $added_left);
			$right_line = $this->find_next_numbered($right, $added_right);

			// I believe these should always be true
			assert($left_line['line'] == $right_line['line']);

			foreach ($added_left AS $l)
			{
				$chunks[] = vB_Text_Merge_Threeway_Chunk::create_add_left($current_line, $l['data']);
			}

			foreach ($added_right AS $r)
			{
				$chunks[] = vB_Text_Merge_Threeway_Chunk::create_add_right($current_line, $r['data']);
			}

			//move this after checking for added lines in case there are added lines after
			//all of the numbered lines.  This doesn't happen in most cases because the
			//diff code adds a spurious blank numbers (match even) line to the end of
			//the list if there is a newline at the end of the last real line.  This is
			//typical, but if that is not the case then we end up failing to account for
			//the added lines at the end.
			//
			//Besides, I'd like to fix the diff code eventually.
			if (!$left_line)
			{
				assert(!$right_line);
				break;
			}

			//inserts happen before the current numbered line for the loop
			//left and right are the same per above assert.
			$current_line = $left_line["line"];

			if ($left_line['type'] == 'unchanged' AND $right_line['type'] == 'unchanged')
			{
				$chunks[] = vB_Text_Merge_Threeway_Chunk::create_stable($current_line, $left_line['data']);
			}
			else if ($left_line['type'] == 'unchanged' AND $right_line['type'] == 'removed')
			{
				$chunks[] = vB_Text_Merge_Threeway_Chunk::create_remove_right($current_line, $left_line['data']);
			}
			else if ($left_line['type'] == 'removed' AND $right_line['type'] == 'unchanged')
			{
				$chunks[] = vB_Text_Merge_Threeway_Chunk::create_remove_left($current_line, $right_line['data']);
			}
			else if ($left_line['type'] == 'removed' AND $right_line['type'] == 'removed')
			{
				$chunks[] = vB_Text_Merge_Threeway_Chunk::create_remove_both($current_line, $left_line['data']);
			}
		}
		while (true);
		$chunks = $this->collapse_chunks($chunks);
		return $chunks;
	}

	public function fetch_origin_new_diff()
	{
		$diff = new vB_Text_Diff($this->origin, $this->new, $this->output_progress);
		return $diff->fetch_annotated_lines();
	}

	public function fetch_origin_custom_diff()
	{
		$diff = new vB_Text_Diff($this->origin, $this->custom, $this->output_progress);
		return $diff->fetch_annotated_lines();
	}

	private function attempt_merge($chunks)
	{
		$merged = array();
		foreach ($chunks AS $chunk)
		{
			if ($chunk->is_stable())
			{
				$merged[] = $chunk->get_text_original();
			}
			else
			{
				$merge_line = "";

				if ($chunk->get_original() === $chunk->get_left())
				{
					$merge_line = $chunk->get_text_right();
				}
				else if ($chunk->get_original() === $chunk->get_right())
				{
					$merge_line = $chunk->get_text_left();
				}
				else if ($chunk->get_left() === $chunk->get_right())
				{
					$merge_line = $chunk->get_text_left();
				}
				else
				{
					return false;
				}

				if (!is_null($merge_line))
				{
					$merged[] = $merge_line;
				}
			}
		}

		//remove extraneous newline.
		$text = implode("", $merged);
		$text = substr($text, 0, -1);
		return $text;
	}


	private function collapse_chunks($chunks)
	{
		$new_chunks = array();
		$working_chunk = null;

		foreach ($chunks AS $chunk)
		{
			if (!$working_chunk)
			{
				$working_chunk = $chunk;
			}
			else if ($chunk->get_type() != $working_chunk->get_type())
			{
				$new_chunks[] = $working_chunk;
				$working_chunk = $chunk;
			}
			else // same type
			{
				$working_chunk->append($chunk);
			}
		}

		if ($working_chunk)
		{
			$new_chunks[] = $working_chunk;
		}

		return $new_chunks;
	}

	private function find_next_numbered(&$data, &$added)
	{
		$added = array();

		do
		{
			list(, $line) = each($data);

			if (!empty($line['line']))
			{
				return $line;
			}
			else
			{
				$added[] = $line;
			}
		}
		while ($line);

		return false;
	}

}

//"private" helper class
class vB_Text_Merge_Threeway_Chunk
{
	private $type = 'unstable';

	private $original = array();
	private $left = array();
	private $right = array();
	private $line = null;

	public static function create_stable($line, $data)
	{
		$chunk = new vB_Text_Merge_Threeway_Chunk();
		$chunk->line = $line;
		$chunk->type = 'stable';
		$chunk->original[] = $data;
		return $chunk;
	}

	public static function create_add_left($line, $data)
	{
		$chunk = new vB_Text_Merge_Threeway_Chunk();
		$chunk->line = $line;
		$chunk->left[] = $data;
		return $chunk;
	}

	public static function create_add_right($line, $data)
	{
		$chunk = new vB_Text_Merge_Threeway_Chunk();
		$chunk->line = $line;
		$chunk->line_numbers[] = $line;
		$chunk->right[] = $data;
		return $chunk;
	}

	public static function create_remove_right($line, $data)
	{
		$chunk = new vB_Text_Merge_Threeway_Chunk();
		$chunk->line = $line;
		$chunk->original[] = $data;
		$chunk->left[] = $data;
		return $chunk;
	}

	public static function create_remove_left($line, $data)
	{
		$chunk = new vB_Text_Merge_Threeway_Chunk();
		$chunk->line = $line;
		$chunk->original[] = $data;
		$chunk->right[] = $data;
		return $chunk;
	}

	public static function create_remove_both($line, $data)
	{
		$chunk = new vB_Text_Merge_Threeway_Chunk();
		$chunk->line = $line;
		$chunk->original[] = $data;
		return $chunk;
	}

	//force the use of the static factory methods
	private function __construct()
	{
	}

	public function get_type()
	{
		return $this->type;
	}

	public function is_stable()
	{
		return ($this->type === 'stable');
	}

	public function is_conflict()
	{
		return ($this->get_merged_text() === false);
	}

	public function get_merged_text()
	{
		if ($this->is_stable())
		{
			return $this->get_text_original();
		}
		else
		{
			if ($this->get_original() === $this->get_left())
			{
				return $this->get_text_right();
			}
			else if ($this->get_original() === $this->get_right())
			{
				return $this->get_text_left();
			}
			else if ($this->get_left() === $this->get_right())
			{
				return $this->get_text_left();
			}
			else
			{
				return false;
			}
		}
	}

	public function get_original()
	{
		return $this->original;
	}

	public function get_left()
	{
		return $this->left;
	}

	public function get_right()
	{
		return $this->right;
	}

	public function get_text_original()
	{
		return $this->get_text($this->original);
	}

	public function get_text_left()
	{
		return $this->get_text($this->left);
	}

	public function get_text_right()
	{
		return $this->get_text($this->right);
	}

	public function append($chunk)
	{
		assert($this->type == $chunk->type);

		$this->appendInPlace($this->original, $chunk->original);

		if (!$this->is_stable())
		{
			$this->appendInPlace($this->left, $chunk->left);
			$this->appendInPlace($this->right, $chunk->right);
		}
	}

	private function appendInPlace(&$array1, $array2)
	{
		foreach ($array2 as $item)
		{
			if (!is_null($item))
			{
				$array1[] = $item;
			}
		}
	}

	private function get_text($lines)
	{
		$new_lines = array();
		foreach ($lines as $line)
		{
			if (!is_null($line))
			{
				$new_lines[] = $line;
			}
		}

		if (count($new_lines) == 0)
		{
			return null;
		}
		else
		{
			$text = implode("\n", $new_lines) . "\n";
			return $text;
		}
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27819 $
|| ####################################################################
\*======================================================================*/
