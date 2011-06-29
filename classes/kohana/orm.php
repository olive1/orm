<?php defined('SYSPATH') or die('No direct script access.');
/**
 * [Object Relational Mapping][ref-orm] (ORM) is a method of abstracting database
 * access to standard PHP calls. All table rows are represented as model objects,
 * with object properties representing row data. ORM in Kohana generally follows
 * the [Active Record][ref-act] pattern.
 *
 * [ref-orm]: http://wikipedia.org/wiki/Object-relational_mapping
 * [ref-act]: http://wikipedia.org/wiki/Active_record
 *
 * @package    Kohana/ORM
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaframework.org/license
 *
 *
 * @method ORM where()
 * @method ORM and_where()
 * @method ORM or_where()
 * @method ORM where_open()
 * @method ORM and_where_open()
 * @method ORM or_where_open()
 * @method ORM where_close()
 * @method ORM and_where_close()
 * @method ORM or_where_close()
 * @method ORM distinct()
 * @method ORM select()
 * @method ORM from()
 * @method ORM join()
 * @method ORM on()
 * @method ORM group_by()
 * @method ORM having()
 * @method ORM and_having()
 * @method ORM or_having()
 * @method ORM having_open()
 * @method ORM and_having_open()
 * @method ORM or_having_open()
 * @method ORM having_close()
 * @method ORM and_having_close()
 * @method ORM or_having_close()
 * @method ORM order_by()
 * @method ORM limit()
 * @method ORM offset()
 * @method ORM cached()
 * @method Validation validation()
 * @method Array object()
 *
 * @property string $object_name Name of the model
 * @property string $object_plural Plural name of the model
 * @property bool $loaded ORM object was loaded?
 * @property bool $saved ORM object was saved?
 * @property mixed $primary_key
 * @property mixed $primary_val
 * @property string $table_name
 * @property string $table_columns
 * @property array $has_one
 * @property array $belongs_to
 * @property array $has_many
 * @property array $has_many_through
 * @property array $load_with
 * @property string $updated_column
 * @property string $created_column
 */
class Kohana_ORM extends Model implements serializable {

	/**
	 * Stores column information for ORM models
	 * @var array
	 */
	protected static $_column_cache = array();

	/**
	 * Callable database methods
	 * @var array
	 */
	protected static $_db_methods = array
	(
		'where', 'and_where', 'or_where', 'where_open', 'and_where_open', 'or_where_open', 'where_close',
		'and_where_close', 'or_where_close', 'distinct', 'select', 'from', 'join', 'on', 'group_by',
		'having', 'and_having', 'or_having', 'having_open', 'and_having_open', 'or_having_open',
		'having_close', 'and_having_close', 'or_having_close', 'order_by', 'limit', 'offset', 'cached',
	);

	/**
	 * Members that have access methods
	 * @var array
	 */
	protected static $_properties = array
	(
		'object_name', 'object_plural', 'loaded', 'saved', // Object
		'primary_key', 'primary_val', 'table_name', 'table_columns', // Table
		'has_one', 'belongs_to', 'has_many', 'has_many_through', 'load_with', // Relationships
		'updated_column', 'created_column',
		'validation',
		'object',
	);

	/**
	 * Creates and returns a new model.
	 *
	 * @chainable
	 * @param   string  $model  Model name
	 * @param   mixed   $id     Parameter for find()
	 * @return  ORM
	 */
	public static function factory($model, $id = NULL)
	{
		// Set class name
		$model = 'Model_'.ucfirst($model);

		return new $model($id);
	}

	/**
	 * "Has one" relationships
	 * @var array
	 */
	protected $_has_one = array();

	/**
	 * "Belongs to" relationships
	 * @var array
	 */
	protected $_belongs_to = array();

	/**
	 * "Has many" relationships
	 * @var array
	 */
	protected $_has_many = array();

	/**
	 * Relationships that should always be joined
	 * @var array
	 */
	protected $_load_with = array();

	/**
	 * Validation object created before saving/updating
	 * @var Validation
	 */
	protected $_validation = NULL;

	/**
	 * Current object
	 * @var array
	 */
	protected $_object = array();

	/**
	 * @var array
	 */
	protected $_changed = array();

	/**
	 * @var array
	 */
	protected $_related = array();

	/**
	 * @var bool
	 */
	protected $_valid = FALSE;

	/**
	 * @var bool
	 */
	protected $_loaded = FALSE;

	/**
	 * @var bool
	 */
	protected $_saved = FALSE;

