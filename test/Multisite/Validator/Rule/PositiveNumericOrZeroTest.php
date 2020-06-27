<?php
if (!defined('ABSPATH')) {
	die('Access denied.');
}

if (class_exists('Ut_NextADInt_Multisite_Validator_Rule_PositiveNumericOrZeroTest')) {
	return;
}

/**
 * @author  Tobias Hellmann <the@neos-it.de>
 * @author  Sebastian Weinert <swe@neos-it.de>
 * @author  Danny Meißner <dme@neos-it.de>
 *
 * @access
 */
class Ut_NextADInt_Multisite_Validator_Rule_PositiveNumericOrZeroTest extends Ut_BasicTest
{
	const VALIDATION_MESSAGE = 'Validation failed!';


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
	 * @param $msg string
	 *
	 * @return NextADInt_Multisite_Validator_Rule_PositiveNumericOrZero|PHPUnit_Framework_MockObject_MockObject
	 */
	public function sut($methods = null)
	{
		return $this->getMockBuilder('NextADInt_Multisite_Validator_Rule_PositiveNumericOrZero')
			->setConstructorArgs(
				array(
					self::VALIDATION_MESSAGE
				)
			)
			->setMethods($methods)
			->getMock();
	}

	/**
	 * @test
	 */
	public function validate_withPositiveNumeric_returnTrue()
	{
		$sut = $this->sut(null);

		$actual = $sut->validate(
			2,
			null
		);

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function validate_withZero_returnTrue()
	{
		$sut = $this->sut(null);

		$actual = $sut->validate(
			0,
			null
		);

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function validate_withNegativeNumeric_returnString()
	{
		$sut = $this->sut(null);

		$actual = $sut->validate(
			-123456789,
			null
		);

		$this->assertEquals(array(NextADInt_Core_Message_Type::ERROR => self::VALIDATION_MESSAGE), $actual);
	}
}