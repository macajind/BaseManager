<?php

use CRUDManager\ClassName;
use CRUDManager\Inflect;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\MemberAccessException;
use Nette\SmartObject;

/**
 * Class CRUDManager which brings CRUD operations to other inherited model classes using Nette Database library.
 * @author Jindřich Máca
 */
abstract class CRUDManager
{
	use SmartObject { __call as private call; } // Need to call it as parent method.
	use ClassName;

	/** Naming pattern for classes to map on database tables. */
	const TABLE_NAME_PATTERN = '/(\w+)Manager$/';

	/** @var Context Nette database context. */
	protected $database;

	/** @var string Name of the database table represented by class. */
	private $tableName;

	/** @var null|array Array of all known tables or null. */
	private static $tables = null;

	/**
	 * Checks if table represented by class exists in database.
	 *
	 * Static approach for loading all database tables only once should be more effective than asking directly for
	 * every database table.
	 *
	 * @return bool Indicates if database table exists.
	 */
	private function tableExists(): bool
	{
		if (is_null(self::$tables)) self::$tables = $this->database->getStructure()->getTables();
		return array_search($this->getTableName(), array_column(self::$tables, 'name')) === false ? false : true;
	}

	/**
	 * CRUDManager constructor.
	 *
	 * @param Context $database Nette database context.
	 *
	 * @throws \UnexpectedValueException If class name does not match the pattern for database table recognition.
	 * @throws OutOfBoundsException If database table represented by class does not exists.
	 */
	public function __construct(Context $database)
	{
		$this->database = $database;
		$this->setTableName(self::getClassName(self::TABLE_NAME_PATTERN));
		if (!$this->tableExists()) throw new OutOfBoundsException("Table with name '{$this->getTableName()}' does not exist!");
	}

	/**
	 * Returns name of the database table represented by class.
	 * @return string Table name.
	 */
	public final function getTableName(): string { return $this->tableName; }

	/**
	 * Sets name of the database table represented by class.
	 * @param string $tableName New table name.
	 * @return void
	 */
	protected function setTableName(string $tableName): void { $this->tableName = strtolower($tableName); }

	/**
	 * Returns Nette filtered table representation.
	 * @return Selection Filtered table representation.
	 * @see Selection
	 */
	protected final function getTable(): Selection { return $this->database->table($this->getTableName()); }

	/**
	 * Returns Nette filtered table representation of m:n decomposition database table.
	 *
	 * @param CRUDManager $relatedTable Related table.
	 * @param bool        $relatedFirst Determinate order of the relation (default is related first).
	 * @param string      $delimiter    Delimiter in decomposition database table name.
	 *
	 * @return Selection Filtered table representation.
	 * @see Selection
	 */
	protected final function getTableRelation(CRUDManager $relatedTable, bool $relatedFirst = false, string $delimiter = '_'): Selection
	{
		return $relatedFirst
			? $this->database->table($relatedTable->getTableName() . $delimiter . $this->getTableName())
			: $this->database->table($this->getTableName() . $delimiter . $relatedTable->getTableName());
	}

	/**
	 * Returns row specified by given primary key from database table.
	 * @param mixed $id Primary key.
	 * @return false|ActiveRow Row specified by primary key or false if there is no such row.
	 * @see ActiveRow
	 */
	public function getById($id) { return $this->getAll()->get($id); }

	/**
	 * Returns Nette filtered table representation.
	 * @return Selection Filtered table representation.
	 * @see Selection
	 */
	public function getAll(): Selection { return $this->getTable(); }

	/**
	 * Inserts row/s into the database table.
	 * @param array|Selection|Traversable $record Record data to be inserted.
	 * @return bool|int|ActiveRow Returns IRow or number of affected rows for Selection or table without primary key.
	 *
	 * @see Selection
	 * @see Traversable
	 * @see ActiveRow
	 */
	public function add($record) { return $this->getTable()->insert($record); }

	/**
	 * Updates all rows in the database table specified by given primary key.
	 * @param mixed    $id     Primary key.
	 * @param iterable $record New record data to be updated.
	 * @return int Number of affected rows.
	 * @see Traversable
	 */
	public function update($id, iterable $record): int { return $this->getTable()->wherePrimary($id)->update($record); }

	/**
	 * Deletes all rows in the database table specified by given primary key.
	 * @param mixed $id Primary key.
	 * @return int Number of affected rows.
	 */
	public function remove($id): int { return $this->getTable()->wherePrimary($id)->delete(); }

	/**
	 * Call to undefined method.
	 *
	 * Allows to call all table manipulation methods also with the name of the table.
	 * For example: TestManager->addTest(...) => TestManager->add(...)
	 *
	 * @param string $name      Method's name.
	 * @param array  $arguments Method's arguments.
	 *
	 * @return mixed Return value of the method.
	 *
	 * @throws MemberAccessException If accessing of the method fails.
	 */
	public function __call(string $name, array $arguments)
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
				return $this->call($name, $arguments);
		}
	}
}
