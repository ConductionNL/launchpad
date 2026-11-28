<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		class="launchpad-modal"
		:name="t('launchpad', 'LaunchPad setup wizard')"
		size="large"
		@close="onClose">
		<div class="setup-wizard" data-test="setup-wizard">
			<header class="setup-wizard__header">
				<h2>{{ t('launchpad', 'LaunchPad setup wizard') }}</h2>
				<p class="setup-wizard__counter" data-test="setup-wizard-counter">
					{{
						t('launchpad', 'Step {n} / {total}', {
							n: currentStep,
							total: totalSteps,
						})
					}}
				</p>
			</header>

			<section class="setup-wizard__body">
				<!-- Step 1 — Welcome -->
				<div v-if="currentStep === 1" class="setup-wizard__step">
					<h3>{{ t('launchpad', 'Welcome') }}</h3>
					<p>
						{{
							t(
								'launchpad',
								'This setup sets the groups, the example dashboards and the roles, in five steps. Existing dashboards stay as they are.',
							)
						}}
					</p>
				</div>

				<!-- Step 2 — Group priority order (REQ-WIZ-004; embeds existing UI) -->
				<div v-else-if="currentStep === 2" class="setup-wizard__step">
					<h3>{{ t('launchpad', 'Group priority order') }}</h3>
					<p>
						{{
							t(
								'launchpad',
								'Pick the order Nextcloud groups appear when LaunchPad routes users to a workspace.',
							)
						}}
					</p>
					<GroupPriorityOrder :initialActive="[]" />
				</div>

				<!-- Step 3 — Demo data (REQ-WIZ-005; delivered by demo-data-showcases sibling). -->
				<div v-else-if="currentStep === 3" class="setup-wizard__step">
					<h3>{{ t('launchpad', 'Demo data') }}</h3>
					<AdminDemoData
						data-test="demo-data-panel"
						:choice="demoChoice"
						@choose="onDemoChoose" />
				</div>

				<!-- Step 4 — Admin roles (REQ-WIZ-006): one group gets the admin role on Next. -->
				<div v-else-if="currentStep === 4" class="setup-wizard__step">
					<h3>{{ t('launchpad', 'Admin roles') }}</h3>
					<p>
						{{
							t(
								'launchpad',
								"Assign 'Dashboard Admin' role to a group to delegate LaunchPad administration to group members.",
							)
						}}
					</p>
					<NcSelect
						v-model="roleGroup"
						:options="roleGroups"
						:inputLabel="t('launchpad', 'Group')"
						:placeholder="t('launchpad', 'No group')"
						:loading="roleGroupsLoading"
						data-test="setup-wizard-role-group" />
				</div>

				<!-- Step 5 — Done (REQ-WIZ-012 final step) -->
				<div v-else-if="currentStep === 5" class="setup-wizard__step">
					<h3>{{ t('launchpad', 'All set') }}</h3>
					<p>
						{{
							t(
								'launchpad',
								'Your setup is complete. Click Finish to save changes and dismiss the setup banner.',
							)
						}}
					</p>
				</div>
			</section>

			<footer class="setup-wizard__footer">
				<NcButton
					variant="tertiary"
					:disabled="currentStep <= 1"
					data-test="setup-wizard-back"
					@click="onBack">
					{{ t('launchpad', 'Back') }}
				</NcButton>
				<NcButton
					v-if="!isFinalStep"
					variant="secondary"
					data-test="setup-wizard-skip"
					@click="onSkip">
					{{ t('launchpad', 'Skip') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="loading"
					data-test="setup-wizard-next"
					@click="onNext">
					{{
						isFinalStep
							? t('launchpad', 'Finish')
							: t('launchpad', 'Next')
					}}
				</NcButton>
			</footer>
		</div>
	</NcModal>
</template>

<script>
import { NcButton, NcModal, NcSelect } from '@conduction/nextcloud-vue'
import AdminDemoData from '../components/admin/AdminDemoData.vue'
import GroupPriorityOrder from '../components/admin/GroupPriorityOrder.vue'
import { api } from '../services/api.js'
import { logger } from '../utils/logger.js'

/**
 * SetupWizardModal — the five-step first-run wizard (REQ-WIZ-012):
 * Welcome, Groups, Examples, Roles, Done.
 *
 * The shell owns navigation state; per-step bodies inline simple UI for
 * Welcome / Done and embed canonical sibling components for the group
 * order and the demo data. The storage step was retired (decision 131)
 * and the footer step too: the footer is set on its own admin tab. Step 3
 * keeps the checked demos and installs them on Next; Step 4 gives the one
 * selected group the admin role on Next.
 *
 * @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps
 */
export default {
	name: 'SetupWizardModal',

	components: {
		AdminDemoData,
		GroupPriorityOrder,
		NcButton,
		NcModal,
		NcSelect,
	},

	emits: ['close', 'completed'],

	/** @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps */
	data() {
		return {
			currentStep: 1,
			totalSteps: 5,
			loading: false,
			completed: false,
			// Step 3: `{ [showcaseId]: boolean }`, kept here so Back keeps it.
			demoChoice: {},
			// Step 4: the group ids, and the one chosen for the admin role.
			roleGroups: [],
			roleGroupsLoading: false,
			roleGroup: null,
		}
	},

	computed: {
		isFinalStep() {
			return this.currentStep === this.totalSteps
		},
	},

	watch: {
		/**
		 * Load the groups when Step 4 opens.
		 *
		 * @param {number} step The step now shown.
		 * @spec openspec/specs/setup-wizard/spec.md#req-wiz-006
		 */
		currentStep(step) {
			if (step === 4) {
				this.loadRoleGroups()
			}
		},
	},

	async created() {
		await this.loadState()
	},

	methods: {
		/** @spec openspec/specs/setup-wizard/spec.md */
		async loadState() {
			try {
				const { data } = await api.getSetupWizardState()
				if (data) {
					if (data.currentRecommendedStep && !data.complete) {
						this.currentStep = Math.max(
							1,
							Math.min(data.currentRecommendedStep, this.totalSteps),
						)
					}
				}
			} catch (error) {
				// REQ-WIZ-008: a failed state read MUST NOT block the wizard.
				// Default to Step 1.
				logger.warn('Failed to load wizard state', error)
			}
		},

		/** @spec openspec/specs/setup-wizard/spec.md */
		async onNext() {
			if (this.loading) {
				return
			}
			this.loading = true
			try {
				if (this.isFinalStep) {
					await api.completeSetupWizard()
					this.completed = true
					this.$emit('completed')
					this.$emit('close')
					return
				}
				if (this.currentStep === 3) {
					await this.installChosenDemos()
				} else if (this.currentStep === 4 && this.roleGroup) {
					await this.assignRoleGroup()
				}
				this.currentStep += 1
			} catch (error) {
				logger.error('Wizard step failed', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Record a demo checkbox.
		 *
		 * @param {string} id The showcase id.
		 * @param {boolean} chosen Whether it is checked.
		 * @spec openspec/specs/setup-wizard/spec.md#req-wiz-005
		 */
		onDemoChoose(id, chosen) {
			this.demoChoice = { ...this.demoChoice, [id]: chosen }
		},

		/**
		 * Install the checked showcases that are not installed yet. An
		 * unchecked installed showcase stays installed.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/setup-wizard/spec.md#req-wiz-005
		 */
		async installChosenDemos() {
			const wanted = Object.keys(this.demoChoice).filter((id) => this.demoChoice[id] === true)
			if (wanted.length === 0) {
				return
			}
			const { data } = await api.listDemoShowcases()
			const installed = new Set((data || []).filter((showcase) => showcase.isInstalled).map((showcase) => showcase.id))
			for (const id of wanted.filter((candidate) => !installed.has(candidate))) {
				try {
					await api.installDemoShowcase(id)
				} catch (error) {
					logger.error('Demo install failed', error)
				}
			}
		},

		/**
		 * Load the Nextcloud groups Step 4 offers, once.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/setup-wizard/spec.md#req-wiz-006
		 */
		async loadRoleGroups() {
			if (this.roleGroups.length > 0 || this.roleGroupsLoading) {
				return
			}
			this.roleGroupsLoading = true
			try {
				const { data } = await api.getAdminGroups()
				this.roleGroups = [...(data?.active || []), ...(data?.inactive || [])]
			} catch (error) {
				logger.warn('Failed to load groups', error)
			} finally {
				this.roleGroupsLoading = false
			}
		},

		/**
		 * Give the selected group the admin role. A group that already has
		 * it is left as it is.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/setup-wizard/spec.md#req-wiz-006
		 */
		async assignRoleGroup() {
			try {
				await api.assignAdminRoleToGroup(this.roleGroup)
			} catch (error) {
				if (error?.response?.status !== 409) {
					throw error
				}
			}
		},

		/** @spec openspec/specs/setup-wizard/spec.md */
		onBack() {
			if (this.currentStep > 1) {
				this.currentStep -= 1
			}
		},

		/** @spec openspec/specs/setup-wizard/spec.md */
		onSkip() {
			if (!this.isFinalStep) {
				this.currentStep += 1
			}
		},

		/** @spec openspec/specs/setup-wizard/spec.md */
		onClose() {
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.setup-wizard {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 24px;
	min-height: 360px;
}

.setup-wizard__header {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	gap: 16px;
	border-bottom: 1px solid var(--color-border);
	padding-bottom: 12px;
}

.setup-wizard__header h2 {
	margin: 0;
}

.setup-wizard__counter {
	color: var(--color-text-maxcontrast);
	margin: 0;
	font-size: 14px;
}

.setup-wizard__body {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.setup-wizard__step h3 {
	margin: 0 0 8px;
}

.setup-wizard__note {
	padding: 12px;
	background: var(--color-background-dark);
	border-left: 3px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	margin: 0;
}

.setup-wizard__footer {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}
</style>
