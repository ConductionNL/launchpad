<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	ActivityReportingTab: the switch for reporting on a named person, the
	purpose it needs, and the report itself (dashboards-and-who-may-see-them
	REQ-DWMS-005). Off by default. It cannot be turned on without a purpose,
	and the purpose is shown on every report about somebody else.
-->

<template>
	<div class="activity-reporting-tab" data-testid="activity-reporting-tab">
		<h3>{{ t('launchpad', 'Activity reporting') }}</h3>

		<form class="activity-reporting-tab__policy" @submit.prevent="save">
			<NcCheckboxRadioSwitch
				v-model="enabled"
				type="switch"
				data-testid="activity-reporting-enabled">
				{{ t('launchpad', 'Allow reports on a named person\'s activity') }}
			</NcCheckboxRadioSwitch>
			<label class="activity-reporting-tab__purpose">
				{{ t('launchpad', 'Purpose') }}
				<textarea
					v-model="purpose"
					rows="2"
					maxlength="500"
					aria-describedby="activity-reporting-purpose-hint"
					data-testid="activity-reporting-purpose" />
			</label>
			<p id="activity-reporting-purpose-hint" class="activity-reporting-tab__hint">
				{{ t('launchpad', 'Say why you report on a named person, for example workload balancing. Every report about somebody else shows it.') }}
			</p>
			<NcNoteCard v-if="error" type="error" data-testid="activity-reporting-error">
				{{ error }}
			</NcNoteCard>
			<NcButton type="submit" variant="primary" data-testid="activity-reporting-save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
		</form>

		<ActivityReport :subjectEditable="true" />
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcNoteCard } from '@conduction/nextcloud-vue'
import ActivityReport from '../../activity/ActivityReport.vue'
import { getActivityPolicy, saveActivityPolicy } from '../../../services/activityReport.js'

export default {
	name: 'ActivityReportingTab',

	components: { ActivityReport, NcButton, NcCheckboxRadioSwitch, NcNoteCard },

	data() {
		return {
			enabled: false,
			purpose: '',
			error: '',
		}
	},

	/** @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md */
	async created() {
		try {
			const policy = await getActivityPolicy()
			this.enabled = policy.enabled === true
			this.purpose = policy.purpose || ''
		} catch {
			this.error = this.t('launchpad', 'This report could not be loaded.')
		}
	},

	methods: {
		/**
		 * Store the switch and the purpose. Turning it on without a purpose
		 * is refused here and by the server.
		 *
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @return {Promise<void>}
		 */
		async save() {
			this.error = ''
			if (this.enabled && this.purpose.trim() === '') {
				this.error = this.t('launchpad', 'Write down a purpose before you turn this on.')
				return
			}
			try {
				const policy = await saveActivityPolicy(this.enabled, this.purpose)
				this.enabled = policy.enabled === true
				this.purpose = policy.purpose || ''
			} catch {
				this.error = this.t('launchpad', 'Write down a purpose before you turn this on.')
			}
		},
	},
}
</script>

<style scoped>
.activity-reporting-tab__policy {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	max-width: 640px;
	margin-bottom: calc(var(--default-grid-baseline) * 6);
}

.activity-reporting-tab__purpose {
	display: flex;
	flex-direction: column;
}

.activity-reporting-tab__hint {
	color: var(--color-text-maxcontrast);
}
</style>
