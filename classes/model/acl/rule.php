<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * Access rule model
 *
 * @see            http://github.com/banks/aacl
 * @package        ACL
 * @uses        Auth
 * @uses        ORM
 * @author        Paul Banks
 * @copyright    (c) Paul Banks 2010
 * @license        MIT
 */
class Model_ACL_Rule extends ORM_ACL {

	/**
	 * Override Default model actions
	 */
	protected $_acl_orm_actions = array();

	protected $_table_name = 'acl';

	protected $_primary_key = 'id';

	protected $_table_columns = array(
		'id' => array('type' => 'int'),
		'role_id' => array('type' => 'int', 'null' => TRUE),
		'resource' => array('type' => 'varchar'),
		'action' => array('type' => 'varchar'),
		'condition' => array('type' => 'varchar'),
		'description' => array('type' => 'varchar'),
		'created' => array('type' => 'int'),
		'updated' => array('type' => 'int'),
	);

	protected $_created_column = array('column' => 'created', 'format' => TRUE);
	protected $_updated_column = array('column' => 'updated', 'format' => TRUE);

	protected $_belongs_to = array(
		'role' => array(
			'model' => 'Role',
			'foreign_key' => 'role_id',
		),
	);

	// TODO: validation

	/**
	 * @acl
	 * grant access / create rule
	 *
	 * @param array $data
	 * @return $this
	 * @throws Exception
	 */
	public function grant(array $data)
	{
		$this->values($data);
		$this->check();
		if ( ! $this->role_id)
		{
			$this->role_id = NULL;
		}
		$this->save();
		return $this;
	}

	/**
	 * @acl
	 * revoke access / delete rule
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function revoke()
	{
		if ( ! $this->loaded())
		{
			throw new Exception('rule doesn\'t exist');
		}

		ACL::revoke($this->role, $this->resource, $this->action, $this->condition);

		return TRUE;
	}

	/**
	 * Check if rule matches current request
	 *
	 * @param ACL_Resource $resource ACL_Resource object or it's id that user requested access to
	 * @param string               $action action requested [optional]
	 * @param Model_User           $user ACL instance
	 * @return bool
	 */
	public function allows_access_to(ACL_Resource $resource, $action = NULL, Model_User $user = NULL)
	{
		if ( ! $this->_object['resource'])
		{
			// this rule is invalid
			// No point checking anything else!
			return FALSE;
		}

		if ($action === NULL)
		{
			// Check to see if Resource wants to define it's own action
			$action = $resource->acl_actions(TRUE);
		}

		// Make sure action matches
		if ($this->_object['action'] AND $action !== $this->_object['action'])
		{
			// This rule has a specific action and it doesn't match the specific one passed
			return FALSE;
		}

		$resource_id = $resource->acl_id();

		$matches = FALSE;

		// Make sure rule resource is the same as requested resource, or is an ancestor
		while ( ! $matches)
		{
			// Attempt match
			if ($this->_object['resource'] === $resource_id)
			{
				// Stop loop
				$matches = TRUE;
			}
			else
			{
				// Find last occurence of '.' separator
				$last_dot_pos = strrpos($resource_id, '.');

				if ($last_dot_pos !== FALSE)
				{
					// This rule might match more generally, try the next level of specificity
					$resource_id = substr($resource_id, 0, $last_dot_pos);
				}
				else
				{
					// We can't make this any more general as there are no more dots
					// And we haven't managed to match the resource requested
					return FALSE;
				}
			}
		}

		// Now we know this rule matches the resource, check any match condition
		if ($this->_object['condition'] AND ! $resource->acl_conditions($user, $this->_object['condition']))
		{
			// Condition wasn't met (or doesn't exist)
			return FALSE;
		}

		// All looks rosy!
		return TRUE;
	}

	/**
	 * Override create to remove less specific rules when creating a rule
	 *
	 * @param Validation $validation
	 * @return $this
	 */
	public function create(Validation $validation = NULL)
	{
		// Delete all more specific rules for this role
		$delete = DB::delete($this->_table_name);
		if (isset($this->_changed['role']))
		{
			$delete->where('role_id', '=', $this->_changed['role']);
		}
		else
		{
			$delete->where('role_id', 'IS', NULL);
		}

		// If resource is NULL we don't need any more rules - we just delete every rule for this role

		// Otherwise
		if ( ! is_null($this->resource))
		{
			// Need to restrict to roles with equal or more specific resource id
			$delete->where_open()
				->where('resource', '=', $this->resource)
				->or_where('resource', 'LIKE', $this->resource.'.%')
				->where_close();
		}

		if ( ! is_null($this->action))
		{
			// If this rule has an action, only remove other rules with the same action
			$delete->where('action', '=', $this->action);
		}

		if ( ! is_null($this->condition))
		{
			// If this rule has a condition, only remove other rules with the same condition
			$delete->where('condition', '=', $this->condition);
		}

		// Do the delete
		$delete->execute();

		// Create new rule
		return parent::create($validation);
	}

} // End Model_ACL_Core_Rule
