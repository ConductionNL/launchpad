<?php

/**
 * TextTableValidator Test
 *
 * The `text` widget table checks on save (REQ-TBLE-008): ragged rows, a
 * column-alignment count that does not match the columns and a span past
 * the grid are refused; a full grid, with or without merges, passes, and
 * text mode is not checked.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/text-display-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\TextTableValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see TextTableValidator}.
 */
class TextTableValidatorTest extends TestCase {

	/**
	 * A table cell.
	 *
	 * @param string $text    The text.
	 * @param int    $rowSpan The row span.
	 * @param int    $colSpan The column span.
	 *
	 * @return array The cell.
	 */
	private static function cell(string $text, int $rowSpan=1, int $colSpan=1): array {
		return ['text' => $text, 'rowSpan' => $rowSpan, 'colSpan' => $colSpan];
	}//end cell()

	/**
	 * Table content in table mode.
	 *
	 * @param array $alignments The column alignments.
	 * @param array $rows       The rows.
	 *
	 * @return array The content.
	 */
	private static function table(array $alignments, array $rows): array {
		return [
			'text'      => '',
			'tableMode' => true,
			'tableData' => ['headerRow' => false, 'columnAlignments' => $alignments, 'rows' => $rows],
		];
	}//end table()

	/**
	 * Refused tables and the message they are refused with.
	 *
	 * @return array<string,array{0:array,1:string}>
	 */
	public static function refusals(): array {
		$abc = [self::cell('a'), self::cell('b'), self::cell('c')];
		return [
			'ragged rows'           => [
				self::table(['left', 'left', 'left'], [$abc, [self::cell('d'), self::cell('e')]]),
				'Grid is not rectangular',
			],
			'too few alignments'    => [self::table(['left', 'left'], [$abc]), 'Grid is not rectangular'],
			'row span past the end' => [
				self::table(['left'], [[self::cell('a')], [self::cell('b', 2, 1)]]),
				'Cell span exceeds grid bounds',
			],
			'col span past the end' => [
				self::table(['left', 'left'], [[self::cell('a'), self::cell('b', 1, 2)]]),
				'Cell span exceeds grid bounds',
			],
			'no rows'               => [self::table(['left'], []), 'Table must have at least one row'],
			'no columns'            => [self::table([], [[self::cell('a')]]), 'Table must have at least one column'],
		];
	}//end refusals()

	/**
	 * Each broken grid is refused with its message.
	 *
	 * @param array  $content The content.
	 * @param string $message The expected message.
	 *
	 * @return void
	 */
	#[DataProvider('refusals')]
	public function testRefusesABrokenGrid(array $content, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		(new TextTableValidator())->validate(content: $content);
	}//end testRefusesABrokenGrid()

	/**
	 * A full grid whose first cell spans two columns passes.
	 *
	 * @return void
	 */
	public function testAcceptsAMergedFullGrid(): void {
		(new TextTableValidator())->validate(
			content: self::table(
				['left', 'left', 'left'],
				[
					[self::cell('Wide', 1, 2), self::cell(''), self::cell('z')],
					[self::cell('a'), self::cell('b'), self::cell('c')],
				]
			)
		);

		$this->addToAssertionCount(1);
	}//end testAcceptsAMergedFullGrid()

	/**
	 * Text mode is not checked, so a kept but broken tableData does not block it.
	 *
	 * @return void
	 */
	public function testIgnoresTextMode(): void {
		$content = self::table(['left'], [[self::cell('a'), self::cell('b')]]);
		$content['tableMode'] = false;

		(new TextTableValidator())->validate(content: $content);

		$this->addToAssertionCount(1);
	}//end testIgnoresTextMode()
}//end class
