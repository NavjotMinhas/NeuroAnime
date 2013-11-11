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
* Determines whether we should redirect to registers page given the script context
* 	- query string parameter dofbredirect must be set to 1 to perform redirect
*
* @return	bool, true if we do not want to redirect to register page for this script
*/
function do_facebook_redirect()
{
	// first make sure we are not already registering, logging in or making an ajax request
	if (THIS_SCRIPT == 'register' OR THIS_SCRIPT == 'ajax')
	{
		return false;
	}

	// if we have already performed this check return cached value,
	// otherwise check for the proper query string param
	static $dofbredirect;
	if (!isset($dofbredirect))
	{
		global $vbulletin;
		$vbulletin->input->clean_array_gpc('r', array(
			'dofbredirect'	=> TYPE_BOOL
		));
		$dofbredirect = ($vbulletin->GPC_exists['dofbredirect'] AND $vbulletin->GPC['dofbredirect'] == 1);
	}

	// if we are performing facebook redirect, make sure auth token is still valid
	if ($dofbredirect)
	{
		$dofbredirect = vB_Facebook::instance()->isValidAuthToken();
	}

	return $dofbredirect;
}

/**
* Checks wheather the current user account is connected to a FB account
*
* @return	bool, true if vB account is connected to FB account
*/
function is_userfbconnected()
{
	global $vbulletin;
	return !empty($vbulletin->userinfo['fbuserid']);
}

/**
* Checks wheather the current session is active with Facebook
*
* @return	bool, true if session is active with Facebook
*/
function is_userfbactive()
{
	global $show;
	return !empty($show['facebookuser']);
}

/**
* Returns the canonical url pertinent to the content of the page
* 	- for open graph and like button
*
* @return	string, canonical url for the content
*/
function get_fbcanonicalurl()
{
	global $vbulletin, $og_array;
	static $fbcanonicalurl;

	if (empty($fbcanonicalurl))
	{
		if (THIS_SCRIPT == 'showthread')
		{
			global $threadinfo;
			$fbcanonicalurl = create_full_url(fetch_seo_url('thread|js|nosession', $threadinfo, null, null, null, true));
		}
		else if (THIS_SCRIPT == 'entry')
		{
			global $bloginfo;
			$fbcanonicalurl = create_full_url(fetch_seo_url('entry|js|nosession', $bloginfo, null, null, null, true));
		}
		else if (THIS_SCRIPT == 'vbcms' AND isset($vbulletin->vbcms['content_type']) AND $vbulletin->vbcms['content_type']=='Article')
		{
			$fbcanonicalurl = isset($vbulletin->vbcms['page_url']) ? $vbulletin->vbcms['page_url'] : $og_array['og:url'];
		}
		else
		{
			// do not cache canonical url in this case
			global $vbulletin;
			return $vbulletin->options['bburl'];
		}
	}

	return $fbcanonicalurl;
}

/**
* Returns an array representing open graph data
*
* @return	array, open graph data
*/
function get_fbopengrapharray()
{
	global $vbulletin, $show;

	// prepare open graph default data
	$show['fb_opengraph'] = true;
	$og_array = array(
		'fb:app_id'		=>	$vbulletin->options['facebookappid'],
		'og:site_name'	=>	$vbulletin->options['bbtitle'],
		'og:description'=>	$vbulletin->options['description'],
		'og:url'		=>	$vbulletin->options['bburl'],
		'og:type'		=>	'website'
	);

	// use the feed image if there is one
	if (!empty($vbulletin->options['facebookfeedimageurl']))
	{
		$og_array['og:image'] = $vbulletin->options['facebookfeedimageurl'];
	}

	// now we prepare content sensitive open graph data
	if (THIS_SCRIPT == 'showthread'
		OR (THIS_SCRIPT == 'entry')
		OR (THIS_SCRIPT == 'vbcms' AND isset($vbulletin->vbcms['content_type']) AND $vbulletin->vbcms['content_type'] == 'Article')
	)
	{
		$og_array['og:type'] = 'article';
		$og_array['og:url'] = get_fbcanonicalurl();
	}

	return $og_array;
}

