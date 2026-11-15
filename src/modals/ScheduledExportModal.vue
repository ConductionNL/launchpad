<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal launchpad-modal--wide"
		size="large"
		:name="title"
		@close="$emit('close')">
		<form
			class="export-editor"
			data-testid="export-editor"
			@submit.prevent="save">
			<h2 class="launchpad-modal__title">
				{{ title }}
			</h2>

			<NcNoteCard
				v-if="error"
				type="error"
				role="alert"
				data-testid="export-editor-error">
				{{ error }}
			</NcNoteCard>

			<!-- Basic -->
			<section
				class="export-editor__section"
				aria-labelledby="export-editor-basic">
				<h3 id="export-editor-basic">
					{{ t('launchpad', 'General') }}
				</h3>
				<NcTextField
					v-model="form.name"
					:label="t('launchpad', 'Name')"
					data-testid="export-name" />
				<NcTextArea
					v-model="form.description"
					:label="t('launchpad', 'Description (optional)')"
					:helperText="
						t(
							'launchpad',
							'Shown in the email and on the cover page of the PDF.',
						)
					"
					data-testid="export-description" />
				<NcSelect
					v-model="form.subject"
					:inputLabel="t('launchpad', 'What to export')"
					:options="subjectOptions"
					label="label"
					:clearable="false"
					data-testid="export-subject" />
				<NcCheckboxRadioSwitch
					v-model="form.enabled"
					type="switch"
					data-testid="export-enabled">
					{{ t('launchpad', 'Run on schedule') }}
				</NcCheckboxRadioSwitch>
			</section>

			<!-- Recurrence -->
			<section
				class="export-editor__section"
				aria-labelledby="export-editor-recurrence">
				<h3 id="export-editor-recurrence">
					{{ t('launchpad', 'When') }}
				</h3>
				<NcSelect
					:modelValue="recurrenceTypeOption"
					:inputLabel="t('launchpad', 'Repeats')"
					:options="recurrenceTypeOptions"
					label="label"
					:clearable="false"
					data-testid="export-recurrence-type"
					@update:modelValue="
						form.recurrence.type = $event?.id ?? 'weekly'
					" />

				<fieldset
					v-if="form.recurrence.type === 'weekly'"
					class="export-editor__days"
					data-testid="export-days">
					<legend>{{ t('launchpad', 'On these days') }}</legend>
					<NcCheckboxRadioSwitch
						v-for="day in weekdays"
						:key="day.code"
						:modelValue="form.recurrence.daysOfWeek.includes(day.code)"
						:data-testid="`export-day-${day.code}`"
						@update:modelValue="toggleDay(day.code, $event)">
						{{ day.label }}
					</NcCheckboxRadioSwitch>
				</fieldset>

				<template v-if="form.recurrence.type === 'monthly'">
					<NcCheckboxRadioSwitch
						v-model="form.recurrence.lastDay"
						type="switch"
						data-testid="export-last-day">
						{{ t('launchpad', 'On the last day of the month') }}
					</NcCheckboxRadioSwitch>
					<NcTextField
						v-if="!form.recurrence.lastDay"
						:modelValue="String(form.recurrence.dayOfMonth)"
						type="number"
						min="1"
						max="31"
						:label="t('launchpad', 'Day of the month')"
						:helperText="
							t(
								'launchpad',
								'In a shorter month the export runs on its last day.',
							)
						"
						data-testid="export-day-of-month"
						@update:modelValue="
							form.recurrence.dayOfMonth =
								Number.parseInt($event, 10) || 1
						" />
				</template>

				<NcTextField
					v-if="form.recurrence.type === 'cron'"
					v-model="form.recurrence.expression"
					:label="t('launchpad', 'Cron expression')"
					:helperText="
						t(
							'launchpad',
							'Five fields: minute, hour, day of the month, month and day of the week, for example 0 9 * * 1.',
						)
					"
					data-testid="export-cron" />
				<NcTextField
					v-else
					v-model="form.recurrence.timeOfDay"
					type="time"
					:label="t('launchpad', 'Time')"
					data-testid="export-time" />

				<NcTextField
					v-model="form.runTimezone"
					:label="t('launchpad', 'Time zone')"
					:helperText="t('launchpad', 'For example Europe/Amsterdam.')"
					data-testid="export-timezone" />

				<p
					class="export-editor__summary"
					data-testid="export-recurrence-summary">
					{{ recurrenceSummary }}
				</p>
			</section>

			<!-- Render targets -->
			<section
				class="export-editor__section"
				aria-labelledby="export-editor-targets">
				<h3 id="export-editor-targets">
					{{ t('launchpad', 'Files to make') }}
				</h3>
				<div
					v-for="(target, index) in form.renderTargets"
					:key="target.key"
					class="export-editor__item"
					data-testid="export-target">
					<div class="export-editor__item-head">
						<NcSelect
							:modelValue="
								formatOptions.find(
									(option) => option.id === target.format,
								)
							"
							:inputLabel="t('launchpad', 'Format')"
							:options="formatOptions"
							label="label"
							:clearable="false"
							data-testid="export-target-format"
							@update:modelValue="setFormat(target, $event?.id)" />
						<NcButton
							variant="tertiary"
							:aria-label="t('launchpad', 'Remove this file')"
							:disabled="form.renderTargets.length === 1"
							data-testid="export-target-remove"
							@click="form.renderTargets.splice(index, 1)">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</div>
					<ExportOptionFields
						:fields="formatFields(target.format)"
						:values="target.formatOptions"
						@update="
							(key, value) => (target.formatOptions[key] = value)
						" />
					<NcTextField
						v-model="target.filenamePattern"
						:label="t('launchpad', 'File name')"
						:helperText="
							t(
								'launchpad',
								'You can use {name}, {date}, {recipient} and {ext}.',
							)
						"
						data-testid="export-target-filename" />
				</div>
				<NcButton
					variant="secondary"
					data-testid="export-target-add"
					@click="addTarget">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('launchpad', 'Add a file') }}
				</NcButton>
			</section>

			<!-- Recipients -->
			<section
				class="export-editor__section"
				aria-labelledby="export-editor-recipients">
				<h3 id="export-editor-recipients">
					{{ t('launchpad', 'Recipients') }}
				</h3>
				<div
					v-for="(recipient, index) in form.recipients"
					:key="recipient.key"
					class="export-editor__item"
					data-testid="export-recipient">
					<div class="export-editor__item-head">
						<NcSelect
							:modelValue="
								channelOptions.find(
									(option) => option.id === recipient.channel,
								)
							"
							:inputLabel="t('launchpad', 'Send by')"
							:options="channelOptions"
							label="label"
							:clearable="false"
							data-testid="export-recipient-channel"
							@update:modelValue="setChannel(recipient, $event?.id)" />
						<NcButton
							variant="tertiary"
							:aria-label="t('launchpad', 'Remove this recipient')"
							:disabled="form.recipients.length === 1"
							data-testid="export-recipient-remove"
							@click="form.recipients.splice(index, 1)">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</div>
					<NcCheckboxRadioSwitch v-model="recipient.enabled" type="switch">
						{{ t('launchpad', 'Send to this recipient') }}
					</NcCheckboxRadioSwitch>
					<NcTextField
						v-if="recipient.channel === 'email'"
						v-model="recipient.addresses"
						:label="t('launchpad', 'Email addresses')"
						:helperText="
							t('launchpad', 'Separate addresses with commas.')
						"
						data-testid="export-recipient-addresses" />
					<ExportOptionFields
						v-else
						:fields="channelFields(recipient.channel)"
						:values="recipient.channelConfig"
						@update="
							(key, value) => (recipient.channelConfig[key] = value)
						" />

					<fieldset
						class="export-editor__filters"
						data-testid="export-recipient-filters">
						<legend>
							{{ t('launchpad', 'Filters for this recipient') }}
						</legend>
						<p class="export-editor__hint">
							{{
								t(
									'launchpad',
									'This recipient gets the data filtered on these values, for example gemeenteCode GM0355.',
								)
							}}
						</p>
						<div
							v-for="(filter, filterIndex) in recipient.filters"
							:key="filter.key"
							class="export-editor__filter"
							data-testid="export-filter">
							<NcTextField
								v-model="filter.field"
								:label="t('launchpad', 'Field')"
								data-testid="export-filter-field" />
							<NcTextField
								v-model="filter.value"
								:label="t('launchpad', 'Value')"
								data-testid="export-filter-value" />
							<NcButton
								variant="tertiary"
								:aria-label="t('launchpad', 'Remove this filter')"
								@click="recipient.filters.splice(filterIndex, 1)">
								<template #icon>
									<Delete :size="20" />
								</template>
							</NcButton>
						</div>
						<NcButton
							variant="tertiary"
							data-testid="export-filter-add"
							@click="
								recipient.filters.push({
									key: nextKey(),
									field: '',
									value: '',
								})
							">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('launchpad', 'Add a filter') }}
						</NcButton>
					</fieldset>
				</div>
				<NcButton
					variant="secondary"
					data-testid="export-recipient-add"
					@click="addRecipient">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('launchpad', 'Add a recipient') }}
				</NcButton>
			</section>

			<div class="export-editor__actions">
				<NcButton variant="tertiary" @click="$emit('close')">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="saving"
					data-testid="export-save"
					@click="save">
					{{ t('launchpad', 'Save') }}
				</NcButton>
			</div>
		</form>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcModal,
	NcNoteCard,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ExportOptionFields from '../components/ScheduledExports/ExportOptionFields.vue'
