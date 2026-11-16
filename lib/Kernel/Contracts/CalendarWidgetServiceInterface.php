<?php

/**
 * Contract of CalendarWidgetService
 *
 * The public surface of CalendarWidgetService that other components call, so they depend
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
 * Contract of CalendarWidgetService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface CalendarWidgetServiceInterface {
	/**
	 * Aggregate events for a single placement configuration.
	 *
	 * Combines internal NC calendar events and external ICS events.
	 * Failures on individual sources are logged and reported via
	 * the `failures` array but never abort the whole call.
	 *
	 * @param array $config Parsed calendar widget config — keys:
	 *                      internalCalendars: string[],
	 *                      externalIcsUrls: string[].
	 * @param string $from ISO 8601 start (inclusive).
	 * @param string $to ISO 8601 end (inclusive).
	 *
	 * @return array{events: array<int, array<string, mixed>>, failures: array<int, string>}
	 *
	 * @spec openspec/specs/calendar-widget/spec.md
	 */
	public function getEvents(array $config, string $from, string $to): array;

	/**
	 * List the current user's internal calendars for the config-form picker
	 * (REQ-CAL-002). Each entry's `key` is the identifier
	 * {@see fetchInternalEvents} filters on (the search result's
	 * `calendar-key`). Returns an empty list when the Calendar app is absent.
	 *
	 * @param string $userId The current user id.
	 *
	 * @return array<int, array{key: string, name: string, color: string}>
	 *
	 * @spec openspec/specs/calendar-widget/spec.md
	 */
	public function listCalendars(string $userId): array;
}//end interface
