<?php

/**
 * NotifierScheduledExportTest
 *
 * The notification of a failed scheduled export (scheduled-exports
 * REQ-SCH-006): subject with the export name, the reason as the message,
 * and a link to the app.
 *
 * @category  Test
 * @package   Unit\Notification
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Notification;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Notification\NotificationCopy;
use OCA\LaunchPad\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Notification\Notifier
 * @uses   \OCA\LaunchPad\Notification\NotificationCopy
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */
class NotifierScheduledExportTest extends TestCase {

	/**
	 * Subject, message and link of a failed export.
	 *
	 * @return void
	 */
	public function testFailedExportCopy(): void {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('launchpad.page.index')->willReturn('https://host/apps/launchpad/');
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->expects($this->never())->method('find');

		$record = [];
		$n      = $this->createMock(INotification::class);
		$n->method('getApp')->willReturn('launchpad');
		$n->method('getSubject')->willReturn('scheduled_export_failed');
		$n->method('getObjectId')->willReturn('0b9c4d1e-0000-4000-8000-000000000001');
		$n->method('getSubjectParameters')->willReturn(['Weekly', 'webhook: HTTP 503']);
		foreach (['setRichSubject' => 'rich', 'setParsedSubject' => 'subject', 'setParsedMessage' => 'message', 'setLink' => 'link'] as $setter => $key) {
			$n->method($setter)->willReturnCallback(function (string $value) use (&$record, $key, $n): INotification {
				$record[$key] = $value;
				return $n;
			});
		}

		$n->method('setRichMessage')->willReturn($n);

		(new Notifier(l10nFactory: $factory, urlGenerator: $urls, dashboardMapper: $mapper))->prepare(notification: $n, languageCode: 'en');

		$this->assertSame('The scheduled export **Weekly** failed', $record['rich']);
		$this->assertSame('The scheduled export Weekly failed', $record['subject']);
		$this->assertSame('webhook: HTTP 503', $record['message']);
		$this->assertSame('https://host/apps/launchpad/', $record['link']);
		$this->assertTrue(class_exists(NotificationCopy::class));
	}//end testFailedExportCopy()
}//end class
