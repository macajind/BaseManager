<?php

namespace CRUDManager;

use CRUDManager;
use CRUDManager\Dummies\BaseManagerDummy;
use CRUDManager\Dummies\TestManager;
use CRUDManager\Dummies\WrongManager;
use Mockery;
use Nette\Database\Context;
use Nette\Database\Table\Selection;
use Tester\Assert;

require_once 'bootstrap.php';

/**
 * Class CRUDManagerTest.
 * @package CRUDManager
 * @author  Jindřich Máca
 * @testCase
 */
final class CRUDManagerTest extends MockTestCase
{
	/** @var TestManager */
	private $testManager = null;

	/** @var Context */
	private $database;

	/** @var Mockery\MockInterface */
	private $selection;

	/** @inheritdoc */
	protected function setUp()
	{
		parent::setUp();
		$this->selection = Mockery::mock('\Nette\Database\Table\Selection');
		$this->database = Mockery::mock('\Nette\Database\Context');
		$this->database->shouldReceive('table')->andReturnUsing(function ($table) {
			return $table !== 'test'
				? $this->selection->expects('get')->andReturn(false)->getMock()
				: $this->selection;
		});
		$this->database->shouldReceive('getStructure->getTables')->andReturn([['name' => 'test']]);
		$this->testManager = new TestManager($this->database);
	}

	private function getTableName(string $className)
	{
		$matches = [];
		if (preg_match('/(?P<name>\w+)Manager$/', $className, $matches) === 1)
			return strtolower($matches['name']);
		else return false;
	}

	public function testCreatingClassWithNonExistingTable(): void
	{
		$tableName = $this->getTableName(WrongManager::class);
		Assert::exception(
			function () { new WrongManager($this->database); },
			'OutOfBoundsException',
			"Table with name '$tableName' does not exist!"
		);
	}

	public function testCreatingClassWithWrongNamePattern(): void
	{
		Assert::exception(
			function () { new BaseManagerDummy($this->database); },
			'\UnexpectedValueException',
			"Class name '" . BaseManagerDummy::class . "' does not match the pattern '" . CRUDManager::TABLE_NAME_PATTERN . "' for its name extraction!"
		);
	}

	public function testGetTableName(): void
	{
		Assert::same($this->getTableName(TestManager::class), $this->testManager->getTableName());
	}

	private function mockSelectionGet(int $id, string $name = null): void
	{
		if (is_null($name)) $this->selection->expects('get')->with($id)->andReturn(false);
		else $this->selection->expects('get')->with($id)->andReturn(['name' => $name]);
	}

	protected function getTestRecords(): array
	{
		return [
			[1, 'test'],
			[2, 'test2'],
			[3, 'test3'],
		];
	}

	/** @dataProvider getTestRecords */
	public function testGeneralGetByIdWhenIdExists(int $id, string $name): void
	{
		$this->mockSelectionGet($id, $name);
		$row = $this->testManager->getById($id);
		Assert::same($name, $row['name']);
	}

	public function testGeneralGetByIdWhenIdNotExists(): void
	{
		$this->mockSelectionGet(0);
		Assert::false($this->testManager->getById(0));
	}

	/** @dataProvider getTestRecords */
	public function testSpecificGetByIdWhenIdExists(int $id, string $name): void
	{
		$this->mockSelectionGet($id, $name);
		$row = $this->testManager->getTestById($id);
		Assert::same($name, $row['name']);
	}

	public function testSpecificGetByIdWhenIdNotExists(): void
	{
		$this->mockSelectionGet(0);
		Assert::false($this->testManager->getTestById(0));
	}

	private function controlValuesInSelection(Selection $selection): void
	{
		$testRecords = $this->getTestRecords();
		$count = count($testRecords);
		$this->selection->expects('count')->andReturn($count);
		Assert::same($count, $selection->count());
		for ($i = 0; $i < $count; $i++) {
			$this->selection->expects('fetch')->andReturn(['id' => $testRecords[$i][0], 'name' => $testRecords[$i][1]]);
			$row = $selection->fetch();
			Assert::same($testRecords[$i][0], $row['id']);
			Assert::same($testRecords[$i][1], $row['name']);
		}
	}

	public function testGeneralGetAll(): void
	{
		$this->controlValuesInSelection($this->testManager->getAll());
	}

	public function testSpecificGetAll(): void
	{
		$this->controlValuesInSelection($this->testManager->getAllTests());
	}

	private function validateData(int $id, string $name): void
	{
		$row = $this->database->table($this->getTableName(TestManager::class))->get($id);
		Assert::same($name, $row['name']);
	}

	private function mockSelectionInsert(int $id, string $name): void
	{
		$this->selection->expects('insert')->with(['id' => $id, 'name' => $name]);
		$this->mockSelectionGet($id, $name);
	}

	protected function getInsertionRecords(): array
	{
		return [
			[4, 'test4'],
			[5, 'test5']
		];
	}

	/** @dataProvider getInsertionRecords */
	public function testGeneralAdd(int $id, string $name): void
	{
		$this->mockSelectionInsert($id, $name);
		$this->testManager->add(['id' => $id, 'name' => $name]);
		$this->validateData($id, $name);

	}

	/** @dataProvider getInsertionRecords */
	public function testSpecificAdd(int $id, string $name): void
	{
		$this->mockSelectionInsert($id, $name);
		$this->testManager->addTest(['id' => $id, 'name' => $name]);
		$this->validateData($id, $name);
	}

	private function mockSelectionWherePrimary(int $id): void
	{
		$this->selection->expects('wherePrimary')->with($id)->andReturn($this->selection);
	}

	private function mockSelectionUpdate(int $id, string $name): void
	{
		$this->mockSelectionWherePrimary($id);
		$this->selection->expects('update')->with(['name' => $name])->andReturn(1);
		$this->mockSelectionGet($id, $name);
	}

	protected function getUpdatingRecords(): array
	{
		return [
			[1, 'test4'],
			[2, 'test5']
		];
	}

	/** @dataProvider getUpdatingRecords */
	public function testGeneralUpdate(int $id, string $name): void
	{
		$this->mockSelectionUpdate($id, $name);
		$this->testManager->update($id, ['name' => $name]);
		$this->validateData($id, $name);
	}

	/** @dataProvider getUpdatingRecords */
	public function testSpecificUpdate(int $id, string $name): void
	{
		$this->mockSelectionUpdate($id, $name);
		$this->testManager->updateTest($id, ['name' => $name]);
		$this->validateData($id, $name);
	}

	private function mockSelectionDelete(int $id): void
	{
		$this->mockSelectionWherePrimary($id);
		$this->selection->expects('delete')->andReturn(1);
		$this->mockSelectionGet($id);
	}

	protected function getRemovingRecords(): array
	{
		return [
			[1],
			[2]
		];
	}

	/** @dataProvider getRemovingRecords */
	public function testGeneralRemove(int $id): void
	{
		$this->mockSelectionDelete($id);
		$this->testManager->remove($id);
		Assert::false($this->database->table($this->getTableName(TestManager::class))->get($id));
	}

	/** @dataProvider getRemovingRecords */
	public function testSpecifiedRemove(int $id): void
	{
		$this->mockSelectionDelete($id);
		$this->testManager->removeTest($id);
		Assert::false($this->database->table($this->getTableName(TestManager::class))->get($id));
	}
}

$testCase = new CRUDManagerTest();
$testCase->run();
