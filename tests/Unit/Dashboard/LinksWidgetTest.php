<?php

/**
 * LinksWidget Test
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

use OCA\LaunchPad\Dashboard\LinksWidget;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IIconWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The `launchpad_links` Nextcloud dashboard widget.
 *
 * @covers \OCA\LaunchPad\Dashboard\LinksWidget
 * @covers \OCA\LaunchPad\Dashboard\LaunchPadApiWidget
 * @uses \OCA\LaunchPad\Db\WidgetPlacement
 */
class LinksWidgetTest extends TestCase {

	private const ICON = 'https://cloud.example/apps/launchpad/img/links-dark.svg';

	private WidgetPlacementMapper&MockObject $mapper;

	private LinksWidget $widget;

	protected function setUp(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => 'T:' . $text);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(static fn (string $app, string $file): string => '/apps/' . $app . '/img/' . $file);
		$urls->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'https://cloud.example' . $url);
		$urls->method('linkToRouteAbsolute')->willReturnCallback(static fn (string $route): string => 'https://cloud.example/route/' . $route);

		$this->mapper = $this->createMock(WidgetPlacementMapper::class);
		$this->widget = new LinksWidget(l10n: $l10n, urlGenerator: $urls, placementMapper: $this->mapper);
	}//end setUp()

	/**
	 * A links placement with the given sections.
	 *
	 * @param array $sections The sections.
	 *
	 * @return WidgetPlacement
	 */
	private static function placement(array $sections): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setWidgetId('links');
		$placement->setContent(json_encode(['sections' => $sections]));
		return $placement;
	}//end placement()

	public function testRegistryMetadata(): void {
		$this->assertInstanceOf(IAPIWidgetV2::class, $this->widget);
		$this->assertInstanceOf(IIconWidget::class, $this->widget);
		$this->assertSame('launchpad_links', $this->widget->getId());
		$this->assertSame('T:Links', $this->widget->getTitle());
		$this->assertSame(self::ICON, $this->widget->getIconUrl());
		$this->assertSame('https://cloud.example/route/launchpad.page.index', $this->widget->getUrl());
	}//end testRegistryMetadata()

	public function testItemsAreTheUsersLinksInOrderWithIconsAndSubtitles(): void {
		$this->mapper->expects($this->once())->method('findOwnedByUserAndWidgetId')
			->with('alice', 'links')
			->willReturn([
				self::placement([
					['title' => 'Work', 'links' => [
						['label' => 'Intranet', 'url' => 'https://intra.example', 'icon' => 'https://intra.example/i.png', 'description' => 'Our intranet'],
						['label' => 'Files', 'url' => '/apps/files', 'icon' => 'icon-files'],
					]],
				]),
				self::placement([
					['title' => 'More', 'links' => [
						['label' => 'Intranet again', 'url' => 'https://intra.example'],
						['label' => 'Evil', 'url' => 'javascript:alert(1)'],
						['label' => 'Relative escape', 'url' => '//evil.example'],
						['label' => '', 'url' => 'https://docs.example/'],
					]],
				]),
			]);

		$items = $this->widget->getItemsV2(userId: 'alice')->getItems();

		$this->assertSame(['Intranet', 'Files', 'https://docs.example/'], array_map(static fn ($i) => $i->getTitle(), $items));
		$this->assertSame(['https://intra.example', 'https://cloud.example/apps/files', 'https://docs.example/'], array_map(static fn ($i) => $i->getLink(), $items));
		$this->assertSame(['Our intranet', 'Work', 'More'], array_map(static fn ($i) => $i->getSubtitle(), $items));
		// A URL icon is used; an icon name or none falls back to the widget icon.
		$this->assertSame(['https://intra.example/i.png', self::ICON, self::ICON], array_map(static fn ($i) => $i->getIconUrl(), $items));
	}//end testItemsAreTheUsersLinksInOrderWithIconsAndSubtitles()

	public function testTheLimitStopsTheWalk(): void {
		$links = [];
		for ($i = 0; $i < 10; $i++) {
			$links[] = ['label' => 'L' . $i, 'url' => 'https://x.example/' . $i];
		}

		$this->mapper->method('findOwnedByUserAndWidgetId')->willReturn([self::placement([['title' => 'S', 'links' => $links]])]);

		$this->assertCount(3, $this->widget->getItemsV2(userId: 'alice', limit: 3)->getItems());
	}//end testTheLimitStopsTheWalk()

	public function testOnlyTheRequestingUsersOwnPlacementsAreRead(): void {
		$this->mapper->expects($this->once())->method('findOwnedByUserAndWidgetId')->with('bob', 'links')->willReturn([]);
		$this->mapper->expects($this->never())->method('findByWidgetId');
		$this->mapper->expects($this->never())->method('findAll');
		$this->mapper->expects($this->never())->method('findByDashboardId');

		$this->widget->getItemsV2(userId: 'bob');
	}//end testOnlyTheRequestingUsersOwnPlacementsAreRead()

	public function testEmptyAndMalformedContentGiveTheEmptyState(): void {
		$broken = new WidgetPlacement();
		$broken->setContent('not json');
		$this->mapper->method('findOwnedByUserAndWidgetId')->willReturn([
			$broken,
			self::placement([['title' => 'Nothing yet', 'links' => []], 'not a section']),
		]);

		$result = $this->widget->getItemsV2(userId: 'alice');

		$this->assertSame([], $result->getItems());
		$this->assertSame('T:Add a links widget to one of your dashboards to see its links here.', $result->getEmptyContentMessage());
	}//end testEmptyAndMalformedContentGiveTheEmptyState()
}//end class
