<?php

/**
 * CalculatedFieldSourcesTest
 *
 * Unit tests for CalculatedFieldSources: the "register/schema" key of a
 * widget's OpenRegister source, and why a calculated field is downgraded
 * when its widget has no source or refers to a widget on another register.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\CalculatedFields
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\CalculatedFields;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldSources
 */
class CalculatedFieldSourcesTest extends TestCase {

	/**
	 * Widget contents and the source key each one has.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function contents(): array {
		return [
			'top-level register and schema' => [['register' => 'zaken', 'schema' => 'zaak'], 'zaken/zaak'],
			'nested source'                 => [['source' => ['register' => 5, 'schema' => 12]], '5/12'],
			'top level wins over nested'    => [['register' => 'a', 'schema' => 'b', 'source' => ['register' => 'c', 'schema' => 'd']], 'a/b'],
			'no schema'                     => [['register' => 'zaken'], ''],
			'empty schema'                  => [['register' => 'zaken', 'schema' => ''], ''],
			'array register'                => [['register' => ['x'], 'schema' => 'zaak'], ''],
			'source not an array'           => [['source' => 'zaken/zaak'], ''],
			'not an array at all'           => ['zaken/zaak', ''],
			'nothing'                       => [null, ''],
		];
	}//end contents()

	/**
	 * The source key is "register/schema", or empty when the content names no complete source.
	 *
	 * @param mixed  $content  The widget content.
	 * @param string $expected The key.
	 *
	 * @return void
	 */
	#[DataProvider('contents')]
	public function testSourceKey(mixed $content, string $expected): void {
		$this->assertSame(expected: $expected, actual: (new CalculatedFieldSources())->sourceKey(content: $content));
	}//end testSourceKey()

	/**
	 * A widget without a source of its own is downgraded.
	 *
	 * @return void
	 */
	public function testAWidgetWithoutASourceIsDowngraded(): void {
		$this->assertSame(
			expected: 'The widget has no OpenRegister source',
			actual: (new CalculatedFieldSources())->downgradeReason(id: 'w1', own: '', ast: [], sources: [])
		);
	}//end testAWidgetWithoutASourceIsDowngraded()

	/**
	 * References to itself and to widgets on the same register are fine, at any depth.
	 *
	 * @return void
	 */
	public function testReferencesOnTheSameRegisterAreAllowed(): void {
		$ast = [
			'type'  => 'binary',
			'left'  => ['type' => 'fieldRef', 'source' => 'widget:w1', 'field' => 'a'],
			'right' => ['type' => 'call', 'args' => [['type' => 'fieldRef', 'source' => 'widget:w2', 'field' => 'b']]],
		];

		$this->assertNull(
			actual: (new CalculatedFieldSources())->downgradeReason(id: 'w1', own: 'zaken/zaak', ast: $ast, sources: ['w2' => 'zaken/zaak'])
		);
	}//end testReferencesOnTheSameRegisterAreAllowed()

	/**
	 * A nested reference to a widget on another register, or to an unknown widget, is cross-register.
	 *
	 * @return void
	 */
	public function testAReferenceToAnotherRegisterIsDowngraded(): void {
		$ast = ['type' => 'call', 'args' => [['type' => 'unary', 'operand' => ['type' => 'fieldRef', 'source' => 'widget:w3']]]];
		$sources = new CalculatedFieldSources();

		$this->assertSame(
			expected: 'Cross-register reference',
			actual: $sources->downgradeReason(id: 'w1', own: 'zaken/zaak', ast: $ast, sources: ['w3' => 'hr/medewerker'])
		);
		$this->assertSame(
			expected: 'Cross-register reference',
			actual: $sources->downgradeReason(id: 'w1', own: 'zaken/zaak', ast: $ast, sources: [])
		);
	}//end testAReferenceToAnotherRegisterIsDowngraded()

	/**
	 * Only fieldRef nodes with a widget: source count as references.
	 *
	 * @return void
	 */
	public function testOnlyWidgetFieldRefsCount(): void {
		$ast = [
			'type' => 'call',
			'args' => [
				['type' => 'fieldRef', 'source' => 'self', 'field' => 'a'],
				['type' => 'literal', 'source' => 'widget:w9'],
				['type' => 'fieldRef', 'source' => 42],
			],
		];

		$this->assertNull(actual: (new CalculatedFieldSources())->downgradeReason(id: 'w1', own: 'zaken/zaak', ast: $ast, sources: []));
	}//end testOnlyWidgetFieldRefsCount()
}//end class
