<?php

/**
 * A user-enumeration scope over Nextcloud's defaults (enumeration open), for
 * tests of the people listings that are not about enumeration (REQ-PPL-015).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\UserEnumerationScope;
use OCP\IAppConfig;

/**
 * Mix into a TestCase.
 */
trait OpenEnumeration {
	/**
	 * A scope that answers every core key with its default.
	 *
	 * @return UserEnumerationScope The scope.
	 */
	protected function openEnumeration(): UserEnumerationScope {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default=''): string => $default
		);

		return new UserEnumerationScope(
			config: $config,
			templates: $this->createMock(originalClassName: AdminTemplateService::class)
		);
	}//end openEnumeration()
}//end trait
