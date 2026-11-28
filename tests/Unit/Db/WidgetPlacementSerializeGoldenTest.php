<?php

/**
 * WidgetPlacementSerializeGoldenTest
 *
 * Pins the exact serialised shape of a widget placement: every key in
 * order, the empty-blob-as-object rule and the tile block that only a
 * tile carries.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacement;
use PHPUnit\Framework\TestCase;

class WidgetPlacementSerializeGoldenTest extends TestCase {

	public function testAWidgetWithEmptyBlobs(): void {
		$placement = new WidgetPlacement();
		$placement->setId(5);
		$placement->setDashboardId(2);
		$placement->setWidgetId('w/x');

		$this->assertSame(
			expected: '{"id":5,"dashboardId":2,"widgetId":"w\/x","gridX":0,"gridY":0,"gridWidth":4,"gridHeight":4,'
			. '"isCompulsory":0,"isVisible":1,"styleConfig":{},"customTitle":null,"customIcon":null,"content":{},"calculatedFields":[],'
			. '"showTitle":1,"sortOrder":0,"ruleMatch":"any","requiresAcknowledgement":0,"acknowledgementPrompt":null,'
			. '"acknowledgementDeadline":null,"reacknowledgeOnChange":0,"acknowledgementContentVersion":1,'
			. '"announcementKey":null,"templatePlacementId":null,"version":0,"createdAt":null,"updatedAt":null}',
			actual: json_encode($placement->jsonSerialize())
		);
	}

	public function testATileWithBlobs(): void {
		$placement = new WidgetPlacement();
		$placement->setId(6);
		$placement->setStyleConfig('{"color":"red"}');
		$placement->setContent('{"widgetId":"x"}');
		$placement->setTileType('custom');
		$placement->setTileTitle('Mail');
		$placement->setTileIcon('mail');
		$placement->setTileIconType('class');
		$placement->setTileBackgroundColor('#000');
		$placement->setTileTextColor('#fff');
		$placement->setTileLinkType('app');
		$placement->setTileLinkValue('mail');

		$data = $placement->jsonSerialize();
		$this->assertSame(expected: ['color' => 'red'], actual: $data['styleConfig']);
		$this->assertSame(expected: ['widgetId' => 'x'], actual: $data['content']);
		$this->assertSame(
			expected: ['tileType', 'tileTitle', 'tileIcon', 'tileIconType', 'tileBackgroundColor', 'tileTextColor', 'tileLinkType', 'tileLinkValue'],
			actual: array_slice(array_keys($data), 27)
		);
		$this->assertSame(
			expected: ['custom', 'Mail', 'mail', 'class', '#000', '#fff', 'app', 'mail'],
			actual: array_slice(array_values($data), 27)
		);
		$this->assertCount(expectedCount: 35, haystack: $data);
	}
}
