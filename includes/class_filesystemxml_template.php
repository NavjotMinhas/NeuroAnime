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

require_once(DIR . '/includes/class_xml.php');

/**
* Helper class to facilitate storing templates on the file system
*
* @package	vBulletin
* @version	$Revision: 40911 $
* @date		$Date: 2010-12-02 14:38:25 -0800 (Thu, 02 Dec 2010) $
*/
class vB_FilesystemXml_Template
{

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* holds error string
	*
	* @var	array
	*/
	protected $errors = array();

	/**
	 * If we are not operating on a working directory we need an svn directory
	 * do the log lookups from.
	 */
	protected $base_svn_url = "";

	/**
	* Array that template information by product
	*
	* @var	array
	*/
	protected $productinfo = array(
		'vbulletin' => array(
			'relpath' => '/install/vbulletin-style.xml',
			'xmlgroup' => 'templategroup',
		),
		'vbblog' => array(
			'relpath' => '/includes/xml/product-vbblog.xml',
			'xmlgroup' => 'templates',
		),
		'vbcms' => array(
			'relpath' => '/includes/xml/product-vbcms.xml',
			'xmlgroup' => 'templates',
		),
	);

	/**
	* Cached list of templates read from the file system
	*
	* @var	array
	*/
	protected $templatelist = null;


	/**
	* Constructor - caches registry object
	*/
	public function __construct()
	{
		global $vbulletin;
		$this->registry = $vbulletin;
	}

	/**
	* Gets the template directory
	*
	* @return	string - path to the template directory
	*/
	public function get_templatedirectory()
	{
		return realpath(DIR . DIRECTORY_SEPARATOR  . 'templates');
	}


	/**
	 * Gets the source for the svn template lookup.  If an svn url is given, use that
	 * Otherwise assume that the templates are in an svn working directory.
	 */
	protected function get_svn_template_source()
	{
		if($this->base_svn_url)
		{
			return $this->base_svn_url . DIRECTORY_SEPARATOR  . 'templates';
		}
		else
		{
			return $this->get_templatedirectory();
		}
	}

	/**
	* Returns the path to a products xml file
	*
	* @param	string - name of the product
	*
	* @return	mixed - path to the product's xml file, false if not found
	*/
	protected function get_xmlpath($product)
	{
		if (isset($this->productinfo[$product]) AND isset($this->productinfo[$product]['relpath']))
		{
			return DIR . $this->productinfo[$product]['relpath'];
		}
		else
		{
			$this->errors[] = "Could not find the path to $product's xml file";
			return false;
		}
	}

	/**
	* Outputs an array of all products this helper class is setup up to process
	*
	* @return	array - strings of all product names with xml files
	*/
	public function get_all_products()
	{
		return array_keys($this->productinfo);
	}

	/**
	 *
	 */
	public function set_base_svn_url($url)
	{
		$this->base_svn_url = $url;
	}

	/**
	* wraps empty xml element in an array (copied from adminfunctions_template
	*/
	protected function get_xml_list($xmlarray)
	{
		if (is_array($xmlarray) AND array_key_exists(0, $xmlarray))
		{
			return $xmlarray;
		}
		else
		{
			return array($xmlarray);
		}
	}

// ################################################################################
// ##                    Master XML to Template Files
// ################################################################################

	/**
	* Takes a the file name of an xml file, and parses it into an xml object
	*
	* @param	string - file name (including path) of the xml file
	*
	* @return	array - parsed xml object of the file
	*/
	protected function parse_xml_from_file($filename)
	{
		$xmlobj = new vB_XML_Parser(false, $filename);

		if ($xmlobj->error_no == 1 OR $xmlobj->error_no == 2)
		{
			$this->errors[] = "Please ensure that the file $filename exists";
			return false;
		}

		if (!$parsed_xml = $xmlobj->parse())
		{
			$this->errors[] = 'xml error '.$xmlobj->error_string().', on line ' . $xmlobj->error_line();
			return false;
		}

		return $parsed_xml;
	}

