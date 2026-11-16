<?php

/**
 * Contract of PublicStartPage
 *
 * The public surface of PublicStartPage that other components call, so they depend
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

use OCA\LaunchPad\Db\PublicShare;

/**
 * Contract of PublicStartPage.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface PublicStartPageInterface {
	/**
	 * The start page as configured: switched on or not, the chosen link, whether it is live, and why not.
	 *
	 * @return array{enabled: bool, token: string, active: bool, reason: string|null}
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function status(): array;

	/**
	 * The link the start page shows, or null when it is off or its link can no longer be shown.
	 *
	 * @return PublicShare|null
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function active(): ?PublicShare;

	/**
	 * Choose the link and switch the start page on or off.
	 *
	 * An empty token clears the choice, and is only accepted with the page off.
	 *
	 * @param bool   $enabled Whether the start page is on.
	 * @param string $token   The chosen link's token, or '' for none.
	 *
	 * @return array{enabled: bool, token: string, active: bool, reason: string|null} The new status.
	 *
	 * @throws \InvalidArgumentException When the link cannot be the start page, or the page is switched on without one.
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function configure(bool $enabled, string $token): array;

	/**
	 * The links that may be chosen: not revoked, not expired, no password.
	 *
	 * @return list<array{token: string, dashboardUuid: string, dashboardName: string, expiresAt: string|null}>
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
	 */
	public function candidates(): array;
}//end interface
