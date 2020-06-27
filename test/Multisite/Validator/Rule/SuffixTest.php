<?php
if (!defined('ABSPATH')) {
	die('Access denied.');
}

if (class_exists('Ut_NextADInt_Multisite_Validator_Rule_SuffixTest')) {
	return;
}

class Ut_NextADInt_Multisite_Validator_Rule_SuffixTest extends Ut_BasicTest
{
	const VALIDATION_MESSAGE = 'Username has to contain a suffix.';

	public function setUp() : void
	{
		parent::setUp();
	}

	public function tearDown() : void
	{
		parent::tearDown();
	}

	/**
	 * @param $methods
	 *
	 * @return NextADInt_Multisite_Validator_Rule_Suffix|PHPUnit_Framework_MockObject_MockObject
	 */
	public function sut($methods = null)
	{
		return $this->getMockBuilder('NextADInt_Multisite_Validator_Rule_Suffix')
			->setConstructorArgs(
				array(
					self::VALIDATION_MESSAGE, '@',
				)
			)
			->setMethods($methods)
			->getMock();
	}

	/**
	 * @test
	 */
	public function validate_withEmptyMessage_returnTrue()
	{
		$sut = $this->sut();

		$actual = $sut->validate('', array());

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function validate_returnMessage()
	{
		$sut = $this->sut(null);

		$actual = $sut->validate('Administrator', array());

		$this->assertEquals(array(NextADInt_Core_Message_Type::ERROR => self::VALIDATION_MESSAGE), $actual);
	}

	/**
	 * @test
	 */
	public function validate_returnTrue()
	{
		$sut = $this->sut(null);

		$actual = $sut->validate('Administrator@test.ad', array());

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function getMsg()
	{
		$sut = $this->sut(null);

		$actual = $sut->getMsg();

		$this->assertEquals(array(NextADInt_Core_Message_Type::ERROR => self::VALIDATION_MESSAGE), $actual);
	}
}