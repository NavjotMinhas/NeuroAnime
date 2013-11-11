<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is ©2000-2011 vBulletin Solutions Inc. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/liceNse.html # ||
|| #################################################################### ||
\*======================================================================*/

define('MAIL_INCLUDED', true);

// Attempt to load XML extension if we don't have the XML functions
// already loaded. It is needed for utf8_encode()
if (!function_exists('xml_set_element_handler'))
{
	$extension_dir = ini_get('extension_dir');
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
	{
		$extension_file = 'php_xml.dll';
	}
	else
	{
		$extension_file = 'xml.so';
	}
	if ($extension_dir AND file_exists($extension_dir . '/' . $extension_file))
	{
		ini_set('display_errors', true);
		dl($extension_file);
	}
}

/**
* Standard Mail Sending Object
*
* This class sends email from vBulletin using the PHP mail() function
*
* @package 		vBulletin
* @version		$Revision: 44840 $
* @date 		$Date: 2011-06-21 13:03:13 -0700 (Tue, 21 Jun 2011) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Mail
{
	/**
	* Destination address
	*
	* @var	string
	*/
	var $toemail = '';

	/**
	* Subject
	*
	* @var	string
	*/
	var $subject = '';

	/**
	* Message
	*
	* @var	string
	*/
	var $message = '';

	/**
	* All headers to be sent with the message
	*
	* @var	string
	*/
	var $headers = '';

	/**
	* Sender email
	*
	* @var	string
	*/
	var $fromemail = '';

	/**
	* Line delimiter
	*
	* @var	string
	*/
	var $delimiter = "\r\n";

	/**
	* Registry object for any options we need
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Switch to enable/disable debugging. When enabled, warnings are not suppressed
	*
	* @var	boolean
	*/
	var $debug = false;

	/**
	* Message to log if logging is enabled
	*
	* @var	string
	*/
	var $log = '';

	/**
	* Constructor
	*
	* @param	vB_Registry	vBulletin registry object
	*/
	function vB_Mail(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('Registry object is not an object', E_USER_ERROR);
		}

		$sendmail_path = @ini_get('sendmail_path');
		if (!$sendmail_path OR $this->registry->options['use_smtp'] OR defined('FORCE_MAIL_CRLF'))
		{
			// no sendmail, so we're using SMTP or a server that lines CRLF to send mail // the use_smtp part is for the MailQueue extension
			$this->delimiter = "\r\n";
		}
		else
		{
			$this->delimiter = "\n";
		}
	}

	/**
	 * Factory method for mail.
	 *
	 * @param 	vB_Registry	vBulletin registry object
	 * @param	bool		Whether mail sending can be deferred
	 *
	 * @return	vB_Mail
	 */
	function fetchLibrary($registry, $deferred = false)
	{
		if ($deferred)
		{
			return vB_QueueMail::fetch_instance();
		}

		if ($registry->options['use_smtp'])
		{
			return new vB_SmtpMail($registry);
		}

		return new vB_Mail($registry);
	}

	/**
	* Starts the process of sending an email - preps it so it's fully ready to send.
	* Call send() to actually send it.
	*
	* @param	string	Destination email address
	* @param	string	Email message subject
	* @param	string	Email message body
	* @param	string	Optional name/email to use in 'From' header
	* @param	string	Additional headers
	* @param	string	Username of person sending the email
	*
	* @param	boolean	True on success, false on failure
	*/
	function start($toemail, $subject, $message, $from = '', $uheaders = '', $username = '')
	{
		$toemail = $this->fetch_first_line($toemail);

		if (empty($toemail))
		{
			return false;
		}

		$delimiter =& $this->delimiter;
		$vbulletin =& $this->registry;

		$toemail = unhtmlspecialchars($toemail);
		$subject = $this->fetch_first_line($subject);
		$message = preg_replace("#(\r\n|\r|\n)#s", $delimiter, trim($message));

		if ((strtolower(vB_Template_Runtime::fetchStyleVar('charset')) == 'iso-8859-1' OR vB_Template_Runtime::fetchStyleVar('charset') == '') AND preg_match('/&[a-z0-9#]+;/i', $message))
		{
			$message = utf8_encode($message);
			$subject = utf8_encode($subject);
			$username = utf8_encode($username);

			$encoding = 'UTF-8';
			$unicode_decode = true;
		}
		else if ($this->registry->options['utf8encode'])
		{
			$message = to_utf8($message, vB_Template_Runtime::fetchStyleVar('charset'));
			$subject = to_utf8($subject, vB_Template_Runtime::fetchStyleVar('charset'));
			$username = to_utf8($username, vB_Template_Runtime::fetchStyleVar('charset'));

			$encoding = 'UTF-8';
			$unicode_decode = true;
		}
		else
		{
			// we know nothing about the message's encoding in relation to UTF-8,
			// so we can't modify the message at all; just set the encoding
			$encoding = vB_Template_Runtime::fetchStyleVar('charset');
			$unicode_decode = false;
		}

		// theses lines may need to call convert_int_to_utf8 directly
		$message = unhtmlspecialchars($message, $unicode_decode);
		$subject = $this->encode_email_header(unhtmlspecialchars($subject, $unicode_decode), $encoding, false, false);

		$from = $this->fetch_first_line($from);
		if (empty($from))
		{
			global $vbphrase;
			if (isset($vbphrase['x_mailer']))
			{
				$mailfromname = construct_phrase($this->fetch_first_line($vbphrase['x_mailer']), $vbulletin->options['bbtitle']);
			}
			else
			{
				$mailfromname = $vbulletin->options['bbtitle'];
			}

			if ($unicode_decode == true)
			{
				$mailfromname = utf8_encode($mailfromname);
			}
			$mailfromname = $this->encode_email_header(unhtmlspecialchars($mailfromname, $unicode_decode), $encoding);

			$headers .= "From: $mailfromname <" . $vbulletin->options['webmasteremail'] . '>' . $delimiter;
			$headers .= 'Auto-Submitted: auto-generated' . $delimiter;

			// Exchange (Oh Microsoft) doesn't respect auto-generated: http://www.vbulletin.com/forum/project.php?issueid=27687
			if ($vbulletin->options['usebulkheader'])
			{
				$headers .= 'Precedence: bulk' . $delimiter;
			}
		}
		else
		{
			if ($username)
			{
				$mailfromname = "$username @ " . $vbulletin->options['bbtitle'];
			}
			else
			{
				$mailfromname = $from;
			}

			if ($unicode_decode == true)
			{
				$mailfromname = utf8_encode($mailfromname);
			}
			$mailfromname = $this->encode_email_header(unhtmlspecialchars($mailfromname, $unicode_decode), $encoding);

			$headers .= "From: $mailfromname <$from>" . $delimiter;
			$headers .= "Sender: " . $vbulletin->options['webmasteremail'] . $delimiter;
		}

		$fromemail = empty($vbulletin->options['bounceemail']) ? $vbulletin->options['webmasteremail'] : $vbulletin->options['bounceemail'];
		$headers .= 'Return-Path: ' . $fromemail . $delimiter;

		$http_host = VB_HTTP_HOST;
		if (!$http_host)
		{
			$http_host = substr(md5($message), 12, 18) . '.vb_unknown.unknown';
		}

		$msgid = '<' . gmdate('YmdHis') . '.' . substr(md5($message . microtime()), 0, 12) . '@' . $http_host . '>';
		$headers .= 'Message-ID: ' . $msgid . $delimiter;

		$headers .= preg_replace("#(\r\n|\r|\n)#s", $delimiter, $uheaders);
		unset($uheaders);

		$headers .= 'MIME-Version: 1.0' . $delimiter;
		$headers .= 'Content-Type: text/plain' . iif($encoding, "; charset=\"$encoding\"") . $delimiter;
		$headers .= 'Content-Transfer-Encoding: 8bit' . $delimiter;
		$headers .= 'X-Priority: 3' . $delimiter;
		$headers .= 'X-Mailer: vBulletin Mail via PHP' . $delimiter;

		($hook = vBulletinHook::fetch_hook('mail_send')) ? eval($hook) : false;

		$this->toemail = $toemail;
		$this->subject = $subject;
		$this->message = $message;
		$this->headers = $headers;
		$this->fromemail = $fromemail;

		return true;
	}

	/**
	* Set all the necessary variables for sending a message.
	*
	* @param	string	Destination address
	* @param	string	Subject
	* @param	string	Message
	* @param	string	All headers to be sent with the message
	* @param	string	Sender email
	*/
	function quick_set($toemail, $subject, $message, $headers, $fromemail)
	{
		$this->toemail = $toemail;
		$this->subject = $subject;
		$this->message = $message;
		$this->headers = $headers;
		$this->fromemail = $fromemail;
	}

	/**
	 * Send the mail.
	 * Note: If you define DISABLE_MAIL in config.php as:
	 * 	delimited email addresses	- Only mail for the recipients will be sent
	 *	<filename>.log				- Mail will be logged to the given file if writable
	 *  any other value				- Mail will be disabled
	 *
	 * If $force_mail is true, DISABLE_MAIL will be ignored.
	 *
	 * @return boolean True on success, false on failure
	 */
	function send($force_send = false)
	{
		// No recipient, abort
		if (!$this->toemail)
		{
			return false;
		}

		// Check debug settings
		if (!$force_send AND defined('DISABLE_MAIL'))
		{
			if (is_string(DISABLE_MAIL))
			{
				// check for a recipient whitelist
				if (strpos(DISABLE_MAIL, '@') !== false)
				{
					// check if the address is allowed
					if (strpos($this->toemail, DISABLE_MAIL) === false)
					{
						return false;
					}
				}
				else if (strpos(DISABLE_MAIL, '.log') !== false)
				{
					// mail is only logged
					$this->log_email('DEBUG', DISABLE_MAIL);

					return true;
				}
				else
				{
					// recipient not in the whitelist and not logging
					return false;
				}
			}
			else
			{
				// DISABLE_MAIL defined but isn't a string so just disable
				return false;
			}
		}

		// Send the mail
		return $this->exec_send();
	}

	/**
	* Actually send the message.
	*
	* @return	boolean	True on success, false on failure
	*/
	protected function exec_send()
	{
		if (!$this->toemail)
		{
			return false;
		}

		@ini_set('sendmail_from', $this->fromemail);

		($hook = vBulletinHook::fetch_hook('mail_internal_send_before')) ? eval($hook) : false;

		if (!SAFEMODE AND $this->registry->options['needfromemail'])
		{
			$result =  @mail($this->toemail, $this->subject, $this->message, trim($this->headers), '-f ' . $this->fromemail);
		}
		else
		{
			$result = @mail($this->toemail, $this->subject, $this->message, trim($this->headers));
		}

		($hook = vBulletinHook::fetch_hook('mail_internal_send_after')) ? eval($hook) : false;

		$this->log_email($result);
		return $result;
	}

	/**
	* Returns the first line of a string -- good to prevent errors when sending emails (above)
	*
	* @param	string	String to be trimmed
	*
	* @return	string
	*/
	function fetch_first_line($text)
	{
		$text = preg_replace("/(\r\n|\r|\n)/s", "\r\n", trim($text));
		$pos = strpos($text, "\r\n");
		if ($pos !== false)
		{
			return substr($text, 0, $pos);
		}
		return $text;
	}

	/**
	* Encodes a mail header to be RFC 2047 compliant. This allows for support
	* of non-ASCII character sets via the quoted-printable encoding.
	*
	* @param	string	The text to encode
	* @param	string	The character set of the text
	* @param	bool	Whether to force encoding into quoted-printable even if not necessary
	* @param	bool	Whether to quote the string; applies only if encoding is not done
	*
	* @return	string	The encoded header
	*/
	function encode_email_header($text, $charset = 'utf-8', $force_encode = false, $quoted_string = true)
	{
		$text = trim($text);

		if (!$charset)
		{
			// don't know how to encode, so we can't
			return $text;
		}

		if ($force_encode == true)
		{
			$qp_encode = true;
		}
		else
		{
			$qp_encode = false;

			for ($i = 0; $i < strlen($text); $i++)
			{
				if (ord($text{$i}) > 127)
				{
					// we have a non ascii character
					$qp_encode = true;
					break;
				}
			}
		}

		if ($qp_encode == true)
		{
			// see rfc 2047; not including _ as allowed here, as I'm encoding spaces with it
			$outtext = preg_replace('#([^a-zA-Z0-9!*+\-/ ])#e', "'=' . strtoupper(dechex(ord(str_replace('\\\"', '\"', '\\1'))))", $text);
			$outtext = str_replace(' ', '_', $outtext);
			$outtext = "=?$charset?q?$outtext?=";
			return $outtext;
		}
		else
		{
			if ($quoted_string)
			{
				$text = str_replace(array('"', '(', ')'), array('\"', '\(', '\)'), $text);
				return "\"$text\"";
			}
			else
			{
				return preg_replace('#(\r\n|\n|\r)+#', ' ', $text);
			}
		}
	}

	/**
	* Sets the debug member
	*
	* @param	boolean
	*/
	function set_debug($debug)
	{
		$this->debug = $debug;
	}

	/**
	* Logs email to file
	*
	*/
	function log_email($status = true, $errfile = false)
	{
		if (is_demo_mode())
		{
			return;
		}

		// log file is passed or taken from options
		$errfile = $errfile ? $errfile : $this->registry->options['errorlogemail'];

		// no log file specified
		if (!$errfile)
		{
			return;
		}

		// trim .log from logfile
		$errfile = (substr($errfile, -4) == '.log') ? substr($errfile, 0, -4) : $errfile;

		if ($this->registry->options['errorlogmaxsize'] != 0 AND $filesize = @filesize("$errfile.log") AND $filesize >= $this->registry->options['errorlogmaxsize'])
		{
			@copy("$errfile.log", $errfile . TIMENOW . '.log');
			@unlink("$errfile.log");
		}

		$timenow = date('r', TIMENOW);

		$is_admin = ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']);

		$fp = @fopen("$errfile.log", 'a+b');

		if ($fp)
		{
			if ($status === true)
			{
				$output = "SUCCESS\r\n";
			}
			else
			{
				$output = "FAILED";
				if ($status !== false)
				{
					$output .= ": $status";
				}
				$output .= "\r\n";
			}
			if ($this->delimiter == "\n")
			{
				$append = "$timenow\r\nTo: " . $this->toemail . "\r\nSubject: " . $this->subject . "\r\n" . $this->headers . "\r\n\r\n" . $this->message . "\r\n=====================================================\r\n\r\n";
				@fwrite($fp, $output . $append);
			}
			else
			{
				$append = preg_replace("#(\r\n|\r|\n)#s", "\r\n", "$timenow\r\nTo: " . $this->toemail . "\r\nSubject: " . $this->subject . "\r\n" . $this->headers . "\r\n\r\n" . $this->message . "\r\n=====================================================\r\n\r\n");

				@fwrite($fp, $output . $append);
			}
			fclose($fp);
		}
	}
}

