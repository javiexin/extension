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
 * Improved extension management ACP module.
 */
class extensions_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	function main($id, $mode)
	{
		global $phpbb_container;

		// Get an instance of the admin controller
		$admin_controller = $phpbb_container->get(str_replace('\\', '.', __NAMESPACE__) . '.controller');

		$admin_controller->setup($this);
		$admin_controller->main($id, $mode);
	}
}
