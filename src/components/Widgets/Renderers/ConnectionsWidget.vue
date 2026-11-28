<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="connections-widget" data-testid="connections-widget">
		<p v-if="!hasIntegriq" class="connections-widget__state" data-testid="connections-no-app">
			{{ t('launchpad', 'Connection states need the Integrations app') }}
		</p>
		<p v-else-if="used.length === 0" class="connections-widget__state" data-testid="connections-none">
			{{ t('launchpad', 'No widget on this dashboard uses an outside connection') }}
		</p>
		<p v-else-if="state === 'loading'" class="connections-widget__state" data-testid="connections-loading">
			{{ t('launchpad', 'Loading connection states …') }}
		</p>
		<p v-else-if="state === 'error'" class="connections-widget__state" data-testid="connections-error">
			{{ t('launchpad', 'The connection states could not be loaded. Try again later.') }}
		</p>
		<ul v-else class="connections-widget__list">
			<li
				v-for="line in lines"
				:key="line.key"
				class="connections-widget__item"
				:data-testid="'connection-' + line.key">
				<div class="connections-widget__head">
					<span class="connections-widget__title">{{ line.title }}</span>
					<span
						class="connections-widget__status"
						:class="{ 'connections-widget__status--error': line.isError }"
						data-testid="connection-status">{{ line.statusLabel }}</span>
				</div>
				<div class="connections-widget__meta" data-testid="connection-success">
					{{ t('launchpad', 'Last success: {time}', { time: timeText(line.lastSuccessAt) }) }}
				</div>
				<div v-if="line.lastErrorAt" class="connections-widget__meta" data-testid="connection-error">
					{{ t('launchpad', 'Last error: {time}', { time: timeText(line.lastErrorAt) }) }}<template v-if="line.message">
						· {{ line.message }}
					</template>
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { connectionsUsedBy, fetchConnectionRows, joinConnectionStates } from '../../../services/dashboardConnections.js'

/**
 * ConnectionsWidget: the `connections` widget, "Koppelingen van dit
 * dashboard" (board LpManagement). Lists, once each, the declared connections
 * the placements on its own dashboard use, with the status the Integrations
 * page names, the last success and the last error with its message, as
 * integriq holds them. The rows are read through OpenRegister with the
 * viewer's own session; the widget never calls a connection itself.
 *
 * The dashboard's placements come from the `dashboardPlacements` the
 * dashboard view provides. Without integriq the widget says so.
 *
 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export default {
	name: 'ConnectionsWidget',

	inject: {
		/** Which apps behind the integration widgets are enabled for the viewer. */
		integrationApps: { from: 'integrationApps', default: () => ({}) },
		/** The open dashboard's placements, a ref provided by the dashboard view. */
		dashboardPlacements: { from: 'dashboardPlacements', default: null },
	},

	props: {
		// The widget has no settings; declared so it stays out of $attrs.
		// eslint-disable-next-line vue/no-unused-properties
		content: OBJECT_PROP,
		// eslint-disable-next-line vue/no-unused-properties
		placement: OBJECT_PROP,
	},

	data() {
		return { state: 'loading', rows: [] }
	},

	computed: {
		/**
		 * @return {boolean} Whether integriq is enabled for the viewer.
		 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
		 */
		hasIntegriq() {
			const app = this.integrationApps?.integriq
			return app === true || (typeof app === 'string' && app !== '')
		},

		/**
		 * @return {Array<object>} The declared connections this dashboard's placements use.
		 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
		 */
		used() {
			const placements = this.dashboardPlacements?.value ?? this.dashboardPlacements
			return connectionsUsedBy(Array.isArray(placements) ? placements : [])
		},

		/**
		 * @return {Array<object>} The connections in use with their states.
		 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
		 */
		lines() {
			return joinConnectionStates(this.used, this.rows)
		},
	},

	/** @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections */
	async created() {
		if (!this.hasIntegriq) {
			return
		}
		const result = await fetchConnectionRows()
		this.rows = result.rows
		this.state = result.state
	},

	methods: {
		t,

		/**
		 * A time in the reader's locale, or "Not yet".
		 *
		 * @param {string|null} at The ISO time.
		 * @return {string} The time text.
		 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
		 */
		timeText(at) {
			const date = at ? new Date(at) : null
			if (!date || Number.isNaN(date.getTime())) {
				return t('launchpad', 'Not yet')
			}
			return date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
		},
	},
}
</script>

<style scoped>
.connections-widget {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	min-height: 0;
	overflow-y: auto;
}

.connections-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.connections-widget__list {
	display: flex;
	flex-direction: column;
	gap: 10px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.connections-widget__head {
	display: flex;
	justify-content: space-between;
	gap: 8px;
}

.connections-widget__title {
	font-weight: 600;
}

.connections-widget__status--error {
	color: var(--color-error-text);
}

.connections-widget__meta {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>
