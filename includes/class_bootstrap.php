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

/**
* General frontend bootstrapping class. As this is designed to be as backwards
* compatible as possible, there are loads of global variables. Beware!
*
* @package	vBulletin
*/
class vB_Bootstrap
{
	/**
	* A particular style ID to force. If specified, it will be used even if disabled.
	*
	* @var	int
	*/
	protected $force_styleid = 0;

	/**
	* Determines the called actions
	*
	* @var	array
	*/
	protected $called = array(
		'style'    => false,
		'template' => false
	);

	/**
	* A list of datastore entries to cache.
	*
	* @var	array
	*/
	public $datastore_entries = array();

	/**
	* A list of templates (names) that should be cached. Does not include
	* globally cached templates.
	*
	* @var	array
	*/
	public $cache_templates = array();

	// ============ MAIN BOOTSTRAPPING FUNCTIONS ===============

	/**
	* General bootstrap wrapper. This can be used to do virtually all of the
	* work that you'd usually want to do at the beginning. Style and template
	* setup are deferred until first usage.
	*/
	public function bootstrap()
	{
		global $VB_API_REQUESTS;
		
		$this->init();

		$this->load_language();
		$this->load_permissions();

		$this->read_input_context();
		$this->load_show_variables();

		//$this->load_style();
		//$this->process_templates();

		if (!defined('NOCHECKSTATE') AND (!VB_API OR $VB_API_REQUESTS['api_m'] != 'api_init'))
		{
			$this->check_state();
		}

		$this->load_facebook();

		($hook = vBulletinHook::fetch_hook('global_bootstrap_complete')) ? eval($hook) : false;
	}

	/**
	* Basic initialization of things like DB, session, etc.
	*/
	public function init()
	{
		global $vbulletin, $db, $show;

		$specialtemplates = $this->datastore_entries;

		define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

		if (!defined('VB_API'))
		{
			define('VB_API', false);
		}
		
		require_once(CWD . '/includes/init.php');

		($hook = vBulletinHook::fetch_hook('global_bootstrap_init_start')) ? eval($hook) : false;

		if (!defined('VB_ENTRY'))
		{
			define('VB_ENTRY', 1);
		}

		// Set Display of Ads to true - Set to false on non content pages
		if (!defined('CONTENT_PAGE'))
		{
			define('CONTENT_PAGE', true);
		}

		global $template_hook;
		$template_hook = array();

		global $ad_location;
		$ad_location = array();

		($hook = vBulletinHook::fetch_hook('global_bootstrap_init_complete')) ? eval($hook) : false;
	}

	/**
	* Reads some context based on general input information
	*/
	public function read_input_context()
	{
		global $vbulletin;

		$vbulletin->input->clean_array_gpc('r', array(
			'referrerid' => TYPE_UINT,
			'a'          => TYPE_STR,
			'nojs'       => TYPE_BOOL
		));

		$vbulletin->input->clean_array_gpc('p', array(
			'ajax' => TYPE_BOOL,
		));

		// #############################################################################
		// set the referrer cookie if URI contains a referrerid
		if ($vbulletin->GPC['referrerid'] AND !$vbulletin->GPC[COOKIE_PREFIX . 'referrerid'] AND !$vbulletin->userinfo['userid'] AND $vbulletin->options['usereferrer'])
		{
			if ($referrerid = verify_id('user', $vbulletin->GPC['referrerid'], 0))
			{
				vbsetcookie('referrerid', $referrerid);
			}
		}
	}

	/**
	* Loads permissions for the currently logged-in user.
	*/
	public function load_permissions()
	{
		global $vbulletin;
		cache_permissions($vbulletin->userinfo);
	}

	/**
	* Loads the language information for the logged-in user.
	*/
	public function load_language()
	{
		global $vbulletin;

		fetch_options_overrides($vbulletin->userinfo);
		fetch_time_data();

		global $vbphrase, $vbphrasegroup;
		if (!VB_API OR (defined('VB_API_LOADLANG') AND VB_API_LOADLANG === true))
		{
			$vbphrase = init_language();
			// Disable "Directional Markup Fix" from language options. API doesn't need it.
			$vbulletin->userinfo['lang_options'] -= $vbulletin->bf_misc_languageoptions['dirmark'];
		}
		else
		{
			$vbphrase = array();
		}

		// set a default username
		if ($vbulletin->userinfo['username'] == '')
		{
			$vbulletin->userinfo['username'] = $vbphrase['unregistered'];
		}
	}

	/**
	* Loads style information (selected style and style vars)
	*/
	public function load_style()
	{
		if ($this->called('style') AND !(defined('UNIT_TESTING') AND UNIT_TESTING === true))
		{
			return;
		}
		$this->called['style'] = true;

		global $style;
		$style = $this->fetch_style_record($this->force_styleid);
		define('STYLEID', $style['styleid']);

		global $vbulletin;
		$vbulletin->stylevars = unserialize($style['newstylevars']);
		fetch_stylevars($style, $vbulletin->userinfo);
	}

	/**
	* Check if facebook is enabled, and perform appropriate action based on
	* 	authentication state (fb and vb) of the user
	*/
	public function load_facebook()
	{
		global $vbulletin, $show;

		// check if facebook and session is enabled
		if (is_facebookenabled())
		{
			// is user is logged into facebook?
			if ($show['facebookuser'] = vB_Facebook::instance()->userIsLoggedIn())
			{
				// is user logged into vB?
				if (!empty($vbulletin->userinfo['userid']))
				{
					// if vb user is not associated with the current facebook account (or no facebook account at all),
					// redirect to the register association page, if doing facebook redirect
					if ($vbulletin->userinfo['fbuserid'] != vB_Facebook::instance()->getLoggedInFbUserId())
					{
						if (do_facebook_redirect())
					{
						exec_header_redirect('register.php' . $vbulletin->session->vars['sessionurl_q']);
					}

						// if not doing facebook redirect and not on the reg page,
						// pretend the user is not logged into facebook at all so user can browse
						else if (THIS_SCRIPT != 'register')
						{
							$show['facebookuser'] = false;
						}
					}
				}

				// user is not logged into vb, but logged into facebook
				else
				{
					// check if there is an associated vb account, if so attempt to log that user in
					if (vB_Facebook::instance()->getVbUseridFromFbUserid())
				{
						// make sure user is trying to login
						if (do_facebook_redirect())
						{
							// need to load the style here to display
							// the login welcome message properly
							$this->load_style();

					require_once(DIR . '/includes/functions_login.php');
					if (verify_facebook_authentication())
					{
						// create new session
						process_new_login('fbauto', false, '');

						// do redirect
						do_login_redirect();
					}
				}
						// if user is not trying to login with FB connect,
						// pretend like the user is not logged in to FB
						else if (THIS_SCRIPT != 'register')
						{
							$show['facebookuser'] = false;
						}
					}

				// otherwise, fb account is not associated with any vb user
					else
					{
						// redirect to the registration page to create a vb account
						if (do_facebook_redirect())
				{
					exec_header_redirect('register.php' . $vbulletin->session->vars['sessionurl_q']);
				}

						// if not doing redirect and not trying to register,
						// pretend user is not logged into facebook so they can still browse the site
						else if (THIS_SCRIPT != 'register')
			{
				$show['facebookuser'] = false;
			}
		}
	}
			}
		}
	}



