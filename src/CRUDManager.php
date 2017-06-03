<?php

use CRUDManager\Inflect;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Object;
use Nette\UnexpectedValueException;
use Nette\Utils\AssertionException;
use Nette\Utils\Callback;

// TODO: Finish comments in whole class.

/**
 * Class CRUDManager
 * @author Jindřich Máca
 */
abstract class CRUDManager extends Object
{
	/**  */
	const TABLE_NAME_PATTERN = "/(?P<name>\w+)Manager$/";

	/** @var Context */
	private $database;

	/** @var string */
	private $tableName;

	/** @var null|array */
	public static $tables = null;

	/** @var callable[] */
	private $methods = [];

	/**
	 * More effective than asking directly for every database table.
	 * @return bool
	 */
	private function tableExists()
	{
		if (is_null(self::$tables)) self::$tables = $this->database->getStructure()->getTables();
		return array_search($this->getTableName(), array_column(self::$tables, 'name')) === false ? false : true;
	}

	/**
	 * CRUDManager constructor.
	 * @param Context $database
	 * @throws UnexpectedValueException
	 * @throws OutOfBoundsException
	 */
	public function __construct(Context $database)
	{
		$this->database = $database;
		$className = $this->getReflection()->getName();
		$matches = [];
		if (preg_match(static::TABLE_NAME_PATTERN, $className, $matches) === 1) $this->setTableName($matches['name']);
		else throw new UnexpectedValueException("Class name '{$className}' does not match the pattern '" . self::TABLE_NAME_PATTERN . "' for database table recognition!");
		if (!$this->tableExists()) throw new OutOfBoundsException("Table with name '{$this->getTableName()}' does not exist!");
	}

	/**
	 *
	 * @return string
	 */
	public final function getTableName()
	{
		return $this->tableName;
	}

	/**
	 *
	 * @param string $tableName
	 */
	protected function setTableName($tableName)
	{
		$this->tableName = strtolower($tableName);
	}

	/**
	 *
	 * @return Selection
	 */
	protected final function getTable()
	{
		return $this->database->table($this->getTableName());
	}

	/**
	 *
	 * @param CRUDManager $relatedTable
	 * @param bool        $relatedFirst
	 * @param string      $delimiter
	 * @return Selection
	 * @throws AssertionException
	 */
	protected final function getTableRelation(CRUDManager $relatedTable, $relatedFirst = false, $delimiter = '_')
	{
		return $relatedFirst
			? $this->database->table($relatedTable->getTableName() . $delimiter . $this->getTableName())
			: $this->database->table($this->getTableName() . $delimiter . $relatedTable->getTableName());
	}

	/**
	 *
	 * @param mixed $id
	 * @return false|IRow
	 */
	public function getById($id)
	{
		return $this->getTable()->get($id);
	}

	/**
	 *
	 * @return Selection
	 */
	public function getAll()
	{
		return $this->getTable();
	}

	/**
	 *
	 * @param array|Selection|Traversable $record
	 * @return bool|int|IRow
	 */
	public function add($record)
	{
		return $this->getTable()->insert($record);
	}

	/**
	 *
	 * @param mixed             $id
	 * @param array|Traversable $record
	 * @return int
	 */
	public function update($id, $record)
	{
		return $this->getTable()->wherePrimary($id)->update($record);
	}

	/**
	 *
	 * @param mixed $id
	 * @return int
	 */
	public function remove($id)
	{
		return $this->getTable()->wherePrimary($id)->delete();
	}

	/**
	 *
	 * @param string   $name
	 * @param callable $method
	 */
	public final function registerMethods($name, callable $method)
	{
		$this->methods[$name] = $method;
	}

	/**
	 *
	 * @param string $name
	 * @return bool
	 */
	private function methodExists($name)
	{
		return array_key_exists($name, $this->methods);
	}

	/**
	 *
	 * @param string $name
	 * @param array  $args
	 * @return mixed
	 */
	private function callMethod($name, $args)
	{
		$method = $this->methods[$name];
		return Callback::invokeArgs($method, $args);
	}

	/** @inheritdoc */
	public function __call($name, $arguments)
	{
		$methodName = ucfirst($this->getTableName());
		switch ($name) {
			case "get{$methodName}ById":
				return $this->getById($arguments[0]);
				break;
			case 'getAll' . Inflect::pluralize($methodName):
				return $this->getAll();
				break;
			case "add{$methodName}":
				return $this->add($arguments[0]);
				break;
			case "update{$methodName}":
				return $this->update($arguments[0], $arguments[1]);
				break;
			case "remove{$methodName}":
				return $this->remove($arguments[0]);
				break;
			default:
				return $this->methodExists($name)
					? $this->callMethod($name, $arguments)
					: parent::__call($name, $arguments);
		}
	}
}
