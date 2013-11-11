<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API AND !(defined('UNIT_TESTING') AND UNIT_TESTING === true)) die;

abstract class vBI_APIMethod
{
	abstract public function output();

	public function processed_output()
	{
		$output = $this->output();
		
		if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
		{
			global $vbulletin;
			$charset = $vbulletin->userinfo['lang_charset'];
		}

		$lower_charset = strtolower($charset);
		if ($lower_charset != 'utf-8')
		{
			// Browsers tend to interpret character set iso-8859-1 as windows-1252
			if ($lower_charset == 'iso-8859-1')
			{
				$lower_charset = 'windows-1252';
			}
			$this->processregistered($output, $lower_charset);
		}

		return $output;
	}

	/**
	 * Util method for output error
	 *
	 * @param string $errorid The unique error id for client
	 * @param string $errormessage The human readable error message for client
	 * @return array The errormessage for JSON output
	 */
	protected function error($errorid, $errormessage = '')
	{
		return array('response' => array(
				'errormessage' => array(
					$errorid, $errormessage
				)
			)
		);
	}

	protected function processregistered(&$value, $charset)
	{
		global $VB_API_REQUESTS;

		if (is_array($value))
		{
			foreach ($value AS &$el)
			{
				$this->processregistered($el, $charset);
			}
		}

		if (is_string($value))
		{
			$value = unhtmlspecialchars(to_utf8($value, $charset, true), true);
		}

		if ($VB_API_REQUESTS['api_version'] > 1 AND is_bool($value))
		{
			if ($value)
			{
				$value = 1;
			}
			else
			{
				$value = 0;
			}
		}
	}
}

class vB_APICallback
{
	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache_Observer
	 */
	protected static $instance;

	/**
	 * An array of API callbacks
	 */
	protected $currentname;
	protected $callbacks;
	protected $params;

	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 */
	protected function __construct(){}

	/**
	 * Returns singleton instance of self.
	 *
	 * @return vB_APICallback
	 */
	public function instance()
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	* Add callback to be executed
	*
	* @param string $name - Name of call back
	* @param string $callback - Name of call back function to call
	* @param string $version - API version. Set it to previous version to overwrite old callback. Version 0 for common whitelist.
	*/
	public function add($name, $callback, $version)
	{
		if (!is_array($this->callbacks))
		{
			$this->callbacks = array();
		}

		$this->callbacks[$name][$version] = $callback;
	}

	public function setname($name)
	{
		$this->currentname = $name;
	}

	public function addparam($pos, $value)
	{
		if (!$this->currentname)
		{
			throw new Exception('Please call setname() first!');
		}
		$this->params[$this->currentname][$pos] = $value;
	}

	public function addparamref($pos, &$value)
	{
		$this->params[$this->currentname][$pos] = &$value;
	}

	public function callback()
	{
		if (sizeof($this->callbacks[$this->currentname]))
		{
			foreach ($this->callbacks[$this->currentname] AS $callback)
			{
				if (is_callable($callback))
				{
					call_user_func_array($callback, $this->params[$this->currentname]);
				}
			}
		}
	}

}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/