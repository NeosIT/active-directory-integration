<?php
if (!defined('ABSPATH')) {
	die('Access denied.');
}

if (class_exists('Ut_NextADInt_Adi_Authentication_SingleSignOn_ServiceTest')) {
	return;
}

class Ut_NextADInt_Adi_Authentication_SingleSignOn_ServiceTest extends Ut_BasicTest
{
	/* @var NextADInt_Adi_Authentication_Persistence_FailedLoginRepository|PHPUnit_Framework_MockObject_MockObject $failedLoginRepository */
	private $failedLoginRepository;

	/* @var NextADInt_Multisite_Configuration_Service|PHPUnit_Framework_MockObject_MockObject $configuration */
	private $configuration;

	/* @var NextADInt_Ldap_Connection|PHPUnit_Framework_MockObject_MockObject $ldapConnection */
	private $ldapConnection;

	/* @var NextADInt_Adi_User_Manager|PHPUnit_Framework_MockObject_MockObject $userManager */
	private $userManager;

	/* @var NextADInt_Adi_Mail_Notification|PHPUnit_Framework_MockObject_MockObject $mailNotification */
	private $mailNotification;

	/* @var NextADInt_Adi_Authentication_Ui_ShowBlockedMessage|PHPUnit_Framework_MockObject_MockObject $userBlockedMessage */
	private $userBlockedMessage;

	/* @var NextADInt_Ldap_Attribute_Service|PHPUnit_Framework_MockObject_MockObject $attributeService */
	private $attributeService;

	/* @var NextADInt_Adi_Role_Manager|PHPUnit_Framework_MockObject_MockObject $roleManager */
	private $roleManager;

	/* @var NextADInt_Core_Session_Handler|PHPUnit_Framework_MockObject_MockObject $sessionHandler */
	private $sessionHandler;

	/* @var NextADInt_Core_Util_Internal_Native|PHPUnit_Framework_MockObject_MockObject $sessionHandler */
	private $native;

	/** @var NextADInt_Adi_Authentication_SingleSignOn_Validator|PHPUnit_Framework_MockObject_MockObject $ssoValidation */
	private $ssoValidation;

    /** @var NextADInt_Adi_LoginState|PHPUnit_Framework_MockObject_MockObject $loginState */
	private $loginState;

	/** @var NextADInt_Adi_User_LoginSucceededService */
	private $loginSucceededService;

	public function setUp() : void
	{
		parent::setUp();

		$this->failedLoginRepository = $this->createMock('NextADInt_Adi_Authentication_Persistence_FailedLoginRepository');
		$this->configuration = $this->createMock('NextADInt_Multisite_Configuration_Service');
		$this->ldapConnection = $this->createMock('NextADInt_Ldap_Connection');
		$this->userManager = $this->createMock('NextADInt_Adi_User_Manager');
		$this->mailNotification = $this->createMock('NextADInt_Adi_Mail_Notification');
		$this->userBlockedMessage = $this->createMock('NextADInt_Adi_Authentication_Ui_ShowBlockedMessage');
		$this->attributeService = $this->createMock('NextADInt_Ldap_Attribute_Service');
		$this->roleManager = $this->createMock('NextADInt_Adi_Role_Manager');
		$this->sessionHandler = $this->createMock('NextADInt_Core_Session_Handler');
		$this->ssoValidation = $this->createMock('NextADInt_Adi_Authentication_SingleSignOn_Validator');
		$this->loginSucceededService = $this->createMock('NextADInt_Adi_User_LoginSucceededService');
		$this->loginState = new NextADInt_Adi_LoginState();

		// mock away our internal php calls
		$this->native = $this->createMockedNative();
		NextADInt_Core_Util::native($this->native);
	}

	public function tearDown() : void
	{
		parent::tearDown();
		NextADInt_Core_Util::native(null);
	}

