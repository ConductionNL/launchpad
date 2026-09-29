<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Schedule dashboard')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<div class="schedule-dialog">
			<label class="schedule-dialog__field">
				{{ t('launchpad', 'Goes live on') }}
				<input
					v-model="goLive"
					type="datetime-local"
					data-testid="schedule-go-live" />
			</label>
			<label class="schedule-dialog__field">
				{{ t('launchpad', 'Comes down on (optional)') }}
				<input
					v-model="takeDown"
					type="datetime-local"
					data-testid="schedule-take-down" />
			</label>
			<p
				v-if="error"
				class="schedule-dialog__error"
				role="alert"
				data-testid="schedule-error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="!goLive && !takeDown"
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

/**
 * ScheduleDashboardDialog: set when a dashboard goes live, when it comes
 * down, or both. Emits `save` with ISO-8601 strings (or null) for
 * `publishAt` and `unpublishAt`; the host shows the server's refusal
 * through the `error` prop.
 *
 * @spec openspec/specs/dashboards/spec.md
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

		/** Why the last attempt was refused. */
		error: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'save'],

	/** @spec openspec/specs/dashboards/spec.md */
	data() {
		return { goLive: '', takeDown: '' }
	},

	methods: {
		t,

		/**
		 * Turn a `datetime-local` value into ISO-8601 with the viewer's offset.
		 *
		 * @param {string} local Value like `2026-10-01T09:00`.
		 * @return {string|null} ISO string, or null when empty.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		toIso(local) {
			if (!local) {
				return null
			}
			const date = new Date(local)
			return Number.isNaN(date.getTime()) ? null : date.toISOString()
		},

		/**
		 * Emit the chosen times.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		save() {
			this.$emit('save', {
				publishAt: this.toIso(this.goLive),
				unpublishAt: this.toIso(this.takeDown),
			})
		},
	},
}
</script>

<style scoped>
.schedule-dialog {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.schedule-dialog__field {
	display: flex;
	flex-direction: column;
}

.schedule-dialog__error {
	color: var(--color-error-text);
}
</style>
