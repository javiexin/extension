<?php
/**
 *
 * Improved Extension Management. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @copyright (c) 2017, javiexin
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace javiexin\extension\extension;

/**
 * Improved extension manager
 */

use phpbb\exception\runtime_exception;
use phpbb\file_downloader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* The extension manager provides means to activate/deactivate extensions.
*/
class manager extends \phpbb\extension\manager
{
	/**
	* Instantiates the extension meta class for the extension with the given name
	*
	* @param string $name The extension name
	* @return \phpbb\extension\extension_interface Instance of the extension meta class or
	*						\phpbb\extension\base if the class does not exist
	*						null if extension is not available
	*/
	public function get_extension($name)
	{
		if (!$this->is_available($name))
		{
			return null;
		}

		$extension_class_name = str_replace('/', '\\', $name) . '\\ext';

		if (!class_exists($extension_class_name))
		{
			$extension_class_name = '\phpbb\extension\base';
		}

		return new $extension_class_name($this->container, $this->get_finder(), $this->container->get('migrator'), $name, $this->get_extension_path($name, true));
	}

	/**
	* Checks if an extension is enableable
	*
	* @param string $name The extension name
	* @return bool
	*/
	public function is_enableable($name)
	{
		return $this->get_extension($name)->is_enableable();
	}

	/**
	* Instantiates the metadata manager for the extension with the given name
	*
	* @param string $name The extension name
	* @return \phpbb\extension\metadata_manager Instance of the (improved) metadata manager
	*/
	public function create_extension_metadata_manager($name)
	{
		if (!isset($this->extensions[$name]['metadata']))
		{
			$metadata = new \javiexin\extension\extension\metadata_manager($name, $this->get_extension_path($name, true));
			$this->extensions[$name]['metadata'] = $metadata;
		}
		return $this->extensions[$name]['metadata'];
	}

	/**
	* Gets the metadata manager for the extension with the given name, if it does not exist, it creates one
	*
	* @param string $name The extension name
	* @return \phpbb\extension\metadata_manager Instance of the (improved) metadata manager
	*/
	public function get_extension_metadata_manager($name)
	{
		return $this->create_extension_metadata_manager($name);
	}

	/**
	* Gets the metadata element for the extension with the given name
	*
	* @param string $name		The extension name
	* @param string $element	The extension metadata element: 'all' returns an array, 'name', 'version' and 'display-name' return string
	* @return string|array 		The metadata value for element, or null on error
	*/
	public function get_extension_metadata($name, $element = 'all')
	{
		try
		{
			return $this->get_extension_metadata_manager($name)->get_metadata($element);
		}
		catch (\phpbb\extension\exception $e)
		{
			return false;
		}
	}

	/**
	* Validates the metadata element for the extension with the given name
	*
	* @param string $name		The extension name
	* @param string $element	The extension metadata element:
	*								'all' for display and enable validation returns an array, 'name', 'version' and 'display-name' return string
	*								'display' for name, type, license, version and authors
	*								'name', 'type', 'version', 'license' validate that field
	*								'enable', 'dir', 'authors', 'require_php', 'require_phpbb' validate the corresponding metadata parameter
	* @return bool 				True if valid, throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_extension_metadata($name, $element = 'display')
	{
		return $this->get_extension_metadata_manager($name)->validate($element);
	}

	/**
	* Update the database entry for an extension
	*
	* @param string $name Extension name to update
	* @param array	$data Data to update in the database
	* @param string	$action Action to perform, by default 'update', may be also 'insert' or 'delete'
	*/
	protected function update_state($name, $data, $action = 'update')
	{
		switch ($action)
		{
			case 'insert':
				$this->extensions[$name] = $data;
				$this->extensions[$name]['ext_path'] = $this->get_extension_path($name);
				ksort($this->extensions);
				$sql = 'INSERT INTO ' . $this->extension_table . '
					' . $this->db->sql_build_array('INSERT', $data);
				$this->db->sql_query($sql);
			break;

			case 'update':
				$this->extensions[$name] = array_merge($this->extensions[$name], $data);
				$sql = 'UPDATE ' . $this->extension_table . '
					SET ' . $this->db->sql_build_array('UPDATE', $data) . "
					WHERE ext_name = '" . $this->db->sql_escape($name) . "'";
				$this->db->sql_query($sql);
			break;

			case 'delete':
				unset($this->extensions[$name]);
				$sql = 'DELETE FROM ' . $this->extension_table . "
					WHERE ext_name = '" . $this->db->sql_escape($name) . "'";
				$this->db->sql_query($sql);
			break;
		}

		if ($this->cache)
		{
			$this->cache->purge();
		}
	}

