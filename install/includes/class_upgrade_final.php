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

class vB_Upgrade_final extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = 'final';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = 'final';

	/*Properties====================================================================*/


	/**
	* Step #1 - Import Settings XML
	*
	*/
	function step_1()
	{
		build_forum_permissions();
		vBulletinHook::build_datastore($this->db);
		build_product_datastore();

		if (VB_AREA == 'Upgrade')
		{
			$this->show_message($this->phrase['final']['import_latest_options']);
			require_once(DIR . '/includes/adminfunctions_options.php');

			if (!($xml = file_read(DIR . '/install/vbulletin-settings.xml')))
			{
				$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-settings.xml'), self::PHP_TRIGGER_ERROR, true);
				return;
			}

			$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-settings.xml'));
			xml_import_settings($xml);
			$this->show_message($this->phrase['core']['import_done']);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #2 - Import Admin Help XML
	*
	*/
	function step_2()
	{
		$this->show_message($this->phrase['final']['import_latest_adminhelp']);
		require_once(DIR . '/includes/adminfunctions_help.php');

		if (!($xml = file_read(DIR . '/install/vbulletin-adminhelp.xml')))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-adminhelp.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-adminhelp.xml'));

		xml_import_help_topics($xml);
		$this->show_message($this->phrase['core']['import_done']);
	}

	/**
	* Step #3 - Import Language XML
	*
	*/
	function step_3()
	{
		$this->show_message($this->phrase['final']['import_latest_language']);
		require_once(DIR . '/includes/adminfunctions_language.php');

		if (!($xml = file_read(DIR . '/install/vbulletin-language.xml')))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-language.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-language.xml'));

		xml_import_language($xml, -1, '', false, true, !defined('SUPPRESS_KEEPALIVE_ECHO'));
		build_language();
		build_language_datastore();
		$this->show_message($this->phrase['core']['import_done']);
	}

	/**
	* Step #4 - Import Style XML
	*
	* @param	array	contains id to startat processing at
	*
	*/
	function step_4($data = null)
	{
		$perpage = 1;
		$startat = intval($data['startat']);
		require_once(DIR . '/includes/adminfunctions_template.php');

		if (!($xml = file_read(DIR . '/install/vbulletin-style.xml')))
		{
			$this->add_error(sprintf($this->phrase['vbphrase']['file_not_found'], 'vbulletin-style.xml'), self::PHP_TRIGGER_ERROR, true);
			return;
		}

		if ($startat == 0)
		{
			$this->show_message(sprintf($this->phrase['vbphrase']['importing_file'], 'vbulletin-style.xml'));
		}

		$info = xml_import_style($xml, -1, -1, '', false, 1, false, $startat, $perpage);

		if (!$info['done'])
		{
			$this->show_message($info['output']);
			return array('startat' => $startat + $perpage);
		}
		else
		{
			$this->show_message($this->phrase['core']['import_done']);
		}
	}

		/**
	* Step #5 Check Product Dependencies
	*
	*/
	function step_5()
	{
		if (VB_AREA == 'Install')
		{
			$this->skip_message();
			return;
		}

		$this->show_message($this->phrase['final']['verifying_product_dependencies']);

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		require_once(DIR . '/includes/class_upgrade_product.php');
		$this->product = new vB_Upgrade_Product($this->registry, $this->phrase['vbphrase'], true, $this->caller);

		$dependency_list = array();
		$product_dependencies = $this->db->query_read("
			SELECT pd.*
			FROM " . TABLE_PREFIX . "productdependency AS pd
			INNER JOIN " . TABLE_PREFIX . "product AS p ON (p.productid = pd.productid)
			WHERE
				###pd.productid NOT IN ('','vbulletin')###
					pd.productid IN ('vbblog', 'vbcms', 'skimlinks')
					AND
				p.active = 1
			ORDER BY
				pd.dependencytype, pd.parentproductid, pd.minversion
		");
		while ($product_dependency = $this->db->fetch_array($product_dependencies))
		{
			$dependency_list["$product_dependency[productid]"][] = array(
				'dependencytype'  => $product_dependency['dependencytype'],
				'parentproductid' => $product_dependency['parentproductid'],
				'minversion'      => $product_dependency['minversion'],
				'maxversion'      => $product_dependency['maxversion'],
			);
		}

		$product_list = fetch_product_list(true);
		$disabled = array();

		foreach($dependency_list AS $productid => $dependencies)
		{
			$this->show_message(sprintf($this->phrase['final']['verifying_product_x'], $productid));
			$this->product->productinfo['productid'] = $productid;
			$disableproduct = false;
			try
			{
				$this->product->import_dependencies($dependencies);
			}
			catch(vB_Exception_AdminStopMessage $e)
			{
				$message = $this->stop_exception($e);
				$this->show_message($message);
				$disableproduct = true;
			}

			if ($disableproduct)
			{
				$disabled[] = $productid;
				$this->product->disable();
				$this->add_adminmessage(
					'disabled_product_x_y_z',
					array(
						'dismissable' => 1,
						'script'      => '',
						'action'      => '',
						'execurl'     => '',
						'method'      => '',
						'status'      => 'undone',
					),
					true,
					array($product_list["$productid"]['title'], $productid, $message)
				);
				$this->show_message(sprintf($this->phrase['final']['product_x_disabled'], $productid));
			}
		}
		if (!should_install_suite())
		{
			if (!$disabled['vbblog'] AND $product_list['vbblog']['active'])
			{
				$this->product = new vB_Upgrade_Product($this->registry, $this->phrase['vbphrase'], true, $this->caller);
				$this->product->productinfo['productid'] = 'vbblog';
				$this->product->disable();
				$this->show_message(sprintf($this->phrase['final']['product_x_disabled'], 'vbblog'));
			}
			if (!$disabled['vbcms'] AND $product_list['vbcms']['active'])
			{
				$this->product = new vB_Upgrade_Product($this->registry, $this->phrase['vbphrase'], true, $this->caller);
				$this->product->productinfo['productid'] = 'vbcms';
				$this->product->disable();
				$this->show_message(sprintf($this->phrase['final']['product_x_disabled'], 'vbcms'));
			}
		}
	}

	/**
	* Step #6 Template Merge
	* If this step changes from "Step 6", vbulletin-upgrade.js must also be updated in the process_bad_response() function
	*
	* @param	array	contains start info
	*
	*/
	function step_6($data = null)
	{
		if ($data['response'] == 'timeout')
		{
			$this->show_message($this->phrase['final']['step_timed_out']);
			return;
		}

		$this->show_message($this->phrase['final']['merge_template_changes']);
		$startat = intval($data['startat']);
		require_once(DIR . '/includes/class_template_merge.php');

		$products = array("''", "'vbulletin'", "'skimlinks'");

		if (should_install_suite())
		{
			$products = array_merge($products, array("'vbblog'", "'vbcms'"));
	 	}

		$merge_data = new vB_Template_Merge_Data($this->registry);
		$merge_data->start_offset = $startat;
		$merge_data->add_condition($c = "tnewmaster.product IN (" . implode(', ', $products) . ")");

		$merge = new vB_Template_Merge($this->registry);
		$merge->time_limit = 4;
		$output = array();
		$completed = $merge->merge_templates($merge_data, $output);

		if ($output)
		{
			foreach($output AS $message)
			{
				$this->show_message($message);
			}
		}

		if ($completed)
		{
			$this->set_option('upgrade_from', 'version', '', 'string', '' );;
			if ($error = build_all_styles(0, 0, '', true))
			{
				$this->add_error($error, self::PHP_TRIGGER_ERROR, true);
				return false;
			}
		}
		else
		{
			return array('startat' => $startat + $merge->fetch_processed_count() );
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/