	/**
	 * @var array
	 */
	protected $_sorting;

	/**
	 * Foreign key suffix
	 * @var string
	 */
	protected $_foreign_key_suffix = '_id';

	/**
	 * Model name
	 * @var string
	 */
	protected $_object_name;

	/**
	 * Plural model name
	 * @var string
	 */
	protected $_object_plural;

	/**
	 * Table name
	 * @var string
	 */
	protected $_table_name;

	/**
	 * Table columns
	 * @var array
	 */
	protected $_table_columns;

	/**
	 * Auto-update columns for updates
	 * @var string
	 */
	protected $_updated_column = NULL;

	/**
	 * Auto-update columns for creation
	 * @var string
	 */
	protected $_created_column = NULL;

	/**
	 * Table primary key
	 * @var string
	 */
	protected $_primary_key = 'id';

	/**
	 * Primary key value
	 * @var mixed
	 */
	protected $_primary_key_value;

	/**
	 * Model configuration, table names plural?
	 * @var bool
	 */
	protected $_table_names_plural = TRUE;

	/**
	 * Model configuration, reload on wakeup?
	 * @var bool
	 */
	protected $_reload_on_wakeup = TRUE;

	/**
	 * Database Object
	 * @var Database
	 */
	protected $_db = NULL;

	/**
	 * Database config group
	 * @var String
	 */
	protected $_db_group = NULL;

	/**
	 * Database methods applied
	 * @var array
	 */
	protected $_db_applied = array();

	/**
	 * Database methods pending
	 * @var array
	 */
	protected $_db_pending = array();

	/**
	 * Reset builder
	 * @var bool
	 */
	protected $_db_reset = TRUE;

	/**
	 * Database query builder
	 * @var Database_Query_Builder_Where
	 */
	protected $_db_builder;

	/**
	 * With calls already applied
	 * @var array
	 */
	protected $_with_applied = array();

	/**
	 * Data to be loaded into the model from a database call cast
	 * @var array
	 */
	protected $_cast_data = array();

	/**
	 * Constructs a new model and loads a record if given
	 *
	 * @param   mixed $id Parameter for find or object to load
	 * @return  void
	 */
	public function __construct($id = NULL)
	{
		$this->_initialize();

		if ($id !== NULL)
		{
			if (is_array($id))
			{
				foreach ($id as $column => $value)
				{
					// Passing an array of column => values
					$this->where($column, '=', $value);
				}

				$this->find();
			}
			else
			{
				// Passing the primary key
				$this->where($this->_table_name.'.'.$this->_primary_key, '=', $id)->find();
			}
		}
		elseif ( ! empty($this->_cast_data))
		{
			// Load preloaded data from a database call cast
			$this->_load_values($this->_cast_data);

			$this->_cast_data = array();
		}
	}

	/**
	 * Prepares the model database connection, determines the table name,
	 * and loads column information.
	 *
	 * @return void
	 */
	protected function _initialize()
	{
		// Set the object name and plural name
		$this->_object_name = strtolower(substr(get_class($this), 6));
		$this->_object_plural = Inflector::plural($this->_object_name);

		if ( ! is_object($this->_db))
		{
			// Get database instance
			$this->_db = Database::instance($this->_db_group);
		}

		if (empty($this->_table_name))
		{
			// Table name is the same as the object name
			$this->_table_name = $this->_object_name;

			if ($this->_table_names_plural === TRUE)
			{
				// Make the table name plural
				$this->_table_name = Inflector::plural($this->_table_name);
			}
		}

		foreach ($this->_belongs_to as $alias => $details)
		{
			$defaults['model'] = $alias;
			$defaults['foreign_key'] = $alias.$this->_foreign_key_suffix;

			$this->_belongs_to[$alias] = array_merge($defaults, $details);
		}

		foreach ($this->_has_one as $alias => $details)
		{
			$defaults['model'] = $alias;
			$defaults['foreign_key'] = $this->_object_name.$this->_foreign_key_suffix;

			$this->_has_one[$alias] = array_merge($defaults, $details);
		}

		foreach ($this->_has_many as $alias => $details)
		{
			$defaults['model'] = Inflector::singular($alias);
			$defaults['foreign_key'] = $this->_object_name.$this->_foreign_key_suffix;
			$defaults['through'] = NULL;
			$defaults['far_key'] = Inflector::singular($alias).$this->_foreign_key_suffix;

			$this->_has_many[$alias] = array_merge($defaults, $details);
		}

		// Load column information
		$this->reload_columns();

		// Clear initial model state
		$this->clear();
	}

