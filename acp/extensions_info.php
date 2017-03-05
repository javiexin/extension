<?php
/**
 *
 * Improved Extension Management. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, javiexin
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace javiexin\extension\acp;

/**
 * Improved extension management ACP module info.
 */
class extensions_info
{
	public function module()
	{
		return array(
			'filename'	=> '\javiexin\extension\acp\extensions_module',
			'title'		=> 'ACP_IMP_EXTENSIONS',
			'modes'		=> array(
				'main'	=> array(
					'title'	=> 'ACP_IMP_EXTENSIONS',
					'auth'	=> 'ext_javiexin/extension && acl_a_extensions',
					'cat'	=> array('ACP_IMP_EXTENSION_MANAGEMENT')
				),
			),
		);
	}
}
