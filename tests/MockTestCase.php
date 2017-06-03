<?php

namespace CRUDManager;

use Mockista\Registry;
use Tester\TestCase;

// TODO: Finish comments in whole class.

/**
 * Class MockTestCase
 * @package CRUDManager
 * @author  Jindřich Máca
 */
abstract class MockTestCase extends TestCase
{
	/** @var Registry */
	protected $mockista;

	/**
	 * @inheritdoc
	 */
	protected function setUp()
	{
		parent::setUp();
		$this->mockista = new Registry();
	}

	/**
	 * @inheritdoc
	 */
	protected function tearDown()
	{
		parent::tearDown();
		$this->mockista->assertExpectations();
	}
}
