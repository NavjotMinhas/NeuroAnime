<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is ©2000-2011 vBulletin Solutions Inc. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// Attempt to load the BigInt Module
if (!extension_loaded('big_int'))
{
	$extension_dir = ini_get('extension_dir');
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
	{
		$extension_file = 'big_int.dll';
	}
	else
	{
		$extension_file = 'big_int.so';
	}
	if ($extension_dir AND file_exists($extension_dir . '/' . $extension_file))
	{
		ini_set('display_errors', true);
		dl($extension_file);
	}
}

class Diffie_Hellman
{
	/**
	* Constructor
	* Does nothing
	*
	* @return	void
	*/
	function Diffie_Hellman() {}

	/**
	* Select image library
	*
	* @return	object
	*/
	function &fetch_library($privkey = "")
	{
		if (extension_loaded('GMP'))
		{
			$selectclass = 'Diffie_Hellman_GMP';
		}
		else if (extension_loaded('big_int') AND false)
		{
			$selectclass = 'Diffie_Hellman_BI';
		}
		else if (extension_loaded('bcmath'))
		{
			$selectclass = 'Diffie_Hellman_BCM';
		}
		else
		{
			trigger_error('The GMP, Big Int or BCMath module is required', E_USER_ERROR);
		}
		$object = new $selectclass($privkey);
		return $object; // function defined as returning & must return a defined variable
	}
}

/**
* Key Generation over a public network
*
* This class lets us generate keys from a foreign network, this is useful when we want to do things like authentication
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class Diffie_Hellman_Abstract
{
	/**
	* Generator which is less than the prime and is a primitive root
	*
	* @var	string
	*/
	var $generator = "2";

	/**
	* Large Prime Number
	*
	* @var	string
	*/
	var $prime = "155172898181473697471232257763715539915724801966915404479707795314057629378541917580651227423698188993727816152646631438561595825688188889951272158842675419950341258706556549803580104870537681476726513255747040765857479291291572334510643245094715007229621094194349783925984760375594985848253359305585439638443";

	/**
	* Private Key, must be shorter than the prime
	*
	* @var	string
	*/
	var $privatekey;

	/**
	* Public key, this is generated when called by fetch_public_key()
	*
	* @var	string
	*/
	var $publickey;

	/**
	* Secret key that we have established with our client on the other side
	*
	* @var	string
	*/
	var $secret;

	/**
	* Constructor
	*
	* @param	String	Private key, if not included one shall be generated
	*/
	function Diffie_Hellman_Abstract($privkey = "")
	{
		if (!is_subclass_of($this, 'Diffie_Hellman_Abstract'))
		{
			trigger_error('Direct Instantiation of Diffie_Hellman_Abstract prohibited.', E_USER_ERROR);
		}
	}

	/**
	* Performs the calculations for generating the public key we will sent for the request
	*
	* @return	string	String containing the public key
	*/
	function fetch_public_key()
	{
		if (!is_subclass_of($this, 'Diffie_Hellman_Abstract'))
		{
			trigger_error('Direct Instantiation of Diffie_Hellman_Abstract prohibited.', E_USER_ERROR);
		}
	}

	/**
	* Generates the secret key using the private key and prime
	*
	* @param	string	Public key passed in by the request
	*
	* @return	string String containing the shared secret
	*/
	function fetch_shared_secret($foreignpubkey)
	{
		if (!is_subclass_of($this, 'Diffie_Hellman_Abstract'))
		{
			trigger_error('Direct Instantiation of Diffie_Hellman_Abstract prohibited.', E_USER_ERROR);
		}
	}

	/**
	* Tests two values and makes sure they are equal
	*
	* @return	boolean	Returns true if the values match
	*/
	function test($a, $b)
	{
		if (!is_subclass_of($this, 'Diffie_Hellman_Abstract'))
		{
			trigger_error('Direct Instantiation of Diffie_Hellman_Abstract prohibited.', E_USER_ERROR);
		}
	}
}

