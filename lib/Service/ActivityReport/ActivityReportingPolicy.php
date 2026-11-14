<?php

/**
 * ActivityReportingPolicy
 *
 * Whether activity reporting on named people is on, and for what purpose (REQ-DWMS-005).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\ActivityReport
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
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ActivityReport;

use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCP\IAppConfig;

/**
 * The administrator's switch for reporting on a named person's activity.
 *
 * Off by default. It can only be turned on with a purpose, which every
 * report on a named person shows; turning it off keeps the purpose, so it
 * reads the same when it is turned on again.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-005-activity-reporting-names-its-purpose
 */
class ActivityReportingPolicy {

	/**
	 * The app config key of the switch.
	 */
	public const KEY_ENABLED = 'activity_reporting_enabled';

	/**
	 * The app config key of the purpose.
	 */
	public const KEY_PURPOSE = 'activity_reporting_purpose';

	/**
	 * The longest purpose kept.
	 */
	public const MAX_PURPOSE_LENGTH = 500;

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Where the switch and the purpose are kept.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.1
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * Whether activity reporting on named people is on: switched on, with a purpose.
	 *
	 * @return bool True when on.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.1
	 */
	public function isEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::KEY_ENABLED, false) === true
			&& $this->purpose() !== '';
	}//end isEnabled()

	/**
	 * The recorded purpose, or '' when none was recorded.
	 *
	 * @return string The purpose.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.1
	 */
	public function purpose(): string {
		return trim($this->appConfig->getValueString(Application::APP_ID, self::KEY_PURPOSE, ''));
	}//end purpose()

	/**
	 * Switch activity reporting on or off.
	 *
	 * @param bool        $enabled Whether to switch it on.
	 * @param string|null $purpose The purpose; null keeps the recorded one.
	 *
	 * @return array{enabled:bool,purpose:string} The policy now.
	 *
	 * @throws InvalidArgumentException When it is switched on without a purpose, or the purpose is too long.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-4.1
	 */
	public function configure(bool $enabled, ?string $purpose=null): array {
		$purpose = trim((string) ($purpose ?? $this->purpose()));
		if ($enabled === true && $purpose === '') {
			throw new InvalidArgumentException('Activity reporting can only be turned on with a purpose.');
		}

		if (mb_strlen($purpose) > self::MAX_PURPOSE_LENGTH) {
			throw new InvalidArgumentException('The purpose is longer than ' . self::MAX_PURPOSE_LENGTH . ' characters.');
		}

		$this->appConfig->setValueString(Application::APP_ID, self::KEY_PURPOSE, $purpose);
		$this->appConfig->setValueBool(Application::APP_ID, self::KEY_ENABLED, $enabled);

		return ['enabled' => $enabled, 'purpose' => $purpose];
	}//end configure()
}//end class
