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

		<!-- Template Editor Modal -->
		<NcModal
			v-if="editingTemplate"
			:name="editingTemplate.id ? t('launchpad', 'Edit template') : t('launchpad', 'Create template')"
			size="large"
			@close="closeTemplateEditor">
			<div class="launchpad-admin__modal">
				<h2>{{ editingTemplate.id ? t('launchpad', 'Edit template') : t('launchpad', 'Create template') }}</h2>

				<!--
					The visible text moves from a bare `<label>` onto
					NcTextField's own `label` prop.

					A `<label>` with no `for` (and not wrapping the field) is
					not associated with anything — it renders as text, and a
					screen reader announces the field with no name at all.
					NcTextField renders the label against the `<input>` it
					owns, which is the association the markup was reaching
					for. The placeholder stays as the example value it always
					was; it is not a substitute for a name, because it
					disappears as soon as the field has content.
				-->
				<div class="launchpad-admin__field">
					<NcTextField
						v-model="editingTemplate.name"
						:label="t('launchpad', 'Template name')"
						:placeholder="t('launchpad', 'My template')" />
				</div>

				<div class="launchpad-admin__field">
					<NcTextField
						v-model="editingTemplate.description"
						:label="t('launchpad', 'Description')"
						:placeholder="t('launchpad', 'Optional description')" />
				</div>

				<div class="launchpad-admin__field">
					<label>{{ t('launchpad', 'Target groups') }}</label>
					<NcSelectTags
						v-model="editingTemplate.targetGroups"
						:options="availableGroups"
						:multiple="true"
						:aria-label-combobox="t('launchpad', 'Target groups')"
						:placeholder="t('launchpad', 'Select groups (leave empty for all users)')" />
				</div>

				<div class="launchpad-admin__field">
					<NcSelect
						v-model="editingTemplate.permissionLevel"
						:input-label="t('launchpad', 'Permission level')"
						:options="permissionOptions"
						label="label"
						track-by="id"
						:clearable="false" />
				</div>

				<NcCheckboxRadioSwitch
					v-model="editingTemplate.isDefault">
					{{ t('launchpad', 'Set as default template') }}
				</NcCheckboxRadioSwitch>

				<div class="launchpad-admin__modal-actions">
					<NcButton type="secondary" @click="closeTemplateEditor">
						{{ t('launchpad', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" @click="saveTemplate">
						{{ t('launchpad', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import {
	NcButton,
	NcSelect,
	NcSelectTags,
	NcTextField,
	NcCheckboxRadioSwitch,
	NcEmptyContent,
	NcModal,
	CnDashboardIcon,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import { api } from '../../../services/api.js'
import TemplateResyncModal from '../../../modals/TemplateResyncModal.vue'

/**
 * TemplatesPage — the Templates SUB_PAGE for the admin Beheer area
 * (admin-templates spec). Hosts the dashboard-template list + CRUD modal
 * that previously lived inline in `AdminSettings.vue`. This is now the only
 * place templates can be managed, satisfying the IA's "Templates SUB_PAGE"
 * requirement.
 */
export default {
	name: 'TemplatesPage',

	components: {
		NcButton,
		NcSelect,
		NcSelectTags,
		NcTextField,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcModal,
		Plus,
		ViewDashboard,
		CnDashboardIcon,
		TemplateResyncModal,
	},

	data() {
		return {
			templates: [],
			availableGroups: [],
			editingTemplate: null,
			resyncingTemplate: null,
			permissionOptions: [
				{ id: 'view_only', label: t('launchpad', 'View only') },
				{ id: 'add_only', label: t('launchpad', 'Add only') },
				{ id: 'full', label: t('launchpad', 'Full customization') },
			],
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
			this.editingTemplate = {
				id: null,
				name: '',
				description: '',
				targetGroups: [],
				permissionLevel: this.permissionOptions[1],
				isDefault: false,
			}
		},

		/**
		 * Open the editor pre-filled from an existing template.
		 *
		 * @param {object} template The template to edit.
		 * @spec openspec/specs/admin-templates/spec.md
		 */
		editTemplate(template) {
			this.editingTemplate = {
				...template,
				permissionLevel: this.permissionOptions.find(
					p => p.id === template.permissionLevel,
				) || this.permissionOptions[1],
			}
		},

		/** @spec openspec/specs/admin-templates/spec.md */
		closeTemplateEditor() {
			this.editingTemplate = null
		},

		/** @spec openspec/specs/admin-templates/spec.md */
		async saveTemplate() {
			try {
				const data = {
					name: this.editingTemplate.name,
					description: this.editingTemplate.description,
					targetGroups: this.editingTemplate.targetGroups,
					permissionLevel: this.editingTemplate.permissionLevel?.id,
					isDefault: this.editingTemplate.isDefault,
				}

				if (this.editingTemplate.id) {
					await api.updateAdminTemplate(this.editingTemplate.id, data)
				} else {
					await api.createAdminTemplate(data)
				}

				await this.loadTemplates()
				this.closeTemplateEditor()
			} catch (error) {
				console.error('Failed to save template:', error)
			}
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

.launchpad-admin__field {
	margin-bottom: 16px;
}

.launchpad-admin__field label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
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

.launchpad-admin__modal {
	padding: 24px;
}

.launchpad-admin__modal h2 {
	margin: 0 0 24px;
}

.launchpad-admin__modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 24px;
}
</style>