	/**
	 * Initializes validation rules, and labels
	 *
	 * @return void
	 */
	protected function _validation()
	{
		// Build the validation object with its rules
		$this->_validation = Validation::factory($this->_object)
			->bind(':model', $this);

		foreach ($this->rules() as $field => $rules)
		{
			$this->_validation->rules($field, $rules);
		}

		// Use column names by default for labels
		$columns = array_keys($this->_table_columns);

		// Merge user-defined labels
		$labels = array_merge(array_combine($columns, $columns), $this->labels());

		foreach ($labels as $field => $label)
		{
			$this->_validation->label($field, $label);
		}
	}

	/**
	 * Reload column definitions.
	 *
	 * @chainable
	 * @param   boolean $force Force reloading
	 * @return  ORM
	 */
	public function reload_columns($force = FALSE)
	{
		if ($force === TRUE OR empty($this->_table_columns))
		{
			if (isset(ORM::$_column_cache[$this->_object_name]))
			{
				// Use cached column information
				$this->_table_columns = ORM::$_column_cache[$this->_object_name];
			}
			else
			{
				// Grab column information from database
				$this->_table_columns = $this->list_columns(TRUE);

				// Load column cache
				ORM::$_column_cache[$this->_object_name] = $this->_table_columns;
			}
		}

		return $this;
	}

	/**
	 * Unloads the current object and clears the status.
	 *
	 * @chainable
	 * @return ORM
	 */
	public function clear()
	{
		// Create an array with all the columns set to NULL
		$values = array_combine(array_keys($this->_table_columns), array_fill(0, count($this->_table_columns), NULL));

		// Replace the object and reset the object status
		$this->_object = $this->_changed = $this->_related = array();

		// Replace the current object with an empty one
		$this->_load_values($values);

		// Reset primary key
		$this->_primary_key_value = NULL;

		$this->reset();

		return $this;
	}

	/**
	 * Reloads the current object from the database.
	 *
	 * @chainable
	 * @return ORM
	 */
	public function reload()
	{
		$primary_key = $this->pk();

		// Replace the object and reset the object status
		$this->_object = $this->_changed = $this->_related = array();

		// Only reload the object if we have one to reload
		if ($this->_loaded)
			return $this->clear()
				->where($this->_table_name.'.'.$this->_primary_key, '=', $primary_key)
				->find();
		else
			return $this->clear();
	}

	/**
	 * Checks if object data is set.
	 *
	 * @param  string $column Column name
	 * @return boolean
	 */
	public function __isset($column)
	{
		return (isset($this->_object[$column]) OR
			isset($this->_related[$column]) OR
			isset($this->_has_one[$column]) OR
			isset($this->_belongs_to[$column]) OR
			isset($this->_has_many[$column]));
	}

	/**
	 * Unsets object data.
	 *
	 * @param  string $column Column name
	 * @return void
	 */
	public function __unset($column)
	{
		unset($this->_object[$column], $this->_changed[$column], $this->_related[$column]);
	}

	/**
	 * Displays the primary key of a model when it is converted to a string.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->pk();
	}

	/**
	 * Allows serialization of only the object data and state, to prevent
	 * "stale" objects being unserialized, which also requires less memory.
	 *
	 * @return array
	 */
	public function serialize()
	{
		// Store only information about the object
		foreach (array('_primary_key_value', '_object', '_changed', '_loaded', '_saved', '_sorting') as $var)
		{
			$data[$var] = $this->{$var};
		}

		return serialize($data);
	}

	/**
	 * Prepares the database connection and reloads the object.
	 *
	 * @param string $data String for unserialization
	 * @return  void
	 */
	public function unserialize($data)
	{
		// Initialize model
		$this->_initialize();

		foreach (unserialize($data) as $name => $var)
		{
			$this->{$name} = $var;
		}

		if ($this->_reload_on_wakeup === TRUE)
		{
			// Reload the object
			$this->reload();
		}
	}

	/**
	 * Handles pass-through to database methods. Calls to query methods
	 * (query, get, insert, update) are not allowed. Query builder methods
	 * are chainable.
	 *
	 * @param   string  $method Method name
	 * @param   array   $args   Method arguments
	 * @return  mixed
	 */
	public function __call($method, array $args)
	{
		if (in_array($method, ORM::$_properties))
		{
			if ($method === 'validation')
			{
				if ( ! isset($this->_validation))
				{
					// Initialize the validation object
					$this->_validation();
				}
			}

			// Return the property
			return $this->{'_'.$method};
		}
		elseif (in_array($method, ORM::$_db_methods))
		{
			// Add pending database call which is executed after query type is determined
			$this->_db_pending[] = array('name' => $method, 'args' => $args);

			return $this;
		}
		else
		{
			throw new Kohana_Exception('Invalid method :method called in :class',
				array(':method' => $method, ':class' => get_class($this)));
		}
	}

