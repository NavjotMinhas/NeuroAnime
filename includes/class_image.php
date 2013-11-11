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

/**#@+
* Global image type defines used by serveral functions
*/
define('GIF', 1);
define('JPG', 2);
define('PNG', 3);
/**#@-*/

/**#@+
* These make up the bit field to enable specific parts of image verification
*/
define('ALLOW_RANDOM_FONT',  1);
define('ALLOW_RANDOM_SIZE',  2);
define('ALLOW_RANDOM_SLANT', 4);
define('ALLOW_RANDOM_COLOR', 8);
define('ALLOW_RANDOM_SHAPE', 16);
/**#@-*/

if (function_exists('imagegif'))
{
	define('IMAGEGIF', true);
}
else
{
	define('IMAGEGIF', false);
}

if (function_exists('imagejpeg'))
{
	define('IMAGEJPEG', true);
}
else
{
	define('IMAGEJPEG', false);
}

if (function_exists('imagepng'))
{
	define('IMAGEPNG', true);
}
else
{
	define('IMAGEPNG', false);
}

if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0)
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

/**
* Abstracted image class
*
* @package 		vBulletin
* @version		$Revision: 37230 $
* @date 		$Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
*
*/
class vB_Image
{
	/**
	* Constructor
	* Does nothing :p
	*
	* @return	void
	*/
	function vB_Image() {}

	/**
	* Select image library
	*
	* @return	object
	*/
	function &fetch_library(&$registry, $type = 'image')
	{
		// Library used for thumbnails, image functions
		if ($type == 'image')
		{
			$selectclass = 'vB_Image_' . ($registry->options['imagetype'] ? $registry->options['imagetype'] : 'GD');
		}
		// Library used for Verification Image
		else
		{
			switch($registry->options['regimagetype'])
			{
				case 'Magick':
					$selectclass = 'vB_Image_Magick';
					break;
				default:
					$selectclass = 'vB_Image_GD';
			}
		}
		$object = new $selectclass($registry);
		return $object; // function defined as returning & must return a defined variable
	}
}

/**
* Abstracted image class
*
* @package 		vBulletin
* @version		$Revision: 37230 $
* @date 		$Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
*
*/
class vB_Image_Abstract
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* @var	array
	*/
	var $thumb_extensions = array();

	/**
	* @var	array
	*/
	var $info_extensions = array();

	/**
	* @var	array
	*/
	var $must_convert_types = array();

	/**
	* @var	array
	*/
	var $resize_types = array();

	/**
	* @var	mixed
	*/
	var $imageinfo = null;

	/**
	* @var	array $extension_map
	*/
	var $extension_map = array(
		'gif'  => 'GIF',
		'jpg'  => 'JPEG',
		'jpeg' => 'JPEG',
		'jpe'  => 'JPEG',
		'png'  => 'PNG',
		'bmp'  => 'BMP',
		'tif'  => 'TIFF',
		'tiff' => 'TIFF',
		'psd'  => 'PSD',
		'pdf'  => 'PDF',
	);

	/**
	* @var	array	$regimageoption
	*/
	var $regimageoption = array(
		'randomfont'  => false,
		'randomsize'  => false,
		'randomslant' => false,
		'randomcolor' => false,
		'randomshape'  => false,
	);

	/**
	* Constructor
	* Don't allow direct construction of this abstract class
	* Sets registry
	*
	* @return	void
	*/
	function vB_Image_Abstract(&$registry)
	{
		if (!is_subclass_of($this, 'vB_Image_Abstract'))
		{
			trigger_error('Direct Instantiation of vB_Image_Abstract prohibited.', E_USER_ERROR);
			return NULL;
		}

		$this->registry = &$registry;
		$this->regimageoption['randomfont'] = $this->registry->options['regimageoption'] & ALLOW_RANDOM_FONT;
		$this->regimageoption['randomsize'] = $this->registry->options['regimageoption'] & ALLOW_RANDOM_SIZE;
		$this->regimageoption['randomslant'] = $this->registry->options['regimageoption'] & ALLOW_RANDOM_SLANT;
		$this->regimageoption['randomcolor'] = $this->registry->options['regimageoption'] & ALLOW_RANDOM_COLOR;
		$this->regimageoption['randomshape'] = $this->registry->options['regimageoption'] & ALLOW_RANDOM_SHAPE;
	}

	/**
	* Private
	* Fetches image files from the backgrounds directory
	*
	* @return array
	*
	*/
	function &fetch_regimage_backgrounds()
	{
		// Get backgrounds
		$backgrounds = array();
		if ($handle = @opendir(DIR . '/images/regimage/backgrounds/'))
		{
			while ($filename = @readdir($handle))
			{
				if (preg_match('#\.(gif|jpg|jpeg|jpe|png)$#i', $filename))
				{
					$backgrounds[] = DIR . "/images/regimage/backgrounds/$filename";
				}
			}
			@closedir($handle);
		}
		return $backgrounds;
	}

	/**
	* Private
	* Fetches True Type fonts from the fonts directory
	*
	* @return array
	*
	*/
	function &fetch_regimage_fonts()
	{
		// Get fonts
		$fonts = array();
		if ($handle = @opendir(DIR . '/images/regimage/fonts/'))
		{
			while ($filename =@ readdir($handle))
			{
				if (preg_match('#\.ttf$#i', $filename))
				{
					$fonts[] = DIR . "/images/regimage/fonts/$filename";
				}
			}
			@closedir($handle);
		}
		return $fonts;
	}

	/**
	*Public
	*
	*
	* @param	string	$type		Type of image from $info_extensions
	*
	* @return	bool
	*/
	function fetch_must_convert($type)
	{
		return !empty($this->must_convert_types["$type"]);
	}

	/**
	* Public
	* Checks if supplied extension can be used by fetch_image_info
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function is_valid_info_extension($extension)
	{
		return !empty($this->info_extensions[strtolower($extension)]);
	}

	/**
	* Public
	* Checks if supplied extension can be resized into a smaller permanent image, not to be used for PSD, PDF, etc as it will lose the original format
	*
	* @param	string	$type 	Type of image from $info_extensions
	*
	* @return	bool
	*/
	function is_valid_resize_type($type)
	{
		return !empty($this->resize_types["$type"]);
	}

	/**
	* Public
	* Checks if supplied extension can be used by fetch_thumbnail
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function is_valid_thumbnail_extension($extension)
	{
		return !empty($this->thumb_extensions[strtolower($extension)]);
	}

	/**
	* Public
	* Checks if supplied extension can be used by fetch_thumbnail
	*
	* @param	string	$extension 	Extension of file
	*
	* @return	bool
	*/
	function fetch_imagetype_from_extension($extension)
	{
		return $this->extension_map[strtolower($extension)];
	}

	/**
	* Private
	* Checks for HTML tags that can be exploited via IE
	*
	* @param string	filename
	*
	* @return bool
	*/
	function verify_image_file($filename)
	{
		// Verify that file is playing nice
		$fp = fopen($filename, 'rb');
		if ($fp)
		{
			$header = fread($fp, 256);
			fclose($fp);
			if (preg_match('#<html|<head|<body|<script|<pre|<plaintext|<table|<a href|<img|<title#si', $header))
			{
				return false;
			}
		}
		else
		{
			return false;
		}

		return true;
	}

	/**
	* Public
	* Retrieve info about image
	*
	* @param	string	filename	Location of file
	* @param	string	extension	Extension of file name
	*
	* @return	array	[0]			int		width
	*					[1]			int		height
	*					[2]			string	type ('GIF', 'JPEG', 'PNG', 'PSD', 'BMP', 'TIFF',) (and so on)
	*					[scenes]	int		scenes
	*					[channels]	int		Number of channels (GREYSCALE = 1, RGB = 3, CMYK = 4)
	*					[bits]		int		Number of bits per pixel
	*					[library]	string	Library Identifier
	*/
	function fetch_image_info() {}

	/**
	* Public
	* Output an image based on a string
	*
	* @param	string	string	String to output
	* @param bool		moveabout	move text about
	*
	* @return	void
	*/
	function print_image_from_string() {}

	/**
	* Public
	* Returns an array containing a thumbnail, creation time, thumbnail size and any errors
	*
	* @param	string	filename	filename of the source file
	* @param	string	location	location of the source file
	* @param	int		newsize		new size of image (longest side of image)
	* @param	int		quality		Jpeg Quality
	* @param bool		labelimage	Include image dimensions and filesize on thumbnail
	* @param bool		drawborder	Draw border around thumbnail
	*
	* @return	array
	*/
	function fetch_thumbnail() {}

	/**
	* Public
	* Return Error from graphics library
	*
	* @return	mixed
	*/
	function fetch_error() {}
}

