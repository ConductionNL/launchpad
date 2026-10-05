<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="templates-page" data-test="templates-page">
		<div class="launchpad-admin__section-header">
			<h3>{{ t('launchpad', 'Dashboard templates') }}</h3>
			<NcButton
				variant="primary"
				data-testid="admin-create-template"
				@click="createTemplate">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('launchpad', 'Create template') }}
			</NcButton>
		</div>

		<p class="launchpad-admin__hint">
			{{
				t(
					'launchpad',
					'Create dashboard templates that will be applied to users based on their groups.',
				)
			}}
		</p>

		<section
			v-if="shippedTemplates.length > 0"
			class="launchpad-admin__shipped"
			data-testid="admin-shipped-templates"
			:aria-label="t('launchpad', 'Ready-made templates')">
			<h4>{{ t('launchpad', 'Ready-made templates') }}</h4>
			<p class="launchpad-admin__hint">
				{{
					t(
						'launchpad',
						'Add a template that comes with LaunchPad. Then edit it to choose who gets it.',
					)
				}}
			</p>
			<div
				v-for="shipped in shippedTemplates"
				:key="shipped.id"
				class="launchpad-admin__template"
				:data-testid="`admin-shipped-template-${shipped.id}`">
				<div class="launchpad-admin__template-info">
					<strong>{{ shipped.name }}</strong>
					<span class="launchpad-admin__template-groups">{{
						shipped.description
					}}</span>
					<span
						v-if="shipped.missingWidgets.length > 0"
						class="launchpad-admin__template-groups"
						data-testid="admin-shipped-template-missing">
						{{
							t(
								'launchpad',
								'No app here provides these widgets, so they will show empty: {widgets}',
								{ widgets: shipped.missingWidgets.join(', ') },
							)
						}}
					</span>
				</div>
				<div class="launchpad-admin__template-actions">
					<span
						v-if="shipped.isInstalled"
						class="launchpad-admin__badge"
						data-testid="admin-shipped-template-added">
						{{ t('launchpad', 'Added') }}
					</span>
					<NcButton
						v-else
						variant="secondary"
						:disabled="installingId !== null"
						data-testid="admin-shipped-template-add"
						@click="installShipped(shipped)">
						{{ t('launchpad', 'Add template') }}
					</NcButton>
				</div>
			</div>
		</section>

		<p
			v-if="errorMessage !== ''"
			class="launchpad-admin__error"
			role="alert"
			data-testid="admin-templates-error">
			{{ errorMessage }}
		</p>

		<div v-if="templates.length === 0" class="launchpad-admin__empty">
			<NcEmptyContent :description="t('launchpad', 'No templates yet')">
				<template #icon>
					<ViewDashboard :size="48" />
				</template>
			</NcEmptyContent>
		</div>

		<div v-else class="launchpad-admin__templates">
			<div
				v-for="template in templates"
				:key="template.id"
				class="launchpad-admin__template">
				<div class="launchpad-admin__template-info">
					<CnDashboardIcon :name="template.icon" :size="20" />
					<strong>{{ template.name }}</strong>
					<span v-if="template.isDefault" class="launchpad-admin__badge">
						{{ t('launchpad', 'Default') }}
					</span>
					<span class="launchpad-admin__template-groups">
						{{ formatTargetGroups(template.targetGroups) }}
					</span>
				</div>
				<div class="launchpad-admin__template-actions">
					<NcButton
						variant="secondary"
						data-testid="admin-resync-template"
						@click="openResyncModal(template)">
						{{ t('launchpad', 'Re-sync to existing copies') }}
					</NcButton>
					<NcButton
						variant="secondary"
						data-testid="admin-download-template"
						@click="downloadTemplate(template)">
						{{ t('launchpad', 'Download') }}
					</NcButton>
					<NcButton variant="secondary" @click="editTemplate(template)">
						{{ t('launchpad', 'Edit') }}
					</NcButton>
					<NcButton variant="error" @click="deleteTemplate(template)">
						{{ t('launchpad', 'Delete') }}
					</NcButton>
				</div>
			</div>
		</div>

		<TemplateResyncModal
			:open="resyncingTemplate !== null"
			:template="resyncingTemplate"
			@close="closeResyncModal"
			@resynced="closeResyncModal" />

		<TemplateEditorModal
			:open="isEditorOpen"
			:template="editingTemplate"
			@close="closeTemplateEditor"
			@saved="onTemplateSaved" />
	</div>
</template>

<script>
import { CnDashboardIcon, NcButton, NcEmptyContent } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import TemplateEditorModal from '../../../modals/TemplateEditorModal.vue'
import TemplateResyncModal from '../../../modals/TemplateResyncModal.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

/**
 * TemplatesPage — the Templates SUB_PAGE for the admin Beheer area
 * (admin-templates spec). Hosts the dashboard-template list and drives the
 * create/edit modal (`TemplateEditorModal`) and the re-sync modal. This is
 * the only place templates can be managed, satisfying the IA's
 * "Templates SUB_PAGE" requirement.
 *
 * The page owns list state and which template is open in the editor; the
 * editor owns the form itself (ADR-004 modal isolation).
 */