	/**
	* Returns the parsed xml data that is pertinent to product
	*
	* @param	string - the product name
	*
	* @return	array - parsed xml pertinent to the product
	*/
	protected function get_template_xml($product)
	{
		// get the path name for the products's xml file
		if (!$productpath = $this->get_xmlpath($product))
		{
			return false;
		}

		// attempt to parse the xml
		if (!$parsed_xml = $this->parse_xml_from_file($productpath))
		{
			return false;
		}

		// now, grab only the appropriate data from the parsed xml array
		// making sure we can find the product template data in the parsed xml
		if (isset($this->productinfo[$product]['xmlgroup']) AND isset($parsed_xml[$this->productinfo[$product]['xmlgroup']]))
		{
			$xmlarray = $parsed_xml[$this->productinfo[$product]['xmlgroup']];
		}
		else
		{
			$this->errors[] = "Could not find $product template data in $productpath";
			return false;
		}

		// wrap single xml element in an array if neccessary
		return $this->get_xml_list($xmlarray);
	}

	/**
	* Writes a single template to the file system
	*
	* @param	string - template
	* @param	string - the actual contents of the template
	* @param	string - the product to which the template belongs
	* @param	string - the version string
	* @param	string - the username of last editor
	* @param	string - the datestamp of last edit
	* @param	string - the old title if available
	*
	* @return	bool - true if successful, false otherwise
	*/
	public function write_template_to_file($name, $text, $product, $version, $username, $datestamp, $oldname="")
	{
		try
		{
			$template_path = $this->get_templatedirectory() . DIRECTORY_SEPARATOR . "$name.xml";

			if ($oldname and $oldname != $name)
			{
				$old_template_path = $this->get_templatedirectory() . DIRECTORY_SEPARATOR . "$oldname.xml";
				if (file_exists($old_template_path))
				{
					//$message = "Auto export template name changed in db, renaming file to match.";
					if (file_exists($template_path))
					{
						unlink($template_path);
					}

					$cmd = "svn rename $old_template_path $template_path";
					shell_exec($cmd);
				}
			}
			//we only want to set the time/date the first time a template is saved.
			//additional updates will be drawn from the svn repository.
			//the goal is to avoid generating an svn conflict every time a template is
			//edited on two branches, while still preserving all of the legacy data
			//on the templates.

			$new_file = true;
			if (file_exists($template_path))
			{
				$parsed = $this->parse_xml_from_file($template_path);

				if(!empty($parsed['username']))
				{
					$username = $parsed['username'];
				}

				if (!empty($parsed['username']))
				{
					$datestamp = $parsed['date'];
				}
			}
			else
			{
				$new_file= true;
			}

			$attributes = array (
				'product' => $product,
				'version' => $version,
				'username' => $username,
				'date' => $datestamp
			);

			$xml = new vB_XML_Builder(new stdClass, null, 'ISO-8859-1');
			$xml->add_tag('template', $text, $attributes, true);

			file_put_contents($template_path, $xml->fetch_xml());

			if ($new_file)
			{
				$cmd = "svn add $template_path";
				shell_exec($cmd);
			}
		}

		// if an error occured we dont care about the type, just make sure we track it
		catch (Exception $e)
		{
			$this->errors[] = "Could not write template $name to the file system";
			return false;
		}

		return true;
	}

	public function delete_template_file($name)
	{
		$template_path = $this->get_templatedirectory() . DIRECTORY_SEPARATOR . "$name.xml";
		if (file_exists($template_path))
		{
			$cmd = "svn --force delete $template_path";
			shell_exec($cmd);
		}
	}