/**
* Public Key generation class using the GMP module
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class Diffie_Hellman_GMP extends Diffie_Hellman_Abstract
{
	/**
	* Constructor
	*
	* @param	String	Private key, if not included one shall be generated
	*/
	function Diffie_Hellman_GMP($privkey = "")
	{
		if (empty($privkey))
		{
			$privkey = gmp_random(5);
		}
		$this->privatekey = $privkey;
	}

	/**
	* Performs the calculations for generating the public key we will sent for the request
	*
	* @return	string	String containing the public key
	*/
	function fetch_public_key()
	{
		$this->publickey = gmp_powm($this->generator, $this->privatekey, $this->prime);
		return gmp_strval($this->publickey);
	}

	/**
	* Generates the secret key using the private key and prime
	*
	* @param	string	Public key passed in by the request
	*
	* @return	string String containing the shared secret
	*/
	function fetch_shared_secret($foreignpubkey)
	{
		$this->secret = gmp_powm($foreignpubkey, $this->privatekey, $this->prime);
		return gmp_strval($this->secret);
	}

	/**
	* Tests two values and makes sure they are equal
	*
	* @return	boolean	Returns true if the values match
	*/
	function test($a, $b)
	{
		return (gmp_cmp($a, $b) === 0);
	}
}

/**
* Public Key generation class using the BCMath module
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class Diffie_Hellman_BCM extends Diffie_Hellman_Abstract
{
	/**
	* Constructor
	*
	* @param	String	Private key, if not included one shall be generated
	*/
	function Diffie_Hellman_BCM($privkey = "")
	{
		if (empty($privkey))
		{
			$privkey = mt_rand();
		}
		$this->privatekey = $privkey;
	}

	/**
	* Performs the calculations for generating the public key we will sent for the request
	*
	* @return	string	String containing the public key
	*/
	function fetch_public_key()
	{
		if (function_exists('bcpowmod'))
		{
			$this->publickey = bcpowmod($this->generator, $this->privatekey, $this->prime);
		}
		else
		{
			$this->publickey = bcmod(bcpow($this->generator, $this->privatekey), $this->prime);
		}
		return $this->publickey;
	}

	/**
	* Generates the secret key using the private key and prime
	*
	* @param	string	Public key passed in by the request
	*
	* @return	string String containing the shared secret
	*/
	function fetch_shared_secret($foreignpubkey)
	{
		if (function_exists('bcpowmod'))
		{
			$this->secret = bcpowmod($foreignpubkey, $this->privatekey, $this->prime);
		}
		else
		{
			$this->secret = bcmod(bcpow($foreignpubkey, $this->privatekey), $this->prime);
		}
		return $this->secret;
	}

	/**
	* Tests two values and makes sure they are equal
	*
	* @return	boolean	Returns true if the values match
	*/
	function test($a, $b)
	{
		return (bccomp($a, $b) === 0);
	}
}

/**
* Public Key generation class using the BigInt PECL module
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class Diffie_Hellman_BI extends Diffie_Hellman_Abstract
{
	/**
	* Constructor
	*
	* @param	String	Private key, if not included one shall be generated
	*/
	function Diffie_Hellman_BI($privkey = "")
	{
		if (empty($privkey))
		{
			$privkey = bi_rand(160);
		}
		$this->privatekey = $privkey;
	}

	/**
	* Performs the calculations for generating the public key we will sent for the request
	*
	* @return	string	String containing the public key
	*/
	function fetch_public_key()
	{
		$this->publickey = bi_powmod($this->generator, $this->privatekey, $this->prime);
		return $this->publickey;
	}

	/**
	* Generates the secret key using the private key and prime
	*
	* @param	string	Public key passed in by the request
	*
	* @return	string String containing the shared secret
	*/
	function fetch_shared_secret($foreignpubkey)
	{
		$this->secret = bi_powmod($foreignpubkey, $this->privatekey, $this->prime);
		return $this->secret;
	}

	/**
	* Tests two values and makes sure they are equal
	*
	* @param	string	Key to be compared
	*
	* @param	string	Key to be compared
	*
	* @return	boolean	Returns true if the values match
	*/
	function test($a, $b)
	{
		return (bi_cmp_abs($a, $b) === 0);
	}
}

