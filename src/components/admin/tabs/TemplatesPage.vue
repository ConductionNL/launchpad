<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="templates-page" data-test="templates-page">
		<div class="launchpad-admin__section-header">
			<h3>{{ t('launchpad', 'Dashboard templates') }}</h3>
			<NcButton type="primary" data-testid="admin-create-template" @click="createTemplate">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('launchpad', 'Create template') }}
			</NcButton>
		</div>

		<p class="launchpad-admin__hint">
			{{ t('launchpad', 'Create dashboard templates that will be applied to users based on their groups.') }}
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
						type="secondary"
						data-testid="admin-resync-template"
						@click="openResyncModal(template)">
						{{ t('launchpad', 'Re-sync to existing copies') }}
					</NcButton>
					<NcButton type="secondary" @click="editTemplate(template)">
						{{ t('launchpad', 'Edit') }}
					</NcButton>
					<NcButton type="error" @click="deleteTemplate(template)">
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
import {
	NcButton,
	NcEmptyContent,
	CnDashboardIcon,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import { api } from '../../../services/api.js'
import TemplateResyncModal from '../../../modals/TemplateResyncModal.vue'
import TemplateEditorModal from '../../../modals/TemplateEditorModal.vue'

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
			isEditorOpen: false,
			editingTemplate: null,
			resyncingTemplate: null,
		}
	},

	/** @spec openspec/specs/admin-templates/spec.md */
	created() {
		this.loadTemplates()
	},

	methods: {
		t,

		/** @spec openspec/specs/admin-templates/spec.md */
		async loadTemplates() {
			try {
				const { data } = await api.getAdminTemplates()
				this.templates = data || []
			} catch (error) {
				console.error('Failed to load templates:', error)
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
			if (!confirm(t('launchpad', 'Are you sure you want to delete this template?'))) {
				return
			}

			try {
				await api.deleteAdminTemplate(template.id)
				await this.loadTemplates()
			} catch (error) {
				console.error('Failed to delete template:', error)
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
</style>
