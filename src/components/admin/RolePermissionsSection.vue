<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-admin__section">
		<h3>{{ t('launchpad', 'Role-based widget permissions') }}</h3>
		<p class="launchpad-admin__hint">
			{{
				t(
					'launchpad',
					'Restrict which widgets each Nextcloud group can add to their dashboard. Empty list = full catalogue (legacy).',
				)
			}}
		</p>

		<div v-if="store.error" class="launchpad-admin__error" role="alert">
			{{ store.error }}
		</div>

		<NcEmptyContent
			v-if="!store.loading && store.permissions.length === 0"
			:name="t('launchpad', 'No role permissions configured')"
			:description="
				t(
					'launchpad',
					'Add a role-permission row to start filtering the widget catalogue per Nextcloud group.',
				)
			">
			<template #icon>
				<AccountGroup :size="40" />
			</template>
		</NcEmptyContent>

		<div v-else class="launchpad-admin__role-list">
			<div
				v-for="row in store.permissions"
				:key="row.id"
				class="launchpad-admin__role-row">
				<div class="launchpad-admin__role-meta">
					<strong>{{ row.name }}</strong>
					<span class="launchpad-admin__role-group">{{
						row.groupId
					}}</span>
				</div>
				<div class="launchpad-admin__role-widgets">
					<span
						v-for="wid in row.allowedWidgets"
						:key="wid"
						class="launchpad-admin__chip">
						{{ wid }}
					</span>
					<span
						v-for="wid in row.deniedWidgets"
						:key="`d-${wid}`"
						class="launchpad-admin__chip launchpad-admin__chip--denied">
						{{ wid }}
					</span>
				</div>
				<div class="launchpad-admin__role-actions">
					<NcButton
						type="tertiary"
						:aria-label="t('launchpad', 'Edit')"
						@click="openEdit(row)">
						<template #icon>
							<Pencil :size="20" />
						</template>
					</NcButton>
					<NcButton
						type="tertiary"
						:aria-label="t('launchpad', 'Delete')"
						@click="confirmDelete(row)">
						<template #icon>
							<Delete :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>

		<NcButton type="primary" data-testid="admin-add-role" @click="openCreate">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('launchpad', 'Add role permission') }}
		</NcButton>

		<RolePermissionEditorModal
			v-if="showEditor"
			:row="editorRow"
			:allowedWidgetsCsv="allowedWidgetsCsv"
			:deniedWidgetsCsv="deniedWidgetsCsv"
			:saving="store.saving"
			@update:row="editorRow = $event"
			@update:allowedWidgetsCsv="allowedWidgetsCsv = $event"
			@update:deniedWidgetsCsv="deniedWidgetsCsv = $event"
			@save="save"
			@close="closeEditor" />

		<!-- Delete confirmation dialog (ADR-004: extracted to dialogs/) -->
		<RolePermissionDeleteDialog
			v-if="showDeleteDialog"
			:open="showDeleteDialog"
			:groupId="deleteTarget ? deleteTarget.groupId : ''"
			@update:open="showDeleteDialog = $event"
			@confirm="doDelete" />
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@conduction/nextcloud-vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import RolePermissionDeleteDialog from '../../dialogs/RolePermissionDeleteDialog.vue'
import RolePermissionEditorModal from '../../modals/RolePermissionEditorModal.vue'
import { useRoleFeaturePermissionStore } from '../../stores/roleFeaturePermissions.js'

