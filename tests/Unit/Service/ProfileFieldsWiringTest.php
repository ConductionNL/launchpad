<?php

/**
 * ProfileFieldsWiringTest
 *
 * widgets-people-expertise-and-fields from the callers' side: the daily sync
 * job, the sign-in, profile-edit and delete listener, the personal settings
 * section and the API shape of a stored value, each through the real
 * ProfileFieldService on in-memory values. A failure in one person's sync is
 * counted and logged, never thrown.
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

use Exception;
use OCA\LaunchPad\BackgroundJob\ProfileFieldsSyncJob;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\ProfileValue;
use OCA\LaunchPad\Listener\ProfileFieldsListener;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCA\LaunchPad\Settings\LaunchPadPersonal;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\UserUpdatedEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;
use OCP\LDAP\ILDAPProviderFactory;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserLoggedInEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;
use Unit\Support\InMemoryProfileValues;

/**
 * Tests for the callers of ProfileFieldService.
 */
class ProfileFieldsWiringTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Warnings logged.
	 *
	 * @var string[]
	 */
	private array $warnings = [];

	private InMemoryProfileValues $values;

	private ProfileFieldService $service;

	private LoggerInterface $logger;

	/**
	 * Wire the real service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$settingMapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);

		$accounts = $this->createMock(IAccountManager::class);
		$accounts->method('getAccount')->willReturnCallback(function (IUser $user): IAccount {
			if ($user->getUID() === 'ghost') {
				throw new Exception('no account');
			}

			$headline = $this->createMock(IAccountProperty::class);
			$headline->method('getValue')->willReturn('Subsidieadviseur');
			$headline->method('getScope')->willReturn(IAccountManager::SCOPE_FEDERATED);
			$account = $this->createMock(IAccount::class);
			$account->method('getProperty')->willReturn($headline);

			return $account;
		});

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->logger->method('warning')->willReturnCallback(function (string $message): void {
			$this->warnings[] = $message;
		});

		$this->values  = new InMemoryProfileValues();
		$this->service = new ProfileFieldService(
			settingMapper: $settingMapper,
			valueMapper: $this->values,
			accountManager: $accounts,
			adminTemplateService: $this->createMock(AdminTemplateService::class),
			ldapProviderFactory: $this->createMock(ILDAPProviderFactory::class),
			logger: $this->logger,
		);
	}//end setUp()

	/**
	 * A user with a uid; `getBackendClassName` throws for "broken".
	 *
	 * @param string $uid The uid.
	 *
	 * @return IUser
	 */
	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		if ($uid === 'broken') {
			$user->method('getBackendClassName')->willThrowException(new RuntimeException('backend gone'));
		} else {
			$user->method('getBackendClassName')->willReturn('Database');
		}

		return $user;
	}//end user()

	/**
	 * The job mirrors every seen user and counts a failing one.
	 *
	 * @return void
	 */
	public function testTheDailyJobMirrorsEveryoneAndCountsAFailure(): void {
		$this->service->saveDefinitions(raw: [['key' => 'office', 'label' => 'Kantoorlocatie', 'type' => 'text', 'source' => 'ldap', 'ldapAttribute' => 'physicalDeliveryOfficeName']]);
		$users = $this->createMock(IUserManager::class);
		$users->method('callForSeenUsers')->willReturnCallback(function (\Closure $callback): void {
			foreach (['pieter', 'broken'] as $uid) {
				$callback($this->user(uid: $uid));
			}
		});

		$job = new ProfileFieldsSyncJob(time: $this->createMock(ITimeFactory::class), userManager: $users, profileFields: $this->service, logger: $this->logger);
		(new ReflectionMethod($job, 'run'))->invoke($job, null);

		$mirrored = array_map(static fn (ProfileValue $row): string => $row->getValue(), $this->values->findByUsers(userIds: ['pieter']));
		$this->assertContains('Subsidieadviseur', $mirrored, 'the job mirrored Pieter');
		$this->assertSame(['ProfileFieldsSyncJob: 1 user(s) could not be refreshed.'], $this->warnings);
	}//end testTheDailyJobMirrorsEveryoneAndCountsAFailure()

	/**
	 * Sign-in mirrors, a profile edit mirrors, a delete removes, and a failure is logged.
	 *
	 * @return void
	 */
	public function testTheListenerFollowsSignInEditAndDelete(): void {
		$listener = new ProfileFieldsListener(profileFields: $this->service, logger: $this->logger);

		$listener->handle(new UserLoggedInEvent($this->user(uid: 'pieter'), 'pieter', null, false));
		$this->assertNotSame([], $this->values->findByUsers(userIds: ['pieter']), 'sign-in mirrors the standard fields');

		$listener->handle(new UserUpdatedEvent($this->user(uid: 'sanne'), []));
		$this->assertNotSame([], $this->values->findByUsers(userIds: ['sanne']), 'a profile edit mirrors them');

		$listener->handle(new UserDeletedEvent($this->user(uid: 'pieter')));
		$this->assertSame([], $this->values->findByUsers(userIds: ['pieter']), 'a delete removes them');

		$this->service->saveDefinitions(raw: [['key' => 'office', 'label' => 'Kantoorlocatie', 'type' => 'text', 'source' => 'ldap', 'ldapAttribute' => 'physicalDeliveryOfficeName']]);
		$listener->handle(new UserLoggedInEvent($this->user(uid: 'broken'), 'broken', null, false));
		$this->assertSame(['Profile field sync failed: backend gone'], $this->warnings, 'a failure never breaks the sign-in');
	}//end testTheListenerFollowsSignInEditAndDelete()

	/**
	 * The personal section shows only once a field is defined.
	 *
	 * @return void
	 */
	public function testThePersonalSectionShowsOnceAFieldExists(): void {
		$personal = new LaunchPadPersonal(profileFields: $this->service);
		$this->assertNull($personal->getSection());
		$this->assertSame(90, $personal->getPriority());

		$this->service->saveDefinitions(raw: [['key' => 'expertise', 'label' => 'Expertise', 'type' => 'tags', 'source' => 'self']]);
		$this->assertSame('personal-info', $personal->getSection());
	}//end testThePersonalSectionShowsOnceAFieldExists()

	/**
	 * The personal form loads the personal bundle into its template.
	 *
	 * @return void
	 */
	public function testThePersonalFormRendersItsTemplate(): void {
		if (class_exists(class: '\OC', autoload: false) === false) {
			$this->markTestSkipped(message: 'Util::addScript needs a live Nextcloud; CI boots one.');
		}

		$form = (new LaunchPadPersonal(profileFields: $this->service))->getForm();
		$this->assertSame('settings/personal', $form->getTemplateName());
	}//end testThePersonalFormRendersItsTemplate()

	/**
	 * A stored value's API shape.
	 *
	 * @return void
	 */
	public function testAValueSerialisesWithoutItsSearchCopy(): void {
		$row = new ProfileValue();
		$row->setUserId('pieter');
		$row->setFieldKey('expertise');
		$row->setValue('Subsidies');
		$row->setValueSearch('subsidies');
		$row->setSource('self');

		$this->assertSame(
			['id' => null, 'userId' => 'pieter', 'fieldKey' => 'expertise', 'value' => 'Subsidies', 'source' => 'self', 'scope' => null],
			$row->jsonSerialize()
		);
	}//end testAValueSerialisesWithoutItsSearchCopy()
}//end class
