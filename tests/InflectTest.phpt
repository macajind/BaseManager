<?php

namespace CRUDManager;

use Tester\Assert;
use Tester\TestCase;

require_once 'bootstrap.php';

// TODO: Finish comments in whole class.

/**
 * Class InflectTest
 * @package CRUDManager
 * @author  Jindřich Máca
 * @testCase
 */
class InflectTest extends TestCase
{
	protected function getWords()
	{
		return [
			['test', 'tests'],
			['mouse', 'mice']
		];
	}

	/**
	 *
	 * @param string $singular
	 * @param string $plural
	 * @dataProvider getWords
	 */
	public function testPluralize($singular, $plural)
	{
		Assert::same($plural, Inflect::pluralize($singular));
	}

	/**
	 *
	 * @param string $singular
	 * @param string $plural
	 * @dataProvider getWords
	 */
	public function testSingularize($singular, $plural)
	{
		Assert::same($singular, Inflect::singularize($plural));
	}

	protected function getExpressions()
	{
		return [
			[1, 'test', '1 test'],
			[2, 'mouse', '2 mice']
		];
	}

	/**
	 *
	 * @param int    $count
	 * @param string $word
	 * @param string $expression
	 * @dataProvider getExpressions
	 */
	public function testIfPluralize($count, $word, $expression)
	{
		Assert::same($expression, Inflect::pluralize_if($count, $word));
	}
}

$testCase = new InflectTest();
$testCase->run();