	/**
	 * Handles retrieval of all model values, relationships, and metadata.
	 *
	 * @param   string $column Column name
	 * @return  mixed
	 */
	public function __get($column)
	{
		if (array_key_exists($column, $this->_object))
		{
			return $this->_object[$column];
		}
		elseif (isset($this->_related[$column]))
		{
			// Return related model that has already been fetched
			return $this->_related[$column];
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			$model = $this->_related($column);

			// Use this model's column and foreign model's primary key
			$col = $model->_table_name.'.'.$model->_primary_key;
			$val = $this->_object[$this->_belongs_to[$column]['foreign_key']];

			$model->where($col, '=', $val)->find();

			return $this->_related[$column] = $model;
		}
		elseif (isset($this->_has_one[$column]))
		{
			$model = $this->_related($column);

			// Use this model's primary key value and foreign model's column
			$col = $model->_table_name.'.'.$this->_has_one[$column]['foreign_key'];
			$val = $this->pk();

			$model->where($col, '=', $val)->find();

			return $this->_related[$column] = $model;
		}
		elseif (isset($this->_has_many[$column]))
		{
			$model = ORM::factory($this->_has_many[$column]['model']);

			if (isset($this->_has_many[$column]['through']))
			{
				// Grab has_many "through" relationship table
				$through = $this->_has_many[$column]['through'];

				// Join on through model's target foreign key (far_key) and target model's primary key
				$join_col1 = $through.'.'.$this->_has_many[$column]['far_key'];
				$join_col2 = $model->_table_name.'.'.$model->_primary_key;

				$model->join($through)->on($join_col1, '=', $join_col2);

				// Through table's source foreign key (foreign_key) should be this model's primary key
				$col = $through.'.'.$this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}
			else
			{
				// Simple has_many relationship, search where target model's foreign key is this model's primary key
				$col = $model->_table_name.'.'.$this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}

			return $model->where($col, '=', $val);
		}
		else
		{
			throw new Kohana_Exception('The :property property does not exist in the :class class',
				array(':property' => $column, ':class' => get_class($this)));
		}
	}

	/**
	 * Base set method - this should not be overridden.
	 *
	 * @param  string $column  Column name
	 * @param  mixed  $value   Column value
	 * @return void
	 */
	public function __set($column, $value)
	{
		if ( ! isset($this->_object_name))
		{
			// Object not yet constructed, so we're loading data from a database call cast
			$this->_cast_data[$column] = $value;
		}
		else
		{
			// Set the model's column to given value
			$this->set($column, $value);
		}
	}

	/**
	 * Handles setting of column
	 *
	 * @param  string $column Column name
	 * @param  mixed  $value  Column value
	 * @return void
	 */
	public function set($column, $value)
	{
		if (array_key_exists($column, $this->_object))
		{
			// Filter the data
			$value = $this->run_filter($column, $value);

			// See if the data really changed
			if ($value !== $this->_object[$column])
			{
				$this->_object[$column] = $value;

				// Data has changed
				$this->_changed[$column] = $column;

				// Object is no longer saved or valid
				$this->_saved = $this->_valid = FALSE;
			}
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			// Update related object itself
			$this->_related[$column] = $value;

			// Update the foreign key of this model
			$this->_object[$this->_belongs_to[$column]['foreign_key']] = $value->pk();

			$this->_changed[$column] = $this->_belongs_to[$column]['foreign_key'];
		}
		else
		{
			throw new Kohana_Exception('The :property: property does not exist in the :class: class',
				array(':property:' => $column, ':class:' => get_class($this)));
		}

		return $this;
	}

