<!-- SPDX-License-Identifier: EUPL-1.2 -->
<template>
	<NcDialog :name="row.id ? t('mydash', 'Edit layout default') : t('mydash', 'Add layout default')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<div class="mydash-admin__editor">
				<NcTextField
					:value="row.name"
					:label="t('mydash', 'Name')"
					:placeholder="t('mydash', 'Manager — analysedashboard')"
					@update:value="update('name', $event)" />
				<NcTextField
					:value="row.groupId"
					:label="t('mydash', 'Group ID')"
					:placeholder="t('mydash', 'managers')"
					@update:value="update('groupId', $event)" />
				<NcTextField
					:value="row.widgetId"
					:label="t('mydash', 'Widget ID')"
					:placeholder="t('mydash', 'analytics_dashboard')"
					@update:value="update('widgetId', $event)" />
				<div class="mydash-admin__editor-row">
					<NcTextField
						:value="String(row.gridX)"
						:label="t('mydash', 'Grid X')"
						type="number"
						@update:value="update('gridX', Number($event))" />
					<NcTextField
						:value="String(row.gridY)"
						:label="t('mydash', 'Grid Y')"
						type="number"
						@update:value="update('gridY', Number($event))" />
				</div>
				<div class="mydash-admin__editor-row">
					<NcTextField
						:value="String(row.gridWidth)"
						:label="t('mydash', 'Width (columns)')"
						type="number"
						@update:value="update('gridWidth', Number($event))" />
					<NcTextField
						:value="String(row.gridHeight)"
						:label="t('mydash', 'Height (rows)')"
						type="number"
						@update:value="update('gridHeight', Number($event))" />
				</div>
				<NcTextField
					:value="String(row.sortOrder)"
					:label="t('mydash', 'Sort order')"
					type="number"
					@update:value="update('sortOrder', Number($event))" />
				<NcCheckboxRadioSwitch
					:checked="Boolean(row.isCompulsory)"
					@update:checked="update('isCompulsory', $event)">
					{{ t('mydash', 'Compulsory (user cannot remove this widget)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField
					:value="row.description || ''"
					:label="t('mydash', 'Description (optional)')"
					@update:value="update('description', $event)" />
			</div>
		</template>
		<template #actions>
			<NcButton :disabled="saving" type="tertiary" @click="$emit('update:open', false)">
				{{ t('mydash', 'Cancel') }}
			</NcButton>
			<NcButton :disabled="saving" type="primary" @click="$emit('save')">
				{{ saving ? t('mydash', 'Saving…') : t('mydash', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDialog,
	NcTextField,
} from '@conduction/nextcloud-vue'

export default {
	name: 'RoleLayoutDefaultEditorDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcTextField,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},
		row: {
			type: Object,
			required: true,
		},
		saving: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:open', 'update:row', 'save'],

	methods: {
		/** @spec openspec/changes/role-based-content/tasks.md#task-6 */
		update(key, value) {
			this.$emit('update:row', { ...this.row, [key]: value })
		},
	},
}
</script>

<style scoped>
.mydash-admin__editor {
	padding: calc(var(--default-grid-baseline) * 2);
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	min-width: 480px;
}
.mydash-admin__editor-row {
	display: flex;
	gap: var(--default-grid-baseline);
}
.mydash-admin__editor-row > * {
	flex: 1;
}
</style>
