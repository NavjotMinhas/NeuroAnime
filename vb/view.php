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
 * View
 * Base class for views.
 *
 * The view class is responsible for accepting view data, preparing it for output and
 * rendering it into a string.
 *
 * Each view must have a result id to identify the output data required.  This
 * allows a single view class to be reused for various output where much of the data
 * transformation will be the same.
 *
 * The view class can also use it's result id to prenotify templater classes of the
 * required resources.  This allows templaters to gather resources for all views
 * before render time; such as templates.
 *
 * The resultid itself should be a unique string that indicates the output result
 * that will be created.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View
{
	/*Properties====================================================================*/

	/**
	 * The unique string id for the view result.
	 *
	 * @var string
	 */
	protected $_resultid;

	/**
	 * Array of output type => templater.
	 * The templaters should be instantiated and registered with the base view class
	 * using registerTemplater().
	 *
	 * //TODO: Provide a non static method for this and keep track of the root view
	 * in a view tree.  This allows applications or subsystems to use a different
	 * set to their parent applications or client code.
	 *
	 * @var array vB_Templater					- Array of output type => $templater
	 */
	protected static $_templaters = array();

	/**
	 * The output data assigned to the view.
	 * Properties may be transformed when adding.
	 */
	protected $_properties = array();

	/**
	 * The output type of this view.
	 * Output types identifiers are used to match the rendering of a view to a
	 * templater.  The output_type value itself is arbitrary but must match an
	 * output type for a templater previously registered in  vB_View::$templaters
	 * with vB_View::registerTemplater().
	 *
	 * A set of constants are defined in vB_View to help with consistency but these
	 * are by no means exclusive.  If your application deals with further output
	 * types then it may use an extended base view class with further constants.
	 *
	 * @var string								- A string identifier of an output type.
	 */
	protected $_output_type = vB_View::OT_XHTML;

	/**
	 * Whether to allow runtime output type overriding.
	 * If this is true then client code can set an output type for the view on
	 * construction.
	 * @see vB_View::$output_type
	 *
	 * @var mixed
	 */
	protected $_allow_output_type_override = true;

	/**
	 * Result id's of inner views for prenotification.
	 * Useful if views are created as properties at render time so that the
	 * templater can be prenotified before render time.
	 *
	 * @var array mixed
	 */
	protected $_cache_results;

	/**
	 *  set false if is to be hidden when empty, say the doesn't return any results
	 *	@boolean
	*/
	protected $_display = true;

	/*Constants=====================================================================*/

	/**
	 * Identifiers for output types.
	 * These constants are for conveniance for keeping consistancy between view
	 * output types and registered templater output types.
	 *
	 * Note: OT_NULL indicates that the view will not use a templater, and will
	 * parse it's output with it's own render method.
	 *
	 * Note: A templater could handle various content types.  You may want to output
	 * xml using your xhtml templater.  You can either register the templater with
	 * multiple output types or configure the xml view to use the x/html output type.
	 */
	const OT_NULL = 'null';
	const OT_XHTML = 'xhtml';
	const OT_HTML = 'html';
	const OT_XML = 'xml';
	const OT_RSS = 'rss';
	const OT_JSON = 'json';



	/*Initialisation================================================================*/

	/**
	 * Constructor.
	 * Assigns the view result.
	 *
	 * @param string $resultid					- The string id of the view result.
	 */
	public function __construct($resultid = false, $output_type = false)
	{
		if ($resultid)
		{
			$this->_resultid = strtolower($resultid);
		}
		else if (!$this->_resultid)
		{
			throw (new vB_Exception_View('No resultid set for view \'' . get_class($this) . '\''));
		}

		if ($output_type AND $this->_allow_output_type_override)
		{
			if (self::OT_NULL == $output_type)
			{
				throw (new vB_Exception_View('A vB_View can only have the output type OT_NULL by definition.  Setting it is not allowed'));
			}

			$this->_output_type = $output_type;
		}

		// Prenotify templater of a resultid so it can prefetch resources.
		if (isset(self::$_templaters[$this->_output_type]))
		{
			self::$_templaters[$this->_output_type]->notifyResult($this->_resultid);

			if (!empty($this->_cache_results))
			{
				self::$_templaters[$this->_output_type]->notifyResults($this->_cache_results);
			}
		}

		// TODO: Remove once this has been done with the template system
		$this->securitytoken = vB::$vbulletin->userinfo['securitytoken'];
		$this->sessionhash = vB::$vbulletin->session->vars['sessionurl'];
	}



	/*Templating====================================================================*/

	/**
	 * Registers a templater with an output type.
	 * The templater will be used to render the view data with the resultid into a
	 * string for output.
	 *
	 * Example output types may be 'html', 'xml' etc.
	 *
	 * @param string $output_type				- Arbitrary identifier for the output type
	 * @param vB_Templater $templater			- The templater to use to render the output_type
	 */
	public static function registerTemplater($output_type, vB_Templater $templater)
	{
		if (self::OT_NULL == $output_type)
		{
			throw (new vB_Exception_View('Registering a templater with the vB_View::OT_NULL output type is not allowed'));
		}

		self::$_templaters[$output_type] = $templater;
	}



	/*Properties====================================================================*/

	/**
	 * Sets a property.
	 *
	 * @param string $property					- Name of the property to set
	 * @param mixed $value						- The value to set it to
	 */
	public function __set($property, $value)
	{
		$this->_properties[$property] = $value;
	}


	/**
	 * Gets a property
	 *
	 * @param string $property					- Name of the property to get
	 * @return mixed							- Value of the property
	 */
	public function __get($property)
	{
		if (isset($this->_properties[$property]))
		{
			return $this->_properties[$property];
		}

		return null;
	}


	/**
	 * Checks if a property is set
	 *
	 * @param string $property					- The name of the property to check
	 * @return bool								- Whether the property is set
	 */
	public function __isset($property)
	{
		return isset($this->_properties[$property]);
	}


	/**
	 * Adds an assoc array of properties.
	 * Accepts an assoc array of property => value and assigns each individual
	 * element as a single property.
	 *
	 * @param array mixed $properties			- The array of properties to add
	 */
	public function addArray($properties, $prefix = false)
	{
		if (!is_array($properties))
		{
			throw (new vB_Exception_View('Properties added to view with addArray are not an array'));
		}

		if ($prefix)
		{
			$prefixed = array();

			foreach ($properties AS $property => $value)
			{
				$prefixed[$prefix . $property] = $value;
			}

			$properties = $prefixed;
		}

		$this->_properties = array_merge($properties, $this->_properties);
	}

	/**
	 * Fetch final display status
	 *
	 * @return bool
	 */
	public function fetchDisplayView()
	{
		return $this->_display;
	}

	/**
	 * Set final display status
	 *
	 * @param	boolean
	 */
	public function setDisplayView($value)
	{
		$this->_display = (bool)$value;
	}

	/*Render========================================================================*/

	/**
	 * Renders the view to a string and returns it.
	 * Note: send_content_headers should only be set as true if this view is
	 * rendering the top level output.
	 *
	 * @param bool $headers						- Whether the View should send any appropriate headers.
	 * @return string
	 */
	public function render($send_content_headers = false)
	{
		if (self::OT_NULL == $this->_output_type)
		{
			throw (new vB_Exception_View('vB_View\'s output type is OT_NULL but no Render method is defined'));
		}

		if (!isset(self::$_templaters[$this->_output_type]))
		{
			throw (new vB_Exception_View('No templater registered for a view\'s output type \'' . htmlspecialchars($this->_output_type) . ' \''));
		}

		// Perform any required property transformations
		$this->prepareProperties();

		// Render ourself with the templater and return the result
		$result = self::$_templaters[$this->_output_type]->renderView($this);

		if ($send_content_headers)
		{
			$this->sendContentHeaders(strlen($result));
		}

		return $result;
	}


	/**
	 * Sends the appropriate headers to the client for the view.
	 */
	protected function sendContentHeaders($length)
	{
		if (!vB::contentHeadersSent())
		{
			vB::$vbulletin->contenttype = self::$_templaters[$this->_output_type]->getContentType();

			/* done by exec_headers() for now
			$charset = self::$_templaters[$this->_output_type]->getCharset();

			header("Content-Type: $contenttype; charset=$charset");
			header("Content-Length: " . $length);

			vB::contentHeadersSent(true);
			*/
		}
	}


	/**
	 * Prepares properties for rendering.
	 * Child classes should override this method to perform all necessary changes to
	 * the raw data to prepare it for output.
	 */
	protected function prepareProperties(){}


	/**
	 * Magic toString method for rendering.
	 *
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			return $this->render();
		}
		catch(vB_Exception $e)
		{
			//If debug, return the error, else
			return '';
		}
	}



	/*Accessors=====================================================================*/

	/**
	 * Returns the view's result id.
	 *
	 * @return string
	 */
	public function getResult()
	{
		return $this->_resultid;
	}


	/**
	 * Returns the prepared view data;
	 *
	 * @return array mixed
	 */
	public function getViewData()
	{
		return $this->_properties;
	}


	/**
	 * Unsets a property.
	 * This may only be done internally.
	 *
	 * @param string $property					- The name of the property to unset.
	 */
	public function __unset($property)
	{
		unset($this->_properties[$property]);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