	/**
	* Processes the global templates (header, footer, etc) and the variables they need.
	*/
	public function process_templates()
	{
		if ($this->called('template'))
		{
			return;
		}
		$this->called['template'] = true;

		global $vbulletin, $show, $vbphrase, $style, $template_hook;

		$this->load_style();

		$this->cache_templates($style['templatelist']);

		// #############################################################################
		// turn off popups if they are not available to this browser
		if ($vbulletin->options['usepopups'])
		{
			if ((is_browser('ie', 5) AND !is_browser('mac')) OR is_browser('mozilla') OR is_browser('firebird') OR is_browser('opera', 7) OR is_browser('webkit') OR is_browser('konqueror', 3.2))
			{
				// use popups
			}
			else
			{
				// don't use popups
				$vbulletin->options['usepopups'] = 0;
			}
		}

		global $vbcollapse;
		$vbcollapse = self::build_vbcollapse($vbulletin->GPC['vbulletin_collapse']);

		// #############################################################################
		// set up the vars for the private message area of the navbar
		global $pmbox;
		$pmbox = array(
			'lastvisitdate' => vbdate($vbulletin->options['dateformat'], $vbulletin->userinfo['lastvisit'], 1),
			'lastvisittime' => vbdate($vbulletin->options['timeformat'], $vbulletin->userinfo['lastvisit'])
		);

		if ($vbulletin->userinfo['userid'])
		{
			$pmunread_html = construct_phrase(($vbulletin->userinfo['pmunread'] ? $vbphrase['numeric_value_emphasized'] : $vbphrase['numeric_value']), $vbulletin->userinfo['pmunread']);
			$vbphrase['unread_x_nav_compiled'] = construct_phrase($vbphrase['unread_x_nav'], $pmunread_html);
			$vbphrase['total_x_nav_compiled'] = construct_phrase($vbphrase['total_x_nav'], $vbulletin->userinfo['pmtotal']);
		}

		// #############################################################################
		// Generate Language/Style Chooser Dropdowns
		if (!VB_API)
		{
			if (empty($_POST['do']))
			{
				$languagecount = 0;
				$languagechooserbits = construct_language_options('--', true, $languagecount);
				$show['languagechooser'] = ($languagecount > 1 ? true : false);
			}
			else
			{
				$show['languagechooser'] = false;
			}

			if ($vbulletin->options['allowchangestyles'] AND empty($_POST['do']))
			{
				$stylecount = 0;
				$quickchooserbits = construct_style_options(-1, '--', true, true, $stylecount);
				$show['quickchooser'] = ($stylecount > 1 ? true : false);
			}
			else
			{
				$show['quickchooser'] = false;
			}
		}

		// #############################################################################
		// do cron stuff - goes into footer
		if ($vbulletin->cron <= TIMENOW)
		{
			$cronimage = '<img src="' . create_full_url('cron.php?' . $vbulletin->session->vars['sessionurl'] . 'rand=' .  TIMENOW) . '" alt="" width="1" height="1" border="0" />';
		}
		else
		{
			$cronimage = '';
		}

		global $ad_location;

		// parse some global templates
		global $gobutton, $spacer_open, $spacer_close;
		$gobutton = vB_Template::create('gobutton')->render();
		$spacer_open = vB_Template::create('spacer_open')->render();
		$spacer_close = vB_Template::create('spacer_close')->render();

		// facebook templates
		if (is_facebookenabled())
		{
			$templater = vB_Template::create('facebook_opengraph');
			$templater->register('opengrapharray', get_fbopengrapharray());
			$facebook_opengraph = $templater->render();

			$templater = vB_Template::create('facebook_header');
			$templater->register('profileurl', get_fbprofileurl());
			$templater->register('squarepicurl', get_fbprofilepicurl());
			$facebook_header = $templater->render();

			$templater = vB_Template::create('facebook_footer');
			$templater->register('connected', (is_userfbconnected() ? 1 : 0));
			$templater->register('active', (is_userfbactive() ? 1 : 0));
			$facebook_footer = $templater->render();
		}

		($hook = vBulletinHook::fetch_hook('parse_templates')) ? eval($hook) : false;

		// parse headinclude, header & footer
		$admincpdir = $vbulletin->config['Misc']['admincpdir'];
		$modcpdir = $vbulletin->config['Misc']['modcpdir'];

		// advertising location setup
		$template = vB_Template::create('ad_navbar_below');
		$template->register('adsense_pub_id', $vbulletin->adsense_pub_id);
		$template->register('adsense_host_id', $vbulletin->adsense_host_id);
		$ad_location['ad_navbar_below'] = $template->render();

		$ad_location['global_header1'] = vB_Template::create('ad_global_header1')->render();
		$ad_location['global_header2'] = vB_Template::create('ad_global_header2')->render();
		$ad_location['global_below_navbar'] = vB_Template::create('ad_global_below_navbar')->render();
		$ad_location['global_above_footer'] = vB_Template::create('ad_global_above_footer')->render();

		$template = vB_Template::create('ad_footer_start');
		$template->register('adsense_pub_id', $vbulletin->adsense_pub_id);
		$template->register('adsense_host_id', $vbulletin->adsense_host_id);
		$ad_location['ad_footer_start'] = $template->render();

		// #############################################################################
		// handle notices
		global $notices;
		$notices = $this->build_notices();
		$show['notices'] = ($notices !== '');

		// #############################################################################
		// set up user notifications
		$notifications = $this->build_notifications();
		if ($notifications)
		{
			$show['notifications'] = true;
			global $notifications_menubits, $notifications_total;
			$notifications_menubits = $notifications['bits'];
			$notifications_total = $notifications['total'];
		}
		else
		{
			$notifications_menubits = '';
			$notifications_total = '';
			$show['notifications'] = false;
		}

		$newpm = $this->check_new_pm();

		// #############################################################################
		// page number is used in meta tags (sometimes)
		global $pagenumber, $headinclude, $headinclude_bottom, $header, $footer;
		global $threadinfo, $foruminfo;
		if ($pagenumber === NULL)
		{
			$pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
		}

		$templater = vB_Template::create('headinclude');
			$templater->register('foruminfo', $foruminfo);
			$templater->register('pagenumber', $pagenumber);
			//$templater->register('style', $style);
			$templater->register('basepath', $vbulletin->input->fetch_basepath());
			$templater->register('this_script', THIS_SCRIPT);
			$templater->register('facebook_opengraph', $facebook_opengraph);
		$headinclude = $templater->render();

		$templater = vB_Template::create('headinclude_bottom');
			$templater->register('foruminfo', $foruminfo);
			$templater->register('pagenumber', $pagenumber);
			//$templater->register('style', $style);
			$templater->register('basepath', $vbulletin->input->fetch_basepath());
		$headinclude_bottom = $templater->render();

		$templater = vB_Template::create('header');
			$templater->register('ad_location', $ad_location);
			$templater->register('pmbox', $pmbox);
			$templater->register('notifications_menubits', $notifications_menubits);
			$templater->register('notifications_total', $notifications_total);
			$templater->register('notices', $notices);
			$templater->register('facebook_header', $facebook_header);
		$header = $templater->render();

		$templater = vB_Template::create('footer');
			$templater->register('admincpdir', $admincpdir);
			$templater->register('ad_location', $ad_location);
			$templater->register('cronimage', $cronimage);
			$templater->register('languagechooserbits', $languagechooserbits);
			$templater->register('modcpdir', $modcpdir);
			$templater->register('quickchooserbits', $quickchooserbits);
			$templater->register('template_hook', $template_hook);
			$templater->register('facebook_footer', $facebook_footer);
		$footer = $templater->render();

		// #############################################################################
		// Check for pm popup
		if ($newpm)
		{
			if ($vbulletin->userinfo['pmunread'] == 1)
			{
				$pmpopupurl = 'private.php?' . $vbulletin->session->vars['sessionurl_js'] . "do=showpm&pmid=$newpm[pmid]";
			}
			else if (!empty($vbulletin->session->vars['sessionurl_js']))
			{
				$pmpopupurl = 'private.php?' . $vbulletin->session->vars['sessionurl_js'];
			}
			else
			{
				$pmpopupurl = 'private.php';
			}
			$templater = vB_Template::create('pm_popup_script');
				$templater->register('newpm', $newpm);
				$templater->register('pmpopupurl', $pmpopupurl);
			$footer .= $templater->render();
		}

		if (!$vbulletin->options['bbactive'] AND
			($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			// show the board disabled warning message so that admins don't leave the board turned off by accident
			$warning = vB_Template::create('board_inactive_warning')->render();
			$header = $warning . $header;
			$footer .= $warning;
		}

		($hook = vBulletinHook::fetch_hook('process_templates_complete')) ? eval($hook) : false;

		exec_headers();
	}

	/**
	* Loads assorted show variables. Ideally, these would be used in templates,
	* but sometimes they're used within code.
	*/
	public function load_show_variables()
	{
		global $show, $vbulletin, $vbphrase;

		$show['old_explorer'] = (is_browser('ie') AND !is_browser('ie', 6));

		$show['rtl'] = (!($vbulletin->userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['direction']));

		$show['admincplink'] = ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] ? true : false);
		// This generates an extra query for non-admins/supermods on many pages so we have chosen to only display it to supermods & admins
		// $show['modcplink'] = iif(can_moderate(), true, false);
		$show['modcplink'] = (
			$vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
			OR $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
		);

