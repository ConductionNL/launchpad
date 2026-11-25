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
		$root = dirname(__DIR__, 3);
		$info = (string) file_get_contents($root.'/appinfo/info.xml');
		preg_match_all('#<command>OCA\\\\LaunchPad\\\\Command\\\\(\w+)</command>#', $info, $matches);
		$this->assertNotEmpty($matches[1], 'info.xml registers commands');

		$tooLong = [];
		foreach ($matches[1] as $class) {
			$source = (string) file_get_contents($root.'/lib/Command/'.$class.'.php');
			$found  = preg_match("#setDescription\(\s*(?:description:\s*)?'((?:[^'\\\\]|\\\\.)*)'\s*\)#", $source, $description);
			$this->assertSame(1, $found, $class.' sets a description');
			$text = stripcslashes($description[1]);
			if (mb_strlen($text) > 60 || str_contains($text, "\n") === true) {
				$tooLong[] = $class.' ('.mb_strlen($text).'): '.$text;
			}
		}

		$this->assertSame([], $tooLong);
	}//end testEveryRegisteredCommandHasAShortOneLineDescription()
}//end class
