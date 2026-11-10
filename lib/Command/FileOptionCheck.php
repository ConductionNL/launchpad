<?php

/**
 * FileOptionCheck
 *
 * The refusal of a command option that must name an existing file.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/cli-commands/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

/**
 * Checks a file-path option of a command.
 *
 * @spec openspec/specs/cli-commands/spec.md
 */
final class FileOptionCheck {
	/**
	 * The error line for a file option that is missing or names no
	 * existing file.
	 *
	 * @param string $option The option name, without the leading dashes.
	 * @param string $path   The option value.
	 *
	 * @return string|null The error line, or null when the file exists.
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public static function error(string $option, string $path): string|null {
		if ($path === '') {
			return '<error>--' . $option . ' parameter is required</error>';
		}

		if (file_exists(filename: $path) === false) {
			return '<error>File not found: ' . $path . '</error>';
		}

		return null;
	}//end error()
}//end class
