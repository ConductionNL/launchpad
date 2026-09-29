<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<nav
		v-if="crumbs.length > 1"
		class="dashboard-breadcrumb"
		:aria-label="t('launchpad', 'Dashboard location')"
		data-testid="dashboard-breadcrumb">
		<ol class="dashboard-breadcrumb__list">
			<li
				v-for="(crumb, index) in crumbs"
				:key="crumb.uuid ?? `hidden-${index}`">
				<span v-if="index === crumbs.length - 1" aria-current="page">{{
					crumb.name
				}}</span>
				<span
					v-else-if="crumb.hidden"
					:title="t('launchpad', 'A dashboard you cannot open')"
					>…</span
				>
				<button
					v-else
					type="button"
					class="dashboard-breadcrumb__link"
					@click="$emit('navigate', crumb.uuid)">
					{{ crumb.name }}
				</button>
				<span
					v-if="index < crumbs.length - 1"
					class="dashboard-breadcrumb__separator"
					aria-hidden="true"
					>/</span
				>
			</li>
		</ol>
	</nav>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * DashboardBreadcrumb: where a child dashboard sits, root to leaf, from
 * the server's breadcrumbs (the dashboard read). An ancestor the viewer may
 * not open shows as "…", never by name. Hidden for a top-level dashboard.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
export default {
	name: 'DashboardBreadcrumb',

	props: {
		/** The active dashboard (`id`, `uuid`, `parentUuid`). */
		dashboard: {
			type: Object,
			default: null,
		},
	},

	emits: ['navigate'],

	/** @spec openspec/specs/dashboards/spec.md */
	data() {
		return { crumbs: [] }
	},

	watch: {
		dashboard: {
			immediate: true,
			/**
			 * Read the crumbs whenever another child dashboard opens.
			 *
			 * @param {object|null} dashboard The active dashboard.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			handler(dashboard) {
				this.load(dashboard)
			},
		},
	},

	methods: {
		t,

		/**
		 * Fetch the breadcrumbs of a child dashboard; clear them otherwise.
		 *
		 * @param {object|null} dashboard The active dashboard.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async load(dashboard) {
			if (
				!dashboard?.parentUuid
				|| dashboard.id === undefined
				|| dashboard.id === null
			) {
				this.crumbs = []
				return
			}
			try {
				const { data } = await api.getDashboardById(dashboard.id)
				this.crumbs = Array.isArray(data?.breadcrumbs)
					? data.breadcrumbs
					: []
			} catch (error) {
				logger.warn(
					'[DashboardBreadcrumb] could not read the breadcrumbs',
					error,
				)
				this.crumbs = []
			}
		},
	},
}
</script>

<style scoped>
.dashboard-breadcrumb {
	margin: 0 8px 8px;
}

.dashboard-breadcrumb__list {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.dashboard-breadcrumb__link {
	padding: 0;
	border: none;
	background: none;
	color: var(--color-primary-element);
	text-decoration: underline;
	cursor: pointer;
}

.dashboard-breadcrumb__separator {
	margin-inline-start: 4px;
	color: var(--color-text-maxcontrast);
}
</style>
