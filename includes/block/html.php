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

class vB_BlockType_Html extends vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = '';

	/**
	 * The title of the block type
	 * We use it only when reload block types in admincp.
	 * Automatically set in the vB_BlockType constructor.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * The description of the block type
	 * We use it only when reload block types in admincp. So it's static.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * The block settings
	 * It uses the same data structure as forum settings table
	 * e.g.:
	 * <code>
	 * $settings = array(
	 *     'varname' => array(
	 *         'defaultvalue' => 0,
	 *         'optioncode'   => 'yesno'
	 *         'displayorder' => 1,
	 *         'datatype'     => 'boolean'
	 *     ),
	 * );
	 * </code>
	 * @see print_setting_row()
	 *
	 * @var string
	 */
	protected $settings = array(
		'html_type' => array(
			'defaultvalue' => 'text',
			'optioncode'   => 'radio:piped
text|pure_text
html|html
php|php',
			'displayorder' => 1,
			'datatype'     => 'string'
		),
		'html_content' => array(
			'defaultvalue' => '',
			'optioncode'   => 'textarea',
			'displayorder' => 2,
			'datatype'     => 'free'
		),
		'html_template' => array(
			'defaultvalue' => 'block_html',
			'displayorder' => 3,
			'datatype'     => 'free'
		),
	);

	public function getData()
	{
		if ($this->config['html_type'] == 'html')
		{
			$content = $this->config['html_content'];
		}
		elseif ($this->config['html_type'] == 'php')
		{
			if (is_demo_mode())
			{
				$content = 'PHP Execution not allowed in Demo Mode!';
			}
			else
			{
				$content = eval($this->config['html_content']);

				if ((!isset($content) OR empty($content)) AND isset($output) AND !empty($output))
				{
					$content = $output;
				}
			}
		}
		elseif ($this->config['html_type'] == 'text')
		{
			$content = nl2br(htmlspecialchars_uni($this->config['html_content']));
		}
		
		return $content;
	}
	
	public function getHTML($content = false)
	{
		if (! $content)
		{
			$content = $this->getData();
		}


		if ($content)
		{
			$templater = vB_Template::create($this->config['html_template']);
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('content', $content);
			return $templater->render();
		}
	}
}