/**
* Returns the users profile URL
*
* @return	string, url of the current users profile
*/
function get_fbprofileurl()
{
	if ($fbuserid = vB_Facebook::instance()->getLoggedInFbUserId())
	{
		return "http://www.facebook.com/profile.php?id=$fbuserid";
	}
	else
	{
		return false;
	}
}


/**
* Returns the users profile pic URL
*
* @return	string, url of the current users profile
*/
function get_fbprofilepicurl()
{
	global $vbulletin;
	static $picurl;

	// attempt to pull profile pic from userinfo, cookie, or use unknown.gif, in that order
	if (empty($picurl))
	{
		$picurl = !empty($vbulletin->userinfo['fbuserid']) ? (Facebook::$DOMAIN_MAP['graph'].$vbulletin->userinfo['fbuserid'].'/picture') : htmlspecialchars_uni($vbulletin->input->clean_gpc('c', COOKIE_PREFIX . 'fbprofilepicurl', TYPE_STR));
		$picurl = !empty($picurl) ? $picurl : vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/unknown.gif';
	}

	return $picurl;
}

/**
 * Verify that the user granted email permission
 *
 * @return bool, true if user granted email permission
 */
function check_emailpermissions()
{
	$info = vB_Facebook::instance()->getFbUserInfo();
	return !empty($info['email']);
}


/**
* Saves fb data into a user data manager
*
* @param	vB_DataManager_User, the datamanager to save the fb form info into
* @param	bool	True if we don't process userfields
*/
function save_fbdata($userdata, $skip_userfields = true)
{
	global $vbulletin;

	// save the data from the import form
	save_fbimportform_into_userdm($userdata, $skip_userfields);

	// save the facebook usergroup
	save_fbusergroup($userdata);
}

/**
* Saves an auto registered Facebook user
*
* @param	vB_DataManager_User, the datamanager to set the fields into
*
* @return	bool	true if saved worked, false otherwise
*/
function save_fbautoregister($userdata)
{
	global $vbulletin;

	$info = vB_Facebook::instance()->getFbUserInfo();

	//make sure we have all the fields we need for auto reg
	if (empty($info['uid'])
		OR empty($info['name'])
		OR empty($info['email']))
	{
		$userdata->error('not_enough_facebook_userinfo');
		return;
	}

	// make sure facebook account is not already associated with a vb account
	if (vB_Facebook::instance()->getVbUseridFromFbUserid())
	{
		$userdata->error('facebook_account_already_registered');
		return;
	}

	// set misc user data
	$userdata->set('email', $info['email']);
	$userdata->set('username', $info['name']);
	$userdata->set('password', time().'@facebook');
	$userdata->set('usergroupid', ($vbulletin->options['moderatenewmembers'] ? 4 : 2));
	$userdata->set('languageid', $vbulletin->userinfo['languageid']);
	$userdata->set_usertitle('', false, $vbulletin->usergroupcache["$newusergroupid"], false, false);
	$userdata->set('timezoneoffset', $info['timezone']);
	$userdata->set('ipaddress', IPADDRESS);
	$userdata->set('fbuserid', $info['uid']);
	$userdata->set('fbname', $info['name']);
	$userdata->set('fbjoindate', time());
	$userdata->set('logintype', 'fb');

	// save the facebook usergroup
	save_fbusergroup($userdata);

	// NOTE: Disabled for now, we will put this back when we have time to revisit
	// uploading facebook avatar for guests
	//save_fbavatar($userdata, $info['pic_big'], true, $info['pic']);
}

/**
 * Builds an array of information facebook we can use to populate vb user profile fields
 *
 * @param array, profile information from facebook
 * @return mixed, the biography blurb to user for vb profile, or false if there is none
 */