	/**
	 * Set values from an array with support for one-one relationships.  This method should be used
	 * for loading in post data, etc.
	 *
	 * @param  array $values   Array of column => val
	 * @param  array $expected Array of keys to take from $values
	 * @return ORM
	 */
	public function values(array $values, array $expected = NULL)
	{
		// Default to expecting everything except the primary key
		if ($expected === NULL)
		{
			$expected = array_keys($this->_table_columns);

			// Don't set the primary key by default
			unset($values[$this->_primary_key]);
		}

		foreach ($expected as $key => $column)
		{
			if (is_string($key))
			{
				// isset() fails when the value is NULL (we want it to pass)
				if ( ! array_key_exists($key, $values))
					continue;

				// Try to set values to a related model
				$this->{$key}->values($values[$key], $column);
			}
			else
			{
				// isset() fails when the value is NULL (we want it to pass)
				if ( ! array_key_exists($column, $values))
					continue;

				// Update the column, respects __set()
				$this->$column = $values[$column];
			}
		}

		return $this;
	}

	/**
	 * Returns the values of this object as an array, including any related one-one
	 * models that have already been loaded using with()
	 *
	 * @return array
	 */
	public function as_array()
	{
		$object = array();

		foreach ($this->_object as $column => $value)
		{
			// Call __get for any user processing
			$object[$column] = $this->__get($column);
		}

		foreach ($this->_related as $column => $model)
		{
			// Include any related objects that are already loaded
			$object[$column] = $model->as_array();
		}

		return $object;
	}

	/**
	 * Binds another one-to-one object to this model.  One-to-one objects
	 * can be nested using 'object1:object2' syntax
	 *
	 * @param  string $target_path Target model to bind to
	 * @return void
	 */
	public function with($target_path)
	{
		if (isset($this->_with_applied[$target_path]))
		{
			// Don't join anything already joined
			return $this;
		}

		// Split object parts
		$aliases = explode(':', $target_path);
		$target = $this;
		foreach ($aliases as $alias)
		{
			// Go down the line of objects to find the given target
			$parent = $target;
			$target = $parent->_related($alias);

			if ( ! $target)
			{
				// Can't find related object
				return $this;
			}
		}

		// Target alias is at the end
		$target_alias = $alias;

		// Pop-off top alias to get the parent path (user:photo:tag becomes user:photo - the parent table prefix)
		array_pop($aliases);
		$parent_path = implode(':', $aliases);

		if (empty($parent_path))
		{
			// Use this table name itself for the parent path
			$parent_path = $this->_table_name;
		}
		else
		{
			if ( ! isset($this->_with_applied[$parent_path]))
			{
				// If the parent path hasn't been joined yet, do it first (otherwise LEFT JOINs fail)
				$this->with($parent_path);
			}
		}

		// Add to with_applied to prevent duplicate joins
		$this->_with_applied[$target_path] = TRUE;

		// Use the keys of the empty object to determine the columns
		foreach (array_keys($target->_object) as $column)
		{
			$name = $target_path.'.'.$column;
			$alias = $target_path.':'.$column;

			// Add the prefix so that load_result can determine the relationship
			$this->select(array($name, $alias));
		}

		if (isset($parent->_belongs_to[$target_alias]))
		{
			// Parent belongs_to target, use target's primary key and parent's foreign key
			$join_col1 = $target_path.'.'.$target->_primary_key;
			$join_col2 = $parent_path.'.'.$parent->_belongs_to[$target_alias]['foreign_key'];
		}
		else
		{
			// Parent has_one target, use parent's primary key as target's foreign key
			$join_col1 = $parent_path.'.'.$parent->_primary_key;
			$join_col2 = $target_path.'.'.$parent->_has_one[$target_alias]['foreign_key'];
		}

		// Join the related object into the result
		$this->join(array($target->_table_name, $target_path), 'LEFT')->on($join_col1, '=', $join_col2);

		return $this;
	}

	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param  integer $type Type of Database query
	 * @return ORM
	 */
	protected function _build($type)
	{
		// Construct new builder object based on query type
		switch ($type)
		{
			case Database::SELECT:
				$this->_db_builder = DB::select();
			break;
			case Database::UPDATE:
				$this->_db_builder = DB::update($this->_table_name);
			break;
			case Database::DELETE:
				$this->_db_builder = DB::delete($this->_table_name);
		}

		// Process pending database method calls
		foreach ($this->_db_pending as $method)
		{
			$name = $method['name'];
			$args = $method['args'];

			$this->_db_applied[$name] = $name;

			call_user_func_array(array($this->_db_builder, $name), $args);
		}

		return $this;
	}

