<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
					<NcButton type="secondary" @click="editTemplate(template)">
						{{ t('launchpad', 'Edit') }}
					</NcButton>
					<NcButton type="error" @click="deleteTemplate(template)">
						{{ t('launchpad', 'Delete') }}
					</NcButton>
				</div>
			</div>
		</div>

		<!-- Template Editor Modal -->
		<NcModal
			v-if="editingTemplate"
			:name="editingTemplate.id ? t('launchpad', 'Edit template') : t('launchpad', 'Create template')"
			size="large"
			@close="closeTemplateEditor">
			<div class="launchpad-admin__modal">
				<h2>{{ editingTemplate.id ? t('launchpad', 'Edit template') : t('launchpad', 'Create template') }}</h2>

				<div class="launchpad-admin__field">
					<label>{{ t('launchpad', 'Template name') }}</label>
					<NcTextField v-model="editingTemplate.name" :placeholder="t('launchpad', 'My template')" />
				</div>

				<div class="launchpad-admin__field">
					<label>{{ t('launchpad', 'Description') }}</label>
					<NcTextField v-model="editingTemplate.description" :placeholder="t('launchpad', 'Optional description')" />
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
					:checked="editingTemplate.isDefault"
					@update:checked="editingTemplate.isDefault = $event">
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
	},

	data() {
		return {
			templates: [],
			availableGroups: [],
			editingTemplate: null,
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

		/** @spec openspec/specs/admin-templates/spec.md */
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

		/** @spec openspec/specs/admin-templates/spec.md */
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

		/** @spec openspec/specs/admin-templates/spec.md */
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
