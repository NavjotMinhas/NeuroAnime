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
abstract class vB
{
	/*Properties====================================================================*/

	/**
	 * Whether the framework has been initialized.
	 *
	 * @var bool
	 */
	private static $initialized;

	/**
	 * The legacy registry object
	 *
	 * @var vB_Registry
	 */
	public static $vbulletin;

	/**
	 * The database class.
	 * This is the main database used by vBulletin.
	 *
	 * @var vB_Database
	 */
	public static $db;

	/**
	 * Array of classname => filename for autoloading.
	 * This is purely for performance reasons and is optional.  Each entry should
	 * specify the class name and the absolute path to the file to include.
	 *
	 * It is also useful for any classes residing in files that do not meet the
	 * class name => file location pattern that is resolved by the autoloader.
	 *
	 * @var array classname => filename
	 */
	private static $load_map = array();

	/**
	 * Whether content headers have been sent.
	 * This only tracks the Content-Type and Content-Length headers.  When a view or
	 * other system sends content headers, it should let vB know with
	 * vB::contentHeadersSent(true).
	 *
	 * @var bool
	 */
	private static $content_headers_sent = false;



	/*Initialisation================================================================*/

	/**
	 * Initializes the vB framework.
	 * All framework level objects and services are created so that they are available
	 * throughout the application.  This is only done once per actual request.
	 *
	 * Note: If the framework is used this way then there are several limitations.
	 *  - If no vB_Bootstrap was created (ie, the admincp), then you cannot render any
	 *    views created by the framework.
	 *  - VB_ENTRY must be defined to a valid request script that runs the framework
	 *    with vB::Main()
	 *  - If you are rendering views, try to create all of the views that will be
	 *    used before rendering any of them.  This optimises template and phrase
	 *    fetching.
	 */
	public static function init($relative_path = false)
	{
		global $vbulletin, $vbphrase;

		if (self::$initialized)
		{
			return;
		}

		// Reference legacy registry
		self::$vbulletin = $vbulletin;

		// Legacy DB
		self::$db = $vbulletin->db;

		// Set a top level exception handler
		set_exception_handler(array('vB', 'handleException'));

		// Set unserializer to use spl registered autoloader
		ini_set('unserialize_callback_func', 'spl_autoload_call');

		// Set class autoloader
		spl_autoload_register(array('vB', 'autoload'));

		// Legacy language
		vB_Phrase::setLanguage((!empty(self::$vbulletin->session->vars['languageid']) ? self::$vbulletin->session->vars['languageid'] : intval(self::$vbulletin->options['languageid'])));
		vB_Phrase::preCache($vbphrase, $GLOBALS['phrasegroups']);

		// Ensure we have friendly url class
		require_once (DIR . '/includes/class_friendly_url.php');

		if ($relative_path)
		{
			vB_Router::setRelativePath($relative_path);
		}

		// Done
		self::$initialized = true;
	}



	/*Response======================================================================*/

	/**
	 * Gets or sets whether content headers have been sent.
	 *
	 * @param bool $sent						- If true, the headers have been sent
	 */
	public function contentHeadersSent($sent = false)
	{
		if ($sent)
		{
			self::$content_headers_sent = true;
		}

		return self::$content_headers_sent;
	}



	/*Autoload======================================================================*/

	/**
	 * Autloads a class file when required.
	 * Classnames are broken down into segments which are used to resolve the class
	 * directory and filename.
	 *
	 * If the first segment matches 'vB' then it is in /vB else it is in
	 * /packages/segment/
	 *
	 * An optional load map of classname => filename can be passed to skip the path
	 * resolution.
	 *
	 * @TODO: Investigate performance of file_exists and where $check_file could be
	 * used, defaulting to false.
	 *
	 * @param string $classname					- The name of the class to load
	 * @param array $load_map					- Assoc array of classname => filename for quick load
	 * @param bool $check_file					- Whether to check if the file exists and return false
	 * @return bool								- Success
	 */
	public static function autoload($classname, $load_map = false, $check_file = true)
	{
		if (!$classname)
		{
			return false;
		}

		$filename = false;
		$fclassname = strtolower($classname);

		if (preg_match('#\W#', $fclassname))
		{
			return false;
		}

		if (isset($load_map[$classname]))
		{
			$filename = $load_map[$classname];
		}
		else if (isset(self::$load_map[$classname]))
		{
			$filename = self::$load_map[$classname];
		}
		else
		{
			$segments = explode('_', $fclassname);

			$filename = ('vb' == $segments[0] ? VB_PATH : VB_PKG_PATH);

			if (sizeof($segments) > ('vb' == $segments[0] ? 2 : 1))
			{
				$filename .= implode('/', array_slice($segments, ('vb' == $segments[0] ? 1 : 0), -1)) . '/';
			}

			$filename .= array_pop($segments) . '.php';
		}

		// Include the required class file
		if ($filename)
		{
			if ($check_file AND !file_exists($filename))
			{
				return false;
			}

			require($filename);

			if (class_exists($classname, false) OR interface_exists($classname, false))
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Allows autoloaders to be registered before the vb autoloader.
	 *
	 * @param callback $callback
	 */
	public static function autoloadPreregister($add_callback)
	{
		$registered = spl_autoload_functions();

		foreach ($registered AS $callback)
		{
			spl_autoload_unregister($callback);
		}

		array_unshift($registered, $add_callback);

		foreach ($registered AS $callback)
		{
			spl_autoload_register($callback);
		}
	}


	/**
	 * Allows entries to be added to the load map.
	 * This is especially useful for calling during application initialization.
	 *
	 * @param array class => file $load_map
	 */
	public static function autoloadMap(array $load_map)
	{
		self::$load_map = array_merge($load_map, self::$load_map);
	}



	/*ErrorHandling=================================================================*/

	/**
	 * Catches any uncaught exceptions.
	 *
	 * @param Exception $exception
	 */
	public static function handleException($exception)
	{
		if (!$code = $exception->getCode())
		{
			$code = E_USER_ERROR;
		}

		echo($exception->getMessage() . ' on line ' . $exception->getLine() . ' in ' . $exception->getFile() . "<br />\n" . nl2br($exception->getTraceAsString()) . '<br /><br />');

		// TODO: Our own error output, with the exception's message and stack trace
		trigger_error($exception->getMessage() . ' on line ' . $exception->getLine() . ' in ' . $exception->getFile() . "\n", $code);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28823 $
|| ####################################################################
\*======================================================================*/