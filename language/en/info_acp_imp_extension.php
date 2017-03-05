<?php
/**
 *
 * Improved Extension Management. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, javiexin
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACP_IMP_EXTENSIONS'			=> 'Improved Extension Mgmt',
	'ACP_IMP_EXTENSION_MANAGEMENT'	=> 'Improved Extension Management',
	'ACP_EXTENSION_REDIRECT_IMPROVED'		=> 'Redirecting to the “<strong>Improved Extension Management</strong>” module',
	'ACP_EXTENSION_USE_IMPROVED'			=> 'Use Improved Extension Management module',
	'ACP_EXTENSION_USE_IMPROVED_CONFIRM'	=> 'The “<strong>Improved Extension Management</strong>” module is enabled.  Do you want to use it instead of the default?',
));
