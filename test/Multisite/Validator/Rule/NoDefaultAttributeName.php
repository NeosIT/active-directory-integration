<?php
if (!defined('ABSPATH')) {
	die('Access denied.');
}

if (class_exists('Ut_NextADInt_Multisite_Validator_Rule_NoDefaultAttributeNameTest')) {
	return;
}

class Ut_NextADInt_Multisite_Validator_Rule_NoDefaultAttributeNameTest extends Ut_BasicTest
{
	/** @var string */
	private $invalidTestString = 'givenname:string:next_ad_int_samaccountname:first name:true:true:true';
	/** @var string */
	private $validTestString = 'givenname:string:next_ad_int_first_name:first name:true:true:true';

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
		return $this->getMockBuilder('NextADInt_Multisite_Validator_Rule_NoDefaultAttributeName')
			->setConstructorArgs(array('test'))
			->setMethods($methods)
			->getMock();
	}

	/**
	 * @test
	 */
	public function validate_withoutDefaultAttributes_returnsTrue()
	{
		$sut = $this->sut();

		$actual = $sut->validate($this->validTestString, null);

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function validate_withDefaultAttributes_returnsMessage()
	{
		$sut = $this->sut();

		$actual = $sut->validate($this->invalidTestString, null);

		$this->assertEquals(array(NextADInt_Core_Message_Type::ERROR => 'test'), $actual);
	}
}