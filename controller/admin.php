<?php
/**
 *
 * Improved Extension Management. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, javiexin
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace javiexin\extension\controller;

use phpbb\exception\exception_interface;
use phpbb\exception\version_check_exception;
use phpbb\extension\exception as metadata_exception;


/**
 * Improved extension admin controller
 */
class admin
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\event\dispatcher */
	protected $dispatcher;

	/** @var \phpbb\extension\manager */
	protected $ext_manager;

	/** @var \javiexin\extension\event\listener */
	protected $listener;

	/** @var object holding the ACP module */
	protected $module;

	/** @var string Custom form action, template name & page title */
	protected $u_action;
	protected $tpl_name;
	protected $page_title;

	/** @var int Safe time limit */
	protected $end_before;

	/**
	* Constructor for admin controller
	*
	* @param \phpbb\config\config				$config		Config object
	* @param \phpbb\request\request				$request	Request object
	* @param \phpbb\template\template			$template	Template object
	* @param \phpbb\user						$user		User object
	* @param \phpbb\log\log						$log		Log object
	* @param \phpbb\event\dispatcher			$dispatcher		Dispatcher object
	* @param \phpbb\extension\manager			$ext_manager	Extension manager object
	* @param \javiexin\extension\event\listener	$listener		Subscriber object for this extension
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbb\log\log $log, \phpbb\event\dispatcher $dispatcher, \phpbb\extension\manager $ext_manager, \javiexin\extension\event\listener $listener)
	{
		$this->config		= $config;
		$this->request		= $request;
		$this->template		= $template;
		$this->user			= $user;
		$this->log			= $log;
		$this->dispatcher	= $dispatcher;
		$this->ext_manager	= $ext_manager;
		$this->listener		= $listener;
	}

	public function setup($module)
	{
		$this->module		= $module;
		$this->page_title	= &$module->page_title;
		$this->tpl_name		= &$module->tpl_name;
		$this->u_action		= &$module->u_action;

		$this->page_title = 'ACP_IMP_EXTENSIONS';

		$this->user->add_lang(array('install', 'acp/extensions', 'migrator'));
		$this->user->add_lang_ext('javiexin/extension', 'extension');
	}

	public function setup_from_listener($event)
	{
		$this->module		= null;
		$this->page_title	= '';  // Will not be considered as not exposed in the event
		$this->tpl_name		= $event['tpl_name'];
		$this->u_action		= $event['u_action'];

		$this->user->add_lang_ext('javiexin/extension', 'extension');
	}

	public function update_event($event)
	{
		$event['tpl_name']	= $this->tpl_name;
		$event['u_action']	= $this->u_action;
		return $event;
	}

	public function main($id, $mode)
	{
		$action = $this->request->variable('action', 'list');
		$ext_name = $this->request->variable('ext_name', '');

		// What is a safe limit of execution time? Half the max execution time should be safe.
		$safe_time_limit = (ini_get('max_execution_time') / 2);
		$start_time = time();

		// Cancel action
		if ($this->request->is_set_post('cancel'))
		{
			$action = 'list';
			$ext_name = '';
		}

		if (in_array($action, array('enable', 'disable', 'delete_data')) && !check_link_hash($this->request->variable('hash', ''), $action . '.' . $ext_name))
		{
			trigger_error('FORM_INVALID', E_USER_WARNING);
		}

		// Get the list of extensions for multi actions; may set ext_name
		$ext_list = $this->prepare_multi_action($action, $ext_name);

		// As we are in control we do not want to interfere with event execution, so we remove our own subscriber
		$this->dispatcher->removeSubscriber($this->listener);

		/**
		* Event to run a specific action on extension
		*
		* @event core.acp_extensions_run_action_before
		* @var	string	action			Action to run; if the event completes execution of the action, should be set to 'none'
		* @var	string	u_action		Url we are at
		* @var	string	ext_name		Extension name from request
		* @var	array	ext_list		List of extensions for multi action -- NON STANDARD (not in the original event)
		* @var	int		safe_time_limit	Safe limit of execution time
		* @var	int		start_time		Start time
		* @var	string	tpl_name		Template file to load
		* @since 3.1.11-RC1
		* @changed 3.2.1-RC1			Renamed to core.acp_extensions_run_action_before, added tpl_name, added action 'none'
		* @changed Improved ext mgr		Included non standard variable ext_list
		*/
		$u_action = $this->u_action;
		$tpl_name = '';
		$vars = array('action', 'u_action', 'ext_name', 'ext_list', 'safe_time_limit', 'start_time', 'tpl_name');
		extract($this->dispatcher->trigger_event('core.acp_extensions_run_action_before', compact($vars)));

		// In case they have been updated by the event
		$this->u_action = $u_action;
		$this->tpl_name = $tpl_name;

		// Execute the specified action in an extension (or a list of extensions), before a time limit
		$this->execute($action, $ext_name, $ext_list, $start_time + $safe_time_limit);

		/**
		* Event to run after a specific action on extension has completed
		*
		* @event core.acp_extensions_run_action_after
		* @var	string	action			Action that has run
		* @var	string	u_action		Url we are at
		* @var	string	ext_name		Extension name from request
		* @var	array	ext_list		List of extensions for multi action -- NON STANDARD (not in the original event)
		* @var	int		safe_time_limit	Safe limit of execution time
		* @var	int		start_time		Start time
		* @var	string	tpl_name		Template file to load
		* @since 3.1.11-RC1
		* @changed Improved ext mgr		Included non standard variable ext_list
		*/
		$u_action = $this->u_action;
		$tpl_name = $this->tpl_name;
		$vars = array('action', 'u_action', 'ext_name', 'ext_list', 'safe_time_limit', 'start_time', 'tpl_name');
		extract($this->dispatcher->trigger_event('core.acp_extensions_run_action_after', compact($vars)));

		// In case they have been updated by the event
		$this->u_action = $u_action;
		$this->tpl_name = $tpl_name;

		// If this extension has been disabled, we revert to the standard module
		if (!$this->ext_manager->is_enabled('javiexin/extension'))
		{
			$this->u_action = str_replace('-javiexin-extension-acp-extensions_module', 'acp_extensions', $u_action);
			$this->template->assign_vars(array(
				'U_RETURN'	=> $this->u_action . '&amp;action=list',
			));
		}
	}

	/**
	* Prepare for multi action, reading the request parameters
	*
	* @param string		$action		Action to perform
	* @param string		$ext_name	Reference to extension name, may be modified here
	* @return array					Extension list for multi actions
	*/
	public function prepare_multi_action($action, &$ext_name)
	{
		// Multi action from the form sets multi and ext_list as an array, from the url sets ext_list as a comma separated string
		$ext_list = ($this->request->variable('multi', false)) ?
							$this->request->variable('ext_list', array('')) :
							explode(',', $this->request->variable('ext_list', ''));

		// List is empty
		$ext_list = ($ext_list === array('')) ? array() : $ext_list;

		$pre = (substr($action, -4) === '_pre');
		$action = str_replace('_pre', '', $action);
		$action_name = ($action == 'delete_data') ? 'purge' : $action;
		$check_doable = 'check_' . $action_name . 'able';

		// If we are preparing an action, we perform extra checks
		if ($pre)
		{
			// Remove extensions that do not need this action
			foreach ($ext_list as $key => $name)
			{
				if (!$this->ext_manager->$check_doable($name))
				{
					unset($ext_list[$key]);
				}
			}

			// Do not allow actions without target extension(s)
			if (empty($ext_name) && empty($ext_list))
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}
		}

		// Multi action on a single extension, revert to normal action
		if (count($ext_list) == 1)
		{
			$ext_name = array_shift($ext_list);
		}

		// If this extension has been specified for multi action, we make it last
		if (($key = array_search('javiexin/extension', $ext_list)) !== false)
		{
			unset($ext_list[$key]);
			$ext_list[] = 'javiexin/extension';
		}

		return array_values($ext_list);
	}

	/**
	* Execute specified action
	*
	* @param string		$action		Action to perform
	* @param string		$ext_name	Extension name
	* @param array		$ext_list	Extension list for multi actions
	* @param int		$end_before	Safe execution time limit
	* @return null
	*/
	public function execute($action, $ext_name, $ext_list, $end_before)
	{
		// What are we doing?
		switch ($action)
		{
			case 'none':
				// Intentionally empty, used by extensions that execute additional actions in the prior event
				break;

			case 'set_config_version_check_force_unstable':
				if($this->request->variable('force_unstable', false))
				{
					confirm_box(false, $this->user->lang('EXTENSION_FORCE_UNSTABLE_CONFIRM'), build_hidden_fields(array('force_unstable' => true)));
				}
				else
				{
					$this->config->set('extension_force_unstable', false);
					trigger_error($this->user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
				}
				break;

			case 'list':
			default:
				$action = 'list'; // Should be a valid value, so that extensions may rely on it
				if (confirm_box(true))
				{
					$this->config->set('extension_force_unstable', true);
					trigger_error($this->user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
				}

				// Enabled extensions
				$this->output_list_to_template($this->ext_manager->all_enabled(), 'enabled', array('disable'));

				// Disabled extensions
				$this->output_list_to_template($this->ext_manager->all_disabled(), 'disabled', array('enable', 'delete_data'));

				// Available not configured extensions
				$this->output_list_to_template(array_diff_key($this->ext_manager->all_available(), $this->ext_manager->all_configured()), 'disabled', array('enable'));

				$this->template->assign_vars(array(
					'U_VERSIONCHECK_FORCE' 	=> $this->u_action . '&amp;action=list&amp;versioncheck_force=1',
					'FORCE_UNSTABLE'		=> $this->config['extension_force_unstable'],
					'U_ACTION' 				=> $this->u_action,
				));

				$this->tpl_name = '@javiexin_extension/acp_ext_list';
			break;

			case 'enable_pre':
			case 'disable_pre':
			case 'delete_data_pre':
			case 'enable':
			case 'disable':
			case 'delete_data':
				if ($ext_list)
				{
					$this->user->add_lang_ext('javiexin/extension', 'multi'); // This overwrites some core language vars with "multi" variants

					$this->validate_multi_action($action, $ext_list);
					$ext_name = $this->select_extension_for_multi_action($action, $ext_list);
					$this->u_action .= '&amp;ext_list=' . urlencode(implode(',', $ext_list));
				}
				$this->validate_action($action, $ext_name);
				$this->perform_action($action, $ext_name, $end_before);
				if ($ext_list)
				{
					$this->perform_multi_action_after($action, $ext_name, $ext_list);
				}

				$this->tpl_name = '@javiexin_extension/acp_ext_action';
			break;

			case 'details':
				$this->validate_action($action, $ext_name);
				// Output it to the template
				$this->output_metadata_to_template($ext_name);
				$this->output_versioncheck_to_template($ext_name);

				$this->template->assign_vars(array(
					'U_BACK'				=> $this->u_action . '&amp;action=list',
					'U_VERSIONCHECK_FORCE'	=> $this->u_action . '&amp;action=details&amp;versioncheck_force=1&amp;ext_name=' . urlencode($ext_name),
				));

				$this->tpl_name = '@javiexin_extension/acp_ext_details';
			break;
		}
	}

	/**
	* Validation before acting on an extension list
	*
	* @param string		$action		Action to perform
	* @param array		$ext_list	List of extension names
	* @return null
	*/
	protected function validate_multi_action($action, $ext_list)
	{
		$unavailable = array();
		foreach ($ext_list as $ext_name)
		{
			if (!$this->ext_manager->is_available($ext_name))
			{
				$unavailable[] = $ext_name;
			}
		}
		if (!empty($unavailable))
		{
			trigger_error($this->user->lang('EXTENSIONS_NOT_AVAILABLE', count($unavailable)) . $this->to_html_string($unavailable) . adm_back_link($this->u_action), E_USER_WARNING);
		}
	}

	/**
	* Validation before acting on an extension
	*
	* @param string		$action		Action to perform
	* @param string		$ext_name	Extension name
	* @return null
	*/
	protected function validate_action($action, $ext_name)
	{
		$action = str_replace('_pre', '', $action);

		try
		{
			$this->ext_manager->validate_extension_metadata($ext_name);
			if (in_array($action, array('enable', 'delete_data')))
			{
				if ($this->ext_manager->is_enabled($ext_name))
				{
					redirect($this->u_action);
				}
				$this->ext_manager->validate_extension_metadata($ext_name, 'enable');
			}
			else if ($action === 'disable')
			{
				if (!$this->ext_manager->is_enabled($ext_name))
				{
					redirect($this->u_action);
				}
			}
		}
		catch (exception_interface $e)
		{
			$message = call_user_func_array(array($this->user, 'lang'), array_merge(array($e->getMessage()), $e->get_parameters()));
			trigger_error($message . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if ($action === 'enable' && !$this->ext_manager->is_enableable($ext_name))
		{
			trigger_error($this->user->lang['EXTENSION_NOT_ENABLEABLE'] . adm_back_link($this->u_action), E_USER_WARNING);
		}
	}

	/**
	* Select the next extension to act on from the given list
	*
	* @param string		$action		Action to perform
	* @param array		$ext_list	List of extension names
	* @return string		the name of the extension to use to continue action
	*/
	protected function select_extension_for_multi_action($action, $ext_list)
	{
		$action = str_replace('_pre', '', $action);
		$action_name = ($action == 'delete_data') ? 'purge' : $action;
		$check_done = 'is_' . $action_name . 'd';

		$this->template->assign_block_vars_array('ext', $this->ext_list_data($ext_list, $check_done));

		foreach ($ext_list as $ext_name)
		{
			if (!$this->ext_manager->$check_done($ext_name))
			{
				break;
			}
		}
		return $ext_name;
	}

	/**
	* Perform action on an extension
	*
	* @param string								$action		Action to perform
	* @param string								$ext_name	Extension name
	* @param int								$end_before	Safe execution time limit
	* @return null
	*/
	protected function perform_action($action, $ext_name, $end_before)
	{
		$pre = (substr($action, -4) === '_pre');
		$action = str_replace('_pre', '', $action);
		$action_name = ($action == 'delete_data') ? 'purge' : $action;

		$this->template->assign_vars(array(
			'EXTENSION_ACTION'					=> $action,
			'L_EXTENSION_ACTION'				=> $this->user->lang('EXTENSION_' . strtoupper($action)),
			'L_EXTENSION_ACTION_EXPLAIN'		=> $this->user->lang('EXTENSION_' . strtoupper($action) . '_EXPLAIN'),
			'L_EXTENSION_ACTION_CONFIRM'		=> $this->user->lang('EXTENSION_' . strtoupper($action) . '_CONFIRM', ($ext_name) ? $this->ext_manager->get_extension_metadata($ext_name, 'display-name') : ''),
			'L_EXTENSION_ACTION_IN_PROGRESS'	=> $this->user->lang('EXTENSION_' . strtoupper($action) . '_IN_PROGRESS'),
			'L_EXTENSION_ACTION_SUCCESS'		=> $this->user->lang('EXTENSION_' . strtoupper($action) . '_SUCCESS'),
		));

		if ($pre)
		{
			$this->template->assign_vars(array(
				'PRE'					=> true,
				'U_ACTION'				=> $this->u_action . '&amp;action=' . $action . '&amp;ext_name=' . urlencode($ext_name) . '&amp;hash=' . generate_link_hash($action . '.' . $ext_name),
			));
		}
		else
		{
			$action_step = $action_name . '_step';
			try
			{
				while ($this->ext_manager->$action_step($ext_name))
				{
					// Are we approaching the time limit? If so we want to pause the update and continue after refreshing
					if (time() >= $end_before)
					{
						$this->template->assign_vars(array(
							'S_NEXT_STEP'		=> true,
						));

						meta_refresh(0, $this->u_action . '&amp;action=' . $action . '&amp;ext_name=' . urlencode($ext_name) . '&amp;hash=' . generate_link_hash($action . '.' . $ext_name));
					}
				}
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_EXT_' . strtoupper($action_name), time(), array($ext_name));
			}
			catch (\phpbb\db\migration\exception $e)
			{
				$this->template->assign_var('MIGRATOR_ERROR', $e->getLocalisedMessage($this->user));
			}

			$this->template->assign_vars(array(
				'U_RETURN' => $this->u_action . '&amp;action=list',
			));
		}
	}

	/**
	* Perform action on an extension list after actual action
	*
	* @param string		$action		Action being performed
	* @param array		$ext_name	Name of extension that has been acted on
	* @param array		$ext_list	List of extension names
	* @return null
	*/
	protected function perform_multi_action_after($action, $ext_name, $ext_list)
	{
		if (substr($action, -4) === '_pre')
		{
			return;
		}
		$action_name = ($action == 'delete_data') ? 'purge' : $action;
		$check_done = 'is_' . $action_name . 'd';

		$this->template->alter_block_array('ext', array('S_DONE' => true), array('NAME' => $ext_name), 'change');

		foreach ($ext_list as $name)
		{
			if (!$this->ext_manager->$check_done($name))
			{
				$this->template->assign_vars(array(
					'S_NEXT_STEP'						=> true,
				));
				meta_refresh(0, $this->u_action . '&amp;action=' . $action . '&amp;hash=' . generate_link_hash($action . '.'));
			}
		}

		$this->template->assign_vars(array(
			'U_RETURN' => preg_replace('/&(amp;)?(ext_list)=[^&]*/', '', $this->u_action) . '&amp;action=list',
		));
	}

	/**
	* Outputs a list of extensions to the specified block, with actions attached to it
	*
	* @param	array	$ext_list	Array of extensions
	* @param	string	$block		Name of block to use for the template
	* @param	array	$actions	Array of actions that may be performed on the extension
	* @return null
	*/
	protected function output_list_to_template($ext_list, $block, $actions)
	{
		$extension_data = array();

		foreach ($ext_list as $name => $location)
		{
			$ext_data = $this->get_extension_data($name);
			if (!empty($ext_data['S_INVALID_EXT']))
			{
				$this->template->assign_block_vars('disabled', $ext_data);
			}
			else
			{
				$extension_data[$name] = $ext_data;
			}
		}

		uasort($extension_data, array($this, 'sort_extension_meta_data_table'));

		foreach ($extension_data as $name => $block_vars)
		{
			$block_vars['NAME'] = $name;
			$block_vars['U_DETAILS'] = $this->u_action . '&amp;action=details&amp;ext_name=' . urlencode($name);
			$block_vars['EXT_ACTIONS'] = implode(' ', $actions);

			$this->template->assign_block_vars($block, $block_vars);

			$this->output_actions($block, $name, $actions);
		}
	}

	/**
	* Get extension data for template
	*
	* @return array of data to be dumped to the appropriate template block
	*/
	protected function get_extension_data($name)
	{
		$ext_template_data = array();

		try
		{
			$ext_template_data = array(
				'META_DISPLAY_NAME' => $this->ext_manager->get_extension_metadata($name, 'display-name'),
				'META_VERSION' => $this->ext_manager->get_extension_metadata($name, 'version'),
			);

			$force_update = $this->request->variable('versioncheck_force', false);
			$updates = $this->ext_manager->version_check_name($name, $force_update, !$force_update);

			$ext_template_data['S_UP_TO_DATE'] = empty($updates);
			$ext_template_data['S_VERSIONCHECK'] = true;
			$ext_template_data['U_VERSIONCHECK_FORCE'] = $this->u_action . '&amp;action=details&amp;versioncheck_force=1&amp;ext_name=' . urlencode($name);
		}
		catch (metadata_exception $e)
		{
			$message = call_user_func_array(array($this->user, 'lang'), array_merge(array($e->getMessage()), $e->get_parameters()));
			$ext_template_data = array(
				'S_INVALID_EXT'			=> true,
				'META_DISPLAY_NAME'		=> $this->user->lang('EXTENSION_INVALID_LIST', $name, $message),
				'S_VERSIONCHECK'		=> false,
			);
		}
		catch (exception_interface $e)
		{
			$ext_template_data['S_VERSIONCHECK'] = false;
		}
		catch (\RuntimeException $e)
		{
			$ext_template_data['S_VERSIONCHECK'] = false;
		}

		return $ext_template_data;
	}

	/**
	* Output actions to a block
	*
	* @param string $block
	* @param array $actions
	*/
	protected function output_actions($block, $name, $actions)
	{
		foreach ($actions as $action)
		{
			$this->template->assign_block_vars($block . '.actions', array(
				'L_ACTION'			=> $this->user->lang('EXTENSION_' . strtoupper($action)),
				'L_ACTION_EXPLAIN'	=> (isset($this->user->lang['EXTENSION_' . strtoupper($action) . '_EXPLAIN'])) ? $this->user->lang('EXTENSION_' . strtoupper($action) . '_EXPLAIN') : '',
				'U_ACTION'			=> $this->u_action . '&amp;action=' . $action . '_pre&amp;ext_name=' . urlencode($name),
			));
		}
	}

	/**
	* Sort helper for the table containing the metadata about the extensions.
	*/
	protected function sort_extension_meta_data_table($val1, $val2)
	{
		return strnatcasecmp($val1['META_DISPLAY_NAME'], $val2['META_DISPLAY_NAME']);
	}

	/**
	* Outputs extension metadata into the template
	*
	* @param string $ext_name Name of the extension to get the metadata
	* @return null
	*/
	protected function output_metadata_to_template($ext_name)
	{
		$metadata = $this->ext_manager->get_extension_metadata($ext_name);

		$this->template->assign_vars(array(
			'META_NAME'			=> $metadata['name'],
			'META_TYPE'			=> $metadata['type'],
			'META_DESCRIPTION'	=> (isset($metadata['description'])) ? $metadata['description'] : '',
			'META_HOMEPAGE'		=> (isset($metadata['homepage'])) ? $metadata['homepage'] : '',
			'META_VERSION'		=> $metadata['version'],
			'META_TIME'			=> (isset($metadata['time'])) ? $metadata['time'] : '',
			'META_LICENSE'		=> $metadata['license'],

			'META_REQUIRE_PHP'		=> (isset($metadata['require']['php'])) ? $metadata['require']['php'] : '',
			'META_REQUIRE_PHP_FAIL'	=> (isset($metadata['require']['php'])) ? false : true,

			'META_REQUIRE_PHPBB'		=> (isset($metadata['extra']['soft-require']['phpbb/phpbb'])) ? $metadata['extra']['soft-require']['phpbb/phpbb'] : '',
			'META_REQUIRE_PHPBB_FAIL'	=> (isset($metadata['extra']['soft-require']['phpbb/phpbb'])) ? false : true,

			'META_DISPLAY_NAME'	=> (isset($metadata['extra']['display-name'])) ? $metadata['extra']['display-name'] : '',
		));

		foreach ($metadata['authors'] as $author)
		{
			$this->template->assign_block_vars('meta_authors', array(
				'AUTHOR_NAME'		=> $author['name'],
				'AUTHOR_EMAIL'		=> (isset($author['email'])) ? $author['email'] : '',
				'AUTHOR_HOMEPAGE'	=> (isset($author['homepage'])) ? $author['homepage'] : '',
				'AUTHOR_ROLE'		=> (isset($author['role'])) ? $author['role'] : '',
			));
		}
	}

	/**
	* Outputs extension version check information into the template
	*
	* @param string $ext_name Name of the extension to check for new versions
	* @return null
	*/
	protected function output_versioncheck_to_template($ext_name)
	{
		try
		{
			$updates_available = $this->ext_manager->version_check_name($ext_name, $this->request->variable('versioncheck_force', false), false, $this->config['extension_force_unstable'] ? 'unstable' : null);

			$this->template->assign_vars(array(
				'S_VERSIONCHECK'	=> true,
				'S_UP_TO_DATE'		=> empty($updates_available),
				'UP_TO_DATE_MSG'	=> $this->user->lang(empty($updates_available) ? 'UP_TO_DATE' : 'NOT_UP_TO_DATE', $this->ext_manager->get_extension_metadata($ext_name, 'display-name')),
			));

			foreach ($updates_available as $branch => $version_data)
			{
				$this->template->assign_block_vars('updates_available', $version_data);
			}
		}
		catch (exception_interface $e)
		{
			$message = call_user_func_array(array($this->user, 'lang'), array_merge(array($e->getMessage()), $e->get_parameters()));

			$this->template->assign_vars(array(
				'S_VERSIONCHECK'			=> ($e->getMessage() !== 'NO_VERSIONCHECK') ? true : false,
				'S_VERSIONCHECK_FAIL'		=> true,
				'VERSIONCHECK_FAIL_REASON'	=> ($e->getMessage() !== 'VERSIONCHECK_FAIL') ? $message : '',
			));
		}
	}

	/**
	 * Converts an array of extensions into a single HTML string for displaying
	 *
	 * @param array	$ext_list	List of extensions
	 * @return string			The HTML string with the list of extensions
	 */
	protected function to_html_string($ext_list)
	{
		$html_string = '';
		foreach($ext_list as $ext_name)
		{
			$html_string .= '<br/>';
			$html_string .= $this->ext_manager->get_extension_metadata($ext_name, 'display-name');
		}
		return $html_string;
	}

	/**
	 * Generate extension list template array
	 * Given an array of extension names, returns an array of template data for each extension
	 * The returned array is suitable to be used by assign_block_vars_array
	 *
	 * @param array		$ext_list	Extension list
	 * @param string	$check_done	Name of method to check if action is completed
	 * @return array				Array of arrays of template data, one entry per extension in the list
	 */
	protected function ext_list_data($ext_list, $check_done)
	{
		$ext_list_data = array();
		foreach($ext_list as $ext_name)
		{
			$ext_list_data[] = array(
										'NAME'			=> $ext_name,
										'DISPLAY_NAME'	=> $this->ext_manager->get_extension_metadata($ext_name, 'display-name'),
										'VERSION'		=> $this->ext_manager->get_extension_metadata($ext_name, 'version'),
										'S_DONE'		=> $this->ext_manager->$check_done($ext_name),
										'U_DETAILS'		=> $this->u_action . '&amp;action=details&amp;ext_name=' . urlencode($ext_name),
									);
		}
		return $ext_list_data;
	}
}