import {
	LIST_PROP,
	NULLABLE_OBJECT_PROP,
	REQUIRED_FLAG_PROP,
} from '../constants/propDefinitions.js'
import { useExportsStore } from '../stores/scheduledExports.js'
import {
	channelLabel,
	EXPORT_CHANNELS,
	EXPORT_FORMATS,
	formatLabel,
	serverMessage,
} from '../utils/scheduledExports.js'

const DAY_CODES = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU']

const DEFAULT_FILENAME = '{name}-{date}.{ext}'

/** Format options a new render target of each format starts with. */
const FORMAT_DEFAULTS = {
	pdf: { pageSize: 'A4', orientation: 'portrait', header: true, footer: true },
	png: { width: 1920, height: 1080, dpi: 96 },
	csv: { delimiter: ',', quoteChar: '"' },
	xlsx: { sheetPerWidget: true },
}

/** Channel settings a new recipient of each channel starts with. */
const CHANNEL_DEFAULTS = {
	email: {},
	nextcloudFiles: { targetPath: '/Reports', overwrite: false },
	webhook: { url: '', signingSecret: '' },
	sftp: {
		host: '',
		port: 22,
		username: '',
		remotePath: '',
		password: '',
		privateKey: '',
		hostKeySha256: '',
	},
}

/** Channel settings that hold a secret: sent only when filled in. */
const SECRET_KEYS = ['password', 'privateKey', 'signingSecret']

