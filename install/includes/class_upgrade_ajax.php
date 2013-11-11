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

class vB_Upgrade_Ajax extends vB_Upgrade_Abstract
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* The object that will be used to execute queries
	*
	* @var	vB_Database
	*/
	protected $db = null;

	/**
	* vB_Upgrade Object
	*
	* @var vB_Upgrade
	*/
	protected $upgrade = null;

	/**
	* Identifier for this library
	*
	* @var	string
	*/
	protected $identifier = 'ajax';

	/**
	* Limit Step Queries
	*
	* @var	boolean
	*/
	protected $limitqueries = true;

	/**
	* Customer Number
	*
	* @var	string
	*/
	private $custnumber = '';

	/**
	* Options for HTML display
	*
	* @var	array
	*/
	private $htmloptions = array();

	/**
	* Constructor.
	*
	* @param	vB_Registry	Reference to registry object
	* @var	string	Setup type - 'install' or 'upgrade'
	*/
	public function __construct(&$registry, $phrases, $setuptype = 'upgrade')
	{
		parent::__construct($registry, $phrases, $setuptype);
	}

	/**
	* Stuff to setup specific to Ajax upgrading - executes after upgrade has been established
	*
	*/
	protected function init()
	{
		parent::init();
		$this->custnumber = (strlen('0a77ea114b8ec6fe65ff2125ecc0fdd6') == 32) ? '0a77ea114b8ec6fe65ff2125ecc0fdd6' : md5(strtoupper('0a77ea114b8ec6fe65ff2125ecc0fdd6'));

		$this->registry->input->clean_array_gpc('p', array(
			'ajax'   => TYPE_BOOL,
			'jsfail' => TYPE_BOOL,
		));

		if ($this->registry->GPC['jsfail'])
		{
			$this->startup_errors[] = $this->phrase['core']['javascript_disabled'];
		}

		$this->registry->input->clean_array_gpc('c', array(
			'bbcustomerid' => TYPE_STR,
		));

		$this->htmloptions['finalversion'] = end($this->versions);
		$this->htmloptions['setuptype'] = sprintf($this->phrase['core']['vb_' . $this->setuptype . '_system'], $this->htmloptions['finalversion']);
		$this->htmloptions['setuptypetitle'] = sprintf($this->phrase['core']['vb_' . $this->setuptype . '_system_title'], $this->htmloptions['finalversion']);
		$this->htmloptions['enter_system'] = $this->phrase['authenticate']['enter_' . $this->setuptype . '_system'];

		if ($this->registry->GPC['ajax'])
		{
			$this->registry->input->clean_array_gpc('p', array(
				'step'       => TYPE_UINT,
				'startat'    => TYPE_UINT,
				'version'    => TYPE_NOHTML,
				'response'   => TYPE_NOHTML,
				'checktable' => TYPE_BOOL,
				'status'     => TYPE_BOOL,
				'firstrun'   => TYPE_BOOL,
				'only'       => TYPE_BOOL,
				'htmlsubmit' => TYPE_BOOL,
				'htmldata'   => TYPE_ARRAY,
			));

			$this->registry->GPC['response'] = convert_urlencoded_unicode($this->registry->GPC['response']);
			$this->registry->GPC['htmldata'] = convert_urlencoded_unicode($this->registry->GPC['htmldata']);

			if ($this->registry->GPC['bbcustomerid'] != $this->custnumber)
			{
				$xml = new vB_AJAX_XML_Builder($this->registry, 'text/xml', vB_Template_Runtime::fetchStyleVar('charset'));
					$xml->add_tag('error', $this->phrase['authenticate']['cust_num_incorrect']);
				$xml->print_xml();
			}

			if ($this->registry->GPC['status'])
			{
				$this->fetch_query_status();
			}

			$this->scriptinfo = array(
				'version' => $this->fetch_short_version($this->registry->GPC['version']),
				'startat' => $this->registry->GPC['startat'],
				'step'    => $this->registry->GPC['step'],
				'only'    => $this->registry->GPC['only'],
			);
			$script = $this->load_script($this->scriptinfo['version']);

			$this->process_step($this->registry->GPC['version'], $this->registry->GPC['step'], $this->registry->GPC['startat'], $this->registry->GPC['checktable'], $this->registry->GPC_exists['response'] ? $this->registry->GPC['response'] : null, $this->registry->GPC['firstrun'], $this->registry->GPC['only'], $this->registry->GPC['htmlsubmit'], $this->registry->GPC['htmldata']);
		}
		else
		{
			$this->registry->input->clean_array_gpc('r', array(
				'version' => TYPE_NOHTML,
				'startat' => TYPE_UINT,
				'step'    => TYPE_UINT,
				'only'    => TYPE_BOOL,
			));

			$proceed = false;
			if ($this->registry->GPC['bbcustomerid'] != $this->custnumber)
			{
				if ($this->login())
				{
					$proceed = true;
				}
			}
			else
			{
				$proceed = true;
			}

			if ($proceed)
			{
				if ($this->registry->GPC['version'] AND $this->versions[$this->registry->GPC['version']])
				{
					$this->scriptinfo = array(
						'version' => $this->registry->GPC['version'],
						'startat' => $this->registry->GPC['startat'],
						'step'    => $this->registry->GPC['step'],
					);
				}
				$this->begin_upgrade($this->scriptinfo['version'], $this->registry->GPC['only']);
			}

			$this->print_html();

			// Begin processing
			if ($this->htmloptions['processlog'])
			{

			}
		}
	}

	/**
	* Is this a big board?
	*
	* @return	mixed
	*/
	protected function is_big_forum()
	{
		$postcount = $this->registry->db->query_first("
			SELECT SUM(replycount) AS postcount
			FROM " . TABLE_PREFIX . "forum
		");

		return $postcount['postcount'] > 1200000 ? vb_number_format(
			$postcount['postcount'],
			0,
			false,
			vB_Template_Runtime::fetchStyleVar('decimalsep'),
			vB_Template_Runtime::fetchStyleVar('thousandsep')
		) : false;
	}

	protected function fetch_additional_scripts($startscript)
	{
		$result = array();
		$start = false;
		foreach ($this->endscripts AS $product)
		{
			if ($start)
			{
				$result[] = $product;
			}
			if ($product == $startscript)
			{
				$start = true;
			}
			if ($product == 'final')
			{
				return $result;
			}
		}

		return $result;
	}

	/**
	* Begin Upgrade
	*
	* @var	string	script version
	* @var	bool		Only execute one script, must be specified in version=
	*/
	protected function begin_upgrade($version, $only = false)
	{
		if ($this->registry->GPC['ajax'])
		{
			return;
		}
		$script = $this->load_script($version);

		$this->registry->input->clean_array_gpc('p', array(
			'version' => TYPE_NOHTML,
		));

		if (!$this->verify_version($version, $script) AND !in_array($this->registry->GPC['version'], array($version, $this->registry->options['templateversion'])))
		{
			$this->htmloptions['mismatch'] = true;
			$this->htmloptions['version'] = $version;
		}
		else
		{
			$this->htmloptions['processlog'] = true;
			if ($this->registry->GPC['version'])
			{
				//$this->scriptinfo = $this->get_upgrade_start($this->fetch_short_version($this->registry->GPC['version']));
				$this->scriptinfo = array(
					'version' => $this->fetch_short_version($this->registry->GPC['version']),
					'only'    => $only,
				);
				$script = $this->load_script($this->scriptinfo['version']);
			}

			$process = false;
			$totalsteps = ($script->stepcount ? $script->stepcount : 1);
			if ($this->scriptinfo['step'])
			{
				$totalsteps = $totalsteps - $this->scriptinfo['step'] + 1;
			}

			if (!$only)
			{
				foreach ($this->versions AS $version => $longversion)
				{
					$version = strval($version);
					if ($this->scriptinfo['version'] === $version)
					{
						$process = true;
						continue;
					}
					if ($process AND strpos($version, '*') === false)
					{
						$tempscript = $this->load_script($version);
						$totalsteps += $tempscript->stepcount ? $tempscript->stepcount : 1;
						unset($tempscript);
					}
				}
			}

			$addscripts = array();

			if (!in_array($script->SHORT_VERSION, $this->endscripts))
			{
				$addscripts = array('skimlinks', 'final');
				if ($this->install_suite())
				{
					foreach($this->products AS $product)
					{
						$addscripts[] = $product;
					}
				}
				if ($this->setuptype == 'install')
				{
					$this->htmloptions['upgrademessage'] = sprintf($this->phrase['core']['press_button_to_begin_install'], end($this->versions));
				}
				else
				{
					$this->htmloptions['upgrademessage'] = sprintf($this->phrase['core']['press_button_to_begin_upgrade'], $this->registry->options['templateversion'], end($this->versions));
					$this->htmloptions['suggestconsole'] = $this->is_big_forum();
				}
			}
			else
			{
				$addscripts = $this->fetch_additional_scripts($script->SHORT_VERSION);
				$this->htmloptions['upgrademessage'] = $this->phrase['core']['press_button_to_begin_' . $script->SHORT_VERSION . '_upgrade'];
			}

			if (!$only)
			{
				foreach ($addscripts AS $scriptname)
				{
					$tempscript = $this->load_script($scriptname);
					$totalsteps += $tempscript->stepcount;
					unset($tempscript);
				}
			}

			$this->htmloptions['progress'] = $this->phrase['core'][$this->setuptype . '_progress'];
			$this->htmloptions['upgrading_to_x'] = sprintf($this->phrase['core'][$this->setuptype . 'ing_to_x'], $this->htmloptions['finalversion']);
			$this->htmloptions['begin_setup'] = $this->phrase['core']['begin_' . $this->setuptype];
			$this->htmloptions['totalsteps'] = $totalsteps;
			$this->htmloptions['version'] = $this->scriptinfo['version'];
			$this->htmloptions['longversion'] = $script->LONG_VERSION;
			$this->htmloptions['step'] = $this->scriptinfo['step'] ? $this->scriptinfo['step'] : 1;
			$this->htmloptions['startat'] = intval($this->scriptinfo['startat']);
			$this->htmloptions['only'] = $this->scriptinfo['only'];
			$this->htmloptions['status'] =
				$script->stepcount
					? $this->fetch_status($script->LONG_VERSION, $this->htmloptions['step'], $script->stepcount)
					: $this->fetch_status($script->LONG_VERSION);

			if ($this->startup_errors)
			{
				$this->htmloptions['startup_errors'] = '';
				foreach ($this->startup_errors AS $error)
				{
					$this->htmloptions['startup_errors'] .= "<li>$error</li>";
				}
				$this->htmloptions['login'] = false;
				$this->htmloptions['mismatch'] = false;
				$this->htmloptions['processlog'] = false;
			}
		}
	}

	/**
	* Process a step
	*
	* @var	string	Version
	* @var	int			Step
	* @var	int			Startat
	* @var 	bool		Check table status
	* @var	string	Response from a prompt
	* @var	bool		Received an HTML form response
	* @var	array		Values returned from an HTML form
	*/
	private function process_step($version, $step, $startat, $checktable = true, $response = null, $firstrun = false, $only = false, $htmlsubmit = false, $htmldata = array())
	{
		$script = $this->load_script($version);
		$startstep = $step ? $step : 1;
		$endstep = $script->stepcount;

		$xml = new vB_AJAX_XML_Builder($this->registry, 'text/xml', vB_Template_Runtime::fetchStyleVar('charset'));

		$xml->print_xml_header();
		$xml->add_group('upgrade');

		$only = ($this->scriptinfo['only'] OR $only);

		if ($endstep)
		{
			$result = $this->execute_step($startstep, $startat, $script, $xml, $checktable, $response, $htmlsubmit, $htmldata);
			if ($result AND $result['returnvalue']['startat'])
			{
				$script->log_upgrade_step($startstep, $result['returnvalue']['startat'], $only);
				$nextstep = $startstep;
			}
			else if ($result AND ($result['returnvalue']['prompt'] OR $result['returnvalue']['html']))
			{
				$nextstep = $startstep;
			}
			else if ($result)
			{
				$script->log_upgrade_step($startstep, 0, $only);
				if ($startstep == $endstep)
				{
					$version = $script->SHORT_VERSION;
					$nextstep = 0;
				}
				else
				{
					$nextstep = $startstep + 1;
				}
			}
			else
			{
				$nextstep = $startstep;
			}
		}
		else
		{
			$script->log_upgrade_step(0, 0, $only);
			$version = $script->SHORT_VERSION;
			$nextstep = 0;
		}

		if ($nextstep == 0)
		{
			if ($this->scriptinfo['version'] == 'final' OR $only)
			{
				$this->scriptinfo['version'] = 'done';
				$status = $this->phrase['core']['status_done'];
			}
			else
			{
				$this->scriptinfo = $this->get_upgrade_start($version);
				$nextscript = $this->load_script($this->scriptinfo['version']);
				if ($nextscript->stepcount)
				{
					$status = $this->fetch_status($nextscript->LONG_VERSION, 1, $nextscript->stepcount);
				}
				else
				{
					$status = $this->fetch_status($nextscript->LONG_VERSION);
				}
			}
			$nextstep = 1;
			$this->process_script_end();
		}
		else
		{
			$status = $this->fetch_status($script->LONG_VERSION, $nextstep, $endstep);
		}

		if ($result['returnvalue']['startat'])
		{
			$xml->add_tag('startat', $result['returnvalue']['startat']);
		}
		else if ($result['returnvalue']['prompt'])
		{
			$xml->add_tag('prompt', $result['returnvalue']['prompt']);
			if ($result['returnvalue']['confirm'])
			{
				$xml->add_tag('confirm', $result['returnvalue']['confirm']);
			}
			if ($result['returnvalue']['hidecancel'])
			{
				$xml->add_tag('hidecancel', true);
			}
			if ($result['returnvalue']['cancel'])
			{
				$xml->add_tag('cancel', $result['returnvalue']['cancel']);
			}
			if ($result['returnvalue']['ok'])
			{
				$xml->add_tag('ok', $result['returnvalue']['ok']);
			}
			if ($result['returnvalue']['title'])
			{
				$xml->add_tag('title', $result['returnvalue']['title']);
			}
			if ($result['returnvalue']['reset'])
			{
				$xml->add_tag('reset', true);
			}
		}
		else if ($result['returnvalue']['html'])
		{
			$xml->add_tag('html', $result['returnvalue']['html']);
			if ($result['returnvalue']['width'])
			{
				$xml->add_tag('width', $result['returnvalue']['width']);
			}
			if ($result['returnvalue']['height'])
			{
				$xml->add_tag('height', $result['returnvalue']['height']);
			}
			if ($result['returnvalue']['cancel'])
			{
				$xml->add_tag('cancel', $result['returnvalue']['cancel']);
			}
			if ($result['returnvalue']['ok'])
			{
				$xml->add_tag('ok', $result['returnvalue']['ok']);
			}
			if ($result['returnvalue']['hidecancel'])
			{
				$xml->add_tag('hidecancel', true);
			}
			if ($result['returnvalue']['title'])
			{
				$xml->add_tag('title', $result['returnvalue']['title']);
			}
			if ($result['returnvalue']['reset'])
			{
				$xml->add_tag('reset', true);
			}
		}

		$xml->add_tag('status', $status);
		$xml->add_tag('longversion', $script->LONG_VERSION);
		$xml->add_tag('version', $this->scriptinfo['version']);
		$xml->add_tag('nextstep', $nextstep);
		if ($this->scriptinfo['version'] == 'done')
		{
			$xml->add_tag('upgradecomplete', $this->phrase['final'][$this->setuptype . '_complete']);
			$xml->add_tag('oktext', $this->phrase['final']['goto_admincp']);
			$xml->add_tag('canceltext', $this->phrase['final']['back_to_' . $this->setuptype]);
		}

		if ($firstrun OR ($result['returnvalue']['startat'] == 0 AND $startstep == 1))
		{
			// Might be able to replace the array_merge with $this->endscripts --- Check
			if (in_array($this->scriptinfo['version'], array_merge(array('final', 'skimlinks', 'install', $this->products))) AND $script->LONG_VERSION == $this->scriptinfo['version'])
			{
				$xml->add_tag('upgradenotice', $this->phrase['core']['processing_' . $this->scriptinfo['version']]);
			}
			else
			{
				// <!-- "upgradeing" is a purposeful typo -->
				$xml->add_tag('upgradenotice', sprintf($this->phrase['core']['upgradeing_to_x'], $script->LONG_VERSION));
			}
		}

		$xml->close_group('upgrade');
		$xml->print_xml_end();
	}

	/**
	* Is this a vB version step, or a cms/blog/install step
	*
	* @var string	Script Tag
	* @var int		Beginning step
	* @var int		End Step
	*
	* @return string
	*/
	private function fetch_status($script, $start = null, $end = null)
	{
		if (!$start)
		{
			return sprintf($this->phrase['core']['status_x'], $script);
		}
		else
		{
			if (preg_match('#^\d#', $script))
			{
				return sprintf($this->phrase['core']['status_x_y_z'], $script, $start, $end);
			}
			else
			{
				return sprintf($this->phrase['core']["status_{$script}_x_y"], $start, $end);
			}
		}
	}

	/**
	* Execute a step
	*
	* @var	int			Step
	* @var	startat	Startat
	* @var	Object	vB_Upgrade Object
	* @var	Object	vB_AJAX_XML_Builder
	* @var	bool		Check table status
	* @var	string	Response from a prompt
	* @var	bool		Received an HTML form response
	* @var	array		Values returned from an HTML form
	*/
	private function execute_step($step, $startat, $script, &$xml, $checktable = true, $response = null, $htmlsubmit = false, $htmldata = array())
	{
		$data = array(
			'startat'    => $startat,
			'htmlsubmit' => $htmlsubmit,
			'htmldata'   => $htmldata,
		);
		if ($response !== null)
		{
			$data['response'] = $response;
		}

		$result = $script->execute_step($step, $checktable, $data);
		if ($result['message'])
		{
			foreach ($result['message'] AS $message)
			{
				$xml->add_tag('message', $message['value'], array('replace' => intval($message['replace'])));
			}
		}

		if ($result['error'])
		{
			foreach($result['error'] AS $error)
			{
				if ($error['fatal'])
				{
					switch ($error['code'])
					{
						case vB_Upgrade_Version::MYSQL_HALT:
							$xml->add_tag('apperror', sprintf($this->phrase['core']['dberror_ajax'], $this->scriptinfo['version'], $step, $error['value']['errno'], $error['value']['error'], $error['value']['message']), array('type' => 'MYSQL_HALT'));
							return false;
							break;

						case vB_Upgrade_Version::PHP_TRIGGER_ERROR:
							$xml->add_tag('apperror', sprintf($this->phrase['core']['phperror_ajax'], $this->scriptinfo['version'], $step, $error['value']), array('type' => 'PHP_TRIGGER_ERROR'));
							return false;
							break;

						case vB_Upgrade_Version::APP_CREATE_TABLE_EXISTS:
							$xml->add_tag('apperror', sprintf($this->phrase['core']['tables_exist_ajax'], $error['value']), array('type' => 'APP_CREATE_TABLE_EXISTS'));
							return false;
							break;
					}
				}
			}
		}

		return $result;
	}

	/**
	* Display Login
	*
	* @return	boolean login success
	*/
	private function login()
	{
		if (isset($_POST['customerid']))
		{
			$this->registry->input->clean_array_gpc('p', array(
				'customerid' => TYPE_NOHTML,
			));
			if (md5(strtoupper($this->registry->GPC['customerid'])) == $this->custnumber)
			{
				setcookie('bbcustomerid', $this->custnumber, 0, '/', '');
				return true;
			}
			else
			{
				$this->htmloptions['login'] = true;
				$this->htmloptions['loginerror'] = true;
				return false;
			}
		}
		else
		{
			$this->htmloptions['login'] = true;
			return false;
		}
	}

	/**
	* Retrieve current MYSQL process list
	*
	*/
	protected function fetch_query_status()
	{
		$xml = new vB_AJAX_XML_Builder($this->registry, 'text/xml', vB_Template_Runtime::fetchStyleVar('charset'));
		$xml->add_group('processes');
		$xml->add_tag('query_status', $this->phrase['core']['query_status_title']);

		$processes = $this->db->query_read("
			SHOW FULL PROCESSLIST
		");
		$found = false;
		while ($process = $this->db->fetch_array($processes))
		{
			if (
				$process['db'] == $this->registry->config['Database']['dbname']
					AND
				$process['User'] == $this->registry->config['MasterServer']['username']
					AND
				$process['Info'] != 'SHOW FULL PROCESSLIST'
					AND
				$process['Command'] == 'Query'
					AND
				preg_match('/^(\s+)?### vBulletin Database Alter ###/s', $process['Info'])
			)
			{
				$process['Info'] = preg_replace("/^(\s+)?### vBulletin Database Alter ###/s", "", $process['Info']);
				$found = true;
				$totalseconds = intval($process['Time']);

				$hours = floor($seconds / 3600);
				$totalseconds -= $hours * 3600;

				$minutes = floor($totalseconds / 60);
				$totalseconds -= $minutes * 60;

				$seconds = $totalseconds;

				$xml->add_tag('process', construct_phrase($this->phrase['core']['process_x_y_z'], str_pad($hours, 2, "0", STR_PAD_LEFT), str_pad($minutes, 2, "0", STR_PAD_LEFT), str_pad($seconds, 2, "0", STR_PAD_LEFT), htmlspecialchars_uni($process['State']), htmlspecialchars_uni($process['Info'])));
			}
		}

		if (!$found)
		{
			$xml->add_tag('noprocess', $this->phrase['core']['no_processes_found']);
		}

		$xml->close_group('processes');
		$xml->print_xml();
	}

	/**
	* Output page HTML
	*
	*/
	private function print_html()
	{
		header('Expires: ' . gmdate('D, d M Y H:i:s', TIMENOW) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', TIMENOW) . ' GMT');

		$hiddenfields = '';
		if ($this->registry->GPC['version'])
		{
			$hiddenfields = "<input type=\"hidden\" name=\"version\" value=\"{$this->registry->GPC['version']}\" />";
		}
		$hiddenfields .= "
			<input type=\"hidden\" name=\"step\" value=\"{$this->registry->GPC['step']}\" />
			<input type=\"hidden\" name=\"startat\" value=\"{$this->registry->GPC['startat']}\" />
			<input type=\"hidden\" name=\"only\" value=\"{$this->registry->GPC['only']}\" />
		";

		$output_version = defined('ADMIN_VERSION_VBULLETIN') ? ADMIN_VERSION_VBULLETIN : ($this->registry->options['templateversion'] ? $this->registry->options['templateversion'] : $this->htmloptions['finalversion']);
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" dir="<?php echo vB_Template_Runtime::fetchStyleVar('textdirection'); ?>" lang="<?php echo vB_Template_Runtime::fetchStyleVar('languagecode'); ?>">
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=<?php echo vB_Template_Runtime::fetchStyleVar('charset'); ?>" />
			<meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />
			<meta http-equiv="Pragma" content="no-cache, must-revalidate" />
			<title><?php echo $this->htmloptions['setuptypetitle'] . " " . $steptitle; ?></title>
			<link rel="stylesheet" href="<?php echo "../cpstyles/{$this->registry->options['cpstylefolder']}/controlpanel.css"; ?>" />
			<style type="text/css">
			body {
				margin:0;
			}
			#vb_overlay_background {
				opacity:0.50;
				-moz-opacity:0.50;
				filter:alpha(opacity=50);
				background-color:#000000;
				z-index:10;
				position:fixed;
				top:0px;
				<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:0px;
				width:100%;
				height:100%;
			}
			#header {
				border:outset 2px;
				display:block;
				padding:5px;
			}

			.floatcontainer:after {
				content: ".";
				display: block;
				height: 0;
				clear: both;
				visibility: hidden;
			}
			.floatcontainer {
				display:inline-block;
			}

			/* IE-Mac hide \*/
			* html .floatcontainer {
				height:1%;
			}
			.floatcontainer {
				display:block;
			}
			.floatcontainer.hidden {
				display:none;
			}
			/* End IE-Mac hide */

			#header div.logo {
				width:160px;
				float:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
			}
			#header div.notice {
				padding-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:220px;
				padding-top:18px;
			}

			#header div.xml1 {
				float:<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>;
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>;
				padding-top:5px;
			}
			#header div.xml2 {
				float:<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>;
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
				padding-top:5px;
				padding-<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>:10px;
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:15px;
			}
			#header div.xml1 ul, #header div.xml2 ul {
				list-style-type:none;
				margin:0;
				padding:0;
				font-size: 11px;
				font-family: tahoma, verdana, arial, helvetica, sans-serif;
			}
			#all {
				margin: 10px;
			}
			#all p, #all td, #all li, #all div {
				font-size: 11px;
				font-family: verdana, arial, helvetica, sans-serif;
			}

			#confirm, #prompt {
				z-index:11;
				position:absolute;
			}

			#prompt div.input {
				display:block;
			}

			#progressbox, #mismatch, #authenticate, #prompt, #confirm, #startup_errors {
				width:375px;
				text-align:center;
				margin-left:auto;
				margin-right:auto;
				margin-top:20px;
			}

			#startup_errors .messagebody
			{
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
				padding:10px;
			}

			#promptmessage, #confirmmessage {
				padding:10px;
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
				overflow:auto;
				max-height:400px;
			}

			#progresssection .buttons {
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
			}

			.messageheader {
				padding:5px;
				font-weight:bold;
				text-align:center;
			}

			.submit, .messagebody {
				padding:5px;
			}

			.advancedconfirmbody {
				padding-left:10px;
				padding-right:10px;
			}

			.advancedconfirm .messagebody{
				padding:0;
			}

			.advancedconfirm #confirmmessage {
				padding:0;
			}

			.list_no_decoration {
				list-style-type:none;
				margin:0px;
				padding:0px;
			}

			#detailbox {
				margin: 20px auto;
				width:375px;
			}

			#promptresponse {
				width:90%;
			}

			#customerid {
				width:250px;
				font-weight:bold;
				display:block;
		    margin:10px auto 0 auto;
			}

			#customerid_error {
				margin:10px 0;
				padding:3px;
			}

			input[type="submit"] {
				margin-top:10px;
			}

			#mainmessage {
				height:300px;
				overflow:auto;
				padding-bottom:10px;
			}

			#mainmessage > ul > li {
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:25px;
				text-indent:-25px;
			}

			#mainmessage > ul > li ol,
			#mainmessage > ul > li.noindent {
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>:0;
				text-indent:0;
			}

			#mainmessage li.querystatus {}

			#upgradeprogress {
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>:10px;
				margin-top:4px;
				float:<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>;
				vertical-align:middle;
			}

			#progressbar_container {
				border:1px outset;
				height:16px;
				width:95%;
				margin:10px auto;
				background-color:#FFFFFF;
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
			}

			#progressbar {
				width:0;
				height:15px;
				padding-top:1px;
				background-color:#CCCCCC;
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>;
				overflow:auto;
				float:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
			}

			#progressbar span {
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('right'); ?>: 2px;
			}

			#percentageout {
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>: 2px;
				padding-top:1px;
				float:left;
			}

			 #showdetails, #hidedetails {
				margin-<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>: 7px;
			}

			#beginsection {
				#padding-bottom:10px;
			}

			#confirmform, #promptform {
				padding:0;
				margin:0;
			}

			.hidden {
				display:none;
			}

			.usererror {
				display:block;
				color:red;
			}

			.usererror.hidden {
				display:none;
			}

			.lbinstallselect {
				max-width:180px;
				_width:180px;
			}

			div.consolemsg {
				text-align:<?php echo vB_Template_Runtime::fetchStyleVar('left'); ?>;
				padding:10px;
			}

			div.consolemsg ul li {
				font-weight:bold;
				list-style-type:none;
			}
}

			</style>
			<script type="text/javascript">
			<!--
				var IMGDIR_MISC = "../cpstyles/<?php echo $this->registry->options['cpstylefolder']; ?>";
				var CLEARGIFURL = "./clear.gif";
				var CUSTNUMBER = "<?php echo $this->custnumber; ?>";
				var VERSION = "<?php echo $this->htmloptions['version']; ?>";
				var SCRIPTINFO = {
					version: "<?php echo $this->htmloptions['version']; ?>",
					startat: "<?php echo $this->htmloptions['startat']; ?>",
					step   : "<?php echo $this->htmloptions['step']; ?>",
					only   : "<?php echo $this->htmloptions['only']; ?>"
				};
				var ADMINDIR = "../<?php echo $this->registry->config['Misc']['admincpdir']; ?>";
				var TOTALSTEPS = <?php echo intval($this->htmloptions['totalsteps']); ?>;
				var ABORTMSG = "<?php echo $this->phrase['core']['status_aborted']; ?>";
				var UNEXPECTED_TEXT = "<?php echo $this->phrase['core']['unexpected_text']; ?>";
				var SETUPTYPE = "<?php echo $this->setuptype; ?>";
				var STEP_X_Y = "<?php echo $this->phrase['core']['step_x_y']; ?>";
				var SERVER_NO_RESPONSE = "<?php echo $this->phrase['core']['server_no_response'] ?>";
			//-->
			</script>
			<script type="text/javascript" src="../clientscript/yui/yuiloader-dom-event/yuiloader-dom-event.js"></script>
			<script type="text/javascript" src="../clientscript/yui/connection/connection-min.js"></script>
			<script type="text/javascript" src="../clientscript/vbulletin_global.js"></script>
			<script type="text/javascript" src="../clientscript/vbulletin-core.js"></script>
		</head>
		<body>
		<div id="vb_overlay_background" class="hidden"></div>
		<div id="header" class="navbody floatcontainer">
			<div class="xml2">
				<ul>
					<li id="vb_style_version"><?php echo $this->xml_versions['style']; ?></li>
					<li id="vb_settings_version"><?php echo $this->xml_versions['settings']; ?></li>
					<li id="vb_language_version"><?php echo $this->xml_versions['language']; ?></li>
					<li id="vb_admin_help_version"><?php echo $this->xml_versions['adminhelp']; ?></li>
				</ul>
			</div>
			<div class="xml1">
				<ul>
					<li>vbulletin-style.xml:</li>
					<li>vbulletin-settings.xml:</li>
					<li>vbulletin-language.xml:</li>
					<li>vbulletin-adminhelp.xml:</li>
				</ul>
			</div>
			<div class="logo">
				<img src="../cpstyles/<?php echo $this->registry->options['cpstylefolder']; ?>/cp_logo.gif" alt="" title="vBulletin 4 &copy; <?php echo date('Y'); ?> vBulletin Solutions, Inc. All rights reserved." />
			</div>
			<div class="notice">
				<strong><?php echo $this->htmloptions['setuptype']; ?></strong><br />
				<?php echo $this->phrase['core']['may_take_some_time']; ?>
			</div>
		</div>

		<div id="all">
			<div class="tborder<?php if (!$this->htmloptions['startup_errors']) { echo " hidden"; } ?>" id="startup_errors">
				<div class="navbody messageheader"><?php echo $this->phrase['core']['startup_errors']; ?></div>
				<div class="messagebody logincontrols">
					<?php echo $this->phrase['core']['startup_errors_desc']; ?>
					<ul>
						<li class="hidden"></li>
						<?php echo $this->htmloptions['startup_errors']; ?>
					</ul>
				</div>
			</div>
			<div class="tborder<?php if (!$this->htmloptions['login']) { echo " hidden"; } ?>" id="authenticate">
				<div class="navbody messageheader"><?php echo $this->phrase['authenticate']['enter_cust_num']; ?></div>
				<div class="messagebody logincontrols">
					<?php echo $this->phrase['authenticate']['cust_num_explanation']; ?>
					<form action="<?php echo $this->setuptype; ?>.php" method="post">
						<input type="text" tabindex="1" value="" name="customerid" id="customerid" />
						<?php if ($this->htmloptions['loginerror']) { ?><div id="customerid_error" class="navbody"><?php echo $this->phrase['authenticate']['cust_num_incorrect']; ?></div><?php } ?>
						<input class="button" type="submit" tabindex="1" accesskey="s" id="authsubmit" value="<?php echo $this->htmloptions['enter_system'] ?>" />
						<?php echo $hiddenfields ?>
					</form>
				</div>
			</div>
			<div class="tborder<?php if (!$this->htmloptions['mismatch']) { echo " hidden"; } ?>" id="mismatch">
				<div class="navbody messageheader"><?php echo $this->phrase['core']['version_mismatch']; ?></div>
				<div class="messagebody logincontrols">
					<?php echo construct_phrase($this->phrase['core']['wrong_version'], $this->versions[$this->htmloptions['version']], $this->registry->options['templateversion']); ?>
					<form action="upgrade.php" method="post">
						<input type="hidden" name="mismatch" value="1" />
						<label for="version1"><input id="version1" type="radio" name="version" value="<?php echo htmlspecialchars_uni($this->htmloptions['version']); ?>" /><?php echo construct_phrase($this->phrase['core']['upgrade_from_x'], $this->versions[$this->htmloptions['version']]); ?></label>
						<label for="version2"><input id="version2" type="radio" name="version" value="<?php echo htmlspecialchars_uni($this->registry->options['templateversion']); ?>" /><?php echo construct_phrase($this->phrase['core']['upgrade_from_x'], $this->registry->options['templateversion']); ?></label>
						<input class="button" type="submit" tabindex="1" accesskey="s" name="" value="<?php echo $this->htmloptions['enter_system'] ?>" />
						<?php echo $hiddenfields ?>
					</form>
				</div>
			</div>

			<div class="tborder<?php if (!$this->htmloptions['processlog']) { echo " hidden"; } ?>" id="progressbox">
				<div class="navbody messageheader"><?php echo $this->htmloptions['upgrading_to_x']; ?></div>
				<div class="messagebody logincontrols">
					<div class="hidden" id="progresssection">
						<div id="progressmessage"><?php echo $this->htmloptions['status'] ?></div>
						<div id="progressbar_container">
							<div id="progressbar"></div>
							<div id="percentageout"></div>
						</div>
						<div id="progressnotice"></div>
						<div class="buttons floatcontainer">
							<img id="upgradeprogress" class="hidden" src="../cpstyles/<?php echo $this->registry->options['cpstylefolder']; ?>/progress.gif" alt="" />
							<input class="button" type="button" id="showdetails" tabindex="1" name="" value="<?php echo $this->phrase['core']['show_details'] ?>" />
							<input class="button hidden" type="button" id="hidedetails" tabindex="1" name="" value="<?php echo $this->phrase['core']['hide_details'] ?>" />
							<input class="button hidden" type="button" id="admincp" tabindex="1" name="" value="<?php echo $this->phrase['core']['admin_cp'] ?>" />
							<input class="button hidden" type="button" id="querystatus" tabindex="1" name="" value="<?php echo $this->phrase['core']['query_status'] ?>" />
						</div>
					</div>
					<div id="beginsection">
						<form action="<?php echo $this->setuptype; ?>.php" method="post">
							<?php if ($this->htmloptions['suggestconsole']) { echo '<div class="consolemsg">' . construct_phrase($this->phrase['core']['console_upgrade_steps'], $this->htmloptions['suggestconsole']) . '</div>'; } ?>
							<p><?php echo $this->htmloptions['upgrademessage']; ?></p>
							<input type="hidden" name="jsfail" value="1" />
							<input class="button" type="submit" id="beginupgrade" tabindex="1" name="" value="<?php echo $this->htmloptions['begin_setup'] ?>" />
						</form>
					</div>
				</div>
			</div>

			<div id="detailbox" class="tborder hidden">
				<div class="navbody messageheader"><?php echo $this->htmloptions['progress']; ?></div>
				<div id="mainmessage" class="messagebody logincontrols"></div>
				<div class="status">
					<span id="statusmessage"><?php echo $this->htmloptions['status'] ?></span>
				</div>
			</div>

			<div class="tborder hidden" id="prompt">
				<div class="navbody messageheader" id="prompttitle"><?php echo $this->phrase['core']['action_required']; ?></div>
				<div class="messagebody logincontrols">
					<div id="promptmessage"></div>
					<form action="upgrade.php" method="post" id="promptform">
						<input type="text" tabindex="1" value="" name="promptresponse" id="promptresponse" />
						<div class="submit">
							<input class="button" type="submit" name="submit" tabindex="1" accesskey="s" id="promptsubmit" value="<?php echo $this->phrase['vbphrase']['ok']; ?>" />
							<input class="button hidden" type="reset" name="reset" tabindex="1" id="promptreset" value="<?php echo $this->phrase['vbphrase']['reset']; ?>" />
							<input class="button hidden" type="submit" name="submit" tabindex="1" accesskey="s" id="promptcancel" value="<?php echo $this->phrase['vbphrase']['cancel']; ?>" />
						</div>
					</form>
				</div>
			</div>

			<div class="tborder hidden" id="confirm">
				<div class="navbody messageheader" id="confirmtitle"><?php echo $this->phrase['core']['action_required']; ?></div>
				<div class="messagebody logincontrols">
					<form action="<?php echo $this->setuptype; ?>.php" method="post" id="confirmform">
						<div id="confirmmessage"></div>
						<div class="submit">
							<input class="button" type="submit" name="submit" tabindex="1" accesskey="s" id="confirmok" value="<?php echo $this->phrase['vbphrase']['ok']; ?>" />
							<input class="button hidden" type="reset" name="reset" tabindex="1" id="confirmreset" value="<?php echo $this->phrase['vbphrase']['reset']; ?>" />
							<input class="button" type="button" name="cancel" tabindex="1" accesskey="s" id="confirmcancel" value="<?php echo $this->phrase['vbphrase']['cancel']; ?>" />
						</div>
					</form>
				</div>
			</div>

		</div>

		<p align="center"><a href="http://www.vbulletin.com/" target="_blank" class="copyright">
		<?php echo construct_phrase($this->phrase['vbphrase']['vbulletin_copyright_orig'], $output_version, date('Y')); ?>
		</a></p>
		<script type="text/javascript" src="vbulletin-upgrade.js"></script>
		</body>
		</html>
		<?php
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
