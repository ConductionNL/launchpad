<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal @close="$emit('close')">
		<div class="launchpad-admin__editor">
			<h3>
				{{
					row.id
						? t('launchpad', 'Edit role permission')
						: t('launchpad', 'Add role permission')
				}}
			</h3>
			<!-- @nextcloud/vue@9: `value` + `update:value` became
			     `modelValue` + `update:modelValue`; the old pair is a
			     silent no-op under Vue 3. Listener stays camelCase. -->
			<NcTextField
				:modelValue="row.name"
				:label="t('launchpad', 'Name')"
				required
				@update:modelValue="updateRow('name', $event)" />
			<NcTextField
				:modelValue="row.groupId"
				:label="t('launchpad', 'Nextcloud group ID')"
				required
				:disabled="!!row.id"
				@update:modelValue="updateRow('groupId', $event)" />
			<NcTextField
				:modelValue="row.description"
				:label="t('launchpad', 'Description (optional)')"
				@update:modelValue="updateRow('description', $event)" />
			<NcTextField
				:modelValue="allowedWidgetsCsv"
				:label="t('launchpad', 'Allowed widget IDs (comma separated)')"
				@update:modelValue="$emit('update:allowedWidgetsCsv', $event)" />
			<NcTextField
				:modelValue="deniedWidgetsCsv"
				:label="t('launchpad', 'Denied widget IDs (comma separated)')"
				@update:modelValue="$emit('update:deniedWidgetsCsv', $event)" />
			<div class="launchpad-admin__editor-actions">
				<NcButton type="tertiary" @click="$emit('close')">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					type="primary"
					:disabled="saving || !row.name || !row.groupId"
					@click="$emit('save')">
					{{ t('launchpad', 'Save') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcButton, NcModal, NcTextField } from '@conduction/nextcloud-vue'

export default {
	name: 'RolePermissionEditorModal',

	components: {
		NcButton,
		NcModal,
		NcTextField,
	},

	props: {
		row: {
			type: Object,
			required: true,
		},

		allowedWidgetsCsv: {
			type: String,
			default: '',
		},

		deniedWidgetsCsv: {
			type: String,
			default: '',
		},

		saving: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'close',
		'save',
		'update:row',
		'update:allowedWidgetsCsv',
		'update:deniedWidgetsCsv',
	],

	methods: {
		/**
		 * Emit the edited row with one field replaced. The row prop is
		 * never mutated in place.
		 *
		 * @param {string} key Field to change.
		 * @param {*} value New value for that field.
		 * @spec openspec/specs/admin-roles/spec.md
		 */
		updateRow(key, value) {
			this.$emit('update:row', { ...this.row, [key]: value })
		},
	},
}
</script>

<style scoped>
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