let keySeed = 0

/**
 * A key for a row of the form, stable while the row lives.
 *
 * @return {string} The key.
 */
function newKey() {
	keySeed += 1
	return `row-${keySeed}`
}

/**
 * A plain copy of an object (or an empty object).
 *
 * @param {object|undefined} value The object.
 * @return {object} The copy.
 */
function copy(value) {
	return value && typeof value === 'object'
		? JSON.parse(JSON.stringify(value))
		: {}
}

/**
 * ScheduledExportModal: create or edit one scheduled export, in four
 * sections: what (name, description, the dashboard or widget), when (a
 * daily, weekly, monthly or cron recurrence with its time zone), which
 * files (render targets with their format options) and who gets them
 * (recipients with their channel settings and filter context). Saving
 * POSTs a new export or PATCHes an existing one; a refusal shows the
 * server's reason.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
 */
export default {
	name: 'ScheduledExportModal',

	components: {
		Delete,
		ExportOptionFields,
		NcButton,
		NcCheckboxRadioSwitch,
		NcModal,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		NcTextField,
		Plus,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The export to edit; null creates a new one. */
		exportData: NULLABLE_OBJECT_PROP,

		/** What can be exported: `{type: 'dashboard'|'widget', id, label}`. */
		subjects: LIST_PROP,

		/** The subject a new export starts with: `{type, id}`. */
		defaultSubject: NULLABLE_OBJECT_PROP,
	},

	emits: ['close', 'saved'],

	/** @spec openspec/changes/scheduled-exports/tasks.md#task-12 */
	data() {
		return {
			form: this.emptyForm(),
			saving: false,
			error: '',
		}
	},

	computed: {
		/**
		 * @return {string} The dialog title.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		title() {
			return this.exportData?.id
				? t('launchpad', 'Edit scheduled export')
				: t('launchpad', 'New scheduled export')
		},

		/**
		 * The subjects as select options, plus the edited export's own
		 * subject when it is not among them.
		 *
		 * @return {object[]} `{id, type, label}` options.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		subjectOptions() {
			const options = this.subjects.map((subject) =>
				this.subjectOption(subject.type, subject.id, subject.label),
			)
			const current = this.form.subject
			if (current && !options.some((option) => option.key === current.key)) {
				options.push(current)
			}
			return options
		},

		/**
		 * @return {object[]} The recurrence types as options.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		recurrenceTypeOptions() {
			return [
				{ id: 'daily', label: t('launchpad', 'Every day') },
				{ id: 'weekly', label: t('launchpad', 'Every week') },
				{ id: 'monthly', label: t('launchpad', 'Every month') },
				{ id: 'cron', label: t('launchpad', 'Custom (cron expression)') },
			]
		},

		/**
		 * @return {object|null} The option of the chosen recurrence type.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		recurrenceTypeOption() {
			return (
				this.recurrenceTypeOptions.find(
					(option) => option.id === this.form.recurrence.type,
				) ?? null
			)
		},

		/**
		 * @return {object[]} The days of the week, Monday first, as `{code, label}`.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		weekdays() {
			const labels = [
				t('launchpad', 'Monday'),
				t('launchpad', 'Tuesday'),
				t('launchpad', 'Wednesday'),
				t('launchpad', 'Thursday'),
				t('launchpad', 'Friday'),
				t('launchpad', 'Saturday'),
				t('launchpad', 'Sunday'),
			]
			return DAY_CODES.map((code, i) => ({ code, label: labels[i] }))
		},

		/**
		 * @return {object[]} The render formats as options.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		formatOptions() {
			return EXPORT_FORMATS.map((id) => ({ id, label: formatLabel(id) }))
		},

		/**
		 * @return {object[]} The delivery channels as options.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		channelOptions() {
			return EXPORT_CHANNELS.map((id) => ({ id, label: channelLabel(id) }))
		},

		/**
		 * The recurrence in words, so the author sees what the editor means.
		 *
		 * @return {string} The summary.
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
		 */
		recurrenceSummary() {
			const rule = this.form.recurrence
			const time = rule.timeOfDay
			switch (rule.type) {
				case 'daily':
					return t('launchpad', 'Runs every day at {time}.', { time })
				case 'weekly': {
					const days = this.weekdays
						.filter((day) => rule.daysOfWeek.includes(day.code))
						.map((day) => day.label)
						.join(', ')
					return days
						? t('launchpad', 'Runs every {days} at {time}.', {
								days,
								time,
							})
						: t('launchpad', 'Choose at least one day.')
				}
				case 'monthly':
					return rule.lastDay
						? t(
								'launchpad',
								'Runs on the last day of every month at {time}.',
								{ time },
							)
						: t(
								'launchpad',
								'Runs on day {day} of every month at {time}.',
								{ day: rule.dayOfMonth, time },
							)
				default:
					return t(
						'launchpad',
						'Runs when the cron expression {expression} matches.',
						{ expression: rule.expression },
					)
			}
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Fill the form from the export each time the dialog opens.
			 *
			 * @param {boolean} isOpen New open state.
			 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
			 */
			handler(isOpen) {
				if (isOpen) {
					this.form = this.formFrom(this.exportData)
					this.error = ''
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * @return {string} A fresh row key.
		 */
		nextKey() {
			return newKey()
		},

		/**
		 * One subject option.
		 *
		 * @param {string} type dashboard or widget.
		 * @param {string} id The subject id.
		 * @param {string} label Its name.
		 * @return {object} The option.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		subjectOption(type, id, label) {
			const name = label || String(id)
			return {
				key: `${type}:${id}`,
				type,
				id: String(id),
				label:
					type === 'widget'
						? t('launchpad', 'Widget: {name}', { name })
						: t('launchpad', 'Dashboard: {name}', { name }),
			}
		},

		/**
		 * The form of a new export.
		 *
		 * @return {object} The form.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		emptyForm() {
			let subject = null
			if (this.defaultSubject?.id) {
				const known = this.subjects.find(
					(s) =>
						s.type === this.defaultSubject.type
						&& String(s.id) === String(this.defaultSubject.id),
				)
				subject = this.subjectOption(
					this.defaultSubject.type,
					this.defaultSubject.id,
					known?.label,
				)
			}
			return {
				name: '',
				description: '',
				enabled: true,
				subject,
				runTimezone:
					Intl.DateTimeFormat().resolvedOptions().timeZone
					|| 'Europe/Amsterdam',

				recurrence: {
					type: 'weekly',
					timeOfDay: '09:00',
					daysOfWeek: ['MO'],
					dayOfMonth: 1,
					lastDay: false,
					expression: '0 9 * * 1',
				},

				renderTargets: [this.newTarget('pdf')],
				recipients: [this.newRecipient('email')],
			}
		},

		/**
		 * The form of an existing export, or of a new one.
		 *
		 * @param {object|null} source The export.
		 * @return {object} The form.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		formFrom(source) {
			const form = this.emptyForm()
			if (!source) {
				return form
			}
			const rule = source.recurrence ?? {}
			const known = this.subjects.find(
				(s) =>
					s.type === source.subjectType
					&& String(s.id) === String(source.subjectId),
			)
			return {
				name: source.name ?? '',
				description: source.description ?? '',
				enabled: source.enabled !== false,
				subject: source.subjectId
					? this.subjectOption(
							source.subjectType ?? 'dashboard',
							source.subjectId,
							known?.label,
						)
					: null,

				runTimezone: source.runTimezone || form.runTimezone,
				recurrence: {
					...form.recurrence,
					type: rule.type ?? 'weekly',
					timeOfDay: rule.timeOfDay ?? form.recurrence.timeOfDay,
					daysOfWeek: Array.isArray(rule.daysOfWeek)
						? [...rule.daysOfWeek]
						: [],

					dayOfMonth: rule.dayOfMonth ?? 1,
					lastDay: rule.lastDay === true,
					expression: rule.expression ?? '',
				},

				renderTargets: (source.renderTargets ?? []).map((target) => ({
					key: newKey(),
					original: copy(target),
					format: target.format,
					formatOptions: {
						...FORMAT_DEFAULTS[target.format],
						...copy(target.formatOptions),
					},
					filenamePattern: target.filenamePattern ?? DEFAULT_FILENAME,
				})),

				recipients: (source.recipients ?? []).map((recipient) => {
					const config = copy(recipient.channelConfig)
					return {
						key: newKey(),
						original: copy(recipient),
						enabled: recipient.enabled !== false,
						channel: recipient.channel,
						channelConfig: config,
						addresses: Array.isArray(config.addresses)
							? config.addresses.join(', ')
							: (config.to ?? ''),
						filters: Object.entries(recipient.filterContext ?? {}).map(
							([field, value]) => ({
								key: newKey(),
								field,
								value:
									typeof value === 'string'
										? value
										: JSON.stringify(value),
							}),
						),
					}
				}),
			}
		},

		/**
		 * A new render target of a format.
		 *
		 * @param {string} format The format.
		 * @return {object} The target row.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		newTarget(format) {
			return {
				key: newKey(),
				original: {},
				format,
				formatOptions: { ...FORMAT_DEFAULTS[format] },
				filenamePattern: DEFAULT_FILENAME,
			}
		},

		/**
		 * A new recipient of a channel.
		 *
		 * @param {string} channel The channel.
		 * @return {object} The recipient row.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		newRecipient(channel) {
			return {
				key: newKey(),
				original: {},
				enabled: true,
				channel,
				channelConfig: { ...CHANNEL_DEFAULTS[channel] },
				addresses: '',
				filters: [],
			}
		},

		/**
		 * Add a render target (a PDF).
		 *
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		addTarget() {
			this.form.renderTargets.push(this.newTarget('pdf'))
		},

		/**
		 * Add a recipient (an email).
		 *
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		addRecipient() {
			this.form.recipients.push(this.newRecipient('email'))
		},

		/**
		 * Change a target's format; its options start over for the new format.
		 *
		 * @param {object} target The target row.
		 * @param {string} format The new format.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		setFormat(target, format) {
			if (!format || format === target.format) {
				return
			}
			target.format = format
			target.formatOptions = { ...FORMAT_DEFAULTS[format] }
		},

		/**
		 * Change a recipient's channel; its settings start over for the new channel.
		 *
		 * @param {object} recipient The recipient row.
		 * @param {string} channel The new channel.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		setChannel(recipient, channel) {
			if (!channel || channel === recipient.channel) {
				return
			}
			recipient.channel = channel
			recipient.channelConfig = { ...CHANNEL_DEFAULTS[channel] }
			recipient.addresses = ''
		},

		/**
		 * Switch a day of the week on or off for a weekly recurrence.
		 *
		 * @param {string} code MO to SU.
		 * @param {boolean} on Whether the day is chosen.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		toggleDay(code, on) {
			const days = this.form.recurrence.daysOfWeek.filter(
				(day) => day !== code,
			)
			if (on) {
				days.push(code)
			}
			this.form.recurrence.daysOfWeek = DAY_CODES.filter((day) =>
				days.includes(day),
			)
		},

		/**
		 * The option fields of a render format.
		 *
		 * @param {string} format The format.
		 * @return {object[]} The fields.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		formatFields(format) {
			switch (format) {
				case 'pdf':
					return [
						{
							key: 'pageSize',
							kind: 'select',
							label: t('launchpad', 'Page size'),
							options: ['A4', 'A3', 'Letter'].map((id) => ({
								id,
								label: id,
							})),
						},
						{
							key: 'orientation',
							kind: 'select',
							label: t('launchpad', 'Orientation'),
							options: [
								{
									id: 'portrait',
									label: t('launchpad', 'Portrait'),
								},
								{
									id: 'landscape',
									label: t('launchpad', 'Landscape'),
								},
							],
						},
						{
							key: 'header',
							kind: 'switch',
							label: t('launchpad', 'Header on every page'),
						},
						{
							key: 'footer',
							kind: 'switch',
							label: t('launchpad', 'Footer on every page'),
						},
					]
				case 'png':
					return [
						{
							key: 'width',
							type: 'number',
							label: t('launchpad', 'Width in pixels'),
						},
						{
							key: 'height',
							type: 'number',
							label: t('launchpad', 'Height in pixels'),
						},
						{
							key: 'dpi',
							type: 'number',
							label: t('launchpad', 'Resolution (DPI)'),
						},
					]
				case 'csv':
					return [
						{
							key: 'delimiter',
							kind: 'select',
							label: t('launchpad', 'Separator'),
							options: [
								{ id: ',', label: t('launchpad', 'Comma (,)') },
								{ id: ';', label: t('launchpad', 'Semicolon (;)') },
								{ id: '\t', label: t('launchpad', 'Tab') },
							],
						},
						{
							key: 'quoteChar',
							label: t('launchpad', 'Quote character'),
						},
					]
				case 'xlsx':
					return [
						{
							key: 'sheetPerWidget',
							kind: 'switch',
							label: t('launchpad', 'One sheet per widget'),
						},
					]
				default:
					return []
			}
		},

		/**
		 * The settings fields of a delivery channel (email has its own field).
		 *
		 * @param {string} channel The channel.
		 * @return {object[]} The fields.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		channelFields(channel) {
			const source = {
				key: 'openconnectorSourceId',
				label: t('launchpad', 'OpenConnector source (optional)'),
				help: t(
					'launchpad',
					'Send through this OpenConnector source, which holds the credentials.',
				),
			}
			switch (channel) {
				case 'nextcloudFiles':
					return [
						{ key: 'targetPath', label: t('launchpad', 'Folder') },
						{
							key: 'overwrite',
							kind: 'switch',
							label: t(
								'launchpad',
								'Replace a file with the same name',
							),
						},
					]
				case 'webhook':
					return [
						{
							key: 'url',
							type: 'url',
							label: t('launchpad', 'Web address (URL)'),
						},
						{
							key: 'signingSecret',
							kind: 'secret',
							label: t('launchpad', 'Signing secret'),
							help: t(
								'launchpad',
								'Signs every request so the receiver can check it came from LaunchPad.',
							),
						},
						source,
					]
				case 'sftp':
					return [
						{ key: 'host', label: t('launchpad', 'Server') },
						{
							key: 'port',
							type: 'number',
							label: t('launchpad', 'Port'),
						},
						{ key: 'username', label: t('launchpad', 'User name') },
						{
							key: 'password',
							kind: 'secret',
							label: t('launchpad', 'Password'),
						},
						{
							key: 'privateKey',
							kind: 'textarea',
							label: t(
								'launchpad',
								'Private key (instead of a password)',
							),
						},
						{
							key: 'remotePath',
							label: t('launchpad', 'Folder on the server'),
						},
						{
							key: 'hostKeySha256',
							label: t(
								'launchpad',
								'Server key fingerprint (SHA-256)',
							),
						},
						source,
					]
				default:
					return []
			}
		},

		/**
		 * The recurrence as the API stores it, with only the fields of its type.
		 *
		 * @return {object} The recurrence.
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-001-recurrence-editor-produces-deterministic-next-run-timestamps
		 */
		recurrencePayload() {
			const rule = this.form.recurrence
			switch (rule.type) {
				case 'cron':
					return { type: 'cron', expression: rule.expression.trim() }
				case 'weekly':
					return {
						type: 'weekly',
						daysOfWeek: [...rule.daysOfWeek],
						timeOfDay: rule.timeOfDay,
					}
				case 'monthly':
					return rule.lastDay
						? {
								type: 'monthly',
								lastDay: true,
								timeOfDay: rule.timeOfDay,
							}
						: {
								type: 'monthly',
								dayOfMonth: Number(rule.dayOfMonth),
								timeOfDay: rule.timeOfDay,
							}
				default:
					return { type: 'daily', timeOfDay: rule.timeOfDay }
			}
		},

		/**
		 * One recipient as the API stores it. An empty secret is left out; the
		 * redacted placeholder is sent back so the stored secret is kept.
		 *
		 * @param {object} recipient The recipient row.
		 * @return {object} The recipient.
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-004-per-recipient-filter-context
		 */
		recipientPayload(recipient) {
			const config = { ...recipient.channelConfig }
			if (recipient.channel === 'email') {
				delete config.to
				config.addresses = recipient.addresses
					.split(',')
					.map((a) => a.trim())
					.filter(Boolean)
			}
			for (const key of Object.keys(config)) {
				if (
					(SECRET_KEYS.includes(key) || key === 'openconnectorSourceId')
					&& (config[key] === '' || config[key] === null)
				) {
					delete config[key]
				}
			}
			const result = {
				...recipient.original,
				enabled: recipient.enabled,
				channel: recipient.channel,
				channelConfig: config,
			}
			delete result.filterContext
			const filters = recipient.filters.filter(
				(filter) => filter.field.trim() !== '',
			)
			if (filters.length > 0) {
				result.filterContext = Object.fromEntries(
					filters.map((filter) => [
						filter.field.trim(),
						this.filterValue(filter.value),
					]),
				)
			}
			return result
		},

		/**
		 * A typed filter value: a JSON list or object stays one, anything else is text.
		 *
		 * @param {string} value What was typed.
		 * @return {string|object|Array} The value.
		 */
		filterValue(value) {
			const text = String(value ?? '').trim()
			if (/^[[{]/.test(text)) {
				try {
					return JSON.parse(text)
				} catch {
					return text
				}
			}
			return text
		},

		/**
		 * The export as the API takes it.
		 *
		 * @return {object} The export.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		payload() {
			const form = this.form
			return {
				name: form.name.trim(),
				description: form.description,
				enabled: form.enabled,
				subjectType: form.subject?.type ?? 'dashboard',
				subjectId: form.subject?.id ?? '',
				runTimezone: form.runTimezone.trim(),
				recurrence: this.recurrencePayload(),
				renderTargets: form.renderTargets.map((target) => ({
					...target.original,
					format: target.format,
					formatOptions: { ...target.formatOptions },
					filenamePattern: target.filenamePattern || DEFAULT_FILENAME,
				})),

				recipients: form.recipients.map((recipient) =>
					this.recipientPayload(recipient),
				),
			}
		},

		/**
		 * Save: POST a new export or PATCH this one, then tell the list.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		async save() {
			if (this.saving) {
				return
			}
			this.saving = true
			this.error = ''
			try {
				const body = this.payload()
				if (this.exportData?.id) {
					body.id = this.exportData.id
				}
				const saved = await useExportsStore().saveExport(body)
				this.$emit('saved', saved)
				this.$emit('close')
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The scheduled export could not be saved.'),
				)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.export-editor {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.export-editor__section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.export-editor__section h3 {
	margin: 0;
	font-size: 1.1em;
	font-weight: bold;
}

.export-editor__item {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.export-editor__item-head,
.export-editor__filter {
	display: flex;
	gap: 8px;
	align-items: flex-end;
}

.export-editor__item-head > :first-child,
.export-editor__filter > :not(:last-child) {
	flex: 1 1 0;
}

.export-editor__days,
.export-editor__filters {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 16px;
	border: 0;
	padding: 0;
	margin: 0;
}

.export-editor__filters {
	flex-direction: column;
}

.export-editor__days legend,
.export-editor__filters legend {
	font-weight: bold;
	margin-bottom: 4px;
}

.export-editor__hint,
.export-editor__summary {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.export-editor__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