/**
* Image class for ImageMagick
*
* @package 		vBulletin
* @version		$Revision: 37230 $
* @date 		$Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
*
*/
class vB_Image_Magick extends vB_Image_Abstract
{

	/**
	* @var	string
	*/
	var $convertpath = '/usr/local/bin/convert';

	/**
	* @var	string
	*/
	var $identifypath = '/usr/local/bin/identify';

	/**
	* @var	integer
	*/
	var $returnvalue = 0;

	/**
	* @var  string
	*/
	var $identifyformat = '';

	/**
	* @var	string
	*/
	var $convertoptions = array(
		'width' => '100',
		'height' => '100',
		'quality' => '75',
	);

	/**
	* @var  string
	*
	*/
	var $error = '';

	/**
	* @var string
	*
	*/
	var $thumbcolor = 'black';

	/**
	* Constructor
	* Sets ImageMagick paths to convert and identify
	*
	* @return	void
	*/
	function vB_Image_Magick(&$registry)
	{
		parent::vB_Image_Abstract($registry);

		$path = preg_replace('#[/\\\]+$#', '', $this->registry->options['magickpath']);

		if (preg_match('#^WIN#i', PHP_OS))
		{
			$this->identifypath = '"' . $path . '\identify.exe"';
			$this->convertpath = '"' . $path . '\convert.exe"';
		}
		else
		{
			$this->identifypath = "'" . $path .  "/identify'";
			$this->convertpath = "'" . $path . "/convert'";
		}

		$this->must_convert_types = array(
			'PSD'  => true,
			'BMP'  => true,
			'TIFF' => true,
			'PDF'  => true,
		);

		$this->resize_types = array(
			'GIF'   => true,
			'JPEG'  => true,
			'PNG'   => true,
			'BMP'   => true,
			'TIFF'  => true
		);

		$this->thumb_extensions = array(
			'gif'  => true,
			'jpg'  => true,
			'jpe'  => true,
			'jpeg' => true,
			'png'  => true,
			'psd'  => true,
			'pdf'  => true,
			'bmp'  => true,
			'tiff' => true,
			'tif'  => true,
		);
		$this->info_extensions =& $this->thumb_extensions;

		if (preg_match('~^#([0-9A-F]{6})$~i', $this->registry->options['thumbcolor'], $match))
		{
			$this->thumbcolor = $match[0];
		}

		$this->version = $this->fetch_version();
	}

	/**
	* Private
	* Return Error from fetch_im_exec()
	*
	* @return	string
	*/
	function fetch_error()
	{
		if (!empty($this->error))
		{
			return implode("\n", $this->error);
		}
		else
		{
			return false;
		}
	}

	/**
	* Private
	* Generic call to imagemagick binaries
	*
	* @param	string	command	ImageMagick binary to execute
	* @param	string	args	Arguments to the ImageMagick binary
	*
	* @return	mixed
	*/
	function fetch_im_exec($command, $args, $needoutput = false, $dieongs = true)
	{
		if (!function_exists('exec'))
		{
			$this->error = array(fetch_error('php_error_exec_disabled'));
			return false;
		}

		$imcommands = array(
			'identify' => $this->identifypath,
			'convert'  => $this->convertpath,
		);

		$input = $imcommands["$command"] . ' ' . $args . ' 2>&1';
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' AND PHP_VERSION < '5.3.0')
		{
			$input = '"' . $input . '"';
		}
		$exec = @exec($input, $output, $this->returnvalue);

		if ($this->returnvalue OR $exec === null)
		{	// error was encountered
			if (!empty($output))
			{	// command issued by @exec failed
				if (strpos(strtolower(implode(' ', $output)), 'postscript delegate failed') !== false)
				{
					$output[] = fetch_error('install_ghostscript_to_resize_pdf');
				}
				$this->error = $output;
			}
			else if (!empty($php_errormsg))
			{	// @exec failed so display error and remove path reveal
				$this->error = array(fetch_error('php_error_x', str_replace($this->registry->options['magickpath'] . '\\', '', $php_errormsg)));
			}
			else if ($this->returnvalue == -1)
			{	// @exec failed but we don't have $php_errormsg to tell us why
				$this->error = array(fetch_error('php_error_unspecified_exec'));
			}
			return false;
		}
		else
		{
			$this->error = '';
			if (!empty($output))
			{	// $output is an array of returned text
				// This is for IM which doesn't return false for failed font
				if (strpos(strtolower(implode(' ', $output)), 'unable to read font') !== false)
				{
					$this->error = $output;
					return false;
				}

				if (strpos(strtolower(implode(' ', $output)), 'postscript delegate failed') !== false)
				{	// this is for IM 6.2.4+ which doesn't return false for exec(convert.exe) on .pdf when GS isn't installed
					$this->error = array(fetch_error('install_ghostscript_to_resize_pdf'));
				}
				return $output;
			}
			else if (empty($output) AND $needoutput)
			{	// $output is empty and we expected something back
				return false;
			}
			else
			{	// $output is empty and we didn't expect anything back
				return true;
			}
		}
	}

	/**
	* Private
	* Fetch Imagemagick Version
	*
	* @return	mixed
	*/
	function fetch_version()
	{
		if ($result = $this->fetch_im_exec('convert', '-version', true) AND preg_match('#ImageMagick (\d+\.\d+\.\d+)#', $result[0], $matches))
		{
			return $matches[1];
		}

		return false;
	}

	/**
	* Private
	* Identify an image
	*
	* @param	string	$filename File to obtain image information from
	*
	* @return	mixed
	*/
	function fetch_identify_info($filename)
	{
		$fp = @fopen($filename, 'rb');
		if (($header = @fread($fp, 4)) == '%PDF')
		{	// this is a PDF so only look at frame 0 to save mucho processing time
			$frame0 = '[0]';
		}
		@fclose($fp);

		$execute = (!empty($this->identifyformat) ? "-format {$this->identifyformat} \"$filename\"" : "\"$filename\"") . $frame0;

		if ($result = $this->fetch_im_exec('identify', $execute, true))
		{
			if (empty($result) OR !is_array($result))
			{
				return false;
			}

			do
			{
				$last = array_pop($result);
			}
			while (!empty($result) AND $last == '');

			$temp = explode('###', $last);

			if (count($temp) < 6)
			{
				return false;
			}

			preg_match('#^(\d+)x(\d+)#', $temp[0], $matches);

			$imageinfo = array(
				2         => $temp[3],
				'bits'    => $temp[6],
				'scenes'  => $temp[4],
				'animated' => ($temp[4] > 1),
				'library' => 'IM',
			);

			if (version_compare($this->version, '6.2.6', '>='))
			{
				$imageinfo[0] = $matches[1];
				$imageinfo[1] = $matches[2];
			}
			else	//IM v6.2.5 and lower don't support -laters optimize
			{
				$imageinfo[0] = $temp[1];
				$imageinfo[1] = $temp[2];
			}

			switch($temp[5])
			{
				case 'PseudoClassGray':
				case 'PseudoClassGrayMatte':
				case 'PseudoClassRGB':
				case 'PseudoClassRGBMatte':
					$imageinfo['channels'] = 1;
					break;
				case 'DirectClassRGB':
					$imageinfo['channels'] = 3;
					break;
				case 'DirectClassCMYK':
					$imageinfo['channels'] = 4;
					break;
				default:
					$imageinfo['channels'] = 1;
			}

			return $imageinfo;
		}
		else
		{
			return false;
		}
	}

	/**
	* Private
	* Set image size for convert
	*
	* @param	width	Width of new image
	* @param	height	Height of new image
	* @param	quality Quality of Jpeg images
	* @param bool		Include image dimensions and filesize on thumbnail
	* @param bool		Draw border around thumbnail
	*
	* @return	void
	*/
	function set_convert_options($width = 100, $height = 100, $quality = 75, $labelimage = false, $drawborder = false, $jpegconvert = false, $owidth = null, $oheight = null, $ofilesize = null)
	{
		$this->convertoptions['width'] = $width;
		$this->convertoptions['height'] = $height;
		$this->convertoptions['quality'] = $quality;
		$this->convertoptions['labelimage'] = $labelimage;
		$this->convertoptions['drawborder'] = $drawborder;
		$this->convertoptions['owidth'] = $owidth;
		$this->convertoptions['oheight'] = $oheight;
		$this->convertoptions['ofilesize'] = $ofilesize;
		$this->convertoptions['jpegconvert'] = $jpegconvert;
	}

