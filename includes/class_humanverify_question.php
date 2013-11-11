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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Human Verification class for Question & Answer Verification
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_HumanVerify_Question extends vB_HumanVerify_Abstract
{
	/**
	* Constructor
	*
	* @return	void
	*/
	function vB_HumanVerify_Question(&$registry)
	{
		parent::vB_HumanVerify_Abstract($registry);
	}

	/**
	 * Returns the HTML to be displayed to the user for Human Verification
	 *
	 * @param	string	Passed to template
	 *
	 * @return 	string	HTML to output
	 *
	 */
	function output_token($var_prefix = 'humanverify')
	{
		global $vbphrase, $show;
		$vbulletin =& $this->registry;

		$humanverify = $this->generate_token();

		require_once(DIR . '/includes/functions_misc.php');
		$humanverify['question'] = fetch_phrase('question' . $humanverify['answer'], 'hvquestion', '', false, true, $this->registry->userinfo['languageid'], false);

		$templater = vB_Template::create('humanverify_question');
			$templater->register('humanverify', $humanverify);
			$templater->register('var_prefix', $var_prefix);
		$output = $templater->render();

		return $output;
	}

	/**
	 * Fetches a random question ID from the database
	 *
	 * @return	integer
	 *
	 */
	function fetch_answer()
	{
		$question = $this->registry->db->query_first_slave("
			SELECT hvquestion.questionid, COUNT(*) AS answers
			FROM " . TABLE_PREFIX . "hvquestion AS hvquestion
			LEFT JOIN " . TABLE_PREFIX . "hvanswer AS hvanswer
				ON (hvquestion.questionid = hvanswer.questionid)
			WHERE hvanswer.answerid IS NOT NULL
				OR hvquestion.regex <> ''
			GROUP BY hvquestion.questionid
			ORDER BY RAND()
			LIMIT 1
		");

		return $question['questionid'];
	}

	/**
	* Verify is supplied token/reponse is valid
	*
	*	@param	array	Values given by user 'input' and 'hash'
	*
	* @return	bool
	*/
	function verify_token($input)
	{
		$input['input'] = trim($input['input']);
		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "humanverify
			SET viewed = 1
			WHERE hash = '" . $this->registry->db->escape_string($input['hash']) . "'
				AND viewed = 0
		");
		if ($this->registry->db->affected_rows())
		{
			// Hitting the master since we just updated this value
			$question = $this->registry->db->query_first("
				SELECT question.questionid, question.regex
				FROM " . TABLE_PREFIX . "humanverify AS hv
				LEFT JOIN " . TABLE_PREFIX . "hvquestion AS question ON (hv.answer = question.questionid)
				WHERE hash = '" . $this->registry->db->escape_string($input['hash']) . "'
					AND viewed = 1
			");

			// Remove token since we no longer need it.
			$this->delete_token($input['hash']);

			if (!$question)
			{
				// this happens if the hash gets killed somewhere between the update and select
				$this->error = 'humanverify_question_wronganswer';
				return false;
			}
			else if (!$question['questionid'])
			{
				// this happens if no question was available, so we need to just accept their answer
				// otherwise it'd be impossible to get past
				return true;
			}
			else
			{	// Check answer!
				if ($question['regex'] AND preg_match('#' . str_replace('#', '\#', $question['regex']) . '#siU', $input['input']))
				{
					return true;
				}
				else if ($this->registry->db->query_first("
						SELECT answerid
						FROM " . TABLE_PREFIX . "hvanswer
						WHERE questionid = " . intval($question['questionid']) . " AND
							answer = '" . $this->registry->db->escape_string($input['input']) . "'
				"))
				{
					return true;
				}
				else
				{
					$this->error = 'humanverify_question_wronganswer';
					return false;
				}
			}
		}
		else
		{
			$this->delete_token($input['hash'], NULL, 0);
			$this->error = 'humanverify_question_wronganswer';
			return false;
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
