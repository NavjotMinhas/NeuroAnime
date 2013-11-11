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
 * AJAX View
 * XML View for standard vB AJAX html load responses.
 *
 * @TODO: This is a bit specific to the Overlay response to be a generic AJAXHTML
 * view; rename?
 *
 * The AJAX Html Loader view returns an AJAX response designed for HTML replacement.
 * The view expects the replacement html to be specified as the property $content.
 *
 * An arbitrary status should be added to the view to inform the client of the
 * server state in response to the request.  This informs the client of what action
 * is expected next.
 *
 * Complete is generally used to signal that a current UI operation is no longer
 * needed.  For example, if an overlay is open to perform an action, the complete
 * status indicates that the overlay may close.
 *
 * Additionally, the response may return URL's for various actions.  If
 * $completed_url, $error_url or $abort_url are specified then the user may be
 * redirected to the url on the given event respectively.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View_AJAXHTML extends vB_View
{
	/*Constants====================================================================*/

	const URL_CANCEL = 'cancel';
	const URL_FINISHED = 'finished';

	const STATUS_MESSAGE = 0;
	const STATUS_VIEW = 1;
	const STATUS_FINISHED = 2;



	/*Properties====================================================================*/

	/**
	 * Whether this view type needs to send content headers.
	 *
	 * @var bool
	 */
	protected $send_content_headers = true;

	/**
	 * The inner content for the response.
	 * The inner content should be a view that is rendered and returned as 'content'
	 * tag with CDATA content.
	 *
	 * @var vB_View
	 */
	protected $content;

	/**
	 * The status of the response.
	 * The status is used to notify the client on the appropriate action to take.
	 * The status is rendered as a 'status' tag.  The action to be taken is
	 * arbitrary and should be decided by the client and the view's client code.
	 *
	 * @var string
	 */
	protected $status = self::STATUS_VIEW;

	/**
	 * User friendly status message.
	 * The 'feedback' tag should be populated with a user friendly status message,
	 * giving them feedback on required input, general errors that occured, and
	 * information on the next expected action.
	 *
	 * @var string
	 */
	protected $feedback;

	/**
	 * Response errors.
	 * Errors are grouped into an 'errors' tag.  Child error elements contain the
	 * error strings for each error that is registered with the view.
	 *
	 * Each error is an array with an arbitrary error code 'code' and a string
	 * description.
	 *
	 * @var array array key => message
	 */
	protected $errors = array();

	/**
	 * The output type of this view.
	 * @see vB_View::$_output_type
	 *
	 * @var string								- A string identifier of an output type.
	 */
	protected $_output_type = vB_View::OT_NULL;

	/**
	 * Whether to allow runtime output type overriding.
	 *
	 * @var mixed
	 */
	protected $_allow_output_type_override = false;

	/**
	 * URL's
	 *
	 * @var array string
	 */
	protected $urls = array();


	/*Accessors=====================================================================*/

	/**
	 * Sets the inner content for the response.
	 *
	 * @param vB_View $content
	 */
	public function setContent(vB_View $content)
	{
		$this->content = $content;
	}


	/**
	 * Sets the response status.
	 * Optionally, a feedback message can be set here as well.
	 *
	 * @param string $status					- An arbitrary response status
	 * @param string $feedback					- User friendly feedback
	 */
	public function setStatus($status, $feedback = false)
	{
		$this->status = $status;
		$this->feedback = $feedback;
	}


	/**
	 * Sets a user friendly feedback message.
	 *
	 * @param string $feedback
	 */
	public function setFeedback($feedback)
	{
		$this->feedback = $feedback;
	}


	/**
	 * Sets a url
	 *
	 * @param string $type						- The type to report in the xml
	 * @param string $url						- The URL
	 */
	public function setUrl($type, $url)
	{
		$this->urls[$type] = $url;
	}



	/*Errors========================================================================*/

	/**
	 * Adds an error to the response.
	 * Note: Adding an error does not necessarily indicate that the AJAX request
	 * failed.  For example, an error may be used to inform the user of input
	 * validation failure.  To indicate a server side error, $status should also be
	 * set to something meaningful, such as 'error' or 'failed'.
	 *
	 * @param string $message					- User friendly error message
	 * @param mixed $code						- Arbitrary error code
	 */
	public function addError($message, $code = false)
	{
		$error = array('message' => $message, 'code' => $code);

		// Don't duplicate errors
		if (!in_array($error, $this->errors, true))
		{
			$this->errors[] = $error;
		}
	}


	/**
	 * Adds an array of errors.
	 * Replacement error codes can be given.
	 *
	 * @param array array key => message
	 * @param array string $errorcodes
	 */
	public function addErrors($errors, array $errorcodes = null)
	{
		if (!is_array($errors) OR empty($errors))
		{
			return;
		}

		foreach ($errors AS $code => $error)
		{
			if (is_array($error))
			{
				if (!isset($error['message']))
				{
					throw (new vB_Exception_View('No message defined in errors added to ' . get_class($this)));
				}

				$message = $error['message'];
				$code = (isset($error['code']) ? $error['code'] : false);
			}
			else if ($error instanceof vB_Phrase)
			{
				$message = $error;

				if (isset($errorcodes[$code]))
				{
					$code = $errorcodes[$code];
				}
			}

			$this->addError($message, $code);
		}
	}



	/*Render========================================================================*/

	/**
	 * Renders the view to a string and returns it.
	 *
	 * @return string
	 */
	public function render($send_content_headers = false)
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder(vB::$vbulletin, 'text/xml');

		$xml->add_group('container');
		$xml->add_tag('success', 1);

		if ($this->content)
		{
			$xml->add_tag('html', $this->content->render());
		}

		$xml->add_tag('title', $this->title);
		$xml->add_tag('status', $this->status);
		$xml->add_tag('message', $this->feedback);

		if (sizeof($this->errors))
		{
			$xml->add_group('errors');

			foreach ($this->errors AS $error)
			{
				$xml->add_tag('error', $error['message'], array('errcode' => $error['code']));
			}

			$xml->close_group();
		}

		if (sizeof($this->urls))
		{
			$xml->add_group('urls');

			foreach ($this->urls AS $type => $url)
			{
				$xml->add_tag('url', $url, array('type' => $type));
			}

			$xml->close_group();
		}

		$xml->close_group();

		if ($send_content_headers AND !vB::contentHeadersSent())
		{
			$xml->send_content_type_header();
			$xml->send_content_length_header();

			vB::contentHeadersSent(true);
		}

		return $xml->fetch_xml();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/