<?php

/**
 * CatalogueServiceTest
 *
 * The app catalogue (launcher-app-catalogue, REQ-ACAT-001..004): group
 * filtering, entry validation, the open-request limit, the approve and
 * decline paths, and who is notified.
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

use DomainException;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\AppRequest;
use OCA\LaunchPad\Db\AppRequestMapper;
use OCA\LaunchPad\Db\CatalogueApp;
use OCA\LaunchPad\Db\CatalogueAppMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\CatalogueService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for CatalogueService.
 */
class CatalogueServiceTest extends TestCase {

	/**
	 * Stored catalogue entries.
	 *
	 * @var array<int, CatalogueApp>
	 */
	private array $apps = [];

	/**
	 * Stored requests, by uuid.
	 *
	 * @var array<string, AppRequest>
	 */
	private array $requests = [];

	/**
	 * Sent notifications: [user, subject, params, object id].
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $sent = [];

	/**
	 * What each notification was set to.
	 *
	 * @var \WeakMap<INotification, \ArrayObject>
	 */
	private \WeakMap $records;

	/**
	 * Admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Open requests per user, as the mapper counts them.
	 *
	 * @var array<string, int>
	 */
	private array $openCounts = [];

	/**
	 * Build the service over in-memory mappers.
	 *
	 * @param array<string, array<int, string>> $userGroups Group ids per user.
	 *
	 * @return CatalogueService
	 */
	private function service(array $userGroups = []): CatalogueService {
		$this->records = new \WeakMap();
		$appMapper = $this->createMock(CatalogueAppMapper::class);
		$appMapper->method('findAllOrdered')->willReturnCallback(fn (): array => array_values($this->apps));
		$appMapper->method('insert')->willReturnCallback(
			function (CatalogueApp $app): CatalogueApp {
				$app->setId(count($this->apps) + 1);
				$this->apps[$app->getId()] = $app;
				return $app;
			}
		);
		$appMapper->method('update')->willReturnArgument(0);

		$requestMapper = $this->createMock(AppRequestMapper::class);
		$requestMapper->method('countOpenBy')->willReturnCallback(fn (string $userId): int => $this->openCounts[$userId] ?? 0);
		$requestMapper->method('insert')->willReturnCallback(
			function (AppRequest $request): AppRequest {
				$this->requests[$request->getUuid()] = $request;
				return $request;
			}
		);
		$requestMapper->method('update')->willReturnArgument(0);
		$requestMapper->method('findByUuid')->willReturnCallback(fn (string $uuid): AppRequest => $this->requests[$uuid]);

		$groups = $this->createMock(AdminTemplateService::class);
		$groups->method('getUserGroupIdsFor')->willReturnCallback(fn (string $userId): array => $userGroups[$userId] ?? []);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->willReturnCallback(
			function (string $gid): ?IGroup {
				$members = ['admin' => ['noor', 'root'], 'appbeheer' => ['kees', 'noor']][$gid] ?? null;
				if ($members === null) {
					return null;
				}

				$group = $this->createMock(IGroup::class);
				$group->method('getUsers')->willReturn(array_map(fn (string $uid): IUser => $this->user(uid: $uid), $members));
				return $group;
			}
		);
		$groupManager->method('groupExists')->willReturnCallback(fn (string $gid): bool => in_array($gid, ['admin', 'appbeheer'], true));

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('getDisplayName')->willReturnCallback(fn (string $uid): ?string => ['pieter' => 'Pieter'][$uid] ?? null);

		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default);
		$settingMapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): \OCA\LaunchPad\Db\AdminSetting {
				$this->settings[$key] = $value;
				return new \OCA\LaunchPad\Db\AdminSetting();
			}
		);

		$notifications = $this->createMock(INotificationManager::class);
		$notifications->method('createNotification')->willReturnCallback(fn (): INotification => $this->recordingNotification());
		$notifications->method('notify')->willReturnCallback(
			function (INotification $notification): void {
				$this->sent[] = $this->records[$notification]->getArrayCopy();
			}
		);

		return new CatalogueService(
			appMapper: $appMapper,
			requestMapper: $requestMapper,
			groups: $groups,
			groupManager: $groupManager,
			userManager: $userManager,
			settingMapper: $settingMapper,
			notificationManager: $notifications,
			logger: $this->createMock(LoggerInterface::class)
		);
	}//end service()

	/**
	 * A user with an id.
	 *
	 * @param string $uid The id.
	 *
	 * @return IUser
	 */
	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}//end user()

	/**
	 * A notification that records what it was set to.
	 *
	 * @return INotification
	 */
	private function recordingNotification(): INotification {
		$record = new \ArrayObject(['', '', [], '']);
		$mock   = $this->createMock(INotification::class);
		$this->records[$mock] = $record;
		$mock->method('setApp')->willReturnSelf();
		$mock->method('setDateTime')->willReturnSelf();
		$mock->method('setUser')->willReturnCallback(
			function (string $user) use ($mock, $record): INotification {
				$record[0] = $user;
				return $mock;
			}
		);
		$mock->method('setSubject')->willReturnCallback(
			function (string $subject, array $params = []) use ($mock, $record): INotification {
				$record[1] = $subject;
				$record[2] = $params;
				return $mock;
			}
		);
		$mock->method('setObject')->willReturnCallback(
			function (string $type, string $id) use ($mock, $record): INotification {
				$record[3] = $type.':'.$id;
				return $mock;
			}
		);
		return $mock;
	}//end recordingNotification()

	/**
	 * Add an entry.
	 *
	 * @param CatalogueService $service The service.
	 * @param string           $name    The name.
	 * @param array<int,string> $groups The target groups.
	 *
	 * @return CatalogueApp
	 */
	private function addEntry(CatalogueService $service, string $name, array $groups = []): CatalogueApp {
		return $service->create(
			input: ['name' => $name, 'linkType' => 'url', 'linkValue' => 'https://'.strtolower($name).'.example.nl', 'targetGroups' => $groups]
		);
	}//end addEntry()

	/**
	 * A member of the target group sees the entry; a non-member does not;
	 * an entry without groups is for everyone.
	 *
	 * @return void
	 */
	public function testTheListFollowsTheCallersGroups(): void {
		$service = $this->service(userGroups: ['pieter' => ['Burgerzaken'], 'sanne' => ['Financien']]);
		$this->addEntry(service: $service, name: 'Zaaksysteem', groups: ['Burgerzaken']);
		$this->addEntry(service: $service, name: 'Agenda');

		$this->assertSame(['Zaaksysteem', 'Agenda'], array_map(static fn (CatalogueApp $a): string => $a->getName(), $service->listFor(userId: 'pieter')));
		$this->assertSame(['Agenda'], array_map(static fn (CatalogueApp $a): string => $a->getName(), $service->listFor(userId: 'sanne')));
		$this->assertCount(2, $service->listAll());
	}//end testTheListFollowsTheCallersGroups()

	/**
	 * A stored entry carries its fields, distinct groups and a uuid.
	 *
	 * @return void
	 */
	public function testCreateStoresTheEntry(): void {
		$app = $this->service()->create(
			input: [
				'name' => ' Zaaksysteem ',
				'description' => 'Cases',
				'icon' => 'icon-folder',
				'iconType' => 'odd',
				'linkType' => 'url',
				'linkValue' => 'https://zaken.example.nl',
				'targetGroups' => ['Burgerzaken', '', 'Burgerzaken', ['id' => 'Vergunningen']],
				'sortOrder' => '3',
			]
		);

		$json = $app->jsonSerialize();
		$this->assertSame('Zaaksysteem', $json['name']);
		$this->assertSame('class', $json['iconType']);
		$this->assertSame(['Burgerzaken', 'Vergunningen'], $json['targetGroups']);
		$this->assertSame(3, $json['sortOrder']);
		$this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $json['uuid']);
	}//end testCreateStoresTheEntry()

	/**
	 * Entries without a name, with a bad address or a bad app id are refused.
	 *
	 * @return void
	 */
	public function testInvalidEntriesAreRefused(): void {
		$service = $this->service();
		$bad = [
			['name' => '', 'linkType' => 'url', 'linkValue' => 'https://a.nl'],
			['name' => 'A', 'linkType' => 'url', 'linkValue' => 'javascript:alert(1)'],
			['name' => 'A', 'linkType' => 'app', 'linkValue' => '../files'],
			['name' => 'A', 'linkType' => 'ftp', 'linkValue' => 'ftp://a.nl'],
		];
		foreach ($bad as $input) {
			try {
				$service->create(input: $input);
				$this->fail('accepted '.json_encode($input));
			} catch (InvalidArgumentException $e) {
				$this->assertNotSame('', $e->getMessage());
			}
		}

		$this->assertSame('files', $service->create(input: ['name' => 'Bestanden', 'linkType' => 'app', 'linkValue' => 'files'])->getLinkValue());
	}//end testInvalidEntriesAreRefused()

	/**
	 * A request is stored open and reaches every administrator and the
	 * manager group's members once each.
	 *
	 * @return void
	 */
	public function testARequestNotifiesTheAdministratorsAndTheManagerGroup(): void {
		$service = $this->service();
		$service->setManagerGroup(groupId: 'appbeheer');

		$request = $service->request(userId: 'pieter', appName: ' Kaartviewer ', purpose: 'Ik moet percelen bekijken bij vergunningen');

		$this->assertSame('open', $request->getStatus());
		$this->assertSame('Kaartviewer', $request->getAppName());
		$this->assertSame('pieter', $request->getRequestedBy());
		$object = 'app_request:'.$request->getUuid();
		$this->assertSame(
			[
				['noor', 'app_requested', ['Pieter', 'Kaartviewer'], $object],
				['root', 'app_requested', ['Pieter', 'Kaartviewer'], $object],
				['kees', 'app_requested', ['Pieter', 'Kaartviewer'], $object],
			],
			$this->sent
		);
	}//end testARequestNotifiesTheAdministratorsAndTheManagerGroup()

	/**
	 * The eleventh open request is refused and nothing is stored or sent.
	 *
	 * @return void
	 */
	public function testTheEleventhOpenRequestIsRefused(): void {
		$this->openCounts['pieter'] = 10;
		$service = $this->service();

		try {
			$service->request(userId: 'pieter', appName: 'Kaartviewer', purpose: '');
			$this->fail('an eleventh open request was accepted');
		} catch (DomainException $e) {
			$this->assertSame(CatalogueService::ERROR_REQUEST_LIMIT, $e->getMessage());
		}

		$this->assertSame([], $this->requests);
		$this->assertSame([], $this->sent);

		$this->openCounts['pieter'] = 9;
		$this->assertSame('open', $service->request(userId: 'pieter', appName: 'Kaartviewer', purpose: '')->getStatus());
	}//end testTheEleventhOpenRequestIsRefused()

	/**
	 * A request without a name is refused.
	 *
	 * @return void
	 */
	public function testARequestNeedsAName(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service()->request(userId: 'pieter', appName: '  ', purpose: 'x');
	}//end testARequestNeedsAName()

	/**
	 * Approving adds the completed entry under the requested name, links the
	 * request to it and tells the requester.
	 *
	 * @return void
	 */
	public function testApprovingAddsTheEntryAndTellsTheRequester(): void {
		$service = $this->service();
		$request = $service->request(userId: 'pieter', appName: 'Kaartviewer', purpose: '');
		$this->sent = [];

		$result = $service->approve(
			requestUuid: $request->getUuid(),
			adminId: 'noor',
			entry: ['linkType' => 'url', 'linkValue' => 'https://kaart.example.nl']
		);

		$this->assertSame('Kaartviewer', $result['app']->getName());
		$this->assertSame('approved', $result['request']->getStatus());
		$this->assertSame($result['app']->getId(), $result['request']->getCatalogueAppId());
		$this->assertSame('noor', $result['request']->getDecidedBy());
		$this->assertNotNull($result['request']->getDecidedAt());
		$this->assertSame([['pieter', 'app_request_decided', ['Kaartviewer', 'approved', ''], 'app_request:'.$request->getUuid()]], $this->sent);
	}//end testApprovingAddsTheEntryAndTellsTheRequester()

	/**
	 * Declining needs a note, stores it and tells the requester why.
	 *
	 * @return void
	 */
	public function testDecliningNeedsANoteAndTellsTheRequesterWhy(): void {
		$service = $this->service();
		$request = $service->request(userId: 'pieter', appName: 'Kaartviewer', purpose: '');
		$this->sent = [];

		try {
			$service->decline(requestUuid: $request->getUuid(), adminId: 'noor', note: ' ');
			$this->fail('declined without a note');
		} catch (InvalidArgumentException) {
			$this->assertSame('open', $request->getStatus());
		}

		$declined = $service->decline(requestUuid: $request->getUuid(), adminId: 'noor', note: 'Gebruik de kaartlaag in het zaaksysteem');

		$this->assertSame('declined', $declined->getStatus());
		$this->assertSame('Gebruik de kaartlaag in het zaaksysteem', $declined->getDecisionNote());
		$this->assertSame(
			[['pieter', 'app_request_decided', ['Kaartviewer', 'declined', 'Gebruik de kaartlaag in het zaaksysteem'], 'app_request:'.$request->getUuid()]],
			$this->sent
		);
	}//end testDecliningNeedsANoteAndTellsTheRequesterWhy()

	/**
	 * A decided request cannot be decided again.
	 *
	 * @return void
	 */
	public function testADecidedRequestCannotBeDecidedAgain(): void {
		$service = $this->service();
		$request = $service->request(userId: 'pieter', appName: 'Kaartviewer', purpose: '');
		$service->decline(requestUuid: $request->getUuid(), adminId: 'noor', note: 'No');

		$this->expectException(DomainException::class);
		$this->expectExceptionMessage(CatalogueService::ERROR_NOT_OPEN);
		$service->approve(requestUuid: $request->getUuid(), adminId: 'noor', entry: ['linkType' => 'app', 'linkValue' => 'maps']);
	}//end testADecidedRequestCannotBeDecidedAgain()

	/**
	 * The manager group must exist; '' clears it.
	 *
	 * @return void
	 */
	public function testTheManagerGroupMustExist(): void {
		$service = $this->service();
		$this->assertSame('', $service->getManagerGroup());
		$this->assertSame('appbeheer', $service->setManagerGroup(groupId: 'appbeheer'));
		$this->assertSame('appbeheer', $service->getManagerGroup());
		$this->assertSame('', $service->setManagerGroup(groupId: ''));

		$this->expectException(InvalidArgumentException::class);
		$service->setManagerGroup(groupId: 'nobody');
	}//end testTheManagerGroupMustExist()

	/**
	 * An empty catalogue gets the four demo entries, once.
	 *
	 * @return void
	 */
	public function testTheDemoEntriesSeedAnEmptyCatalogueOnce(): void {
		$service = $this->service();

		$this->assertSame(4, $service->seedDemoEntries());
		$this->assertSame(
			['Zaaksysteem', 'Personeelsportaal', 'Bestanden', 'Agenda'],
			array_map(static fn (CatalogueApp $a): string => $a->getName(), $service->listAll())
		);
		$this->assertSame(['files', 'calendar'], [$this->apps[3]->getLinkValue(), $this->apps[4]->getLinkValue()]);
		$this->assertSame([], $this->apps[1]->getTargetGroupList());
		$this->assertSame(0, $service->seedDemoEntries());
		$this->assertCount(4, $this->apps);
	}//end testTheDemoEntriesSeedAnEmptyCatalogueOnce()
}//end class
