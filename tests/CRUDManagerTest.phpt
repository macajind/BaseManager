<?php

namespace CRUDManager;

use CRUDManager;
use CRUDManager\Dummies\BaseManagerDummy;
use CRUDManager\Dummies\TestManager;
use CRUDManager\Dummies\WrongManager;
use Mockista\Mock;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Tester\Assert;

require_once 'bootstrap.php';

/**
 * Class CRUDManagerTest.
 * @package CRUDManager
 * @author  Jindřich Máca
 * @testCase
 */
class CRUDManagerTest extends MockTestCase
{
	/** @var TestManager */
	private $testManager = null;

	/** @var Context */
	private $database;

	/** @var Mock */
	private $selection;

	/** @inheritdoc */
	protected function setUp()
	{
		parent::setUp();
		$this->selection = $this->mockista->create('Nette\Database\Table\Selection');
		$builder = $this->mockista->createBuilder('Nette\Database\Context', [
			'table' => function ($table) {
				return $table !== 'test'
					? $this->selection->expects('get')->andReturn(false)
					: $this->selection;
			},
			'getStructure' => $this->mockista->create('Nette\Database\IStructure', [
				'getTables' => [
					['name' => 'test']
				]
			])
		]);
		$this->database = $builder->getMock();
		$this->testManager = new TestManager($this->database);
	}

	private function getTableName($className)
	{
		$matches = [];
		if (preg_match('/(?P<name>\w+)Manager$/', $className, $matches) === 1)
			return strtolower($matches['name']);
		else return false;
	}

	public function testCreatingClassWithNonExistingTable()
	{
		$tableName = $this->getTableName(WrongManager::class);
		Assert::exception(function () {
			new WrongManager($this->database);
		}, 'OutOfBoundsException', "Table with name '$tableName' does not exist!");
	}

	public function testCreatingClassWithWrongNamePattern()
	{
		Assert::exception(function () {
			new BaseManagerDummy($this->database);
		}, 'Nette\UnexpectedValueException',
			"Class name '" . BaseManagerDummy::class . "' does not match the pattern '" . CRUDManager::TABLE_NAME_PATTERN . "' for database table recognition!"
		);
	}

	public function testGetTableName()
	{
		Assert::same($this->getTableName(TestManager::class), $this->testManager->getTableName());
	}

	private function mockSelectionGet($id, $name = null)
	{
		if (is_null($name)) $this->selection->expects('get')->with($id)->andReturn(false);
		else $this->selection->expects('get')->with($id)->andReturn(['name' => $name]);
	}

	protected function getTestRecords()
	{
		return [
			[1, 'test'],
			[2, 'test2'],
			[3, 'test3'],
		];
	}

	/** @dataProvider getTestRecords */
	public function testGeneralGetByIdWhenIdExists($id, $name)
	{
		$this->mockSelectionGet($id, $name);
		$row = $this->testManager->getById($id);
		Assert::same($name, $row['name']);
	}

	public function testGeneralGetByIdWhenIdNotExists()
	{
		$empty = '';
		$this->mockSelectionGet($empty);
		Assert::false($this->testManager->getById($empty));
	}

	/** @dataProvider getTestRecords */
	public function testSpecificGetByIdWhenIdExists($id, $name)
	{
		$this->mockSelectionGet($id, $name);
		$row = $this->testManager->getTestById($id);
		Assert::same($name, $row['name']);
	}

	public function testSpecificGetByIdWhenIdNotExists()
	{
		$empty = '';
		$this->mockSelectionGet($empty);
		Assert::false($this->testManager->getTestById($empty));
	}

	private function controlValuesInSelection(Selection $selection)
	{
		$testRecords = $this->getTestRecords();
		$count = count($testRecords);
		$this->selection->expects('count')->andReturn($count);
		Assert::same($count, $selection->count());
		for ($i = 0; $i < $selection->count(); $i++) {
			$this->selection->expects('fetch')->andReturn(['id' => $testRecords[$i][0], 'name' => $testRecords[$i][1]]);
			$row = $selection->fetch();
			Assert::same($testRecords[$i][0], $row['id']);
			Assert::same($testRecords[$i][1], $row['name']);
		}
	}

	public function testGeneralGetAll()
	{
		$this->controlValuesInSelection($this->testManager->getAll());
	}

	public function testSpecificGetAll()
	{
		$this->controlValuesInSelection($this->testManager->getAllTests());
	}

	private function validateData($id, $name)
	{
		$row = $this->database->table($this->getTableName(TestManager::class))->get($id);
		Assert::same($name, $row['name']);
	}

	private function mockSelectionInsert($id, $name)
	{
		$this->selection->expects('insert')->with(['id' => $id, 'name' => $name]);
		$this->mockSelectionGet($id, $name);
	}

	protected function getInsertionRecords()
	{
		return [
			[4, 'test4'],
			[5, 'test5']
		];
	}

	/** @dataProvider getInsertionRecords */
	public function testGeneralAdd($id, $name)
	{
		$this->mockSelectionInsert($id, $name);
		$this->testManager->add(['id' => $id, 'name' => $name]);
		$this->validateData($id, $name);

	}

	/** @dataProvider getInsertionRecords */
	public function testSpecificAdd($id, $name)
	{
		$this->mockSelectionInsert($id, $name);
		$this->testManager->addTest(['id' => $id, 'name' => $name]);
		$this->validateData($id, $name);
	}

	private function mockSelectionWherePrimary($id)
	{
		$this->selection->expects('wherePrimary')->with($id)->andReturn($this->selection);
	}

	private function mockSelectionUpdate($id, $name)
	{
		$this->mockSelectionWherePrimary($id);
		$this->selection->expects('update')->with(['name' => $name]);
		$this->mockSelectionGet($id, $name);
	}

	protected function getUpdatingRecords()
	{
		return [
			[1, 'test4'],
			[2, 'test5']
		];
	}

	/** @dataProvider getUpdatingRecords */
	public function testGeneralUpdate($id, $name)
	{
		$this->mockSelectionUpdate($id, $name);
		$this->testManager->update($id, ['name' => $name]);
		$this->validateData($id, $name);
	}

	/** @dataProvider getUpdatingRecords */
	public function testSpecificUpdate($id, $name)
	{
		$this->mockSelectionUpdate($id, $name);
		$this->testManager->updateTest($id, ['name' => $name]);
		$this->validateData($id, $name);
	}

	private function mockSelectionDelete($id)
	{
		$this->mockSelectionWherePrimary($id);
		$this->selection->expects('delete');
		$this->mockSelectionGet($id);
	}

	protected function getRemovingRecords()
	{
		return [
			[1],
			[2]
		];
	}

	/** @dataProvider getRemovingRecords */
	public function testGeneralRemove($id)
	{
		$this->mockSelectionDelete($id);
		$this->testManager->remove($id);
		Assert::false($this->database->table($this->getTableName(TestManager::class))->get($id));
	}

	/** @dataProvider getRemovingRecords */
	public function testSpecifiedRemove($id)
	{
		$this->mockSelectionDelete($id);
		$this->testManager->removeTest($id);
		Assert::false($this->database->table($this->getTableName(TestManager::class))->get($id));
	}
}

$testCase = new CRUDManagerTest();
$testCase->run();
