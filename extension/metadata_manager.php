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
 * Improved extension metadata manager
 */

 /**
* The extension metadata manager validates and gets meta-data for extensions
*/
class metadata_manager extends \phpbb\extension\metadata_manager
{
	/**
	* Load exception
	* @var string
	*/
	protected $load_exception;

	/**
	* List of required fields with validation criteria
	* @var array
	*/
	protected $fields;

	/**
	* Creates the metadata manager
	*
	* @param string				$ext_name			Name (including vendor) of the extension
	* @param string				$ext_path			Path to the extension directory including root path
	*/
	public function __construct($ext_name, $ext_path)
	{
		$this->ext_name = $ext_name;
		$this->metadata = array();
		$this->metadata_file = $ext_path . 'composer.json';

		// Initialize required fields
		$this->fields = array(
			'name'		=> '#^[a-zA-Z0-9_\x7f-\xff]{2,}/[a-zA-Z0-9_\x7f-\xff]{2,}$#',
			'type'		=> '#^phpbb-extension$#',
			'license'	=> '#.+#',
			'version'	=> '#.+#',
		);

		// Load the file and capture any error without throwing the exception yet
		$this->load_exception = false;

		if (!file_exists($this->metadata_file))
		{
			$this->load_exception = 'FILE_NOT_FOUND';
		}

		if (!$this->load_exception && !($file_contents = file_get_contents($this->metadata_file)))
		{
			$this->load_exception = 'FILE_CONTENT_ERR';
		}

		if (!$this->load_exception && ($metadata = json_decode($file_contents, true)) === null)
		{
			$this->load_exception = 'FILE_JSON_DECODE_ERR';
		}

		if (!$this->load_exception)
		{
			array_walk_recursive($metadata, array($this, 'sanitize_json'));
			$this->metadata = $metadata;
		}
	}

	/**
	 * Sanitize input from JSON array using htmlspecialchars()
	 *
	 * @param mixed		$value	Value of array row
	 * @param string	$key	Key of array row
	 */
	public function sanitize_json(&$value, $key)
	{
		$value = htmlspecialchars($value);
	}

	/**
	* Processes and gets the metadata requested
	*
	* @param  string $element			All for all metadata that it has and is valid, otherwise specify which section you want by its shorthand term.
	* @return mixed						Array containing all of the requested metadata, string for specific metadata elements, throws an exception on failure
	* @throws \phpbb\extension\exception
	*/
	public function get_metadata($element = 'all')
	{
		switch ($element)
		{
			case 'all':
			default:
				$this->validate();
				return $this->metadata;
			break;

			case 'version':
			case 'name':
				$this->validate($element);
				return $this->metadata[$element];
			break;

			case 'display-name':
				return (isset($this->metadata['extra']['display-name'])) ? $this->metadata['extra']['display-name'] : $this->get_metadata('name');
			break;
		}
	}

	/**
	* Validate fields
	*
	* @param string $name  ("all" for display and enable validation
	* 						"display" for name, type, and authors
	* 						"name", "type")
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	*								provided for backward compatibility with this default value
	* @return bool True if validation succeeded, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate($name = 'display', $throw_exceptions = true)
	{
		try
		{
			// Throw exceptions caught during initialization
			if ($this->load_exception)
			{
				throw new \phpbb\extension\exception($this->load_exception, array($this->metadata_file));
			}

			switch ($name)
			{
				case 'all':
					$this->validate('display');
					$this->validate_enable(true);
				break;

				case 'display':
					foreach ($this->fields as $field => $data)
					{
						$this->validate($field);
					}
				// No break

				case 'authors':
					return $this->validate_authors();
				break;

				// Proxy for other validation methods
				case 'enable':
				case 'dir':
				case 'require_php':
				case 'require_phpbb':
					return $this->{'validate_' . $name}(true);
				break;

				default:
					if (isset($this->fields[$name]))
					{
						if (!isset($this->metadata[$name]))
						{
							throw new \phpbb\extension\exception('META_FIELD_NOT_SET', array($name));
						}

						if (!preg_match($this->fields[$name], $this->metadata[$name]))
						{
							throw new \phpbb\extension\exception('META_FIELD_INVALID', array($name));
						}
					}
				break;
			}
		}
		catch (\phpbb\extension\exception $e)
		{
			if ($throw_exceptions)
			{
				throw $e;
			}
			return false;
		}

		return true;
	}

	/**
	* Validates the contents of the authors field
	*
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	*								provided for backward compatibility with this default value
	* @return bool True if validation succeeded, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_authors($throw_exceptions = true)
	{
		if (empty($this->metadata['authors']))
		{
			if ($throw_exceptions)
			{
				throw new \phpbb\extension\exception('META_FIELD_NOT_SET', array('authors'));
			}
			return false;
		}

		foreach ($this->metadata['authors'] as $author)
		{
			if (!isset($author['name']))
			{
				if ($throw_exceptions)
				{
					throw new \phpbb\extension\exception('META_FIELD_NOT_SET', array('author name'));
				}
				return false;
			}
		}

		return true;
	}

	/**
	* This array handles the verification that this extension can be enabled on this board
	*
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	*								provided for backward compatibility with this default value
	* @return bool True if validation succeeded, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_enable($throw_exceptions = false)
	{
		// Check for valid directory & phpBB, PHP versions
		return $this->validate_dir($throw_exceptions) && $this->validate_require_phpbb($throw_exceptions) && $this->validate_require_php($throw_exceptions);
	}

	/**
	* Validates the most basic directory structure to ensure it follows <vendor>/<ext> convention.
	*
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	*								provided for backward compatibility with this default value
	* @return boolean True when passes validation, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_dir($throw_exceptions = false)
	{
		$is_valid_dir = (substr_count($this->ext_name, '/') === 1 && $this->ext_name == $this->get_metadata('name')) ? true : false;

		if ($throw_exceptions && !$is_valid_dir)
		{
			throw new \phpbb\extension\exception('EXTENSION_DIR_INVALID');
		}

		return $is_valid_dir;
	}

	/**
	* Validates the contents of the phpbb requirement field
	*
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	*								provided for backward compatibility with this default value
	* @return boolean True when passes validation, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_require_phpbb($throw_exceptions = false)
	{
		$is_valid_require_phpbb = isset($this->metadata['extra']['soft-require']['phpbb/phpbb']);

		if ($throw_exceptions && !$is_valid_require_phpbb)
		{
			throw new \phpbb\extension\exception('META_FIELD_NOT_SET', array('soft-require'));
		}

		return $is_valid_require_phpbb;
	}

	/**
	* Validates the contents of the php requirement field
	*
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	*								provided for backward compatibility with this default value
	* @return boolean True when passes validation, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_require_php($throw_exceptions = false)
	{
		$is_valid_require_php = isset($this->metadata['require']['php']);

		if ($throw_exceptions && !$is_valid_require_php)
		{
			throw new \phpbb\extension\exception('META_FIELD_NOT_SET', array('require php'));
		}

		return $is_valid_require_php;
	}

	/**
	* Outputs the metadata into the template
	* DEPRECATED: should not be used, but as it is present in the inherited interface, must be redefined
	* Now always throws an exception
	*
	* @param \phpbb\template\template	$template	phpBB Template instance
	*/
	public function output_template_data(\phpbb\template\template $template)
	{
		global $phpbb_container;
		$controller = $phpbb_container->get('javiexin.extension.acp.controller');
		$controller->output_metadata_to_template($this->ext_name);
//		throw new \phpbb\extension\exception('EXTENSION_NOT_AVAILABLE');
	}
}
