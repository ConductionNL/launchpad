<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	VisibilityRuleRow — one conditional-visibility rule
	(conditional-visibility-editor spec, REQ-CVUI-002).

	Lets an author configure any of the four supported rule types
	(group / time / date / attribute) with type-appropriate operand
	fields, plus an include/exclude toggle. Owns its own local edit
	state (seeded once from the `rule` prop — each row instance is keyed
	1:1 to one rule by the parent's `v-for :key`) and emits:
	  - `update:rule`  the live canonical `{ruleType, ruleConfig,
	                    isInclude}` on every valid change, so the parent's
	                    copy stays in sync for the preview affordance
	                    even before Save is pressed (REQ-CVUI-004
	                    "Preview evaluates unsaved edits").
	  - `save`          the same payload, requesting persistence.
	  - `remove`        requesting the row be deleted / discarded.
-->

<template>
	<div class="visibility-rule-row" data-test="visibility-rule-row">
		<div class="visibility-rule-row__fields">
			<NcSelect
				v-model="typeOption"
				class="visibility-rule-row__field"
				:inputLabel="t('launchpad', 'Rule type')"
				:options="typeOptions"
				label="label"
				trackBy="id"
				:clearable="false"
				data-test="rule-type-select" />

			<!-- group -->
			<div
				v-if="local.ruleType === 'group'"
				class="visibility-rule-row__field">
				<NcSelectTags
					v-model="local.ruleConfig.groups"
					:options="availableGroups"
					:multiple="true"
					:aria-label-combobox="t('launchpad', 'Groups')"
					:placeholder="t('launchpad', 'Select groups')"
					data-test="rule-groups"
					@update:modelValue="onChange" />
			</div>

			<!-- time -->
			<template v-else-if="local.ruleType === 'time'">
				<NcTextField
					class="visibility-rule-row__field"
					:modelValue="local.ruleConfig.startTime"
					:label="t('launchpad', 'Start time (HH:MM)')"
					placeholder="09:00"
					:error="
						!!local.ruleConfig.startTime
						&& !isValidTime(local.ruleConfig.startTime)
					"
					:helperText="
						!!local.ruleConfig.startTime
						&& !isValidTime(local.ruleConfig.startTime)
							? t('launchpad', 'Use HH:MM, e.g. 09:00')
							: ''
					"
					data-test="rule-start-time"
					@update:modelValue="onStartTimeChange" />
				<NcTextField
					class="visibility-rule-row__field"
					:modelValue="local.ruleConfig.endTime"
					:label="t('launchpad', 'End time (HH:MM)')"
					placeholder="17:00"
					:error="
						!!local.ruleConfig.endTime
						&& !isValidTime(local.ruleConfig.endTime)
					"
					:helperText="
						!!local.ruleConfig.endTime
						&& !isValidTime(local.ruleConfig.endTime)
							? t('launchpad', 'Use HH:MM, e.g. 17:00')
							: ''
					"
					data-test="rule-end-time"
					@update:modelValue="onEndTimeChange" />
				<div class="visibility-rule-row__field visibility-rule-row__days">
					<span class="visibility-rule-row__days-label">{{
						t(
							'launchpad',
							'Days (optional — all days when none selected)',
						)
					}}</span>
					<NcCheckboxRadioSwitch
						v-for="day in dayOptions"
						:key="day.id"
						:modelValue="local.ruleConfig.days.includes(day.id)"
						type="switch"
						data-test="rule-day-toggle"
						@update:modelValue="toggleDay(day.id)">
						{{ day.label }}
					</NcCheckboxRadioSwitch>
				</div>
			</template>

			<!-- date -->
			<template v-else-if="local.ruleType === 'date'">
				<NcTextField
					class="visibility-rule-row__field"
					:modelValue="local.ruleConfig.startDate"
					:label="t('launchpad', 'Start date (YYYY-MM-DD)')"
					placeholder="2026-12-01"
					:error="
						!!local.ruleConfig.startDate
						&& !isValidDate(local.ruleConfig.startDate)
					"
					:helperText="
						!!local.ruleConfig.startDate
						&& !isValidDate(local.ruleConfig.startDate)
							? t('launchpad', 'Use YYYY-MM-DD')
							: ''
					"
					data-test="rule-start-date"
					@update:modelValue="onStartDateChange" />
				<NcTextField
					class="visibility-rule-row__field"
					:modelValue="local.ruleConfig.endDate"
					:label="t('launchpad', 'End date (YYYY-MM-DD)')"
					placeholder="2026-12-31"
					:error="
						!!local.ruleConfig.endDate
						&& !isValidDate(local.ruleConfig.endDate)
					"
					:helperText="
						!!local.ruleConfig.endDate
						&& !isValidDate(local.ruleConfig.endDate)
							? t('launchpad', 'Use YYYY-MM-DD')
							: ''
					"
					data-test="rule-end-date"
					@update:modelValue="onEndDateChange" />
			</template>

			<!-- attribute -->
			<template v-else-if="local.ruleType === 'attribute'">
				<NcTextField
					class="visibility-rule-row__field"
					:modelValue="local.ruleConfig.attribute"
					:label="t('launchpad', 'Attribute')"
					placeholder="language"
					data-test="rule-attribute"
					@update:modelValue="
						onAttributeFieldChange('attribute', $event)
					" />
				<NcSelect
					v-model="operatorOption"
					class="visibility-rule-row__field"
					:inputLabel="t('launchpad', 'Operator')"
					:options="operatorOptions"
					label="label"
					trackBy="id"
					:clearable="false"
					data-test="rule-operator" />
				<NcTextField
					class="visibility-rule-row__field"
					:modelValue="local.ruleConfig.value"
					:label="t('launchpad', 'Value')"
					placeholder="nl"
					data-test="rule-value"
					@update:modelValue="onAttributeFieldChange('value', $event)" />
			</template>
		</div>

		<!-- Include/exclude — two labelled radio buttons, not a bare
		     switch, so the state is legible from text, not colour or
		     position alone (WCAG 2.1 AA 1.4.1). -->
		<fieldset class="visibility-rule-row__mode" data-test="rule-mode">
			<legend class="visibility-rule-row__mode-legend">
				{{ t('launchpad', 'Effect') }}
			</legend>
			<NcCheckboxRadioSwitch
				:modelValue="local.isInclude === true"
				type="radio"
				:name="'rule-mode-' + rowKey"
				data-test="rule-mode-include"
				@update:modelValue="setMode(true)">
				{{ t('launchpad', 'Include — show when matched') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:modelValue="local.isInclude === false"
				type="radio"
				:name="'rule-mode-' + rowKey"
				data-test="rule-mode-exclude"
				@update:modelValue="setMode(false)">
				{{ t('launchpad', 'Exclude — hide when matched') }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<div class="visibility-rule-row__actions">
			<NcButton
				type="primary"
				:disabled="busy || !isValid"
				data-test="rule-save"
				@click="save">
				{{ isNew ? t('launchpad', 'Add rule') : t('launchpad', 'Save') }}
			</NcButton>
			<NcButton
				type="tertiary"
				:disabled="busy"
				:aria-label="
					isNew
						? t('launchpad', 'Discard rule')
						: t('launchpad', 'Remove rule')
				"
				data-test="rule-remove"
				@click="$emit('remove')">
				<template #icon>
					<Delete :size="18" />
				</template>
				{{ isNew ? t('launchpad', 'Discard') : t('launchpad', 'Remove') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcSelect,
	NcSelectTags,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Delete from 'vue-material-design-icons/Delete.vue'

const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/

/**
 * Build a pristine, type-correct `ruleConfig` for a given ruleType. Used
 * both to seed a fresh row and to reset operands when the author switches
 * ruleType mid-edit.
 *
 * @param {string} ruleType one of group/time/date/attribute
 * @return {object} an empty-but-shaped ruleConfig for that type
 */
function emptyConfigFor(ruleType) {
	switch (ruleType) {
		case 'group':
			return { groups: [] }
		case 'time':
			return { startTime: '', endTime: '', days: [] }
		case 'date':
			return { startDate: '', endDate: '' }
		case 'attribute':
			return { attribute: '', operator: 'equals', value: '' }
		default:
			return {}
	}
}

export default {
	name: 'VisibilityRuleRow',

	components: {
		NcButton,
		NcSelect,
		NcSelectTags,
		NcTextField,
		NcCheckboxRadioSwitch,
		Delete,
	},

	props: {
		/** The rule this row edits: `{id?, ruleType, ruleConfig, isInclude}`. */
		rule: {
			type: Object,
			required: true,
		},

		/** Group-id options for the group-rule multi-select. */
		availableGroups: {
			type: Array,
			default: () => [],
		},

		/** Disables Save/Remove while a request for this row is in flight. */
		busy: {
			type: Boolean,
			default: false,
		},

		/** True for a not-yet-persisted draft row (no `id` yet). */
		isNew: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:rule', 'save', 'remove'],

	data() {
		// Seeded ONCE from the `rule` prop. Each row instance is keyed 1:1
		// to one rule by the parent's `v-for :key`, so there is no need to
		// re-sync from the prop after mount — this component owns the
		// authoritative edit buffer for its row.
		return {
			local: this.cloneRule(this.rule),
		}
	},

	computed: {
		/** @spec openspec/specs/conditional-visibility-editor/spec.md */
		rowKey() {
			return this.rule.id ?? this.rule._localKey ?? 'draft'
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md */
		typeOptions() {
			return [
				{ id: 'group', label: t('launchpad', 'Group') },
				{ id: 'time', label: t('launchpad', 'Time of day') },
				{ id: 'date', label: t('launchpad', 'Date range') },
				{ id: 'attribute', label: t('launchpad', 'User attribute') },
			]
		},

		typeOption: {
			/** @spec openspec/specs/conditional-visibility-editor/spec.md */
			get() {
				return (
					this.typeOptions.find((o) => o.id === this.local.ruleType)
					|| this.typeOptions[0]
				)
			},

			/**
			 * Switch the rule type, resetting its config to the new type's
			 * empty shape.
			 *
			 * @param {object|null} option Selected type option.
			 * @spec openspec/specs/conditional-visibility-editor/spec.md
			 */
			set(option) {
				if (!option || option.id === this.local.ruleType) {
					return
				}
				this.local.ruleType = option.id
				this.local.ruleConfig = emptyConfigFor(option.id)
				this.onChange()
			},
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md */
		operatorOptions() {
			return [
				{ id: 'equals', label: t('launchpad', 'Equals') },
				{ id: 'not_equals', label: t('launchpad', 'Does not equal') },
				{ id: 'contains', label: t('launchpad', 'Contains') },
				{ id: 'starts_with', label: t('launchpad', 'Starts with') },
				{ id: 'ends_with', label: t('launchpad', 'Ends with') },
			]
		},

		operatorOption: {
			/** @spec openspec/specs/conditional-visibility-editor/spec.md */
			get() {
				return (
					this.operatorOptions.find(
						(o) => o.id === this.local.ruleConfig.operator,
					) || this.operatorOptions[0]
				)
			},

			/**
			 * Set the comparison operator, defaulting to `equals` when the
			 * selection is cleared.
			 *
			 * @param {object|null} option Selected operator option.
			 * @spec openspec/specs/conditional-visibility-editor/spec.md
			 */
			set(option) {
				this.local.ruleConfig.operator = option ? option.id : 'equals'
				this.onChange()
			},
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md */
		dayOptions() {
			return [
				{ id: 'mon', label: t('launchpad', 'Mon') },
				{ id: 'tue', label: t('launchpad', 'Tue') },
				{ id: 'wed', label: t('launchpad', 'Wed') },
				{ id: 'thu', label: t('launchpad', 'Thu') },
				{ id: 'fri', label: t('launchpad', 'Fri') },
				{ id: 'sat', label: t('launchpad', 'Sat') },
				{ id: 'sun', label: t('launchpad', 'Sun') },
			]
		},

		/**
		 * Client-side validation gate for the Save button (REQ-CVUI-002 /
		 * tasks.md "client-side operand validation. blocks malformed
		 * operands (bad time, empty groups)").
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {boolean} whether the current draft can be saved.
		 */
		isValid() {
			switch (this.local.ruleType) {
				case 'group':
					return (
						Array.isArray(this.local.ruleConfig.groups)
						&& this.local.ruleConfig.groups.length > 0
					)
				case 'time':
					return (
						this.isValidTime(this.local.ruleConfig.startTime)
						&& this.isValidTime(this.local.ruleConfig.endTime)
					)
				case 'date':
					return this.hasAtLeastOneValidDate()
				case 'attribute':
					return (
						!!this.local.ruleConfig.attribute
						&& !!this.local.ruleConfig.operator
						&& this.local.ruleConfig.value !== ''
					)
				default:
					return false
			}
		},
	},

	methods: {
		t,

		/**
		 * Deep-clone a rule prop into a fresh, type-shaped local buffer.
		 *
		 * @param {object} rule the source rule
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {object} an independent local copy
		 */
		cloneRule(rule) {
			const ruleType = rule.ruleType || 'group'
			return {
				ruleType,
				ruleConfig: {
					...emptyConfigFor(ruleType),
					...(rule.ruleConfig || {}),
				},

				isInclude: rule.isInclude !== false,
			}
		},

		isValidTime(value) {
			return typeof value === 'string' && TIME_RE.test(value)
		},

		isValidDate(value) {
			return typeof value === 'string' && DATE_RE.test(value)
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md */
		hasAtLeastOneValidDate() {
			const { startDate, endDate } = this.local.ruleConfig
			const hasStart = !!startDate
			const hasEnd = !!endDate
			if (!hasStart && !hasEnd) {
				return false
			}
			if (hasStart && !this.isValidDate(startDate)) {
				return false
			}
			if (hasEnd && !this.isValidDate(endDate)) {
				return false
			}
			return true
		},

		/**
		 * Set the rule's daily start time.
		 *
		 * @param {string} value `HH:MM` start time.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		onStartTimeChange(value) {
			this.local.ruleConfig.startTime = value
			this.onChange()
		},

		/**
		 * Set the rule's daily end time.
		 *
		 * @param {string} value `HH:MM` end time.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		onEndTimeChange(value) {
			this.local.ruleConfig.endTime = value
			this.onChange()
		},

		/**
		 * Set the first date on which the rule applies.
		 *
		 * @param {string} value ISO date string.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		onStartDateChange(value) {
			this.local.ruleConfig.startDate = value
			this.onChange()
		},

		/**
		 * Set the last date on which the rule applies.
		 *
		 * @param {string} value ISO date string.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		onEndDateChange(value) {
			this.local.ruleConfig.endDate = value
			this.onChange()
		},

		/**
		 * Set one field of an attribute-type rule's config.
		 *
		 * @param {string} field Config key to write.
		 * @param {string|number|boolean|string[]|null} value New value for that key.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		onAttributeFieldChange(field, value) {
			this.local.ruleConfig[field] = value
			this.onChange()
		},

		/**
		 * Add or remove a weekday from the rule's active days.
		 *
		 * @param {string|number} dayId Identifier of the day to toggle.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		toggleDay(dayId) {
			const days = this.local.ruleConfig.days || []
			const index = days.indexOf(dayId)
			if (index === -1) {
				this.local.ruleConfig.days = [...days, dayId]
			} else {
				this.local.ruleConfig.days = days.filter((d) => d !== dayId)
			}
			this.onChange()
		},

		/**
		 * Switch the rule between include and exclude semantics.
		 *
		 * @param {boolean} isInclude True for an include rule, false for
		 *   an exclude rule.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		setMode(isInclude) {
			this.local.isInclude = isInclude
			this.onChange()
		},

		/**
		 * Build the canonical `ruleConfig` for the CURRENT type, dropping
		 * empty optional keys (REQ-CVUI-002 date scenario: "MUST NOT emit
		 * an empty endDate key").
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {object} the canonical ruleConfig blob.
		 */
		buildRuleConfig() {
			switch (this.local.ruleType) {
				case 'group':
					return { groups: [...(this.local.ruleConfig.groups || [])] }
				case 'time': {
					const config = {
						startTime: this.local.ruleConfig.startTime,
						endTime: this.local.ruleConfig.endTime,
					}
					if (
						Array.isArray(this.local.ruleConfig.days)
						&& this.local.ruleConfig.days.length > 0
					) {
						config.days = [...this.local.ruleConfig.days]
					}
					return config
				}
				case 'date': {
					const config = {}
					if (this.local.ruleConfig.startDate) {
						config.startDate = this.local.ruleConfig.startDate
					}
					if (this.local.ruleConfig.endDate) {
						config.endDate = this.local.ruleConfig.endDate
					}
					return config
				}
				case 'attribute':
					return {
						attribute: this.local.ruleConfig.attribute,
						operator: this.local.ruleConfig.operator || 'equals',
						value: this.local.ruleConfig.value,
					}
				default:
					return {}
			}
		},

		/**
		 * The canonical, emit-ready payload for the current draft.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {{ruleType: string, ruleConfig: object, isInclude: boolean}}
		 */
		buildPayload() {
			return {
				ruleType: this.local.ruleType,
				ruleConfig: this.buildRuleConfig(),
				isInclude: this.local.isInclude,
			}
		},

		/**
		 * Live-sync every change up to the parent so the in-editor rule set
		 * (used by preview) never lags the row's own state, even before
		 * Save is pressed.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {void}
		 */
		onChange() {
			this.$emit('update:rule', this.buildPayload())
		},

		/**
		 * Request persistence of the current draft. Only fires when
		 * client-side validation passes.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {void}
		 */
		save() {
			if (!this.isValid) {
				return
			}
			this.$emit('save', this.buildPayload())
		},
	},
}
</script>

<style scoped>
.visibility-rule-row {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px;
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.visibility-rule-row__fields {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
}

.visibility-rule-row__field {
	min-width: 160px;
	flex: 1 1 160px;
}

.visibility-rule-row__days {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.visibility-rule-row__days-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.visibility-rule-row__mode {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px;
	border: none;
	margin: 0;
	padding: 0;
}

.visibility-rule-row__mode-legend {
	font-size: 12px;
	font-weight: 600;
	padding: 0;
}

.visibility-rule-row__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
}
</style>
