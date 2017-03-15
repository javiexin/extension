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
	'IMP_EXTENSIONS_ADMIN'			=> 'Improved Extensions Manager',
	'IMP_EXTENSIONS_EXPLAIN'		=> 'The Improved Extensions Manager is a tool which allows you to manage all of your extensions statuses and view information about them.',
	'IMP_EXTENSIONS_MULTI_ACTIONS'	=> 'Execute action on selected extensions',
));
