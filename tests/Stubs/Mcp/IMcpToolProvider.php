<?php

/**
 * OpenRegister IMcpToolProvider stub for unit tests and static analysis.
 *
 * OpenRegister ships the interface at runtime; LaunchPad does not depend on it
 * at install time. The guard makes this a no-op when the real interface is
 * loaded. The signature mirrors decidiq's stub of the same interface.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Mcp;

if (interface_exists(IMcpToolProvider::class) === false) {
	/**
	 * A per-app MCP tool provider OpenRegister enumerates.
	 */
	interface IMcpToolProvider {

		/**
		 * The app id the provider answers for.
		 *
		 * @return string
		 */
		public function getAppId(): string;

		/**
		 * The tool descriptors.
		 *
		 * @return array<int, array<string, mixed>>
		 */
		public function getTools(): array;

		/**
		 * Run one tool.
		 *
		 * @param string               $toolId    The tool id.
		 * @param array<string, mixed> $arguments The arguments.
		 *
		 * @return array<string, mixed>
		 */
		public function invokeTool(string $toolId, array $arguments): array;
	}//end interface
}//end if
