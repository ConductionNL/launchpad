/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Small stand-ins for the `@conduction/nextcloud-vue` components the
 * scheduled-exports screens use, shared by their specs through
 * `vi.mock('@conduction/nextcloud-vue', () => import('./helpers/scheduledExportStubs.js'))`.
 * Each renders plain HTML and keeps the props/events the screens rely on,
 * so a spec can type, pick, click and read what is shown.
 */

export const NcModal = {
	name: 'NcModal',
	props: ['name', 'size'],
	emits: ['close'],
	template: '<div class="modal"><slot /></div>',
}

export const NcButton = {
	name: 'NcButton',
	props: ['disabled', 'variant'],
	emits: ['click'],
	template:
		'<button type="button" :disabled="disabled" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
}

function field(tag) {
	return {
		props: ['modelValue', 'label', 'helperText', 'type'],
		emits: ['update:modelValue'],
		template: `<${tag} :aria-label="label" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />`,
	}
}

export const NcTextField = { name: 'NcTextField', ...field('input') }
export const NcPasswordField = { name: 'NcPasswordField', ...field('input') }
export const NcTextArea = { name: 'NcTextArea', ...field('textarea') }

export const NcSelect = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'inputLabel', 'label', 'clearable'],
	emits: ['update:modelValue'],
	template:
		'<select :aria-label="inputLabel" @change="$emit(\'update:modelValue\', options[$event.target.selectedIndex])">'
		+ '<option v-for="o in options" :key="o.label" :selected="modelValue && o.label === modelValue.label">{{ o.label }}</option>'
		+ '</select>',
}

export const NcCheckboxRadioSwitch = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type'],
	emits: ['update:modelValue'],
	template:
		'<label><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)"><slot /></label>',
}

export const NcNoteCard = {
	name: 'NcNoteCard',
	props: ['type'],
	template: '<div class="note"><slot /></div>',
}

export const NcLoadingIcon = {
	name: 'NcLoadingIcon',
	template: '<span class="loading" />',
}

export const NcActions = {
	name: 'NcActions',
	template: '<div class="actions"><slot /></div>',
}

export const NcActionButton = {
	name: 'NcActionButton',
	props: ['disabled', 'closeAfterClick'],
	emits: ['click'],
	template:
		'<button type="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

export const CnConfirmDialog = {
	name: 'CnConfirmDialog',
	props: ['dialogTitle', 'message', 'confirmLabel'],
	emits: ['confirm', 'close'],
	template:
		'<div class="confirm">{{ message }}<button type="button" data-testid="confirm" @click="$emit(\'confirm\')">{{ confirmLabel }}</button></div>',
}

/** Renders each row with the column slots, like the library table, and emits `row-click`. */
export const CnDataTable = {
	name: 'CnDataTable',
	props: ['columns', 'rows', 'loading', 'emptyText', 'rowKey'],
	emits: ['row-click'],
	template:
		'<table><tbody>'
		+ '<tr v-for="row in rows" :key="row.id" class="row" @click="$emit(\'row-click\', row)">'
		+ '<td v-for="col in columns" :key="col.key"><slot :name="\'column-\' + col.key" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot></td>'
		+ '<td><slot name="row-actions" :row="row" /></td>'
		+ '</tr>'
		+ '<tr v-if="!rows || rows.length === 0"><td class="empty">{{ emptyText }}</td></tr>'
		+ '</tbody></table>',
}