function get_vbprofileinfo()
{
	global $show;

	// the array we are going to return, populated with FB data
	$profilefields = array(
		'fbuserid'           => '',
		'fbname'             => '',
		'biography'          => '',
		'location'           => '',
		'interests'          => '',
		'occupation'         => '',
		'homepageurl'        => '',
		'birthday'           => '',
		'avatarurl'          => '',
		'fallback_avatarurl' => '',
		'timezone'           => '',
	);

	// grab fb account information
	$fb_info = vB_Facebook::instance()->getFbUserInfo();

	// interests
	$profilefields['interests'] .= (!empty($fb_info['interests']) ? $fb_info['interests'] . ' ' : '');
	$profilefields['interests'] .= (!empty($fb_info['activities']) ? $fb_info['activities'] . ' ' : '');
	$profilefields['interests'] .= (!empty($fb_info['books']) ? $fb_info['books'] . ' ' : '');
	$profilefields['interests'] .= (!empty($fb_info['movies']) ? $fb_info['movies'] . ' ' : '');
	$profilefields['interests'] .= (!empty($fb_info['music']) ? $fb_info['music'] . ' ' : '');
	$profilefields['interests'] .= (!empty($fb_info['quotes']) ? $fb_info['quotes'] . ' ' : '');

	// occupation
	if (isset($fb_info['work_history']) AND isset($fb_info['work_history'][0]))
	{
		$occupation = array();
		if (isset($fb_info['work_history'][0]['position']) AND !empty($fb_info['work_history'][0]['position']))
		{
			$occupation[] = $fb_info['work_history'][0]['position'];
		}
		if (isset($fb_info['work_history'][0]['description']) AND !empty($fb_info['work_history'][0]['description']))
		{
			$occupation[] = $fb_info['work_history'][0]['description'];
		}
		if (!empty($occupation))
		{
			$profilefields['occupation'] = implode(', ', $occupation);
		}
	}

	// location
	if (isset($fb_info['current_location']))
	{
		$location = array();
		if (isset($fb_info['current_location']['name']) AND !empty($fb_info['current_location']['name']))
		{
			$location[] = $fb_info['current_location']['name'];
		}
		if (isset($fb_info['current_location']['country']) AND !empty($fb_info['current_location']['country']))
		{
			$location[] = $fb_info['current_location']['country'];
		}
		if (!empty($location))
		{
			$profilefields['location'] = implode(', ', $location);
		}
	}

	$profilefields['biography'] .= (!empty($fb_info['about_me']) ? $fb_info['about_me'] : '');
	$profilefields['homepageurl'] .= (!empty($fb_info['website']) ? $fb_info['website'] : '');
	$profilefields['birthday'] .= (!empty($fb_info['birthday_date']) ? $fb_info['birthday_date'] : '');
	$profilefields['timezone'] .= (!empty($fb_info['timezone']) ? $fb_info['timezone'] : '');

	$profilefields['fbuserid'] .= (!empty($fb_info['uid']) ? $fb_info['uid'] : '');
	$profilefields['fbname'] .= (!empty($fb_info['name']) ? $fb_info['name'] : '');
	$profilefields['avatarurl'] .= (!empty($fb_info['pic_big']) ? $fb_info['pic_big'] : '');
	$profilefields['fallback_avatarurl'] .= (!empty($fb_info['pic']) ? $fb_info['pic'] : '');

	return $profilefields;
}

