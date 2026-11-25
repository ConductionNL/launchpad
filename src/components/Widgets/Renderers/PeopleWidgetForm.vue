<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="people-widget-form">
		<label v-if="offerNew" class="people-widget-form__field">
			{{ t('launchpad', 'Show') }}
			<select
				class="people-widget-form__input"
				data-testid="people-selection"
				:value="newChosen ? 'new' : 'people'"
				@change="chooseSelection($event.target.value)">
				<option value="people">
					{{ t('launchpad', 'People from the directory') }}
				</option>
				<option value="new">
					{{ t('launchpad', 'New colleagues') }}
				</option>
			</select>
		</label>

		<template v-if="newChosen">
			<label class="people-widget-form__field">
				{{ t('launchpad', 'Started in the last (days, 1–90)') }}
				<input
					type="number"
					class="people-widget-form__input"
					data-testid="people-new-since"
					:min="windows.since.min"
					:max="windows.since.max"
					:value="sinceDays"
					@input="setWindow('since', $event.target.value)">
				<span v-if="errors.since" class="people-widget-form__error" role="alert">{{ errors.since }}</span>
			</label>
			<label class="people-widget-form__field">
				{{ t('launchpad', 'Starts in the next (days, 0–30)') }}
				<input
					type="number"
					class="people-widget-form__input"
					data-testid="people-new-until"
					:min="windows.until.min"
					:max="windows.until.max"
					:value="untilDays"
					@input="setWindow('until', $event.target.value)">
				<span v-if="errors.until" class="people-widget-form__error" role="alert">{{ errors.until }}</span>
			</label>
			<p v-if="startFieldSet === false" class="people-widget-form__error" role="alert">
				{{ t('launchpad', 'The administrator has not set a start date field, so new colleagues cannot be listed.') }}
			</p>
		</template>

		<CnPeopleWidgetForm
			ref="form"
			:editingWidget="editingWidget"
			:value="value"
			@update:content="onContent" />
	</div>
</template>

<script>
import { CnPeopleWidgetForm } from '@conduction/nextcloud-vue'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NULLABLE_OBJECT_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { NEW_SELECTION_MODE, NEW_WINDOWS, windowDays } from '../../../services/newColleagues.js'

/**
 * The people widget form with the "New colleagues" selection (REQ-PPL-014):
 * a choice between people from the directory and new colleagues, offered
 * only when a profile field is the start date, and the two windows (days
 * back, days ahead). Every other setting is CnPeopleWidgetForm's.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export default {
	name: 'PeopleWidgetForm',

	components: { CnPeopleWidgetForm },

	props: {
		/** The people placement being edited; null when a new one is added. */
		editingWidget: NULLABLE_OBJECT_PROP,

		/** The content a new placement starts from. */
		value: OBJECT_PROP,
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			windows: NEW_WINDOWS,
			newChosen: initial.selectionMode === NEW_SELECTION_MODE,
			sinceDays: windowDays(initial, 'since'),
			untilDays: windowDays(initial, 'until'),
			errors: { since: '', until: '' },
			// null until the server answers; then whether a field is the start date.
			startFieldSet: null,
			innerContent: null,
		}
	},

	computed: {
		/**
		 * Offer the choice when a start date field exists, or when the
		 * placement already lists new colleagues (so it can be switched back).
		 *
		 * @return {boolean}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		offerNew() {
			return this.startFieldSet === true || this.newChosen
		},

		/**
		 * The content the dialog stores: the shared form's, with the selection
		 * and, for new colleagues, the two windows.
		 *
		 * @return {object|undefined}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		assembledContent() {
			const inner = this.innerContent || this.$refs.form?.assembledContent
			if (!inner) {
				return undefined
			}
			const { newSinceDays, newUntilDays, ...rest } = inner
			if (!this.newChosen) {
				return { ...rest, selectionMode: rest.selectionMode === NEW_SELECTION_MODE ? 'filter' : rest.selectionMode }
			}
			return { ...rest, selectionMode: NEW_SELECTION_MODE, newSinceDays: this.sinceDays, newUntilDays: this.untilDays }
		},
	},

	/**
	 * Read the shared form's first content and ask whether a start date field exists.
	 *
	 * @return {void}
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	mounted() {
		this.innerContent = this.$refs.form?.assembledContent || null
		this.probeStartField()
	},

	methods: {
		t,

		/**
		 * Ask the server whether a profile field is the start date: the New
		 * colleagues list over an empty window says `configured`.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		async probeStartField() {
			try {
				const response = await axios.get(generateUrl('/apps/launchpad/api/people/new'), {
					params: { since: NEW_WINDOWS.since.min, until: NEW_WINDOWS.until.min },
				})
				this.startFieldSet = response?.data?.configured === true
			} catch {
				this.startFieldSet = false
			}
		},

		/**
		 * The shared form changed.
		 *
		 * @param {object} content Its assembled content.
		 * @return {void}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		onContent(content) {
			this.innerContent = content
			this.emitContent()
		},

		/**
		 * Switch between people from the directory and new colleagues.
		 *
		 * @param {string} choice `people` or `new`.
		 * @return {void}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		chooseSelection(choice) {
			this.newChosen = choice === 'new'
			this.emitContent()
		},

		/**
		 * Set a window; a value outside its range keeps an inline error (and
		 * the last valid value) until it is corrected.
		 *
		 * @param {'since'|'until'} bound Which window.
		 * @param {string} raw The typed value.
		 * @return {void}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		setWindow(bound, raw) {
			const range = NEW_WINDOWS[bound]
			const days = Number(raw)
			if (raw === '' || !Number.isInteger(days) || days < range.min || days > range.max) {
				this.errors[bound] = t('launchpad', 'Must be between {min} and {max}', { min: range.min, max: range.max })
			} else {
				this.errors[bound] = ''
				this[bound === 'since' ? 'sinceDays' : 'untilDays'] = days
			}
			this.emitContent()
		},

		/**
		 * Pass the assembled content up.
		 *
		 * @return {void}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		emitContent() {
			const content = this.assembledContent
			if (content) {
				this.$emit('update:content', content)
			}
		},

		/**
		 * The shared form's errors, plus a window out of range.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		validate() {
			const errors = this.$refs.form ? [...this.$refs.form.validate()] : []
			if (this.newChosen) {
				errors.push(...[this.errors.since, this.errors.until].filter((error) => error !== ''))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.people-widget-form__field {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline, 4px) * 1);
	margin-bottom: calc(var(--default-grid-baseline, 4px) * 3);
}

.people-widget-form__input {
	max-width: 320px;
}

.people-widget-form__error {
	color: var(--color-error-text, var(--color-error));
}
</style>
