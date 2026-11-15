<?php

/**
 * LaunchPadSearchProviderEdgeCaseTest
 *
 * Pins the search provider's edges that LaunchPadSearchProviderTest leaves
 * open: dashboards without id or uuid, failing placement and metadata
 * lookups, text widgets whose content cannot match, empty metadata values
 * and the exact result links.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Search
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Search;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Search\LaunchPadSearchProvider;
use OCA\LaunchPad\Search\SearchLinks;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\MetadataService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Edge cases of the LaunchPad unified-search provider.
 *
 * @covers \OCA\LaunchPad\Search\LaunchPadSearchProvider
 * @uses   \OCA\LaunchPad\Search\SearchBuckets
 * @uses   \OCA\LaunchPad\Search\SearchLinks
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 */
class LaunchPadSearchProviderEdgeCaseTest extends TestCase {

	private const BASE = 'https://nc.example/apps/launchpad/';

	/** @var DashboardService&MockObject */
	private $dashboardService;
	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;
	/** @var MetadataService&MockObject */
	private $metadataService;

	private LaunchPadSearchProvider $provider;

	/**
	 * Provider over mocked services; l10n formats its parameters, URLs are fixed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->metadataService = $this->createMock(MetadataService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $params = []): string => vsprintf($text, $params)
		);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('launchpad.page.index')->willReturn(self::BASE);
		$urls->method('imagePath')->willReturn('/apps/launchpad/img/app.svg');
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'https://nc.example' . $url);

		$this->provider = new LaunchPadSearchProvider(
			dashboardService: $this->dashboardService,
			placementMapper: $this->placementMapper,
			metadataService: $this->metadataService,
			links: new SearchLinks(l10nFactory: $factory, urlGenerator: $urls)
		);
	}//end setUp()

	/**
	 * A dashboard entity.
	 *
	 * @param int|null    $id          The id.
	 * @param string|null $uuid        The uuid.
	 * @param string      $name        The name.
	 * @param string      $description The description.
	 *
	 * @return Dashboard
	 */
	private function dashboard(?int $id, ?string $uuid, string $name, string $description = ''): Dashboard {
		$dashboard = new Dashboard();
		if ($id !== null) {
			$dashboard->setId($id);
		}

		$dashboard->setUuid($uuid);
		$dashboard->setName($name);
		$dashboard->setDescription($description);
		return $dashboard;
	}//end dashboard()

