<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Base class for access controlled controllers
 *
 * @see            http://github.com/banks/aacl
 * @package        ACL
 * @uses        Auth
 * @uses        Sprig
 * @author        Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license        MIT
 */
class Controller_ACL extends Controller_Template implements ACL_Resource {

	/**
	 * ACL_Resource::acl_id() implementation
	 *
	 * @return    string
	 */
	public function acl_id()
	{
		// Controller namespace, controller name
		return 'c:'.strtolower(substr(get_class($this), 11));
	}

	/**
	 * ACL_Resource::acl_actions() implementation
	 *
	 * @param    bool $return_current [optional]
	 * @return    mixed
	 */
	public function acl_actions($return_current = FALSE)
	{
		if ($return_current)
		{
			return $this->request->action();
		}

		// Find all actions in this class
		$reflection = new ReflectionClass($this);

		$actions = array();

		// Add all public methods that start with 'action_'
		foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
		{
			if (substr($method->name, 0, 7) === 'action_')
			{
				$actions[] = substr($method->name, 7);
			}
		}

		return $actions;
	}

	/**
	 * ACL_Resource::acl_conditions() implementation
	 *
	 * @param    Model_User $user [optional] logged in user model
	 * @param    string     $condition [optional] condition to test
	 * @throws   ACL_Exception
	 * @return   mixed
	 */
	public function acl_conditions(Model_User $user = NULL, $condition = NULL)
	{
		if (is_null($user) AND is_null($condition))
		{
			// We have no conditions
			return array();
		}

		// We have no conditions so this test should fail!
		return FALSE;
	}

	/**
	 * ACL_Resource::acl_instance() implementation
	 *
	 * Note that the object instance returned should not be used for anything except querying the acl_* methods
	 *
	 * @param    string $class_name Class name of object required
	 * @return    Object
	 */
	public static function acl_instance($class_name)
	{
		// Return controller instance populated with manipulated request details
		$instance = new $class_name(Request::current(), Response::factory());
		// Remove "controller_" part from name
		$controller_name = strtolower(substr($class_name, 11));

		if ($controller_name !== Request::current()->controller())
		{
			// Manually override controller name and action
			$instance->request->controller(strtolower($controller_name));

			$instance->request->action('');
		}

		return $instance;
	}
} // End Controller_ACL_Core
