<?php

/**
 * Contract of FooterService
 *
 * The public surface of FooterService that other components call, so they depend
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
 * Contract of FooterService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface FooterServiceInterface {
	/**
	 * Read the five footer settings into a camelCase response payload.
	 *
	 * Defaults:
	 *  - `footerEnabled` — false (REQ-FTR-001 default-off scenario).
	 *  - `footerHtml` — empty string.
	 *  - `footerConfig` — empty stdClass-like array (`[]`).
	 *  - `footerBackgroundColor` / `footerTextColor` — null (theme
	 *    fallback).
	 *
	 * @return array<string, mixed> Settings keyed by camelCase
	 *                              (`footerEnabled`, `footerHtml`,
	 *                              `footerConfig`,
	 *                              `footerBackgroundColor`,
	 *                              `footerTextColor`).
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function getGlobalSettings(): array;

	/**
	 * Patch one or more global footer settings (REQ-FTR-001..003,
	 * REQ-FTR-009, REQ-FTR-010).
	 *
	 * Only keys present in `$patch` are updated; untouched keys
	 * retain their previous values. Each value is validated before
	 * persistence:
	 *  - `footerEnabled` — coerced to bool.
	 *  - `footerHtml` — sanitised via {@see FooterService::sanitiseHtml()}
	 *    (throws on > 8 KB input).
	 *  - `footerConfig` — validated via {@see FooterService::validateStructuredConfig()}.
	 *  - `footerBackgroundColor` / `footerTextColor` — validated via
	 *    {@see FooterService::assertHexColour()} (or NULL to clear).
	 *
	 * @param array<string, mixed> $patch Partial payload from the
	 *                                    HTTP body.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException When validation fails. The
	 *                                  controller maps the message to
	 *                                  HTTP 400 / 413 as appropriate.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function updateGlobalSettings(array $patch): void;
}//end interface
