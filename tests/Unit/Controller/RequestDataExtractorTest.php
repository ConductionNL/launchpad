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
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(function (string $key, mixed $default = null) use ($params): mixed {
			$this->asked[] = $key;
			return array_key_exists($key, $params) ? $params[$key] : $default;
		});
		return $request;
	}

	public function testTileDataDefaults(): void {
		$this->assertSame(
			expected: [
				'title' => 'New Tile',
				'icon' => 'icon-link',
				'iconType' => 'class',
				'bgColor' => '#0082c9',
				'txtColor' => '#ffffff',
				'linkType' => 'app',
				'linkVal' => '',
				'linkTarget' => null,
				'gridX' => 0,
				'gridY' => 0,
			],
			actual: RequestDataExtractor::extractTileData($this->request(params: []))
		);
		$this->assertSame(
			expected: ['title', 'icon', 'iconType', 'bgColor', 'textColor', 'linkType', 'linkValue', 'linkTarget', 'gridX', 'gridY'],
			actual: $this->asked
		);
	}

	public function testTileDataFromRequest(): void {
		$this->assertSame(
			expected: [
				'title' => 'Mail',
				'icon' => 'mail.svg',
				'iconType' => 'url',
				'bgColor' => '#000000',
				'txtColor' => '#111111',
				'linkType' => 'url',
				'linkVal' => 'https://x',
				'linkTarget' => 'side-panel',
				'gridX' => 3,
				'gridY' => 4,
			],
			actual: RequestDataExtractor::extractTileData($this->request(params: [
				'title' => 'Mail', 'icon' => 'mail.svg', 'iconType' => 'url', 'bgColor' => '#000000',
				'textColor' => '#111111', 'txtColor' => 'ignored', 'linkType' => 'url', 'linkValue' => 'https://x',
				'linkTarget' => 'side-panel', 'gridX' => '3', 'gridY' => 4.7,
			]))
		);
	}

	public function testPlacementDataKeepsOnlyGivenFields(): void {
		$this->assertSame(
			expected: ['gridX' => 1, 'isVisible' => false, 'content' => '', 'acknowledgementContentVersion' => 2],
			actual: RequestDataExtractor::extractPlacementData($this->request(params: [
				'acknowledgementContentVersion' => 2, 'content' => '', 'isVisible' => false, 'gridX' => 1,
				'gridY' => null, 'announcementKey' => 'k',
			]))
		);
		$this->assertSame(
			expected: [
				'version', 'gridX', 'gridY', 'gridWidth', 'gridHeight', 'isVisible', 'showTitle', 'customTitle', 'customIcon',
				'styleConfig', 'content', 'calculatedFields', 'tileTitle', 'tileIcon', 'tileIconType', 'tileBackgroundColor', 'tileTextColor',
				'tileLinkType', 'tileLinkValue', 'requiresAcknowledgement', 'acknowledgementPrompt',
				'acknowledgementDeadline', 'reacknowledgeOnChange', 'acknowledgementContentVersion', 'ruleMatch',
			],
			actual: $this->asked
		);
	}
}
