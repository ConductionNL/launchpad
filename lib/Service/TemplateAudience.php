<?php

/**
 * TemplateAudience
 *
 * Decides whether an admin template is offered to a user, from the template's
 * target groups and the user's groups (REQ-TMPL-018). The gallery lists what
 * it offers and a fork accepts the same, so both ask this one rule.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;

/**
 * Who an admin template is offered to.
 *
 * @spec openspec/specs/admin-templates/spec.md#requirement-the-gallery-offers-a-template-to-the-groups-it-names-req-tmpl-018
 */
final class TemplateAudience {
	/**
	 * Whether a template is offered to a user with these groups: it names no
	 * groups, names the `default` group, or names one of the user's groups.
	 *
	 * @param Dashboard $template     The admin template.
	 * @param string[]  $userGroupIds The user's group IDs.
	 *
	 * @return bool
	 *
	 * @spec openspec/specs/admin-templates/spec.md#requirement-the-gallery-offers-a-template-to-the-groups-it-names-req-tmpl-018
	 */
	public function offers(Dashboard $template, array $userGroupIds): bool {
		$targets = $template->getTargetGroupsArray();
		if ($targets === [] || in_array(needle: Dashboard::DEFAULT_GROUP_ID, haystack: $targets, strict: true) === true) {
			return true;
		}

		return array_intersect($targets, $userGroupIds) !== [];
	}//end offers()
}//end class
