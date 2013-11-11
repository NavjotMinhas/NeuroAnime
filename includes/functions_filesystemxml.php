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

require_once(DIR . '/includes/adminfunctions_template.php');
require_once(DIR . '/includes/class_core.php');
require_once(DIR . '/includes/functions.php');

/**
* Outputs the XML templates to the file system from a specified product
*
* @param	mixed	the product string or string in an array, if null we will process all products
*
* @return	bool	true if successfully wrote the products to the file system
*/
function roll_up_templates($product = null, $base_svn_url="")
{
	require_once(DIR . '/includes/class_filesystemxml_template.php');
	
	// instantiate the file system helper
	$helper = new vB_FilesystemXml_Template();

	// if not given a product, we will process all products
	if (empty($product))
	{
		$product = $helper->get_all_products();
	}
	
	// wrap the product string in an array
	else if (!is_array($product))
	{
		$product = array($product);
	}
	
	if ($base_svn_url)
	{
		$helper->set_base_svn_url($base_svn_url);
	}

	// now loop through each product and roll up to master xml
	$successful = true;
	foreach ($product as $p)
	{
		// check for success of each product rollup, but keep processing on failure
		if (!$helper->rollup_product_templates($p))
		{
			$successful = false;
		}
	}
	
	return $successful;
}

/**
* Outputs the XML templates to the file system from a specified product
*
* @param	mixed	the product string or string in an array, if null we will process all products
*
* @return	bool	true if successfully wrote the products to the file system
*/
function xml_templates_to_files($product = null)
{
	require_once(DIR . '/includes/class_filesystemxml_template.php');
	
	// instantiate the file system helper
	$helper = new vB_FilesystemXml_Template();
	
	// if not given a product, we will process all products
	if (empty($product))
	{
		$product = $helper->get_all_products();
	}
	
	// wrap the product string in an array
	else if (!is_array($product))
	{
		$product = array($product);
	}
	
	// now loop through each product and output to filesystem
	$successful = true;
	foreach ($product as $p)
	{
		// check for success of each product, but keep processing on failure
		if (!$helper->write_product_to_files($p))
		{
			$successful = false;
		}
	}
	
	return $successful;
}

/**
* Writes a single template to the file system, using a helper class
*
* @param	string - template
* @param	string - the actual contents of the template
* @param	string - the product to which the template belongs
* @param	string - the version string
* @param	string - the username of last editor
* @param	string - the datestamp of last edit
* @param	string - the previous title of the template if applicable.  If oldtitle=title
* 			no action will be taken.
* 
* @return	bool - true if successful, false otherwise
*/
function autoexport_write_template($name, $text, $product, $version, $username, $datestamp, $oldname="")
{
	require_once(DIR . '/includes/class_filesystemxml_template.php');
	$helper = new vB_FilesystemXml_Template();
	return $helper->write_template_to_file($name, $text, $product, $version, $username, $datestamp, $oldname);
}

/**
* Deletes a single template to the file system, using a helper class
*
*
* @param	string - template
*/
function autoexport_delete_template($name)
{
	require_once(DIR . '/includes/class_filesystemxml_template.php');
	$helper = new vB_FilesystemXml_Template();
	return $helper->delete_template_file($name);
}

function autoexport_write_settings($product)
{
	autoexport_route_update(-1, $product, 'autoexport_write_main_settings');
}

function autoexport_write_help($product)
{
	autoexport_route_update(-1, $product, 'autoexport_write_main_help');
}


function autoexport_write_settings_and_language($languageid, $product)
{
	autoexport_route_update($languageid, $product, 
		array('autoexport_write_main_settings', 'autoexport_write_master_language'));
}


function autoexport_write_style($styleid, $product)
{
	autoexport_route_update($styleid, $product, 'autoexport_write_master_style');
}


function autoexport_write_language($languageid, $product)
{
	autoexport_route_update($languageid, $product, 'autoexport_write_master_language');
}

//doing language and style in one operation avoids writing the product file twice
//if we have a product (since any operation on a product write the entire product file).
function autoexport_write_style_and_language($styleid, $product)
{
	autoexport_route_update($styleid, $product, 
		array('autoexport_write_master_style', 'autoexport_write_master_language'));
}


