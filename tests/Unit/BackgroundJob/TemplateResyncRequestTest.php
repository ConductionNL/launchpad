<?php

/**
 * Unit tests for TemplateResyncRequest: argument parsing and completeness.
 *
 * @category Test
 * @package  Unit\BackgroundJob
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\TemplateResyncRequest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\BackgroundJob\TemplateResyncRequest
 */
class TemplateResyncRequestTest extends TestCase {
	public function testACompleteArgumentIsParsed(): void {
		$request = new TemplateResyncRequest(['templateId' => '12', 'strategy' => 'merge', 'actingAdminId' => 'admin']);

		$this->assertSame(12, $request->templateId);
		$this->assertSame('merge', $request->strategy);
		$this->assertSame('admin', $request->actingAdminId);
		$this->assertTrue($request->isComplete());
	}

	/**
	 * @dataProvider incompleteArguments
	 */
	public function testIncompleteArgumentsAreNotComplete(mixed $argument): void {
		$this->assertFalse((new TemplateResyncRequest($argument))->isComplete());
	}

	public static function incompleteArguments(): array {
		return [
			'null' => [null],
			'empty array' => [[]],
			'zero template' => [['templateId' => 0, 'strategy' => 'merge', 'actingAdminId' => 'admin']],
			'negative template' => [['templateId' => -3, 'strategy' => 'merge', 'actingAdminId' => 'admin']],
			'missing strategy' => [['templateId' => 1, 'actingAdminId' => 'admin']],
			'empty admin' => [['templateId' => 1, 'strategy' => 'merge', 'actingAdminId' => '']],
		];
	}

	public function testMissingKeysDefaultToEmptyValues(): void {
		$request = new TemplateResyncRequest(null);

		$this->assertSame(0, $request->templateId);
		$this->assertSame('', $request->strategy);
		$this->assertSame('', $request->actingAdminId);
	}
}