	/**
	 * @param null $methods
	 *
	 * @return NextADInt_Adi_Authentication_SingleSignOn_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	public function sut($methods = null)
	{
		return $this->getMockBuilder('NextADInt_Adi_Authentication_SingleSignOn_Service')
			->setConstructorArgs(
				array(
					$this->failedLoginRepository,
					$this->configuration,
					$this->ldapConnection,
					$this->userManager,
					$this->mailNotification,
					$this->userBlockedMessage,
					$this->attributeService,
					$this->ssoValidation,
                    $this->loginState,
					$this->loginSucceededService
				)
			)
			->setMethods($methods)
			->getMock();
	}

	/**
	 * @test
	 */
	public function createUpnCredentials_withCorrectCredentials_returnCredentialsWithUpn()
	{
		$expected = 'someusername@test.ad';
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('someUsername', 'somePassword');
		$ldapAttributes = new NextADInt_Ldap_Attributes(array('userprincipalname' => 'someUsername'), array('userprincipalname' => $expected));

		$sut = $this->sut();

		$this->attributeService->expects($this->once())
			->method('findLdapAttributesOfUser')
			->with($credentials, '')
			->willReturn($ldapAttributes);

		$actual = $sut->createUpnCredentials($credentials);

		$this->assertEquals($expected, $actual->getLogin());
	}

	/**
	 * @test
	 */
	public function createUpnCredentials_withWrongCredentials_throwsException()
	{
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('testUser', 'somePassword');
		$ldapAttributes = new NextADInt_Ldap_Attributes(array(), array());

		$sut = $this->sut();

		$this->attributeService->expects($this->once())
			->method('findLdapAttributesOfUser')
			->with($credentials, '')
			->willReturn($ldapAttributes);

		$this->expectExceptionThrown('NextADInt_Adi_Authentication_Exception', "User 'testuser' does not exist in Active Directory");

		$sut->createUpnCredentials($credentials);
	}

	/**
	 * @test
	 */
	public function authenticate_withoutUsername_returnFalse()
	{
		$sut = $this->sut();

		WP_Mock::wpFunction('is_user_logged_in', array(
			'times' => 1,
			'return' => false,
		));

		$actual = $sut->authenticate();

		$this->assertFalse($actual);
	}

	/**
	 * @test
	 */
	public function authenticate_userLoggedIn_returnFalse()
	{
		$sut = $this->sut();

		WP_Mock::wpFunction('is_user_logged_in', array(
			'times' => 1,
			'return' => true,
		));

		$actual = $sut->authenticate();

		$this->assertFalse($actual);
	}

	/**
	 * @test
	 */
	public function clearAuthenticationState_withGetParameter_doesClearSessionValues()
	{
		$_GET['reauth'] = 'sso';
		$sut = $this->sut(array('getSessionHandler'));
		$sessionHandler = $this->createMock('NextADInt_Core_Session_Handler');

		$sessionHandler->expects($this->exactly(2))
			->method('clearValue')
			->withConsecutive(
				array(NextADInt_Adi_Authentication_SingleSignOn_Service::FAILED_SSO_UPN),
				array(NextADInt_Adi_Authentication_SingleSignOn_Service::USER_LOGGED_OUT)
			);

		$sut->expects($this->exactly(2))
			->method('getSessionHandler')
			->willReturn($sessionHandler);

		$this->invokeMethod($sut, 'clearAuthenticationState');
	}

	/**
	 * @test
	 */
	public function clearAuthenticationState_withoutGetParameter_doesNotClearSessionValues()
	{
		$sut = $this->sut(array('getSessionHandler'));
		$sessionHandler = $this->createMock('NextADInt_Core_Session_Handler');

		$sessionHandler->expects($this->never())
			->method('clearValue')
			->withConsecutive(
				array(NextADInt_Adi_Authentication_SingleSignOn_Service::FAILED_SSO_UPN),
				array(NextADInt_Adi_Authentication_SingleSignOn_Service::USER_LOGGED_OUT)
			);

		$sut->expects($this->never())
			->method('getSessionHandler')
			->willReturn($sessionHandler);

		$this->invokeMethod($sut, 'clearAuthenticationState');
	}

