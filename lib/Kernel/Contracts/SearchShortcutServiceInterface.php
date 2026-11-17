<?php

/**
 * Contract of SearchShortcutService
 *
 * The public surface of SearchShortcutService that other components call, so they depend
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
 * Contract of SearchShortcutService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface SearchShortcutServiceInterface {
	/**
	 * The stored shortcuts. A stored list that no longer validates reads as
	 * empty, so the page never receives an address it must not open.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}>
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function getShortcuts(): array;

	/**
	 * Validate and store the shortcuts.
	 *
	 * @param array $raw Shortcuts as sent by the admin screen.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}> The stored list.
	 *
	 * @throws \InvalidArgumentException When an entry is invalid.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function saveShortcuts(array $raw): array;
}//end interface
