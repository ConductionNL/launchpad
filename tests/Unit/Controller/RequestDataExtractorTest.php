<?php

/**
 * RequestDataExtractorTest
 *
 * Pins the tile and placement data read from a request: the parameter
 * names, their defaults, the integer grid cast, the key order and that
 * absent placement fields are left out.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\RequestDataExtractor;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class RequestDataExtractorTest extends TestCase {

	/** @var list<string> */
	private array $asked = [];

	/**
	 * A request carrying the given parameters, recording every key asked for.
	 *
	 * @param array<string, mixed> $params The parameters.
	 *
	 * @return IRequest
	 */
	private function request(array $params): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(function (string $key, mixed $default = null) use ($params): mixed {
			$this->asked[] = $key;
			return array_key_exists($key, $params) ? $params[$key] : $default;
		});
		return $request;
	}

	public function testTileDataDefaults(): void {
		$this->assertSame(
			[
				'title' => 'New Tile',
				'icon' => 'icon-link',
				'iconType' => 'class',
				'bgColor' => '#0082c9',
				'txtColor' => '#ffffff',
				'linkType' => 'app',
				'linkVal' => '',
				'gridX' => 0,
				'gridY' => 0,
			],
			RequestDataExtractor::extractTileData($this->request([]))
		);
		$this->assertSame(
			['title', 'icon', 'iconType', 'bgColor', 'textColor', 'linkType', 'linkValue', 'gridX', 'gridY'],
			$this->asked
		);
	}

	public function testTileDataFromRequest(): void {
		$this->assertSame(
			[
				'title' => 'Mail',
				'icon' => 'mail.svg',
				'iconType' => 'url',
				'bgColor' => '#000000',
				'txtColor' => '#111111',
				'linkType' => 'url',
				'linkVal' => 'https://x',
				'gridX' => 3,
				'gridY' => 4,
			],
			RequestDataExtractor::extractTileData($this->request([
				'title' => 'Mail', 'icon' => 'mail.svg', 'iconType' => 'url', 'bgColor' => '#000000',
				'textColor' => '#111111', 'txtColor' => 'ignored', 'linkType' => 'url', 'linkValue' => 'https://x',
				'gridX' => '3', 'gridY' => 4.7,
			]))
		);
	}

	public function testPlacementDataKeepsOnlyGivenFields(): void {
		$this->assertSame(
			['gridX' => 1, 'isVisible' => false, 'content' => '', 'acknowledgementContentVersion' => 2],
			RequestDataExtractor::extractPlacementData($this->request([
				'acknowledgementContentVersion' => 2, 'content' => '', 'isVisible' => false, 'gridX' => 1,
				'gridY' => null, 'announcementKey' => 'k',
			]))
		);
		$this->assertSame(
			[
				'gridX', 'gridY', 'gridWidth', 'gridHeight', 'isVisible', 'showTitle', 'customTitle', 'customIcon',
				'styleConfig', 'content', 'tileTitle', 'tileIcon', 'tileIconType', 'tileBackgroundColor', 'tileTextColor',
				'tileLinkType', 'tileLinkValue', 'requiresAcknowledgement', 'acknowledgementPrompt',
				'acknowledgementDeadline', 'reacknowledgeOnChange', 'acknowledgementContentVersion',
			],
			$this->asked
		);
	}
}
