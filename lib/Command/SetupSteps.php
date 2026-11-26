<?php

/**
 * SetupSteps
 *
 * Steps 3 to 5 of `occ launchpad:setup`, applied through the services the
 * wizard uses: the demo
 * showcases (Step 3), the role assignments (Step 4) and the global footer
 * (Step 5). They are the services the wizard modal and the admin page use,
 * so a YAML config stores exactly what the browser would store.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
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

namespace OCA\LaunchPad\Command;

use InvalidArgumentException;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\RoleService;

/**
 * Applies the demo data, admin role and footer steps of a setup.yaml.
 *
 * @spec openspec/specs/setup-wizard/spec.md#requirement-req-wiz-010-cli-command-for-non-interactive-setup-iac-friendly
 */
class SetupSteps {
	/**
	 * Who a role row written by this command names as its assigner.
	 *
	 * @var string
	 */
	public const ASSIGNED_BY = 'occ';

	/**
	 * Constructor.
	 *
	 * @param DemoShowcasesService $showcases Installs the demo showcases (Step 3).
	 * @param RoleService          $roles     Writes role assignments (Step 4).
	 * @param FooterService        $footer    Stores the global footer (Step 5).
	 *
	 * @spec openspec/specs/setup-wizard/spec.md#requirement-req-wiz-010-cli-command-for-non-interactive-setup-iac-friendly
	 */
	public function __construct(
		private readonly DemoShowcasesService $showcases,
		private readonly RoleService $roles,
		private readonly FooterService $footer,
	) {
	}//end __construct()

	/**
	 * Apply Step 3 — install the listed demo showcases (REQ-WIZ-005,
	 * REQ-WIZ-010). A showcase that is already installed is not installed
	 * again.
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 *
	 * @return string The outcome: done, already configured or skipped.
	 *
	 * @throws InvalidArgumentException When `demo_packages` is not a list of strings.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md#requirement-req-wiz-010-cli-command-for-non-interactive-setup-iac-friendly
	 */
	public function applyDemoData(array $config): string {
		if (array_key_exists(key: 'demo_packages', array: $config) === false) {
			return 'skipped (not in config)';
		}

		$showcaseIds = self::stringList(value: $config['demo_packages'], key: 'demo_packages');
		$installed   = 0;
		foreach ($showcaseIds as $showcaseId) {
			$result = $this->showcases->installShowcase(showcaseId: $showcaseId);
			if ($result['alreadyInstalled'] === false) {
				$installed++;
			}
		}

		if ($installed === 0) {
			return 'already configured, skipping';
		}

		return 'done';
	}//end applyDemoData()

	/**
	 * Apply Step 4 — give one group the Dashboard Admin role (REQ-WIZ-006,
	 * REQ-WIZ-010). A group that already holds it gets no second row.
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 *
	 * @return string The outcome: done, already configured or skipped.
	 *
	 * @throws InvalidArgumentException When `admin_role_group` is not a non-empty string.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md#requirement-req-wiz-010-cli-command-for-non-interactive-setup-iac-friendly
	 */
	public function applyAdminRole(array $config): string {
		if (array_key_exists(key: 'admin_role_group', array: $config) === false) {
			return 'skipped (not in config)';
		}

		$groupId = $config['admin_role_group'];
		if (is_string($groupId) === false || trim(string: $groupId) === '') {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'admin_role_group' must be a group id"
			);
		}

		if ($this->roles->hasGroupRole(groupId: $groupId, role: RoleAssignment::ROLE_ADMIN) === true) {
			return 'already configured, skipping';
		}

		$this->roles->assignRole(
			userId: null,
			groupId: $groupId,
			role: RoleAssignment::ROLE_ADMIN,
			assignedBy: self::ASSIGNED_BY
		);
		return 'done';
	}//end applyAdminRole()

	/**
	 * Apply Step 5 — store the structured footer config (REQ-WIZ-007,
	 * REQ-WIZ-010), validated as the admin page's save validates it.
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 *
	 * @return string The outcome: done, already configured or skipped.
	 *
	 * @throws InvalidArgumentException When `footer_config` is not a map or fails validation.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md#requirement-req-wiz-010-cli-command-for-non-interactive-setup-iac-friendly
	 */
	public function applyFooter(array $config): string {
		if (array_key_exists(key: 'footer_config', array: $config) === false) {
			return 'skipped (not in config)';
		}

		$footer = $config['footer_config'];
		if (is_array($footer) === false || ($footer !== [] && array_is_list(array: $footer) === true)) {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'footer_config' must be a map"
			);
		}

		$current = ($this->footer->getGlobalSettings()['footerConfig'] ?? null);
		if ($current === $footer) {
			return 'already configured, skipping';
		}

		try {
			$this->footer->updateGlobalSettings(patch: ['footerConfig' => $footer]);
		} catch (InvalidArgumentException $e) {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'footer_config': " . $e->getMessage(),
				previous: $e
			);
		}

		return 'done';
	}//end applyFooter()

	/**
	 * The value of a YAML key that must be a list of non-empty strings.
	 *
	 * @param mixed  $value The parsed value.
	 * @param string $key   The YAML key, for the error.
	 *
	 * @return list<string> The strings.
	 *
	 * @throws InvalidArgumentException When the value is not such a list.
	 */
	private static function stringList(mixed $value, string $key): array {
		$error = "Invalid setup.yaml: '" . $key . "' must be a list of strings";
		if (is_array($value) === false || array_is_list(array: $value) === false) {
			throw new InvalidArgumentException(message: $error);
		}

		foreach ($value as $item) {
			if (is_string($item) === false || $item === '') {
				throw new InvalidArgumentException(message: $error);
			}
		}

		return $value;
	}//end stringList()
}//end class
