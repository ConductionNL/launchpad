<?php

/**
 * Nextcloud's template print helpers, for tests that render a template
 * outside a Nextcloud boot (the same escaping as core's legacy functions).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

if (function_exists('p') === false) {
	/**
	 * Print a value HTML-escaped, as core's p().
	 *
	 * @param mixed $string The value.
	 *
	 * @return void
	 */
	function p(mixed $string): void {
		print(htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	}
}

if (function_exists('print_unescaped') === false) {
	/**
	 * Print a value as it is, as core's print_unescaped().
	 *
	 * @param mixed $string The value.
	 *
	 * @return void
	 */
	function print_unescaped(mixed $string): void {
		print((string) $string);
	}
}
