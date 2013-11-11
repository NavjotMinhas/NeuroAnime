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
* Client for Vertical Response© communication
*
* @package 		vBulletin
* @version		$Revision: 41550 $
* @date 		$Date: 2011-01-27 14:24:50 -0800 (Thu, 27 Jan 2011) $
*
*/
class vB_VerticalResponse
{
	//location of the
	protected $wsdl = "https://api.verticalresponse.com/wsdl/1.0/VRAPI.wsdl"; //location of the wsdl;
	protected $vr = false;
	protected $sid = false;

	/* Logs into Vertical Response
	*
	*		@param	string
   *		@param	string
   *
   *		@return	bool
	*/
	public function login($username, $password)
	{
		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}

		try
		{
			$this->sid = $this->vr->login( array(
				'username'                 => $username,
				'password'                 => $password,
				'session_duration_minutes' => 15
			) );
			return $this->sid;
		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}
	}

	/* lists all active Vertical Response email lists for this sub-account
   *
   *		@param	string
   *
   *		@return	bool
	*/
	public function enumerateLists($sessionid)
	{
		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}
		try
		{
			$lists = $this->vr->enumerateLists( array(
				'session_id'         => $sessionid,
				'type'               => 'email',
				'include_field_info' => false,
				'order_by'           => 'name',
				'limit'              => 500) );

			return $lists;
		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}
	}

	/* sets the two correct custom fields for a list- username and userid
   *
   *		@param	string
   *		@param	string
   *
   *		@return	bool
	*/
	public function setCustomListFields($sessionid, $listid)
	{
		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}
		try
		{
			$lists = $this->vr->enumerateLists( array(
				'session_id'         => $sessionid,
				'type'               => 'email',
			'list_id'    => $listid));

			if (empty($lists))
			{
				return false;
			}
			return $this->vr->setCustomListFields(array(
				'session_id' => $sessionid,
				'list_id'    => $listid,
				'fields'     => array('userid', 'username')));

		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}
	}

	/* lists all active Vertical Response email lists for this sub-account
	   *
	   *		@param	string
	   *
	   *		@return	bool
	*/
	public function checkStatus($sessionid)
	{
		if (!$sessionid)
		{
			return false;
		}

		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}
		try
		{
			return $this->vr->refresh( array(
			'session_id' => $sessionid,
			) );

		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}
	}

	/* Creates a new VR email list
   *
   *		@param	string
   *		@param	string
   *
   *		@return	bool
	*/
	public function createList($sessionid, $list_name)
	{
		if (!$sessionid OR empty($list_name))
		{
			return false;
		}

		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}

		try
		{
			//check to see if it exists.
			$list = $this->vr->enumerateLists( array(
				'session_id'         => $sessionid,
				'name'           => $list_name,
				'limit'              => 500) );
			if (count($list))
			{
				if ($list->type == 'email')
				{
					return $list->listid;
				}
				//it exists but is the wrong type.
				return -1;
			}

			$listid = $this->vr->createList( array (
			'session_id' => $sessionid,
			'name' => $list_name,
			'type' => 'email',
			'custom_field_names' => array('userid', 'username'),
			) );
		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}


		return $listid;
	}

	/* Adds a block of members to a VR email list
   *
   *		@param	string
   *		@param	integer
   *		@param	string
   *
   *		@return	bool
	*/
	public function addListMembers($sessionid, $listid, $members, $fields)
	{
		if (!$sessionid OR !$listid OR empty($members))
		{
			return false;
		}

		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}

		try
		{
			$result = $this->vr->appendFileToList( array(
	        'session_id' => $sessionid,
	        'list_id'    => $listid,
	        'file'       => array(
	            'filename'  => 'vb_upload_tmp',
	            'delimiter' => 'csv',
	            'contents'  => base64_encode($members),
	        ),
	        'fields' => $fields,
	    	) );
			return true;
		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}


	}

	/* Removes all members from a Vertical Response email list
	   *
	   *		@param	string
	   *		@param	mixed	array of email addresses.
	   *
	   *		@return	bool
	*/

	/* Adds a block of members to a VR email list
	   *
	   *		@param	string
	   *		@param	integer
	   *		@param	string
	   *
	   *		@return	bool
	*/
	public function addListMember($sessionid, $listid, $member)
	{
		if (!$sessionid OR !$listid)
		{
			return false;
		}

		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}

		try
		{
			//compose the member info
			$member_data = array();
			foreach ($member as $field => $value)
			{
				$member_data[] = array(
				'name' => $field,
				'value' =>$value);
			}
			$result = $this->vr->addListMember( array(
				'session_id' => $sessionid,
				'list_member' => array(
				'list_id'    => $listid,
				'member_data' => $member_data)
		    	) );

			return true;
		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}


	}

	/* Removes all members from a Vertical Response email list
	   *
	   *		@param	string
	   *		@param	mixed	array of email addresses.
	   *
	   *		@return	bool
	*/


	public function eraseListMembers($sessionid, $listid)
	{
		if (!$sessionid OR !$listid)
		{
			return false;
		}

		if (!$this->vr)
		{
			$this->vr = new SoapClient($this->wsdl);
		}

		try
		{
			$this->vr->eraseListMembers( array(
			'session_id' => $sessionid,
			'list_id'    => $listid,
		) );
		}
		catch(exception $e)
		{
			//Probably an expired login
			return false;
		}
	}

}