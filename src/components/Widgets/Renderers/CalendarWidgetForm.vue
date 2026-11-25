<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="calendar-widget-form">
		<fieldset class="calendar-widget-form__group">
			<legend class="calendar-widget-form__legend">
				{{ t('launchpad', 'Calendars') }}
			</legend>
			<p v-if="loading" class="calendar-widget-form__hint">
				{{ t('launchpad', 'Loading…') }}
			</p>
			<NcCheckboxRadioSwitch
				v-for="calendar in availableCalendars"
				:key="calendar.key"
				:modelValue="internalCalendars.includes(calendar.key)"
				:data-testid="'calendar-' + calendar.key"
				@update:modelValue="toggleCalendar(calendar.key, $event)">
				<span
					class="calendar-widget-form__swatch"
					:style="{ background: calendar.color || 'var(--color-primary-element)' }"
					aria-hidden="true" />
				{{ calendar.name || calendar.key }}
			</NcCheckboxRadioSwitch>
			<!-- A stored calendar that is no longer offered stays listed, so
			     saving never drops it without the editor seeing it. -->
			<NcCheckboxRadioSwitch
				v-for="key in unlistedCalendars"
				:key="key"
				:modelValue="true"
				@update:modelValue="toggleCalendar(key, $event)">
				{{ key }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<fieldset class="calendar-widget-form__group">
			<legend class="calendar-widget-form__legend">
				{{ t('launchpad', 'External calendar (ICS address)') }}
			</legend>
			<ul v-if="externalIcsUrls.length > 0" class="calendar-widget-form__urls">
				<li v-for="(address, index) in externalIcsUrls" :key="address" class="calendar-widget-form__url">
					<span class="calendar-widget-form__url-text">{{ address }}</span>
					<NcButton
						variant="tertiary"
						:aria-label="t('launchpad', 'Remove')"
						@click="removeIcs(index)">
						{{ t('launchpad', 'Remove') }}
					</NcButton>
				</li>
			</ul>
			<div class="calendar-widget-form__add">
				<NcTextField
					:modelValue="newIcs"
					:label="t('launchpad', 'External calendar (ICS address)')"
					placeholder="https://example.com/calendar.ics"
					:error="icsError !== ''"
					:helperText="icsError"
					@update:modelValue="onIcsInput"
					@keydown.enter.prevent="addIcs" />
				<NcButton :disabled="newIcs.trim() === ''" @click="addIcs">
					{{ t('launchpad', 'Add') }}
				</NcButton>
			</div>
		</fieldset>

		<fieldset class="calendar-widget-form__group calendar-widget-form__periods">
			<legend class="calendar-widget-form__legend">
				{{ t('launchpad', 'Period') }}
			</legend>
			<label
				v-for="option in periodOptions"
				:key="option.value"
				class="calendar-widget-form__period"
				:class="{ 'calendar-widget-form__period--selected': period === option.value }">
				<input
					type="radio"
					name="calendar-period"
					:value="option.value"
					:checked="period === option.value"
					:data-testid="'calendar-period-' + option.value"
					@change="onPeriodChange(option.value)">
				{{ option.label }}
			</label>
		</fieldset>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'
import { contentForPeriod, isIcsAddress, periodOf } from '../../../utils/calendarPeriod.js'

const DEFAULT_CONTENT = Object.freeze({
	internalCalendars: [],
	externalIcsUrls: [],
	viewMode: 'agenda',
	daysAhead: 7,
	colorByCalendar: true,
})

/**
 * CalendarWidgetForm — the content part of a Calendar widget as board
 * LpWidgetInstellen draws it (REQ-CAL-020): the reader's calendars to choose
 * from, external calendars by ICS address, and the period 7 days or Month.
 * Stores the content the Calendar renderer already reads (`internalCalendars`,
 * `externalIcsUrls`, `viewMode`, `daysAhead`, `colorByCalendar`).
 *
 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
 */
export default {
	name: 'CalendarWidgetForm',

	components: { NcButton, NcCheckboxRadioSwitch, NcTextField },

	props: {
		/** The placement being edited, or `null` in create mode. */
		editingWidget: NULLABLE_OBJECT_PROP,

		/** Initial content values, used when not editing. */
		value: objectProp(DEFAULT_CONTENT),

		/**
		 * `async () => [{key, name, color}]`: the reader's calendars, passed
		 * by the modal.
		 */
		calendarsFetcher: {
			type: Function,
			default: null,
		},
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			internalCalendars: Array.isArray(initial.internalCalendars) ? [...initial.internalCalendars] : [],
			externalIcsUrls: Array.isArray(initial.externalIcsUrls) ? [...initial.externalIcsUrls] : [],
			period: periodOf(initial),
			colorByCalendar: initial.colorByCalendar !== false,
			availableCalendars: [],
			loading: false,
			newIcs: '',
			icsError: '',
		}
	},

	computed: {
		/**
		 * The two periods.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		periodOptions() {
			return [
				{ value: 'week', label: t('launchpad', '7 days') },
				{ value: 'month', label: t('launchpad', 'Month') },
			]
		},

		/**
		 * Stored calendars the fetcher no longer offers.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		unlistedCalendars() {
			const offered = new Set(this.availableCalendars.map((calendar) => calendar.key))
			return this.loading ? [] : this.internalCalendars.filter((key) => !offered.has(key))
		},

		/**
		 * The added ICS addresses plus a valid one still in the field, so an
		 * address typed but not added is not lost on save.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		icsWithPending() {
			const pending = this.newIcs.trim()
			if (pending !== '' && isIcsAddress(pending) && !this.externalIcsUrls.includes(pending)) {
				return [...this.externalIcsUrls, pending]
			}
			return [...this.externalIcsUrls]
		},

		/**
		 * The stored content.
		 *
		 * @return {object}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		assembledContent() {
			return {
				internalCalendars: [...this.internalCalendars],
				externalIcsUrls: this.icsWithPending,
				...contentForPeriod(this.period),
				colorByCalendar: this.colorByCalendar,
			}
		},
	},

	mounted() {
		this.fetchCalendars()
	},

	methods: {
		t,

		/** Emit the content. */
		emitUpdate() {
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Load the reader's calendars.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		async fetchCalendars() {
			if (typeof this.calendarsFetcher !== 'function') {
				return
			}
			this.loading = true
			try {
				const list = await this.calendarsFetcher()
				this.availableCalendars = Array.isArray(list) ? list.filter((calendar) => calendar && calendar.key) : []
			} catch {
				this.availableCalendars = []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Choose or drop one of the reader's calendars.
		 *
		 * @param {string} key The calendar key.
		 * @param {boolean} on Whether it is chosen.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		toggleCalendar(key, on) {
			const without = this.internalCalendars.filter((item) => item !== key)
			this.internalCalendars = on ? [...without, key] : without
			this.emitUpdate()
		},

		/**
		 * Typing in the ICS field clears its error.
		 *
		 * @param {string} val The typed address.
		 */
		onIcsInput(val) {
			this.newIcs = typeof val === 'string' ? val : ''
			this.icsError = ''
			this.emitUpdate()
		},

		/**
		 * Add the typed ICS address; it must be https.
		 *
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		addIcs() {
			const address = this.newIcs.trim()
			if (address === '') {
				return
			}
			if (!isIcsAddress(address)) {
				this.icsError = t('launchpad', 'An ICS address starts with https://')
				return
			}
			if (!this.externalIcsUrls.includes(address)) {
				this.externalIcsUrls = [...this.externalIcsUrls, address]
			}
			this.newIcs = ''
			this.emitUpdate()
		},

		/**
		 * Remove an added ICS address.
		 *
		 * @param {number} index Its position.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		removeIcs(index) {
			this.externalIcsUrls = this.externalIcsUrls.filter((_, at) => at !== index)
			this.emitUpdate()
		},

		/**
		 * Choose 7 days or Month.
		 *
		 * @param {string} val `week` or `month`.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		onPeriodChange(val) {
			this.period = val === 'month' ? 'month' : 'week'
			this.emitUpdate()
		},

		/**
		 * The form's errors: at least one calendar, and every address https.
		 * An address still in the field counts. Pure: the modal calls it from a
		 * computed on every input.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
		 */
		validate() {
			const errors = []
			const pending = this.newIcs.trim()
			if (pending !== '' && !isIcsAddress(pending)) {
				errors.push(t('launchpad', 'An ICS address starts with https://'))
			}
			if (this.internalCalendars.length === 0 && this.icsWithPending.length === 0) {
				errors.push(t('launchpad', 'Choose at least one calendar or add an ICS address'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.calendar-widget-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.calendar-widget-form__group {
	display: flex;
	flex-direction: column;
	gap: 4px;
	border: 0;
	margin: 0;
	padding: 0;
}

.calendar-widget-form__legend {
	margin-bottom: 4px;
	font-weight: 600;
}

.calendar-widget-form__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.calendar-widget-form__swatch {
	display: inline-block;
	width: 10px;
	height: 10px;
	margin-inline-end: 6px;
	border-radius: 50%;
}

.calendar-widget-form__urls {
	margin: 0;
	padding: 0;
	list-style: none;
}

.calendar-widget-form__url {
	display: flex;
	gap: 8px;
	align-items: center;
}

.calendar-widget-form__url-text {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.calendar-widget-form__add {
	display: flex;
	gap: 8px;
	align-items: flex-start;
}

.calendar-widget-form__add > :first-child {
	flex: 1;
}

.calendar-widget-form__periods {
	flex-direction: row;
	flex-wrap: wrap;
	gap: 8px;
}

.calendar-widget-form__periods .calendar-widget-form__legend {
	width: 100%;
}

.calendar-widget-form__period {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	padding: 4px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	cursor: pointer;
}

.calendar-widget-form__period--selected {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}
</style>
