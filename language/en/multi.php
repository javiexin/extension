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
	'EXTENSION_ACTION_DONE'			=> 'DONE',

	'EXTENSIONS_NOT_AVAILABLE'		=> array(
		1	=> 'The following extension is not available:',
		2	=> 'The following extensions are not available:',
	),

	'EXTENSION_DELETE_DATA_CONFIRM'	=> 'Are you sure that you wish to delete the data associated with these extensions?<br />This removes all of its data and settings and cannot be undone!',
	'EXTENSION_DISABLE_CONFIRM'		=> 'Are you sure that you wish to disable these extensions?',
	'EXTENSION_ENABLE_CONFIRM'		=> 'Are you sure that you wish to enable these extensions?',

	'EXTENSION_DELETE_DATA_EXPLAIN'	=> 'Deleting an extension’s data removes all of its data and settings. The extension files are retained so it can be enabled again.',
	'EXTENSION_DISABLE_EXPLAIN'		=> 'Disabling an extension retains its files, data and settings but removes any functionality added by the extension.',
	'EXTENSION_ENABLE_EXPLAIN'		=> 'Enabling an extension allows you to use it on your board.',

	'EXTENSION_DELETE_DATA_IN_PROGRESS'	=> 'The data from these extensions is currently being deleted. Please do not leave or refresh this page until this process is completed.',
	'EXTENSION_DISABLE_IN_PROGRESS'	=> 'These extensions are currently being disabled. Please do not leave or refresh this page until this process is completed.',
	'EXTENSION_ENABLE_IN_PROGRESS'	=> 'These extensions are currently being enabled. Please do not leave or refresh this page until this process is completed.',

	'EXTENSION_DELETE_DATA_SUCCESS'	=> 'The data from these extensions was deleted successfully',
	'EXTENSION_DISABLE_SUCCESS'		=> 'These extensions were disabled successfully',
	'EXTENSION_ENABLE_SUCCESS'		=> 'These extensions ware enabled successfully',
));
