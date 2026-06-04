<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<div class="mydash-admin__section">
		<h3>{{ t('mydash', 'Role-based default layouts') }}</h3>
		<p class="mydash-admin__hint">
			{{ t('mydash', 'Define the default grid positions for each group\'s widgets. New users whose group has no admin template will receive these positions as their starting layout.') }}
		</p>

		<div v-if="store.error" class="mydash-admin__error" role="alert">
			{{ store.error }}
		</div>

		<NcEmptyContent
			v-if="!store.loading && store.layoutDefaults.length === 0"
			:name="t('mydash', 'No layout defaults configured')"
			:description="t('mydash', 'Add layout default rows to seed role-based starting dashboards for new users.')">
			<template #icon>
				<ViewDashboard :size="40" />
			</template>
		</NcEmptyContent>

		<div v-else class="mydash-admin__role-list">
			<div v-for="row in store.layoutDefaults"
				:key="row.id"
				class="mydash-admin__role-row">
				<div class="mydash-admin__role-meta">
					<strong>{{ row.name }}</strong>
					<span class="mydash-admin__role-group">{{ row.groupId }} / {{ row.widgetId }}</span>
				</div>
				<div class="mydash-admin__role-widgets">
					<span class="mydash-admin__chip">
						{{ t('mydash', '{x},{y} {w}×{h}', { x: row.gridX, y: row.gridY, w: row.gridWidth, h: row.gridHeight }) }}
					</span>
					<span v-if="row.isCompulsory" class="mydash-admin__chip mydash-admin__chip--compulsory">
						{{ t('mydash', 'Compulsory') }}
					</span>
				</div>
				<div class="mydash-admin__role-actions">
					<NcButton type="tertiary"
						:aria-label="t('mydash', 'Edit')"
						@click="openEdit(row)">
						<template #icon>
							<Pencil :size="20" />
						</template>
					</NcButton>
					<NcButton type="tertiary"
						:aria-label="t('mydash', 'Delete')"
						@click="openDeleteDialog(row)">
						<template #icon>
							<Delete :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>

		<NcButton type="primary" data-testid="admin-add-layout-default" @click="openCreate">
			<template #icon>
				<Plus :size="20" />
			</template>
			{{ t('mydash', 'Add layout default') }}
		</NcButton>

		<!-- Editor dialog -->
		<RoleLayoutDefaultEditorDialog
			v-if="showEditor"
			:open="showEditor"
			:row="editorRow"
			:saving="store.saving"
			@update:open="showEditor = $event"
			@update:row="editorRow = $event"
			@save="save" />

		<!-- Delete confirmation dialog -->
		<RoleLayoutDefaultDeleteDialog
			v-if="showDeleteDialog"
			:open="showDeleteDialog"
			:group-id="deleteTarget ? deleteTarget.groupId : ''"
			:widget-id="deleteTarget ? deleteTarget.widgetId : ''"
			@update:open="showDeleteDialog = $event"
			@confirm="confirmDelete" />
	</div>
</template>

<script>
import {
	NcButton,
	NcEmptyContent,
} from '@conduction/nextcloud-vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import RoleLayoutDefaultEditorDialog from '../../dialogs/RoleLayoutDefaultEditorDialog.vue'
import RoleLayoutDefaultDeleteDialog from '../../dialogs/RoleLayoutDefaultDeleteDialog.vue'
import { useRoleFeaturePermissionStore } from '../../stores/roleFeaturePermissions.js'

export default {
	name: 'RoleLayoutDefaultsSection',

	components: {
		NcButton,
		NcEmptyContent,
		ViewDashboard,
		Plus,
		Pencil,
		Delete,
		RoleLayoutDefaultEditorDialog,
		RoleLayoutDefaultDeleteDialog,
	},

	/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
	setup() {
		const store = useRoleFeaturePermissionStore()
		return { store }
	},

	data() {
		return {
			showEditor: false,
			showDeleteDialog: false,
			editorRow: this.emptyRow(),
			deleteTarget: null,
		}
	},

	/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
	async mounted() {
		try {
			await this.store.loadLayoutDefaults()
		} catch (e) {
			console.error('Failed to load layout defaults', e)
		}
	},

	methods: {
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		emptyRow() {
			return {
				id: null,
				name: '',
				groupId: '',
				widgetId: '',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 4,
				sortOrder: 0,
				isCompulsory: false,
				description: '',
			}
		},
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		openCreate() {
			this.editorRow = this.emptyRow()
			this.showEditor = true
		},
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		openEdit(row) {
			this.editorRow = { ...row }
			this.showEditor = true
		},
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		openDeleteDialog(row) {
			this.deleteTarget = row
			this.showDeleteDialog = true
		},
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		async save() {
			try {
				await this.store.saveLayoutDefault(this.editorRow)
				this.showEditor = false
			} catch (e) {
				console.error('Failed to save layout default', e)
			}
		},
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		async confirmDelete() {
			if (this.deleteTarget === null) {
				return
			}
			try {
				await this.store.deleteLayoutDefault(this.deleteTarget.id)
				this.showDeleteDialog = false
				this.deleteTarget = null
			} catch (e) {
				console.error('Failed to delete layout default', e)
			}
		},
	},
}
</script>

<style scoped>
.mydash-admin__hint {
	color: var(--color-text-maxcontrast);
	margin: 0 0 var(--default-grid-baseline) 0;
}
.mydash-admin__error {
	background: var(--color-error);
	color: var(--color-primary-element-text);
	padding: var(--default-grid-baseline);
	border-radius: var(--border-radius);
	margin-bottom: var(--default-grid-baseline);
}
.mydash-admin__role-list {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	margin-bottom: var(--default-grid-baseline);
}
.mydash-admin__role-row {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	padding: var(--default-grid-baseline);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}
.mydash-admin__role-meta {
	display: flex;
	flex-direction: column;
	min-width: 200px;
}
.mydash-admin__role-group {
	color: var(--color-text-maxcontrast);
	font-family: var(--font-face-monospace, monospace);
	font-size: 0.85em;
}
.mydash-admin__role-widgets {
	flex: 1;
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}
.mydash-admin__chip {
	background: var(--color-background-hover);
	padding: 2px 8px;
	border-radius: var(--border-radius);
	font-size: 0.85em;
}
.mydash-admin__chip--compulsory {
	background: var(--color-warning);
}
.mydash-admin__role-actions {
	display: flex;
	gap: 4px;
}
</style>
