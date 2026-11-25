<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	TemplateResyncModal — the rollout window behind "Push changes" on a
	template (board LpBeheerSjablonen; admin-templates REQ-RESYNC-001..008).

	On open it runs a dry run for the preselected choice (Keep personal
	changes, `merge`) and says, in the board's words, what the template gained
	and lost since the copies were last synced and how many people changed
	their copy. "How to update?" offers the three strategies with their
	consequence; choosing another one runs a fresh dry run, so the push button
	only ever applies the plan of the choice on screen. The button names the
	number of dashboards it pushes to.
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal"
		size="normal"
		:name="title"
		@close="handleClose">
		<div class="launchpad-resync" data-testid="template-resync-dialog">
			<h2 data-testid="template-resync-title">
				{{ title }}
			</h2>

			<p
				v-if="plan"
				class="launchpad-resync__hint"
				data-testid="template-resync-template">
				{{
					t('launchpad', 'Template "{name}" has {count} copies.', {
						name: template ? template.name : '',
						count: plan.totalCopies,
					})
				}}
			</p>

			<ul
				v-if="plan"
				class="launchpad-resync__changes"
				data-testid="template-resync-changes">
				<li v-if="gained.length > 0" data-testid="template-resync-gained">
					{{
						t('launchpad', 'Added since the last sync: {widgets}', {
							widgets: gained.join(', '),
						})
					}}
				</li>
				<li v-if="lost.length > 0" data-testid="template-resync-lost">
					{{
						t('launchpad', 'Removed since the last sync: {widgets}', {
							widgets: lost.join(', '),
						})
					}}
				</li>
				<li
					v-if="gained.length === 0 && lost.length === 0"
					data-testid="template-resync-no-changes">
					{{
						t(
							'launchpad',
							'No widgets were added or removed since the last sync.',
						)
					}}
				</li>
			</ul>

			<fieldset
				class="launchpad-resync__choices"
				data-testid="template-resync-strategy">
				<legend class="launchpad-resync__legend">
					{{ t('launchpad', 'How to update?') }}
				</legend>
				<label
					v-for="choice in choices"
					:key="choice.id"
					class="launchpad-resync__choice"
					:class="{ 'launchpad-resync__choice--active': strategy === choice.id }">
					<input
						type="radio"
						name="launchpad-resync-strategy"
						:value="choice.id"
						:checked="strategy === choice.id"
						:disabled="applying"
						:data-testid="`template-resync-choice-${choice.id}`"
						@change="chooseStrategy(choice.id)">
					<span class="launchpad-resync__choice-text">
						<span class="launchpad-resync__choice-label">{{ choice.label }}</span>
						<span class="launchpad-resync__choice-consequence">{{ choice.consequence }}</span>
					</span>
				</label>
			</fieldset>

			<p
				v-if="plan && plan.changedByOwnerCount > 0"
				class="launchpad-resync__changed"
				data-testid="template-resync-changed-count">
				{{
					t('launchpad', '{count} people changed their dashboard.', {
						count: plan.changedByOwnerCount,
					})
				}}
			</p>

			<div
				v-if="error"
				class="launchpad-resync__error"
				role="alert"
				data-testid="template-resync-error">
				{{ error }}
			</div>

			<div v-if="loading" class="launchpad-resync__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<div
				v-if="applied"
				class="launchpad-resync__success"
				role="status"
				data-testid="template-resync-success">
				<span v-if="applied.async">
					{{
						t(
							'launchpad',
							'Re-sync queued — {count} copies will be updated in the background.',
							{ count: applied.affectedCount },
						)
					}}
				</span>
				<span v-else>
					{{
						t('launchpad', 'Re-sync applied — {count} copies updated.', {
							count: applied.affectedCount,
						})
					}}
				</span>
			</div>

			<div class="launchpad-resync__actions">
				<NcButton
					variant="secondary"
					data-testid="template-resync-cancel"
					@click="handleClose">
					{{ t('launchpad', 'Close') }}
				</NcButton>
				<NcButton
					variant="primary"
					data-testid="template-resync-apply"
					:disabled="!canApply"
					@click="apply">
					{{ pushLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcLoadingIcon,
	NcModal,
} from '@conduction/nextcloud-vue'
import { FLAG_PROP, NULLABLE_OBJECT_PROP } from '../constants/propDefinitions.js'
import { api } from '../services/api.js'

/** The choice the window opens with (REQ-RESYNC-008). */
const DEFAULT_STRATEGY = 'merge'

/**
 * The rollout window: what a push changes, how to update, and the push.
 *
 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
 */
export default {
	name: 'TemplateResyncModal',

	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
	},

	props: {
		open: FLAG_PROP,

		template: NULLABLE_OBJECT_PROP,
	},

	emits: ['close', 'resynced'],

	data() {
		return {
			strategy: DEFAULT_STRATEGY,

			choices: [
				{
					id: 'add_only',
					label: t('launchpad', 'Only add new widgets'),
					consequence: t(
						'launchpad',
						'Nothing disappears; personal layout stays.',
					),
				},
				{
					id: 'merge',
					label: t('launchpad', 'Keep personal changes'),
					consequence: t(
						'launchpad',
						'The template wins, except what someone moved or added themselves.',
					),
				},
				{
					id: 'overwrite',
					label: t('launchpad', 'Replace everything with the template'),
					consequence: t('launchpad', 'Personal changes are lost.'),
				},
			],

			loading: false,
			applying: false,
			error: '',
			plan: null,
			applied: null,
			requestSeq: 0,
		}
	},

	computed: {
		/**
		 * The push button applies only a plan computed for the choice on
		 * screen (REQ-RESYNC-002).
		 *
		 * @return {boolean} whether the push button is enabled.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		canApply() {
			return (
				this.plan !== null
				&& this.plan.strategy === this.strategy
				&& this.loading === false
				&& this.applying === false
			)
		},

		/**
		 * The window's title, naming the number of dashboards once known.
		 *
		 * @return {string} the title.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		title() {
			if (!this.plan) {
				return t('launchpad', 'Push changes')
			}
			return t('launchpad', 'Push changes to {count} dashboards', {
				count: this.plan.totalCopies,
			})
		},

		/**
		 * The confirm button's label, naming the number of dashboards.
		 *
		 * @return {string} the label.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		pushLabel() {
			if (!this.plan) {
				return t('launchpad', 'Push changes')
			}
			return t('launchpad', 'Push to {count} dashboards', {
				count: this.plan.totalCopies,
			})
		},

		/**
		 * Widgets the template gained since the copies were last synced.
		 *
		 * @return {string[]} the widget names.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		gained() {
			return Array.isArray(this.plan?.gained) ? this.plan.gained : []
		},

		/**
		 * Widgets the template lost since the copies were last synced.
		 *
		 * @return {string[]} the widget names.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		lost() {
			return Array.isArray(this.plan?.lost) ? this.plan.lost : []
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * On open: back to Keep personal changes and a fresh dry run, so a
			 * plan from an earlier template can never be applied.
			 *
			 * @param {boolean} isOpen Whether the modal is now open.
			 * @return {void}
			 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
			 */
			handler(isOpen) {
				if (isOpen) {
					this.resetState()
					this.runDryRun()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Clear the plan, result and error, and preselect Keep personal changes.
		 *
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		resetState() {
			this.strategy = DEFAULT_STRATEGY
			this.loading = false
			this.applying = false
			this.error = ''
			this.plan = null
			this.applied = null
		},

		/**
		 * Another choice: its own dry run replaces the shown plan.
		 *
		 * @param {string} id The strategy id (`add_only`, `merge`, `overwrite`).
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		chooseStrategy(id) {
			if (id === this.strategy) {
				return
			}
			this.strategy = id
			this.applied = null
			this.runDryRun()
		},

		/**
		 * Compute the plan for the current choice without changing anything
		 * (REQ-RESYNC-002). A slower answer for an earlier choice is dropped.
		 *
		 * @return {Promise<void>} resolves once the plan is loaded.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		async runDryRun() {
			if (!this.template) {
				return
			}

			const seq = ++this.requestSeq
			const strategy = this.strategy
			this.loading = true
			this.error = ''

			try {
				const { data } = await api.resyncAdminTemplate(this.template.id, {
					strategy,
					dryRun: true,
				})
				if (seq !== this.requestSeq) {
					return
				}
				this.plan = { ...data, strategy }
			} catch {
				if (seq !== this.requestSeq) {
					return
				}
				this.plan = null
				this.error = t('launchpad', 'Could not compute the re-sync plan.')
			} finally {
				if (seq === this.requestSeq) {
					this.loading = false
				}
			}
		},

		/**
		 * Push the plan on screen (REQ-RESYNC-001, REQ-RESYNC-005).
		 *
		 * @return {Promise<void>} resolves once the re-sync request completes.
		 * @spec openspec/changes/archive/2026-11-25-template-rollout-choices/specs/admin-templates/spec.md#requirement-req-resync-008-the-rollout-window-says-what-will-happen
		 */
		async apply() {
			if (!this.template || !this.canApply) {
				return
			}

			this.applying = true
			this.error = ''

			try {
				const { data } = await api.resyncAdminTemplate(this.template.id, {
					strategy: this.strategy,
					dryRun: false,
				})
				this.applied = data
				this.plan = null
				this.$emit('resynced', data)
			} catch {
				this.error = t('launchpad', 'Could not apply the re-sync.')
			} finally {
				this.applying = false
			}
		},

		/**
		 * Close the window.
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
	margin-bottom: 8px;
}

.launchpad-resync__changes {
	margin: 0 0 16px;
	padding-inline-start: 20px;
	list-style: disc;
}

.launchpad-resync__choices {
	border: none;
	margin: 0 0 12px;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.launchpad-resync__legend {
	font-weight: 600;
	margin-bottom: 8px;
}

.launchpad-resync__choice {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	cursor: pointer;
}

.launchpad-resync__choice--active {
	border-color: var(--color-primary-element);
	background-color: var(--color-primary-element-light);
}

.launchpad-resync__choice input {
	margin-top: 3px;
}

.launchpad-resync__choice-text {
	display: flex;
	flex-direction: column;
}

.launchpad-resync__choice-label {
	font-weight: 600;
}

.launchpad-resync__choice-consequence {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.launchpad-resync__changed {
	margin: 8px 0;
}

.launchpad-resync__error {
	color: var(--color-error);
	padding: 8px 0;
}

.launchpad-resync__loading {
	display: flex;
	justify-content: center;
	padding: 12px;
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
