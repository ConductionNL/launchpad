<?php

/**
 * ActivityPublisher golden test
 *
 * Pins every event row the publisher writes, and every count it returns,
 * for a fixed set of publish, recipient, group and global cases, against a
 * recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Activity;

use OCA\LaunchPad\Activity\PublisherTools;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\ActivitySubject;
use OCA\LaunchPad\Activity\DebounceHelper;
use OCA\LaunchPad\Activity\Extension;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The publisher's rows and counts, case by case.
 *
 * @covers \OCA\LaunchPad\Activity\ActivityPublisher
 * @uses   \OCA\LaunchPad\Activity\DebounceHelper
 * @uses   \OCA\LaunchPad\Activity\ActivityRow
 * @uses   \OCA\LaunchPad\Activity\ActivitySubject
 * @uses   \OCA\LaunchPad\Activity\PublisherTools
 */
class ActivityPublisherGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/activity-publisher.json';

	/**
	 * The calls of each case, in order: [method, named arguments].
	 *
	 * @return array<string, list<array{0: string, 1: array<string, mixed>}>>
	 */
	private static function cases(): array {
		$dash = [
			'dashboardUuid' => 'uuid-1',
			'dashboardName' => 'Team',
			'dashboardLink' => 'https://nc.example/d/1',
		];
		$long = str_repeat('abcdefghij', 25);

		return [
			'publish to other'        => [['publish', ['type' => Extension::EVENT_SHARED, 'actorUserId' => 'alice', 'recipientUserId' => 'bob'] + $dash]],
			'publish to self'         => [['publish', ['type' => Extension::EVENT_CREATED, 'actorUserId' => 'alice', 'recipientUserId' => 'alice'] + $dash]],
			'publish with params'     => [['publish', ['type' => Extension::EVENT_SHARED, 'actorUserId' => 'alice', 'recipientUserId' => 'bob', 'extraParams' => ['recipient' => 'bob', 'role' => 'view', 'self' => true, 'actor' => 'mallory', 'dashboard' => 'Other']] + $dash]],
			'publish long message'    => [['publish', ['type' => Extension::EVENT_COMMENTED, 'actorUserId' => 'alice', 'recipientUserId' => 'bob', 'extraParams' => ['message' => $long]] + $dash]],
			'publish empty message'   => [['publish', ['type' => Extension::EVENT_COMMENTED, 'actorUserId' => 'alice', 'recipientUserId' => 'bob', 'extraParams' => ['message' => '']] + $dash]],
			'publish numeric message' => [['publish', ['type' => Extension::EVENT_COMMENTED, 'actorUserId' => 'alice', 'recipientUserId' => 'bob', 'extraParams' => ['message' => 42]] + $dash]],
			'publish unknown type'    => [['publish', ['type' => 'dashboard_viewed', 'actorUserId' => 'alice', 'recipientUserId' => 'bob'] + $dash]],
			'publish failing manager' => [['publish', ['type' => Extension::EVENT_DELETED, 'actorUserId' => 'alice', 'recipientUserId' => 'boom'] + $dash]],
			'reaction debounced'      => [
				['publish', ['type' => Extension::EVENT_REACTED, 'actorUserId' => 'alice', 'recipientUserId' => 'bob'] + $dash],
				['publish', ['type' => Extension::EVENT_REACTED, 'actorUserId' => 'alice', 'recipientUserId' => 'carol'] + $dash],
				['publish', ['type' => Extension::EVENT_REACTED, 'actorUserId' => 'dave', 'recipientUserId' => 'bob'] + $dash],
			],
			'recipients'              => [['publishToRecipients', ['type' => Extension::EVENT_SHARED, 'actorUserId' => 'alice', 'recipientUserIds' => ['bob', 'carol', 'alice', 'bob'], 'extraParams' => ['role' => 'edit', 'self' => 'x']] + $dash]],
			'recipients none'         => [['publishToRecipients', ['type' => Extension::EVENT_SHARED, 'actorUserId' => 'alice', 'recipientUserIds' => []] + $dash]],
			'recipients one fails'    => [['publishToRecipients', ['type' => Extension::EVENT_UPDATED, 'actorUserId' => 'alice', 'recipientUserIds' => ['boom', 'bob']] + $dash]],
			'group'                   => [['publishToGroup', ['type' => Extension::EVENT_UPDATED, 'actorUserId' => 'alice', 'groupId' => 'team', 'extraParams' => ['k' => 'v']] + $dash]],
			'group unknown'           => [['publishToGroup', ['type' => Extension::EVENT_UPDATED, 'actorUserId' => 'alice', 'groupId' => 'nobody'] + $dash]],
			'global'                  => [
				['publishGlobal', ['type' => Extension::EVENT_PUBLISHED, 'actorUserId' => 'bob', 'extraParams' => ['k' => 'v', 'self' => 'x']] + $dash],
				['publishGlobal', ['type' => Extension::EVENT_PUBLISHED, 'actorUserId' => 'bob'] + $dash],
				['publishGlobal', ['type' => Extension::EVENT_UPDATED, 'actorUserId' => 'bob'] + $dash],
				['publishGlobal', ['type' => Extension::EVENT_PUBLISHED, 'actorUserId' => 'bob', 'dashboardUuid' => 'uuid-2'] + $dash],
			],
		];
	}//end cases()

	/**
	 * Call one publisher method with the case's arguments.
	 *
	 * @param ActivityPublisher    $publisher The publisher under test.
	 * @param string               $method    The publisher method.
	 * @param array<string, mixed> $args      The case's named arguments.
	 *
	 * @return int|bool What the publisher returned.
	 */
	private static function call(ActivityPublisher $publisher, string $method, array $args): int|bool {
		$row = new ActivityRow(
			type: $args['type'],
			actorUserId: $args['actorUserId'],
			dashboard: new ActivitySubject(uuid: $args['dashboardUuid'], name: $args['dashboardName'], link: $args['dashboardLink']),
			extraParams: $args['extraParams'] ?? []
		);

		return match ($method) {
			'publish' => $publisher->publish(row: $row, recipientUserId: $args['recipientUserId']),
			'publishToRecipients' => $publisher->publishToRecipients(row: $row, recipientUserIds: $args['recipientUserIds']),
			'publishToGroup' => $publisher->publishToGroup(row: $row, groupId: $args['groupId']),
			'publishGlobal' => $publisher->publishGlobal(row: $row),
		};
	}//end call()

	/**
	 * Run one case on a fresh publisher and record what it did.
	 *
	 * @param list<array{0: string, 1: array<string, mixed>}> $calls The case's calls.
	 *
	 * @return array<string, mixed> The returns, rows and log lines.
	 */
	private function record(array $calls): array {
		$rows    = [];
		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturnCallback(static fn (): IEvent => new RecordingEvent());
		$manager->method('publish')->willReturnCallback(
			static function (IEvent $event) use (&$rows): void {
				if ($event->getAffectedUser() === 'boom') {
					throw new RuntimeException('down');
				}

				$rows[] = [
					'app'      => $event->getApp(),
					'type'     => $event->getType(),
					'author'   => $event->getAuthor(),
					'affected' => $event->getAffectedUser(),
					'subject'  => $event->getSubject(),
					'params'   => $event->getSubjectParameters(),
					'object'   => [$event->getObjectType(), $event->getObjectId(), $event->getObjectName()],
					'link'     => $event->getLink(),
					'message'  => $event->getMessage(),
					'stamped'  => $event->getTimestamp() > 0,
				];
			}
		);

		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$this->user(uid: 'carol'), $this->user(uid: 'alice'), $this->user(uid: 'erin')]);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturnCallback(static fn (string $gid): ?IGroup => $gid === 'team' ? $group : null);

		$users = $this->createMock(IUserManager::class);
		$users->method('callForAllUsers')->willReturnCallback(
			function (callable $callback): void {
				foreach (['alice', 'bob', 'boom'] as $uid) {
					$callback($this->user(uid: $uid));
				}
			}
		);

		$log    = [];
		$logger = $this->createMock(LoggerInterface::class);
		foreach (['warning', 'error', 'debug'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (string $message, array $context = []) use (&$log, $level): void {
					unset($context['exception']);
					$log[] = [$level, $message, $context];
				}
			);
		}

		$publisher = new ActivityPublisher(
			manager: $manager,
			groupManager: $groups,
			userManager: $users,
			tools: new PublisherTools(
				debounce: new DebounceHelper(clock: static fn (): int => 1000),
				logger: $logger
			)
		);

		$returns = [];
		foreach ($calls as [$method, $args]) {
			$returns[] = self::call(publisher: $publisher, method: $method, args: $args);
		}

		return ['returns' => $returns, 'rows' => $rows, 'log' => $log];
	}//end record()

	/**
	 * Build a user double.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUser
	 */
	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}//end user()

	/**
	 * Every case writes the recorded rows and returns the recorded counts.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		if (function_exists('apcu_clear_cache') === true) {
			apcu_clear_cache();
		}

		$actual = [];
		foreach (self::cases() as $case => $calls) {
			$actual[$case] = $this->record(calls: $calls);
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$expected = json_decode((string) file_get_contents(self::FIXTURE), true);
		foreach ($expected as $case => $result) {
			$this->assertSame($result, $actual[$case], $case);
		}

		$this->assertSame(array_keys($expected), array_keys($actual));
	}//end testRowsMatchTheRecordedFixture()
}//end class