		$show['registerbutton'] = (
			!$show['search_engine']
			AND $vbulletin->options['allowregistration']
			AND (!$vbulletin->userinfo['userid'] OR $vbulletin->options['allowmultiregs'])
		);
		$show['searchbuttons'] = (
			!$show['search_engine']
			AND $vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['cansearch']
			AND $vbulletin->options['enablesearches']
		);
		$show['quicksearch'] = (!fetch_require_hvcheck('search'));
		$show['memberslist'] = (
			$vbulletin->options['enablememberlist']
			AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
		);

		$loggedout = false;
		if (THIS_SCRIPT == 'login' AND $_REQUEST['do'] == 'logout' AND $vbulletin->userinfo['userid'] != 0)
		{
			$vbulletin->input->clean_gpc('r', 'logouthash', TYPE_STR);
			if (verify_security_token($vbulletin->GPC['logouthash'], $vbulletin->userinfo['securitytoken_raw']))
			{
				$loggedout = true;
			}
		}
		if (!$vbulletin->userinfo['userid'] OR $loggedout)
		{
			$show['guest'] = true;
			$show['member'] = false;
		}
		else
		{
			$show['guest'] = false;
			$show['member'] = true;
		}

		$show['detailedtime'] = ($vbulletin->options['yestoday'] == 2);

		$show['popups'] = (!$show['search_engine'] AND $vbulletin->options['usepopups'] AND !$vbulletin->GPC['nojs']);
		if ($show['popups'])
		{
			// this isn't what $show is for, but it's a variable that's available in many places
			$show['nojs_link'] = $vbulletin->scriptpath . (strpos($vbulletin->scriptpath, '?') ? '&amp;' : '?') . 'nojs=1';
		}
		else
		{
			$show['nojs_link'] = '';
		}

