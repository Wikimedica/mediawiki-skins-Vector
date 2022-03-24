<?php
/*
 * @file
 * @ingroup skins
 */

namespace MediaWiki\Skins\Vector\Tests\Integration;

use HashConfig;
use MediaWiki\User\UserOptionsManager;
use MediaWikiIntegrationTestCase;
use ReflectionMethod;
use RequestContext;
use ResourceLoaderContext;
use RuntimeException;
use Title;
use User;
use Vector\Constants;
use Vector\FeatureManagement\FeatureManager;
use Vector\Hooks;
use Vector\SkinVector22;
use Vector\SkinVectorLegacy;

/**
 * Integration tests for Vector Hooks.
 *
 * @group Vector
 * @coversDefaultClass \Vector\Hooks
 */
class VectorHooksTest extends MediaWikiIntegrationTestCase {
	private const HIDE_IF = [ '!==', 'skin', Constants::SKIN_NAME_LEGACY ];

	private const SKIN_PREFS_SECTION = 'rendering/skin/skin-prefs';

	/**
	 * @param bool $excludeMainPage
	 * @param array $excludeNamespaces
	 * @param array $include
	 * @param array $querystring
	 * @return array
	 */
	private static function makeMaxWidthConfig(
		$excludeMainPage,
		$excludeNamespaces = [],
		$include = [],
		$querystring = []
	) {
		return [
			'exclude' => [
				'mainpage' => $excludeMainPage,
				'namespaces' => $excludeNamespaces,
				'querystring' => $querystring,
			],
			'include' => $include
		];
	}

