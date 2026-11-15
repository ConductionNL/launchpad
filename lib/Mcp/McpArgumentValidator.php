<?php

/**
 * LaunchPad McpArgumentValidator
 *
 * Typed argument checks for the MCP tools: each returns a structured error
 * envelope, or null when the argument is valid, so a handler refuses before it
 * authorises or calls a service (mcp-tool-surface REQ-MCP-005).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Mcp
 * @package  OCA\LaunchPad\Mcp
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Mcp;

/**
 * Argument checks returning structured MCP errors.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpArgumentValidator {

	private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

	/**
	 * The structured error envelope every tool answers a refusal with.
	 *
	 * @param string $code    The machine code: invalid_arguments, unauthenticated, forbidden, not_found, unknown_tool.
	 * @param string $message A sentence for the agent.
	 *
	 * @return array{isError: true, error: string, message: string}
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public static function error(string $code, string $message): array {
		return ['isError' => true, 'error' => $code, 'message' => $message];
	}//end error()

	/**
	 * A required UUID argument.
	 *
	 * @param array<string, mixed> $args The arguments.
	 * @param string               $name The argument name.
	 *
	 * @return array<string, mixed>|null The error, or null.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function uuid(array $args, string $name): ?array {
		$value = ($args[$name] ?? null);
		if (is_string($value) === false || preg_match(self::UUID_PATTERN, $value) !== 1) {
			return self::error(code: 'invalid_arguments', message: "Argument {$name} must be a UUID.");
		}

		return null;
	}//end uuid()

	/**
	 * A required positive integer id argument.
	 *
	 * @param array<string, mixed> $args The arguments.
	 * @param string               $name The argument name.
	 *
	 * @return array<string, mixed>|null The error, or null.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function id(array $args, string $name): ?array {
		$value = ($args[$name] ?? null);
		if (is_int($value) === false || $value < 1) {
			return self::error(code: 'invalid_arguments', message: "Argument {$name} must be a positive integer.");
		}

		return null;
	}//end id()

	/**
	 * A string argument between a minimum and a maximum length; absent is allowed when not required.
	 *
	 * @param array<string, mixed> $args     The arguments.
	 * @param string               $name     The argument name.
	 * @param int                  $min      The minimum length.
	 * @param int                  $max      The maximum length.
	 * @param bool                 $required Whether the argument must be present.
	 *
	 * @return array<string, mixed>|null The error, or null.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function string(array $args, string $name, int $min, int $max, bool $required=true): ?array {
		if (array_key_exists($name, $args) === false && $required === false) {
			return null;
		}

		$value = ($args[$name] ?? null);
		if (is_string($value) === false || mb_strlen($value) < $min || mb_strlen($value) > $max) {
			return self::error(
				code: 'invalid_arguments',
				message: "Argument {$name} must be a string of {$min} to {$max} characters."
			);
		}

		return null;
	}//end string()

	/**
	 * An argument that must be one of a fixed set; absent is allowed when not required.
	 *
	 * @param array<string, mixed> $args     The arguments.
	 * @param string               $name     The argument name.
	 * @param list<string>         $allowed  The allowed values.
	 * @param bool                 $required Whether the argument must be present.
	 *
	 * @return array<string, mixed>|null The error, or null.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function oneOf(array $args, string $name, array $allowed, bool $required=true): ?array {
		if (array_key_exists($name, $args) === false && $required === false) {
			return null;
		}

		if (in_array(($args[$name] ?? null), $allowed, true) === false) {
			return self::error(
				code: 'invalid_arguments',
				message: "Argument {$name} must be one of: " . implode(', ', $allowed) . '.'
			);
		}

		return null;
	}//end oneOf()

	/**
	 * Grid bounds of a placement: each given coordinate or size is an integer in range.
	 *
	 * @param array<string, mixed> $args The arguments, with any of gridX, gridY, gridWidth, gridHeight.
	 *
	 * @return array<string, mixed>|null The error, or null.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function grid(array $args): ?array {
		$bounds = ['gridX' => [0, 1000], 'gridY' => [0, 1000], 'gridWidth' => [1, 12], 'gridHeight' => [1, 100]];
		foreach ($bounds as $name => [$min, $max]) {
			if (array_key_exists($name, $args) === false) {
				continue;
			}

			$value = $args[$name];
			if (is_int($value) === false || $value < $min || $value > $max) {
				return self::error(
					code: 'invalid_arguments',
					message: "Argument {$name} must be an integer from {$min} to {$max}."
				);
			}
		}

		return null;
	}//end grid()
}//end class
