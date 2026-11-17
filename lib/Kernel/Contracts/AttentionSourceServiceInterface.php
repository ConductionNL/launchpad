<?php

/**
 * Contract of AttentionSourceService
 *
 * The public surface of AttentionSourceService that other components call, so they depend
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

use OCP\IUser;

/**
 * Contract of AttentionSourceService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AttentionSourceServiceInterface {
	/**
	 * Every attention item the user's apps declare.
	 *
	 * @param IUser $user The signed-in user.
	 *
	 * @return array{sources: array<int, array<string, mixed>>, invalid: array<int, array<string, string>>}
	 *
	 * @spec openspec/specs/attention-feed/spec.md#req-att-001
	 */
	public function collect(IUser $user): array;
}//end interface
