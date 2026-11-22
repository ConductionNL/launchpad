<?php

/**
 * WidgetTypeList: LaunchPad's own widget types, read from `lib/widget-types.json`.
 *
 * One list, several readers: the demo showcase installer keeps a showcase
 * widget whose type is on it, and the shipped `default` widget rule lets every
 * user add the types on it. The frontend registry must equal the same file
 * (`widgetRegistry.completeness.spec.js`).
 *
 * @category  Support
 * @package   OCA\LaunchPad\Support
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

namespace OCA\LaunchPad\Support;

use RuntimeException;

/**
 * Reads LaunchPad's own widget types.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
 */
final class WidgetTypeList {
	/**
	 * Where the list lives.
	 *
	 * @var string
	 */
	public const PATH = __DIR__ . '/../widget-types.json';

	/**
	 * The Nextcloud dashboard widgets the shipped `default` widget rule allows
	 * besides LaunchPad's own types; older versions shipped only these.
	 *
	 * @var list<string>
	 */
	public const DEFAULT_RULE_DASHBOARD_WIDGETS = ['activity', 'recommendations'];

	/**
	 * Constructor.
	 *
	 * @param string $path The list to read; the shipped one by default.
	 */
	public function __construct(
		private readonly string $path=self::PATH,
	) {
	}//end __construct()

	/**
	 * What the shipped `default` widget rule allows: the Nextcloud dashboard
	 * widgets it always allowed plus every LaunchPad widget type (REQ-RFP-011).
	 *
	 * @return list<string> The widget ids.
	 *
	 * @throws RuntimeException When the list is missing or malformed.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	public function defaultRuleWidgets(): array {
		return $this->withOwnTypes(widgetIds: self::DEFAULT_RULE_DASHBOARD_WIDGETS);
	}//end defaultRuleWidgets()

	/**
	 * The given Nextcloud dashboard widget ids followed by every LaunchPad
	 * widget type, which is what a shipped widget rule allows: a rule lists
	 * every widget its group may use, so a rule without LaunchPad's own types
	 * would hide them on every dashboard (REQ-RFP-001, REQ-RFP-011).
	 *
	 * @param list<string> $widgetIds The Nextcloud dashboard widget ids.
	 *
	 * @return list<string> The widget ids.
	 *
	 * @throws RuntimeException When the list is missing or malformed.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	public function withOwnTypes(array $widgetIds): array {
		return array_values(array_unique(array_merge($widgetIds, $this->types())));
	}//end withOwnTypes()

	/**
	 * The non-empty type names of the list, in file order.
	 *
	 * Throws rather than returning an empty list: an empty list would make
	 * every caller silently skip or refuse every LaunchPad widget.
	 *
	 * @return list<string> The types.
	 *
	 * @throws RuntimeException When the list is missing or malformed.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-011-the-shipped-default-rule-lets-every-user-add-launchpads-own-widgets
	 */
	public function types(): array {
		$raw = null;
		if (is_readable(filename: $this->path) === true) {
			$raw = file_get_contents(filename: $this->path);
		}

		$decoded = null;
		if (is_string($raw) === true) {
			$decoded = json_decode(json: $raw, associative: true);
		}

		if (is_array($decoded) === false || is_array($decoded['types'] ?? null) === false) {
			throw new RuntimeException(message: 'LaunchPad widget type list missing or malformed: ' . $this->path);
		}

		$types = [];
		foreach ($decoded['types'] as $type) {
			if (is_string($type) === true && $type !== '') {
				$types[] = $type;
			}
		}

		return array_values(array_unique($types));
	}//end types()
}//end class