	/**
	* Private
	* Convert an image
	*
	* @param	string	filename	Image file to convert
	* @param	string	output		Image file to write converted image to
	* @param	string	extension	Filetype
	* @param	boolean	thumbnail	Generate a thumbnail for display in a browser
	* @param	boolean	sharpen		Sharpen the output
	*
	* @return	mixed
	*/
	function fetch_converted_image($filename, $output, $imageinfo, $thumbnail = true, $sharpen = true)
	{
		$execute = '';

		if ($thumbnail)
		{
			// Only specify scene 1 if this is a PSD or a PDF -- allows animated gifs to be resized..
			$execute .= (in_array($imageinfo[2], array('PDF', 'PSD'))) ? " \"{$filename}\"[0] " : " \"$filename\"";
		}
		else
		{
			$execute .= " \"$filename\"";
		}

		if ($imageinfo['scenes'] > 1 AND version_compare($this->version, '6.2.6', '>='))
		{
			$execute .= ' -coalesce ';
		}

		if ($this->convertoptions['width'] > 0 OR $this->convertoptions['height'] > 0)
		{
			if ($this->convertoptions['width'])
			{
				$size = $this->convertoptions['width'];
				if ($this->convertoptions['height'])
				{
					$size .= 'x' . $this->convertoptions['height'];
				}
			}
			else if ($this->convertoptions['height'])
			{
				$size .= 'x' . $this->convertoptions['height'];
			}
			$execute .= " -size $size ";
		}

		if ($thumbnail)
		{
			if ($size)
			{	// have to use -thumbnail here .. -sample looks BAD for animated gifs
				$execute .= " -thumbnail \"$size>\" ";
			}
		}
		$execute .= ($sharpen AND $imageinfo[2] == 'JPEG') ? " -sharpen 0x1 " : '';

		if ($imageinfo['scenes'] > 1 AND version_compare($this->version, '6.2.6', '>='))
		{
			$execute .= ' -layers optimize ';
		}

		// ### Convert a CMYK jpg to RGB since IE/Firefox will not display CMYK inline .. conversion is ugly since we don't specify profiles
		if ($this->imageinfo['channels'] == 4 AND $thumbnail)
		{
			$execute .= ' -colorspace RGB ';
		}

		if ($thumbnail)
		{
			$xratio = ($this->convertoptions['width'] == 0 OR $imageinfo[0] <= $this->convertoptions['width']) ? 1 : $imageinfo[0] / $this->convertoptions['width'];
			$yratio = ($this->convertoptions['height'] == 0 OR $imageinfo[1] <= $this->convertoptions['height']) ? 1 : $imageinfo[1] / $this->convertoptions['height'];

			if ($xratio > $yratio)
			{
				$new_width = round($imageinfo[0] / $xratio) - 1;
				$new_height = round($imageinfo[1] / $xratio) - 1;
			}
			else
			{
				$new_width = round($imageinfo[0] / $yratio) - 1;
				$new_height = round($imageinfo[1] / $yratio) - 1;
			}

#			if ($imageinfo[0] <= $this->convertoptions['width'] AND $imageinfo[1] <= $this->convertoptions['height'])
#			{
#				$this->convertoptions['labelimage'] = false;
#				$this->convertoptions['drawborder'] = false;
#			}

			if ($this->convertoptions['labelimage'])
			{
				if ($this->convertoptions['owidth'])
				{
					$dimensions = "{$this->convertoptions['owidth']}x{$this->convertoptions['oheight']}";
				}
				else
				{
					$dimensions = "$imageinfo[0]x$imageinfo[1]";
				}
				if ($this->convertoptions['ofilesize'])
				{
					$filesize = $this->convertoptions['ofilesize'];
				}
				else
				{
					$filesize = @filesize($filename);
				}
				if ($filesize / 1024 < 1)
				{
					$filesize = 1024;
				}
				$sizestring = (!empty($filesize)) ? number_format($filesize / 1024, 0, '', '') . 'kb' : '';

				if (!$this->convertoptions['jpegconvert'] OR $imageinfo[2] == 'PSD' OR $imageinfo[2] == 'PDF')
				{
					$type = $imageinfo[2];
				}
				else
				{
					$type = 'JPEG';
				}

				if (($new_width / strlen("$dimensions $sizestring $type")) >= 6)
				{
					$finalstring = "$dimensions $sizestring $type";
				}
				else if  (($new_width / strlen("$dimensions $sizestring")) >= 6)
				{
					$finalstring = "$dimensions $sizestring";
				}
				else if (($new_width / strlen($dimensions)) >= 6)
				{
					$finalstring = $dimensions;
				}
				else if (($new_width / strlen($sizestring)) >= 6)
				{
					$finalstring = $sizestring;
				}

				if ($finalstring)
				{	// confusing -flip statements added to workaround an issue with very wide yet short images. See http://www.imagemagick.org/discourse-server/viewtopic.php?t=10367
					$execute .= " -flip -background \"{$this->thumbcolor}\" -splice 0x15 -flip -gravity South -fill white  -pointsize 11 -annotate 0 \"$finalstring\" ";
				}
			}

			if ($this->convertoptions['drawborder'])
			{
				$execute .= " -bordercolor \"{$this->thumbcolor}\" -compose Copy -border 1 ";
			}

			if (($imageinfo[2] == 'PNG' OR $imageinfo[2] == 'PSD') AND !$this->convertoptions['jpegconvert'])
			{
				$execute .= " -depth 8 -quality {$this->convertoptions['quality']} PNG:";
			}
			else if ($this->fetch_must_convert($imageinfo[2]) OR $imageinfo[2] == 'JPEG' OR $this->convertoptions['jpegconvert'])
			{
				$execute .= " -quality {$this->convertoptions['quality']} JPEG:";
			}
			else if ($imageinfo[2] == 'GIF')
			{
				$execute .= " -depth $imageinfo[bits] ";
			}
		}

		$execute .= "\"$output\"";

		if ($zak = $this->fetch_im_exec('convert', $execute))
		{
			return $zak;
		}
		else if ($sharpen AND !empty($this->error[0]) AND strpos($this->error[0], 'image smaller than radius') !== false)
		{	// try to resize again, but without sharpen
			$this->error = '';
			return $this->fetch_converted_image($filename, $output, $imageinfo, $thumbnail, false);
		}
		else
		{
			return false;
		}
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_image_info($filename)
	{
		if (!$this->verify_image_file($filename))
		{
			return false;
		}

		$this->identifyformat = '%g###%w###%h###%m###%n###%r###%z###';
		$this->imageinfo = $this->fetch_identify_info($filename);
		return $this->imageinfo;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_thumbnail($filename, $location, $maxwidth = 100, $maxheight = 100, $quality = 75, $labelimage = false, $drawborder = false, $jpegconvert = false, $sharpen = true, $owidth = null, $oheight = null, $ofilesize = null)
	{
		$thumbnail = array(
			'filedata'   => '',
			'filesize'   => 0,
			'dateline'   => 0,
			'imageerror' => '',
		);

		if ($this->is_valid_thumbnail_extension(file_extension($filename)))
		{
			if ($imageinfo = $this->fetch_image_info($location))
			{
				$thumbnail['source_width'] = $imageinfo[0];
				$thumbnail['source_height'] = $imageinfo[1];
				if ($this->fetch_imagetype_from_extension(file_extension($filename)) != $imageinfo[2])
				{
					$thumbnail['imageerror'] = 'thumbnail_notcorrectimage';
				}
				else if ($imageinfo[0] > $maxwidth OR $imageinfo[1] > $maxheight OR $this->fetch_must_convert($imageinfo[2]))
				{
					if ($this->registry->options['safeupload'])
					{
						$tmpname = $this->registry->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->registry->userinfo['userid']);
					}
					else
					{
						if (!($tmpname = @tempnam(ini_get('upload_tmp_dir'), 'vbthumb')))
						{
							$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
							return $thumbnail;
						}
					}

					$this->set_convert_options($maxwidth, $maxheight, $quality, $labelimage, $drawborder, $jpegconvert, $owidth, $oheight, $ofilesize);
					if ($result = $this->fetch_converted_image($location, $tmpname, $imageinfo, true, $sharpen))
					{
						if ($imageinfo = $this->fetch_image_info($tmpname))
						{
							$thumbnail['width'] = $imageinfo[0];
							$thumbnail['height'] = $imageinfo[1];
						}
						$extension = strtolower(file_extension($filename));
						if ($jpegconvert)
						{
							$thumbnail['filename'] = preg_replace('#' . preg_quote(file_extension($filename), '#') . '$#', 'jpg', $filename);
						}
						$thumbnail['filesize'] = filesize($tmpname);
						$thumbnail['dateline'] = TIMENOW;
						$thumbnail['filedata'] = file_get_contents($tmpname);
					}
					else
					{
						$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
					}
					@unlink($tmpname);
				}
				else
				{
					if ($imageinfo[0] > 0 AND $imageinfo[1] > 0)
					{
						$thumbnail['filedata'] = @file_get_contents($location);
						$thumbnail['width'] = $imageinfo[0];
						$thumbnail['height'] = $imageinfo[1];
						$thumbnail['imageerror'] = 'thumbnailalready';
					}
					else
					{
						$thumbnail['filedata'] = '';
						$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
					}
				}
			}
			else
			{
				$thumbnail['filedata'] = '';
				$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
			}
		}

		if (!empty($thumbnail['filedata']))
		{
			$thumbnail['filesize'] = strlen($thumbnail['filedata']);
			$thumbnail['dateline'] = TIMENOW;
		}
		return $thumbnail;
	}

	/**
	* See function definition in vB_Image_Abstract
	*/
	function print_image_from_string($string, $moveabout = true)
	{
		if ($this->registry->options['safeupload'])
		{
			$tmpname = $this->registry->options['tmppath'] . '/' . md5(uniqid(microtime()) . $this->registry->userinfo['userid']);
		}
		else
		{
			if (!($tmpname = @tempnam(ini_get('upload_tmp_dir'), 'vb')))
			{
				echo 'Could not create temporary file.';
				return false;
			}
		}

		// Command start for no background image
		$execute = ' -size 201x61 xc:white ';

		$fonts =& $this->fetch_regimage_fonts();
		if ($moveabout)
		{
			$backgrounds =& $this->fetch_regimage_backgrounds();

			if (!empty($backgrounds))
			{
				$index = mt_rand(0, count($backgrounds) - 1);
				$background = $backgrounds["$index"];

				// replace Command start with background image
				$execute = " \"$background\" -resize 201x61! -swirl " . mt_rand(10, 100);

				// randomly rotate the background image 180 degrees
				$execute .= (TIMENOW & 2) ? ' -rotate 180 ' : '';
			}

			// Randomly move the letters up and down
			for ($x = 0; $x < strlen($string); $x++)
			{
				if (!empty($fonts))
				{
					$index = mt_rand(0, count($fonts) - 1);
					if ($this->regimageoption['randomfont'])
					{
						$font = $fonts["$index"];
					}
					else
					{
						if (!$font)
						{
							$font = $fonts["$index"];
						}
					}
				}
				else
				{
					$font = 'Helvetica';
				}

				if ($this->regimageoption['randomshape'])
					{
					// Stroke Width, 1 or 2
					$strokewidth = mt_rand(1, 2);
					// Pick a random color
					$r = mt_rand(50, 200);
					$b = mt_rand(50, 200);
					$g = mt_rand(50, 200);
					// Pick a Shape

					$x1 = mt_rand(0, 200);
					$y1 = mt_rand(0, 60);
					$x2 = mt_rand(0, 200);
					$y2 = mt_rand(0, 60);
					$start = mt_rand(0, 360);
					$end = mt_rand(0, 360);
					switch(mt_rand(1, 5))
					{
						case 1:
							$shape = "\"roundrectangle $x1,$y1 $x2,$y2 $start,end\"";
							break;
						case 2:
							$shape = "\"arc $x1,$y1 $x2,$y2 20,15\"";
							break;
						case 3:
							$shape = "\"ellipse $x1,$y1 $x2,$y2 $start,$end\"";
							break;
						case 4:
							$shape = "\"line $x1,$y1 $x2,$y2\"";
							break;
						case 5:
							$x3 = mt_rand(0, 200);
							$y3 = mt_rand(0, 60);
							$x4 = mt_rand(0, 200);
							$y4 = mt_rand(0, 60);
							$shape = "\"polygon $x1,$y1 $x2,$y2 $x3,$y3 $x4,$y4\"";
							break;
					}
					// before or after
					$place = mt_rand(1, 2);

					$finalshape = " -flatten -stroke \"rgb($r,$b,$g)\" -strokewidth $strokewidth -fill none -draw $shape -stroke none ";

					if ($place == 1)
					{
						$execute .= $finalshape;
					}
				}

				$slant = (($x <= 1 OR $x == 5) AND $this->regimageoption['randomslant']) ? true : false;
				$execute .= $this->annotate($string["$x"], $font, $slant, true);

				if ($this->regimageoption['randomshape'] AND $place == 2)
				{
					$execute .= $finalshape;
				}
			}
		}
		else
		{
			if (!empty($fonts))
			{
				$font = $fonts[0];
			}
			else
			{
				$font = 'Helvetica';
			}
			$execute .= $this->annotate("\"$string\"", $font, false, false);
		}

		// Swirl text, stroke inner border of 1 pixel and output as GIF
		$execute .= ' -flatten ';

		$execute .= ($moveabout AND $this->regimageoption['randomslant']) ? ' -swirl 20 ' : '';
		$execute .= " -stroke black -strokewidth 1 -fill none -draw \"rectangle 0,60 200,0\" -depth 8 PNG:\"$tmpname\"";

		if ($result = $this->fetch_im_exec('convert', $execute))
		{
			header('Content-disposition: inline; filename=image.png');
			header('Content-transfer-encoding: binary');
			header('Content-Type: image/png');
			if ($filesize = @filesize($tmpname))
			{	// this is here because of a stupid Win32 CGI thingymajig. filesize fails but readfile works, go figure
				header("Content-Length: $filesize");
			}
			readfile($tmpname);
			@unlink($tmpname);
		}
		else
		{
			echo htmlspecialchars_uni($this->fetch_error());
			@unlink($tmpname);
			return false;
		}
	}

	/**
	* Private
	* Return a letter position command
	*
	* @param	string	letter	Character to position
	*
	* @return	string
	*/
	function annotate($letter, $font, $slant = false, $random = true)
	{
		// Start position
		static $r, $g, $b, $position = 10;

		// Character Slant
		static $slants = array(
			'0x0',     # Normal
			'0x30',    # Slant Right
			'20x20',   # Slant Down
			'315x315', # Slant Up
			'45x45',
			'0x330',
		);

		// Can't use slants AND swirl at the same time, it just looks bad ;)
		if ($slant)
		{
			$coord = mt_rand(1, count($slants) - 1);
			$coord = $slants["$coord"];
		}
		else
		{
			$coord = $slants[0];
		}

		if ($random)
		{
			// Y Axis position, random from 32 to 48
			$y = mt_rand(32, 48);

			if ($this->regimageoption['randomcolor'] OR empty($r))
			{
				// Generate a random color..
				$r = mt_rand(50, 200);
				$b = mt_rand(50, 200);
				$g = mt_rand(50, 200);
			}

			$pointsize = $this->regimageoption['randomsize'] ? mt_rand(28, 36) : 32;
		}
		else
		{
			$y = 40;
			$pointsize = 32;
			$r = $b = $g = 0;
		}

		$output = " -font \"$font\" -pointsize $pointsize -fill \"rgb($r,$b,$g)\" -annotate $coord+$position+$y $letter ";
		$position += rand(25, 35);

		return $output;

	}
}

/**
* Image class for GD Image Library
*
* @package 		vBulletin
* @version		$Revision: 37230 $
* @date 		$Date: 2010-05-28 11:50:59 -0700 (Fri, 28 May 2010) $
*
*/
class vB_Image_GD extends vB_Image_Abstract
{
	/**
	* @var string
	*
	*/
	var $thumbcolor = array(
		'r' => 0,
		'b' => 0,
		'g' => 0
	);

