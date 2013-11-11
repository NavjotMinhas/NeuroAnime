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

/* USAGE
$r = new vB_Xml_Replace(DIR . '/install/vbulletin-language.xml', $xml);
$r->replace();
*/


/**
* Helper class to facilitate replacing master xml files with information from the DB
*
* @package	vBulletin
* @version	$Revision: 37624 $
* @date		$Date: 2010-06-21 13:46:58 -0700 (Mon, 21 Jun 2010) $
*/
class vB_FilesystemXml_Replace
{
	public function __construct($source_file)
	{
		$this->source_file = $source_file;
		$this->temp_file = $source_file . '.tmp';
		
		$this->reader = new xmlReader();
	}

	public function replace($overwrite_file = true)
	{
		$result = $this->reader->open($this->source_file, $this->charset);
		if (!$result) 
		{
			return false;	
		}

		$this->output = fopen ($this->temp_file, 'w+');
		if (!$this->output)
		{
			return false;
		}

		//can figure out how to write this from the 
		fwrite ($this->output, '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n");
		while ($this->skip_next_read OR $this->reader->read())
		{
			$this->skip_next_read = false;	
			if ($this->reader->nodeType == XMLReader::ELEMENT)
			{
				$this->last_open_depth = $this->reader->depth;
				$this->handle_open_element();
			}

			else if ($this->reader->nodeType == XMLReader::TEXT)
			{
				$this->handle_text();
			}
			
			else if ($this->reader->nodeType == XMLReader::CDATA)
			{
				$this->handle_cdata();
			}

			else if ($this->reader->nodeType == XMLReader::END_ELEMENT)
			{	
				$this->handle_close_element();
			}
		}

		//make sure to include the trailing newline.
		fwrite($this->output, "\n");

		$this->reader->close();
		fclose($this->output);

		if ($overwrite_file)
		{
			unlink($this->source_file);
			rename($this->temp_file, $this->source_file);
		}

		return true;
	}

	protected function handle_open_element()
	{
		$is_empty = $this->reader->isEmptyElement;
		$name = $this->reader->name;
		$hasValue = $this->reader->hasValue;
		
		$attributes = '';
		while($this->reader->moveToNextAttribute())
		{
			$attributes .= ' ' . $this->reader->name . '="' . $this->reader->value . '"';
		}

		fwrite($this->output, "\n" . str_repeat("\t", $this->last_open_depth));
		fwrite ($this->output, '<' . $name . $attributes);
		if ($is_empty)
		{
			fwrite($this->output, ' /');
		}
		fwrite($this->output, '>');
	}

	protected function handle_close_element()
	{
		if ($this->reader->depth < $this->last_open_depth)
		{
			fwrite($this->output, "\n" . str_repeat("\t", $this->reader->depth));
		}
		fwrite($this->output, '</' . $this->reader->name . '>');
	}

	protected function handle_cdata()
	{
		//the xmlReader handles everything as UTF-8 regardless of the charset for the 
		//document.  We need to convert back on output.

		//readOuterXML isn't avaliable on really old copies of libxml.  
		if (method_exists($this->reader, 'readOuterXML'))
		{
			fwrite($this->output, iconv('utf-8', $this->charset, $this->reader->readOuterXML()));
		}
		else 
		{
			fwrite($this->output, iconv('utf-8', $this->charset, '<![CDATA[' . $this->reader->value . ']]>'));
		}
	}

	protected function handle_text()
	{
		//the xmlReader handles everything as UTF-8 regardless of the charset for the 
		//document.  We need to convert back on output.
		fwrite($this->output, iconv('utf-8', $this->charset, $this->reader->value));
	}

	protected $charset = 'ISO-8859-1';

	protected $reader;
	protected $output;
	protected $source_file;
	protected $temp_file;

	//some state for the parse, avoids the need to pass stuff into the 
	//handle functions 
	protected $last_open_depth;
	protected $skip_next_read = false;
}

class vB_FilesystemXml_Replace_Style_Template extends vB_FilesystemXml_Replace
{
	public function __construct($source_file, $text)
	{
		$this->text = $text;
		parent::__construct($source_file);
	}

	protected function handle_open_element()
	{
		if ($this->reader->name == 'templategroup')
		{

			$groups_to_save = array ('CSS Special Templates', 'StyleVar Special Templates');
			if (!in_array($this->reader->getAttribute('name'), $groups_to_save))
			{	
				$this->skip_next_read = $this->reader->next();
				if (!$this->text_written)
				{
					fwrite($this->output, $this->text);
					$this->text_written = true;
					unset($this->text);
				}
				return;
			}
		}
		parent::handle_open_element();
	}

	private $text;
	private $text_written = false;
}

class vB_FilesystemXml_Replace_Product_Template extends vB_FilesystemXml_Replace
{
	public function __construct($source_file, $text)
	{
		$this->text = $text;
		parent::__construct($source_file);
	}