	/**
	* Writes an entire product's templates to the filesystem from master xml
	*
	* @param	string - product name
	*
	* @return	bool - true if successful, false otherwise
	*/
	public function write_product_to_files($product)
	{
		// get the xml array that applies to the product
		if (!$template_xml = $this->get_template_xml($product))
		{
			return false;
		}

		// loop through each template group in the product
		foreach ($template_xml AS $templategroup)
		{
			$successful = true;

			// loop through each template in the template group
			// wrap template text in xml, and write to file system
			$tg_array = $this->get_xml_list($templategroup['template']);
			foreach($tg_array AS $template)
			{
				if ($template['templatetype'] != 'template')
				{
					//we don't want no regular templates here, at least not right now.
					continue;
				}

				// attempt to output the template to the file system
				// if we failed, keep writing templates, but track that we failed
				if (!$this->write_template_to_file($template['name'], $template['value'], $product, $template['version'], $template['username'], $template['date']))
				{
					$successful = false;
				}
			}
		}

		return $successful;
	}


// ################################################################################
// ##                    Roll-up Functions
// ################################################################################

	/**
	* Rolls up all the template files for a product
	*
	* @param	string - the product id
	*
	* @return	bool - true if successful
	*/
	public function rollup_product_templates($product)
	{
		// get the path name for the products's xml file
		if (!$templates = $this->get_template_lists($product))
		{
			$this->errors[] = "Could not find any templates for product: $product";
			return false;
		}

		// prepare product xml using template array
		if ($product == 'vbulletin')
		{
			$xml = $this->get_vbulletin_template_xml($templates);
		}
		else
		{
			$xml = $this->get_product_template_xml($templates);
		}
		if (empty($xml))
		{
			$this->errors[] = "Could not prepare the XML for product: $product";
			return false;
		}

		// use a helper class to replace the changes to the style as
		// we write the master xml file to the filesystem
		require_once(DIR . '/includes/class_filesystemxml_replace.php');
		if ($product == 'vbulletin')
		{
			$r = new vB_FilesystemXml_Replace_Style_Template($this->get_xmlpath($product), $xml);
		}
		else
		{
			$r = new vB_FilesystemXml_Replace_Product_Template($this->get_xmlpath($product), $xml);
		}
		$success = $r->replace();
		unset($r);

		// if success is not set replace was successful, hence the strict equality check
		return $success !== false;
	}


	/**
	* Rolls up all the template files for a product
	*
	* @param	string - the product id
	*
	* @return	bool - true if successful
	*/
	public function remove_product_templates($product)
	{
		// use a helper class to replace the changes to the style as
		// we write the master xml file to the filesystem
		require_once(DIR . '/includes/class_filesystemxml_replace.php');

		// prepare product xml using template array
		if ($product == 'vbulletin')
		{
			$xml = "\n<templategroup name=\"dummy\"></templategroup>";
		}
		else
		{
			$xml = "\n<templates></templates>";
		}

		if ($product == 'vbulletin')
		{
			$r = new vB_FilesystemXml_Replace_Style_Template($this->get_xmlpath($product), $xml);
		}
		else
		{
			$r = new vB_FilesystemXml_Replace_Product_Template($this->get_xmlpath($product), $xml);
		}

		return $r->replace();
	}