	/**
	* Runs a step of the extension enabling process.
	*
	* Allows the exentension to enable in a long running script that works
	* in multiple steps across requests. State is kept for the extension
	* in the extensions table.
	*
	* @param	string	$name	The extension's name
	* @return	bool			False if enabling is finished, true otherwise
	*/
	public function enable_step($name)
	{
		// ignore extensions that are already enabled
		if ($this->is_enabled($name))
		{
			return false;
		}

		$old_state = (isset($this->extensions[$name]['ext_state'])) ? unserialize($this->extensions[$name]['ext_state']) : false;

		$extension = $this->get_extension($name);

		if (!$extension->is_enableable())
		{
			return false;
		}

		$state = $extension->enable_step($old_state);

		$active = ($state === false);

		$extension_data = array(
			'ext_name'		=> $name,
			'ext_active'	=> $active,
			'ext_state'		=> serialize($state),
		);

		$this->update_state($name, $extension_data, ($this->is_configured($name)) ? 'update' : 'insert');

		if ($active)
		{
			$this->config->increment('assets_version', 1);
		}

		return !$active;
	}

	/**
	* Runs a step of the extension disabling process.
	*
	* Calls the disable method on the extension's meta class to allow it to
	* process the event.
	*
	* @param string $name The extension's name
	* @return bool False if disabling is finished, true otherwise
	*/
	public function disable_step($name)
	{
		// ignore extensions that are already disabled
		if ($this->is_disabled($name))
		{
			return false;
		}

		$old_state = unserialize($this->extensions[$name]['ext_state']);

		$extension = $this->get_extension($name);
		$state = $extension->disable_step($old_state);

		$active = ($state !== false);

		$extension_data = array(
			'ext_active'	=> $active,
			'ext_state'		=> serialize($state),
		);

		$this->update_state($name, $extension_data);

		// continue until the state is false
		return $active;
	}

	/**
	* Runs a step of the extension purging process.
	*
	* Disables the extension first if active, and then calls purge on the
	* extension's meta class to delete the extension's database content.
	*
	* @param string $name The extension's name
	* @return bool False if purging is finished, true otherwise
	*/
	public function purge_step($name)
	{
		// ignore extensions that are not configured
		if (!$this->is_configured($name))
		{
			return false;
		}

		// disable first if necessary
		if ($this->is_enabled($name))
		{
			$this->disable($name);
		}

		$old_state = unserialize($this->extensions[$name]['ext_state']);

		$extension = $this->get_extension($name);
		$state = $extension->purge_step($old_state);

		$purged = ($state === false);

		$extension_data = array(
			'ext_state'		=> serialize($state),
		);

		$this->update_state($name, $extension_data, ($purged) ? 'delete' : 'update');

		// continue until the state is false
		return !$purged;
	}

