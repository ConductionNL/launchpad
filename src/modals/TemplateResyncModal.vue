<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	TemplateResyncModal — admin "Re-sync to existing copies" dialog
	(admin-template-resync REQ-RESYNC-001..005).

	Lets an admin push an updated template onto its already-provisioned user
	copies. A strategy choice (overwrite / merge) selects between replacing
	the whole layout or reconciling template-origin placements while keeping
	each user's personally-added widgets. Apply is gated behind a completed
	Dry-run for the currently-selected strategy — switching strategy or
	re-opening the dialog resets the plan so a stale preview can never be
	applied.
-->

<template>
	<NcModal
		v-if="open"
		size="large"
		:name="t('launchpad', 'Re-sync to existing copies')"
		@close="handleClose">
		<div class="launchpad-resync" data-testid="template-resync-dialog">
			<h2>{{ t('launchpad', 'Re-sync to existing copies') }}</h2>
			<p class="launchpad-resync__hint">
				{{ t('launchpad', 'Push this template’s current layout to everyone who already has a copy.') }}
			</p>

			<div class="launchpad-resync__field">
				<NcSelect
					v-model="strategy"
					:input-label="t('launchpad', 'Strategy')"
					:options="strategyOptions"
					label="label"
					track-by="id"
					:clearable="false"
					data-testid="template-resync-strategy"
					@input="handleStrategyChange" />
				<p class="launchpad-resync__strategy-hint">
					{{ strategyHint }}
				</p>
			</div>

			<div v-if="error" class="launchpad-resync__error" data-testid="template-resync-error">
				{{ error }}
			</div>

			<div v-if="loading" class="launchpad-resync__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<template v-else-if="plan">
				<div class="launchpad-resync__summary" data-testid="template-resync-summary">
					{{ t('launchpad', '{count} of {total} copies affected', { count: plan.affectedCount, total: plan.totalCopies }) }}
				</div>

				<table v-if="plan.copies.length > 0" class="launchpad-resync__table">
					<thead>
						<tr>
							<th>{{ t('launchpad', 'User') }}</th>
							<th>{{ t('launchpad', 'Add') }}</th>
							<th>{{ t('launchpad', 'Update') }}</th>
							<th>{{ t('launchpad', 'Remove') }}</th>
							<th>{{ t('launchpad', 'Unchanged') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="copy in plan.copies"
							:key="copy.dashboardId"
							:data-testid="`template-resync-copy-${copy.dashboardId}`">
							<td>{{ copy.userId }}</td>
							<td>{{ copy.toAdd }}</td>
							<td>{{ copy.toUpdate }}</td>
							<td>{{ copy.toRemove }}</td>
							<td>{{ copy.toPreserve }}</td>
						</tr>
					</tbody>
				</table>
			</template>

			<div v-if="applied" class="launchpad-resync__success" data-testid="template-resync-success">
				<span v-if="applied.async">
					{{ t('launchpad', 'Re-sync queued — {count} copies will be updated in the background.', { count: applied.affectedCount }) }}
				</span>
				<span v-else>
					{{ t('launchpad', 'Re-sync applied — {count} copies updated.', { count: applied.affectedCount }) }}
				</span>
			</div>

			<div class="launchpad-resync__actions">
				<NcButton type="secondary" data-testid="template-resync-cancel" @click="handleClose">
					{{ t('launchpad', 'Close') }}
				</NcButton>
				<NcButton
					type="secondary"
					data-testid="template-resync-dryrun"
					:disabled="loading"
					@click="runDryRun">
					{{ t('launchpad', 'Dry-run') }}
				</NcButton>
				<NcButton
					type="primary"
					data-testid="template-resync-apply"
					:disabled="!canApply"
					@click="apply">
					{{ t('launchpad', 'Apply') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcSelect, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { api } from '../services/api.js'

/**
 * Admin dialog for pushing a template update to its provisioned copies.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
export default {
	name: 'TemplateResyncModal',

	components: {
		NcModal,
		NcButton,
		NcSelect,
		NcLoadingIcon,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		template: {
			type: Object,
			default: null,
		},
	},

	emits: ['close', 'resynced'],

	data() {
		return {
			strategy: {
				id: 'overwrite',
				label: t('launchpad', 'Overwrite — replace the whole layout'),
			},
			strategyOptions: [
				{ id: 'overwrite', label: t('launchpad', 'Overwrite — replace the whole layout') },
				{ id: 'merge', label: t('launchpad', 'Merge — keep personally added widgets') },
			],
			loading: false,
			applying: false,
			error: '',
			plan: null,
			applied: null,
		}
	},

	computed: {
		/**
		 * Apply is disabled until a dry-run for the currently-selected
		 * strategy has been reviewed (REQ-RESYNC-002 / tasks.md).
		 *
		 * @return {boolean} whether the Apply button is enabled.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		canApply() {
			return this.plan !== null && this.loading === false && this.applying === false
		},

		/**
		 * Human-readable explanation of the selected strategy's semantics.
		 *
		 * @return {string} the strategy hint text.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		strategyHint() {
			if (this.strategy?.id === 'merge') {
				return t('launchpad', 'Adds and updates template widgets and restores compulsory widgets, but keeps widgets users added themselves.')
			}
			return t('launchpad', 'Replaces each copy’s entire layout with the template’s current layout — personally-added widgets will be removed.')
		},
	},

	watch: {
		/**
		 * Reset all report/result state whenever the dialog opens or
		 * closes, so a stale plan from a previous template can never be
		 * applied to a different one.
		 *
		 * @param {boolean} isOpen whether the modal is now open.
		 * @return {void}
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		open: {
			immediate: true,
			/** @spec openspec/specs/admin-templates/spec.md */
			handler(isOpen) {
				if (isOpen) {
					this.resetState()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Clear the plan/result/error state (REQ-RESYNC-002 — a fresh
		 * dry-run must always be reviewed before Apply unlocks).
		 *
		 * @return {void}
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		resetState() {
			this.loading = false
			this.applying = false
			this.error = ''
			this.plan = null
			this.applied = null
		},

		/**
		 * Strategy changed — any previously-reviewed plan applied to the
		 * old strategy is no longer valid.
		 *
		 * @return {void}
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		handleStrategyChange() {
			this.plan = null
			this.applied = null
			this.error = ''
		},

		/**
		 * Compute the re-sync plan without mutating anything
		 * (REQ-RESYNC-002).
		 *
		 * @return {Promise<void>} resolves once the plan is loaded.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		async runDryRun() {
			if (!this.template) {
				return
			}

			this.loading = true
			this.error = ''
			this.applied = null

			try {
				const { data } = await api.resyncAdminTemplate(this.template.id, {
					strategy: this.strategy.id,
					dryRun: true,
				})
				this.plan = data
			} catch (e) {
				this.error = t('launchpad', 'Could not compute the re-sync plan.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * Apply the reviewed plan (REQ-RESYNC-001, REQ-RESYNC-005).
		 *
		 * @return {Promise<void>} resolves once the re-sync request completes.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		async apply() {
			if (!this.template || !this.canApply) {
				return
			}

			this.applying = true
			this.error = ''

			try {
				const { data } = await api.resyncAdminTemplate(this.template.id, {
					strategy: this.strategy.id,
					dryRun: false,
				})
				this.applied = data
				this.plan = null
				this.$emit('resynced', data)
			} catch (e) {
				this.error = t('launchpad', 'Could not apply the re-sync.')
			} finally {
				this.applying = false
			}
		},

		/**
		 * Close the dialog.
		 *
		 * @return {void}
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		handleClose() {
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.launchpad-resync {
	padding: 16px;
}

.launchpad-resync__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.launchpad-resync__field {
	margin-bottom: 16px;
}

.launchpad-resync__strategy-hint {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	margin-top: 4px;
}

.launchpad-resync__error {
	color: var(--color-error);
	padding: 8px 0;
}

.launchpad-resync__loading {
	display: flex;
	justify-content: center;
	padding: 24px;
}

.launchpad-resync__summary {
	font-weight: 600;
	margin: 12px 0;
}

.launchpad-resync__table {
	width: 100%;
	border-collapse: collapse;
	margin-bottom: 12px;
}

.launchpad-resync__table th,
.launchpad-resync__table td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.launchpad-resync__success {
	color: var(--color-success);
	padding: 8px 0;
	font-weight: 600;
}

.launchpad-resync__actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 24px;
}
</style>