	/**
	* Gets all the templates from the file system and puts it into an array
	*
	* @param	string - (Optional) the product id, returns all products by default
	*
	* @return	array - information about all the templates stored in the file system
	*/
	protected function get_template_lists($product = null)
	{
		// check to see if we already have read and cached templates from filesystem
		if (!isset($this->templatelist))
		{

			$this->templatelist = array();

			$template_names = $this->get_template_list();
			$template_dir = $this->get_templatedirectory();

			foreach($template_names as $name)
			{
				$path_info = pathinfo($name);
				if ($parsed = $this->parse_xml_from_file($template_dir . '/' . $name))
				{
					$parsed['lastupdated'] = $parsed['date'];
					$this->templatelist[$parsed['product']][$path_info['filename']] = $parsed;
				}
			}

			//The original approach to handling the xml data was to grab the log for each file.  This
			//has the compelling advantage of allowing us to use the limit feature to only pull the
			//recent commits for each file (with each file the commit we are looking for is very likely
			//to be in the last 10 -- there aren't going to be that many non change commits in a row).
			//This means that the process won't slow down as the number of template commits grow.
			//
			//Unfortunately this approach is prohibitively slow.  Each request takes approximately 1 second
			//with intermittant pauses of 5-20 seconds accessing the svn server.  Assuming one second per
			//template, the process will take about 15 minutes -- with the pauses, 20-25 minutes (I
			//never let the script run to completion so these are estimates based on observed rates).
			//
			//The new approach is to download the revisions for the entire template directory at once
			//and parse out the data we need from the big pile of xml.  At the moment, actually, this is
			//very very fast.  The main reason for that is that there are fewer than 10 total commits to the
			//template directory (checking the templates in was one big commit and only a few of them have
			//been edited since).  This will obviously change as this goes into production and the
			//templates get edits saved to the filesystem.
			//
			//As a hedge I did a download of the log for the entire branch.  This took approximately
			//3 minutes (and 10Mb of data transfered).  This is still faster than the 20 minutes of the
			//initial approach (while it only covers the svn costs, not time spent processing the xml the
			//costs dominate).  This represents an extreme (some 10 years of development) and is
			//sufficient for builds though not developer import.
			//
			//It is not actually necesary to download all of the commits, we only need the most recent
			//"change" commit for each template -- which means we need to go back to the oldest of
			//that set of commits.  The approach that would work would be to pull large batches of commits
			//(say 5000) and check to see if all templates are accounted for in that set and if not, pull
			//next 5000.  This hasn't been done because
			//* It is extra work not required now.
			//* It only saves work at the point when every single template has been changed, otherwise
			//  we need to pull all of the commits in any event.  It is likely going to be a year or
			//  more before that happens and even longer before the number of excluded commits will
			//  show a substantial time savings.
			//* Properly testing it would require dummying up the thousands of commits required to see
			//  a difference from the present algorithm.
			//Until implementing it will do some good, its wasted effort.
			//
			//Another possible solution would be to update the baseline dates/users in the template files
			//to a particular revision number at which point we'd only need to pull as far back as that revision.
			//a script would be need to be written to that and we'd need to coordinate the branching
			//carefully but its probably a better solution then the batch approach.
			$svn_data = $this->get_svn_data($template_names);
			if ($svn_data)
			{
				foreach($this->templatelist AS $product_key => $list)
				{
					foreach($list AS $name => $template)
					{
						if (isset($svn_data["$name.xml"]))
						{
							$this->templatelist[$product_key][$name]['lastupdated'] = $svn_data["$name.xml"]['lastupdated'];
							$this->templatelist[$product_key][$name]['username'] = $svn_data["$name.xml"]['username'];
						}
					}
				}
			}
		}




		// check if we only want to return a product specific template array
		// otherwise, return all product template array
		return !empty($product) ? $this->templatelist[$product] : $this->templatelist;
	}

	protected function get_vbulletin_template_xml($templates)
	{
		//this is the name of the groups array.  Why?  I don't know.
		global $only, $vbphrase;

		$groups = array();

		$ugcount = $ugtemplates = 0;
		foreach ($templates as $name => $template)
		{
			$isgrouped = false;
			foreach(array_keys($only) AS $group)
			{
				if (strpos(strtolower(" $name"), $group) == 1)
				{
					$groups["$group"][$name] = $template;
					$isgrouped = true;
				}
			}

			if (!$isgrouped)
			{
				if ($ugtemplates % 10 == 0)
				{
					$ugcount++;
				}
				$ugtemplates++;
				//sort ungrouped templates last.
				$ugcount_key = 'zzz' . str_pad($ugcount, 5, '0', STR_PAD_LEFT);
				$groups[$ugcount_key][$name] = $template;
				$only[$ugcount_key] = construct_phrase($vbphrase['ungrouped_templates_x'], $ugcount);
			}
		}

		if (!empty($templates))
		{
			ksort($groups);
		}
		unset ($templates);

		$xml = new vB_XML_Builder(new stdClass(), null, 'ISO-8859-1');
		$xml->add_group('temp');
		foreach($groups AS $group => $grouptemplates)
		{
			uksort($grouptemplates, "strnatcasecmp");
			$xml->add_group('templategroup', array('name' => (isset($only["$group"]) ? $only["$group"] : $group)));
			foreach($grouptemplates AS $name => $template)
			{
				$xml->add_tag('template', $template['value'],
					array(
						'name' => htmlspecialchars($name),
						'templatetype' => 'template',
						'date' => $template['lastupdated'],
						'username' => $template['username'],
						'version' => htmlspecialchars_uni($template['version'])),
					true
				);
			}
			$xml->close_group();
		}
		$xml->close_group();
		$text = $xml->fetch_xml();
		unset($xml);
		return substr($text, strpos($text, '<temp>') + strlen("<temp>"), -1 * strlen('</temp>\n'));

	}