	/**
	* Retrieves a list of all available extensions on the filesystem
	*
	* @return array An array with extension names as keys and paths to the
	*               extension as values
	*/
	public function all_available()
	{
		$available = array();
		if (!is_dir($this->phpbb_root_path . 'ext/'))
		{
			return $available;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \phpbb\recursive_dot_prefix_filter_iterator(
				new \RecursiveDirectoryIterator($this->phpbb_root_path . 'ext/', \FilesystemIterator::NEW_CURRENT_AND_KEY | \FilesystemIterator::FOLLOW_SYMLINKS)
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		$iterator->setMaxDepth(2);

		foreach ($iterator as $file_info)
		{
			if ($file_info->isFile() && $file_info->getFilename() == 'composer.json')
			{
				$ext_name = $iterator->getInnerIterator()->getSubPath();
				$ext_name = str_replace(DIRECTORY_SEPARATOR, '/', $ext_name);
				if ($this->is_available($ext_name))
				{
					$available[$ext_name] = $this->get_extension_path($ext_name, true);
				}
			}
		}
		ksort($available);
		return $available;
	}

	/**
	* Retrieves all configured extensions.
	*
	* All enabled and disabled extensions are considered configured. A purged
	* extension that is no longer in the database is not configured.
	*
	* @param bool $phpbb_relative Whether the path should be relative to phpbb root
	*
	* @return array An array with extension names as keys and and the
	*               database stored extension information as values
	*/
	public function all_configured($phpbb_relative = true)
	{
		$configured = array();
		foreach ($this->extensions as $name => $data)
		{
			if ($this->is_configured($name))
			{
				unset($data['metadata']);
				$data['ext_path'] = ($phpbb_relative ? $this->phpbb_root_path : '') . $data['ext_path'];
				$configured[$name] = $data;
			}
		}
		return $configured;
	}

	/**
	* Retrieves all enabled extensions.
	* @param bool $phpbb_relative Whether the path should be relative to phpbb root
	*
	* @return array An array with extension names as keys and and the
	*               database stored extension information as values
	*/
	public function all_enabled($phpbb_relative = true)
	{
		$enabled = array();
		foreach ($this->extensions as $name => $data)
		{
			if ($this->is_enabled($name))
			{
				$enabled[$name] = ($phpbb_relative ? $this->phpbb_root_path : '') . $data['ext_path'];
			}
		}
		return $enabled;
	}

	/**
	* Retrieves all disabled extensions.
	*
	* @param bool $phpbb_relative Whether the path should be relative to phpbb root
	*
	* @return array An array with extension names as keys and and the
	*               database stored extension information as values
	*/
	public function all_disabled($phpbb_relative = true)
	{
		$disabled = array();
		foreach ($this->extensions as $name => $data)
		{
			if ($this->is_disabled($name))
			{
				$disabled[$name] = ($phpbb_relative ? $this->phpbb_root_path : '') . $data['ext_path'];
			}
		}
		return $disabled;
	}

	/**
	* Check to see if a given extension is available on the filesystem
	*
	* @param string $name Extension name to check NOTE: Can be user input
	* @return bool Depending on whether or not the extension is available
	*/
	public function is_available($name)
	{
		try
		{
			return $this->get_extension_metadata_manager($name)->validate('all');
		}
		catch (\phpbb\extension\exception $e)
		{
			return false;
		}
	}

	/**
	* Check to see if a given extension is enabled
	*
	* @param string $name Extension name to check
	* @return bool Depending on whether or not the extension is enabled
	*/
	public function is_enabled($name)
	{
		return isset($this->extensions[$name]['ext_active']) && $this->extensions[$name]['ext_active'];
	}

	/**
	* Check to see if a given extension is disabled
	*
	* @param string $name Extension name to check
	* @return bool Depending on whether or not the extension is disabled
	*/
	public function is_disabled($name)
	{
		return isset($this->extensions[$name]['ext_active']) && !$this->extensions[$name]['ext_active'];
	}

	/**
	* Check to see if a given extension is configured
	*
	* All enabled and disabled extensions are considered configured. A purged
	* extension that is no longer in the database is not configured.
	*
	* @param string $name Extension name to check
	* @return bool Depending on whether or not the extension is configured
	*/
	public function is_configured($name)
	{
		return isset($this->extensions[$name]['ext_active']);
	}

	/**
	* Check the version and return the available updates (for an extension).
	*
	* @param string $ext_name The name of the extension to check
	* @param bool $force_update Ignores cached data. Defaults to false.
	* @param bool $force_cache Force the use of the cache. Override $force_update.
	* @param string $stability Force the stability (null by default).
	* @return string
	* @throws runtime_exception
	*/
	public function version_check_name($ext_name, $force_update = false, $force_cache = false, $stability = null)
	{
		return parent::version_check($this->get_extension_metadata_manager($ext_name), $force_update, $force_cache, $stability);
	}
}
