<?php

/**
 * NotifierLocaleTest
 *
 * Pins what NotifierTest leaves open: a notification is rendered in the
 * recipient's language, the notifier's own name is translated, and a
 * dashboard that still exists but has no uuid links to the app root.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Notification
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Notification;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

/**
 * Language, name and uuid-less links of the notifier.
 *
 * @covers \OCA\LaunchPad\Notification\Notifier
 * @uses   \OCA\LaunchPad\Notification\NotificationCopy
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class NotifierLocaleTest extends TestCase {

	/**
	 * A translator that prefixes its language.
	 *
	 * @param string $lang The language.
	 *
	 * @return IL10N
	 */
	private function translator(string $lang): IL10N {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			static fn (string $text, array $params = []): string => $lang . ':' . vsprintf($text, $params)
		);
		return $l;
	}//end translator()

	/**
	 * The notifier over a factory that hands out language-prefixing translators.
	 *
	 * @param DashboardMapper|null $mapper The dashboard mapper.
	 *
	 * @return Notifier
	 */
	private function notifier(?DashboardMapper $mapper = null): Notifier {
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturnCallback(
			fn (string $app, ?string $lang = null): IL10N => $this->translator(lang: ($app . '/' . ($lang ?? 'user')))
		);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('launchpad.page.index')->willReturn('https://host/apps/launchpad/');

		return new Notifier(
			l10nFactory: $factory,
			urlGenerator: $urls,
			dashboardMapper: ($mapper ?? $this->createMock(DashboardMapper::class))
		);
	}//end notifier()

	/**
	 * A transferred-dashboard notification for object id 5, recording its subject and link.
	 *
	 * @param array<string, string> $record Receives the parsed subject and link.
	 *
	 * @return INotification
	 */
	private function transferred(array &$record): INotification {
		$n = $this->createMock(INotification::class);
		$n->method('getApp')->willReturn('launchpad');
		$n->method('getSubject')->willReturn('dashboard_ownership_transferred');
		$n->method('getObjectId')->willReturn('5');
		$n->method('getSubjectParameters')->willReturn(['Ops']);
		$n->method('setParsedSubject')->willReturnCallback(function (string $s) use (&$record, $n): INotification {
			$record['subject'] = $s;
			return $n;
		});
		$n->method('setLink')->willReturnCallback(function (string $link) use (&$record, $n): INotification {
			$record['link'] = $link;
			return $n;
		});
		foreach (['setRichSubject', 'setRichMessage', 'setParsedMessage'] as $setter) {
			$n->method($setter)->willReturn($n);
		}

		return $n;
	}//end transferred()

	/**
	 * The text is rendered in the language the notification is prepared for.
	 *
	 * @return void
	 */
	public function testTheRecipientsLanguageIsUsed(): void {
		$record = [];
		$this->notifier()->prepare(notification: $this->transferred(record: $record), languageCode: 'nl');

		$this->assertSame('launchpad/nl:Ops is now yours', $record['subject']);
	}//end testTheRecipientsLanguageIsUsed()

	/**
	 * A dashboard found without a uuid links to the app root.
	 *
	 * @return void
	 */
	public function testAFoundDashboardWithoutUuidLinksToTheRoot(): void {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->expects($this->once())->method('find')->with(5)->willReturn(new Dashboard());

		$record = [];
		$this->notifier(mapper: $mapper)->prepare(notification: $this->transferred(record: $record), languageCode: 'en');

		$this->assertSame('https://host/apps/launchpad/', $record['link']);
	}//end testAFoundDashboardWithoutUuidLinksToTheRoot()

	/**
	 * The notifier registers under the app id with a translated name.
	 *
	 * @return void
	 */
	public function testIdAndTranslatedName(): void {
		$notifier = $this->notifier();

		$this->assertSame(['launchpad', 'launchpad/user:LaunchPad'], [$notifier->getID(), $notifier->getName()]);
	}//end testIdAndTranslatedName()
}//end class