//Note that there is no seperate faq import/export for vbulletin -- this is 
//handled as part of the the default data and needs to be changed there if
//a master faq item is to be removed or added.  So we either do the product
//export/nothing or product export/language if there are language changes 
//to track.
function autoexport_write_faq($product)
{
	autoexport_route_update(-1, $product, "autoexport_no_op"); 
}

function autoexport_write_faq_and_language($languageid, $product)
{
	autoexport_route_update($languageid, $product, 'autoexport_write_master_language');
}


/*
 *	Internal use for cover functions above
 */

function autoexport_route_update($id, $product, $vb_func)
{
	if ($id == -1)
	{	
		//inits classloader -- required to make vB_Cache work
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();	
//		$timer = vB_Timer::get('timer');
//		$timer->start();

		if (is_array($product))
		{
			//makes sure that if we are passed a list of products
			//that we only process each product a single time.  
			//More is unnecesary and possibly counterproductive.
			//We will actually rely on the fact that we'll only
			//process a product once to avoid having to check if
			//products are the same before adding them to the list.
			$products = array_unique($product);
		}
		else 
		{
			$products = array($product);
		}

		foreach ($products AS $product)
		{
			if ($product == "vbulletin")
			{
				if (!is_array($vb_func))
				{
					$vb_func();
				}
				else 
				{
					//allow multiple callbacks for operations that 
					//touch multiple files.
					foreach($vb_func as $func)
					{
						$func();
					}
				}
			}
			else if (in_array($product, array('vbcms', 'vbblog'))) 
			{
				autoexport_write_product($product);
			}
		}

		
//		var_dump($timer->stop());
	}
}

/*
 * Dummy function for when the vb callback shouldn't actually do anything.
 */
function autoexport_no_op()
{
}

function autoexport_write_main_settings()
{
	require_once(DIR . '/includes/adminfunctions_options.php');
	$xml = get_settings_export_xml('vbulletin');
	autoexport_write_file_with_backup(DIR . '/install/vbulletin-settings.xml', $xml);
}

function autoexport_write_main_help()
{
	require_once(DIR . '/includes/adminfunctions_options.php');
	$xml = get_help_export_xml('vbulletin');
	autoexport_write_file_with_backup(DIR . '/install/vbulletin-adminhelp.xml', $xml);
}

function autoexport_write_master_language()
{
	require_once(DIR . '/includes/adminfunctions_language.php');
	$xml = get_language_export_xml(-1, 'vbulletin', 0, 0);
	autoexport_write_file_with_backup(DIR . '/install/vbulletin-language.xml', $xml);
}

function autoexport_write_master_style()
{
	require_once(DIR . '/includes/adminfunctions_template.php');
	$full_product_info = fetch_product_list(true);
	$xml = get_style_export_xml(-1, 'vbulletin', $full_product_info['vbulletin']['version'], '', 2);
	autoexport_write_file_with_backup(DIR . '/install/vbulletin-style.xml', $xml);

	//we don't want the templates in the xml on the filesystem
	require_once(DIR . '/includes/class_filesystemxml_template.php');
	$helper = new vB_FilesystemXml_Template();
	$helper->remove_product_templates('vbulletin');
}

function autoexport_write_product($product)
{
	require_once(DIR . '/includes/adminfunctions_plugin.php');
	$xml = get_product_export_xml($product);
	autoexport_write_file_with_backup(DIR . "/includes/xml/product-$product.xml", $xml);

	//we don't want the templates in the xml on the filesystem
	require_once(DIR . '/includes/class_filesystemxml_template.php');
	$helper = new vB_FilesystemXml_Template();
	$helper->remove_product_templates($product);
}


function autoexport_write_file_with_backup($file, $xml)
{
	if (file_exists($file))
	{
		$directory = dirname($file);
		$backup = "$directory/autoexport_backups";
		if (!file_exists($backup))
		{
			mkdir($backup);
		}

		if(!is_dir($backup))
		{
			$backup = $directory;
		}

		$timestamp = date('Y-m-d-H-i-s-');
		$backupfilename = $backup . '/' . $timestamp . basename($file);
		if (file_exists($backupfilename))
		{
			$backupfilename = $backup . '/' . $timestamp . '-' . basename($file);
		}
		
		if(!file_exists($backupfilename))
		{
			rename($file, $backupfilename);
		}
	}
	
	file_put_contents($file, $xml);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 37624 $
|| ####################################################################
\*======================================================================*/
?>