		if ($vbulletin->options['enablepms'] AND $vbulletin->userinfo['userid'] AND ($vbulletin->userinfo['pmunread'] OR ($vbulletin->userinfo['receivepm'] AND $vbulletin->userinfo['permissions']['pmquota'])))
		{
			if ($vbulletin->userinfo['pmtotal'] < $vbulletin->userinfo['permissions']['pmquota'])
			{
				if (($vbphrase['pmpercent_nav_compiled'] = number_format(floor($vbulletin->userinfo['pmtotal'] / $vbulletin->userinfo['permissions']['pmquota'] * 100), 0)) >= 90)
				{
					$show['pmwarning'] = true;
				}
				else
				{
					$show['pmwarning'] = false;
				}
			}
			else if ($vbulletin->userinfo['permissions']['pmquota'])
			{
				$show['pmwarning'] = true;
				$vbphrase['pmpercent_nav_compiled'] = '100';
			}
			else
			{
				$show['pmwarning'] = false;
			}
			$show['pmstats'] = true;
		}
		else
		{
			$show['pmstats'] = false;
			$show['pmwarning'] = false;
		}
		$show['pmmainlink'] = (
			$vbulletin->options['enablepms']
			AND $vbulletin->userinfo['userid']
			AND ($vbulletin->userinfo['permissions']['pmquota'] OR $vbulletin->userinfo['pmtotal'])
		);
		$show['pmtracklink'] = ($vbulletin->userinfo['permissions']['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm']);
		$show['pmsendlink'] = ($vbulletin->userinfo['permissions']['pmquota']);

		$show['siglink'] = ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusesignature']);
		$show['avatarlink'] = ($vbulletin->options['avatarenabled']);
		$show['profilepiclink'] = (
			$vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic']
			AND $vbulletin->options['profilepicenabled']
		);
		$show['wollink'] = ($vbulletin->userinfo['permissions']['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline']);

		$show['spacer'] = true; // used in postbit template

		$show['dst_correction'] = (
			THIS_SCRIPT != 'register'
			AND ($vbulletin->session->vars['loggedin'] == 1 OR $vbulletin->session->created OR THIS_SCRIPT == 'usercp')
			AND $vbulletin->userinfo['dstauto'] == 1
			AND $vbulletin->userinfo['userid']
		);

		$show['contactus'] = (
			$vbulletin->options['contactuslink']
			AND ((!$vbulletin->userinfo['userid'] AND $vbulletin->options['contactustype']) OR $vbulletin->userinfo['userid'])
		);

		// you may define this if you don't want the password in the login box to be zapped onsubmit; good for integration
		$show['nopasswordempty'] = defined('DISABLE_PASSWORD_CLEARING') ? 1 : 0; // this nees to be an int for the templates

		// Determine display of certain navbar Quick Links
		$show['quick_links_groups'] = (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
			AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
		);
		$show['quick_links_albums'] = (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
			AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
			AND $vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
		);
		$show['friends_and_contacts'] = (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends']
			AND $vbulletin->userinfo['permissions']['genericpermissions2'] & $vbulletin->bf_ugp_genericpermissions2['canusefriends']
		);
		$show['communitylink'] = ($show['quick_links_groups'] OR $show['quick_links_albums'] OR $vbulletin->userinfo['userid'] OR $show['memberslist']);