export default {
	name: 'RolePermissionsSection',

	components: {
		NcButton,
		NcEmptyContent,
		AccountGroup,
		Plus,
		Pencil,
		Delete,
		RolePermissionEditorModal,
		RolePermissionDeleteDialog,
	},

	/** @spec openspec/specs/admin-roles/spec.md */
	setup() {
		const store = useRoleFeaturePermissionStore()
		return { store }
	},

	data() {
		return {
			showEditor: false,
			showDeleteDialog: false,
			deleteTarget: null,
			editorRow: this.emptyRow(),
			allowedWidgetsCsv: '',
			deniedWidgetsCsv: '',
		}
	},

	/** @spec openspec/specs/admin-roles/spec.md */
	async mounted() {
		try {
			await this.store.loadPermissions()
		} catch (e) {
			console.error('Failed to load role permissions', e)
		}
	},

	methods: {
		/** @spec openspec/specs/admin-roles/spec.md */
		emptyRow() {
			return {
				id: null,
				name: '',
				groupId: '',
				description: '',
				allowedWidgets: [],
				deniedWidgets: [],
				priorityWeights: {},
			}
		},

		/** @spec openspec/specs/admin-roles/spec.md */
		openCreate() {
			this.editorRow = this.emptyRow()
			this.allowedWidgetsCsv = ''
			this.deniedWidgetsCsv = ''
			this.showEditor = true
		},

		/**
		 * Open the editor pre-filled from an existing role-permission row.
		 *
		 * @param {object} row The row to edit.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		openEdit(row) {
			this.editorRow = {
				...row,
				allowedWidgets: row.allowedWidgets ?? [],
				deniedWidgets: row.deniedWidgets ?? [],
				priorityWeights: row.priorityWeights ?? {},
			}
			this.allowedWidgetsCsv = (row.allowedWidgets ?? []).join(', ')
			this.deniedWidgetsCsv = (row.deniedWidgets ?? []).join(', ')
			this.showEditor = true
		},

		/** @spec openspec/specs/admin-roles/spec.md */
		closeEditor() {
			this.showEditor = false
		},

		/**
		 * Split a comma-separated input into trimmed, non-empty entries.
		 *
		 * @param {string} s Raw CSV text from the form.
		 * @return {string[]} The parsed entries.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		parseCsv(s) {
			return (s ?? '')
				.split(',')
				.map((x) => x.trim())
				.filter((x) => x.length > 0)
		},

		/** @spec openspec/specs/admin-roles/spec.md */
		async save() {
			try {
				const payload = {
					name: this.editorRow.name,
					groupId: this.editorRow.groupId,
					description: this.editorRow.description || null,
					allowedWidgets: this.parseCsv(this.allowedWidgetsCsv),
					deniedWidgets: this.parseCsv(this.deniedWidgetsCsv),
					priorityWeights: this.editorRow.priorityWeights ?? {},
				}
				await this.store.savePermission(payload)
				this.closeEditor()
			} catch (e) {
				console.error('Failed to save role permission', e)
			}
		},

		/**
		 * Stage a row for deletion and open the confirmation dialog.
		 *
		 * @param {object} row The row to delete.
		 * @spec openspec/changes/role-based-content/tasks.md#task-5
		 */
		confirmDelete(row) {
			this.deleteTarget = row
			this.showDeleteDialog = true
		},

		/** @spec openspec/changes/role-based-content/tasks.md#task-5 */
		async doDelete() {
			if (this.deleteTarget === null) {
				return
			}
			try {
				await this.store.deletePermission(this.deleteTarget.id)
				this.showDeleteDialog = false
				this.deleteTarget = null
			} catch (e) {
				console.error('Failed to delete role permission', e)
			}
		},
	},
}
</script>

<style scoped>
.launchpad-admin__hint {
	color: var(--color-text-maxcontrast);
	margin: 0 0 var(--default-grid-baseline) 0;
}
.launchpad-admin__error {
	background: var(--color-error);
	color: var(--color-primary-element-text);
	padding: var(--default-grid-baseline);
	border-radius: var(--border-radius);
	margin-bottom: var(--default-grid-baseline);
}
.launchpad-admin__role-list {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	margin-bottom: var(--default-grid-baseline);
}
.launchpad-admin__role-row {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	padding: var(--default-grid-baseline);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}
.launchpad-admin__role-meta {
	display: flex;
	flex-direction: column;
	min-width: 200px;
}
.launchpad-admin__role-group {
	color: var(--color-text-maxcontrast);
	font-family: var(--font-face-monospace, monospace);
	font-size: 0.85em;
}
.launchpad-admin__role-widgets {
	flex: 1;
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}
.launchpad-admin__chip {
	background: var(--color-background-hover);
	padding: 2px 8px;
	border-radius: var(--border-radius);
	font-size: 0.85em;
}
.launchpad-admin__chip--denied {
	background: var(--color-error);
	color: var(--color-primary-element-text);
}
.launchpad-admin__role-actions {
	display: flex;
	gap: 4px;
}
.launchpad-admin__editor {
	padding: calc(var(--default-grid-baseline) * 2);
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	min-width: 480px;
}
.launchpad-admin__editor-actions {
	display: flex;
	justify-content: flex-end;
	gap: var(--default-grid-baseline);
	margin-top: var(--default-grid-baseline);
}
</style>
