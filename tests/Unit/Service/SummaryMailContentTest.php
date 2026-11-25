<?php

/**
 * SummaryMailContent Test
 *
 * Personal-settings REQ-PSET-002: the summary lists the announcements
 * published for the person since the last summary and, per dashboard the
 * person can open, the "What changed" notes since then.
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
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use DateTimeImmutable;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\SummaryMailContent;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SummaryMailContent.
 */
class SummaryMailContentTest extends TestCase {

	/**
	 * Only what came after the last summary, oldest first per dashboard, notes only.
	 *
	 * @return void
	 */
	public function testItListsWhatWasPublishedAndWhatChangedSinceTheLastSummary(): void {
		$announcements = $this->createMock(originalClassName: AnnouncementServiceInterface::class);
		$announcements->expects($this->once())->method('visibleTo')->with('pieter')->willReturn(
			[
				$this->announcement(title: 'Kerstborrel', publishAt: '2026-11-24 15:00'),
				$this->announcement(title: 'Old news', publishAt: '2026-11-20 09:00'),
			]
		);

		$dashboards = $this->getMockBuilder(className: DashboardService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getVisibleToUser'])
			->getMock();
		$dashboards->expects($this->once())->method('getVisibleToUser')->with('pieter')->willReturn(
			[
				['dashboard' => $this->dashboard(uuid: 'uuid-team', name: 'Team Vergunningen'), 'source' => 'shared'],
				['dashboard' => $this->dashboard(uuid: 'uuid-quiet', name: 'Quiet'), 'source' => 'own'],
			]
		);

		$versions = $this->getMockBuilder(className: DashboardVersionMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findLatestByDashboard'])
			->getMock();
		$versions->method('findLatestByDashboard')->willReturnCallback(
			fn (string $uuid): array => match ($uuid) {
				// Newest first, as the mapper reads.
				'uuid-team' => [
					$this->version(note: 'Widget Kantinemenu removed', createdAt: '2026-11-24 16:00:00'),
					$this->version(note: '', createdAt: '2026-11-24 14:00:00'),
					$this->version(note: 'Widget Teamagenda added', createdAt: '2026-11-24 10:00:00'),
					$this->version(note: 'Widget Weer added', createdAt: '2026-11-23 10:00:00'),
				],
				default => [$this->version(note: 'Renamed', createdAt: '2026-11-01 10:00:00')],
			}
		);

		$content = new SummaryMailContent(announcements: $announcements, dashboards: $dashboards, versions: $versions);
		$lines = $content->collect(userId: 'pieter', since: new DateTimeImmutable(datetime: '2026-11-24 07:30'));

		$this->assertSame(expected: ['Kerstborrel'], actual: $lines['announcements']);
		$this->assertSame(
			expected: ['Team Vergunningen: Widget Teamagenda added', 'Team Vergunningen: Widget Kantinemenu removed'],
			actual: $lines['changes']
		);
	}//end testItListsWhatWasPublishedAndWhatChangedSinceTheLastSummary()

	/**
	 * An announcement.
	 *
	 * @param string $title     Its title.
	 * @param string $publishAt When it went live.
	 *
	 * @return Announcement
	 */
	private function announcement(string $title, string $publishAt): Announcement {
		$announcement = new Announcement();
		$announcement->setTitle($title);
		$announcement->setPublishAt(new DateTime(datetime: $publishAt));
		return $announcement;
	}//end announcement()

	/**
	 * A dashboard.
	 *
	 * @param string $uuid Its uuid.
	 * @param string $name Its name.
	 *
	 * @return Dashboard
	 */
	private function dashboard(string $uuid, string $name): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		return $dashboard;
	}//end dashboard()

	/**
	 * A dashboard version.
	 *
	 * @param string $note      Its What changed note, empty for none.
	 * @param string $createdAt When it was stored.
	 *
	 * @return DashboardVersion
	 */
	private function version(string $note, string $createdAt): DashboardVersion {
		$version = new DashboardVersion();
		if ($note !== '') {
			$version->setNote($note);
		}

		$version->setCreatedAt($createdAt);
		return $version;
	}//end version()
}//end class
