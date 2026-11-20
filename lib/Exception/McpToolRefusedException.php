<?php

/**
 * McpToolRefusedException
 *
 * A LaunchPad MCP tool that refused the call: no permission, a target that
 * does not exist, or invalid input. OpenRegister turns an exception from a
 * tool provider into an `isError: true` answer, so the provider throws this
 * instead of returning the refusal as an ordinary result. It carries the
 * refusal as the tool built it, audit record included (REQ-MCP-006).
 *
 * @category Exception
 * @package  OCA\LaunchPad\Exception
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

namespace OCA\LaunchPad\Exception;

/**
 * A refused MCP tool call.
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */
class McpToolRefusedException extends \RuntimeException {

	/**
	 * Constructor. The message is `<error code>: <message>`, which is what
	 * OpenRegister shows the agent.
	 *
	 * @param array<string, mixed> $result The refusal: `isError`, `error`, `message` and, for a write tool, `audit`.
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function __construct(private readonly array $result) {
		parent::__construct(message: $this->getErrorCode() . ': ' . (string)($result['message'] ?? 'The tool refused the call.'));
	}//end __construct()

	/**
	 * The refusal's error code, such as `forbidden`, `not_found` or `invalid_arguments`.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getErrorCode(): string {
		return (string)($this->result['error'] ?? 'failed');
	}//end getErrorCode()

	/**
	 * The refusal as the tool built it.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
	 */
	public function getResult(): array {
		return $this->result;
	}//end getResult()
}//end class