	/**
	* Constructor. Sets up resizable types, extensions, etc.
	*
	* @return	void
	*/
	function vB_Image_GD(&$registry)
	{
		parent::vB_Image_Abstract($registry);

		$this->info_extensions = array(
			'gif'  => true,
			'jpg'  => true,
			'jpe'  => true,
			'jpeg' => true,
			'png'  => true,
			'psd'  => true,
			'bmp'  => true,
			'tiff' => true,
			'tif'  => true,
		);

		$this->thumb_extensions = array(
			'gif'  => true,
			'jpg'  => true,
			'jpe'  => true,
			'jpeg' => true,
			'png'  => true,
		);

		$this->resize_types = array(
			'JPEG' => true,
			'PNG'  => true,
			'GIF'  => true,
		);

		if (preg_match('~#?([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2})~i', $this->registry->options['thumbcolor'], $match))
		{
			$this->thumbcolor = array(
				'r' => hexdec($match[1]),
				'g' => hexdec($match[2]),
				'b' => hexdec($match[3])
			);
		}
	}

	/**
	* Private
	* Output an image
	*
	* @param	object	filename		Image file to convert
	* @param	int		output		Image file to write converted image to
	* @param	bool		headers		Generate image header
	* @param	int		quality		Jpeg Quality
	*
	* @return	void
	*/
	// ###################### Start print_image #######################
	function print_image(&$image, $type = 'JPEG', $headers = true, $quality = 75)
	{
		// Determine what image type to output
		switch($type)
		{
			case 'GIF':
				if (!IMAGEGIF)
				{
					if (IMAGEJPEG)
					{
						$type = 'JPEG';
					}
					else if (IMAGEPNG)
					{
						$type = 'PNG';
					}
					else // nothing!
					{
						imagedestroy($image);
						return false;
					}
				}
				break;

			case 'PNG':
				if (!IMAGEPNG)
				{
					if (IMAGEJPEG)
					{
						$type = 'JPEG';
					}
					else if (IMAGEGIF)
					{
						$type = 'GIF';
					}
					else // nothing!
					{
						imagedestroy($image);
						return false;
					}
				}
				break;

			default:	// JPEG
				if (!IMAGEJPEG)
				{
					if (IMAGEGIF)
					{
						$type = 'GIF';
					}
					else if (IMAGEPNG)
					{
						$type = 'PNG';
					}
					else // nothing!
					{
						imagedestroy($image);
						return false;
					}
				}
				else
				{
					$type = 'JPEG';
				}
				break;
		}

		/* If you are calling print_image inside ob_start in order to capture the image
			remember any headers still get sent to the browser. Mozilla is not happy with this */

		switch ($type)
		{
			case 'GIF':
				if ($headers)
				{
					header('Content-transfer-encoding: binary');
					header('Content-disposition: inline; filename=image.gif');
					header('Content-type: image/gif');
				}
				imagegif($image);
				imagedestroy($image);
				return 'gif';

			case 'PNG':
				if ($headers)
				{
					header('Content-transfer-encoding: binary');
					header('Content-disposition: inline; filename=image.png');
					header('Content-type: image/png');
				}
				imagepng($image);
				imagedestroy($image);
				return 'png';

			case 'JPEG':
				if ($headers)
				{
					header('Content-transfer-encoding: binary');
					header('Content-disposition: inline; filename=image.jpg');
					header('Content-type: image/jpeg');
				}
				imagejpeg($image, '', $quality);
				imagedestroy($image);
				return 'jpg';

			default:
				imagedestroy($image);
				return false;
		}
	}

