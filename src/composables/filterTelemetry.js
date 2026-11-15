/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/**
 * filterTelemetry — records how a dashboard's filters are used, as
 * `filter_event` objects in the launchpad register (REQ-CWF-010).
 *
 * A row names the dimensions and operators that changed and where they came
 * from, never the values. Nothing is written unless the organisation switched
 * telemetry on, so the recorder is a no-op by default. A failed write is
 * logged and dropped: telemetry never gets in the way of filtering.
 *
 * @module composables/filterTelemetry
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-010-telemetry-records-drill-down-paths-opt-in
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { logger } from '../utils/logger.js'

/** The register and schema the rows are written to. */
export const FILTER_EVENT_REGISTER = 'launchpad'
export const FILTER_EVENT_SCHEMA = 'filter_event'

/** The event types the `filter_event` schema accepts. */
const EVENT_TYPES = ['apply', 'reset', 'bookmark', 'share']

/**
 * The clauses as a filter delta: dimension, operator, source, source widget
 * and pinned flag, with the value left out.
 *
 * @param {Array<object>} clauses The bus clauses.
 * @return {Array<object>} The delta items.
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-15
 */
export function filterDelta(clauses) {
	return (Array.isArray(clauses) ? clauses : [])
		.filter((clause) => clause && typeof clause.dimension === 'string' && clause.dimension !== '')
		.map((clause) => {
			const item = {
				dimension: clause.dimension,
				operator: typeof clause.operator === 'string' && clause.operator !== '' ? clause.operator : 'eq',
			}
			if (typeof clause.source === 'string' && clause.source !== '') {
				item.source = clause.source
			}
			if (typeof clause.sourceWidgetId === 'string' && clause.sourceWidgetId !== '') {
				item.sourceWidgetId = clause.sourceWidgetId
			}
			item.pinned = clause.pinned === true
			return item
		})
}

/**
 * Write one row to the register.
 *
 * @param {object} row The `filter_event` row.
 * @return {Promise<unknown>}
 */
function postToRegister(row) {
	return axios.post(
		generateUrl('/apps/openregister/api/objects/{register}/{schema}', {
			register: FILTER_EVENT_REGISTER,
			schema: FILTER_EVENT_SCHEMA,
		}),
		row,
	)
}

/**
 * Create the recorder for one open dashboard.
 *
 * @param {object} options The recorder's context.
 * @param {() => boolean} options.enabled Whether the organisation switched telemetry on; asked on every record.
 * @param {string} options.dashboardId The dashboard's uuid.
 * @param {string} options.userId The current user's id.
 * @param {(row: object) => Promise<unknown>} [options.post] Writes a row (the register by default).
 * @param {() => Date} [options.now] The clock.
 * @return {{record: (eventType: string, clauses: Array<object>, widgetId?: string|null) => Promise<object|null>}}
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-15
 */
export function createFilterTelemetry({ enabled, dashboardId, userId, post = postToRegister, now = () => new Date() }) {
	/**
	 * Record one filter change.
	 *
	 * @param {string} eventType `apply`, `reset`, `bookmark` or `share`.
	 * @param {Array<object>} clauses The clauses added or removed.
	 * @param {string|null} [widgetId] The clicked widget, for a widget click.
	 * @return {Promise<object|null>} The row written, or null when nothing was written.
	 */
	async function record(eventType, clauses, widgetId = null) {
		const delta = filterDelta(clauses)
		if (enabled() !== true || !dashboardId || !userId || !EVENT_TYPES.includes(eventType) || delta.length === 0) {
			return null
		}

		const row = { dashboardId, userId, eventType, filterDelta: delta, timestamp: now().toISOString() }
		if (typeof widgetId === 'string' && widgetId !== '') {
			row.widgetId = widgetId
		}

		try {
			await post(row)
		} catch (error) {
			logger.warn('[launchpad] the filter telemetry row was not written', error)
			return null
		}
		return row
	}

	return { record }
}