	/**
	 * Finds and loads a single database row into the object.
	 *
	 * @chainable
	 * @return ORM
	 */
	public function find()
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Method find() cannot be called on loaded objects');

		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind auto relationships
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		return $this->_load_result(FALSE);
	}

	/**
	 * Finds multiple database rows and returns an iterator of the rows found.
	 *
	 * @return Database_Result
	 */
	public function find_all()
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Method find_all() cannot be called on loaded objects');

		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind auto relationships
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		return $this->_load_result(TRUE);
	}

	/**
	 * Loads a database result, either as a new record for this model, or as
	 * an iterator for multiple rows.
	 *
	 * @chainable
	 * @param  bool $multiple Return an iterator or load a single row
	 * @return ORM|Database_Result
	 */
	protected function _load_result($multiple = FALSE)
	{
		$this->_db_builder->from($this->_table_name);

		if ($multiple === FALSE)
		{
			// Only fetch 1 record
			$this->_db_builder->limit(1);
		}

		// Select all columns by default
		$this->_db_builder->select($this->_table_name.'.*');

		if ( ! isset($this->_db_applied['order_by']) AND ! empty($this->_sorting))
		{
			foreach ($this->_sorting as $column => $direction)
			{
				if (strpos($column, '.') === FALSE)
				{
					// Sorting column for use in JOINs
					$column = $this->_table_name.'.'.$column;
				}

				$this->_db_builder->order_by($column, $direction);
			}
		}

		if ($multiple === TRUE)
		{
			// Return database iterator casting to this object type
			$result = $this->_db_builder->as_object(get_class($this))->execute($this->_db);

			$this->reset();

			return $result;
		}
		else
		{
			// Load the result as an associative array
			$result = $this->_db_builder->as_assoc()->execute($this->_db);

			$this->reset();

			if ($result->count() === 1)
			{
				// Load object values
				$this->_load_values($result->current());
			}
			else
			{
				// Clear the object, nothing was found
				$this->clear();
			}

			return $this;
		}
	}

	/**
	 * Loads an array of values into into the current object.
	 *
	 * @chainable
	 * @param  array $values Values to load
	 * @return ORM
	 */
	protected function _load_values(array $values)
	{
		if (array_key_exists($this->_primary_key, $values))
		{
			if ($values[$this->_primary_key] !== NULL)
			{
				// Flag as loaded, saved, and valid
				$this->_loaded = $this->_saved = $this->_valid = TRUE;

				// Store primary key
				$this->_primary_key_value = $values[$this->_primary_key];
			}
			else
			{
				// Not loaded, saved, or valid
				$this->_loaded = $this->_saved = $this->_valid = FALSE;
			}
		}

		// Related objects
		$related = array();

		foreach ($values as $column => $value)
		{
			if (strpos($column, ':') === FALSE)
			{
				// Load the value to this model
				$this->_object[$column] = $value;
			}
			else
			{
				// Column belongs to a related model
				list ($prefix, $column) = explode(':', $column, 2);

				$related[$prefix][$column] = $value;
			}
		}

		if ( ! empty($related))
		{
			foreach ($related as $object => $values)
			{
				// Load the related objects with the values in the result
				$this->_related($object)->_load_values($values);
			}
		}

		return $this;
	}

	/**
	 * Rule definitions for validation
	 *
	 * @return array
	 */
	public function rules()
	{
		return array();
	}

	/**
	 * Filters a value for a specific column
	 *
	 * @param  string $field  The column name
	 * @param  string $value  The value to filter
	 * @return string
	 */
	protected function run_filter($field, $value)
	{
		$filters = $this->filters();

		// Get the filters for this column
		$wildcards = empty($filters[TRUE]) ? array() : $filters[TRUE];

		// Merge in the wildcards
		$filters = empty($filters[$field]) ? $wildcards : array_merge($wildcards, $filters[$field]);

		// Bind the field name and model so they can be used in the filter method
		$_bound = array
		(
			':field' => $field,
			':model' => $this,
		);

		foreach ($filters as $array)
		{
			// Value needs to be bound inside the loop so we are always using the
			// version that was modified by the filters that already ran
			$_bound[':value'] = $value;

			// Filters are defined as array($filter, $params)
			$filter = $array[0];
			$params = Arr::get($array, 1, array(':value'));

			foreach ($params as $key => $param)
			{
				if (is_string($param) AND array_key_exists($param, $_bound))
				{
					// Replace with bound value
					$params[$key] = $_bound[$param];
				}
			}

			if (is_array($filter) OR ! is_string($filter))
			{
				// This is either a callback as an array or a lambda
				$value = call_user_func_array($filter, $params);
			}
			elseif (strpos($filter, '::') === FALSE)
			{
				// Use a function call
				$function = new ReflectionFunction($filter);

				// Call $function($this[$field], $param, ...) with Reflection
				$value = $function->invokeArgs($params);
			}
			else
			{
				// Split the class and method of the rule
				list($class, $method) = explode('::', $filter, 2);

				// Use a static method call
				$method = new ReflectionMethod($class, $method);

				// Call $Class::$method($this[$field], $param, ...) with Reflection
				$value = $method->invokeArgs(NULL, $params);
			}
		}

		return $value;
	}

	/**
	 * Filter definitions for validation
	 *
	 * @return array
	 */
	public function filters()
	{
		return array();
	}

	/**
	 * Label definitions for validation
	 *
	 * @return array
	 */
	public function labels()
	{
		return array();
	}

	/**
	 * Validates the current model's data
	 *
	 * @param  Validation $extra_validation Validation object
	 * @return ORM
	 */
	public function check(Validation $extra_validation = NULL)
	{
		// Determine if any external validation failed
		$extra_errors = ($extra_validation AND ! $extra_validation->check());

		// Always build a new validation object
		$this->_validation();

		$array = $this->_validation;

		if (($this->_valid = $array->check()) === FALSE OR $extra_errors)
		{
			$exception = new ORM_Validation_Exception($this->_object_name, $array);

			if ($extra_errors)
			{
				// Merge any possible errors from the external object
				$exception->add_object('_external', $extra_validation);
			}
			throw $exception;
		}

		return $this;
	}

	/**
	 * Insert a new object to the database
	 * @param  Validation $validation Validation object
	 * @return ORM
	 */
	public function create(Validation $validation = NULL)
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Cannot create :model model because it is already loaded.', array(':model' => $this->_object_name));

		// Require model validation before saving
		if ( ! $this->_valid)
		{
			$this->check($validation);
		}

		$data = array();
		foreach ($this->_changed as $column)
		{
			// Generate list of column => values
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_created_column))
		{
			// Fill the created column
			$column = $this->_created_column['column'];
			$format = $this->_created_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		$result = DB::insert($this->_table_name)
			->columns(array_keys($data))
			->values(array_values($data))
			->execute($this->_db);

		if ( ! array_key_exists($this->_primary_key, $data))
		{
			// Load the insert id as the primary key if it was left out
			$this->_object[$this->_primary_key] = $this->_primary_key_value = $result[0];
		}

		// Object is now loaded and saved
		$this->_loaded = $this->_saved = TRUE;

		// All changes have been saved
		$this->_changed = array();

		return $this;
	}

	/**
	 * Updates a single record or multiple records
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @return ORM
	 */
	public function update(Validation $validation = NULL)
	{
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot update :model model because it is not loaded.', array(':model' => $this->_object_name));

		if (empty($this->_changed))
		{
			// Nothing to update
			return $this;
		}

		// Require model validation before saving
		if ( ! $this->_valid)
		{
			$this->check($validation);
		}

		$data = array();
		foreach ($this->_changed as $column)
		{
			// Compile changed data
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_updated_column))
		{
			// Fill the updated column
			$column = $this->_updated_column['column'];
			$format = $this->_updated_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		// Use primary key value
		$id = $this->pk();

		// Update a single record
		DB::update($this->_table_name)
			->set($data)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		if (isset($data[$this->_primary_key]))
		{
			// Primary key was changed, reflect it
			$this->_primary_key_value = $data[$this->_primary_key];
		}

		// Object has been saved
		$this->_saved = TRUE;

		// All changes have been saved
		$this->_changed = array();

		return $this;
	}

	/**
	 * Updates or Creates the record depending on loaded()
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @return ORM
	 */
	public function save(Validation $validation = NULL)
	{
		return $this->loaded() ? $this->update($validation) : $this->create($validation);
	}

	/**
	 * Deletes a single record or multiple records, ignoring relationships.
	 *
	 * @chainable
	 * @return ORM
	 */
	public function delete()
	{
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot delete :model model because it is not loaded.', array(':model' => $this->_object_name));

		// Use primary key value
		$id = $this->pk();

		// Delete the object
		DB::delete($this->_table_name)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		return $this->clear();
	}

	/**
	 * Tests if this object has a relationship to a different model,
	 * or an array of different models.
	 *
	 *     // Check if $model has the login role
	 *     $model->has('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Check for the login role if you know the roles.id is 5
	 *     $model->has('roles', 5);
	 *     // Check for all of the following roles
	 *     $model->has('roles', array(1, 2, 3, 4));

	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return Database_Result
	 */
	public function has($alias, $far_keys)
	{
		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		// We need an array to simplify the logic
		$far_keys = (array) $far_keys;

		// Nothing to check if the model isn't loaded or we don't have any far_keys
		if ( ! $far_keys OR ! $this->_loaded)
			return FALSE;

		$count = (int) DB::select(array('COUNT("*")', 'records_found'))
			->from($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
			->where($this->_has_many[$alias]['far_key'], 'IN', $far_keys)
			->execute($this->_db)->get('records_found');

		// Rows found need to match the rows searched
		return $count === count($far_keys);
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 *     // Add the login role using a model instance
	 *     $model->add('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Add the login role if you know the roles.id is 5
	 *     $model->add('roles', 5);
	 *     // Add multiple roles (for example, from checkboxes on a form)
	 *     $model->add('roles', array(1, 2, 3, 4));
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return ORM
	 */
	public function add($alias, $far_keys)
	{
		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$columns = array($this->_has_many[$alias]['foreign_key'], $this->_has_many[$alias]['far_key']);
		$foreign_key = $this->pk();

		$query = DB::insert($this->_has_many[$alias]['through'], $columns);

		foreach ( (array) $far_keys as $key)
		{
			$query->values(array($foreign_key, $key));
		}

		$query->execute($this->_db);

		return $this;
	}

	/**
	 * Removes a relationship between this model and another.
	 *
	 *     // Remove a role using a model instance
	 *     $model->remove('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Remove the role knowing the primary key
	 *     $model->remove('roles', 5);
	 *     // Remove multiple roles (for example, from checkboxes on a form)
	 *     $model->remove('roles', array(1, 2, 3, 4));
	 *     // Remove all related roles
	 *     $model->remove('roles');
	 *
	 * @param  string $alias    Alias of the has_many "through" relationship
	 * @param  mixed  $far_keys Related model, primary key, or an array of primary keys
	 * @return ORM
	 */
	public function remove($alias, $far_keys = NULL)
	{
		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$query = DB::delete($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

		if ($far_keys !== NULL)
		{
			// Remove all the relationships in the array
			$query->where($this->_has_many[$alias]['far_key'], 'IN', (array) $far_keys);
		}

		$query->execute($this->_db);

		return $this;
	}

	/**
	 * Count the number of records in the table.
	 *
	 * @return integer
	 */
	public function count_all()
	{
		$selects = array();

		foreach ($this->_db_pending as $key => $method)
		{
			if ($method['name'] == 'select')
			{
				// Ignore any selected columns for now
				$selects[] = $method;
				unset($this->_db_pending[$key]);
			}
		}

		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind relationship
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		$records = $this->_db_builder->from($this->_table_name)
			->select(array('COUNT("*")', 'records_found'))
			->execute($this->_db)
			->get('records_found');

		// Add back in selected columns
		$this->_db_pending += $selects;

		$this->reset();

		// Return the total number of records in a table
		return $records;
	}

	/**
	 * Proxy method to Database list_columns.
	 *
	 * @return array
	 */
	public function list_columns()
	{
		// Proxy to database
		return $this->_db->list_columns($this->_table_name);
	}

	/**
	 * Returns an ORM model for the given one-one related alias
	 *
	 * @param  string $alias Alias name
	 * @return ORM
	 */
	protected function _related($alias)
	{
		if (isset($this->_related[$alias]))
		{
			return $this->_related[$alias];
		}
		elseif (isset($this->_has_one[$alias]))
		{
			return $this->_related[$alias] = ORM::factory($this->_has_one[$alias]['model']);
		}
		elseif (isset($this->_belongs_to[$alias]))
		{
			return $this->_related[$alias] = ORM::factory($this->_belongs_to[$alias]['model']);
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Returns the value of the primary key
	 *
	 * @return mixed Primary key
	 */
	public function pk()
	{
		return $this->_primary_key_value;
	}

	/**
	 * Returns last executed query
	 *
	 * @return string
	 */
	public function last_query()
	{
		return $this->_db->last_query;
	}

	/**
	 * Clears query builder.  Passing FALSE is useful to keep the existing
	 * query conditions for another query.
	 *
	 * @param bool $next Pass FALSE to avoid resetting on the next call
	 * @return ORM
	 */
	public function reset($next = TRUE)
	{
		if ($next AND $this->_db_reset)
		{
			$this->_db_pending   = array();
			$this->_db_applied   = array();
			$this->_db_builder   = NULL;
			$this->_with_applied = array();
		}

		// Reset on the next call?
		$this->_db_reset = $next;

		return $this;
	}
} // End ORM