	////////////////////////////////////////////////////////////////////////////////////////////////
	////
	////                  p h p U n s h a r p M a s k
	////
	////		Original Unsharp mask algorithm by Torstein Hønsi 2003.
	////		thoensi@netcom.no
	////		Formatted for vBulletin usage by Freddie Bingham
	////
	///////////////////////////////////////////////////////////////////////////////////////////////
	/**
	* Private
	* Sharpen an image
	*
	* @param	object		finalimage
	* @param	int			float
	* @param	radius		float
	* @param	threshold	float
	*
	* @return	void
	*/
	function unsharpmask(&$finalimage, $amount = 50, $radius = 1, $threshold = 0)
	{
		// $finalimg is an image that is already created within php using
		// imgcreatetruecolor. No url! $img must be a truecolor image.

		// Attempt to calibrate the parameters to Photoshop:
		if ($amount > 500)
		{
			$amount = 500;
		}
		$amount = $amount * 0.016;
		if ($radius > 50)
		{
			$radius = 50;
		}
		$radius = $radius * 2;
		if ($threshold > 255)
		{
			$threshold = 255;
		}

		$radius = abs(round($radius)); 	// Only integers make sense.
		if ($radius == 0)
		{
			return true;
		}

		$w = imagesx($finalimage);
		$h = imagesy($finalimage);
		$imgCanvas = imagecreatetruecolor($w, $h);
		$imgBlur = imagecreatetruecolor($w, $h);

		// Gaussian blur matrix:
		//
		//	1	2	1
		//	2	4	2
		//	1	2	1
		//
		//////////////////////////////////////////////////

		$gdinfo = gd_info();
		if (function_exists('imageconvolution') && strstr($gdinfo['GD Version'], 'bundled'))
		{
			$matrix = array(
				array( 1, 2, 1 ),
				array( 2, 4, 2 ),
				array( 1, 2, 1 )
			);
			imagecopy ($imgBlur, $finalimage, 0, 0, 0, 0, $w, $h);
			imageconvolution($imgBlur, $matrix, 16, 0);
		}
		else
		{
			// Move copies of the image around one pixel at the time and merge them with weight
			// according to the matrix. The same matrix is simply repeated for higher radii.
			for ($i = 0; $i < $radius; $i++)
			{
				imagecopy ($imgBlur, $finalimage, 0, 0, 1, 0, $w - 1, $h); // left
				imagecopymerge ($imgBlur, $finalimage, 1, 0, 0, 0, $w, $h, 50); // right
				imagecopymerge ($imgBlur, $finalimage, 0, 0, 0, 0, $w, $h, 50); // center
				imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);

				imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up
				imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down
			}
		}

		if($threshold > 0)
		{
			// Calculate the difference between the blurred pixels and the original
			// and set the pixels
			for ($x = 0; $x < $w - 1; $x++) // each row
			{
				for ($y = 0; $y < $h; $y++) // each pixel
				{
					$rgbOrig = ImageColorAt($finalimage, $x, $y);
					$rOrig = (($rgbOrig >> 16) & 0xFF);
					$gOrig = (($rgbOrig >> 8) & 0xFF);
					$bOrig = ($rgbOrig & 0xFF);

					$rgbBlur = ImageColorAt($imgBlur, $x, $y);

					$rBlur = (($rgbBlur >> 16) & 0xFF);
					$gBlur = (($rgbBlur >> 8) & 0xFF);
					$bBlur = ($rgbBlur & 0xFF);

					// When the masked pixels differ less from the original
					// than the threshold specifies, they are set to their original value.
					$rNew = (abs($rOrig - $rBlur) >= $threshold) ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig)) : $rOrig;

					$gNew = (abs($gOrig - $gBlur) >= $threshold) ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig)) : $gOrig;

