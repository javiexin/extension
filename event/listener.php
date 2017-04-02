<?php
/**
 *
 * Improved Extension Management. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, javiexin
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace javiexin\extension\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Improved extension management Event listener.
 */
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\request\request */
	protected $request;
	/** @var ContainerInterface */
	protected $container;

	/**
	 * Constructor of event listener
	 *
	 * @param \phpbb\request\request				$request		Request object
	 * @param ContainerInterface					$container		Container
	 */
	public function __construct(\phpbb\request\request $request, ContainerInterface $container)
	{
		$this->request = $request;
		$this->container = $container;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_extensions_run_action_before'	=> array(
															array('prepare_multi_action', 123456), // High priority number, as this listener should run first
															array('bypass_acp_extension', -123456), // Low priority number, as this listener should run last
														),
			'core.acp_extensions_run_action'		=> array('workaround_acp_extension', 123456), // Workaround for 3.2.0, should run first
			'core.acp_extensions_run_action_after'	=> array('restore_acp_extension', 123456), // High priority number, as this listener should run first
		);
	}

	/**
	 * Read the request parameters for multi action, and add them to the event
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function prepare_multi_action($event)
	{
		$ext_name = $event['ext_name'];

		$controller = $this->container->get('javiexin.extension.acp.controller');
		// Get the list of extensions for multi actions; may set ext_name
		$ext_list = $controller->prepare_multi_action($event['action'], $ext_name);

		$event['ext_name'] = $ext_name;
		$event['ext_list'] = $ext_list;
	}

	/**
	 * Replaces the execution of the acp_extension module code with this extension controller
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function bypass_acp_extension($event)
	{
		$controller = $this->container->get('javiexin.extension.acp.controller');

		$controller->setup_from_listener($event);
		$controller->execute($event['action'], $event['ext_name'], $event['ext_list'], $event['start_time'] + $event['safe_time_limit']);
		$event = $controller->update_event($event);

		// Save original values for action and ext_name, to be recovered in the next event
		$this->request->overwrite('original_action', $event['action']);
		$this->request->overwrite('original_ext_name', $event['ext_name']);
		// Set action and ext_name to values avoiding execution of the main block in acp_extension
		$event['action']	= 'none';
		$event['ext_name']	= '';
	}

	/**
	 * Restores the original state to correctly run other extension events
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function restore_acp_extension($event)
	{
		// Restore original values for action and ext_name
		$event['action']	= $this->request->variable('original_action', $event['action']);
		$event['ext_name']	= $this->request->variable('original_ext_name', $event['ext_name']);
	}

	/**
	 * Replaces the execution of the acp_extension module code with this extension controller, specific for 3.2.0
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function workaround_acp_extension($event)
	{
		if (($event['action'] == 'list') || $this->request->is_set_post('cancel'))
		{
			if ($this->request->variable('improved', false) && confirm_box(true))
			{
				$u_action = str_replace('acp_extensions', '-javiexin-extension-acp-extensions_module', $event['u_action']);
				meta_refresh(2, $u_action);
				trigger_error('ACP_EXTENSION_REDIRECT_IMPROVED', E_USER_NOTICE);
			}
			else
			{
				confirm_box(false, 'ACP_EXTENSION_USE_IMPROVED', build_hidden_fields(array('improved' => true)));
			}
		}
	}
}
