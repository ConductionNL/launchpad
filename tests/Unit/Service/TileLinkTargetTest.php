<?php

/**
 * Unit tests for TileLinkTarget — the three link targets and how a tile's
 * target is resolved.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\TileLinkTarget;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\TileLinkTarget
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */
class TileLinkTargetTest extends TestCase {

	/**
	 * The tile's own choice wins, then the dashboard default, then the link-type rule.
	 *
	 * @return void
	 */
	public function testTheResolutionOrder(): void {
		$this->assertSame(
			[
				'side-panel',
				'new-tab',
				'same-tab',
				'new-tab',
				'same-tab',
				'same-tab',
				'same-tab',
			],
			[
				TileLinkTarget::resolve(tileTarget: 'side-panel', dashboardDefault: 'same-tab', linkType: 'url'),
				TileLinkTarget::resolve(tileTarget: null, dashboardDefault: 'new-tab', linkType: 'app'),
				TileLinkTarget::resolve(tileTarget: 'bogus', dashboardDefault: 'same-tab', linkType: 'url'),
				TileLinkTarget::resolve(tileTarget: null, dashboardDefault: null, linkType: 'url'),
				TileLinkTarget::resolve(tileTarget: null, dashboardDefault: null, linkType: 'app'),
				TileLinkTarget::resolve(tileTarget: null, dashboardDefault: null, linkType: 'internal'),
				TileLinkTarget::resolve(tileTarget: null, dashboardDefault: null, linkType: null),
			]
		);
	}

	/**
	 * The three values pass, an empty value means none, anything else is refused by field name.
	 *
	 * @return void
	 */
	public function testValidation(): void {
		$this->assertSame(
			['same-tab', 'new-tab', 'side-panel', null, null],
			array_map(
				static fn ($value): ?string => TileLinkTarget::validated(value: $value),
				['same-tab', 'new-tab', 'side-panel', null, '']
			)
		);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('defaultLinkTarget must be one of same-tab, new-tab, side-panel');
		TileLinkTarget::validated(value: 'Same-Tab', field: 'defaultLinkTarget');
	}
}
