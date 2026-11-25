<?php

/**
 * SummaryMailContent Test
 *
 * personal-settings REQ-PSET-002: the summary lists the announcements
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
	public function testItListsWhatWasPublishedAndWhatChangedSinceTheLastSummary(): void {
		$announcements = $this->createMock(AnnouncementServiceInterface::class);
		$announcements->expects($this->once())->method('visibleTo')->with('pieter')->willReturn(
			[
				$this->announcement('Kerstborrel', '2026-11-24 15:00'),
				$this->announcement('Old news', '2026-11-20 09:00'),
			]
		);

		$dashboards = $this->getMockBuilder(DashboardService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getVisibleToUser'])
			->getMock();
		$dashboards->expects($this->once())->method('getVisibleToUser')->with('pieter')->willReturn(
			[
				['dashboard' => $this->dashboard('uuid-team', 'Team Vergunningen'), 'source' => 'shared'],
				['dashboard' => $this->dashboard('uuid-quiet', 'Quiet'), 'source' => 'own'],
			]
		);

		$versions = $this->getMockBuilder(DashboardVersionMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findLatestByDashboard'])
			->getMock();
		$versions->method('findLatestByDashboard')->willReturnCallback(
			fn (string $uuid): array => match ($uuid) {
				// Newest first, as the mapper reads.
				'uuid-team' => [
					$this->version('Widget Kantinemenu removed', '2026-11-24 16:00:00'),
					$this->version('', '2026-11-24 14:00:00'),
					$this->version('Widget Teamagenda added', '2026-11-24 10:00:00'),
					$this->version('Widget Weer added', '2026-11-23 10:00:00'),
				],
				default => [$this->version('Renamed', '2026-11-01 10:00:00')],
			}
		);

		$lines = (new SummaryMailContent($announcements, $dashboards, $versions))
			->collect('pieter', new DateTimeImmutable('2026-11-24 07:30'));

		$this->assertSame(['Kerstborrel'], $lines['announcements']);
		$this->assertSame(
			['Team Vergunningen: Widget Teamagenda added', 'Team Vergunningen: Widget Kantinemenu removed'],
			$lines['changes']
		);
	}//end testItListsWhatWasPublishedAndWhatChangedSinceTheLastSummary()

	private function announcement(string $title, string $publishAt): Announcement {
		$announcement = new Announcement();
		$announcement->setTitle($title);
		$announcement->setPublishAt(new DateTime($publishAt));
		return $announcement;
	}//end announcement()

	private function dashboard(string $uuid, string $name): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		return $dashboard;
	}//end dashboard()

	private function version(string $note, string $createdAt): DashboardVersion {
		$version = new DashboardVersion();
		$version->setNote($note === '' ? null : $note);
		$version->setCreatedAt($createdAt);
		return $version;
	}//end version()
}//end class