	/**
	 * A text-widget placement.
	 *
	 * @param int         $id          The placement id.
	 * @param string|null $styleConfig The raw style config.
	 *
	 * @return WidgetPlacement
	 */
	private function placement(int $id, ?string $styleConfig): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setStyleConfig($styleConfig);
		return $placement;
	}//end placement()

	/**
	 * Style config of a text widget with the given text.
	 *
	 * @param mixed $text The text value.
	 *
	 * @return string
	 */
	private static function textWidget(mixed $text): string {
		return (string)json_encode(['type' => 'text', 'content' => ['text' => $text]]);
	}//end textWidget()

	/**
	 * Search as alice for the term, returning each entry's title, subline and link.
	 *
	 * @param array<int, Dashboard> $dashboards The visible dashboards.
	 * @param string                $term       The search term.
	 *
	 * @return array<int, array{0: string, 1: string, 2: string}>
	 */
	private function search(array $dashboards, string $term): array {
		$this->dashboardService->method('getVisibleToUser')->with('alice')->willReturn(
			array_map(static fn (Dashboard $d): array => ['dashboard' => $d, 'source' => Dashboard::SOURCE_USER], $dashboards)
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn($term);

		$rows = [];
		foreach ($this->provider->search($user, $query)->jsonSerialize()['entries'] as $entry) {
			$data = $entry->jsonSerialize();
			$rows[] = [$data['title'], $data['subline'], $data['resourceUrl']];
		}

		return $rows;
	}//end search()

	/**
	 * A dashboard without id or uuid is matched by name only, links to the
	 * app root and triggers no placement or metadata lookup.
	 *
	 * @return void
	 */
	public function testAnUnsavedDashboardIsMatchedByNameOnly(): void {
		$this->placementMapper->expects($this->never())->method('findByDashboardId');
		$this->metadataService->expects($this->never())->method('getMetadataForDashboard');

		$rows = $this->search(dashboards: [$this->dashboard(id: null, uuid: null, name: 'Draft board')], term: 'draft');

		$this->assertSame([['Draft board', 'LaunchPad dashboard', self::BASE]], $rows);
	}//end testAnUnsavedDashboardIsMatchedByNameOnly()

	/**
	 * Failing placement and metadata lookups drop those buckets but keep the
	 * dashboard match, with its description as subline and an encoded link.
	 *
	 * @return void
	 */
	public function testFailingLookupsKeepTheDashboardMatch(): void {
		$this->placementMapper->method('findByDashboardId')->willThrowException(new RuntimeException('db'));
		$this->metadataService->method('getMetadataForDashboard')->willThrowException(new RuntimeException('db'));

		$rows = $this->search(
			dashboards: [$this->dashboard(id: 3, uuid: 'a b/c', name: 'Budget', description: '  Yearly budget  ')],
			term: 'budget'
		);

		$this->assertSame([['Budget', 'Yearly budget', self::BASE . '#dashboard/a%20b%2Fc']], $rows);
	}//end testFailingLookupsKeepTheDashboardMatch()

	/**
	 * Text widgets whose config or content cannot hold the term never match;
	 * only the real text widget does.
	 *
	 * @return void
	 */
	public function testUnmatchableWidgetConfigsAreSkipped(): void {
		$this->placementMapper->method('findByDashboardId')->with(4)->willReturn([
			$this->placement(id: 1, styleConfig: null),
			$this->placement(id: 2, styleConfig: ''),
			$this->placement(id: 3, styleConfig: '"term"'),
			$this->placement(id: 4, styleConfig: (string)json_encode(['type' => 'text', 'content' => 'term'])),
			$this->placement(id: 5, styleConfig: self::textWidget(text: '')),
			$this->placement(id: 6, styleConfig: self::textWidget(text: ['term'])),
			$this->placement(id: 7, styleConfig: (string)json_encode(['type' => 'image', 'content' => ['text' => 'term']])),
			$this->placement(id: 8, styleConfig: self::textWidget(text: '<b>Term</b> &amp; more')),
		]);
		$this->metadataService->method('getMetadataForDashboard')->willReturn([]);

		$rows = $this->search(dashboards: [$this->dashboard(id: 4, uuid: 'u4', name: 'Board')], term: 'term &');

		$this->assertSame([['Board', 'Widget content on Board', self::BASE . '#dashboard/u4;widget=8']], $rows);
	}//end testUnmatchableWidgetConfigsAreSkipped()

	/**
	 * A widget link without a dashboard uuid uses a query string, and a
	 * placement without id links to the dashboard itself.
	 *
	 * @return void
	 */
	public function testWidgetLinksWithoutUuidOrPlacementId(): void {
		$this->placementMapper->method('findByDashboardId')->willReturnMap([
			[6, [$this->placement(id: 9, styleConfig: self::textWidget(text: 'agenda'))]],
			[7, [$this->placement(id: 0, styleConfig: self::textWidget(text: 'agenda'))]],
		]);
		$this->metadataService->method('getMetadataForDashboard')->willReturn([]);

		$rows = $this->search(
			dashboards: [
				$this->dashboard(id: 6, uuid: null, name: 'No uuid'),
				$this->dashboard(id: 7, uuid: 'u7', name: 'Has uuid'),
			],
			term: 'agenda'
		);

		$this->assertSame(
			[
				['No uuid', 'Widget content on No uuid', self::BASE . '?widget=9'],
				['Has uuid', 'Widget content on Has uuid', self::BASE . '#dashboard/u7'],
			],
			$rows
		);
	}//end testWidgetLinksWithoutUuidOrPlacementId()

	/**
	 * Empty metadata values are skipped and non-string values are matched
	 * by their string form.
	 *
	 * @return void
	 */
	public function testMetadataValuesAreMatchedAsStrings(): void {
		$this->placementMapper->method('findByDashboardId')->willReturn([]);
		$this->metadataService->method('getMetadataForDashboard')->with('u8')->willReturn(
			['empty' => '', 'none' => null, 'budget' => 42000, 'code' => 'B-42']
		);

		$rows = $this->search(dashboards: [$this->dashboard(id: 8, uuid: 'u8', name: 'Finance')], term: '42');

		$this->assertSame(
			[
				['Finance', 'Metadata: budget = 42000', self::BASE . '#dashboard/u8'],
				['Finance', 'Metadata: code = B-42', self::BASE . '#dashboard/u8'],
			],
			$rows
		);
	}//end testMetadataValuesAreMatchedAsStrings()
}//end class
