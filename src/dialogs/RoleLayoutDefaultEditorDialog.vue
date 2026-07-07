<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="row.id ? t('launchpad','Edit layout default') : t('launchpad','Add layout default')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<div class="mydash-admin__editor">
				<NcTextField
					:value="row.name"
					:label="t('launchpad','Name')"
					:placeholder="t('launchpad','Manager — analysedashboard')"
					@update:value="$emit('update:row', { ...row, name: $event })" />
				<NcTextField
					:value="row.groupId"
					:label="t('launchpad','Group ID')"
					:placeholder="t('launchpad','managers')"
					@update:value="$emit('update:row', { ...row, groupId: $event })" />
				<NcTextField
					:value="row.widgetId"
					:label="t('launchpad','Widget ID')"
					:placeholder="t('launchpad','analytics_dashboard')"
					@update:value="$emit('update:row', { ...row, widgetId: $event })" />
				<div class="mydash-admin__editor-row">
					<NcTextField
						:value="String(row.gridX)"
						:label="t('launchpad','Grid X')"
						type="number"
						@update:value="$emit('update:row', { ...row, gridX: Number($event) })" />
					<NcTextField
						:value="String(row.gridY)"
						:label="t('launchpad','Grid Y')"
						type="number"
						@update:value="$emit('update:row', { ...row, gridY: Number($event) })" />
				</div>
				<div class="mydash-admin__editor-row">
					<NcTextField
						:value="String(row.gridWidth)"
						:label="t('launchpad','Width (columns)')"
						type="number"
						@update:value="$emit('update:row', { ...row, gridWidth: Number($event) })" />
					<NcTextField
						:value="String(row.gridHeight)"
						:label="t('launchpad','Height (rows)')"
						type="number"
						@update:value="$emit('update:row', { ...row, gridHeight: Number($event) })" />
				</div>
				<NcTextField
					:value="String(row.sortOrder)"
					:label="t('launchpad','Sort order')"
					type="number"
					@update:value="$emit('update:row', { ...row, sortOrder: Number($event) })" />
				<NcCheckboxRadioSwitch
					:checked="Boolean(row.isCompulsory)"
					@update:checked="$emit('update:row', { ...row, isCompulsory: $event })">
					{{ t('launchpad','Compulsory (user cannot remove this widget)') }}
				</NcCheckboxRadioSwitch>
				<NcTextField
					:value="row.description || ''"
					:label="t('launchpad','Description (optional)')"
					@update:value="$emit('update:row', { ...row, description: $event })" />
			</div>
		</template>
		<template #actions>
			<NcButton :disabled="saving" type="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad','Cancel') }}
			</NcButton>
			<NcButton :disabled="saving" type="primary" @click="$emit('save')">
				{{ saving ? t('launchpad','Saving…') : t('launchpad','Save') }}
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

/**
 * RoleLayoutDefaultEditorDialog — editor extracted from
 * `RoleLayoutDefaultsSection.vue` per ADR-004 modal-isolation rule.
 * State is owned by the parent; this dialog emits update:row /
 * update:open / save and stays presentation-only.
 *
 * @spec openspec/specs/admin-roles/spec.md
 */
export default {
	name: 'RoleLayoutDefaultEditorDialog',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcTextField,
	},

	props: {
		open: { type: Boolean, required: true },
		row: { type: Object, required: true },
		saving: { type: Boolean, default: false },
	},

	emits: ['update:open', 'update:row', 'save'],
}
</script>

<style scoped>
.mydash-admin__editor {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 360px;
}

.mydash-admin__editor-row {
	display: flex;
	gap: 12px;
}
</style>
