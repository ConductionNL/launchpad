<?php

/**
 * CommandDescriptionLengthTest
 *
 * Every command in appinfo/info.xml describes itself in one line of at most
 * 60 characters for `occ list`; the longer text belongs in its help.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use PHPUnit\Framework\TestCase;

/**
 * The `occ list` description of every registered command (REQ-CLI-009).
 *
 * @coversNothing
 */
class CommandDescriptionLengthTest extends TestCase {

	/**
	 * Each registered command sets one description of at most 60 characters.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public function testEveryRegisteredCommandHasAShortOneLineDescription(): void {
		$root = dirname(path: __DIR__, levels: 3);
		$info = (string) file_get_contents(filename: $root.'/appinfo/info.xml');
		preg_match_all(pattern: '#<command>OCA\\\\LaunchPad\\\\Command\\\\(\w+)</command>#', subject: $info, matches: $matches);
		$this->assertNotEmpty(actual: $matches[1], message: 'info.xml registers commands');

		$tooLong = [];
		foreach ($matches[1] as $class) {
			$source = (string) file_get_contents(filename: $root.'/lib/Command/'.$class.'.php');
			$found  = preg_match(pattern: "#setDescription\(\s*(?:description:\s*)?'((?:[^'\\\\]|\\\\.)*)'\s*\)#", subject: $source, matches: $description);
			$this->assertSame(expected: 1, actual: $found, message: $class.' sets a description');
			$text = stripcslashes(string: $description[1]);
			if (mb_strlen(string: $text) > 60 || str_contains(haystack: $text, needle: "\n") === true) {
				$tooLong[] = $class.' ('.mb_strlen(string: $text).'): '.$text;
			}
		}

		$this->assertSame(expected: [], actual: $tooLong);
	}//end testEveryRegisteredCommandHasAShortOneLineDescription()
}//end class
