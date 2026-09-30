<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Schedule {name}', { name: dashboard?.name || '' })"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<div class="schedule-dashboard-form">
			<label class="schedule-dashboard-form__field">
				{{ t('launchpad', 'Goes live on') }}
				<input
					v-model="publishAtInput"
					type="datetime-local"
					data-testid="schedule-publish-at" />
			</label>
			<label class="schedule-dashboard-form__field">
				{{ t('launchpad', 'Comes down on (optional)') }}
				<input
					v-model="unpublishAtInput"
					type="datetime-local"
					data-testid="schedule-unpublish-at" />
			</label>
			<p
				v-if="problem"
				class="schedule-dashboard-form__problem"
				role="alert"
				data-testid="schedule-problem">
				{{ problem }}
			</p>
		</div>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving"
				data-testid="schedule-save"
				@click="save">
				{{ t('launchpad', 'Schedule') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { useDashboardStore } from '../stores/dashboard.js'
import { fromLocalInput, toLocalInput } from '../utils/scheduleTime.js'

/**
 * The server's refusal messages (DashboardService::ERR_SCHEDULE_*), read
 * back into the reader's language.
 *
 * @return {Record<string, string>} Server message to translated text.
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
function refusals() {
	return {
		'publishAt must be a future timestamp': t(
			'launchpad',
			'The go-live time must be in the future.',
		),
		'unpublishAt must be a future timestamp': t(
			'launchpad',
			'The take-down time must be in the future.',
		),
		'unpublishAt must be later than publishAt': t(
			'launchpad',
			'The take-down time must be later than the go-live time.',
		),
	}
}

/**
 * ScheduleDashboardDialog: set when a dashboard goes live and when it comes
 * down (REQ-SCHEDUI-001, REQ-SCHEDUI-002). ADR-004 modal isolation.
 *
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
export default {
	name: 'ScheduleDashboardDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		dashboard: {
			type: Object,
			default: null,
		},
	},

	emits: ['update:open', 'scheduled'],

	data() {
		return {
			publishAtInput: toLocalInput(this.dashboard?.publishAt),
			unpublishAtInput: toLocalInput(this.dashboard?.unpublishAt),
			problem: '',
			saving: false,
		}
	},

	watch: {
		/**
		 * Start from the dashboard's current times each time the dialog opens.
		 *
		 * @param {boolean} isOpen Whether the dialog is now open.
		 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
		 */
		open(isOpen) {
			if (isOpen) {
				this.publishAtInput = toLocalInput(this.dashboard?.publishAt)
				this.unpublishAtInput = toLocalInput(this.dashboard?.unpublishAt)
				this.problem = ''
			}
		},
	},

	methods: {
		t,

		/**
		 * Send the times; on a refusal show the server's reason and stay open.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
		 */
		async save() {
			const publishAt = fromLocalInput(this.publishAtInput)
			const unpublishAt = fromLocalInput(this.unpublishAtInput)
			if (publishAt === null && unpublishAt === null) {
				this.problem = t(
					'launchpad',
					'Enter a go-live time, a take-down time or both.',
				)
				return
			}
			this.problem = ''
			this.saving = true
			try {
				const updated = await useDashboardStore().scheduleDashboard(
					this.dashboard.uuid,
					publishAt,
					unpublishAt,
				)
				this.$emit('scheduled', updated)
				this.$emit('update:open', false)
			} catch (error) {
				const message = error?.response?.data?.message ?? ''
				this.problem =
					refusals()[message]
					?? t('launchpad', 'The schedule could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.schedule-dashboard-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.schedule-dashboard-form__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.schedule-dashboard-form__problem {
	color: var(--color-error-text);
}
</style>