/**
* Builds the form pieces for importing information from facebook conbect
*
* @param	string	the context for this form, gets appeneded to the template name so we use the right template for context
* @param	array	fields that are already on the registration form and should be skipped.
*
* @return	string	renedered template for this form
*/
function construct_fbimportform($context = 'register', $skip_fields = array())
{
	global $vbulletin, $vbphrase, $show;

	// get some basic account information from facebook
	$profileinfo = get_vbprofileinfo();

	// prepare birthday
	if (!empty($profileinfo['birthday']))
	{
		list($bd_month, $bd_day, $bd_year) = explode('/', $profileinfo['birthday']);
		$dayselected = array($bd_day => 'selected="selected"');
		$monthselected = array($bd_month => 'selected="selected"');
	}

	// set up variables for each profile field
	$show['fb_additionaloptions'] = false;
	$query_profilefield_ids = array();
	$profilefieldinfo = array();
	foreach ($profileinfo as $field => $value)
	{
		if (in_array($field, $skip_fields))
		{
			continue;
		}

		if (!empty($value) AND $vbulletin->options['fb_userfield_' . $field])
		{
			$show['fb_additionaloptions'] = true;
			$show['fb_' . $field] = true;

			$field_varname = $vbulletin->options['fb_userfield_' . $field];
			$profilefieldinfo[$field]['title_phrase'] = $vbphrase[$field_varname . '_title'];
			$id = intval(substr($field_varname, strlen('field')));
			$query_profilefield_ids[$id] = $field;
		}
	}

	// get max length for each profile field
	if (!empty($query_profilefield_ids))
	{
		$maxlengthresult = $vbulletin->db->query_read("
			SELECT profilefieldid, maxlength
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid IN(" . implode(',', array_keys($query_profilefield_ids)) . ")
		");
		while ($maxlengthinfo = $vbulletin->db->fetch_array($maxlengthresult))
		{
			$field = $query_profilefield_ids[$maxlengthinfo['profilefieldid']];
			$profilefieldinfo[$field]['maxlength'] = intval($maxlengthinfo['maxlength']);
		}
	}

	// only allow uploading of avatar for registered users, not new users
	$show['avatarupload'] = !empty($vbulletin->userinfo['userid']);

	// if we are showing avatar upload, we need to make sure we are displaying additional options
	$show['fb_additionaloptions'] = ($show['fb_additionaloptions'] OR $show['avatarupload']);

	$templater = vB_Template::create('facebook_import' . $context);
	$templater->register('profileinfo', $profileinfo);
	$templater->register('profilefieldinfo', $profilefieldinfo);
	$templater->register('dayselected', $dayselected);
	$templater->register('monthselected', $monthselected);
	$templater->register('bd_year', $bd_year);

	return $templater->render();
}

/**
* Builds the form input for verifying if a user wants to publish to facebook
*
* @return	string	renedered template for this form
*/
function construct_fbpublishcheckbox()
{
	global $show, $vbulletin;

	// decide whether the checkbox applies to the current user and current form based on settings
	if (is_userfbconnected() AND (
		((THIS_SCRIPT == 'showthread' OR THIS_SCRIPT == 'newreply') AND $vbulletin->options['fbfeedpostreply'])
		OR ((THIS_SCRIPT == 'newthread') AND $vbulletin->options['fbfeednewthread'])
		OR ((THIS_SCRIPT == 'blog_post') AND ($vbulletin->options['fbfeedblogentry'] OR $vbulletin->options['fbfeedblogcomment']))
		OR ((THIS_SCRIPT == 'entry') AND $vbulletin->options['fbfeedblogcomment'])
		OR ((THIS_SCRIPT == 'vbcms') AND $vbulletin->options['fbfeedarticlecomment'])
	))
	{
		$show['fb_publishcheckbox'] = true;
		// generate and return the template
		$templater = vB_Template::create('facebook_publishcheckbox');
		$templater->register('checked', check_fbpublishcheckbox());
		return $templater->render();
	}
	// if checkbox does not apply, return an empty string
	else
	{
		$show['fb_publishcheckbox'] = false;
		return '';
	}
}

/**
* Builds the Like button
*
* @return	string	renedered template for the Like button
*/
function construct_fblikebutton()
{
	global $show, $vbulletin;

	// make sure like button is enabled for the given page
	if ((THIS_SCRIPT == 'showthread' AND $vbulletin->options['facebooklikethreads'])
		OR (THIS_SCRIPT == 'entry' AND $vbulletin->options['facebooklikeblogentries'])
		OR (THIS_SCRIPT == 'vbcms' AND isset($vbulletin->vbcms['content_type']) AND $vbulletin->vbcms['content_type']=='Article' AND $vbulletin->options['facebooklikecmsarticles'])
	)
	{
		$show['fb_likebutton'] = true;
		$templater = vB_Template::create('facebook_likebutton');
		$templater->register('appid', urlencode($vbulletin->options['facebookappid']));

		if(is_browser('ie') || is_browser('opera')) {
		 $templater->register('href', urlencode(get_fbcanonicalurl()));
		}
		else {
		 $templater->register('href', get_fbcanonicalurl());
		}
		return $templater->render();
	}

	// if like button is not enabled, return null data
	else
	{
		$show['fb_likebutton'] = false;
		return '';
	}
}

/**
* Builds the form input for verifying if a user wants to publish to facebook
*
* @return	bool	true if the user checked the publish checkbox
*/
function check_fbpublishcheckbox()
{
	global $vbulletin;
	$vbulletin->input->clean_array_gpc('p', array(
		'fb_dopublish' => TYPE_BOOL
	));
	return !empty($vbulletin->GPC['fb_dopublish']);
}

/**
* Puts data from the facebook import form into the user datamanager
*
* @param	vB_DataManager_User, the datamanager to save the fb form info into
* @param	bool	True if we don't process userfields
*/
function save_fbimportform_into_userdm($userdata, $skip_userfields = true)
{
	global $vbulletin;

	$vbulletin->input->clean_array_gpc('p', array(
		'fbuserid'    => TYPE_STR,
		'fbname'      => TYPE_STR,
		'fboptions'   => TYPE_ARRAY,
		'avatarurl'   => TYPE_STR,
		'userfield'   => TYPE_ARRAY,
		'homepageurl' => TYPE_STR,
		'fbday'       => TYPE_INT,
		'fbmonth'     => TYPE_INT,
		'fbyear'      => TYPE_INT,
	));

	// make sure the current facebook userid matches the one when the form was generated
	if ($vbulletin->GPC['fbuserid'] != vB_Facebook::instance()->getLoggedInFbUserId())
	{
		$userdata->error('facebookuseridmismatch');
	}

	// make sure facebook account is not already associated with a vb account
	else if (vB_Facebook::instance()->getVbUseridFromFbUserid($vbulletin->GPC['fbuserid']))
	{
		$userdata->error('facebook_account_already_registered');
	}

	// passed validation, now we save the data
	else
	{
		$userdata->set('fbuserid', $vbulletin->GPC['fbuserid']);
		$userdata->set('fbname', $vbulletin->GPC['fbname']);
		$userdata->set('fbjoindate', time());

		$fboptions = $vbulletin->GPC['fboptions'];

		// unset any custom profile fields that were not checked
		$fields = array('biography', 'location', 'interests', 'occupation');
		foreach ($fields AS $field)
		{
			if (empty($fboptions["use$field"]) AND !$fboptions["skip$field"])
			{
				unset($vbulletin->GPC['userfield'][$vbulletin->options["fb_userfield_$field"]]);
			}
		}

		// set custom profile fields
		if (!$skip_userfields)
		{
			$userdata->set_userfields($vbulletin->GPC['userfield'], true, 'normal', true);
		}

		// now save any additional data to user profile from facebook, like avatar
		if (!empty($fboptions['useavatar']) AND !empty($vbulletin->GPC['avatarurl']))
		{
			save_fbavatar($userdata, $vbulletin->GPC['avatarurl']);
		}

		// homepage
		if (!empty($fboptions['usehomepageurl']) AND !empty($vbulletin->GPC['homepageurl']))
		{
			$userdata->set('homepage', $vbulletin->GPC['homepageurl']);
		}

		// birthday
		if (!empty($fboptions['usebirthday']))
		{
			$userdata->set('birthday', array(
				'day'   => $vbulletin->GPC['fbday'],
				'month' => $vbulletin->GPC['fbmonth'],
				'year'  => $vbulletin->GPC['fbyear']
			));
		}
	}
}


/**
* Saves the facebook avatar specified from facebook url
*
* @param	vB_DataManager_User, the datamanager to put any upload errors into
* @param	string,	the url to retrieve the avatar from
* @param	bool, flag denoting if we want to try a different URL if this one fails
* @param	string,	the url to retrieve the avatar from if the first one fails
*
* @return	bool	true if saved worked, false otherwise
*/
function save_fbavatar($userdata, $avatarurl = '', $do_fallback = true, $fallback_avatarurl = '')
{
	global $vbulletin;

	// if we are not passed an avatar url, grab it from fb api
	if (empty($avatarurl))
	{
		$pf = get_vbprofileinfo();
		$avatarurl = $pf['avatarurl'];
	}

	// begin custom avatar code
	require_once(DIR . '/includes/class_upload.php');
	require_once(DIR . '/includes/class_image.php');

	// grab permissions info from logged in user, if user not logged in, use permissions from registered usergroup
	$usergroup_info = !empty($vbulletin->userinfo['userid']) ? $vbulletin->userinfo['permissions'] : $vbulletin->usergroupcache[2];

	// if user does not have permission to user custom avatar, skip this step
	if (!($usergroup_info['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']))
	{
		return;
	}

	// initialize the uploader and populate with the avatar permissions
	$upload = new vB_Upload_Userpic($vbulletin);
	$upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
	$upload->image =& vB_Image::fetch_library($vbulletin);
	$upload->maxwidth = $usergroup_info['avatarmaxwidth'];
	$upload->maxheight = $usergroup_info['avatarmaxheight'];
	$upload->maxuploadsize = $usergroup_info['avatarmaxsize'];
	$upload->allowanimation = ($usergroup_info['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cananimateavatar']) ? true : false;

	// upload and validate
	if (!$upload->process_upload($avatarurl))
	{
		// check if we want to try a fallback url
		if ($do_fallback)
		{
			// if we are not passed a fallback url, grab smaller pic from FB api
			if (empty($fallback_avatarurl))
			{
				$pf = get_vbprofileinfo();
				$fallback_avatarurl = $pf['fallback_avatarurl'];
			}

			// do this again, but don't use a fallback if that one fails
			return save_fbavatar($userdata, $fallback_avatarurl, false);
		}

		// not doing a fallback, so add to the errors and return false
		else
		{
			$userdata->error($upload->fetch_error());
			return false;
		}
	}

	// if we get here, there were no errors, so return true
	return true;
}

/**
* Saves fb usergroup into the datamanager
*
* @param	vB_DataManager_User, the datamanager to save the fb form info into
*/
function save_fbusergroup($userdata)
{
	global $vbulletin;

	// save additional fb usergroup if specified, making sure it is not already the primary usergroup
	if ($vbulletin->options['facebookusergroupid'] > 0 AND $vbulletin->options['facebookusergroupid'] != $userdata->fetch_field('usergroupid'))
	{
		$membergroupids = fetch_membergroupids_array($vbulletin->userinfo, false);
		$membergroupids[] = $vbulletin->options['facebookusergroupid'];
		$userdata->set('membergroupids', array_unique($membergroupids));
	}
}

/**
* Publishes a message to users feed, given a phrase as the message
*
* @param	the name of the phrase to publish in the message
* @param	the title of the new thread
* @param	the body of the first post in the thread
* @param	the url to the first post in the thread
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook($phrasename, $title, $body, $link)
{
	global $vbphrase, $vbulletin;

	// check if  Facebook user is active and user wants to publish
	if (is_userfbconnected() AND is_userfbactive() AND check_fbpublishcheckbox())
	{
		// get the preview text for the body
		$body = fetch_trimmed_title(strip_bbcode($body, true, false, true, true), 300);

		// if phrasename is not in the phrase array, simply use phrasename as the message
		$message = isset($vbphrase["$phrasename"]) ? construct_phrase($vbphrase["$phrasename"], $vbulletin->options['bbtitle']): "$phrasename";
		$encoding = strtolower(vB_Template_Runtime::fetchStyleVar('charset'));
		if ($encoding != 'utf-8')
		{
			if (function_exists('iconv'))
			{
				$message = @iconv($encoding, 'UTF-8//TRANSLIT', $message);
				$title = @iconv($encoding, 'UTF-8//TRANSLIT', $title);
				$body = @iconv($encoding, 'UTF-8//TRANSLIT', $body);
			}
			elseif (function_exists('mb_convert_encoding'))
			{
				$message = @mb_convert_encoding($message, 'UTF-8', $encoding);
				$title = @mb_convert_encoding($title, 'UTF-8', $encoding);
				$body = @mb_convert_encoding($body, 'UTF-8', $encoding);
			}
			else
			{
				$message = utf8_encode($message);
				$title = utf8_encode($title);
				$body = utf8_encode($body);
			}
		}
		return vB_Facebook::instance()->publishFeed($message, $title, $link, $body);
	}

	// if we failed the check, return false
	else
	{
		return false;
	}
}

/**
* Attempts to punlish a new blog post to a user's FB newsfeed
*
* @param	the title of the new blog
* @param	the body of the blog
* @param	the url to the first post in the thread
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook_blogentry($title, $body, $link)
{
	global $vbulletin;
	// make sure option is enabled
	if ($vbulletin->options['fbfeedblogentry'])
	{
		return publishtofacebook('fbpublish_message_blogentry', $title, $body, $link);
	}
	else
	{
		return false;
	}
}

/**
* Attempts to punlish a blog comment to a user's FB newsfeed
*
* @param	the title of the new blog
* @param	the body of the blog
* @param	the url to the first post in the thread
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook_blogcomment($title, $body, $link)
{
	global $vbulletin;
	// make sure option is enabled
	if ($vbulletin->options['fbfeedblogcomment'])
	{
		return publishtofacebook('fbpublish_message_blogcomment', $title, $body, $link);
	}
	else
	{
		return false;
	}
}

/**
* Attempts to punlish a thread to a user's FB newsfeed
*
* @param	the title of the new thread
* @param	the body of the first post in the thread
* @param	the url to the first post in the thread
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook_newthread($title, $body, $link)
{
	global $vbulletin;
	// make sure option is enabled
	if ($vbulletin->options['fbfeednewthread'])
	{
		return publishtofacebook('fbpublish_message_newthread', $title, $body, $link);
	}
	else
	{
		return false;
	}
}

/**
* Attempts to punlish a post to a user's FB newsfeed
*
* @param	the title of the new thread
* @param	the body of the first post in the thread
* @param	the url to the first post in the thread
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook_newreply($title, $body, $link)
{
	global $vbulletin;
	// make sure option is enabled
	if ($vbulletin->options['fbfeedpostreply'])
	{
		return publishtofacebook('fbpublish_message_newreply', $title, $body, $link);
	}
	else
	{
		return false;
	}
}

/**
* Attempts to publish a new article to a user's newsfeed
*
* @param	the message to send to FB, different signature because phrasing is handled differently in CMS
* @param	the title of the article
* @param	the body of the article
* @param	the url to the article
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook_newarticle($message, $title, $body, $link)
{
	global $vbulletin;
	// make sure option is enabled
	if ($vbulletin->options['fbfeednewarticle'])
	{
		return publishtofacebook($message, $title, $body, $link);
	}
	else
	{
		return false;
	}
}

/**
* Attempts to publish a article comment to a user's newsfeed
*
* @param	the title of the article
* @param	the body of the first post in the thread
* @param	the url to the first post in the thread
*
* @return	bool	true if the publish worked, false otherwise
*/
function publishtofacebook_articlecomment($title, $body, $link)
{
	global $vbulletin;
	// make sure option is enabled
	if ($vbulletin->options['fbfeedarticlecomment'])
	{
		return publishtofacebook('fbpublish_message_articlecomment', $title, $body, $link);
	}
	else
	{
		return false;
	}
}

/**
* Logs the user out of Facebook Connect, but not out of Facebook.com
*/
function do_facebooklogout()
{
	global $show;
	$show['facebookuser'] = false;
	vB_Facebook::instance()->doLogoutFbUser();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 46027 $
|| ####################################################################
\*======================================================================*/