	public function provideGetVectorResourceLoaderConfig() {
		return [
			[
				[
					'VectorWebABTestEnrollment' => [],
					'VectorSearchHost' => 'en.wikipedia.org'
				],
				[
					'wgVectorSearchHost' => 'en.wikipedia.org',
					'wgVectorWebABTestEnrollment' => [],
				]
			],
			[
				[
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
								'unsampled' => [
										'samplingRate' => 1,
								],
								'control' => [
										'samplingRate' => 0
								],
								'stickyHeaderEnabled' => [
										'samplingRate' => 0
								],
								'stickyHeaderDisabled' => [
										'samplingRate' => 0
								],
						],
					],
					'VectorSearchHost' => 'en.wikipedia.org'
				],
				[
					'wgVectorSearchHost' => 'en.wikipedia.org',
					'wgVectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
								'unsampled' => [
										'samplingRate' => 1,
								],
								'control' => [
										'samplingRate' => 0
								],
								'stickyHeaderEnabled' => [
										'samplingRate' => 0
								],
								'stickyHeaderDisabled' => [
										'samplingRate' => 0
								],
						],
					],
				]
			],
		];
	}

	public function provideGetVectorResourceLoaderConfigWithExceptions() {
		return [
			# Bad experiment (no buckets)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
					],
				]
			],
			# Bad experiment (no unsampled bucket)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
							'a' => [
								'samplingRate' => 0
							],
						]
					],
				]
			],
			# Bad experiment (wrong format)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
							'unsampled' => 1,
						]
					],
				]
			],
			# Bad experiment (samplingRate defined as string)
			[
				[
					'VectorSearchHost' => 'en.wikipedia.org',
					'VectorWebABTestEnrollment' => [
						'name' => 'vector.sticky_header',
						'enabled' => true,
						'buckets' => [
							'unsampled' => [
								'samplingRate' => '1'
							],
						]
					],
				]
			],
		];
	}

	/**
	 * @covers ::shouldDisableMaxWidth
	 */
	public function providerShouldDisableMaxWidth() {
		$excludeTalkFooConfig = self::makeMaxWidthConfig(
			false,
			[ NS_TALK ],
			[ 'Talk:Foo' ],
			[]
		);

		return [
			[
				'No options, nothing disables max width',
				[],
				Title::makeTitle( NS_MAIN, 'Foo' ),
				[],
				false
			],
			[
				'Main page disables max width if exclude.mainpage set',
				self::makeMaxWidthConfig( true ),
				Title::newMainPage(),
				[],
				true
			],
			[
				'Namespaces can be excluded',
				self::makeMaxWidthConfig( false, [ NS_CATEGORY ] ),
				Title::makeTitle( NS_CATEGORY, 'Category' ),
				[],
				true
			],
			[
				'Namespaces are included if not excluded',
				self::makeMaxWidthConfig( false, [ NS_CATEGORY ] ),
				Title::makeTitle( NS_SPECIAL, 'SpecialPages' ),
				[],
				false
			],
			[
				'More than one namespace can be included',
				self::makeMaxWidthConfig( false, [ NS_CATEGORY, NS_SPECIAL ] ),
				Title::makeTitle( NS_SPECIAL, 'Specialpages' ),
				[],
				true
			],
			[
				'Can be disabled on history page',
				self::makeMaxWidthConfig(
					false,
					[
						/* no namespaces excluded */
					],
					[
						/* no includes */
					],
					[ 'action' => 'history' ]
				),
				Title::makeTitle( NS_MAIN, 'History page' ),
				[ 'action' => 'history' ],
				true
			],
			[
				'Include can override exclusions',
				self::makeMaxWidthConfig(
					false,
					[ NS_CATEGORY, NS_SPECIAL ],
					[ 'Special:Specialpages' ],
					[ 'action' => 'history' ]
				),
				Title::makeTitle( NS_SPECIAL, 'Specialpages' ),
				[ 'action' => 'history' ],
				false
			],
			[
				'Max width can be disabled on talk pages',
				$excludeTalkFooConfig,
				Title::makeTitle( NS_TALK, 'A talk page' ),
				[],
				true
			],
			[
				'includes can be used to override any page in a disabled namespace',
				$excludeTalkFooConfig,
				Title::makeTitle( NS_TALK, 'Foo' ),
				[],
				false
			],
			[
				'Excludes/includes are based on root title so should apply to subpages',
				$excludeTalkFooConfig,
				Title::makeTitle( NS_TALK, 'Foo/subpage' ),
				[],
				false
			]
		];
	}

	/**
	 * @covers ::shouldDisableMaxWidth
	 * @dataProvider providerShouldDisableMaxWidth
	 */
	public function testShouldDisableMaxWidth(
		$msg,
		$options,
		$title,
		$requestValues,
		$shouldDisableMaxWidth
	) {
		$this->assertSame(
			$shouldDisableMaxWidth,
			Hooks::shouldDisableMaxWidth( $options, $title, $requestValues ),
			$msg
		);
	}

	private function setFeatureLatestSkinVersionIsEnabled( $isEnabled ) {
		$featureManager = new FeatureManager();
		$featureManager->registerSimpleRequirement( Constants::REQUIREMENT_LATEST_SKIN_VERSION, $isEnabled );
		$featureManager->registerFeature( Constants::FEATURE_LATEST_SKIN, [
			Constants::REQUIREMENT_LATEST_SKIN_VERSION
		] );

		$this->setService( Constants::SERVICE_FEATURE_MANAGER, $featureManager );
	}

	/**
	 * @covers ::getVectorResourceLoaderConfig
	 * @dataProvider provideGetVectorResourceLoaderConfig
	 */
	public function testGetVectorResourceLoaderConfig( $configData, $expected ) {
		$config = new HashConfig( $configData );
		$vectorConfig = Hooks::getVectorResourceLoaderConfig(
			$this->createMock( ResourceLoaderContext::class ),
			$config
		);

		$this->assertSame(
			$vectorConfig,
			$expected
		);
	}

	/**
	 * @covers ::getVectorResourceLoaderConfig
	 * @dataProvider provideGetVectorResourceLoaderConfigWithExceptions
	 */
	public function testGetVectorResourceLoaderConfigWithExceptions( $configData ) {
		$config = new HashConfig( $configData );
		$this->expectException( RuntimeException::class );
		$vectorConfig = Hooks::getVectorResourceLoaderConfig(
			$this->createMock( ResourceLoaderContext::class ),
			$config
		);
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLegacy() {
		$config = new HashConfig( [
			'VectorDefaultSkinVersionForNewAccounts' => Constants::SKIN_VERSION_LEGACY,
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $user, 'skin', Constants::SKIN_NAME_LEGACY );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLatest() {
		$config = new HashConfig( [
			'VectorDefaultSkinVersionForNewAccounts' => Constants::SKIN_VERSION_LATEST,
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( User::class );
		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $this->once() )
			->method( 'setOption' )
			->with( $user, 'skin', Constants::SKIN_NAME_MODERN );
		$this->setService( 'UserOptionsManager', $userOptionsManager );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onSkinTemplateNavigation
	 */
	public function testOnSkinTemplateNavigation() {
		$this->setMwGlobals( [
			'wgVectorUseIconWatch' => true
		] );
		$skin = new SkinVector22( [ 'name' => 'vector' ] );
		$skin->getContext()->setTitle( Title::newFromText( 'Foo' ) );
		$contentNavWatch = [
			'actions' => [
				'watch' => [ 'class' => 'watch' ],
			]
		];
		$contentNavUnWatch = [
			'actions' => [
				'move' => [ 'class' => 'move' ],
				'unwatch' => [],
			],
		];

		Hooks::onSkinTemplateNavigation( $skin, $contentNavUnWatch );
		Hooks::onSkinTemplateNavigation( $skin, $contentNavWatch );

		$this->assertTrue(
			in_array( 'icon', $contentNavWatch['views']['watch']['class'] ) !== false,
			'Watch list items require an "icon" class'
		);
		$this->assertTrue(
			in_array( 'icon', $contentNavUnWatch['views']['unwatch']['class'] ) !== false,
			'Unwatch list items require an "icon" class'
		);
		$this->assertFalse(
			strpos( $contentNavUnWatch['actions']['move']['class'], 'icon' ) !== false,
			'List item other than watch or unwatch should not have an "icon" class'
		);
	}

	/**
	 * @covers ::updateUserLinksItems
	 */
	public function testUpdateUserLinksItems() {
		$vector2022Skin = new SkinVector22( [ 'name' => 'vector-2022' ] );
		$contentNav = [
			'user-page' => [
				'userpage' => [ 'class' => [], 'icon' => 'userpage' ],
			],
			'user-menu' => [
				'login' => [ 'class' => [], 'icon' => 'login' ],
			]
		];
		$contentNavWatchlist = [
			'user-menu' => [
				'watchlist' => [ 'class' => [], 'icon' => 'watchlist' ],
			]
		];
		$vectorLegacySkin = new SkinVectorLegacy( [ 'name' => 'vector' ] );
		$contentNavLegacy = [
			'user-page' => [
				'userpage' => [ 'class' => [], 'icon' => 'userpage' ],
			]
		];

		Hooks::onSkinTemplateNavigation( $vector2022Skin, $contentNav );
		$this->assertFalse( isset( $contentNav['vector-user-menu-overflow'] ),
			'watchlist data is not copied to vector-user-menu-overflow when not provided'
		);
		$this->assertFalse( isset( $contentNav['user-page']['login'] ),
			'updateUserLinksDropdownItems is called when user-page is defined'
		);
		$this->assertContains( 'mw-ui-button',
			$contentNav['user-page']['userpage']['link-class'],
			'updateUserLinksOverflowItems is called when not legacy'
		);

		Hooks::onSkinTemplateNavigation( $vector2022Skin, $contentNavWatchlist );
		$this->assertTrue( isset( $contentNavWatchlist['vector-user-menu-overflow'] ),
			'watchlist data is copied to vector-user-menu-overflow when provided'
		);

		Hooks::onSkinTemplateNavigation( $vectorLegacySkin, $contentNavLegacy );
		$this->assertFalse( isset( $contentNavLegacy['user-page'] ),
			'user-page is unset for legacy vector'
		);
	}

	/**
	 * @covers ::updateUserLinksDropdownItems
	 */
	public function testUpdateUserLinksDropdownItems() {
		$updateUserLinksDropdownItems = new ReflectionMethod(
			Hooks::class,
			'updateUserLinksDropdownItems'
		);
		$updateUserLinksDropdownItems->setAccessible( true );
		$skin = new SkinVector22( [ 'name' => 'vector-2022' ] );
		$contentAnon = [
			'user-menu' => [
				'anonuserpage' => [ 'class' => [], 'icon' => 'anonuserpage' ],
				'createaccount' => [ 'class' => [], 'icon' => 'createaccount' ],
				'login' => [ 'class' => [], 'icon' => 'login' ],
				'login-private' => [ 'class' => [], 'icon' => 'login-private' ],
			],
		];
		$updateUserLinksDropdownItems->invokeArgs( null, [ $skin, &$contentAnon ] );
		$this->assertTrue(
			count( $contentAnon['user-menu'] ) === 0,
			'Anon user page, create account, login, and login private links are removed from anon user links dropdown'
		);

		// Registered user
		$registeredUser = $this->createMock( User::class );
		$registeredUser->method( 'isRegistered' )->willReturn( true );
		$context = new RequestContext();
		$context->setUser( $registeredUser );
		$skin->setContext( $context );
		$contentRegistered = [
			'user-menu' => [
				'userpage' => [ 'class' => [], 'icon' => 'userpage' ],
				'watchlist' => [ 'class' => [], 'icon' => 'watchlist' ],
				'logout' => [ 'class' => [], 'icon' => 'logout' ],
			],
		];
		$updateUserLinksDropdownItems->invokeArgs( null, [ $skin, &$contentRegistered ] );
		$this->assertContains( 'user-links-collapsible-item', $contentRegistered['user-menu']['userpage']['class'],
			'User page link in user links dropdown requires collapsible class'
		);
		$this->assertContains( 'mw-ui-icon-before', $contentRegistered['user-menu']['userpage']['link-class'],
			'User page link in user links dropdown requires before icon classes'
		);
		$this->assertContains( 'user-links-collapsible-item', $contentRegistered['user-menu']['watchlist']['class'],
			'Watchlist link in user links dropdown requires collapsible class'
		);
		$this->assertContains( 'mw-ui-icon-before', $contentRegistered['user-menu']['watchlist']['link-class'],
			'Watchlist link in user links dropdown requires before icon classes'
		);
		$this->assertFalse( isset( $contentRegistered['user-menu']['logout'] ),
			'Logout link in user links dropdown is not set'
		);
	}

	/**
	 * @covers ::updateUserLinksOverflowItems
	 */
	public function testUpdateUserLinksOverflowItems() {
		$updateUserLinksOverflowItems = new ReflectionMethod(
			Hooks::class,
			'updateUserLinksOverflowItems'
		);
		$updateUserLinksOverflowItems->setAccessible( true );
		$content = [
			'notifications' => [
				'alert' => [ 'class' => [], 'icon' => 'alert' ],
			],
			'user-interface-preferences' => [
				'uls' => [ 'class' => [], 'icon' => 'uls' ],
			],
			'user-page' => [
				'userpage' => [ 'class' => [], 'icon' => 'userpage' ],
			],
			'vector-user-menu-overflow' => [
				'watchlist' => [ 'class' => [], 'icon' => 'watchlist' ],
			],
		];
		$updateUserLinksOverflowItems->invokeArgs( null, [ &$content ] );
		$this->assertContains( 'user-links-collapsible-item',
			$content['user-interface-preferences']['uls']['class'],
			'ULS link in user links overflow requires collapsible class'
		);
		$this->assertContains( 'user-links-collapsible-item',
			$content['user-page']['userpage']['class'],
			'User page link in user links overflow requires collapsible class'
		);
		$this->assertContains( 'mw-ui-button',
			$content['user-page']['userpage']['link-class'],
			'User page link in user links overflow requires button classes'
		);
		$this->assertContains( 'mw-ui-quiet',
			$content['user-page']['userpage']['link-class'],
			'User page link in user links overflow requires quiet button classes'
		);
		$this->assertNotContains( 'mw-ui-icon',
			$content['user-page']['userpage']['class'],
			'User page link in user links overflow does not have icon classes'
		);
		$this->assertContains( 'user-links-collapsible-item',
			$content['vector-user-menu-overflow']['watchlist']['class'],
			'Watchlist link in user links overflow requires collapsible class'
		);
		$this->assertContains( 'mw-ui-button',
			$content['vector-user-menu-overflow']['watchlist']['link-class'],
			'Watchlist link in user links overflow requires button classes'
		);
		$this->assertContains( 'mw-ui-quiet',
			$content['vector-user-menu-overflow']['watchlist']['link-class'],
			'Watchlist link in user links overflow requires quiet button classes'
		);
		$this->assertContains( 'mw-ui-icon-element',
			$content['vector-user-menu-overflow']['watchlist']['link-class'],
			'Watchlist link in user links overflow hides text'
		);
		$this->assertTrue(
			$content['vector-user-menu-overflow']['watchlist']['id'] === 'pt-watchlist-2',
			'Watchlist link in user links has unique id'
		);
	}
}
