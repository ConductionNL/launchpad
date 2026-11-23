<?php

/**
 * Contract of ShippedTemplateService
 *
 * The public surface of ShippedTemplateService that other components call, so they depend
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
 * Contract of ShippedTemplateService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ShippedTemplateServiceInterface {
	/**
	 * The templates LaunchPad ships, by id. A fixed list rather than a
	 * directory scan, so the admin page and the command show the same
	 * templates in the same order on every instance.
	 *
	 * @var array<int, string>
	 */
	public const SHIPPED_IDS = ['mijn-werkdag'];

	/**
	 * Every shipped template, with whether it is installed here.
	 *
	 * @return array<int, array<string, mixed>> One descriptor per template.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	public function listTemplates(): array;

	/**
	 * Install a shipped template as an admin template.
	 *
	 * Already installed and not forced: nothing is added, but the groups and
	 * the default flag are still applied, so a deploy script can run twice.
	 * Forced: a fresh copy is added and becomes the recorded install. The
	 * earlier one stays, because users' dashboards made from it point at it.
	 *
	 * @param string               $templateId The shipped template id.
	 * @param array<string, mixed> $options    Optional `targetGroups` (groups whose members get the
	 *                                         template; empty leaves the definition's groups),
	 *                                         `makeDefault` (make it the default template for
	 *                                         everyone; false), `force` (add a fresh copy even when
	 *                                         installed; false) and `userId` (who runs the install,
	 *                                         for the archive; 'cli').
	 *
	 * @return array{templateId:string, uuid:string, id:int, version:int, alreadyInstalled:bool,
	 *               targetGroups:array<int,string>, isDefault:bool, missingWidgets:array<int,string>,
	 *               registers:array<int,string>}
	 *
	 * @throws \InvalidArgumentException When the id or a group is unknown.
	 * @throws \RuntimeException When the definition or the import fails.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
	 */
	public function install(string $templateId, array $options=[]): array;
}//end interface
