<?php

/**
 * ProfileFieldService access golden test
 *
 * Pins, on the code before its split, which profile values a viewer may
 * see or find (owner, everyone, shared group, anonymous, private and
 * public standard-field scopes), what a user may save (own fields only,
 * cleaned values, every refusal message) and what an administrator may
 * define (every definition check and message), plus the LDAP sync
 * outcomes.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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
use OCA\LaunchPad\Db\ProfileFieldValues;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCA\LaunchPad\Service\ProfileFieldTools;
use OCA\LaunchPad\Service\ProfileSources;
use OCP\Accounts\IAccountManager;
use OCP\IUser;
use OCP\LDAP\ILDAPProvider;
use OCP\LDAP\ILDAPProviderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Unit\Support\InMemoryProfileValues;

class ProfileFieldServiceAccessGoldenTest extends TestCase {
	/** @var array<string, mixed> */
	private array $settings = [];

	/** @var array<string, string[]> */
	private array $groups = ['ann' => ['sales'], 'bob' => ['sales'], 'cat' => ['hr']];

	private InMemoryProfileValues $values;
	private ILDAPProviderFactory $ldapFactory;
	private LoggerInterface $logger;
	private ProfileFieldService $service;

	protected function setUp(): void {
		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default);
		$settingMapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) {
			$this->settings[$key] = $value;
			return new \OCA\LaunchPad\Db\AdminSetting();
		});
		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(fn (string $userId): array => $this->groups[$userId] ?? []);
		$this->values = new InMemoryProfileValues();
		$this->ldapFactory = $this->createMock(ILDAPProviderFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new ProfileFieldService(
			$settingMapper,
			$this->values,
			new ProfileSources($this->createMock(IAccountManager::class), $this->ldapFactory),
			new ProfileFieldTools($templates, $this->logger),
		);
	}

	private function define(): void {
		$this->service->saveDefinitions(raw: [
			['key' => 'office', 'label' => 'Office', 'type' => 'text', 'source' => 'ldap', 'ldapAttribute' => 'roomNumber'],
			['key' => 'cost', 'label' => 'Cost centre', 'type' => 'text', 'source' => 'self', 'searchable' => true, 'visibility' => 'groups'],
			['key' => 'skills', 'label' => 'Skills', 'type' => 'tags', 'source' => 'self', 'searchable' => true],
			['key' => 'hidden', 'label' => 'Hidden', 'type' => 'text', 'source' => 'self', 'shownInWidget' => false, 'searchable' => true],
		]);
	}

	private function seed(string $userId, string $key, array $values, ?string $scope = null): void {
		$this->values->replaceValues(userId: $userId, field: new ProfileFieldValues(fieldKey: $key, values: $values, source: 'self', scope: $scope));
	}

	public function testNormalisedDefinition(): void {
		$saved = $this->service->saveDefinitions(raw: ['x' => ['key' => 'k1', 'label' => '  Label  ', 'ldapAttribute' => 'ignored', 'searchable' => 1, 'shownInWidget' => 'yes']]);

		$this->assertSame([[
			'key' => 'k1',
			'label' => 'Label',
			'type' => 'text',
			'source' => 'self',
			'ldapAttribute' => '',
			'searchable' => false,
			'shownInWidget' => false,
			'visibility' => 'everyone',
		]], $saved);
	}

	public function testLdapDefinitionKeepsItsAttribute(): void {
		$saved = $this->service->saveDefinitions(raw: [['key' => 'o', 'label' => 'O', 'source' => 'ldap', 'ldapAttribute' => ' room-Number2 ', 'type' => 'tags', 'visibility' => 'groups', 'searchable' => true]]);

		$this->assertSame(['room-Number2', 'tags', 'groups', true, true], [$saved[0]['ldapAttribute'], $saved[0]['type'], $saved[0]['visibility'], $saved[0]['searchable'], $saved[0]['shownInWidget']]);
	}

	/**
	 * Invalid definitions and their message.
	 *
	 * @return array<string,array{0:mixed,1:string}>
	 */
	public static function invalidDefinitions(): array {
		return [
			'not an object'     => ['text', 'A profile field must be an object'],
			'bad key'           => [['key' => 'Bad'], 'Field key must be lower case letters, digits or _: Bad'],
			'key too long'      => [['key' => 'a' . str_repeat('b', 32)], 'Field key must be lower case letters, digits or _: a' . str_repeat('b', 32)],
			'key before label'  => [['key' => '', 'label' => ''], 'Field key must be lower case letters, digits or _: '],
			'no label'          => [['key' => 'k', 'label' => '   '], 'Field label must be 1 to 64 characters: k'],
			'label too long'    => [['key' => 'k', 'label' => str_repeat('é', 65)], 'Field label must be 1 to 64 characters: k'],
			'label before type' => [['key' => 'k', 'label' => '', 'type' => 'x'], 'Field label must be 1 to 64 characters: k'],
			'bad type'          => [['key' => 'k', 'label' => 'L', 'type' => 'number', 'source' => 'x'], 'Field type must be one of '],
			'bad source'        => [['key' => 'k', 'label' => 'L', 'source' => 'x', 'visibility' => 'x'], 'Field source must be one of '],
			'bad visibility'    => [['key' => 'k', 'label' => 'L', 'visibility' => 'x', 'source' => 'ldap'], 'Field visibility must be one of '],
			'ldap no attribute' => [['key' => 'k', 'label' => 'L', 'source' => 'ldap'], 'An LDAP field needs an LDAP attribute name: k'],
			'ldap bad attr'     => [['key' => 'k', 'label' => 'L', 'source' => 'ldap', 'ldapAttribute' => '9x'], 'An LDAP field needs an LDAP attribute name: k'],
			'type not string'   => [['key' => 'k', 'label' => 'L', 'type' => 1], 'Field type must be one of '],
		];
	}

	/**
	 * @dataProvider invalidDefinitions
	 */
	public function testInvalidDefinition(mixed $entry, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		$this->service->saveDefinitions(raw: [$entry]);
	}

	public function testSavedOwnValuesAreCleanedAndOthersSkipped(): void {
		$this->define();

		$fields = $this->service->saveOwnValues(userId: 'ann', values: [
			'office' => 'ignored, read only',
			'cost' => '  4711 ',
			'skills' => [' PHP ', 'php', null, '', 'Vue'],
		]);

		$byKey = array_column($fields, 'values', 'key');
		$this->assertSame([[], ['4711'], ['PHP', 'Vue'], []], [$byKey['office'], $byKey['cost'], $byKey['skills'], $byKey['hidden']]);
	}

	public function testEmptyValuesClearAField(): void {
		$this->define();
		$this->seed('ann', 'cost', ['1']);

		$fields = $this->service->saveOwnValues(userId: 'ann', values: ['cost' => null, 'skills' => []]);

		$this->assertSame([], array_column($fields, 'values', 'key')['cost']);
	}

	/**
	 * Invalid own values and their message; nothing is written.
	 *
	 * @return array<string,array{0:array<string,mixed>,1:string}>
	 */
	public static function invalidValues(): array {
		return [
			'unknown field'   => [['cost' => '1', 'ghost' => 'x'], 'Unknown profile field: ghost'],
			'tags not list'   => [['skills' => 'php'], 'Tags must be a list: skills'],
			'not text'        => [['cost' => 5], 'A value must be text: cost'],
			'tag not text'    => [['skills' => ['a', ['b']]], 'A value must be text: skills'],
			'too long'        => [['cost' => str_repeat('x', 256)], 'A value is longer than 255 characters: cost'],
			'too many tags'   => [['skills' => array_map('strval', range(1, 26))], 'At most 25 tags: skills'],
			'unknown first'   => [['ghost' => 'x', 'cost' => 5], 'Unknown profile field: ghost'],
		];
	}

	/**
	 * @dataProvider invalidValues
	 */
	public function testInvalidOwnValues(array $values, string $message): void {
		$this->define();
		$this->seed('ann', 'cost', ['keep']);

		try {
			$this->service->saveOwnValues(userId: 'ann', values: $values);
			$this->fail('expected a refusal');
		} catch (InvalidArgumentException $e) {
			$this->assertSame($message, $e->getMessage());
		}

		$this->assertSame(['keep'], array_column($this->service->getOwnFields(userId: 'ann'), 'values', 'key')['cost']);
	}

	public function testVisibleFieldsPerViewer(): void {
		$this->define();
		$this->seed('ann', 'cost', ['4711']);
		$this->seed('ann', 'skills', ['PHP']);
		$this->seed('ann', 'hidden', ['secret']);
		$this->seed('cat', 'skills', ['HR law']);
		$this->seed('dan', 'cost', ['9']);

		$all = ['ann', 'cat', 'dan', 'eve'];
		$asBob = $this->service->visibleFieldsFor(viewerId: 'bob', userIds: $all);
		$asCat = $this->service->visibleFieldsFor(viewerId: 'cat', userIds: $all);
		$asDan = $this->service->visibleFieldsFor(viewerId: 'dan', userIds: $all);
		$anonymous = $this->service->visibleFieldsFor(viewerId: null, userIds: $all);

		$this->assertSame([
			'ann' => [
				['key' => 'cost', 'label' => 'Cost centre', 'type' => 'text', 'values' => ['4711']],
				['key' => 'skills', 'label' => 'Skills', 'type' => 'tags', 'values' => ['PHP']],
			],
			'cat' => [['key' => 'skills', 'label' => 'Skills', 'type' => 'tags', 'values' => ['HR law']]],
		], $asBob);
		$this->assertSame(['ann', 'cat'], array_keys($asCat));
		$this->assertSame(['skills'], array_column($asCat['ann'], 'key'));
		$this->assertSame(['ann', 'cat', 'dan'], array_keys($asDan));
		$this->assertSame([], $anonymous);
		$this->assertSame([], $this->service->visibleFieldsFor(viewerId: 'bob', userIds: []));
	}

	public function testNoWidgetFieldsShowsNothing(): void {
		$this->service->saveDefinitions(raw: [['key' => 'k', 'label' => 'K', 'shownInWidget' => false]]);
		$this->seed('ann', 'k', ['v']);

		$this->assertSame([], $this->service->visibleFieldsFor(viewerId: 'ann', userIds: ['ann']));
	}

	public function testSearchHonoursVisibilityAndScopes(): void {
		$this->define();
		$this->seed('ann', 'cost', ['4711 north']);
		$this->seed('cat', 'cost', ['north 9']);
		$this->seed('dan', 'skills', ['north star']);
		$this->seed('eve', 'office', ['north wing']);
		$this->seed('fay', 'nc:role', ['north lead'], IAccountManager::SCOPE_PRIVATE);
		$this->seed('gus', 'nc:role', ['north lead'], IAccountManager::SCOPE_FEDERATED);
		$this->seed('hal', 'hidden', ['north']);

		$this->assertSame(['ann', 'dan', 'gus', 'hal'], $this->service->findMatchingUserIds(viewerId: 'bob', query: '  NORTH '));
		$this->assertSame(['dan', 'fay', 'gus', 'hal'], $this->service->findMatchingUserIds(viewerId: 'fay', query: 'north'));
		$this->assertSame([], $this->service->findMatchingUserIds(viewerId: null, query: 'north'));
		$this->assertSame([], $this->service->findMatchingUserIds(viewerId: 'bob', query: '   '));
	}

	/**
	 * Scope cases: scope, viewer, owner, allowed.
	 *
	 * @return array<string,array{0:?string,1:?string,2:string,3:bool}>
	 */
	public static function scopes(): array {
		return [
			'owner private'     => [IAccountManager::SCOPE_PRIVATE, 'ann', 'ann', true],
			'other private'     => [IAccountManager::SCOPE_PRIVATE, 'bob', 'ann', false],
			'other local'       => [IAccountManager::SCOPE_LOCAL, 'bob', 'ann', true],
			'other no scope'    => [null, 'bob', 'ann', true],
			'anonymous public'  => [IAccountManager::SCOPE_PUBLISHED, null, 'ann', false],
			'anonymous owner'   => [null, null, '', false],
		];
	}

	/**
	 * @dataProvider scopes
	 */
	public function testScopeAllows(?string $scope, ?string $viewerId, string $ownerId, bool $allowed): void {
		$this->assertSame($allowed, $this->service->scopeAllows(scope: $scope, viewerId: $viewerId, ownerId: $ownerId));
	}

	private function ldapUser(string $backend = 'LDAP'): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('ann');
		$user->method('getBackendClassName')->willReturn($backend);
		return $user;
	}

	public function testLdapSyncOutcomes(): void {
		$this->assertSame(0, $this->service->syncLdapFields(user: $this->ldapUser()));

		$this->service->saveDefinitions(raw: [
			['key' => 'office', 'label' => 'Office', 'source' => 'ldap', 'ldapAttribute' => 'roomNumber'],
			['key' => 'langs', 'label' => 'Languages', 'type' => 'tags', 'source' => 'ldap', 'ldapAttribute' => 'lang'],
		]);
		$this->ldapFactory->method('isAvailable')->willReturnOnConsecutiveCalls(false, true, true);
		$provider = $this->createMock(ILDAPProvider::class);
		$calls = 0;
		$provider->method('getMultiValueUserAttribute')->willReturnCallback(static function (string $uid, string $attribute) use (&$calls): array {
			$calls++;
			if ($calls === 4) {
				throw new RuntimeException('ldap gone');
			}

			return $attribute === 'roomNumber' ? [' A1 ', 'B2'] : ['nl', 'NL', 'en'];
		});
		$this->ldapFactory->method('getLDAPProvider')->willReturn($provider);
		$this->logger->expects($this->once())->method('warning')->with('Profile LDAP sync failed for ann: ldap gone');

		$this->assertSame(0, $this->service->syncLdapFields(user: $this->ldapUser('Database')));
		$this->assertSame(0, $this->service->syncLdapFields(user: $this->ldapUser()));
		$this->assertSame(2, $this->service->syncLdapFields(user: $this->ldapUser()));
		$this->assertSame(1, $this->service->syncLdapFields(user: $this->ldapUser()));

		$byKey = array_column($this->service->getOwnFields(userId: 'ann'), 'values', 'key');
		$this->assertSame([['A1'], ['nl', 'en']], [$byKey['office'], $byKey['langs']]);
	}
}//end class
