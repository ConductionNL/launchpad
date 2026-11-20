<?php

/**
 * TemplateAudience test
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\TemplateAudience;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Who an admin template is offered to (REQ-TMPL-018).
 *
 * @covers \OCA\LaunchPad\Service\TemplateAudience
 */
class TemplateAudienceTest extends TestCase {

	/**
	 * Target groups, the user's groups, and whether the template is offered.
	 *
	 * @return array<string, array{0: string[], 1: string[], 2: bool}>
	 */
	public static function audiences(): array {
		return [
			'no groups: everyone' => [[], [], true],
			'the default group: everyone' => [['default'], [], true],
			'one of the user\'s groups' => [['finance', 'sales'], ['sales'], true],
			'none of the user\'s groups' => [['finance'], ['sales'], false],
			'a user without groups' => [['finance'], [], false],
		];
	}//end audiences()

	/**
	 * The rule, case by case.
	 *
	 * @param string[] $targets The template's target groups.
	 * @param string[] $groups  The user's groups.
	 * @param bool     $offered Whether the template is offered.
	 *
	 * @return void
	 */
	#[DataProvider('audiences')]
	public function testOffers(array $targets, array $groups, bool $offered): void {
		$template = new Dashboard();
		$template->setTargetGroupsArray($targets);

		$this->assertSame(expected: $offered, actual: (new TemplateAudience())->offers(template: $template, userGroupIds: $groups));
	}//end testOffers()
}//end class
