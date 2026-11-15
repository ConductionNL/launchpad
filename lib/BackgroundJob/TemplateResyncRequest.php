<?php

/**
 * TemplateResyncRequest
 *
 * The template, strategy and acting admin a queued template resync carries.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\BackgroundJob;

/**
 * The arguments of one queued template resync.
 *
 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-001-re-sync-action-pushes-template-updates-to-existing-copies
 */
final class TemplateResyncRequest {
	/**
	 * The admin template's dashboard id (0 when missing).
	 *
	 * @var integer
	 */
	public readonly int $templateId;

	/**
	 * `overwrite` or `merge` (empty when missing).
	 *
	 * @var string
	 */
	public readonly string $strategy;

	/**
	 * The admin who queued the resync (empty when missing).
	 *
	 * @var string
	 */
	public readonly string $actingAdminId;

	/**
	 * Read the request from the job argument.
	 *
	 * @param mixed $argument The job argument.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-001-re-sync-action-pushes-template-updates-to-existing-copies
	 */
	public function __construct(mixed $argument) {
		$this->templateId = (int)($argument['templateId'] ?? 0);
		$this->strategy = (string)($argument['strategy'] ?? '');
		$this->actingAdminId = (string)($argument['actingAdminId'] ?? '');
	}//end __construct()

	/**
	 * Whether the argument names a template, a strategy and an admin.
	 *
	 * @return bool True when the resync can run.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-req-resync-001-re-sync-action-pushes-template-updates-to-existing-copies
	 */
	public function isComplete(): bool {
		return $this->templateId > 0 && $this->strategy !== '' && $this->actingAdminId !== '';
	}//end isComplete()
}//end class