/**
* SMTP Mail Sending Object
*
* This class sends email from vBulletin using an SMTP wrapper
*
* @package 		vBulletin
* @version		$Revision: 44840 $
* @date 		$Date: 2011-06-21 13:03:13 -0700 (Tue, 21 Jun 2011) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_SmtpMail extends vB_Mail
{
	/**
	* SMTP host
	*
	* @var	string
	*/
	var $smtpHost;

	/**
	* SMTP port
	*
	* @var	integer
	*/
	var $smtpPort;

	/**
	* SMTP username
	*
	* @var	string
	*/
	var $smtpUser;

	/**
	* SMTP password
	*
	* @var	string
	*/
	var $smtpPass;

	/**
	* Raw SMTP socket
	*
	* @var	resource
	*/
	var $smtpSocket = null;

	/**
	* Return code from SMTP server
	*
	* @var	integer
	*/
	var $smtpReturn = 0;

	/**
	* What security method to use
	*
	* @var	string
	*/
	var $secure = '';

	/**
	* Constructor
	*
	* @param	vB_Registry	vBulletin registry object
	*/
	function vB_SmtpMail(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('Registry object is not an object', E_USER_ERROR);
		}

		$this->secure = $this->registry->options['smtp_tls'];

		// Prior to 3.8 this was a radio button so SSL is 1
		if ($this->registry->options['smtp_tls'] == 1)
		{
			$this->secure = 'ssl';
		}

		//since ('ssl' == 0) is true in php, we need to check for legacy 0 values as well
		//note that in the off change that somebody gets '0' into the system, this will
		//work just fine without conversion.
		else if ($this->registry->options['smtp_tls'] === 0)
		{
			$this->secure = 'none';
		}

		$this->smtpHost = $this->registry->options['smtp_host'];
		$this->smtpPort = (!empty($this->registry->options['smtp_port']) ? intval($this->registry->options['smtp_port']) : 25);
		$this->smtpUser =& $this->registry->options['smtp_user'];
		$this->smtpPass =& $this->registry->options['smtp_pass'];

		$this->delimiter = "\r\n";
	}

	/**
	* Sends instruction to SMTP server
	*
	* @param	string	Message to be sent to server
	* @param	mixed	Message code expected to be returned or false if non expected
	*
	* @return	boolean	Returns false on error
	*/
	function sendMessage($msg, $expectedResult = false)
	{
		if ($msg !== false AND !empty($msg))
		{
			fputs($this->smtpSocket, $msg . "\r\n");
		}
		if ($expectedResult !== false)
		{
			$result = '';
			while ($line = @fgets($this->smtpSocket, 1024))
			{
				$result .= $line;
				if (preg_match('#^(\d{3}) #', $line, $matches))
				{
					break;
				}
			}
			$this->smtpReturn = intval($matches[1]);
			return ($this->smtpReturn == $expectedResult);
		}
		return true;
	}

	/**
	* Triggers PHP warning on error
	*
	* @param	string	Error message to be shown
	*
	* @return	boolean	Always returns false (error)
	*/
	function errorMessage($msg)
	{
		if ($this->debug)
		{
			trigger_error($msg, E_USER_WARNING);
		}
		$this->log_email($msg);
		return false;
	}

	function sendHello()
	{
		if (!$this->smtpSocket)
		{
			return false;
		}
		if (!$this->sendMessage('EHLO ' . $this->smtpHost, 250))
		{
			if (!$this->sendMessage('HELO ' . $this->smtpHost, 250))
			{
				return false;
			}
		}
		return true;
	}

	/**
	* Attempts to send email based on parameters passed into start()/quick_set()
	*
	* @return	boolean	Returns false on error
	*/
	protected function exec_send()
	{
		if (!$this->toemail)
		{
			return false;
		}

		$this->smtpSocket = fsockopen(($this->secure == 'ssl' ? 'ssl://' : 'tcp://') . $this->smtpHost, $this->smtpPort, $errno, $errstr, 30);

		if ($this->smtpSocket)
		{
			if (!$this->sendMessage(false, 220))
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response when connecting to SMTP server');
			}

			// do initial handshake
			if (!$this->sendHello())
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during handshake');
			}

			if ($this->secure == 'tls' AND function_exists('stream_socket_enable_crypto'))
			{
				if ($this->sendMessage('STARTTLS', 220))
				{
					if (!stream_socket_enable_crypto($this->smtpSocket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
					{
						return $this->errorMessage('Unable to negotitate TLS handshake.');
					}
				}

				// After TLS say Hi again
				$this->sendHello();
			}

			if ($this->smtpUser AND $this->smtpPass)
			{
				if ($this->sendMessage('AUTH LOGIN', 334))
				{
					if (!$this->sendMessage(base64_encode($this->smtpUser), 334) OR !$this->sendMessage(base64_encode($this->smtpPass), 235))
					{
						return $this->errorMessage($this->smtpReturn . ' Authorization to the SMTP server failed');
					}
				}
			}

			if (!$this->sendMessage('MAIL FROM:<' . $this->fromemail . '>', 250))
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during FROM address transmission');
			}

			// we could have multiple addresses since a few people might expect this to be the same as PHP
			$addresses = explode(',', $this->toemail);
			foreach ($addresses AS $address)
			{
				if (!$this->sendMessage('RCPT TO:<' . trim($address) . '>', 250))
				{
					return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during TO address transmission');
				}
			}
			if ($this->sendMessage('DATA', 354))
			{
				$this->sendMessage('Date: ' . gmdate('r'), false);
				$this->sendMessage('To: ' . $this->toemail, false);
				$this->sendMessage(trim($this->headers), false); // trim to prevent double \r\n
				$this->sendMessage('Subject: ' . $this->subject, false);
				$this->sendMessage("\r\n", false); // this makes a double \r\n
				// catch any single dots on their own
				$this->message = preg_replace('#^\.' . $this->delimiter . '#m', '..' . $this->delimiter, $this->message);
				$this->sendMessage($this->message, false);
			}
			else
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server during data transmission');
			}

			if (!$this->sendMessage('.', 250))
			{
				return $this->errorMessage($this->smtpReturn . ' Unexpected response from SMTP server when ending transmission');
			}

			// Don't check that QUIT returns a valid result as some servers just kill the connection e.g. smtp.gmail.com
			$this->sendMessage('QUIT', 221);

			fclose($this->smtpSocket);
			$this->log_email();
			return true;
		}
		else
		{
			return $this->errorMessage('Unable to connect to SMTP server');
		}
	}
}


