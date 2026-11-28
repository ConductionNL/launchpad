<?php

/**
 * DraftConflictException
 *
 * An edit-mode Save whose draft changed widgets a colleague saved in the
 * meantime (edit-mode-draft-and-save, design D4).
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * The widgets of a draft that a colleague changed first, as saved now.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
class DraftConflictException extends \RuntimeException {
	/**
	 * Constructor
	 *
	 * @param list<WidgetPlacement> $current The conflicting widgets as saved now.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function __construct(private readonly array $current) {
		parent::__construct(message: 'A colleague changed widgets of this draft first.');
	}//end __construct()

	/**
	 * The conflicting widgets as saved now.
	 *
	 * @return list<WidgetPlacement>
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function getCurrent(): array {
		return $this->current;
	}//end getCurrent()
}//end class