	protected function get_product_template_xml($templates)
	{
		uksort($templates, "strnatcasecmp");

		$xml = new vB_XML_Builder(new stdClass(), null, 'ISO-8859-1');
		$xml->add_group('temp');
		$xml->add_group('templates');
		foreach($templates AS $name => $template)
		{
			$xml->add_tag('template', $template['value'],
				array(
					'name' => htmlspecialchars($name),
					'templatetype' => 'template',
					'date' => $template['lastupdated'],
					'username' => $template['username'],
					'version' => htmlspecialchars_uni($template['version'])),
				true
			);
		}
		$xml->close_group();
		$xml->close_group();
		$text = $xml->fetch_xml();
		unset($xml);
		return substr($text, strpos($text, '<temp>') + strlen("<temp>"), -1 * strlen('</temp>\n'));
	}

	public function get_template_list()
	{
		$template_dir = $this->get_templatedirectory();
		foreach (new DirectoryIterator($template_dir) AS $fileinfo)
		{
			if (!$fileinfo->isFile())
			{
				continue;
			}
	
			$path_info = pathinfo($fileinfo->getFilename());
			if ($path_info['extension'] != 'xml')
			{
				continue;
			}

			$template_names[] = $fileinfo->getFilename();
		}
		return $template_names;
	}

	public function get_svn_data($template_filenames, $minsvnversion=1)
	{
		$template_dir = $this->get_svn_template_source();

		$cmd = 'svn log -rHEAD:' . $minsvnversion . ' --xml -v ' . $template_dir;
		$text = shell_exec($cmd);
		$xmlobj = new vB_XML_Parser($text);

		if (!$parsed_xml = $xmlobj->parse())
		{
			$this->errors[] = sprintf ("xml error '%s', on line '%d'",
				$xmlobj->error_string(), $xmlobj->error_line());
			return false;
		}
		$logentries = $this->get_xml_list($parsed_xml['logentry']);

		$template_dir_basename = basename($template_dir);

		$data = array();
		foreach ($logentries AS $logentry)
		{
			$paths = $this->get_xml_list($logentry['paths']['path']);
			foreach($paths AS $path)
			{
				$is_mod = $path['action'] == 'M';
				$is_rename = (($path['action'] == 'R' OR $path['action'] == 'A') AND $path['copyfrom-path']);

				if($is_mod OR $is_rename)
				{
					//make sure that the paths are the same one directory level up.  This should cut down
					//dramatically on the potential for false matches since both paths would have to end in
					//templates/templatename.ext.  This isn't perfect, but reduces the change of conflict
					//to a level where it realistically won't happen.  We can't easily match the paths because
					//we don't really know how far up we should be checking.  If there is a conflict the
					//only consequence is that we advance the change date in the xml unnecesarily.
					if (basename(dirname($path['value'])) == $template_dir_basename)
					{

						$path_file = basename($path['value']);
						if(!array_key_exists($path_file, $data) AND in_array($path_file, $template_filenames))
						{
							$data[$path_file] = array (
								'lastupdated' => strtotime($logentry['date']),
								'username' => $logentry['author']
							);
						}

					}
				}
			}
		}
		return $data;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 40911 $
|| ####################################################################
\*======================================================================*/
?>
