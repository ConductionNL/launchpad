<?php

/**
 * LaunchPadPersonal
 *
 * Personal settings section where a person fills their own custom profile
 * fields, such as their expertise tags (widgets-people-expertise-and-fields,
 * REQ-PEX-002). The form loads its fields from `GET /api/profile-fields/me`.
 * Shown only when an administrator defined at least one field.
 *
 * @category  Settings
 * @package   OCA\LaunchPad\Settings
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

namespace OCA\LaunchPad\Settings;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

/**
 * Personal settings form for custom profile fields.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class LaunchPadPersonal implements ISettings {
	/**
	 * Constructor.
	 *
	 * @param ProfileFieldService $profileFields Profile field service.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct(
		private readonly ProfileFieldService $profileFields,
	) {
	}//end __construct()

	/**
	 * The form: an empty mount point for the personal settings bundle.
	 *
	 * @return TemplateResponse
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) Util::addScript() is how Nextcloud loads a settings bundle.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getForm(): TemplateResponse {
		Util::addScript(application: Application::APP_ID, file: 'launchpad-personal');

		return new TemplateResponse(appName: Application::APP_ID, templateName: 'settings/personal');
	}//end getForm()

	/**
	 * The section: the personal information page, next to the standard profile.
	 * Null hides the form while no field is defined.
	 *
	 * @return string|null
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getSection(): ?string {
		if ($this->profileFields->getDefinitions() === []) {
			return null;
		}

		return 'personal-info';
	}//end getSection()

	/**
	 * Position within the section.
	 *
	 * @return int
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function getPriority(): int {
		return 90;
	}//end getPriority()
}//end class
