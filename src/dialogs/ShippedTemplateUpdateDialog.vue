<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Update template')"
		:open="open"
		data-testid="shipped-template-update-dialog"
		@update:open="onOpenChange">
		<template #default>
			<p v-if="loading" data-testid="shipped-update-loading">
				{{ t('launchpad', 'Working out what changes.') }}
			</p>
			<p
				v-else-if="errorMessage !== ''"
				class="shipped-update__error"
				role="alert"
				data-testid="shipped-update-error">
				{{ errorMessage }}
			</p>
			<div v-else-if="plan !== null" data-testid="shipped-update-plan">
				<p>
					{{
						t(
							'launchpad',
							'This brings "{name}" from version {from} to version {to}. Its name, its groups and whether it is the default stay as they are.',
							{
								name: plan.name,
								from: plan.installedVersion,
								to: plan.version,
							},
						)
					}}
				</p>
				<p v-if="!hasChanges" data-testid="shipped-update-nothing">
					{{ t('launchpad', 'The widgets stay as they are.') }}
				</p>
				<template v-if="plan.added.length > 0">
					<h4>{{ t('launchpad', 'Widgets added') }}</h4>
					<ul data-testid="shipped-update-added">
						<li v-for="widget in plan.added" :key="`a-${widget}`">
							{{ widget }}
						</li>
					</ul>
				</template>
				<template v-if="plan.removed.length > 0">
					<h4>{{ t('launchpad', 'Widgets removed') }}</h4>
					<ul data-testid="shipped-update-removed">
						<li v-for="widget in plan.removed" :key="`r-${widget}`">
							{{ widget }}
						</li>
					</ul>
				</template>
				<template v-if="plan.changed.length > 0">
					<h4>{{ t('launchpad', 'Widgets changed') }}</h4>
					<ul data-testid="shipped-update-changed">
						<li
							v-for="change in plan.changed"
							:key="`c-${change.widget}`">
							{{ change.widget }}
						</li>
					</ul>
				</template>
				<p class="shipped-update__hint">
					{{
						t(
							'launchpad',
							'Changes you made to the widgets of this template by hand are replaced.',
						)
					}}
				</p>
				<p class="shipped-update__hint" data-testid="shipped-update-copies">
					{{
						t(
							'launchpad',
							'Members with a copy of this template: {count}. Their copies follow. A widget a member added stays.',
							{ count: plan.copies },
						)
					}}
				</p>
			</div>
		</template>
		<template #actions>
			<NcButton
				variant="tertiary"
				data-testid="shipped-update-cancel"
				@click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="plan === null || loading || updating"
				data-testid="shipped-update-confirm"
				@click="confirm">
				{{ t('launchpad', 'Update') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../services/api.js'
import { logger } from '../utils/logger.js'

/**
 * ShippedTemplateUpdateDialog: shows what updating an installed ready-made
 * template changes, before anything is written (admin-templates
 * REQ-TMPL-020). Opening it asks the server for a dry run; "Update" then
 * runs the update for real and reports the result to the parent.
 *
 * In its own file per ADR-004 modal isolation. The parent owns the list of
 * shipped templates; this dialog owns the plan and the update call.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
export default {
	name: 'ShippedTemplateUpdateDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		/** The shipped template row, as the listing returns it. */
		shipped: {
			type: Object,
			default: null,
		},
	},

	emits: ['update:open', 'updated'],

	data() {
		return {
			plan: null,
			loading: false,
			updating: false,
			errorMessage: '',
		}
	},

	computed: {
		/**
		 * Whether the update touches any widget.
		 *
		 * @return {boolean} False when every widget stays as it is.
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
		 */
		hasChanges() {
			return (
				this.plan !== null
				&& this.plan.added.length
					+ this.plan.removed.length
					+ this.plan.changed.length
					> 0
			)
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Load the plan each time the dialog opens.
			 *
			 * @param {boolean} isOpen Whether the dialog is now open.
			 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
			 */
			handler(isOpen) {
				if (isOpen && this.shipped !== null) {
					this.loadPlan()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Pass a close from the dialog chrome on to the parent.
		 *
		 * @param {boolean} isOpen The dialog's new open state.
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
		 */
		onOpenChange(isOpen) {
			this.$emit('update:open', isOpen)
		},

		/**
		 * Ask the server what the update would change. Writes nothing.
		 *
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
		 */
		async loadPlan() {
			this.plan = null
			this.errorMessage = ''
			this.loading = true
			try {
				const { data } = await api.updateShippedTemplate(this.shipped.id, {
					dryRun: true,
				})
				this.plan = data
			} catch (error) {
				logger.error('Failed to load the template update plan:', error)
				this.errorMessage = t(
					'launchpad',
					'The changes could not be worked out. Nothing was updated.',
				)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Run the update and hand the result to the parent.
		 *
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
		 */
		async confirm() {
			this.updating = true
			this.errorMessage = ''
			try {
				const { data } = await api.updateShippedTemplate(this.shipped.id)
				this.$emit('updated', data)
			} catch (error) {
				logger.error('Failed to update the shipped template:', error)
				this.plan = null
				this.errorMessage = t(
					'launchpad',
					'The template "{name}" could not be updated.',
					{ name: this.shipped.name },
				)
			} finally {
				this.updating = false
			}
		},
	},
}
</script>

<style scoped>
.shipped-update__hint {
	color: var(--color-text-maxcontrast);
}

.shipped-update__error {
	color: var(--color-error);
}
</style>
