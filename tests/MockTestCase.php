<?php

namespace CRUDManager;

use Mockery;
use Tester\TestCase;

/**
 * Class MockTestCase.
 * @abstract
 * @package CRUDManager
 * @author  Jindřich Máca
 */
abstract class MockTestCase extends TestCase
{
	/** @inheritdoc */
	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}
}
