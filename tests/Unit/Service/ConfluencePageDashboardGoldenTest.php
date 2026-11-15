<?php

/**
 * Confluence page dashboard golden test
 *
 * Pins the dashboard row that the Confluence importer creates for one
 * page, through a real DashboardFactory, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\ConfluenceDashboardWriters;
use OCA\LaunchPad\Service\ConfluencePageReaders;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\Confluence\ArchiveParser;
use OCA\LaunchPad\Service\Confluence\HtmlSanitizer;
use OCA\LaunchPad\Service\Confluence\LinkRewriter;
use OCA\LaunchPad\Service\Confluence\MacroRenderer;
use OCA\LaunchPad\Service\Confluence\ParsedPage;
use OCA\LaunchPad\Service\ConfluenceImportService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The importer's page dashboards, case by case.
 *
 * @covers \OCA\LaunchPad\Service\ConfluenceImportService
 * @uses   \OCA\LaunchPad\Service\DashboardFactory
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedPage
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\SlugGenerator
 * @uses   \OCA\LaunchPad\Service\ConfluencePageReaders
 * @uses   \OCA\LaunchPad\Service\ConfluenceDashboardWriters
 */
class ConfluencePageDashboardGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/confluence-page-dashboard.json';

	/**
	 * Every page gets the recorded dashboard row.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$actual = [];
		foreach ([['Root page', 0, null], ['Child page', 3, 'p-1']] as [$title, $sort, $parent]) {
			$rows   = [];
			$mapper = $this->createMock(DashboardMapper::class);
			$mapper->method('insert')->willReturnCallback(
				static function (Dashboard $dashboard) use (&$rows): Dashboard {
					$row = $dashboard->jsonSerialize();
					unset($row['uuid'], $row['createdAt'], $row['updatedAt']);
					$rows[] = $row;
					$dashboard->setId(7);
					return $dashboard;
				}
			);

			$service = new ConfluenceImportService(
				readers: new ConfluencePageReaders(
					parser: $this->createMock(ArchiveParser::class),
					sanitizer: $this->createMock(HtmlSanitizer::class),
					macroRenderer: $this->createMock(MacroRenderer::class),
					linkRewriter: $this->createMock(LinkRewriter::class)
				),
				writers: new ConfluenceDashboardWriters(
					dashboardFactory: new DashboardFactory(),
					treeService: $this->createMock(DashboardTreeService::class),
					dashboardMapper: $mapper,
					placementMapper: $this->createMock(WidgetPlacementMapper::class)
				),
				db: $this->createMock(IDBConnection::class),
				logger: $this->createMock(LoggerInterface::class)
			);

			$page = new ParsedPage(relPath: 'p.html', pageId: '42', title: $title, body: '', sortOrder: $sort);
			(new ReflectionMethod(ConfluenceImportService::class, 'createDashboardForPage'))->invoke($service, $page, $parent, 'erin');
			$actual[$title] = $rows;
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, self::compact(rows: $actual));
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents(self::FIXTURE))), json_encode($actual));
	}//end testRowsMatchTheRecordedFixture()

	/**
	 * The fixture text: one line per case.
	 *
	 * @param array<string, mixed> $rows The recorded cases.
	 *
	 * @return string
	 */
	private static function compact(array $rows): string {
		$lines = [];
		foreach ($rows as $case => $row) {
			$lines[] = json_encode((string) $case, JSON_UNESCAPED_UNICODE).': '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		return "{\n".implode(",\n", $lines)."\n}\n";
	}//end compact()
}//end class