/**
* Mail queueing class. This class should be accessed as a singleton via fetch_instance()!
* This class does not actually send emails, but rather queues them to be sent later in a batch.
*
* @package 		vBulletin
* @version		$Revision: 44840 $
* @date 		$Date: 2011-06-21 13:03:13 -0700 (Tue, 21 Jun 2011) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_QueueMail extends vB_Mail
{
	/**
	* The SQL to insert into the mail queue
	*
	* @var	string
	*/
	var $mailsql = '';

	/**
	* The number of mails being inserted into the queue
	*
	* @var	string
	*/
	var $mailcounter = '';

	/**
	* Whether to do bulk inserts into the database.
	* Never set this option directly!
	*
	* @var	boolean
	*/
	var $bulk = false;

	/**
	* Inserts the message into the queue instead of sending it.
	*
	* @return	string	True on success, false on failure
	*/
	protected function exec_send()
	{
		if (!$this->toemail)
		{
			return false;
		}

		$vbulletin =& $this->registry;

		$data = "
			(" . TIMENOW . ",
			'" . $vbulletin->db->escape_string($this->toemail) . "',
			'" . $vbulletin->db->escape_string($this->fromemail) . "',
			'" . $vbulletin->db->escape_string($this->subject) . "',
			'" . $vbulletin->db->escape_string($this->message) . "',
			'" . $vbulletin->db->escape_string($this->headers) . "')
		";

		if ($this->bulk)
		{
			if (!empty($this->mailsql))
			{
				$this->mailsql .= ',';
			}

			$this->mailsql .= $data;
			$this->mailcounter++;

			// current insert exceeds half megabyte, insert it and start over
			if (strlen($this->mailsql) > 524288)
			{
				$this->set_bulk(false);
				$this->set_bulk(true);
			}
		}
		else
		{
			/*insert query*/
			$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "mailqueue
					(dateline, toemail, fromemail, subject, message, header)
				VALUES
				" . $data
			);

			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "datastore SET
					data = data + 1
				WHERE title = 'mailqueue'
			");

			// if we're using a alternate datastore, we need to give it an integer value
			// this may not be atomic
			if (method_exists($vbulletin->datastore, 'build'))
			{
				$mailqueue_db = $vbulletin->db->query_first("
					SELECT data
					FROM " . TABLE_PREFIX . "datastore
					WHERE title = 'mailqueue'
				");
				$vbulletin->datastore->build('mailqueue', intval($mailqueue_db['data']));
			}
		}

		return true;
	}

	/**
	* Sets the bulk option. If disabling the option, this also flushes
	* the cache into the database.
	*
	* @param	boolean
	*/
	function set_bulk($bulk)
	{
		if ($bulk)
		{
			$this->bulk = true;
			$this->mailcounter = 0;
			$this->mailsql = '';
		}
		else if ($this->mailcounter AND $this->mailsql)
		{
			// turning off bulk sending, so save all the mails
			$vbulletin =& $this->registry;

			/*insert query*/
			$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "mailqueue
				(dateline, toemail, fromemail, subject, message, header)
				VALUES
				" . $this->mailsql
			);
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "datastore
				SET data = data + " . intval($this->mailcounter) . "
				WHERE title = 'mailqueue'
			");

			// if we're using a alternate datastore, we need to give it an integer value
			// this may not be atomic
			if (method_exists($vbulletin->datastore, 'build'))
			{
				$mailqueue_db = $vbulletin->db->query_first("
					SELECT data
					FROM " . TABLE_PREFIX . "datastore
					WHERE title = 'mailqueue'
				");
				$vbulletin->datastore->build('mailqueue', intval($mailqueue_db['data']));
			}
		}

		$this->bulk = true;
		$this->mailsql = '';
		$this->mailcounter = 0;
	}

	/**
	* Singleton emulator. Fetches the instance if it doesn't exist.
	* Be sure to accept a reference if using this function!
	*
	* @return	vB_QueueMail	Reference to the instance
	*/
	function &fetch_instance()
	{
		static $instance = null;

		if ($instance === null)
		{
			global $vbulletin;
			$instance = new vB_QueueMail($vbulletin);
		}

		return $instance;
	}

	/**
	* The only part of this class which actually sends an email.
	* Sends mail from the queue.
	*/
	function exec_queue()
	{
		$vbulletin =& $this->registry;

		if ($vbulletin->options['usemailqueue'] == 2)
		{
			// Lock mailqueue table so that only one process can
			// send a batch of emails and then delete them
			$vbulletin->db->lock_tables(array('mailqueue' => 'WRITE'));
		}

		$emails = $vbulletin->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "mailqueue
			ORDER BY mailqueueid
			LIMIT " . intval($vbulletin->options['emailsendnum'])
		);

		$mailqueueids = '';
		$newmail = 0;
		$emailarray = array();
		while ($email = $vbulletin->db->fetch_array($emails))
		{
			// count up number of mails about to send
			$mailqueueids .= ',' . $email['mailqueueid'];
			$newmail++;
			$emailarray[] = $email;
		}
		if (!empty($mailqueueids))
		{
			// remove mails from queue - to stop duplicates being sent
			$vbulletin->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "mailqueue
				WHERE mailqueueid IN (0 $mailqueueids)
			");

			if ($vbulletin->options['usemailqueue'] == 2)
			{
				$vbulletin->db->unlock_tables();
			}

			$prototype = vB_Mail::fetchLibrary($vbulletin);

			foreach ($emailarray AS $index => $email)
			{
				// send those mails
				$mail = clone($prototype);
				$mail->quick_set($email['toemail'], $email['subject'], $email['message'], $email['header'], $email['fromemail']);
				$mail->send();
			}

			$newmail = 'data - ' . intval($newmail);
		}
		else
		{
			if ($vbulletin->options['usemailqueue'] == 2)
			{
				$vbulletin->db->unlock_tables();
			}

			$newmail = 0;
		}

		// update number of mails remaining
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "datastore SET
				data = " . $newmail . ",
				data = IF(data < 0, 0, data)
			WHERE title = 'mailqueue'
		");

		// if we're using a alternate datastore, we need to give it an integer value
		// this may not be atomic
		if (method_exists($vbulletin->datastore, 'build'))
		{
			$mailqueue_db = $vbulletin->db->query_first("
				SELECT data
				FROM " . TABLE_PREFIX . "datastore
				WHERE title = 'mailqueue'
			");
			$vbulletin->datastore->build('mailqueue', intval($mailqueue_db['data']));
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44840 $
|| ####################################################################
\*======================================================================*/
