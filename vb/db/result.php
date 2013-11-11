<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * The vB core class.
 * Everything required at the core level should be accessible through this.
 *
 * The core class performs initialisation for error handling, exception handling,
 * application instatiation and optionally debug handling.
 *
 * @TODO: Much of what goes on in global.php and init.php will be handled, or at
 * least called here during the initialisation process.  This will be moved over as
 * global.php is refactored.
 *
 * @package vBulletin
 * @version $Revision: 28823 $
 * @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_dB_Result implements iterator
{
	/** This class is called by the new vB_dB_Assertor query class.. vB_dB_Query
	 * It's a wrapper for the class_core db class, but instead of calling
	 * db->fetch_array($recordset) it's implemented as an iterator.
	 * We also will allow returning the data in JSON or XML format.
	
	Properties====================================================================*/
	/** the shared database object **/
	protected $db = false;
	
	/** The text of the query**/
	protected $querystring = false;
	
	/** The result recordset **/
	protected $recordset = false;

	/** The result recordset **/
	protected $eof = false;

	/** The result recordset **/
	protected $resultrow = false;

	/** The result recordset **/
	protected $resultseq = 0;


	/** standard constructor
	 *
	 *	@param 	mixed		the standard vbulletin db object
	 * @param 	mixed		the query string
	 *
	 ***/
	public function __construct(&$db, $querystring)
	{
		$this->querystring = $querystring;
		$this->db = $db;
		$this->recordset = $this->db->query_read($querystring);
		$this->resultrow = $this->db->fetch_array($this->recordset);
		
		$this->resultseq = 0;
		if ($this->resultrow)
		{
			$this->eof = false;
		}
		else
		{
			$this->eof = true;
		}
	}
	
	/* standard iterator method */
	public function current()
	{
		if ($this->resultrow === false)
		{
			$this->rewind();
		}
		return $this->resultrow;
	}
	
	/* standard iterator method */
	public function key()
	{
		return $this->resultseq;
	}
	
	/* standard iterator method */
	public function next()
	{
		if ($this->eof)
		{
			return false;
		}
		if ($this->recordset AND !$this->eof)
		{
			$this->resultrow = $this->db->fetch_array($this->recordset);
			
			if (!$this->resultrow)
			{
				$this->eof = true;
			}
			$this->resultseq++;
		}
		return $this->resultrow;
	}
	
	/* standard iterator method */
	public function rewind()
	{
		if ($this->recordset)
		{
			$this->db->free_result($this->recordset);
		}
		
		$this->recordset = $this->db->query_read($this->querystring);
		$this->resultrow = $this->db->fetch_array($this->recordset);

		$this->resultseq = 0;
		if ($this->resultrow)
		{
			$this->eof = false;
		}
		else
		{
			$this->eof = true;
		}
	}
	
	/* standard iterator method */
	public function valid()
	{
		return ($this->recordset AND !$this->eof);
	}
	
	/* returns the complete data array in JSON format */
	public function toJSON()
	{
		$json = array();
		
		if (($this->resultseq > 1) OR !$this->recordset)
		{
			$this->rewind();
		}
	
		while($this->valid())
		{
			$values = array();
			foreach ($this->resultrow as $fieldname => $fieldvalue)
			{
				$values[] = "\"$fieldname\":\"" . str_replace('"', '\"', $fieldvalue) . '"';
				
			}
			$json[] = $this->resultseq . "\":{\n" . implode($values, ",\n" ) . "}\n";
			$this->next();
		}
		return '{"' . implode($json, ",\n" ) . "}\n";
	}
	
		
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded=> 01:57, Mon Sep 12th 2011
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/