	/**
	 * @test
	 */
	public function findUsername_returnsExpectedUsername()
	{
		$sut = $this->sut();
		$remoteVariable = 'REMOTE_USER';
		$expected = "admin@myad.local";
		$_SERVER[$remoteVariable] = $expected;

		$this->configuration->expects($this->once())
			->method('getOptionValue')
			->with(NextADInt_Adi_Configuration_Options::SSO_ENVIRONMENT_VARIABLE)
			->willReturn($remoteVariable);

		$actual = $this->invokeMethod($sut, 'findUsername');

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function findUsername_withDownLevelLogonName_unescapeEscapedUsername()
	{
		$sut = $this->sut();
		$remoteVariable = 'REMOTE_USER';
		$expected = 'TEST\klammer';
		$_SERVER[$remoteVariable] = addslashes($expected); // WordPress call addslashes for every entry in $_SERVEr

		$this->configuration->expects($this->once())
			->method('getOptionValue')
			->with(NextADInt_Adi_Configuration_Options::SSO_ENVIRONMENT_VARIABLE)
			->willReturn($remoteVariable);

		$actual = $this->invokeMethod($sut, 'findUsername');

		$this->assertEquals($expected, $actual);
	}

    /**
     * @test
     * @since 2.1.13
     * @see ADI-712
     */
	public function ADI_712_findUsername_executesFilters() {
	    $sut = $this->sut();

        $remoteVariable = 'REMOTE_USER';
        $expected = 'sAMAccountName@KERBEROS.REALM';
        $_SERVER[$remoteVariable] = $expected;

        $this->configuration->expects($this->once())
            ->method('getOptionValue')
            ->with(NextADInt_Adi_Configuration_Options::SSO_ENVIRONMENT_VARIABLE)
            ->willReturn($remoteVariable);

        WP_Mock::expectFilter(NEXT_AD_INT_PREFIX . 'auth_kerberos_rewrite_username', $expected);
        $actual = $this->invokeMethod($sut, 'findUsername');

        $this->assertEquals($expected, $actual);
    }

	/**
	 * @test
	 */
	public function openLdapConnection_withValidConnection_doesNotThrowException()
	{
		$profile = array();
		$connectionDetails = $this->createMock('NextADInt_Ldap_ConnectionDetails');
		$sut = $this->sut(array('createConnectionDetailsFromProfile'));

		$this->behave($sut, 'createConnectionDetailsFromProfile', $connectionDetails);

		$this->expects($this->ldapConnection, $this->once(), 'connect', $connectionDetails, false);

		$this->behave($this->ldapConnection, 'isConnected', true);

		$this->invokeMethod($sut, 'openLdapConnection', array($profile));
	}

	/**
	 * @test
	 */
	public function openLdapConnection_withoutConnection_throwsException()
	{
		$profile = array();
		$connectionDetails = $this->createMock('NextADInt_Ldap_ConnectionDetails');
		$sut = $this->sut(array('createConnectionDetailsFromProfile'));

		$this->ssoValidation->expects($this->once())
			->method('validateLdapConnection')
			->willThrowException(new NextADInt_Adi_Authentication_Exception('Cannot connect to ldap. Check the connection.'));

		$this->expectExceptionThrown('NextADInt_Adi_Authentication_Exception', 'Cannot connect to ldap. Check the connection.');

		$this->behave($sut, 'createConnectionDetailsFromProfile', $connectionDetails);

		$this->expects($this->ldapConnection, $this->once(), 'connect', $connectionDetails, false);

		$this->behave($this->ldapConnection, 'isConnected', false);

		$this->invokeMethod($sut, 'openLdapConnection', array($profile));
	}

	/**
	 * @test
	 */
	public function findBestConfigurationMatchForProfile_withoutProfile_itReturnsNull()
	{
		$sut = $this->sut(array('findSsoEnabledProfiles'));
		$suffix = '@test';

		$this->behave($sut, 'findSsoEnabledProfiles', array());

		$actual = $sut->findBestConfigurationMatchForProfile(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $suffix);

		$this->assertNull($actual);
	}

	/**
	 * @test
	 */
	public function findBestConfigurationMatchForProfile_withoutCorrespondingProfileForSuffix_itReturnsProfileWithoutSuffixSet()
	{
		$sut = $this->sut(array('findSsoEnabledProfiles'));
		$suffix = '@test';

		$profiles = array(
			array(
				NextADInt_Adi_Configuration_Options::SSO_ENABLED => true,
				NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => '@abc',
			),
			array(
				NextADInt_Adi_Configuration_Options::SSO_ENABLED => true,
				NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => '',
			),
		);

		$this->behave($sut, 'findSsoEnabledProfiles', $profiles);

		$expected = $profiles[1];
		$actual = $sut->findBestConfigurationMatchForProfile(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $suffix);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function findBestConfigurationMatchForProfile_withCorrespondingProfileForSuffix_itReturnsCorrectProfile()
	{
		$sut = $this->sut(array('findSsoEnabledProfiles'));
		$suffix = '@test';

		$profiles = array(
			array(
				NextADInt_Adi_Configuration_Options::SSO_ENABLED => true,
				NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => $suffix,
			),
			array(
				NextADInt_Adi_Configuration_Options::SSO_ENABLED => true,
				NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => '',
			),
		);

		$this->behave($sut, 'findSsoEnabledProfiles', $profiles);

		$expected = $profiles[0];
		$actual = $sut->findBestConfigurationMatchForProfile(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $suffix);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 * Check authenticateAtActiveDirectory overwrite comment
	 */
	public function authenticateAtActiveDirectory_returnsTrue()
	{
		$sut = $this->sut();

		$actual = $sut->authenticateAtActiveDirectory('test', '@test', '');

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function createConnectionDetailsFromProfile_returnsExpectedResult()
	{
		$sut = $this->sut();

		$profile = array(
			NextADInt_Adi_Configuration_Options::DOMAIN_CONTROLLERS => '127.0.0.1',
			NextADInt_Adi_Configuration_Options::PORT => '368',
			NextADInt_Adi_Configuration_Options::ENCRYPTION => 'none',
			NextADInt_Adi_Configuration_Options::NETWORK_TIMEOUT => '3',
			NextADInt_Adi_Configuration_Options::BASE_DN => 'test',
			NextADInt_Adi_Configuration_Options::SSO_USER => 'user',
			NextADInt_Adi_Configuration_Options::SSO_PASSWORD => 'password',
		);

		$expected = new NextADInt_Ldap_ConnectionDetails();
		$expected->setDomainControllers($profile[NextADInt_Adi_Configuration_Options::DOMAIN_CONTROLLERS]);
		$expected->setPort($profile[NextADInt_Adi_Configuration_Options::PORT]);
		$expected->setEncryption($profile[NextADInt_Adi_Configuration_Options::ENCRYPTION]);
		$expected->setNetworkTimeout($profile[NextADInt_Adi_Configuration_Options::NETWORK_TIMEOUT]);
		$expected->setBaseDn($profile[NextADInt_Adi_Configuration_Options::BASE_DN]);
		$expected->setUsername($profile[NextADInt_Adi_Configuration_Options::SSO_USER]);
		$expected->setPassword($profile[NextADInt_Adi_Configuration_Options::SSO_PASSWORD]);

		$actual = $this->invokeMethod($sut, 'createConnectionDetailsFromProfile', array($profile));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function normalizeSuffix_withoutSuffix_returnsExpectedResult()
	{
		$sut = $this->sut();

		$value = 'test';
		$expected = '@' . $value;
		$actual = $this->invokeMethod($sut, 'normalizeSuffix', array($value));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function normalizeSuffix_withExistingSuffix_returnsExpectedResult()
	{
		$sut = $this->sut();

		$expected = '@test';
		$actual = $this->invokeMethod($sut, 'normalizeSuffix', array($expected));

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function getProfilesWithOptionValue_returnsExpectedResult()
	{
		$sut = $this->sut();
		$suffix = '@test';

		$profiles = array(
			array(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => $suffix),
			array(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => ''),
		);

		$expected = array($profiles[0]);
		$actual = $this->invokeMethod($sut, 'getProfilesWithOptionValue', array(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $suffix, $profiles));
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function getProfilesWithoutOptionValue_returnsExpectedResult()
	{
		$sut = $this->sut();

		$profiles = array(
			array(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => '@test'),
			array(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX => ''),
		);

		$expected = array($profiles[1]);
		$actual = $this->invokeMethod($sut, 'getProfilesWithoutOptionValue', array(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $profiles));
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function findSsoEnabledProfiles_returnsProfilesWithSsoEnabled()
	{
		$sut = $this->sut();

		$config = array(
			NextADInt_Adi_Configuration_Options::SSO_ENABLED => array(
				'option_value' => false,
				'option_permission' => 3,
			),
		);

		$profiles = array(
			array(
				NextADInt_Adi_Configuration_Options::SSO_ENABLED => array(
					'option_value' => true,
					'option_permission' => 3,
				),
			),
			array(
				NextADInt_Adi_Configuration_Options::SSO_ENABLED => array(
					'option_value' => false,
					'option_permission' => 3,
				),
			),
		);

		$this->configuration->expects($this->once())
			->method('findAllProfiles')
			->willReturn($profiles);

		$actual = $this->invokeMethod($sut, 'findSsoEnabledProfiles');
		$this->assertCount(1, $actual);
	}

	/**
	 * @test
	 */
	public function findSsoEnabledProfiles_noProfilesFound_returnsEmpty()
	{
		$sut = $this->sut();

		$config = array(
			NextADInt_Adi_Configuration_Options::SSO_ENABLED => array(
				'option_value' => false,
				'option_permission' => 3,
			),
		);

		$profiles = array();

		$this->configuration->expects($this->once())
			->method('findAllProfiles')
			->willReturn($profiles);

		$actual = $this->invokeMethod($sut, 'findSsoEnabledProfiles');
		$this->assertEmpty($actual);
	}

	/**
	 * @test
	 */
	public function detectAuthenticatableSuffixes_validSuffixes_returnsList()
	{
		$suffix = 'test.ad';
		$profile = array(
			'account_suffix' => 'test.ad'
		);

		$sut = $this->sut(array('findBestConfigurationMatchForProfile'));


		$sut->expects($this->once())
			->method('findBestConfigurationMatchForProfile')
			->with(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $suffix)
			->willReturn($profile);

		$actual = $sut->detectAuthenticatableSuffixes($suffix);

		$this->assertEquals(array($suffix), $actual);
	}

	/**
	 * @test
	 */
	public function detectAuthenticatableSuffixes_validSuffixes_withoutProfile_returnsList()
	{
		$suffix = 'test.ad';

		$sut = $this->sut(array('findBestConfigurationMatchForProfile'));


		$sut->expects($this->once())
			->method('findBestConfigurationMatchForProfile')
			->with(NextADInt_Adi_Configuration_Options::ACCOUNT_SUFFIX, $suffix)
			->willReturn(null);

		$actual = $sut->detectAuthenticatableSuffixes($suffix);

		$this->assertEquals(array($suffix), $actual);
	}

	/**
	 * @test
	 */
	public function normalizeProfiles_returnsExpectedResult()
	{
		$sut = $this->sut();

		$expected = array(
			array(
				'domain_controllers' => '127.0.0.1',
				'port' => '389',
			),
		);

		$actual = $this->invokeMethod(
			$sut, 'normalizeProfiles', array(
				array(
					array(
						'domain_controllers' => array('option_value' => '127.0.0.1', 'option_permission' => 3),
						'port' => array('option_value' => '389', 'option_permission' => 3),
					),
				),
			)
		);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function logout_setsFlagForManualLogout()
	{
		$sut = $this->sut(array('getSessionHandler'));

		$this->sessionHandler->expects($this->once())
			->method('setValue')
			->with(NextADInt_Adi_Authentication_SingleSignOn_Service::USER_LOGGED_OUT, true);

		$this->behave($sut, 'getSessionHandler', $this->sessionHandler);

		$sut->logout();
	}

	/**
	 * @test
	 */
	public function register_registersNecessaryHooks()
	{
		$sut = $this->sut();

		WP_Mock::expectActionAdded('wp_logout', array($sut, 'logout'));
		WP_Mock::expectActionAdded('init', array($sut, 'authenticate'));

		$sut->register();
	}

	/**
	 * @test
	 */
	public function loginUser_doesTriggerWordPressFunctions()
	{
		$user = $this->createWpUserMock();
		$sut = $this->sut();

		WP_Mock::wpFunction(
			'home_url', array(
				'times' => 1,
				'args' => '/',
				'return' => '/',
			)
		);

		WP_Mock::expectAction('wp_login', $user->user_login, $user);

		WP_Mock::wpFunction(
			'is_ssl', array(
				'times' => 1,
				'return' => true
			)
		);

		WP_Mock::wpFunction(
			'wp_set_current_user', array(
				'times' => 1,
				'args'  => array($user->ID, $user->user_login),
			)
		);

		WP_Mock::wpFunction(
			'wp_set_auth_cookie', array(
				'times' => 1,
				'args'  => array($user->ID, true, true /* SSL */),
			)
		);

		WP_Mock::wpFunction(
			'wp_safe_redirect', array(
				'times' => 1,
				'args' => '/',
			)
		);

		$this->invokeMethod($sut, 'loginUser', array($user, false));
	}

	/**
	 * @test
	 */
	public function getSessionHandler_returnsSessionHandlerInstance()
	{
		$sut = $this->sut();

		$sessionHandler = $this->invokeMethod($sut, 'getSessionHandler');

		$this->assertInstanceOf('NextADInt_Core_Session_Handler', $sessionHandler);
	}

	/**
	 * @test
	 */
	public function authenticate_userNotAuthenticated_withValidUpn_willTriggerKerberosAuth_itReturnsTrue()
	{
		$sut              = $this->sut(array('findUsername', 'getSessionHandler', 'clearAuthenticationState', 'kerberosAuth', 'parentAuthenticate'));
		$expectedUsername = 'john.doe@test.ad';
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials($expectedUsername);

		WP_Mock::wpFunction(
			'is_user_logged_in', array(
				'times'  => 1,
				'return' => false
			)
		);

		$sut->expects($this->once())
		    ->method('findUsername')
		    ->willReturn($expectedUsername);

		$sut->expects($this->once())
		    ->method('getSessionHandler')
		    ->willReturn($this->sessionHandler);

		$sut->expects($this->once())
		    ->method('clearAuthenticationState');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateUrl');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateLogoutState');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateAuthenticationState')
							->with($credentials);

		$sut->expects($this->once())
			->method('parentAuthenticate')
			->willReturn($credentials);

		$sut->expects($this->once())
			->method('kerberosAuth')
			->with($credentials, $this->ssoValidation)
			->willReturn($credentials);

		$this->sessionHandler->expects($this->once())
			->method('clearValue')
			->with($sut::FAILED_SSO_UPN);

		$actual = $sut->authenticate(null, '', '');

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function authenticate_userNotAuthenticated_withNetbios_willTriggerNtlmAuth_itReturnsTrue()
	{
		$sut              = $this->sut(array('findUsername', 'getSessionHandler', 'clearAuthenticationState', 'ntlmAuth', 'parentAuthenticate'));
		$expectedUsername = 'test\\john.doe';
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials($expectedUsername);

		WP_Mock::wpFunction(
			'is_user_logged_in', array(
				'times'  => 1,
				'return' => false
			)
		);

		$sut->expects($this->once())
		    ->method('findUsername')
		    ->willReturn($expectedUsername);

		$sut->expects($this->once())
		    ->method('getSessionHandler')
		    ->willReturn($this->sessionHandler);

		$sut->expects($this->once())
		    ->method('clearAuthenticationState');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateUrl');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateLogoutState');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateAuthenticationState')
		                    ->with($credentials);

		$sut->expects($this->once())
		    ->method('parentAuthenticate')
		    ->willReturn($credentials);

		$sut->expects($this->once())
		    ->method('ntlmAuth')
		    ->with($credentials, $this->ssoValidation)
		    ->willReturn($credentials);

		$this->sessionHandler->expects($this->once())
		                     ->method('clearValue')
		                     ->with($sut::FAILED_SSO_UPN);

		$actual = $sut->authenticate(null, '', '');

		$this->assertTrue($actual);
	}

	/**
	 * @test
	 */
	public function authenticate_userNotAuthenticated_authenticationFails_itReturnsFalse()
	{
		$sut              = $this->sut(array('findUsername', 'getSessionHandler', 'clearAuthenticationState', 'kerberosAuth', 'parentAuthenticate'));
		$expectedUsername = 'john.doe@test.ad';
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials($expectedUsername);

		WP_Mock::wpFunction(
			'is_user_logged_in', array(
				'times'  => 1,
				'return' => false
			)
		);

		$sut->expects($this->once())
		    ->method('findUsername')
		    ->willReturn($expectedUsername);

		$sut->expects($this->once())
		    ->method('getSessionHandler')
		    ->willReturn($this->sessionHandler);

		$sut->expects($this->once())
		    ->method('clearAuthenticationState');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateUrl');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateLogoutState');

		$this->ssoValidation->expects($this->once())
		                    ->method('validateAuthenticationState')
		                    ->with($credentials);

		$sut->expects($this->once())
		    ->method('kerberosAuth')
		    ->with($credentials, $this->ssoValidation)
		    ->willReturn($credentials);

		$sut->expects($this->once())
		    ->method('parentAuthenticate')
		    ->willReturn(null);

		$this->sessionHandler->expects($this->once())
			->method('setValue')
			->with($sut::FAILED_SSO_UPN, $credentials->getUserPrincipalName());

		$actual = $sut->authenticate(null, '', '');

		$this->assertFalse($actual);
	}

	/**
	 * @test
	 */
	public function authenticate_withNoUsername_returnFalse()
	{
		$sut = $this->sut(array('findUsername'));

		$sut->expects($this->once())
			->method('findUsername')
			->willReturn('');

		$actual = $this->invokeMethod($sut, 'authenticate', array(null));

		$this->assertFalse($actual);
	}

	/**
	 * @test
	 */
	public function authenticate_withExceptionDuringLogout_itReturnFalse()
	{
		$username = 'username@company.local';

		WP_Mock::wpFunction('is_user_logged_in', array(
			'times' => 1,
			'return' => false,
		));

		$sut = $this->sut(
			array('findUsername', 'openLdapConnection', 'getSessionHandler', 'findCorrespondingConfiguration',
				'loginUser', 'requiresActiveDirectoryAuthentication', 'detectAuthenticatableSuffixes',
				'tryAuthenticatableSuffixes')
		);

		$sut->expects($this->once())
			->method('findUsername')
			->willReturn($username);

		$sut->expects($this->once())
			->method('getSessionHandler')
			->willReturn($this->sessionHandler);

		$this->ssoValidation->expects($this->once())
			->method('validateUrl')
			->willThrowException(new NextADInt_Adi_Authentication_LogoutException("error"));

		$this->sessionHandler->expects($this->never())
			->method('setValue')
			->with('failedSsoUpn', $username);

		$actual = $this->invokeMethod($sut, 'authenticate', array(null, '', ''));

		$this->assertFalse($actual);
	}

	/**
	 * @test
	 */
	public function authenticate_withExceptionDuringAuthentication_itReturnFalse()
	{
		$username = 'username@company.local';

		WP_Mock::wpFunction('is_user_logged_in', array(
			'times' => 1,
			'return' => false,
		));

		$sut = $this->sut(
			array('findUsername', 'openLdapConnection', 'getSessionHandler', 'findCorrespondingConfiguration',
				'loginUser', 'requiresActiveDirectoryAuthentication', 'detectAuthenticatableSuffixes',
				'tryAuthenticatableSuffixes')
		);

		$sut->expects($this->once())
			->method('findUsername')
			->willReturn($username);

		$sut->expects($this->once())
			->method('getSessionHandler')
			->willReturn($this->sessionHandler);

		$this->ssoValidation->expects($this->once())
			->method('validateAuthenticationState')
			->willThrowException(new NextADInt_Adi_Authentication_Exception("error"));

		$this->sessionHandler->expects($this->once())
			->method('setValue')
			->with('failedSsoUpn', $username);

		$actual = $this->invokeMethod($sut, 'authenticate', array(null, '', ''));

		$this->assertFalse($actual);
	}

	/**
	 * @test
	 * @issue ADI-418
	 */
	public function ADI_418_loginUser_itUsesEnvironmentVar_REDIRECT_URL_asDefault()
	{
		$user = $this->createWpUserMock();
		$sut = $this->sut();

		$_SERVER['REQUEST_URI'] = '/my-redirect-url';
		$user = $this->createWpUserMock();
		$sut = $this->sut();

		WP_Mock::expectAction('wp_login', $user->user_login, $user);

		WP_Mock::wpFunction(
			'wp_set_current_user', array(
				'times' => 1,
				'args'  => array($user->ID, $user->user_login)
			)
		);

		WP_Mock::wpFunction(
			'is_ssl', array(
				'times' => 1,
				'return' => true
			)
		);

		WP_Mock::wpFunction(
			'wp_set_auth_cookie', array(
				'times' => 1,
				'args'  => array($user->ID, true, true)
			)
		);

		WP_Mock::wpFunction(
			'wp_safe_redirect', array(
				'times' => 1,
				'args'  => $_SERVER['REQUEST_URI'],
			)
		);

		$this->invokeMethod($sut, 'loginUser', array($user, false));
	}

	/**
	 * @test
	 * @issue ADI-418
	 */
	public function ADI_418_loginUser_itUsesWordPressVar_redirect_to_over_REDIRECT_URL()
	{
		$user = $this->createWpUserMock();
		$sut = $this->sut();

		$_SERVER['REDIRECT_URL'] = '/wrong-url';
		$_REQUEST['redirect_to'] = '/expected-url';
		$user = $this->createWpUserMock();
		$sut = $this->sut();

		WP_Mock::expectAction('wp_login', $user->user_login, $user);

		WP_Mock::wpFunction(
			'wp_set_current_user', array(
				'times' => 1,
				'args'  => array($user->ID, $user->user_login)
			)
		);

		WP_Mock::wpFunction(
			'is_ssl', array(
				'times' => 1,
				'return' => true
			)
		);

		WP_Mock::wpFunction(
			'wp_set_auth_cookie', array(
				'times' => 1,
				'args'  => array($user->ID, true, true)
			)
		);

		WP_Mock::wpFunction(
			'wp_safe_redirect', array(
				'times' => 1,
				'args' =>  $_REQUEST['redirect_to'],
			)
		);

		$this->invokeMethod($sut, 'loginUser', array($user, false));
	}

	/**
	 * @test
	 */
	public function kerberosAuth_withCorrectCredentials_returnsValid()
	{
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('someUsername@test.ad', 'somePassword');
		$validator = new NextADInt_Adi_Authentication_SingleSignOn_Validator();
		$profile = array();

		$sut = $this->sut(
			array('findBestConfigurationMatchForProfile', 'openLdapConnection', 'createUpnCredentials')
		);

		$sut->expects($this->once())
			->method('findBestConfigurationMatchForProfile')
			->willReturn($profile);

		$sut->expects($this->never())
			->method('createUpnCredentials');

		$actual = $this->invokeMethod($sut, 'kerberosAuth', array($credentials, $validator));

		$this->assertEquals($credentials, $actual);

	}

	/**
	 * @test
	 */
	public function kerberosAuth_withCorrectCredentials_withoutUpnSuffix_returnsValid()
	{
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('someUsername', 'somePassword');
		$credentials_withUpn = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('someUsername', 'somePassword');
		$credentials_withUpn->setUpnSuffix('@test.ad');
		$validator = new NextADInt_Adi_Authentication_SingleSignOn_Validator();
		$profile = array();

		$sut = $this->sut(
			array('findBestConfigurationMatchForProfile', 'openLdapConnection', 'createUpnCredentials')
		);

		$sut->expects($this->once())
			->method('findBestConfigurationMatchForProfile')
			->willReturn($profile);

		$sut->expects($this->once())
			->method('createUpnCredentials')
			->willReturn($credentials_withUpn);

		$actual = $this->invokeMethod($sut, 'kerberosAuth', array($credentials, $validator));

		$this->assertEquals($credentials_withUpn, $actual);

	}

	/**
	 * @test
	 */
	public function ntlmAuth_withCorrectCredentials_returnsValid()
	{
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('TEST\someUsername', 'somePassword');
		$credentials_withUpn = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('someUsername', 'somePassword');
		$credentials_withUpn->setUpnSuffix('@test.ad');
		$validator = new NextADInt_Adi_Authentication_SingleSignOn_Validator();
		$profile = array();

		$sut = $this->sut(
			array('findBestConfigurationMatchForProfile', 'openLdapConnection', 'createUpnCredentials')
		);

		$sut->expects($this->once())
			->method('findBestConfigurationMatchForProfile')
			->willReturn($profile);

		$sut->expects($this->once())
			->method('createUpnCredentials')
			->willReturn($credentials_withUpn);

		$actual = $this->invokeMethod($sut, 'ntlmAuth', array($credentials, $validator));

		$this->assertEquals($credentials_withUpn, $actual);

	}

	/**
	 * @test
	 */
	public function ntlmAuth_withCorrectCredentials_noProfileFount_throwsException()
	{
		$netBIOSname = 'TEST';
		$credentials = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials($netBIOSname . '\someUsername', 'somePassword');
		$credentials_withUpn = NextADInt_Adi_Authentication_PrincipalResolver::createCredentials('someUsername', 'somePassword');
		$credentials_withUpn->setUpnSuffix('@test.ad');
		$validator = new NextADInt_Adi_Authentication_SingleSignOn_Validator();

		$sut = $this->sut(
			array('findBestConfigurationMatchForProfile', 'openLdapConnection', 'createUpnCredentials')
		);

		$sut->expects($this->once())
			->method('findBestConfigurationMatchForProfile')
			->with(NextADInt_Adi_Configuration_Options::NETBIOS_NAME, $netBIOSname)
			->willReturn(null);

		$this->expectExceptionThrown('NextADInt_Adi_Authentication_Exception', "Unable to find matching NADI profile for NETBIOS name '" . $credentials->getNetbiosName() . "'. Is NADI connected to a valid Active Directory domain?");

		$this->invokeMethod($sut, 'ntlmAuth', array($credentials, $validator));
	}
}