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
 * vB Exception
 * Base class for all exceptions.
 *
 * The standard error message fetched with getMessage() should not be given to the
 * end user and should be used only for development.  For this reason, they should
 * not need to be localised.
 *
 * Exceptions that will use a user visible message should implement their own method
 * to do so.
 *
 * @TODO: Add logging and mailing options / functionality.
 * @TODO: Break down exceptions into error classes (Warning, Critical etc) that
 * concrete classes can extend.
 * @TODO: Use a toString method that gives a short description of the error.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 35273 $
 * @since $Date: 2010-02-01 19:06:32 -0800 (Mon, 01 Feb 2010) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Exception extends Exception
{
	/*Properties====================================================================*/

	/**
	 * Whether the exception is considered as critical by default.
	 * If the exception is considered critical then a new vB_Exception_Critical will
	 * not be thrown when debugging errors.
	 *
	 * @var bool
	 */
	protected $critical;

	/**
	 * The vb option that decides whether to mail the admin about the exception.
	 *
	 * @var string
	 */
	protected $option_mail;

	/**
	 * The vb option that decides whether to log the exception.
	 *
	 * @var sring
	 */
	protected $option_log;

	/**
	 * The vb option that decides whether to debug the exception.
	 * If debugging of the exception is enabled and the exception is not already
	 * considered critical then a vB_Exception_Critical will be thrown.
	 * @see $critical
	 *
	 * @var string
	 */
	protected $config_debug;

	/**
	 * Whether the exception has already been logged.
	 *
	 * @var bool
	 */
	protected $logged;

	/**
	 * Whether the exception has already been mailed.
	 *
	 * @var bool
	 */
	protected $mailed;



	/*Construction==================================================================*/

	/**
	 * Constructor.
	 * Checks whether the error should be logged, mailed and/or debugged.
	 *
	 * @TODO:
	 *
	 * $code, $line and $file should only be specified if the exception was thrown
	 * because of a PHP error.  The code should be the PHP error level of the error.
	 * $file and $line will override where the exception was thrown from and instead
	 * be set to where the PHP error occured.
	 *
	 * @param string $message				- A description of the error
	 * @param int $code						- The PHP code of the error
	 * @param string $file					- The file the exception was thrown from
	 * @param int $line						- The line the exception was thrown from
	 */
	public function __construct($message, $code = false, $file = false, $line = false)
	{
		parent::__construct($message, $code);

		// Set code
		if ($code)
		{
			$this->code = $code;
		}

		// Set file
		if ($file)
		{
			$this->file = $file;
		}

		// Set line
		if ($line)
		{
			$this->line = $line;
		}

		/* TODO Debug, Logging and Mailing
		// If there is no vb options then make the exception critical
		if (!vB::$vbulletin->options)
		{
			// check if exception is not already critical
			if (!$this->critical)
			{
				throw (new vB_Exception_Critical($message, $code, $file, $line));
			}

			return;
		}

		// If debug mode is on and we're debugging this exception type then throw critical
		if (vB::$vbulletin->options AND vB::$debug AND vB::$vbulletin->options['debug_errors'] AND $this->option_debug AND vB::$vbulletin->options[$this->option_debug])
		{
			if (!$this->critical)
			{
				throw (new vB_Exception_Critical($message, $code, $file, $line));
			}
		}

		// Log error
		if (vB::$vbulletin->options['error_log'] AND $this->option_log AND vB::$vbulletin->options[$this->option_log])
		{
			$this->logError();
		}

		// Mail error
		if (vB::$vbulletin->options['error_mail'] AND $this->option_mail AND vB::$vbulletin->options[$this->option_mail]
		{
			$this->mailError();
		}
		*/
	}



	/*Log===========================================================================*/

	/**
	 * Logs details of the error.
	 */
	public function logError()
	{
		if ($this->logged)
		{
			return;
		}

		// TODO: method not yet implemented
		//vB::Log($this->toString(true));
		$this->logged = true;
	}


	/**
	 * Sends details of the error to a configured email address.
	 */
	public function mailError()
	{
		if ($this->mailed)
		{
			return;
		}

		// Mail error to admin
		//TODO

		$this->mailed = true;
	}


	/**
	 * Creates a string representation of the error.
	 * This is useful when logging or mailing the error and displays full details
	 * of the error and where it occurred, including a full stack trace.
	 *
	 * @param bool $verbose					- Whether to display full details
	 * @return string
	 */
	public function toString($verbose = false)
	{
		// Build string
		$str = $this->message . "\r\n";

		// Add details
		if ($verbose)
		{
			$str .= "********************\r\n";
			$str .= 'Occured in file: ' . $this->file . " on line $this->line\r\n";
			$str .= 'Current Route: ' . vB_Router::getCurrentRoute() . "\r\n";
			$str .= 'Initial Route: ' . vB_Router::getInitialRoute() . "\r\n";
			$str .= 'Time: ' . date('l dS \of F Y h:i:s A') . "\r\n";
			$str .= "Stack Trace:\n" . $this->getTraceAsString() . "\r\n";
		}

		return $str;
	}


	/**
	*	Magic method.  
	*
	*	Seperated from toString function because __toString cannot take parameters in 5.3
	*/
	public function __toString()
	{
		return $this->toString();
	}

	/*Accessors=====================================================================*/

	/**
	 * Checks if an exception is considered critical.
	 *
	 * @return bool
	 */
	public function isCritical()
	{
		return $this->critical;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 35273 $
|| ####################################################################
\*======================================================================*/
