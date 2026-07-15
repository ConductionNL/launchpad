<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="cv-overview" data-test="conditional-visibility-overview">
		<h3>{{ t('launchpad', 'Widgets with visibility rules') }}</h3>
		<p class="cv-overview__hint">
			{{ t('launchpad', 'Every widget placement that has at least one conditional visibility rule, across all dashboards.') }}
		</p>

		<div v-if="loading" class="cv-overview__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent
			v-else-if="rows.length === 0"
			:name="t('launchpad', 'No visibility rules yet')"
			:description="t('launchpad', 'When users add conditional visibility rules to their widgets, they appear here.')">
			<template #icon>
				<EyeOff :size="48" />
			</template>
		</NcEmptyContent>

		<table v-else class="cv-overview__table" data-test="cv-overview-table">
			<thead>
				<tr>
					<th>{{ t('launchpad', 'Dashboard') }}</th>
					<th>{{ t('launchpad', 'Widget type') }}</th>
					<th>{{ t('launchpad', 'Rules') }}</th>
					<th>{{ t('launchpad', 'Include') }}</th>
					<th>{{ t('launchpad', 'Exclude') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="row in rows"
					:key="row.placementId"
					data-test="cv-overview-row">
					<td>{{ row.dashboardName || t('launchpad', 'Unknown dashboard') }}</td>
					<td>{{ row.widgetType }}</td>
					<td>{{ row.ruleCount }}</td>
					<td>{{ row.includeCount }}</td>
					<td>{{ row.excludeCount }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { NcEmptyContent, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import { api } from '../../services/api.js'

/**
 * ConditionalVisibilityOverview — admin table listing every widget placement
 * that carries at least one conditional visibility rule
 * (conditional-visibility spec, Admin Versioning & Audit requirement). Reads
 * `GET /api/admin/widgets/with-rules` once on mount.
 */
export default {
	name: 'ConditionalVisibilityOverview',

	components: {
		NcEmptyContent,
		NcLoadingIcon,
		EyeOff,
	},

	data() {
		return {
			rows: [],
			loading: true,
		}
	},

	/** @spec openspec/specs/conditional-visibility/spec.md */
	created() {
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/specs/conditional-visibility/spec.md */
		async load() {
			this.loading = true
			try {
				const { data } = await api.getAdminWidgetsWithRules()
				// ResponseHelper::success wraps the payload; tolerate both
				// the bare array and the `{ data: [...] }` envelope.
				this.rows = Array.isArray(data) ? data : (data?.data || [])
			} catch (error) {
				console.error('Failed to load conditional visibility overview:', error)
				this.rows = []
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.cv-overview__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.cv-overview__loading {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.cv-overview__table {
	width: 100%;
	border-collapse: collapse;
}

.cv-overview__table th,
.cv-overview__table td {
	text-align: left;
	padding: 8px 12px;
	border-bottom: 1px solid var(--color-border);
}

.cv-overview__table th {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}
</style>
