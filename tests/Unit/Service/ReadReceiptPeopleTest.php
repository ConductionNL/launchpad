<?php

/**
 * ReadReceiptPeopleTest
 *
 * REQ-ACK-010: every row of a read-receipt report carries the person's name
 * and the department the administrator's department field holds, where the
 * viewer may see it.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\ProfileFieldValues;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCA\LaunchPad\Service\ProfileFieldTools;
use OCA\LaunchPad\Service\ProfileSources;
use OCA\LaunchPad\Service\ReadReceiptPeople;
use Unit\Support\InMemoryProfileValues;
use OCP\Accounts\IAccountManager;
use OCP\IUserManager;
use OCP\LDAP\ILDAPProviderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReadReceiptPeopleTest extends TestCase {

	/**
	 * The stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Groups per user.
	 *
	 * @var array<string, string[]>
	 */
	private array $groups = ['editor' => ['kcc'], 'sanne' => ['kcc'], 'pieter' => ['burgerzaken']];

	/**
	 * The profile values.
	 *
	 * @var InMemoryProfileValues
	 */
	private InMemoryProfileValues $values;

	/**
	 * The profile field service.
	 *
	 * @var ProfileFieldService
	 */
	private ProfileFieldService $fields;

	/**
	 * Build the services over in-memory settings and values.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$settingMapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$settingMapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);
		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(fn (string $userId): array => $this->groups[$userId] ?? []);
		$this->values = new InMemoryProfileValues();
		$this->fields = new ProfileFieldService(
			settingMapper: $settingMapper,
			valueMapper: $this->values,
			sources: new ProfileSources(
				accountManager: $this->createMock(originalClassName: IAccountManager::class),
				ldapProviderFactory: $this->createMock(originalClassName: ILDAPProviderFactory::class)
			),
			tools: new ProfileFieldTools(adminTemplateService: $templates, logger: $this->createMock(originalClassName: LoggerInterface::class))
		);
	}//end setUp()

	/**
	 * The people service with display names for sanne and pieter.
	 *
	 * @return ReadReceiptPeople
	 */
	private function people(): ReadReceiptPeople {
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('getDisplayName')->willReturnCallback(
			static fn (string $uid): ?string => ['sanne' => 'Sanne de Vries', 'pieter' => 'Pieter Jansen'][$uid] ?? null
		);

		return new ReadReceiptPeople(userManager: $users, profileFields: $this->fields, values: $this->values);
	}//end people()

	/**
	 * A report with three rows.
	 *
	 * @return array<string, mixed>
	 */
	private static function report(): array {
		return [
			'acknowledgedCount' => 1,
			'pendingCount'      => 2,
			'rows'              => [
				['userId' => 'sanne', 'status' => 'acknowledged', 'acknowledgedAt' => '2026-10-13T10:00:00+00:00'],
				['userId' => 'pieter', 'status' => 'pending', 'acknowledgedAt' => null],
				['userId' => 'gone', 'status' => 'pending', 'acknowledgedAt' => null],
			],
		];
	}//end report()

	/**
	 * Store a person's value of a field.
	 *
	 * @param string $userId The person.
	 * @param string $key    The field key.
	 * @param string $value  The value.
	 *
	 * @return void
	 */
	private function seed(string $userId, string $key, string $value): void {
		$this->values->replaceValues(userId: $userId, field: new ProfileFieldValues(fieldKey: $key, values: [$value], source: 'self'));
	}//end seed()

	/**
	 * Names and departments land on every row.
	 *
	 * @return void
	 */
	public function testEveryRowCarriesTheNameAndTheDepartment(): void {
		$this->fields->saveDefinitions(raw: [
			['key' => 'office', 'label' => 'Office'],
			['key' => 'team', 'label' => 'Afdeling', 'department' => true],
		]);
		$this->seed(userId: 'sanne', key: 'team', value: 'KCC');
		$this->seed(userId: 'sanne', key: 'office', value: 'Stadhuis');
		$this->seed(userId: 'pieter', key: 'team', value: 'Burgerzaken');

		$report = $this->people()->withPeople(report: self::report(), viewerId: 'editor');

		$this->assertSame(expected: 'Afdeling', actual: $report['departmentLabel']);
		$this->assertSame(expected: 2, actual: $report['pendingCount']);
		$this->assertSame(
			expected: [
				['Sanne de Vries', 'KCC', 'acknowledged'],
				['Pieter Jansen', 'Burgerzaken', 'pending'],
				['gone', '', 'pending'],
			],
			actual: array_map(array: $report['rows'], callback: static fn (array $row): array => [$row['displayName'], $row['department'], $row['status']])
		);
	}//end testEveryRowCarriesTheNameAndTheDepartment()

	/**
	 * A department in a field shown to shared groups only stays hidden from others.
	 *
	 * @return void
	 */
	public function testADepartmentTheViewerMayNotSeeStaysEmpty(): void {
		$this->fields->saveDefinitions(raw: [['key' => 'team', 'label' => 'Afdeling', 'department' => true, 'visibility' => 'groups']]);
		$this->seed(userId: 'sanne', key: 'team', value: 'KCC');
		$this->seed(userId: 'pieter', key: 'team', value: 'Burgerzaken');

		$rows = $this->people()->withPeople(report: self::report(), viewerId: 'editor')['rows'];

		$this->assertSame(expected: 'KCC', actual: $rows[0]['department']);
		$this->assertSame(expected: '', actual: $rows[1]['department']);
	}//end testADepartmentTheViewerMayNotSeeStaysEmpty()

	/**
	 * Without a department field the column is empty and has no label.
	 *
	 * @return void
	 */
	public function testWithoutADepartmentFieldTheColumnIsEmpty(): void {
		$this->fields->saveDefinitions(raw: [['key' => 'team', 'label' => 'Afdeling']]);
		$this->seed(userId: 'sanne', key: 'team', value: 'KCC');

		$report = $this->people()->withPeople(report: ['rows' => [['userId' => 'sanne']]], viewerId: 'editor');

		$this->assertSame(expected: '', actual: $report['departmentLabel']);
		$this->assertSame(expected: ['userId' => 'sanne', 'displayName' => 'Sanne de Vries', 'department' => ''], actual: $report['rows'][0]);
		$this->assertSame(expected: [], actual: $this->people()->withPeople(report: [], viewerId: null)['rows']);
	}//end testWithoutADepartmentFieldTheColumnIsEmpty()

	/**
	 * Only one field can be the department.
	 *
	 * @return void
	 */
	public function testOnlyOneFieldCanBeTheDepartment(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'Only one profile field can be the department');

		$this->fields->saveDefinitions(raw: [
			['key' => 'team', 'label' => 'Team', 'department' => true],
			['key' => 'unit', 'label' => 'Unit', 'department' => true],
		]);
	}//end testOnlyOneFieldCanBeTheDepartment()
}//end class