		// We don't want the number of columns to be more than the total number of smilies to display #36621
		$vbulletin->options['smcolumns'] = $vbulletin->options['smcolumns'] > $vbulletin->options['smtotal'] ? $vbulletin->options['smtotal'] : $vbulletin->options['smcolumns'];
	}

	/**
	* Checks the state of the request to make sure that it's valid and that
	* we have the necessary permissions to continue. Checks things like
	* CSRF and banning.
	*/
	public function check_state()
	{
		global $vbulletin, $show, $VB_API_REQUESTS;

		if (defined('CSRF_ERROR'))
		{
			define('VB_ERROR_LITE', true);

			$ajaxerror = $vbulletin->GPC['ajax'] ? '_ajax' : '';

			switch (CSRF_ERROR)
			{
				case 'missing':
					standard_error(fetch_error('security_token_missing', $vbulletin->options['contactuslink']));
					break;

				case 'guest':
					standard_error(fetch_error('security_token_guest' . $ajaxerror));
					break;

				case 'timeout':
					standard_error(fetch_error('security_token_timeout' . $ajaxerror, $vbulletin->options['contactuslink']));
					break;

				case 'invalid':
				default:
					standard_error(fetch_error('security_token_invalid', $vbulletin->options['contactuslink']));
			}
			exit;
		}

		// #############################################################################
		// check to see if server is too busy. this is checked at the end of session.php
		if ($this->server_overloaded() AND !($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND THIS_SCRIPT != 'login')
		{
			$vbulletin->options['useforumjump'] = 0;
			standard_error(fetch_error('toobusy'));
		}

		// #############################################################################
		// phpinfo display for support purposes
		if (!empty($_REQUEST['do']) AND $_REQUEST['do'] == 'phpinfo')
		{
			if ($vbulletin->options['allowphpinfo'] AND !is_demo_mode())
			{
				phpinfo();
				exit;
			}
			else
			{
				standard_error(fetch_error('admin_disabled_php_info'));
			}
		}

		// #############################################################################
		// check that board is active - if not admin, then display error
		if (
			!defined('BYPASS_FORUM_DISABLED')
				AND
			!$vbulletin->options['bbactive']
				AND
			!in_array(THIS_SCRIPT, array('login', 'css'))
				AND
			!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
		)
		{
			if (defined('DIE_QUIETLY'))
			{
				exit;
			}

			if (defined('VB_API') AND VB_API === true)
			{
				standard_error(fetch_error('bbclosed', $vbulletin->options['bbclosedreason']));
			}
			else
			{
				// If this is a post submission from an admin whose session timed out, give them a chance to log back in and save what they were working on. See bug #34258
				if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' AND !empty($_POST) AND !$vbulletin->userinfo['userid'] AND !empty($_COOKIE[COOKIE_PREFIX . 'cpsession']))
				{
					define('VB_ERROR_PERMISSION', true);
				}

				$show['enableforumjump'] = true;
				unset($vbulletin->db->shutdownqueries['lastvisit']);

				require_once(DIR . '/includes/functions_misc.php');
				eval('standard_error("' . make_string_interpolation_safe(str_replace("\\'", "'", addslashes($vbulletin->options['bbclosedreason']))) . '");');
			}
		}

		// #############################################################################
		// password expiry system
		if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['passwordexpires'])
		{
			$passworddaysold = floor((TIMENOW - $vbulletin->userinfo['passworddate']) / 86400);

			if ($passworddaysold >= $vbulletin->userinfo['permissions']['passwordexpires'])
			{
				if ((THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile' AND THIS_SCRIPT != 'ajax')
					OR (THIS_SCRIPT == 'profile' AND $_REQUEST['do'] != 'editpassword' AND $_POST['do'] != 'updatepassword')
					OR (THIS_SCRIPT == 'ajax' AND $_REQUEST['do'] != 'imagereg' AND $_REQUEST['do'] != 'securitytoken' AND $_REQUEST['do'] != 'dismissnotice')
				)
				{
					standard_error(fetch_error('passwordexpired',
						$passworddaysold,
						$vbulletin->session->vars['sessionurl']
					));
				}
				else
				{
					$show['passwordexpired'] = true;
				}
			}
		}
		else
		{
			$show['passwordexpired'] = false;
		}

		// #############################################################################
		// password same as username?
		if (!defined('ALLOW_SAME_USERNAME_PASSWORD') AND $vbulletin->userinfo['userid'])
		{
			// save the resource on md5'ing if the option is not enabled or guest
			if ($vbulletin->userinfo['password'] == md5(md5($vbulletin->userinfo['username']) . $vbulletin->userinfo['salt']))
			{
				if ((THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile') OR (THIS_SCRIPT == 'profile' AND $_REQUEST['do'] != 'editpassword' AND $_POST['do'] != 'updatepassword'))
				{
					standard_error(fetch_error('username_same_as_password',
						$vbulletin->session->vars['sessionurl']
					));
				}
			}
		}

		// #############################################################################
		// check required profile fields
		if ($vbulletin->session->vars['profileupdate'] AND THIS_SCRIPT != 'login' AND THIS_SCRIPT != 'profile' AND !VB_API)
		{
			$vbulletin->options['useforumjump'] = 0;
			standard_error(fetch_error('updateprofilefields', $vbulletin->session->vars['sessionurl']));
		}

		// #############################################################################
		// check permission to view forum
		if (!$this->has_global_view_permission())
		{
			if (defined('DIE_QUIETLY'))
			{
				exit;
			}
			else
			{
				print_no_permission();
			}
		}

		// #############################################################################
		// check for IP ban on user
		verify_ip_ban();

		($hook = vBulletinHook::fetch_hook('global_state_check')) ? eval($hook) : false;
	}

	// ============ HELPER FUNCTIONS ===============

	/**
	* Determines whether a particular step of the bootstrapping has been called.
	*
	* @param	string	Name of the step
	*
	* @return	bool	True if called
	*/
	public function called($step)
	{
		return !empty($this->called[$step]);
	}

	/**
	* Determines the style that should be used either by parameter or permissions
	* and then fetches that information
	*
	* @param	integer	A style ID to force (ignoring permissions). 0 to not force any.
	*
	* @return	array	Array of style information
	*/
	protected function fetch_style_record($force_styleid = 0)
	{
		global $vbulletin, $mobile_browser;

		$userselect = THIS_SCRIPT == 'css' ? true : false;

		// is style in the forum/thread set?
		if ($force_styleid AND !$mobile_browser)
		{
			// style specified by forum
			$styleid = $force_styleid;
			$vbulletin->userinfo['styleid'] = $styleid;
			$userselect = true;
		}
		else if ($vbulletin->userinfo['styleid'] > 0 AND ($vbulletin->options['allowchangestyles'] == 1 OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])))
		{
			// style specified in user profile
			$styleid = $vbulletin->userinfo['styleid'];
		}
		else
		{
			// no style specified - use default
			$styleid = $vbulletin->options['styleid'];
			$vbulletin->userinfo['styleid'] = $styleid;
		}

		// #############################################################################
		// if user can control panel, allow selection of any style (for testing purposes)
		// otherwise only allow styles that are user-selectable
		$styleid = intval($styleid);
		$style = NULL;

		($hook = vBulletinHook::fetch_hook('style_fetch')) ? eval($hook) : false;

		if (!is_array($style))
		{
			$style = $vbulletin->db->query_first_slave("
				SELECT *
				FROM " . TABLE_PREFIX . "style
				WHERE (styleid = $styleid" . iif(!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !$userselect, ' AND userselect = 1') . ")
					OR styleid = " . $vbulletin->options['styleid'] . "
				ORDER BY styleid " . iif($styleid > $vbulletin->options['styleid'], 'DESC', 'ASC') . "
				LIMIT 1
			");
		}

		return $style;
	}

	/**
	* Builds the applicable notice HTML
	*
	* @return	string	Applicable notice HTML
	*/
	protected function build_notices()
	{
		global $vbulletin, $vbphrase, $show;

		$notices = '';
		if (!defined('NONOTICES') AND !empty($vbulletin->noticecache) AND is_array($vbulletin->noticecache))
		{
			$return_link = $vbulletin->scriptpath;

			require_once(DIR . '/includes/functions_notice.php');
			if ($vbulletin->userinfo['userid'] == 0)
			{
				$vbulletin->userinfo['musername'] = fetch_musername($vbulletin->userinfo);
			}
			foreach (fetch_relevant_notice_ids() AS $_noticeid)
			{
				$show['notices'] = true;
				if (($vbulletin->noticecache["$_noticeid"]["dismissible"] == 1) AND $vbulletin->userinfo['userid'])
				{
					// only show the dismiss link for registered users; guest who wants to dismiss?  Register please.
					$show['dismiss_link'] = true;
				}
				else
				{
					$show['dismiss_link'] = false;
				}
				$notice_html = str_replace(
					array('{musername}', '{username}', '{userid}', '{sessionurl}', '{sessionurl_q}'),
					array($vbulletin->userinfo['musername'], $vbulletin->userinfo['username'], $vbulletin->userinfo['userid'], $vbulletin->session->vars['sessionurl'], $vbulletin->session->vars['sessionurl_q']),
					$vbphrase["notice_{$_noticeid}_html"]
				);

				($hook = vBulletinHook::fetch_hook('notices_noticebit')) ? eval($hook) : false;

				$templater = vB_Template::create('navbar_noticebit');
					$templater->register('notice_html', $notice_html);
					$templater->register('_noticeid', $_noticeid);
				$notices .= $templater->render();
			}
		}

		return $notices;
	}

	/**
	* Builds the applicable notification HTML and count
	*
	* @return	array	[bits] => HTML, [total] => formatted count
	*/
	protected function build_notifications()
	{
		global $vbulletin, $vbphrase, $show;

		if (!$vbulletin->userinfo['userid'])
		{
			return false;
		}

		$notifications = array();

		if ($vbulletin->options['enablepms']
			AND $vbulletin->userinfo['userid']
			AND
			(
				$vbulletin->userinfo['pmunread']
				OR
				(
					$vbulletin->userinfo['receivepm']
					AND
					$vbulletin->userinfo['permissions']['pmquota']
				)
 			)
			AND ($vbulletin->userinfo['permissions']['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])
		)
		{
			$notifications['pmunread'] = array(
				'phrase' => $vbphrase['unread_private_messages'],
				'link'   => 'private.php' . $vbulletin->session->vars['sessionurl_q'],
				'order'  => 10
			);
		}

		if (
			$vbulletin->userinfo['vm_enable']
				AND
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']
				AND
			$vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
		)
		{
			$notifications['vmunreadcount'] = array(
				'phrase' => $vbphrase['unread_profile_visitor_messages'],
				'link'   => fetch_seo_url('member', $vbulletin->userinfo, array('tab' => 'visitor_messaging')),
				'order'  => 20
			);

			if ($vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile'])
			{
				$notifications['vmmoderatedcount'] = array(
					'phrase' => $vbphrase['profile_visitor_messages_awaiting_approval'],
					'link'   => fetch_seo_url('member', $vbulletin->userinfo, array('tab' => 'visitor_messaging')),
					'order'  => 30
				);
			}
		}

		// check for incoming friend requests if user has permission to use the friends system
		if (($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends']) AND ($vbulletin->userinfo['permissions']['genericpermissions2'] & $vbulletin->bf_ugp_genericpermissions2['canusefriends']))
		{
			$notifications['friendreqcount'] = array(
				'phrase' => $vbphrase['incoming_friend_requests'],
				'link'   => 'profile.php?' . $vbulletin->session->vars['sessionurl'] . 'do=buddylist#irc',
				'order'  => 40
			);
		}

		// social group invitations and join requests
		if ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
		{
			// check for requests to join your own social groups, if user has permission to create groups
			if ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['cancreategroups'])
			{
				$notifications['socgroupreqcount'] = array(
					'phrase' => $vbphrase['requests_to_join_your_social_groups'],
					'link'   => fetch_seo_url('grouphome', array(), array('do' => 'requests')),
					'order'  => 50
				);
			}

			// check for invitations to join social groups, if user has permission to join groups
			if ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups'])
			{
				$notifications['socgroupinvitecount'] = array(
					'phrase' => $vbphrase['invitations_to_join_social_groups'],
					'link'   => fetch_seo_url('grouphome', array(), array('do' => 'invitations')),
					'order'  => 60
				);
			}
		}

		// picture comment notifications
		if ($vbulletin->options['pc_enabled']
			AND
			(
				(
				$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
					AND
				$vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canviewmembers']
					AND
				$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
					AND
				$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']
				)
				OR
				(
					$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
						AND
					$vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
				)
			)
		)
		{
			$notifications['pcunreadcount'] = array(
				'phrase' => $vbphrase['unread_picture_comments'],
				'link'   => 'album.php?' . $vbulletin->session->vars['sessionurl'] . 'do=unread',
				'order'  => 70
			);

			if ($vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment'])
			{
				$notifications['pcmoderatedcount'] = array(
					'phrase' => $vbphrase['picture_comments_awaiting_approval'],
					'link'   => 'album.php?' . $vbulletin->session->vars['sessionurl'] . 'do=moderated',
					'order'  => 80
				);
			}
		}

		if (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
			AND $vbulletin->options['socnet_groups_msg_enabled']
			AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
		)
		{
			$notifications['gmmoderatedcount'] = array(
				'phrase' => $vbphrase['group_messages_awaiting_approval'],
				'link'   => fetch_seo_url('grouphome', array(), array('do' => 'moderatedgms')),
				'order'  => 90
			);
		}

		($hook = vBulletinHook::fetch_hook('notifications_list')) ? eval($hook) : false;

		$notifications_order = array();
		foreach ($notifications AS $userfield => $notification)
		{
			$notifications_order["$notification[order]"]["$userfield"] = $userfield;
		}

		ksort($notifications_order);

		$notifications_total = 0;
		$notifications_menubits = '';

		foreach ($notifications_order AS $notification_order => $userfields)
		{
			ksort($notifications_order["$notification_order"]);

			foreach ($userfields AS $userfield)
			{
				$notification = $notifications["$userfield"];
				if (defined("VB_API") AND VB_API === true)
				{
					$notification['name'] = $userfield;
				}

				if ($vbulletin->userinfo["$userfield"] > 0)
				{
					$show['notifications'] = true;

					$notifications_total += $vbulletin->userinfo["$userfield"];
					$notification['total'] = vb_number_format($vbulletin->userinfo["$userfield"]);

					$templater = vB_Template::create('navbar_notifications_menubit');
					$templater->register('notification', $notification);
					$templater->register('notificationid', $userfield);

					$notifications_menubits .= $templater->render();
				}
			}
		}

		if (!$notifications_total)
		{
			return false;
		}

		return array(
			'bits'  => $notifications_menubits,
			'total' => vb_number_format($notifications_total)
		);
	}

	/**
	* Resolves the required templates for a particular action.
	*
	* @param	string	The action chosen
	* @param	array	Array of action-specific templates (for empty action, key 'none')
	* @param	array	List of global templates (always needed)
	*
	* @return	array	Array of required templates
	*/
	public static function fetch_required_template_list($action, $action_templates, $global_templates = array())
	{
		$action = (empty($action) ? 'none' : $action);

		if (!is_array($global_templates))
		{
			$global_templates = array();
		}

		if (!empty($action_templates["$action"]) AND is_array($action_templates["$action"]))
		{
			$global_templates = array_merge($global_templates, $action_templates["$action"]);
		}

		return $global_templates;
	}

	/**
	* Caches the generally required templates and the specifically requested templates.
	*
	* @param	string	Serialized array of template name => template id pairs
	*/
	protected function cache_templates($template_ids)
	{
		global $vbulletin, $show;

		$cache = is_array($this->cache_templates) ? $this->cache_templates : array();

		// Choose proper human verification template
		if ($vbulletin->options['hv_type'] AND in_array('humanverify', $cache))
		{
			$cache[] = 'humanverify_' . strtolower($vbulletin->options['hv_type']);
		}

		// templates to be included in every single page...
		$cache = array_merge($cache, array(
			// the really important ones
			'header',
			'footer',
			'headinclude',
			'headinclude_bottom',
			// ad location templates
			'ad_header_logo',
			'ad_header_end',
			'ad_navbar_below',
			'ad_footer_start',
			'ad_footer_end',
			'ad_global_header1',
			'ad_global_header2',
			'ad_global_below_navbar',
			'ad_global_above_footer',
			// new private message script
			'pm_popup_script',
			'memberaction_dropdown',
			// navbar construction
			'navbar',
			'navbar_link',
			'navbar_noticebit',
			'navbar_notifications_menubit',
			// forumjump and go button
			'forumjump',
			'forumjump_link',
			'forumjump_subforum',
			'gobutton',
			'option',
			// multi-page navigation
			'pagenav',
			'pagenav_curpage',
			'pagenav_pagelink',
			'pagenav_pagelinkrel',
			'threadbit_pagelink',
			// misc useful
			'spacer_open',
			'spacer_close',
			'STANDARD_ERROR',
			'STANDARD_REDIRECT',
			//'board_inactive_warning'
			// facebook templates
			'facebook_header',
			'facebook_footer',
			'facebook_opengraph'
		));

		// if we are in a message editing page then get the editor templates
		$show['editor_css'] = false;
		if (defined('GET_EDIT_TEMPLATES'))
		{
			$_get_edit_templates = explode(',', GET_EDIT_TEMPLATES);
			if (GET_EDIT_TEMPLATES === true OR in_array($_REQUEST['do'], $_get_edit_templates))
			{
				$cache = array_merge($cache, array(
					// ckeditor
					'editor_ckeditor',
					'editor_smilie',
					'editor_clientscript',
					'editor_smilie_category',
					'editor_smilie_row',
					// javascript menu builders
					'editor_jsoptions_font',
					'editor_jsoptions_size',
					// smiliebox templates
					'editor_smiliebox',
					// needed for thread preview
					'bbcode_code',
					'bbcode_html',
					'bbcode_php',
					'bbcode_quote',
					'bbcode_video',
					// misc often used
					'newpost_threadmanage',
					'newpost_disablesmiliesoption',
					'newpost_preview',
					'newpost_quote',
					'posticonbit',
					'posticons',
					'newpost_usernamecode',
					'newpost_errormessage',
					'forumrules'
				));

				$show['editor_css'] = true;
			}
		}

		($hook = vBulletinHook::fetch_hook('cache_templates')) ? eval($hook) : false;

		cache_templates($cache, $template_ids);
	}

	/**
	* Builds the collapse array based on a string representing collapse sections.
	*
	* @param	string	List of collapsed sections
	*
	* @return	array	Array with 3 values set for each collapsed section
	*/
	public static function build_vbcollapse($collapse_string)
	{
		$vbcollapse = array();
		if (!empty($collapse_string))
		{
			$val = preg_split('#\n#', $collapse_string, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($val AS $key)
			{
				$vbcollapse["collapseobj_$key"] = 'display:none;';
				$vbcollapse["collapseimg_$key"] = '_collapsed';
				$vbcollapse["collapsecel_$key"] = '_collapsed';
			}
			unset($val);
		}

		return $vbcollapse;
	}

	/**
	* Checks if there's a new PM and returns info about it if there is.
	*
	* @return	array|false	Information about the PM or false if there is no new PM
	*/
	protected function check_new_pm()
	{
		global $vbulletin;

		if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['pmpopup'] == 2 AND $vbulletin->options['checknewpm'] AND !defined('NOPMPOPUP'))
		{
			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($vbulletin->userinfo);
			$userdm->set('pmpopup', 1);
			$userdm->save(true, 'pmpopup');	// 'pmpopup' tells db_update to issue a shutdownquery of the same name
			unset($userdm);

			if (THIS_SCRIPT != 'private' AND THIS_SCRIPT != 'login')
			{
				$newpm = $vbulletin->db->query_first("
					SELECT pm.pmid, title, fromusername
					FROM " . TABLE_PREFIX . "pmtext AS pmtext
					LEFT JOIN " . TABLE_PREFIX . "pm AS pm USING(pmtextid)
					WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
						AND pm.folderid = 0
					ORDER BY dateline DESC
					LIMIT 1
				");
				$newpm['username'] = addslashes_js(unhtmlspecialchars($newpm['fromusername']), '"');
				$newpm['title'] = addslashes_js(unhtmlspecialchars($newpm['title']), '"');
				return $newpm;
			}
		}

		return false;
	}

	/**
	* Determines if the server is over the defined load limits
	*
	* @return	bool
	*/
	protected function server_overloaded()
	{
		global $vbulletin;

		if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN' AND $vbulletin->options['loadlimit'] > 0)
		{
			if (!is_array($vbulletin->loadcache) OR $vbulletin->loadcache['lastcheck'] < (TIMENOW - $vbulletin->options['recheckfrequency']))
			{
				update_loadavg();
			}

			if ($vbulletin->loadcache['loadavg'] > $vbulletin->options['loadlimit'])
			{
				return true;
			}
		}

		return false;
	}

	/**
	* Determines if the user has global viewing permissions. There are exceptions
	* for certain scripts (like login) and actions that will always return true.
	*
	* @return	bool
	*/
	protected function has_global_view_permission()
	{
		global $vbulletin;

		if (!($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			$allowed_scripts = array(
				'register',
				'login',
				'image',
				'sendmessage',
				'subscription',
				'ajax'
			);
			if (!in_array(THIS_SCRIPT, $allowed_scripts))
			{
				return false;
			}
			else
			{
				$_doArray = array('contactus', 'docontactus', 'register', 'signup', 'requestemail', 'emailcode', 'activate', 'login', 'logout', 'lostpw', 'emailpassword', 'addmember', 'coppaform', 'resetpassword', 'regcheck', 'checkdate', 'removesubscription', 'imagereg', 'verifyusername');
				if (THIS_SCRIPT == 'sendmessage' AND $_REQUEST['do'] == '')
				{
					$_REQUEST['do'] = 'contactus';
				}
				if (THIS_SCRIPT == 'register' AND $_REQUEST['do'] == '' AND $vbulletin->GPC['a'] == '')
				{
					$_REQUEST['do'] = 'register';
				}
				$_aArray = array('act', 'ver', 'pwd');
				if (!in_array($_REQUEST['do'], $_doArray) AND !in_array($vbulletin->GPC['a'], $_aArray))
				{
					return false;
				}
			}
		}

		return true;
	}

	public function force_styleid($styleid)
	{
		$this->force_styleid = $styleid;
	}
}

/**
* Bootstrapping for forum-specific actions.
*
* @package	vBulletin
*/
class vB_Bootstrap_Forum extends vB_Bootstrap
{
	/**
	* Reads some context based on general input information
	*/
	public function read_input_context()
	{
		global $vbulletin;

		parent::read_input_context();

		global $postinfo, $threadinfo, $foruminfo, $pollinfo;
		global $postid, $threadid, $forumid, $pollid;

		$vbulletin->input->clean_array_gpc('r', array(
			'postid'     => TYPE_UINT,
			'threadid'   => TYPE_UINT,
			'forumid'    => TYPE_INT,
			'pollid'     => TYPE_UINT,
		));

		$codestyleid = 0;

		// Init post/thread/forum values
		$postinfo = array();
		$threadinfo = array();
		$foruminfo = array();

		// automatically query $postinfo, $threadinfo & $foruminfo if $threadid exists
		if ($vbulletin->GPC['postid'] AND $postinfo = verify_id('post', $vbulletin->GPC['postid'], 0, 1))
		{
			$postid = $postinfo['postid'];
			$vbulletin->GPC['threadid'] = $postinfo['threadid'];
		}

		// automatically query $threadinfo & $foruminfo if $threadid exists
		if ($vbulletin->GPC['threadid'] AND $threadinfo = verify_id('thread', $vbulletin->GPC['threadid'], 0, 1))
		{
			$threadid = $threadinfo['threadid'];
			$vbulletin->GPC['forumid'] = $forumid = $threadinfo['forumid'];
			if ($forumid)
			{
				$foruminfo = fetch_foruminfo($threadinfo['forumid']);
				if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
				{
					$codestyleid = $foruminfo['styleid'];
				}
			}

			if ($vbulletin->GPC['pollid'])
			{
				$pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
				$pollid = $pollinfo['pollid'];
			}
		}
		// automatically query $foruminfo if $forumid exists
		else if ($vbulletin->GPC['forumid'])
		{
			$foruminfo = verify_id('forum', $vbulletin->GPC['forumid'], 0, 1);
			$forumid = $foruminfo['forumid'];

			if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
			{
				$codestyleid = $foruminfo['styleid'];
			}
		}
		// automatically query forum for style info if $pollid exists
		else if ($vbulletin->GPC['pollid'] AND THIS_SCRIPT == 'poll')
		{
			$pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
			$pollid = $pollinfo['pollid'];

			$threadinfo = fetch_threadinfo($pollinfo['threadid']);

			$threadid = $threadinfo['threadid'];

			$foruminfo = fetch_foruminfo($threadinfo['forumid']);
			$forumid = $foruminfo['forumid'];

			if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
			{
				$codestyleid = $foruminfo['styleid'];
			}
		}

		// #############################################################################
		// Redirect if this forum has a link
		// check if this forum is a link to an outside site
		if (!empty($foruminfo['link']) AND trim($foruminfo['link']) != '' AND (THIS_SCRIPT != 'subscription' OR $_REQUEST['do'] != 'removesubscription'))
		{
			// get permission to view forum
			$_permsgetter_ = 'forumdisplay';
			$forumperms = fetch_permissions($foruminfo['forumid']);
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				print_no_permission();
			}

			// add session hash to local links if necessary
			if (preg_match('#^([a-z0-9_]+\.php)(\?.*$)?#i', $foruminfo['link'], $match))
			{
				if ($match[2])
				{
					// we have a ?xyz part, put session url at beginning if necessary
					$query_string = preg_replace('/([^a-z0-9])(s|sessionhash)=[a-z0-9]{32}(&amp;|&)?/', '\\1', $match[2]);
					$foruminfo['link'] = $match[1] . '?' . $vbulletin->session->vars['sessionurl_js'] . substr($query_string, 1);
				}
				else
				{
					$foruminfo['link'] .= $vbulletin->session->vars['sessionurl_q'];
				}
			}

			exec_header_redirect($foruminfo['link'], 301);
		}

		$this->force_styleid = $codestyleid;
	}

	/**
	* Loads assorted show variables. Ideally, these would be used in templates,
	* but sometimes they're used within code.
	*/
	public function load_show_variables()
	{
		parent::load_show_variables();

		global $vbulletin, $show, $threadinfo, $foruminfo;


		$show['foruminfo'] = (
			THIS_SCRIPT == 'forumdisplay'
			AND $vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview']
		);

		if (THIS_SCRIPT == 'showthread' AND $threadinfo['threadid'])
		{
			if (!($vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canview'])
				OR
			(((!$threadinfo['visible'] AND !can_moderate($foruminfo['forumid'], 'canmoderateposts'))) OR ($threadinfo['isdeleted'] AND !can_moderate($foruminfo['forumid'])))
				OR
			(in_coventry($threadinfo['postuserid']) AND !can_moderate($foruminfo['forumid']))
				OR
			(!($vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
				OR
			(!($vbulletin->userinfo['forumpermissions']["$foruminfo[forumid]"] & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
				OR
				!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false))
			{
				$show['threadinfo'] = false;
			}
			else
			{
				$show['threadinfo'] = true;
			}
		}
		else
		{
			$show['threadinfo'] = false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26995 $
|| ####################################################################
\*======================================================================*/