export default {
	name: 'TemplatesPage',

	components: {
		NcButton,
		NcEmptyContent,
		Plus,
		ViewDashboard,
		CnDashboardIcon,
		TemplateResyncModal,
		TemplateEditorModal,
	},

	data() {
		return {
			templates: [],
			shippedTemplates: [],
			installingId: null,
			errorMessage: '',
			isEditorOpen: false,
			editingTemplate: null,
			resyncingTemplate: null,
		}
	},

	/** @spec openspec/specs/admin-templates/spec.md */
	created() {
		this.loadTemplates()
		this.loadShippedTemplates()
	},

	methods: {
		t,

		/** @spec openspec/specs/admin-templates/spec.md */
		async loadTemplates() {
			try {
				const { data } = await api.getAdminTemplates()
				this.templates = data || []
			} catch (error) {
				logger.error('Failed to load templates:', error)
			}
		},

		/**
		 * Load the templates LaunchPad ships with. A failure hides the
		 * section and says so, the list of templates below still works.
		 *
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
		 */
		async loadShippedTemplates() {
			try {
				const { data } = await api.getShippedTemplates()
				this.shippedTemplates = Array.isArray(data) ? data : []
			} catch (error) {
				logger.error('Failed to load shipped templates:', error)
				this.errorMessage = t(
					'launchpad',
					'The ready-made templates could not be loaded.',
				)
			}
		},

		/**
		 * Add a shipped template, then show it in the list.
		 *
		 * @param {object} shipped The shipped template to add.
		 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-018
		 */
		async installShipped(shipped) {
			this.installingId = shipped.id
			this.errorMessage = ''
			try {
				await api.installShippedTemplate(shipped.id)
				await Promise.all([
					this.loadTemplates(),
					this.loadShippedTemplates(),
				])
			} catch (error) {
				logger.error('Failed to add shipped template:', error)
				this.errorMessage = t(
					'launchpad',
					'The template "{name}" could not be added.',
					{ name: shipped.name },
				)
			} finally {
				this.installingId = null
			}
		},

		/**
		 * Download one template as an archive that another LaunchPad can
		 * import (REQ-EXIM-012).
		 *
		 * @param {object} template The template to download.
		 * @spec openspec/specs/dashboard-export-import/spec.md#req-exim-012
		 */
		async downloadTemplate(template) {
			this.errorMessage = ''
			try {
				const response = await api.exportDashboards({
					scope: 'dashboard',
					dashboardUuid: template.uuid,
				})
				const url = window.URL.createObjectURL(response.data)
				const link = document.createElement('a')
				link.href = url
				link.download = `launchpad-template-${template.slug || template.uuid}.zip`
				document.body.appendChild(link)
				link.click()
				document.body.removeChild(link)
				window.URL.revokeObjectURL(url)
			} catch (error) {
				logger.error('Failed to download template:', error)
				this.errorMessage = t(
					'launchpad',
					'The template "{name}" could not be downloaded.',
					{ name: template.name },
				)
			}
		},

		/** @spec openspec/specs/admin-templates/spec.md */
		createTemplate() {
			this.editingTemplate = null
			this.isEditorOpen = true
		},

		/**
		 * Open the editor pre-filled from an existing template.
		 *
		 * @param {object} template The template to edit.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		editTemplate(template) {
			this.editingTemplate = template
			this.isEditorOpen = true
		},

		/** @spec openspec/specs/admin-templates/spec.md */
		closeTemplateEditor() {
			this.isEditorOpen = false
			this.editingTemplate = null
		},

		/**
		 * The editor persisted a template — refresh the list and close it.
		 *
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		async onTemplateSaved() {
			await this.loadTemplates()
			this.closeTemplateEditor()
		},

		/**
		 * Delete a template after an explicit user confirmation.
		 *
		 * @param {object} template The template to delete.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		async deleteTemplate(template) {
			if (
				!confirm(
					t('launchpad', 'Are you sure you want to delete this template?'),
				)
			) {
				return
			}

			try {
				await api.deleteAdminTemplate(template.id)
				await this.loadTemplates()
			} catch (error) {
				logger.error('Failed to delete template:', error)
			}
		},

		/**
		 * Open the re-sync modal for a template.
		 *
		 * @param {object} template The template whose copies to re-sync.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		openResyncModal(template) {
			this.resyncingTemplate = template
		},

		/** @spec openspec/specs/admin-templates/spec.md */
		closeResyncModal() {
			this.resyncingTemplate = null
		},

		/**
		 * Summarise a template's target groups for the list row.
		 *
		 * @param {string[]} groups Group ids the template targets.
		 * @return {string} Comma-joined names, or the localised "All users"
		 *   label when the template is unscoped.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		formatTargetGroups(groups) {
			if (!groups || groups.length === 0) {
				return t('launchpad', 'All users')
			}
			return groups.join(', ')
		},
	},
}
</script>

<style scoped>
.launchpad-admin__section-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 16px;
}

.launchpad-admin__section-header h3 {
	margin: 0;
}

.launchpad-admin__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.launchpad-admin__templates {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.launchpad-admin__template {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.launchpad-admin__template-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.launchpad-admin__template-groups {
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.launchpad-admin__template-actions {
	display: flex;
	gap: 8px;
}

.launchpad-admin__badge {
	display: inline-block;
	padding: 2px 8px;
	background: var(--color-primary-element);
	color: var(--color-primary-text);
	border-radius: var(--border-radius-pill);
	font-size: 12px;
}

.launchpad-admin__empty {
	padding: 48px 0;
}

.launchpad-admin__shipped {
	margin-bottom: 24px;
}

.launchpad-admin__shipped h4 {
	margin: 0 0 4px;
}

.launchpad-admin__error {
	color: var(--color-error);
	margin-bottom: 16px;
}
</style>