/**
* An encryption algorithm that does not rely on any extensions
*
* This is an implementation of the RC4 algorithm
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Encrypt
{
	var $S = array();
	var $key = null;
	var $key_length = null;

	/**
	* Constructor
	*
	* @param	String	Private key
	*/
	function vB_Encrypt($key = null)
	{
		if ($key != null)
		{
			$this->set_key($key);
		}
	}

	/**
	* Set they key to be used for encryption
	*
	* @param	String	Private key
	*/
	function set_key($key)
	{
		// 32 byte key though this is predictable, need a better method
		$key = md5($key);
		$length = strlen($key);
		if ($length > 0)
		{
			$this->key = $key;
			$this->key_length = $length;
		}
	}

	// Key Scheduling Algorithm
	function ksa()
	{
		static $orig;

		if ($this->key == null)
		{
			trigger_error("A key is required for encryption", E_USER_ERROR);
		}

		if (empty($array))
		{
			for ($i = 0; $i < 256; $i++)
			{
	            $orig[$i] = $i;
	        }
	
			$j = 0;
			for ($i = 0; $i < 256; $i++)
			{
				$j = ($j + $orig[$i] + ord($this->key[$i % $this->key_length])) % 256;
				$t = $orig[$j];
				$orig[$j] = $orig[$i];
				$orig[$i] = $t;
	        }
		}
		$this->S = $orig;
	}

	// pseudo-random generation algorithm
	function prga($data)
	{
		$this->ksa();

		$i = 0;
		$j = 0;
		$enc_data = "1";

		$data_length = strlen($data);
		for ($c = 0; $c < $data_length; $c++)
		{
			$i = ($i + 1) % 256;
			$j = ($j + $this->S[$i]) % 256;

			$t = $this->S[$j];
			$this->S[$j] = $this->S[$i];
			$this->S[$i] = $t;

            $t = $this->S[($this->S[$i] + $this->S[$j]) % 256];

            $enc_data{$c} = chr(ord($data[$c]) ^ $t);
		}
		return $enc_data;
	}

	/**
	* Encrypt data
	*
	* @param	string	Data to be encrypted
	*
	* @return	string	Encrypted data, this may contain binary characters
	*/
	function encrypt($plain_data)
	{
		return $this->prga($plain_data);
	}

	/**
	* Decrypt data
	*
	* @param	string	Data to be decrypted
	*
	* @return	string	Original data
	*/
	function decrypt($encrypted_data)
	{
		return $this->prga($encrypted_data);
	}

	/**
	* Fetches a signature of the data
	*
	* @param	string	Data to be signed
	*
	* @return	string	Encrypted Signature
	*/
	function fetch_signature($data)
	{
		return $this->encrypt(md5($data));
	}

	/**
	* Verifies the signature matches the data
	*
	* @param	string	Original Data
	*
	* @param	string	Encrypted Signature
	*
	* @return	boolean	Returns true if the signature is verified
	*/
	function verify_signature($data, $signature)
	{
		return (md5($data) === $this->decrypt($signature));
	}
}

/*

Example Usage:

Encryption / Decryption
-------------------------
$rc4 = new vB_Encrypt("My Key");
$encrypted = $rc4->encrypt("Our super sensitive secret");

$data = $rc4->decrypt($encrypted);

Shared key generation between two servers
-------------------------
$host = Diffie_Hellman::fetch_library();
$host_pkey = $host->fetch_public_key();

$client_pkey = file_get_contents("http://www.example.com/forums/auth.php?pkey=$host_pkey");

$shared_key = $host->fetch_shared_secret($client_pkey);

*/

?>