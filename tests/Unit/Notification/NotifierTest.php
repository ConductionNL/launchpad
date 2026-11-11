<?php

/**
 * Notifier rendering tests
 *
 * @category  Tests
 * @package   OCA\LaunchPad\Tests\Unit\Notification
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Notification;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Notification\Notifier;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\TestCase;

/**
 * Pins the exact setter calls Notifier::prepare makes for every subject.
 */
class NotifierTest extends TestCase {

	/**
	 * Recorded setter calls of the last prepared notification.
	 *
	 * @var list<string>
	 */
	private array $calls = [];

	/**
	 * Build a notifier whose mapper resolves id 7 to uuid "u-7".
	 *
	 * @return Notifier
	 */
	private function notifier(): Notifier {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			static fn (string $text, array $params = []): string => '['.vsprintf(str_replace('%1$s', '%s', str_replace('%2$s', '%s', $text)), $params).']'
		);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturn('https://host/apps/launchpad/');
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('find')->willReturnCallback(
			static function (int $id): Dashboard {
				if ($id !== 7) {
					throw new DoesNotExistException('gone');
				}

				$dashboard = new Dashboard();
				$dashboard->setUuid('u 7');
				return $dashboard;
			}
		);

		return new Notifier(l10nFactory: $factory, urlGenerator: $urls, dashboardMapper: $mapper);
	}//end notifier()

	/**
	 * Build a recording notification.
	 *
	 * @param string $subject  The subject.
	 * @param string $objectId The object id.
	 * @param array  $params   The subject parameters.
	 *
	 * @return INotification
	 */
	private function notification(string $subject, string $objectId, array $params): INotification {
		$this->calls = [];
		$n = $this->createMock(INotification::class);
		$n->method('getApp')->willReturn('launchpad');
		$n->method('getSubject')->willReturn($subject);
		$n->method('getObjectId')->willReturn($objectId);
		$n->method('getSubjectParameters')->willReturn($params);
		foreach (['setRichSubject', 'setParsedSubject', 'setRichMessage', 'setParsedMessage', 'setLink'] as $setter) {
			$n->method($setter)->willReturnCallback(
				function (...$args) use ($setter, $n): INotification {
					$this->calls[] = $setter.' '.json_encode($args);
					return $n;
				}
			);
		}

		return $n;
	}//end notification()

	/**
	 * Every subject renders its exact subject, message and link.
	 *
	 * @return void
	 */
	public function testEverySubjectRendersItsTextAndLink(): void {
		$cases = [
			['mentioned_in_comment', 'abc/1', ['alice'], [
				'setRichSubject ["[alice mentioned you in a dashboard comment]",[]]',
				'setParsedSubject ["[alice mentioned you in a dashboard comment]"]',
				'setRichMessage ["[Open the dashboard to read the comment]",[]]',
				'setParsedMessage ["[Open the dashboard to read the comment]"]',
				'setLink ["https:\/\/host\/apps\/launchpad\/?dashboard=abc%2F1"]',
			]],
			['mentioned_in_comment', '', [], [
				'setRichSubject ["[ mentioned you in a dashboard comment]",[]]',
				'setParsedSubject ["[ mentioned you in a dashboard comment]"]',
				'setRichMessage ["[Open the dashboard to read the comment]",[]]',
				'setParsedMessage ["[Open the dashboard to read the comment]"]',
				'setLink ["https:\/\/host\/apps\/launchpad\/"]',
			]],
			['dashboard_shared', '7', ['bob', 'Team', 'add_only'], [
				'setRichSubject ["[bob shared **Team** with you]",[]]',
				'setParsedSubject ["[bob shared Team with you]"]',
				'setRichMessage ["[Add-only access]",[]]',
				'setParsedMessage ["[Add-only access]"]',
				'setLink ["https:\/\/host\/apps\/launchpad\/?dashboard=u+7"]',
			]],
			['dashboard_shared', '8', ['bob', 'Team', 'odd'], [
				'setRichSubject ["[bob shared **Team** with you]",[]]',
				'setParsedSubject ["[bob shared Team with you]"]',
				'setRichMessage ["[Shared access]",[]]',
				'setParsedMessage ["[Shared access]"]',
				'setLink ["https:\/\/host\/apps\/launchpad\/"]',
			]],
			['dashboard_ownership_transferred', '', ['Ops'], [
				'setRichSubject ["[**Ops** is now yours]",[]]',
				'setParsedSubject ["[Ops is now yours]"]',
				'setRichMessage ["[Ownership transferred after the previous owner was removed]",[]]',
				'setParsedMessage ["[Ownership transferred after the previous owner was removed]"]',
				'setLink ["https:\/\/host\/apps\/launchpad\/"]',
			]],
			['dashboard_template_resynced', '7', ['Ops'], [
				'setRichSubject ["[An administrator updated your **Ops** dashboard]",[]]',
				'setParsedSubject ["[An administrator updated your Ops dashboard]"]',
				'setRichMessage ["[The dashboard template you received was updated by an administrator]",[]]',
				'setParsedMessage ["[The dashboard template you received was updated by an administrator]"]',
				'setLink ["https:\/\/host\/apps\/launchpad\/?dashboard=u+7"]',
			]],
		];
		foreach ($cases as [$subject, $objectId, $params, $expected]) {
			$notification = $this->notification(subject: $subject, objectId: $objectId, params: $params);
			$result = $this->notifier()->prepare(notification: $notification, languageCode: 'en');
			$this->assertSame($notification, $result);
			$this->assertSame($expected, $this->calls, $subject.' '.$objectId);
		}

		foreach (['full' => 'Full access', 'view_only' => 'View-only access'] as $level => $label) {
			$this->notifier()->prepare(
				notification: $this->notification(subject: 'dashboard_shared', objectId: '', params: ['b', 'T', $level]),
				languageCode: 'en'
			);
			$this->assertSame('setRichMessage ["['.$label.']",[]]', $this->calls[2]);
		}
	}//end testEverySubjectRendersItsTextAndLink()

	/**
	 * Unknown apps and subjects are refused with their own message.
	 *
	 * @return void
	 */
	public function testUnknownAppAndSubjectAreRefused(): void {
		$notification = $this->notification(subject: 'other', objectId: '', params: []);
		try {
			$this->notifier()->prepare(notification: $notification, languageCode: 'en');
			$this->fail('expected refusal');
		} catch (UnknownNotificationException $e) {
			$this->assertSame('Unknown subject: other', $e->getMessage());
			$this->assertSame([], $this->calls);
		}

		$foreign = $this->createMock(INotification::class);
		$foreign->method('getApp')->willReturn('files');
		$this->expectException(UnknownNotificationException::class);
		$this->expectExceptionMessage('Unknown app: files');
		$this->notifier()->prepare(notification: $foreign, languageCode: 'en');
	}//end testUnknownAppAndSubjectAreRefused()
}//end class