					$bNew = (abs($bOrig - $bBlur) >= $threshold) ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig)) : $bOrig;



					if (($rOrig != $rNew) OR ($gOrig != $gNew) OR ($bOrig != $bNew))
					{
					    $pixCol = ImageColorAllocate($finalimage, $rNew, $gNew, $bNew);
					    ImageSetPixel($finalimage, $x, $y, $pixCol);
					}
				}
			}
		}
		else
		{
			for ($x = 0; $x < $w; $x++) // each row
			{
				for ($y = 0; $y < $h; $y++) // each pixel
				{
					$rgbOrig = ImageColorAt($finalimage, $x, $y);
					$rOrig = (($rgbOrig >> 16) & 0xFF);
					$gOrig = (($rgbOrig >> 8) & 0xFF);
					$bOrig = ($rgbOrig & 0xFF);

					$rgbBlur = ImageColorAt($imgBlur, $x, $y);

					$rBlur = (($rgbBlur >> 16) & 0xFF);
					$gBlur = (($rgbBlur >> 8) & 0xFF);
					$bBlur = ($rgbBlur & 0xFF);

					$rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
					if ($rNew > 255)
					{
						$rNew = 255;
					}
					elseif ($rNew < 0)
					{
						$rNew = 0;
					}

					$gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
					if ($gNew > 255)
					{
						$gNew = 255;
					}
					elseif ($gNew < 0)
					{
						$gNew = 0;
					}

					$bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
					if ($bNew > 255)
					{
						$bNew = 255;
					}
					elseif ($bNew < 0)
					{
						$bNew = 0;
					}

					$rgbNew = ($rNew << 16) + ($gNew << 8) + $bNew;
					ImageSetPixel($finalimage, $x, $y, $rgbNew);
				}
			}
		}
		imagedestroy($imgCanvas);
		imagedestroy($imgBlur);

		return true;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function print_image_from_string($string, $moveabout = true)
	{
		$image_width = 201;
		$image_height = 61;

		$backgrounds = $this->fetch_regimage_backgrounds();

		if ($moveabout)
		{
			$notdone = true;

			while ($notdone AND !empty($backgrounds))
			{
				$index = mt_rand(0, count($backgrounds) - 1);
				$background = $backgrounds["$index"];
				switch(strtolower(file_extension($background)))
				{
					case 'jpg':
					case 'jpe':
					case 'jpeg':
						if (!function_exists('imagecreatefromjpeg') OR !$image = @imagecreatefromjpeg($background))
						{
							unset($backgrounds["$index"]);
						}
						else
						{
							$notdone = false;
						}
						break;
					case 'gif':
						if (!function_exists('imagecreatefromgif') OR !$image = @imagecreatefromgif($background))
						{
							unset($backgrounds["$index"]);
						}
						else
						{
							$notdone = false;
						}
						break;
					case 'png':
						if (!function_exists('imagecreatefrompng') OR !$image = @imagecreatefrompng($background))
						{
							unset($backgrounds["$index"]);
						}
						else
						{
							$notdone = false;
						}
						break;
				}
				sort($backgrounds);
			}
		}

		if ($image)
		{
			// randomly flip
			if (TIMENOW & 2)
			{
				$image =& $this->flipimage($image);
			}
			$gotbackground = true;
		}
		else
		{
			$image =& $this->fetch_image_resource($image_width, $image_height);
		}

		if (function_exists('imagettftext') AND $fonts = $this->fetch_regimage_fonts())
		{
			if ($moveabout)
			{
				// Randomly move the letters up and down
				for ($x = 0; $x < strlen($string); $x++)
				{
					$index = mt_rand(0, count($fonts) - 1);
					if ($this->regimageoption['randomfont'])
					{
						$font = $fonts["$index"];
					}
					else
					{
						if (empty($font))
						{
							$font = $fonts["$index"];
						}
					}
					$image = $this->annotatettf($image, $string["$x"], $font);
				}
			}
			else
			{
				$image = $this->annotatettf($image, $string, $fonts[0], false);
			}
		}

		if ($moveabout)
		{
			$blur = .9;
			/*if (function_exists('imagefilter'))
			{
				#if (!@imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR))
				{
			#		$image =& $this->blur($image, $blur);
				}
			}
			else
			{
			#	$image =& $this->blur($image, $blur);
			}*/
		}

		$text_color = imagecolorallocate($image, 0, 0, 0);

		// draw a border
		imageline($image, 0, 0, $image_width, 0, $text_color);
		imageline($image, 0, 0, 0, $image_height, $text_color);
		imageline($image, $image_width - 1, 0, $image_width - 1, $image_height, $text_color);
		imageline($image, 0, $image_height - 1, $image_width, $image_height - 1, $text_color);

		$this->print_image($image, 'JPEG', true, 100);
	}

	/**
	* Private
	* Create blank image
	*
	* @param int width	Width of image
	* @param int height	Height of image
	*
	* @return resource
	*/
	function &fetch_image_resource($width, $height)
	{
		$image = imagecreatetruecolor($width, $height);
		$background_color = imagecolorallocate($image, 255, 255, 255); //white background
		imagefill($image, 0, 0, $background_color); // For GD2+

		return $image;
	}

	/**
	* Private
	* Return a letter position command
	*
	* @param	resource	image		Image to annotate
	* @param	string	letter	Character to position
	* @param boolean	random 	Apply effects
	*
	* @return	string
	*/
	function &annotategd($image, $letter, $random = true)
	{

		// Start position
		static $r, $g, $b, $xposition = 10;

		if ($random)
		{
			if ($this->regimageoption['randomcolor'] OR empty($r))
			{
				// Generate a random color..
				$r = mt_rand(50, 200);
				$b = mt_rand(50, 200);
				$g = mt_rand(50, 200);
			}

			$yposition = mt_rand(0, 5);

			$text_color = imagecolorallocate($image, $r, $g, $b);
			imagechar($image, 5, $xposition, $yposition, $letter, $text_color);
			$xposition += mt_rand(10, 25);
		}
		else
		{
			$text_color = imagecolorallocate($image, 0, 0, 0);
			$yposition = 2;
			imagechar($image, 5, $xposition, $yposition, $letter, $text_color);
			$xposition += 10;
		}

		return $image;
	}

	/**
	* Private
	* Return a letter position command
	*
	* @param	resource	image		Image to annotate
	* @param	string	letter	Character to position
	* @param string	font		Font to annotate (path)
	* @param boolean	slant		Slant fonts left or right
	* @param boolean	random 	Apply effects
	*
	* @return	string
	*/
	function annotatettf($image, $letter, $font, $random = true)
	{
		if ($random)
		{
			// Start position
			static $r, $g, $b, $position = 15;

			// Y Axis position, random from 35 to 48
			$y = mt_rand(35, 48);

			if ($this->regimageoption['randomcolor'] OR empty($r))
			{
				// Generate a random color..
				$r = mt_rand(50, 200);
				$b = mt_rand(50, 200);
				$g = mt_rand(50, 200);
			}

			if ($this->regimageoption['randomshape'])
			{
				if (function_exists('imageantialias'))
				{	// See http://bugs.php.net/bug.php?id=28147
					imageantialias($image, true);
				}
				// Stroke Width, 2 or 3
				imagesetthickness($image, mt_rand(2, 3));
				// Pick a random color
				$shapecolor = imagecolorallocate($image, mt_rand(50, 200), mt_rand(50, 200), mt_rand(50, 200));

				// Pick a Shape
				$x1 = mt_rand(0, 200);
				$y1 = mt_rand(0, 60);
				$x2 = mt_rand(0, 200);
				$y2 = mt_rand(0, 60);
				$start = mt_rand(0, 360);
				$end = mt_rand(0, 360);
				switch(mt_rand(1, 4))
				{
					case 1:
						imagearc($image, $x1, $y1, $x2, $y2, $start, $end, $shapecolor);
						break;
					case 2:
						imageellipse($image, $x1, $y1, $x2, $y2, $shapecolor);
						break;
					case 3:
						imageline($image, $x1, $y1, $x2, $y2, $shapecolor);
						break;
					case 4:
						imagepolygon($image, array(
							$x1, $y1,
							$x2, $y2,
							mt_rand(0, 200), mt_rand(0, 60),
							mt_rand(0, 200), mt_rand(0, 60),
							),
							4, $shapecolor
						);
						break;
				}
			}

			// Angle
			$slant = $this->regimageoption['randomslant'] ? mt_rand(-20, 60) : 0;
			$pointsize =  $this->regimageoption['randomsize'] ? mt_rand(20, 32) : 24;
			$text_color = imagecolorallocate($image, $r, $g, $b);
		}
		else
		{
			$position = 10;
			$y = 40;
			$slant = 0;
			$pointsize =  24;
			$text_color = imagecolorallocate($image, 0, 0, 0);
		}

		if (!$result = @imagettftext($image, $pointsize, $slant, $position, $y, $text_color, $font, $letter))
		{
			return false;
		}
		else
		{
			$position += rand(25, 35);
			return $image;
		}
	}

	/**
	* Private
	* mirror an image horizontally. Can be extended to other flips but this is all we need for now
	*
	* @param	image	image			Image file to convert
	*
	* @return	object	image
	*/
	function &flipimage(&$image)
	{
		$width = imagesx($image);
		$height = imagesy($image);

		$output = imagecreatetruecolor($width, $height);

		for($x = 0; $x < $height; $x++)
		{
			imagecopy($output, $image, 0, $height - $x - 1, 0, $x, $width, 1);
      }

		return $output;
	}

	/**
	* Private
	* Apply a swirl/twirl filter to an image
	*
	* @param	image	image			Image file to convert
	* @param	float	output			Degree of twirl
	* @param	bool	randirection	Randomize direction of swirl (clockwise/counterclockwise)
	*
	* @return	object	image
	*/
	function &swirl(&$image, $degree = .005, $randirection = true)
	{
		$image_width = imagesx($image);
		$image_height = imagesy($image);

		$temp = imagecreatetruecolor($image_width, $image_height);

		if ($randirection)
		{
			$degree = (mt_rand(0, 1) == 1) ? $degree : $degree * -1;
		}

		$middlex = floor($image_width / 2);
		$middley = floor($image_height / 2);

		for ($x = 0; $x < $image_width; $x++)
		{
			for ($y = 0; $y < $image_height; $y++)
			{
				$xx = $x - $middlex;
				$yy = $y - $middley;

				$theta = atan2($yy, $xx);

				$radius = sqrt($xx * $xx + $yy * $yy);

				$radius -= 5;

				$newx = $middlex + ($radius * cos($theta + $degree * $radius));
				$newy = $middley + ($radius * sin($theta + $degree * $radius));

				if (($newx > 0 AND $newx < $image_width) AND ($newy > 0 AND $newy < $image_height))
				{
					$index = imagecolorat($image, $newx, $newy);
					$colors = imagecolorsforindex($image, $index);
					$color = imagecolorresolve($temp, $colors['red'], $colors['green'], $colors['blue']);
				}
				else
				{
					$color = imagecolorresolve($temp, 255, 255, 255);
				}

				imagesetpixel($temp, $x, $y, $color);
			}
		}

		return $temp;
	}

	/**
	* Private
	* Apply a wave filter to an image
	*
	* @param	image	image			Image  to convert
	* @param	int		wave			Amount of wave to apply
	* @param	bool	randirection	Randomize direction of wave
	*
	* @return	image
	*/
	function &wave(&$image, $wave = 10, $randirection = true)
	{
		$image_width = imagesx($image);
		$image_height = imagesy($image);

		$temp = imagecreatetruecolor($image_width, $image_height);

		if ($randirection)
		{
			$direction = (TIMENOW & 2) ? true : false;
		}

		$middlex = floor($image_width / 2);
		$middley = floor($image_height / 2);

		for ($x = 0; $x < $image_width; $x++)
		{
			for ($y = 0; $y < $image_height; $y++)
			{

				$xo = $wave * sin(2 * 3.1415 * $y / 128);
				$yo = $wave * cos(2 * 3.1415 * $x / 128);

				if ($direction)
				{
					$newx = $x - $xo;
					$newy = $y - $yo;
				}
				else
				{
					$newx = $x + $xo;
					$newy = $y + $yo;
				}

				if (($newx > 0 AND $newx < $image_width) AND ($newy > 0 AND $newy < $image_height))
				{
					$index = imagecolorat($image, $newx, $newy);
               $colors = imagecolorsforindex($image, $index);
               $color = imagecolorresolve($temp, $colors['red'], $colors['green'], $colors['blue']);
				}
				else
				{
					$color = imagecolorresolve($temp, 255, 255, 255);
				}

				imagesetpixel($temp, $x, $y, $color);
			}
		}

		return $temp;
	}

	/**
	* Private
	* Apply a blur filter to an image
	*
	* @param	image	image			Image  to convert
	* @param	int		radius			Radius of blur
	*
	* @return	image
	*/
	function &blur(&$image, $radius = .5)
	{
		$radius = ($radius > 50) ? 100 : abs(round($radius * 2));

		if ($radius == 0)
		{
			return $image;
		}

		$w = imagesx($image);
		$h = imagesy($image);


		$imgCanvas = imagecreatetruecolor($w, $h);
		$imgBlur = imagecreatetruecolor($w, $h);
		imagecopy ($imgCanvas, $image, 0, 0, 0, 0, $w, $h);

		// Gaussian blur matrix:
		//
		//	1	2	1
		//	2	4	2
		//	1	2	1
		//
		//////////////////////////////////////////////////

		// Move copies of the image around one pixel at the time and merge them with weight
		// according to the matrix. The same matrix is simply repeated for higher radii.
		for ($i = 0; $i < $radius; $i++)
		{
			imagecopy($imgBlur, $imgCanvas, 0, 0, 1, 1, $w - 1, $h - 1); // up left
			imagecopymerge($imgBlur, $imgCanvas, 1, 1, 0, 0, $w, $h, 50); // down right
			imagecopymerge($imgBlur, $imgCanvas, 0, 1, 1, 0, $w - 1, $h, 33.33333); // down left
			imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 1, $w, $h - 1, 25); // up right
			imagecopymerge($imgBlur, $imgCanvas, 0, 0, 1, 0, $w - 1, $h, 33.33333); // left
			imagecopymerge($imgBlur, $imgCanvas, 1, 0, 0, 0, $w, $h, 25); // right
			imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 20 ); // up
			imagecopymerge($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 16.666667); // down
			imagecopymerge($imgBlur, $imgCanvas, 0, 0, 0, 0, $w, $h, 50); // center
			imagecopy($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);
		}
		imagedestroy($imgBlur);
		return $imgCanvas;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_image_info($filename)
	{
		static $types = array(
			1 => 'GIF',
			2 => 'JPEG',
			3 => 'PNG',
			4 => 'SWF',
			5 => 'PSD',
			6 => 'BMP',
			7 => 'TIFF',
			8 => 'TIFF',
			9 => 'JPC',
			10=> 'JP2',
			11=> 'JPX',
			12=> 'JB2',
			13=> 'SWC',
			14=> 'IFF',
			15=> 'WBMP',
			16=> 'XBM',
		);

		if (!$this->verify_image_file($filename))
		{
			return false;
		}

		// use PHP's getimagesize if it works
		if ($imageinfo = getimagesize($filename))
		{
 			$this->imageinfo = array(
				0          => $imageinfo[0],
				1          => $imageinfo[1],
				2          => $types["$imageinfo[2]"],
				'channels' => $imageinfo['channels'],
				'bits'     => $imageinfo['bits'],
				'scenes'   => 1,
				'library'  => 'GD',
				'animated' => false,
			);

			if ($this->imageinfo[2] == 'GIF')
			{	// get scenes
				$data = file_get_contents($filename);
				// Look for a Global Color table char and the Image seperator character
				// The scene count could be broken, see #26591
				$this->imageinfo['scenes'] = count(preg_split('#\x00[\x00-\xFF]\x00\x2C#', $data)) - 1;

				$this->imageinfo['animated']  = (strpos($data, 'NETSCAPE2.0') !== false);
				unset($data);
			}

			return $this->imageinfo;
		}
		// getimagesize barfs on some jpegs but we can try to create an image to find the dimensions
		else if (function_exists('imagecreatefromjpeg') AND $img = @imagecreatefromjpeg($filename))
		{
			$this->imageinfo = array(
				0          => imagesx($img),
				1          => imagesy($img),
				2          => 'JPEG',
				'channels' => 3,
				'bits'     => 8,
				'library'  => 'GD',
			);
			imagedestroy($img);

			return $this->imageinfo;
		}
		else
		{
			return false;
		}
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_thumbnail($filename, $location, $maxwidth = 100, $maxheight = 100, $quality = 75, $labelimage = false, $drawborder = false, $jpegconvert = false, $sharpen = true, $owidth = null, $oheight = null, $ofilesize = null)
	{
		$thumbnail = array(
			'filedata' => '',
			'filesize' => 0,
			'dateline' => 0,
			'imageerror' => '',
		);

		if ($validfile = $this->is_valid_thumbnail_extension(file_extension($filename)) AND $imageinfo = $this->fetch_image_info($location))
		{
			$thumbnail['source_width'] = $new_width = $width = $imageinfo[0];
			$thumbnail['source_height'] = $new_height = $height = $imageinfo[1];
			if ($this->fetch_imagetype_from_extension(file_extension($filename)) != $imageinfo[2])
			{
				$thumbnail['imageerror'] = 'thumbnail_notcorrectimage';
			}
			else if ($width > $maxwidth OR $height > $maxheight)
			{
				$memoryok = true;
				if (function_exists('memory_get_usage') AND $memory_limit = @ini_get('memory_limit') AND $memory_limit != -1)
				{
					$memorylimit = vb_number_format($memory_limit, 0, false, null, '');
					$memoryusage = memory_get_usage();
					$freemem = $memorylimit - $memoryusage;
					$checkmem = true;
					$tmemory = $width * $height * ($imageinfo[2] == 'JPEG' ? 5 : 2) + 7372.8 + sqrt(sqrt($width * $height));
					$tmemory += 166000; // fudge factor, object overhead, etc

					if ($freemem > 0 AND $tmemory > $freemem AND $tmemory <= ($memorylimit * 3))
					{	// attempt to increase memory within reason, no more than triple
						if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < $memorylimit + $tmemory AND $current_memory_limit > 0)
						{
							@ini_set('memory_limit', $memorylimit + $tmemory);
						}

						$memory_limit = @ini_get('memory_limit');
						$memorylimit = vb_number_format($memory_limit, 0, false, null, '');
						$memoryusage = memory_get_usage();
						$freemem = $memorylimit - $memoryusage;
					}
				}

				switch($imageinfo[2])
				{
					case 'GIF':
						if (function_exists('imagecreatefromgif'))
						{
							if ($checkmem)
							{
								if ($freemem > 0 AND $tmemory > $freemem)
								{
									$thumbnail['imageerror'] = 'thumbnail_notenoughmemory';
									$memoryok = false;
								}
							}
							if ($memoryok AND !$image = @imagecreatefromgif($location))
							{
								$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
							}
						}
						else
						{
							$thumbnail['imageerror'] = 'thumbnail_nosupport';
						}
						break;
					case 'JPEG':
						if (function_exists('imagecreatefromjpeg'))
						{
							if ($checkmem)
							{
								if ($freemem > 0 AND $tmemory > $freemem)
								{
									$thumbnail['imageerror'] = 'thumbnail_notenoughmemory';
									$memoryok = false;
								}
							}

							if ($memoryok AND !$image = @imagecreatefromjpeg($location))
							{
								$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
							}
						}
						else
						{
							$thumbnail['imageerror'] = 'thumbnail_nosupport';
						}
						break;
					case 'PNG':
						if (function_exists('imagecreatefrompng'))
						{
							if ($checkmem)
							{
								if ($freemem > 0 AND $tmemory > $freemem)
								{
									$thumbnail['imageerror'] = 'thumbnail_notenoughmemory';
									$memoryok = false;
								}
							}
							if ($memoryok AND !$image = @imagecreatefrompng($location))
							{
								$thumbnail['imagerror'] = 'thumbnail_nocreateimage';
							}
						}
						else
						{
							$thumbnail['imageerror'] = 'thumbnail_nosupport';
						}
						break;
				}

				if ($image)
				{
					$xratio = ($maxwidth == 0) ? 1 : $width / $maxwidth;
					$yratio = ($maxheight == 0) ? 1 : $height / $maxheight;
					if ($xratio > $yratio)
					{
						$new_width = round($width / $xratio);
						$new_height = round($height / $xratio);
					}
					else
					{
						$new_width = round($width / $yratio);
						$new_height = round($height / $yratio);
					}

					if ($drawborder)
					{
						$create_width = $new_width + 2;
						$create_height = $new_height + 2;
						$dest_x_start = 1;
						$dest_y_start = 1;
					}
					else
					{
						$create_width = $new_width;
						$create_height = $new_height;
						$dest_x_start = 0;
						$dest_y_start = 0;
					}

					if ($labelimage)
					{
						$font = 2;
						$labelboxheight = ($drawborder) ? 13 : 14;

						if ($ofilesize)
						{
							$filesize = $ofilesize;
						}
						else
						{
							$filesize = @filesize($location);
						}

						if ($filesize / 1024 < 1)
						{
							$filesize = 1024;
						}
						if ($owidth)
						{
							$dimensions = $owidth . 'x' . $oheight;
						}
						else
						{
							$dimensions = (!empty($width) AND !empty($height)) ? "{$width}x{$height}" : '';
						}

						$sizestring = (!empty($filesize)) ? number_format($filesize / 1024, 0, '', '') . 'kb' : '';

						if (($string_length = (strlen($string = "$dimensions $sizestring $imageinfo[2]") * imagefontwidth($font))) < $new_width)
						{
							$finalstring = $string;
							$finalwidth = $string_length;
						}
						else if (($string_length = (strlen($string = "$dimensions $sizestring") * imagefontwidth($font))) < $new_width)
						{
							$finalstring = $string;
							$finalwidth = $string_length;
						}
						else if (($string_length = (strlen($string = $dimensions) * imagefontwidth($font))) < $new_width)
						{
							$finalstring = $string;
							$finalwidth = $string_length;
						}
						else if (($string_length = (strlen($string = $sizestring) * imagefontwidth($font))) < $new_width)
						{
							$finalstring = $string;
							$finalwidth = $string_length;
						}

						if (!empty($finalstring))
						{
							$create_height += $labelboxheight;
							if ($drawborder)
							{
								$label_x_start = ($new_width - ($finalwidth)) / 2 + 2;
								$label_y_start =  ($labelboxheight - imagefontheight($font)) / 2 + $new_height + 1;
							}
							else
							{
								$label_x_start =  ($new_width - ($finalwidth)) / 2 + 1;
								$label_y_start =  ($labelboxheight - imagefontheight($font)) / 2 + $new_height;
							}
						}
					}
					if (!($finalimage = @imagecreatetruecolor($create_width, $create_height)))
					{
						$thumbnail['imageerror'] = 'thumbnail_nocreateimage';
						imagedestroy($image);
						return $thumbnail;
					}

					$bgcolor = imagecolorallocate($finalimage, 255, 255, 255);
					imagefill($finalimage, 0, 0, $bgcolor);
					@imagecopyresampled($finalimage, $image, $dest_x_start, $dest_y_start, 0, 0, $new_width, $new_height, $width, $height);
					imagedestroy($image);
					if ($sharpen AND $this->imageinfo[2] != 'GIF')
					{
						$this->unsharpmask($finalimage);
					}

					if ($labelimage AND !empty($finalstring))
					{
						$bgcolor = imagecolorallocate($finalimage, $this->thumbcolor['r'], $this->thumbcolor['g'], $this->thumbcolor['b']);
						$recstart = ($drawborder) ? $create_height - $labelboxheight - 1 : $create_height - $labelboxheight;
						imagefilledrectangle($finalimage, 0, $recstart, $create_width, $create_height, $bgcolor);
						$textcolor = imagecolorallocate($finalimage, 255, 255, 255);
						imagestring($finalimage, $font, $label_x_start, $label_y_start, $finalstring, $textcolor);
					}

					if ($drawborder)
					{
						$bordercolor = imagecolorallocate($finalimage, $this->thumbcolor['r'], $this->thumbcolor['g'], $this->thumbcolor['b']);
						imageline($finalimage, 0, 0, $create_width, 0, $bordercolor);
						imageline($finalimage, 0, 0, 0, $create_height, $bordercolor);
						imageline($finalimage, $create_width - 1, 0, $create_width - 1, $create_height, $bordercolor);
						imageline($finalimage, 0, $create_height - 1, $create_width, $create_height - 1, $bordercolor);
					}


					ob_start();
						$new_extension = $this->print_image($finalimage, $jpegconvert ? 'JPEG' : $imageinfo[2], false, $quality);
						$thumbnail['filedata'] = ob_get_contents();
					ob_end_clean();
					$thumbnail['width'] = $create_width;
					$thumbnail['height'] = $create_height;
					$extension = file_extension($filename);
					if ($new_extension != $extension)
					{
						$thumbnail['filename'] = preg_replace('#' . preg_quote($extension, '#') . '$#', $new_extension, $filename);
					}
				}
			}
			else
			{
				if ($imageinfo[0] == 0 AND $imageinfo[1] == 0) // getimagesize() failed
				{
					$thumbnail['filedata'] = '';
					$thumbnail['imageerror'] = 'thumbnail_nogetimagesize';
				}
				else
				{
					$thumbnail['filedata'] = @file_get_contents($location);
					$thumbnail['width'] = $imageinfo[0];
					$thumbnail['height'] = $imageinfo[1];
					$thumbnail['imageerror'] = 'thumbnailalready';
				}
			}
		}
		else if (!$validfile)
		{
			$thumbnail['filedata'] = '';
			$thumbnail['imageerror'] = 'thumbnail_nosupport';
		}

		if (!empty($thumbnail['filedata']))
		{
			$thumbnail['filesize'] = strlen($thumbnail['filedata']);
			$thumbnail['dateline'] = TIMENOW;
		}
		return $thumbnail;
	}

	/**
	*
	* See function definition in vB_Image_Abstract
	*
	*/
	function fetch_error()
	{
		return false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 37230 $
|| ####################################################################
\*======================================================================*/
?>
