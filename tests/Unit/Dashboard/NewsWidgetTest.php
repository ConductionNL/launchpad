<?php

/**
 * NewsWidget Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Dashboard
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Dashboard;

use OCA\LaunchPad\Dashboard\NewsWidget;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IIconWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The `launchpad_news` Nextcloud dashboard widget.
 *
 * @covers \OCA\LaunchPad\Dashboard\NewsWidget
 * @covers \OCA\LaunchPad\Dashboard\LaunchPadApiWidget
 * @uses \OCA\LaunchPad\Db\WidgetPlacement
 * @uses \OCA\LaunchPad\Service\NewsWidgetService
 */
class NewsWidgetTest extends TestCase {

	private WidgetPlacementMapper&MockObject $mapper;

	private NewsWidgetService&MockObject $news;

	private NewsWidget $widget;

	protected function setUp(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => 'T:' . $text);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(static fn (string $app, string $file): string => '/apps/' . $app . '/img/' . $file);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'https://cloud.example' . $url);
		$urls->method('linkToRouteAbsolute')->willReturnCallback(static fn (string $route): string => 'https://cloud.example/route/' . $route);

		$this->mapper = $this->createMock(WidgetPlacementMapper::class);
		// Config extraction runs for real; only the network and the metadata lookup are doubled.
		$this->news = $this->getMockBuilder(NewsWidgetService::class)
			->disableOriginalConstructor()
			->onlyMethods(['fetchAndMergeFeeds', 'checkMetadataFilter'])
			->getMock();

		$this->widget = new NewsWidget(l10n: $l10n, urlGenerator: $urls, placementMapper: $this->mapper, news: $this->news);
	}//end setUp()

	/**
	 * A placement on a dashboard with the given content.
	 *
	 * @param int   $dashboardId The dashboard.
	 * @param array $content     The content JSON.
	 *
	 * @return WidgetPlacement
	 */
	private static function placement(int $dashboardId, array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId('news');
		$placement->setContent(json_encode($content));
		return $placement;
	}//end placement()

	/**
	 * The widget declares rows as its first-paint placeholder.
	 *
	 * @return void
	 */
	public function testItDeclaresRowsAsItsPlaceholder(): void {
		$this->assertSame(expected: 'rows', actual: $this->widget->getPlaceholder());
	}

	public function testRegistryMetadata(): void {
		$this->assertInstanceOf(IAPIWidgetV2::class, $this->widget);
		$this->assertInstanceOf(IIconWidget::class, $this->widget);
		$this->assertSame('launchpad_news', $this->widget->getId());
		$this->assertSame('T:News', $this->widget->getTitle());
		$this->assertSame('https://cloud.example/apps/launchpad/img/news-dark.svg', $this->widget->getIconUrl());
		$this->assertSame('https://cloud.example/route/launchpad.page.index', $this->widget->getUrl());
	}//end testRegistryMetadata()

	public function testItemsMergeTheDistinctFeedsOfTheUsersPlacementsInOneFetch(): void {
		$this->mapper->expects($this->once())->method('findOwnedByUserAndWidgetId')
			->with('alice', 'news')
			->willReturn([
				self::placement(1, ['feedUrls' => ['https://a.example/rss', 'https://b.example/rss']]),
				self::placement(2, ['feedUrls' => ['https://b.example/rss', 'ftp://c.example/rss']]),
			]);
		$this->news->expects($this->once())->method('fetchAndMergeFeeds')
			->with(['https://a.example/rss', 'https://b.example/rss'], 5)
			->willReturn([
				'items' => [
					['guid' => 'g1', 'title' => 'Budget', 'link' => 'https://a.example/budget', 'sourceUrl' => 'https://a.example/rss', 'sourceTitle' => 'Feed A', 'thumbnailUrl' => 'https://a.example/t.png'],
					['guid' => 'g2', 'title' => 'Sneaky', 'link' => 'javascript:alert(1)', 'sourceUrl' => 'https://b.example/rss', 'sourceTitle' => 'Feed B', 'thumbnailUrl' => null],
				],
				'feedsFailed' => 0,
				'failedUrls' => [],
			]);

		$result = $this->widget->getItemsV2(userId: 'alice', limit: 5);
		$items = $result->getItems();

		$this->assertCount(2, $items);
		$this->assertSame('Budget', $items[0]->getTitle());
		$this->assertSame('Feed A', $items[0]->getSubtitle());
		$this->assertSame('https://a.example/budget', $items[0]->getLink());
		$this->assertSame('https://a.example/t.png', $items[0]->getIconUrl());
		$this->assertSame('g1', $items[0]->getSinceId());
		// A non-web article link falls back to its feed; no thumbnail falls back to the widget icon.
		$this->assertSame('https://b.example/rss', $items[1]->getLink());
		$this->assertSame('https://cloud.example/apps/launchpad/img/news-dark.svg', $items[1]->getIconUrl());
		$this->assertSame('T:No news articles right now.', $result->getEmptyContentMessage());
	}//end testItemsMergeTheDistinctFeedsOfTheUsersPlacementsInOneFetch()

	public function testOnlyTheRequestingUsersOwnPlacementsAreRead(): void {
		$this->mapper->expects($this->once())->method('findOwnedByUserAndWidgetId')->with('bob', 'news')->willReturn([]);
		$this->mapper->expects($this->never())->method('findByWidgetId');
		$this->mapper->expects($this->never())->method('findAll');
		$this->mapper->expects($this->never())->method('find');

		$this->widget->getItemsV2(userId: 'bob');
	}//end testOnlyTheRequestingUsersOwnPlacementsAreRead()

	public function testAPlacementWhoseMetadataFilterFailsContributesNoFeeds(): void {
		$filter = ['fieldKey' => 'department', 'value' => 'HR'];
		$this->mapper->method('findOwnedByUserAndWidgetId')->willReturn([
			self::placement(7, ['feedUrls' => ['https://hr.example/rss'], 'metadataFilter' => $filter]),
			self::placement(8, ['feedUrls' => ['https://all.example/rss']]),
		]);
		$this->news->expects($this->once())->method('checkMetadataFilter')->with(7, $filter)->willReturn(false);
		$this->news->expects($this->once())->method('fetchAndMergeFeeds')
			->with(['https://all.example/rss'], 7)
			->willReturn(['items' => [], 'feedsFailed' => 0, 'failedUrls' => []]);

		$result = $this->widget->getItemsV2(userId: 'alice');

		$this->assertSame([], $result->getItems());
		$this->assertSame('T:No news articles right now.', $result->getEmptyContentMessage());
	}//end testAPlacementWhoseMetadataFilterFailsContributesNoFeeds()

	public function testNoSourcesExplainsHowToAddOneWithoutFetching(): void {
		$this->mapper->method('findOwnedByUserAndWidgetId')->willReturn([self::placement(1, ['feedUrls' => []])]);
		$this->news->expects($this->never())->method('fetchAndMergeFeeds');

		$result = $this->widget->getItemsV2(userId: 'alice');

		$this->assertSame([], $result->getItems());
		$this->assertSame(
			'T:Add a news widget with a feed to one of your dashboards to see its articles here.',
			$result->getEmptyContentMessage()
		);
	}//end testNoSourcesExplainsHowToAddOneWithoutFetching()

	public function testTheLimitIsCappedAtFifty(): void {
		$this->mapper->method('findOwnedByUserAndWidgetId')->willReturn([self::placement(1, ['feedUrls' => ['https://a.example/rss']])]);
		$this->news->expects($this->once())->method('fetchAndMergeFeeds')
			->with(['https://a.example/rss'], 50)
			->willReturn(['items' => [], 'feedsFailed' => 0, 'failedUrls' => []]);

		$this->widget->getItemsV2(userId: 'alice', limit: 500);
	}//end testTheLimitIsCappedAtFifty()
}//end class
