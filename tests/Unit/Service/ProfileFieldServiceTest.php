<?php

/**
 * ProfileFieldServiceTest
 *
 * Custom profile fields (widgets-people-expertise-and-fields, REQ-PEX-001..004):
 * definition validation, self edits, the LDAP sync with a stubbed provider,
 * the standard-field mirror, and the visibility rule for search and display.
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\IUser;
use OCP\LDAP\ILDAPProvider;
use OCP\LDAP\ILDAPProviderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Unit\Support\InMemoryProfileValues;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) One test per scenario.
 */
class ProfileFieldServiceTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	private InMemoryProfileValues $values;

	private $accountManager;

	/**
	 * Accounts per user id.
	 *
	 * @var array<string, IAccount>
	 */
	private array $accounts = [];

	private $ldapFactory;

	/**
	 * Group ids per user.
	 *
	 * @var array<string, string[]>
	 */
	private array $groups = [];

	private ProfileFieldService $service;

	protected function setUp(): void {
		parent::setUp();

		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$settingMapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value) {
				$this->settings[$key] = $value;
				return new \OCA\LaunchPad\Db\AdminSetting();
			}
		);

		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(
			fn (string $userId): array => $this->groups[$userId] ?? []
		);

		$this->values = new InMemoryProfileValues();
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->accountManager->method('getAccount')->willReturnCallback(
			fn (IUser $user): IAccount => $this->accounts[$user->getUID()]
		);
		$this->ldapFactory = $this->createMock(ILDAPProviderFactory::class);

		$this->service = new ProfileFieldService(
			settingMapper: $settingMapper,
			valueMapper: $this->values,
			accountManager: $this->accountManager,
			adminTemplateService: $templates,
			ldapProviderFactory: $this->ldapFactory,
			logger: $this->createMock(LoggerInterface::class),
		);
	}//end setUp()

	/**
	 * The demo definitions: office (LDAP), cost centre (groups only), expertise (tags).
	 *
	 * @return void
	 */
	private function defineFields(): void {
		$this->service->saveDefinitions(
			raw: [
				['key' => 'office', 'label' => 'Kantoorlocatie', 'type' => 'text', 'source' => 'ldap', 'ldapAttribute' => 'physicalDeliveryOfficeName', 'shownInWidget' => true],
				['key' => 'cost_centre', 'label' => 'Kostenplaats', 'type' => 'text', 'source' => 'self', 'searchable' => true, 'visibility' => 'groups'],
				['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags', 'source' => 'self', 'searchable' => true],
			]
		);
	}//end defineFields()

	private function user(string $uid, string $backend = 'Database'): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getBackendClassName')->willReturn($backend);
		return $user;
	}//end user()

	public function testDefinitionsAreValidatedAndNormalised(): void {
		$this->defineFields();
		$definitions = $this->service->getDefinitions();

		$this->assertCount(3, $definitions);
		$this->assertSame('everyone', $definitions[2]['visibility']);
		$this->assertFalse($definitions[0]['searchable']);
		$this->assertSame('', $definitions[1]['ldapAttribute']);
	}//end testDefinitionsAreValidatedAndNormalised()

	public function testMoreThanTenFieldsIsRefused(): void {
		$raw = [];
		for ($i = 0; $i < 11; $i++) {
			$raw[] = ['key' => 'f' . $i, 'label' => 'Field ' . $i];
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->saveDefinitions(raw: $raw);
	}//end testMoreThanTenFieldsIsRefused()

	public function testAnLdapFieldNeedsAnAttribute(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->saveDefinitions(raw: [['key' => 'office', 'label' => 'Office', 'source' => 'ldap']]);
	}//end testAnLdapFieldNeedsAnAttribute()

	public function testDuplicateKeysAndBadTypesAreRefused(): void {
		try {
			$this->service->saveDefinitions(raw: [['key' => 'a', 'label' => 'A'], ['key' => 'a', 'label' => 'B']]);
			$this->fail('duplicate key accepted');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->saveDefinitions(raw: [['key' => 'a', 'label' => 'A', 'type' => 'number']]);
	}//end testDuplicateKeysAndBadTypesAreRefused()

	/**
	 * REQ-PEX-002 scenario "Pieter adds his subjects".
	 *
	 * @return void
	 */
	public function testPieterAddsHisSubjectsAndLdapFieldsStayReadOnly(): void {
		$this->defineFields();

		$fields = $this->service->saveOwnValues(
			userId: 'pieter',
			values: [
				'expertise' => ['subsidies', ' Omgevingswet ', 'SUBSIDIES', ''],
				'office' => 'I typed this',
			]
		);

		$byKey = array_column($fields, null, 'key');
		$this->assertSame(['subsidies', 'Omgevingswet'], $byKey['expertise']['values']);
		$this->assertSame([], $byKey['office']['values'], 'an LDAP field must not take a self edit');
		$this->assertTrue($byKey['office']['readOnly']);
		$this->assertFalse($byKey['expertise']['readOnly']);
	}//end testPieterAddsHisSubjectsAndLdapFieldsStayReadOnly()

	public function testUnknownKeysAndWrongShapesAreRefused(): void {
		$this->defineFields();

		try {
			$this->service->saveOwnValues(userId: 'pieter', values: ['nope' => 'x']);
			$this->fail('unknown key accepted');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(1);
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->saveOwnValues(userId: 'pieter', values: ['expertise' => 'not a list']);
	}//end testUnknownKeysAndWrongShapesAreRefused()

	/**
	 * REQ-PEX-001 scenario "Office location from LDAP", with a stubbed provider.
	 *
	 * @return void
	 */
	public function testLdapFieldsSyncForLdapAccountsOnly(): void {
		$this->defineFields();
		$provider = $this->createMock(ILDAPProvider::class);
		$provider->method('getMultiValueUserAttribute')
			->with('pieter', 'physicalDeliveryOfficeName')
			->willReturn(['Stadhuis, 3e verdieping', 'ignored second value']);
		$this->ldapFactory->method('isAvailable')->willReturn(true);
		$this->ldapFactory->method('getLDAPProvider')->willReturn($provider);

		$this->assertSame(0, $this->service->syncLdapFields(user: $this->user(uid: 'sanne')), 'a database account is not read from LDAP');
		$this->assertSame(1, $this->service->syncLdapFields(user: $this->user(uid: 'pieter', backend: 'LDAP')));

		$shown = $this->service->visibleFieldsFor(viewerId: 'sanne', userIds: ['pieter']);
		$this->assertSame(
			[['key' => 'office', 'label' => 'Kantoorlocatie', 'type' => 'text', 'values' => ['Stadhuis, 3e verdieping']]],
			$shown['pieter']
		);
	}//end testLdapFieldsSyncForLdapAccountsOnly()

	public function testLdapSyncDoesNothingWithoutUserLdap(): void {
		$this->defineFields();
		$this->ldapFactory->method('isAvailable')->willReturn(false);
		$this->ldapFactory->expects($this->never())->method('getLDAPProvider');

		$this->assertSame(0, $this->service->syncLdapFields(user: $this->user(uid: 'pieter', backend: 'LDAP')));
	}//end testLdapSyncDoesNothingWithoutUserLdap()

	/**
	 * REQ-PEX-003: a tag matches on a part of the word, for any signed-in viewer.
	 *
	 * @return void
	 */
	public function testSearchFindsTheSubsidiesExpertByPartOfTheTag(): void {
		$this->defineFields();
		$this->service->saveOwnValues(userId: 'pieter', values: ['expertise' => ['subsidies']]);

		$this->assertSame(['pieter'], $this->service->findMatchingUserIds(viewerId: 'sanne', query: 'Subsidie'));
		$this->assertSame([], $this->service->findMatchingUserIds(viewerId: null, query: 'subsidie'));
	}//end testSearchFindsTheSubsidiesExpertByPartOfTheTag()

	/**
	 * REQ-PEX-004 scenario "Group-only field".
	 *
	 * @return void
	 */
	public function testAGroupOnlyFieldNeitherShowsNorMatchesOutsideTheGroup(): void {
		$this->defineFields();
		$this->service->saveDefinitions(
			raw: array_map(
				static fn (array $definition): array => array_merge($definition, ['shownInWidget' => true]),
				$this->service->getDefinitions()
			)
		);
		$this->service->saveOwnValues(userId: 'pieter', values: ['cost_centre' => 'KP-4711']);
		$this->groups = ['pieter' => ['finance'], 'sanne' => ['kcc'], 'karin' => ['finance']];

		$this->assertSame([], $this->service->findMatchingUserIds(viewerId: 'sanne', query: 'kp-47'));
		$this->assertArrayNotHasKey('pieter', $this->service->visibleFieldsFor(viewerId: 'sanne', userIds: ['pieter']));

		$this->assertSame(['pieter'], $this->service->findMatchingUserIds(viewerId: 'karin', query: 'kp-47'), 'CONTROL: a colleague in the group does match');
		$this->assertArrayHasKey('pieter', $this->service->visibleFieldsFor(viewerId: 'karin', userIds: ['pieter']));
	}//end testAGroupOnlyFieldNeitherShowsNorMatchesOutsideTheGroup()

	public function testANotSearchableFieldDoesNotMatch(): void {
		$this->service->saveDefinitions(raw: [['key' => 'room', 'label' => 'Room', 'searchable' => false]]);
		$this->service->saveOwnValues(userId: 'pieter', values: ['room' => 'B-204']);

		$this->assertSame([], $this->service->findMatchingUserIds(viewerId: 'sanne', query: 'b-204'));
	}//end testANotSearchableFieldDoesNotMatch()

	/**
	 * REQ-PEX-004 scenario "Private biography is not searchable".
	 *
	 * @return void
	 */
	public function testAPrivateBiographyIsMirroredButNeverMatchesForOthers(): void {
		$this->mirrorBiography(uid: 'karin', text: 'Ik weet alles van subsidies', scope: IAccountManager::SCOPE_PRIVATE);
		$this->mirrorBiography(uid: 'pieter', text: 'Subsidies en fondsen', scope: IAccountManager::SCOPE_LOCAL);

		$this->assertSame(['pieter'], $this->service->findMatchingUserIds(viewerId: 'sanne', query: 'subsidies'));
		$this->assertContains('karin', $this->service->findMatchingUserIds(viewerId: 'karin', query: 'subsidies'), 'CONTROL: the owner matches her own private field');
	}//end testAPrivateBiographyIsMirroredButNeverMatchesForOthers()

	public function testDeletingAUserRemovesTheirValues(): void {
		$this->defineFields();
		$this->service->saveOwnValues(userId: 'pieter', values: ['expertise' => ['subsidies']]);
		$this->service->deleteUser(userId: 'pieter');

		$this->assertSame([], $this->values->rows);
	}//end testDeletingAUserRemovesTheirValues()

	private function mirrorBiography(string $uid, string $text, string $scope): void {
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->willReturnCallback(
			function (string $property) use ($text, $scope) {
				$prop = $this->createMock(IAccountProperty::class);
				$value = '';
				if ($property === IAccountManager::PROPERTY_BIOGRAPHY) {
					$value = $text;
				}

				$prop->method('getValue')->willReturn($value);
				$prop->method('getScope')->willReturn($scope);
				return $prop;
			}
		);
		$this->accounts[$uid] = $account;

		$this->service->mirrorStandardFields(user: $this->user(uid: $uid));
	}//end mirrorBiography()
}//end class
