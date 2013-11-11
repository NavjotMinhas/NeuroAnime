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
 * The Base Content Handler.
 * The content handler provides a facade interface to interact with a particular
 * content type.  It should provide all methods, or act as a gateway to other
 * classes that are reqiured to fetch, render and manage a piece of content or
 * content type.
 *
 * It also acts as a subcontroller for rendering the content requested in the route.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Content
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this content.
	 *
	 * @var string
	 */
	protected $package;

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this content.
	 * Note: The class should always be resolvable from a contenttypeid using the
	 * contenttype table.
	 *
	 * @var string
	 */
	protected $class;

	/**
	 * The id of particular content being worked on.
	 * How this id is interpreted is entirely up to the content handler for the
	 * specific content type.  This is to allow the content handler to load the
	 * content to be worked with.
	 *
	 * The contentid is only used to load the content item, if it was not already
	 * set.
	 *
	 * @var int
	 */
	protected $contentid;

	/**
	 * The contenttypeid of the content being worked on.
	 *
	 * @var int
	 */
	protected $contenttypeid;

	/**
	 * The model item for the content.
	 * Not all methods require a content item to use them.  However if one is needed
	 * it can be set externally, or loaded based on the specified contentid.
	 *
	 * @var vB_Item_Content
	 */
	protected $content;

	/**
	 * Whether the content type uses a content item.
	 * If this is true, the content will be loaded for views.
	 *
	 * @var bool
	 */
	protected $use_item = true;



	/*ViewInfo======================================================================*/

	/**
	 * Standard view types that can be requested through aggregation.
	 * @see $view_info
	 * @see $view_handlers
	 */
	const VIEW_LIST = 1;
	const VIEW_AGGREGATE = 2;
	const VIEW_PREVIEW = 3;
	const VIEW_PAGE = 4;

	/**
	 * Info required for aggregated view types.
	 *
	 * This allows content agnostic aggregators to resolve the info required for
	 * specific standardised views so that it's collections can still be optimised.
	 *
	 * The flag values should be derived from the collection's item class.
	 * @see vB_Item_Content
	 * @see vB_Model
	 *
	 * This should be overridden by child classes where the required info changes.
	 *
	 * @var array
	 */
	protected $view_info = array(
		self::VIEW_LIST => vB_Item_Content::INFO_BASIC,
		self::VIEW_AGGREGATE => vB_Item_Content::INFO_BASIC,
		self::VIEW_PREVIEW => vB_Item_Content::INFO_BASIC,
		self::VIEW_PAGE => vB_Item_Content::INFO_BASIC
	);



	/*Initialisation================================================================*/

	/**
	 * Constructor.
	 *
	 * Note: Content is optional.  Content handlers have methods that do not require
	 * content to be associated with the handler in order to use them.
	 *
	 * If used, $content may be passed as an integer.  How the id is interpreted is
	 * at the specific content handler's discression.  The content handler is then
	 * responsible for creating an appropriate model object based on the id when
	 * methods are called that require it.
	 *
	 * Alternatively, a model object may be passed if it is already known.
	 *
	 * @param mixed $content
	 */
	protected function __construct($content = false)
	{
		if (!$this->package OR !$this->class)
		{
			throw (new vB_Exception_Content('No package or contenttype class defined for content item '));
		}

		if ($content instanceof vB_Item_Content)
		{
			$this->content = $content;
			$this->contentid = $content->getId();
		}
		else
		{
			$this->contentid = $content;
		}
	}


	/**
	 * Factory method for creating a type specific content object.
	 * Note: The contentid is interpreted at each contenttype's descretion.
	 *
	 * @param string $package					- The class identifier for the package
	 * @param string $class						- The class identifier for the contenttype
	 * @param mixed $contentid					- The id of the specific content to load
	 *
	 * @return vB_Content
	 */
	public static function create($package, $class, $contentid = false)
	{
		$class = $package . '_Content_' . $class;

		return new $class($contentid);
	}


	/**
	 * Sets the content item.
	 * Allows externally fetched content to be handed to the content handler.
	 * Child classes may want to perform some type verification here, or override
	 * the parameters to ensure the corrent item content type was passed.
	 *
	 * @param vB_Model $content					- The model object representing the content
	 */
	public function setContentItem(vB_Item_Content $content)
	{
		$this->content = $content;
		$this->contentid = $this->content->getId();

		// Reset any local vars that were resolved based on the content
		$this->Reset();
	}


	/**
	 * Resets any local content dependant information.
	 * This is useful when the content object has changed.
	 */
	protected function Reset()
	{
		$this->contentid = ($this->content ? $this->content->getId() : false);
	}



	/*ItemHandling==================================================================*/

	/**
	 * Returns the appropriate info flags required to render the specified view.
	 * Note the $viewtype should match one of the VIEW constants.
	 *
	 * @param int $view							- The view that is required
	 * @return int								- The required info flags for the specified view
	 */
	public function getViewInfoFlags($viewtype)
	{
		if (!isset($this->view_info[$viewtype]))
		{
			throw (new vB_Exception_Content('View type \'' . htmlspecialchars($viewtype) . '\' given for vB_Content::getViewInfoFlags() is not valid'));
		}

		return $this->view_info[$viewtype];
	}


	/**
	 * Fetches the most generic collection class for the required info flags.
	 * TODO: This should really be gotten from the vB_Item class, or defined as a
	 * class property.
	 *
	 * @param int $infoflags					- Info flags required for loading
	 * @return string							- The name of the most generic collection class
	 */
	public function getCollectionClass($infoflags)
	{
		return $this->package . '_Collection_Content_' . $this->class;
	}


	/**
	 * Fetches the itemid required for the specified collection class.
	 *
	 * @param string $collectionclass			- The name of the collection class
	 * @param int $contenttypeid				- The id of the contenttype
	 * @param int $contentid					- The id of the content
	 * @return mixed							- The id used as the itemid in the specified collection class
	 */
	public function getCollectableKey($collectionclass, $contenttypeid, $contentid)
	{
		return $contentid;
	}


	/**
	 * Sets preloaded info from an existing item to the current content.
	 *
	 * @param vBCms_Item_Content $node
	 */
	public function castFrom(vB_Item_Content $source)
	{
		if ($this->use_item)
		{
			$this->assertContent();

			$source->castInfo($this->content);
		}
	}



	/*Render========================================================================*/

	/**
	 * Fetches a rich page view of the specified content item.
	 * This method can accept parameters from the client code which are usually
	 * derived from user input.  Parameters are passed as an array in the order that
	 * they were received.  Parameters do not normally have assoc keys.
	 *
	 * Note: Parameters are always passed raw, so ensure that validation and
	 * escaping is performed where required.
	 *
	 * Child classes will inevitably override this with wildly different
	 * implementations.
	 *
	 * @param array mixed $parameters			- Request parameters
	 * @return vB_View | bool					- Returns a view or false
	 */
	public function getPageView($parameters = false)
	{
		if ($parameters)
		{
			$this->setParameters($parameters);
		}

		// Load the content item
		if (!$this->loadContent($this->getViewInfoFlags(self::VIEW_PAGE)))
		{
			throw (new vB_Exception_404(new vB_Phrase('error', 'page_not_found')));
		}

		if (!$this->canView())
		{
			throw (new vB_Exception_AccessDenied());
		}

		// Create view
		$view = $this->createView('page', self::VIEW_PAGE);

		// Add the content to the view
		$this->populateViewContent($view, self::VIEW_PAGE);

		return $view;
	}


	/**
	 * Fetches a preview of the specified content item.
	 *
	 * @return vB_View
	 */
	public function getPreview()
	{
		$load_flags = self::getViewInfoFlags(self::VIEW_PREVIEW);

		// Load the content item
		$this->loadContent($load_flags);

		if (!$this->canPreview())
		{
			throw (new vB_Exception_AccessDenied());
		}

		$view = $this->createView('preview', self::VIEW_PREVIEW);
		$this->populateViewContent($view, self::VIEW_PREVIEW);

		return $view;
	}


	/**
	 * Populates a view with the expected info from a content item.
	 * Note: The view type should be based on the VIEW constants defined by the
	 * content handler class.
	 *
	 * Child classes will need to extend or override this for custom content.
	 *
	 * @param vB_View $view
	 * @param int $viewtype
	 */
	protected function populateViewContent(vB_View $view, $viewtype = self::VIEW_PAGE)
	{
		$this->loadContent();

		$view->id = $this->content->getId();
		$view->contenttype = vB_Types::instance()->getTypeKey($this->package, $this->class);
		$view->contenttypetitle = vB_Types::instance()->getContentTypeTitle($view->contenttype);
		$view->contentid = $this->getContentId();
		$view->userid = $this->getUserId();
		$view->username = $this->getUsername();
		$view->class = $this->class;
		$view->package = $this->package;
		$view->publishdate = $this->getPublishDate();
		$view->description = $this->getDescription();
		$view->title = $this->getTitle();
		$view->page_url = $this->getPageURL();
		// TODO: musername
	}


	/**
	 * Creates a content view.
	 * The default method fetches a view based on the required result, package
	 * identifier and content class identifier.  Child classes may want to override
	 * this.  Ths method is also voluntary if the getView methods are overriden.
	 *
	 * @param string $result					- The result identifier for the view
	 * @param int $viewtype
	 * @return vB_View
	 */
	protected function createView($result, $viewtype = self::VIEW_PAGE)
	{
		// TODO: Create a template group for content
		switch ($viewtype)
		{
			case (self::VIEW_LIST):
				$result = 'content_listitem';
				break;
			case (self::VIEW_AGGREGATE):
				$result = 'content_item';
				break;
			default:
				$result = strtolower($this->package . '_content_' . $this->class . '_' . $result);
				break;
		}

		return new vB_View_Content($result);
	}



	/*Parameters====================================================================*/

	/**
	 * Accepts the client parameters passed to render a page view.
	 * If child classes want to accept the parameters then they will need to define
	 * this.
	 *
	 * @param array mixed $parameters			- The parameters to accept.
	 */
	protected function setParameters($parameters){}



	/*Item==========================================================================*/

	/**
	 * Ensures the current content item is instantiated and loaded.
	 *
	 * @param int $load_flags					- The required info needed from the content item
	 * @return bool								- Return whether the content was loaded and is valid
	 */
	protected function loadContent($load_flags = false, $reload = false)
	{
		if (!$this->use_item)
		{
			return true;
		}

		if ($reload)
		{
			unset($this->content);
		}

		$this->assertContent();

		if ($load_flags)
		{
			$this->content->requireInfo($load_flags);
		}

		return $this->content->isValid();
	}


	/**
	 * Reloads the current content item.
	 *
	 * @param int $load_flags					- The required info needed from the content item
	 * @return bool								- Return whether the content was loaded and is valid
	 */
	protected function reloadContent($load_flags = false)
	{
		return $this->loadContent($load_flags, true);
	}


	/**
	 * Ensures that the current content item is instantiated.
	 * This creates the content item without loading anything.
	 */
	protected function assertContent()
	{
		if (!$this->use_item)
		{
			return;
		}

		if (!$this->content)
		{
			$this->content = vB_Item_Content::create($this->package, $this->class, $this->contentid);
		}
	}



	/*Permissions===================================================================*/

	/**
	 * Determines whether the current user can view the content.
	 *
	 * @return bool
	 */
	public function canView()
	{
		return true;
	}


	/**
	 * Determines whether the current user can preview the content.
	 *
	 * @return bool
	 */
	public function canPreview()
	{
		return true;
	}



	/*Errors========================================================================*/

	/**
	 * Returns a standard view for displaying a list of errors.
	 *
	 * @return vB_View
	 */
	public static function getErrorSummary($errors, $keynames = false, $title = false)
	{
		if (!$title)
		{
			$title = new vB_Phrase('global', 'errors_occured_when_submitted');
		}

		foreach ($errors AS $key => $error)
		{
			$errors[$key] = array('name' => $key, 'message' => $error);

			// use replacement keynames where provided
			if (isset($keynames[$key]))
			{
				$errors[$key]['name'] = $keynames[$key];
			}
		}

		$error_view = new vB_View('error_summary');
		$error_view->errors = $errors;
		$error_view->title = $title;

		return $error_view;
	}



	/*Accessors=====================================================================*/

	/**
	 * Gets the class identifier of the content.
	 *
	 * @return string
	 */
	public function getClass()
	{
		if ($this->use_item)
		{
			if ($this->content)
			{
				return $this->content->getClass();
			}
		}

		return $this->class;
	}


	/**
	 * Gets the package identifier of the content.
	 *
	 * return string
	 */
	public function getPackage()
	{
		if ($this->use_item)
		{
			$this->loadContent();

			return $this->content->getPackage();
		}

		return $this->package;
	}


	/**
	 * Returns the contenttype id for the content.
	 *
	 * @return int
	 */
	public function getContentTypeID()
	{
		if (isset($this->contenttypeid))
		{
			return $this->contenttypeid;
		}

		return $this->contenttypeid = vB_Types::instance()->getContentTypeID(array('package' => $this->package, 'class' => $this->class));
	}


	/**
	 * Returns the description of the set content.
	 * This allows content handlers to transform or resolve the description before
	 * providing it from the content item.
	 *
	 * @return string
	 */
	abstract public function getDescription();


	/**
	 * Returns a human readable title for the content.
	 * This allows content handlers to transform or resolve the title before
	 * providing it from the content item.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		if ($this->use_item)
		{
			$this->loadContent();

			return $this->content->getTitle();
		}

		return false;
	}


	/**
	 * Returns the id of the content being handled.
	 *
	 * @return int
	 */
	public function getContentId()
	{
		if ($this->content)
		{
			return $this->content->getId();
		}

		if ($this->contentid)
		{
			return $this->contentid;
		}

		return false;
	}


	/**
	 * Returns the user id of the content author.
	 *
	 * @return unknown_type
	 */
	public function getUserId()
	{
		if ($this->use_item)
		{
			$this->loadContent();

			return $this->content->getUserId();
		}

		return false;
	}


	/**
	 * Returns the name of the content author
	 *
	 * @return string
	 */
	public function getUsername()
	{
		if ($this->use_item)
		{
			$this->loadContent();

			return $this->content->getUsername();
		}

		return false;
	}


	/**
	 * Fetches the URL to the default content page for the current content.
	 *
	 * Note: The full URL should be returned and not just a route segment.  This
	 * ensures that the URL is parsed regardless of the application that is used to
	 * locate the content.
	 *
	 * @return string | bool					- The url or boolean false
	 */
	abstract public function getPageURL();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/