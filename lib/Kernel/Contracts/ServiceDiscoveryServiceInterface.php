<?php

/**
 * Contract of ServiceDiscoveryService
 *
 * The public surface of ServiceDiscoveryService that other components call, so they depend
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
 * Contract of ServiceDiscoveryService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ServiceDiscoveryServiceInterface {
	/**
	 * The apps in the current user's app menu, LaunchPad itself left out.
	 *
	 * @return array<int, array{appId: string, name: string, href: string, icon: string}> The apps, in menu order.
	 *
	 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-the-add-flow-lists-the-apps-this-server-runs-for-the-user-req-disc-001
	 */
	public function discover(): array;
}//end interface
