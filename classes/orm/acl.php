<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Base class for access controlled ORM Models
 *
 * @see          http://github.com/banks/aacl
 * @package      ACL
 * @uses         Auth
 * @uses         ORM
 * @author       Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license      MIT
 */
abstract class ORM_ACL extends ORM implements ACL_Resource {

	/**
	 * @var array
	 */
	protected $_acl_orm_actions = array('create', 'read', 'update', 'delete');

	/**
	 * @var string
	 */
	protected $_acl_id = '';

	/**
	 * ACL_Resource::acl_id() implementation
	 *
	 * Note: keeps a cache of the acl_id and returns it if the model hasn't changed
	 *
	 * @return    string
	 */
	public function acl_id()
	{
		if ($this->_acl_id and ! $this->changed())
		{
			return $this->_acl_id;
		}

		// Create unique id from primary key if it is set
		$id = (string) $this->pk();

		if ($id)
		{
			$id = '.'.$id;
		}

		// Model namespace, model name, pk
		$this->_acl_id = 'm:'.strtolower($this->object_name()).$id;
		return $this->_acl_id;
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
			// We don't know anything about what the user intends to do with us!
			return NULL;
		}

		$class = new ReflectionClass($this);

		$methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

		$acl_actions = array();
		foreach ($methods as $method)
		{
			if ($method->class != get_called_class()) continue;

			if ($method->isStatic() || $method->isAbstract()) continue;

			if ( ! strpos($method->getDocComment(), '@acl')) continue;

			$acl_actions[] = $method->name;
		}

		// Return default model actions
		return array_merge($acl_actions, $this->_acl_orm_actions);
	}

	/**
	 * ACL_Resource::acl_conditions() implementation
	 *
	 * @param    Model_User $user [optional] logged in user model
	 * @param    string     $condition [optional] condition to test
	 * @return    mixed
	 */
	public function acl_conditions(Model_User $user = NULL, $condition = NULL)
	{
		if (is_null($user) AND is_null($condition))
		{
			// We have no conditions - they will be model specific
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
		$model_name = strtolower(substr($class_name, 6));

		return ORM::factory($model_name);
	}
} // End ORM_ACL
