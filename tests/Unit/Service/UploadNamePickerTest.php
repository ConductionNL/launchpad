<?php

/**
 * UploadNamePickerTest
 *
 * Unit tests for UploadNamePicker: name sanitising and the numbered
 * ` (n)` suffix while a name is taken (REQ-FLS-007).
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

use OCA\LaunchPad\Service\UploadNamePicker;
use OCP\Files\Folder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for UploadNamePicker.
 */
#[CoversClass(UploadNamePicker::class)]
class UploadNamePickerTest extends TestCase {
	/**
	 * A folder double holding the given names.
	 *
	 * @param string[] $taken    Names that already exist.
	 * @param string[] $asked    Receives every name the picker asked about.
	 *
	 * @return Folder&MockObject
	 */
	private function folderWith(array $taken, array &$asked=[]): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('nodeExists')->willReturnCallback(
			static function (string $path) use ($taken, &$asked): bool {
				$asked[] = $path;
				return in_array($path, $taken, true);
			}
		);

		return $folder;
	}//end folderWith()

	/**
	 * A free name is returned unchanged after one lookup.
	 *
	 * @return void
	 */
	public function testFreeNameIsKept(): void {
		$asked = [];
		$name = (new UploadNamePicker())->availableName(folder: $this->folderWith([], $asked), desired: 'report.pdf');

		$this->assertSame('report.pdf', $name);
		$this->assertSame(['report.pdf'], $asked);
	}//end testFreeNameIsKept()

	/**
	 * Separators and NUL are replaced; unusable names become `upload`.
	 *
	 * @param string $desired  The desired name.
	 * @param string $expected The sanitised name.
	 *
	 * @return void
	 */
	#[DataProvider('sanitiseCases')]
	public function testNameIsSanitised(string $desired, string $expected): void {
		$name = (new UploadNamePicker())->availableName(folder: $this->folderWith([]), desired: $desired);

		$this->assertSame($expected, $name);
	}//end testNameIsSanitised()

	/**
	 * Desired name → sanitised name.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function sanitiseCases(): array {
		return [
			'slash' => ['../etc/passwd', '.._etc_passwd'],
			'backslash' => ['a\\b.txt', 'a_b.txt'],
			'nul' => ["a\0b", 'a_b'],
			'empty' => ['', 'upload'],
			'dot' => ['.', 'upload'],
			'dotdot' => ['..', 'upload'],
		];
	}//end sanitiseCases()

	/**
	 * A taken name gets the first free ` (n)` before its extension.
	 *
	 * @return void
	 */
	public function testTakenNameGetsFirstFreeNumberBeforeExtension(): void {
		$asked = [];
		$folder = $this->folderWith(['report.pdf', 'report (1).pdf'], $asked);

		$name = (new UploadNamePicker())->availableName(folder: $folder, desired: 'report.pdf');

		$this->assertSame('report (2).pdf', $name);
		$this->assertSame(['report.pdf', 'report (1).pdf', 'report (2).pdf'], $asked);
	}//end testTakenNameGetsFirstFreeNumberBeforeExtension()

	/**
	 * Only the last dot counts as the extension.
	 *
	 * @return void
	 */
	public function testNumberGoesBeforeTheLastExtension(): void {
		$name = (new UploadNamePicker())->availableName(folder: $this->folderWith(['a.tar.gz']), desired: 'a.tar.gz');

		$this->assertSame('a.tar (1).gz', $name);
	}//end testNumberGoesBeforeTheLastExtension()

	/**
	 * A dotfile or a name without a dot gets the number at the end.
	 *
	 * @return void
	 */
	public function testDotfileAndExtensionlessNamesGetNumberAtEnd(): void {
		$picker = new UploadNamePicker();

		$this->assertSame('.env (1)', $picker->availableName(folder: $this->folderWith(['.env']), desired: '.env'));
		$this->assertSame('README (1)', $picker->availableName(folder: $this->folderWith(['README']), desired: 'README'));
	}//end testDotfileAndExtensionlessNamesGetNumberAtEnd()

	/**
	 * The sanitised name, not the desired one, is numbered.
	 *
	 * @return void
	 */
	public function testSanitisedNameIsNumbered(): void {
		$name = (new UploadNamePicker())->availableName(folder: $this->folderWith(['a_b.txt']), desired: 'a/b.txt');

		$this->assertSame('a_b (1).txt', $name);
	}//end testSanitisedNameIsNumbered()

	/**
	 * After 999 taken numbers a unique-id suffix is used.
	 *
	 * @return void
	 */
	public function testExhaustedNumbersFallBackToUniqueSuffix(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('nodeExists')->willReturn(true);

		$name = (new UploadNamePicker())->availableName(folder: $folder, desired: 'x.txt');

		$this->assertMatchesRegularExpression('/^x \([0-9a-f]{13}\)\.txt$/', $name);
	}//end testExhaustedNumbersFallBackToUniqueSuffix()
}//end class