	protected function handle_open_element()
	{
		if ($this->reader->name == 'templates')
		{
			$this->skip_next_read = $this->reader->next();
			if (!$this->text_written)
			{
				fwrite($this->output, $this->text);
				$this->text_written = true;
				unset($this->text);
			}
			return;
		}
		parent::handle_open_element();
	}

	private $text;
	private $text_written = false;
}

class vB_FilesystemXml_Replace_Stylevar extends vB_FilesystemXml_Replace
{
	public function __construct($source_file, $stylevar_name, $text)
	{
		$this->stylevar_name = $stylevar_name;
		$this->text = $text;
		parent::__construct($source_file);
	}

	protected function handle_open_element()
	{
		//both the stylevars and stylevardfns use the stylevar tag.  We
		//only want to replace the stylevars tag for the given name
		if ($this->reader->name == 'stylevars')
		{
			$this->in_stylevars = true;
		}

		else if (
			$this->in_stylevars AND
			$this->reader->name == 'stylevar' AND 
			$this->reader->getAttribute('name') == $this->stylevar_name
		)
		{
			$this->skip_next_read = $this->reader->next();
			fwrite($this->output, "\n" . str_repeat("\t", $this->last_open_depth));
			fwrite($this->output, $this->text);
			unset($this->text);
			return;
		}
		parent::handle_open_element();
	}

	protected function handle_close_element()
	{
		if ($this->reader->name == 'stylevars')
		{
			$this->in_stylevars = false;
		}
		parent::handle_close_element();
	}

	private $stylevar_name;
	private $text;
	private $in_stylevars = false;
}


class vB_FilesystemXml_Replace_StylevarDfn extends vB_FilesystemXml_Replace
{
	public function __construct($source_file, $stylevar_group, $stylevar_name, $text)
	{
		$this->stylevar_group = $stylevar_group;
		$this->stylevar_name = $stylevar_name;
		$this->text = $text;
		parent::__construct($source_file);
	}

	
	protected function handle_open_element()
	{
		//we only want to deal with the replacement once,
		//after that we can skip the custom logic and just finish 
		//copying the file -- it means that we don't have to worry
		//about mutiple matches to the insertion point.
		if ($this->is_handled)
		{
			parent::handle_open_element();
			return;
		}

		//both the stylevars and stylevardfns use the stylevar tag.  We
		//only want to replace the stylevars tag for the given name
		if ($this->reader->name == 'stylevardfns')
		{
			$this->in_stylevardfns = true;
		}
		else if ($this->reader->name =='stylevargroup')
		{
			$this->current_group = $this->reader->getAttribute('name');
			if (strnatcasecmp($this->current_group, $this->stylevar_group) > 0)
			{
				$this->write_tag_with_group($this->reader->depth);
				$this->is_handled = true;
			}
		}
		else if (
			$this->in_stylevardfns AND
			$this->reader->name == 'stylevar' AND 
			$this->current_group == $this->stylevar_group
		)
		{
			$cmp = strnatcasecmp($this->reader->getAttribute('name'), $this->stylevar_name);
			if($cmp >= 0)
			{
				$this->write_tag();

				//replacing this tag, read to the end of it and don't 
				//process.
				if ($cmp == 0)
				{
					$this->skip_next_read = $this->reader->next();
					return;
				}
			}
		}
		parent::handle_open_element();
	}
	
	protected function handle_close_element()
	{
		if (
			!$this->is_handled AND
			$this->in_stylevardfns AND
			$this->reader->name == 'stylevargroup' AND 
			$this->current_group == $this->stylevar_group
		)
		{
			$this->write_tag();
		}

	
		if ($this->reader->name == 'stylevardfns')
		{
			$this->in_stylevars = false;
			if(!$this->is_handled)
			{
				$this->write_tag_with_group($this->reader->depth + 1);
			}
		}
		parent::handle_close_element();
	}

	protected function write_tag_with_group($depth)
	{
		//add the group
		fwrite($this->output, "\n" . str_repeat("\t", $depth));
		fwrite($this->output, '<stylevargroup name="' . $this->stylevar_group . '>');
		fwrite($this->output, "\n" . str_repeat("\t", $depth + 1));
		fwrite($this->output, $this->text);
		fwrite($this->output, "\n" . str_repeat("\t", $depth));
		fwrite($this->output, '</stylevargroup>');
		$this->is_handled = true;
	}
	
	protected function write_tag()
	{
		fwrite($this->output, "\n" . str_repeat("\t", $this->last_open_depth));
		fwrite($this->output, $this->text);
		unset($this->text);
		$this->is_handled = true;
	}


	private $stylevar_group;
	private $stylevar_name;
	private $text;
	
	private $current_group = "";
	private $in_stylevardfns = false;
	private $is_handled = false;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 37624 $
|| ####################################################################
\*======================================================================*/
?>
