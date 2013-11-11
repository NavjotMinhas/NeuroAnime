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

if (VB_AREA != 'Install' AND !isset($GLOBALS['vbulletin']->db))
{
	exit;
}

class vB_Upgrade_skimlinks extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'skimlinks';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'skimlinks';

	/**
	* Can we install the blog product?
	*
	* @var	bool
	*/
	private $caninstall = true;

	/**
	* Result of $product->verify_install
	*
	* @var	mixed
	*/
	private $productresult = null;

	/**
	* Product Obj
	*
	* @var	string
	*/
	private $product = null;

	/*Properties====================================================================*/

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	*/
	public function __construct(&$registry, $phrase, $maxversion)
	{
		parent::__construct($registry, $phrase, $maxversion);

		if (defined('SKIPDB'))
		{
			$this->caninstall = true;
			return;
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		require_once(DIR . '/includes/class_upgrade_product.php');
		$this->product = new vB_Upgrade_Product($registry, $phrase['vbphrase'], true, $this->caller);
		$this->caninstall = ($this->productresult =  $this->product->verify_install('skimlinks'));
	}

	/**
	 *	Verify if product upgrade step needs to be executed
	 *
	 * @param	string	version string
	 * @param	bool	Allow check to succeed if version is the same, only used to force a step to run even if that version is already installed
	 */
	private function verify_product_version($version, $allow_same_version = false)
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return false;
		}

		if (
			!$this->product->installed_version
				OR
			$this->is_newer_version($this->product->installed_version, $version)
		)
		{
			if ($allow_same_version AND $this->product->installed_version == $version)
			{
				return true;
			}
			
			$this->skip_message();
			return false;
		}

		return true;
	}

	/**
	* Step #1 - Install New Skimlinks
	* All schema changes need to be reflected here as a new install only executes this step
	*
	*/
	function step_1()
	{
		if (!$this->caninstall)
		{
			$this->add_error($this->productresult, self::PHP_TRIGGER_ERROR, true);
			return;
		}

		if ($this->product->installed_version)
		{
			$this->skip_message();
			return;
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "skimlinks"),
			"CREATE TABLE " . TABLE_PREFIX . "skimlinks (
				userid INT UNSIGNED NOT NULL,
				enabled TINYINT UNSIGNED NOT NULL,
				PRIMARY KEY (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

		$skimlinks_salt = md5(microtime() . $this->registry->options['bburl']);
		$this->set_new_option('skimlinks_salt', 'version', $skimlinks_salt, false, '', 'skimlinks');
	}

	/**
	* Step #2 - Final Step
	*	This must always be the last step. Just renumber this step as more upgrade steps are added before
	*
	*/
	function step_2()
	{
		if ($this->caninstall)
		{
			$result = $this->product->post_install();
			if (!is_array($result))
			{
				$this->add_error($result, self::PHP_TRIGGER_ERROR, true);
				return false;
			}
			$this->show_message($this->phrase['final']['product_installed']);
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
