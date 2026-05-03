<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="text-display-form">
		<NcSelect
			:value="modeOption"
			:options="modeOptions"
			:input-label="t('mydash', 'Content type')"
			:clearable="false"
			label="label"
			@input="onModeChange" />

		<template v-if="!tableMode">
			<label class="text-display-form__field">
				{{ t('mydash', 'Text') }}
				<textarea
					:value="text"
					class="text-display-form__textarea"
					rows="4"
					required
					@input="updateField('text', $event.target.value)" />
			</label>

			<NcTextField
				:value="fontSize"
				:label="t('mydash', 'Font Size')"
				placeholder="14px"
				@update:value="updateField('fontSize', $event)" />

			<label class="text-display-form__color-label">
				{{ t('mydash', 'Text Color') }}
				<input
					type="color"
					:value="color || '#000000'"
					class="text-display-form__color"
					@input="updateField('color', $event.target.value)">
			</label>

			<label class="text-display-form__color-label">
				{{ t('mydash', 'Background Color') }}
				<input
					type="color"
					:value="backgroundColor || '#ffffff'"
					class="text-display-form__color"
					@input="updateField('backgroundColor', $event.target.value)">
			</label>

			<NcSelect
				:value="textAlign"
				:options="textAlignOptions"
				:input-label="t('mydash', 'Alignment')"
				:clearable="false"
				@input="updateField('textAlign', $event)" />
		</template>

		<template v-else>
			<TextTableEditor
				:value="tableData"
				@input="onTableDataChange" />
		</template>
	</div>
</template>

<script>
import { NcTextField, NcSelect } from '@conduction/nextcloud-vue'
import TextTableEditor from './TextTableEditor.vue'
import { emptyTable, validateTable } from '../../../utils/textTable.js'

const DEFAULT_CONTENT = Object.freeze({
	text: '',
	fontSize: '14px',
	color: '',
	backgroundColor: '',
	textAlign: 'left',
	tableMode: false,
	tableData: null,
})

/**
 * TextDisplayForm is the sub-form for AddWidgetModal when the user is
 * creating or editing a `text` widget placement.
 *
 * Exposes the five controls described in REQ-TXT-004 (textarea, font size
 * input, two colour pickers, alignment select) and a `validate()` method
 * returning `[t('mydash', 'Text is required')]` when text is empty or
 * whitespace-only — matching the AddWidgetModal sub-form contract.
 *
 * REQ-TBLE-002: a top-level "Content type" picker switches between text
 * mode (the original five controls) and table mode (a `TextTableEditor`
 * sub-component editing `content.tableData`). The legacy `text` field is
 * preserved across mode switches so toggling back doesn't lose the user's
 * markdown / plain text. `validate()` defers to `validateTable()` from
 * `utils/textTable.js` when `tableMode` is on.
 */
export default {
	name: 'TextDisplayForm',

	components: {
		NcTextField,
		NcSelect,
		TextTableEditor,
	},

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 * Pre-fills every control from `editingWidget.content`.
		 */
		editingWidget: {
			type: Object,
			default: null,
		},
		/**
		 * Initial content values — used when not editing and the parent
		 * supplies registry defaults.
		 */
		value: {
			type: Object,
			default: () => ({ ...DEFAULT_CONTENT }),
		},
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			text: initial.text ?? DEFAULT_CONTENT.text,
			fontSize: initial.fontSize ?? DEFAULT_CONTENT.fontSize,
			color: initial.color ?? DEFAULT_CONTENT.color,
			backgroundColor: initial.backgroundColor ?? DEFAULT_CONTENT.backgroundColor,
			textAlign: initial.textAlign ?? DEFAULT_CONTENT.textAlign,
			tableMode: initial.tableMode === true,
			tableData: initial.tableData && typeof initial.tableData === 'object'
				? initial.tableData
				: null,
		}
	},

	computed: {
		textAlignOptions() {
			return ['left', 'center', 'right', 'justify']
		},

		modeOptions() {
			return [
				{ id: 'text', label: t('mydash', 'Text') },
				{ id: 'table', label: t('mydash', 'Table') },
			]
		},

		modeOption() {
			return this.modeOptions.find((o) => o.id === (this.tableMode ? 'table' : 'text'))
		},

		assembledContent() {
			return {
				text: this.text,
				fontSize: this.fontSize,
				color: this.color,
				backgroundColor: this.backgroundColor,
				textAlign: this.textAlign,
				tableMode: this.tableMode,
				tableData: this.tableData,
			}
		},
	},

	methods: {
		/**
		 * Set a field and notify parent.
		 *
		 * @param {string} field one of: text, fontSize, color, backgroundColor, textAlign
		 * @param {string} value new value
		 */
		updateField(field, value) {
			this[field] = value
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Switch between text and table content type. Initialises an empty
		 * 1×1 tableData on first switch to table mode (REQ-TBLE-002 minimal
		 * default). Preserves the original text so toggling back is lossless.
		 *
		 * @param {object} option the selected modeOptions item
		 */
		onModeChange(option) {
			const id = option?.id || 'text'
			this.tableMode = id === 'table'
			if (this.tableMode && !this.tableData) {
				this.tableData = emptyTable()
			}
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Receive the latest tableData from `TextTableEditor` and bubble up
		 * through the standard sub-form contract.
		 *
		 * @param {object} next the next tableData value
		 */
		onTableDataChange(next) {
			this.tableData = next
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Returns a list of error strings; empty array means valid.
		 *
		 * @return {string[]} validation errors
		 */
		validate() {
			if (this.tableMode) {
				return validateTable(this.tableData)
			}
			if (typeof this.text !== 'string' || this.text.trim() === '') {
				return [t('mydash', 'Text is required')]
			}
			return []
		},
	},
}
</script>

<style scoped>
.text-display-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.text-display-form__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: 14px;
}

.text-display-form__textarea {
	width: 100%;
	min-height: 96px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
	resize: vertical;
}

.text-display-form__color-label {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	font-size: 14px;
}

.text-display-form__color {
	width: 48px;
	height: 32px;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	background: transparent;
}
</style>
