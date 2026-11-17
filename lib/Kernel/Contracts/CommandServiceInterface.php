<?php

/**
 * Contract of CommandService
 *
 * The public surface of CommandService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

/**
 * Contract of CommandService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface CommandServiceInterface {
	/**
	 * Exit code for unhandled exceptions / generic errors (REQ-CLI-006).
	 *
	 * @var integer
	 */
	public const EXIT_ERROR = 1;

	/**
	 * Exit code for invalid / malformed CLI arguments (REQ-CLI-006).
	 *
	 * @var integer
	 */
	public const EXIT_INVALID_ARGS = 2;

	/**
	 * Exit code for "resource not found" lookups (REQ-CLI-006).
	 *
	 * @var integer
	 */
	public const EXIT_NOT_FOUND = 4;

	/**
	 * Exit code for successful execution (REQ-CLI-006).
	 *
	 * @var integer
	 */
	public const EXIT_SUCCESS = 0;

	/**
	 * Build a successful JSON envelope (REQ-CLI-007).
	 *
	 * @param array<string,mixed>|list<mixed>|null $data Command payload.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public function envelopeSuccess(?array $data): array;

	/**
	 * Build an error JSON envelope (REQ-CLI-007).
	 *
	 * @param int $exitCode Command exit code.
	 * @param string $code Stable error identifier.
	 * @param string $message Human-readable text.
	 * @param array<string,mixed>|null $context Optional metadata.
	 *
	 * @return array<string,mixed>
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public function envelopeError(
		int $exitCode,
		string $code,
		string $message,
		?array $context = null,
	): array;

	/**
	 * Encode an envelope to a single JSON line (REQ-CLI-007).
	 *
	 * @param array<string,mixed> $envelope The envelope to encode.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public function encodeEnvelope(array $envelope): string;

	/**
	 * Write the audit-log line to the platform logger (REQ-CLI-010).
	 *
	 * @param array $run The run, as {@see self::formatAuditLine()} takes it.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	public function audit(array $run): void;
}//end interface
