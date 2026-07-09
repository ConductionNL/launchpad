<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	AcknowledgementPrompt — forced-delivery read-gate for a compulsory widget.

	Renders a BLOCKING sign-off prompt over a placement that requires a
	mandatory-read acknowledgement (REQ-ACK-002). The recipient MUST click the
	single sign-off affordance to clear it — there is deliberately NO dismiss,
	close, snooze, or "later" control, so the gate cannot be bypassed by any
	means other than acknowledging. A passed deadline is shown but never
	auto-acknowledges (REQ-ACK-002 scenario "Deadline is presented but does not
	auto-acknowledge").
-->

<template>
	<div
		class="launchpad-ack-prompt"
		role="alertdialog"
		aria-modal="true"
		:aria-label="t('launchpad', 'Acknowledgement required')"
		data-testid="acknowledgement-prompt">
		<div class="launchpad-ack-prompt__body">
			<p class="launchpad-ack-prompt__title">
				{{ t('launchpad', 'Acknowledgement required') }}
			</p>
			<p class="launchpad-ack-prompt__text" data-testid="acknowledgement-prompt-text">
				{{ promptText }}
			</p>
			<p
				v-if="placement.acknowledgementDeadline"
				class="launchpad-ack-prompt__deadline"
				:class="{ 'launchpad-ack-prompt__deadline--overdue': isOverdue }"
				data-testid="acknowledgement-prompt-deadline">
				{{ deadlineLabel }}
			</p>
			<p v-if="error" class="launchpad-ack-prompt__error" data-testid="acknowledgement-prompt-error">
				{{ error }}
			</p>
			<div class="launchpad-ack-prompt__actions">
				<NcButton
					type="primary"
					:disabled="submitting"
					data-testid="acknowledgement-signoff"
					@click="signOff">
					{{ t('launchpad', 'I have read and understood') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@conduction/nextcloud-vue'
import { showError } from '@nextcloud/dialogs'
import { useDashboardStore } from '../stores/dashboard.js'

export default {
	name: 'AcknowledgementPrompt',

	components: {
		NcButton,
	},

	props: {
		placement: {
			type: Object,
			required: true,
		},
	},

	emits: ['acknowledged'],

	/**
	 * Bind the dashboard store so the sign-off can record the receipt.
	 *
	 * @return {object} the exposed store handle.
	 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	setup() {
		return { dashboardStore: useDashboardStore() }
	},

	data() {
		return {
			submitting: false,
			error: '',
		}
	},

	computed: {
		/**
		 * The author-supplied sign-off text. Falls back to a generic prompt
		 * when an author enabled the requirement without supplying text.
		 *
		 * @return {string} the prompt text.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		promptText() {
			return this.placement.acknowledgementPrompt
				|| t('launchpad', 'Please confirm you have read this item.')
		},

		/**
		 * Whether the acknowledgement deadline lies in the past. Presented
		 * only — a passed deadline never auto-acknowledges (REQ-ACK-002).
		 *
		 * @return {boolean} true when overdue.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		isOverdue() {
			const deadline = this.placement.acknowledgementDeadline
			if (!deadline) {
				return false
			}
			const today = new Date().toISOString().slice(0, 10)
			return deadline < today
		},

		/**
		 * The localised deadline label.
		 *
		 * @return {string} the deadline label.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		deadlineLabel() {
			return t('launchpad', 'Deadline: {date}', { date: this.placement.acknowledgementDeadline })
		},
	},

	methods: {
		/**
		 * Record the current user's sign-off and clear the gate (REQ-ACK-003).
		 *
		 * @return {Promise<void>} resolves once the receipt is recorded.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		async signOff() {
			if (this.submitting) {
				return
			}
			this.submitting = true
			this.error = ''
			try {
				await this.dashboardStore.acknowledgePlacement(this.placement)
				this.$emit('acknowledged', this.placement)
			} catch (e) {
				this.error = t('launchpad', 'Could not record your acknowledgement. Please try again.')
				showError(this.error)
			} finally {
				this.submitting = false
			}
		},
	},
}
</script>

<style scoped>
.launchpad-ack-prompt {
	position: absolute;
	inset: 0;
	z-index: 5;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 12px;
	background: var(--color-main-background-translucent, rgba(255, 255, 255, 0.9));
	backdrop-filter: blur(2px);
	border-radius: var(--border-radius-large, 8px);
}

.launchpad-ack-prompt__body {
	max-width: 420px;
	text-align: center;
}

.launchpad-ack-prompt__title {
	font-weight: 700;
	margin-bottom: 6px;
}

.launchpad-ack-prompt__text {
	margin-bottom: 8px;
	color: var(--color-main-text);
}

.launchpad-ack-prompt__deadline {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}

.launchpad-ack-prompt__deadline--overdue {
	color: var(--color-error);
	font-weight: 600;
}

.launchpad-ack-prompt__error {
	color: var(--color-error);
	margin-bottom: 8px;
}

.launchpad-ack-prompt__actions {
	display: flex;
	justify-content: center;
}
</style>
