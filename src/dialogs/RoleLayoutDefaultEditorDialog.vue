<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="
			row.id
				? t('launchpad', 'Edit layout default')
				: t('launchpad', 'Add layout default')
		"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<div class="launchpad-admin__editor">
				<!-- @nextcloud/vue@9 renamed `value`/`checked` +
				     `update:value`/`update:checked` to `modelValue` +
				     `update:modelValue`. Under Vue 3 the old names fail
				     silently, so every field here rendered but never
				     emitted. The listener must stay camelCase. -->
				<NcTextField
					:modelValue="row.name"
					:label="t('launchpad', 'Name')"
					:placeholder="t('launchpad', 'Manager — analysedashboard')"
					@update:modelValue="
						$emit('update:row', { ...row, name: $event })
					" />
				<NcTextField
					:modelValue="row.groupId"
					:label="t('launchpad', 'Group ID')"
					:placeholder="t('launchpad', 'managers')"
					@update:modelValue="
						$emit('update:row', { ...row, groupId: $event })
					" />
				<NcTextField
					:modelValue="row.widgetId"
					:label="t('launchpad', 'Widget ID')"
					:placeholder="t('launchpad', 'analytics_dashboard')"
					@update:modelValue="
						$emit('update:row', { ...row, widgetId: $event })
					" />
				<div class="launchpad-admin__editor-row">
					<NcTextField
						:modelValue="String(row.gridX)"
						:label="t('launchpad', 'Grid X')"
						type="number"
						@update:modelValue="
							$emit('update:row', { ...row, gridX: Number($event) })
						" />
					<NcTextField
						:modelValue="String(row.gridY)"
						:label="t('launchpad', 'Grid Y')"
						type="number"
						@update:modelValue="
							$emit('update:row', { ...row, gridY: Number($event) })
						" />
				</div>
				<div class="launchpad-admin__editor-row">
					<NcTextField
						:modelValue="String(row.gridWidth)"
						:label="t('launchpad', 'Width (columns)')"
						type="number"
						@update:modelValue="
							$emit('update:row', {
								...row,
								gridWidth: Number($event),
							})
						" />
					<NcTextField
						:modelValue="String(row.gridHeight)"
						:label="t('launchpad', 'Height (rows)')"
						type="number"
						@update:modelValue="
							$emit('update:row', {
								...row,
								gridHeight: Number($event),
							})
						" />
				</div>
				<NcTextField
					:modelValue="String(row.sortOrder)"
					:label="t('launchpad', 'Sort order')"
					type="number"
					@update:modelValue="
						$emit('update:row', { ...row, sortOrder: Number($event) })
					" />
				<NcCheckboxRadioSwitch
					:modelValue="Boolean(row.isCompulsory)"
					@update:modelValue="
						$emit('update:row', { ...row, isCompulsory: $event })
					">
					{{
						t('launchpad', 'Compulsory (user cannot remove this widget)')
					}}
				</NcCheckboxRadioSwitch>
				<NcTextField
					:modelValue="row.description || ''"
					:label="t('launchpad', 'Description (optional)')"
					@update:modelValue="
						$emit('update:row', { ...row, description: $event })
					" />
			</div>
		</template>
		<template #actions>
			<NcButton
				:disabled="saving"
				variant="tertiary"
				@click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton :disabled="saving" variant="primary" @click="$emit('save')">
				{{ saving ? t('launchpad', 'Saving…') : t('launchpad', 'Save') }}
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
.launchpad-admin__editor {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 360px;
}

.launchpad-admin__editor-row {
	display: flex;
	gap: 12px;
}
</style>
