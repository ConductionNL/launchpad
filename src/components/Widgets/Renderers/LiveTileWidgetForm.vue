<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="live-tile-widget-form">
		<!-- REQ-LIVETILE-020 (board LpWidgetInstellen): where the value comes
		     from as two choice cards. -->
		<fieldset class="live-tile-widget-form__choices">
			<legend class="live-tile-widget-form__legend">
				{{ t('launchpad', 'Where does the value come from?') }}
			</legend>
			<label
				v-for="card in sourceCards"
				:key="card.value"
				class="live-tile-widget-form__choice"
				:class="{
					'live-tile-widget-form__choice--selected': sourceMode === card.value,
					'live-tile-widget-form__choice--disabled': card.disabled,
				}">
				<input
					type="radio"
					name="live-tile-source"
					class="live-tile-widget-form__choice-input"
					:value="card.value"
					:checked="sourceMode === card.value"
					:disabled="card.disabled"
					:data-testid="'live-tile-source-' + card.value"
					@change="onSourceModeChange(card.value)">
				<span class="live-tile-widget-form__choice-title">{{ card.title }}</span>
				<span class="live-tile-widget-form__choice-text">{{ card.text }}</span>
			</label>
		</fieldset>

		<!-- @nextcloud/vue@9 renamed the two-way prop of every form control
		     from `value`/`checked` to `modelValue`, and the paired event from
		     `update:value`/`update:checked` to `update:modelValue`. The old
		     names fail silently. The listener must stay camelCase:
		     `useModel()` decides controlled-vs-local by looking for a literal
		     `onUpdate:modelValue` in the vnode props. -->
		<template v-if="sourceMode === 'connector'">
			<NcSelect
				v-if="connections.length > 0"
				:modelValue="sourceId"
				:options="connections"
				:inputLabel="t('launchpad', 'Connection')"
				:reduce="(option) => option.value"
				label="label"
				@update:modelValue="updateField('sourceId', $event || '')" />
			<NcTextField
				v-else
				:modelValue="sourceId"
				:label="t('launchpad', 'Integriq source id')"
				:disabled="!connectorAvailable"
				@update:modelValue="updateField('sourceId', $event)" />
			<p
				v-if="selectedConnection"
				class="live-tile-widget-form__hint-small"
				data-testid="live-tile-last-success">
				{{ t('launchpad', 'Last success: {time}', { time: lastSuccessText }) }}
			</p>
		</template>

		<template v-else>
			<p v-if="!connectorAvailable" class="live-tile-widget-form__warning">
				{{
					t(
						'launchpad',
						'Integriq is not installed — direct-URL mode only.',
					)
				}}
			</p>
			<NcTextField
				:modelValue="url"
				:label="t('launchpad', 'URL')"
				:placeholder="t('launchpad', 'https://…')"
				@update:modelValue="onUrlChange"
				@blur="checkUrlAllowed" />
			<p v-if="urlAllowListError" class="live-tile-widget-form__warning">
				{{ urlAllowListError }}
			</p>
		</template>

		<NcTextField
			:modelValue="valueExpr"
			:label="t('launchpad', 'Path in the response')"
			placeholder="$.data.count"
			@update:modelValue="updateField('valueExpr', $event)" />

		<!-- REQ-LTC-001: a single value, or several mapped fields as a list,
		     a table or key-value pairs. -->
		<fieldset class="live-tile-widget-form__views">
			<legend class="live-tile-widget-form__legend">
				{{ t('launchpad', 'View') }}
			</legend>
			<label
				v-for="option in displayOptions"
				:key="option.value"
				class="live-tile-widget-form__view"
				:class="{ 'live-tile-widget-form__view--selected': display === option.value }">
				<input
					type="radio"
					name="live-tile-view"
					:value="option.value"
					:checked="display === option.value"
					:data-testid="'live-tile-view-' + option.value"
					@change="onDisplayChange(option.value)">
				{{ option.label }}
			</label>
		</fieldset>

		<template v-if="display !== 'value'">
			<NcSelect
				:modelValue="null"
				:options="presetOptions"
				:inputLabel="t('launchpad', 'Preset')"
				:placeholder="t('launchpad', 'Fill the mapping from a preset')"
				label="label"
				@update:modelValue="applyPreset" />

			<NcTextField
				v-if="display !== 'keyvalue'"
				:modelValue="String(limit)"
				type="number"
				:label="t('launchpad', 'Number of rows (1 to 50)')"
				@update:modelValue="onLimitChange" />

			<fieldset class="live-tile-widget-form__mapping">
				<legend>{{ t('launchpad', 'Fields') }}</legend>
				<div
					v-for="(field, index) in mapping"
					:key="index"
					class="live-tile-widget-form__mapping-row">
					<NcTextField
						:modelValue="field.label"
						:label="t('launchpad', 'Field label')"
						@update:modelValue="updateMapping(index, 'label', $event)" />
					<NcTextField
						:modelValue="field.path"
						:label="t('launchpad', 'Field path')"
						placeholder="name"
						@update:modelValue="updateMapping(index, 'path', $event)" />
					<NcSelect
						:modelValue="field.role"
						:options="roleOptions"
						:inputLabel="t('launchpad', 'Field role')"
						:reduce="(option) => option.value"
						label="label"
						@update:modelValue="updateMapping(index, 'role', $event || 'field')" />
					<NcButton
						variant="tertiary"
						:aria-label="t('launchpad', 'Remove field')"
						@click="removeMapping(index)">
						{{ t('launchpad', 'Remove') }}
					</NcButton>
				</div>
				<NcButton
					:disabled="mapping.length >= MAX_FIELDS"
					@click="addMapping">
					{{ t('launchpad', 'Add field') }}
				</NcButton>
			</fieldset>
		</template>

		<div class="live-tile-widget-form__row">
			<NcTextField
				:modelValue="unit"
				:label="t('launchpad', 'Unit (optional)')"
				@update:modelValue="onUnitChange" />
			<NcSelect
				:modelValue="Number(refresh)"
				:options="refreshOptions"
				:inputLabel="t('launchpad', 'Refresh')"
				:reduce="(option) => option.value"
				:clearable="false"
				label="label"
				@update:modelValue="onRefreshChange" />
		</div>

		<fieldset class="live-tile-widget-form__formatting">
			<legend class="live-tile-widget-form__legend">
				{{ t('launchpad', 'Formatting') }}
			</legend>
			<NcTextField
				:modelValue="label"
				:label="t('launchpad', 'Title')"
				:placeholder="t('launchpad', 'e.g. Open tickets')"
				@update:modelValue="updateField('label', $event)" />
			<NcCheckboxRadioSwitch
				:modelValue="showTitle"
				type="switch"
				data-testid="live-tile-show-title"
				@update:modelValue="updateField('showTitle', $event)">
				{{ t('launchpad', 'Show title') }}
			</NcCheckboxRadioSwitch>
			<NcSelect
				:modelValue="colour"
				:options="colourOptions"
				:inputLabel="t('launchpad', 'Color')"
				:reduce="(option) => option.value"
				:clearable="false"
				label="label"
				@update:modelValue="updateField('colour', $event || '')" />
			<NcCheckboxRadioSwitch
				:modelValue="border"
				type="switch"
				data-testid="live-tile-border"
				@update:modelValue="updateField('border', $event)">
				{{ t('launchpad', 'Border') }}
			</NcCheckboxRadioSwitch>
			<div class="live-tile-widget-form__row">
				<NcTextField
					:modelValue="formatPrefix"
					:label="t('launchpad', 'Prefix')"
					@update:modelValue="updateFormat('prefix', $event)" />
			</div>
			<NcCheckboxRadioSwitch
				:modelValue="formatThousands"
				type="switch"
				data-testid="live-tile-thousands"
				@update:modelValue="updateFormat('thousands', $event)">
				{{ t('launchpad', 'Thousands separator') }}
			</NcCheckboxRadioSwitch>

			<NcTextField
				:modelValue="linkUrl"
				:label="t('launchpad', 'Click-through link (optional)')"
				@update:modelValue="updateField('linkUrl', $event)" />
			<NcSelect
				:modelValue="linkTarget"
				:options="linkTargetOptions"
				:inputLabel="t('launchpad', 'Link target')"
				:reduce="(option) => option.value"
				label="label"
				@update:modelValue="
					(val) => updateField('linkTarget', val || 'same-tab')
				" />
		</fieldset>

		<!-- The preview follows the fields (REQ-LIVETILE-020). -->
		<section
			class="live-tile-widget-form__preview"
			:class="previewClasses"
			:aria-label="t('launchpad', 'Preview')"
			data-testid="live-tile-preview">
			<div v-if="showTitle && label" class="live-tile-widget-form__preview-title">
				{{ label }}
			</div>
			<div class="live-tile-widget-form__preview-value">
				{{ previewValue }}
			</div>
			<div class="live-tile-widget-form__hint-small">
				{{ refreshText }}
			</div>
		</section>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextField } from '@nextcloud/vue'
import {
	NULLABLE_OBJECT_PROP,
	objectProp,
} from '../../../constants/propDefinitions.js'
import {
	fetchConnectorAvailability,
	fetchLiveTileValue,
	validateLiveTileSource,
} from '../../../services/liveTileClient.js'
import { fetchLiveTileConnections } from '../../../services/liveTileConnections.js'
import {
	knownColour,
	previewValue,
	refreshLabel,
	refreshOptions,
	suffixToUnit,
	unitToSuffix,
} from '../../../utils/liveTilePreview.js'
import { allowListMessage, urlFieldErrors } from '../../../utils/urlAllowList.js'

const MIN_REFRESH_SECONDS = 30
const DEFAULT_REFRESH_SECONDS = 300

const DEFAULT_CONTENT = Object.freeze({
	label: '',
	sourceMode: 'url',
	url: '',
	sourceId: '',
	valueExpr: '',
	refresh: DEFAULT_REFRESH_SECONDS,
	format: { prefix: '', suffix: '', thousands: false },
	badge: { thresholds: [] },
	linkUrl: '',
	linkTarget: 'same-tab',
})

const MAX_FIELDS = 6
const DEFAULT_LIMIT = 10

/**
 * The "Latest documents" presets (REQ-LTC-004): where each system keeps its
 * documents and which fields give the title, link, last modified and editor.
 *
 * @type {Record<string, {valueExpr: string, paths: string[]}>}
 */
const DOCUMENT_PRESETS = Object.freeze({
	sharepoint: { valueExpr: '$.value', paths: ['name', 'webUrl', 'lastModifiedDateTime', 'lastModifiedBy.user.displayName'] },
	confluence: { valueExpr: '$.results', paths: ['title', '_links.webui', 'version.when', 'version.by.displayName'] },
})

/**
 * The stored mapping as editable rows.
 *
 * @param {Array<object>|undefined} stored The stored mapping.
 * @return {Array<{label: string, path: string, role: string}>} the rows.
 */
function mappingRows(stored) {
	if (!Array.isArray(stored)) {
		return []
	}
	return stored.slice(0, MAX_FIELDS).map((field) => ({
		label: typeof field?.label === 'string' ? field.label : '',
		path: typeof field?.path === 'string' ? field.path : '',
		role: ['title', 'link', 'date', 'field'].includes(field?.role) ? field.role : 'field',
	}))
}

const SOURCE_FIELDS = Object.freeze(['label', 'sourceMode', 'url', 'sourceId', 'valueExpr', 'refresh'])
const LINK_FIELDS = Object.freeze(['linkUrl', 'linkTarget'])

/**
 * The stored value of each named field, or its default when unset or null.
 *
 * @param {object} initial The stored content.
 * @param {Array<string>} fields The field names.
 * @return {object} the fields to edit.
 */
function storedOrDefault(initial, fields) {
	return Object.fromEntries(
		fields.map((field) => [field, initial[field] ?? DEFAULT_CONTENT[field]]),
	)
}

/**
 * The number-format fields of the form, read over the default format.
 *
 * @param {object|null|undefined} stored The stored format.
 * @return {object} formatPrefix, formatSuffix and formatThousands.
 */
function formatFields(stored) {
	const format = { ...DEFAULT_CONTENT.format, ...(stored || {}) }
	return {
		formatPrefix: format.prefix,
		formatSuffix: format.suffix,
		formatThousands: !!format.thousands,
	}
}

/**
 * LiveTileWidgetForm — `CnAddWidgetModal` sub-form for creating or editing a
 * `livetile` placement (REQ-LIVETILE-002/004/005).
 *
 * Offers two source modes: an OpenConnector-backed source picker (only when
 * the `connector-status` capability probe reports OpenConnector present —
 * REQ-LIVETILE-005 "hidden or disabled" when absent) or a direct allow-
 * listed URL. Host allow-list enforcement is authoritative server-side
 * (fail-closed); this form performs a best-effort async check
 * (`validateLiveTileSource`) on blur so authors get fast feedback, surfaced
 * through the synchronous `validate()` the modal's submit gate calls.
 */
export default {
	name: 'LiveTileWidgetForm',

	components: {
		NcButton,
		NcTextField,
		NcSelect,
		NcCheckboxRadioSwitch,
	},

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 *
		 * @type {{content: object}|null}
		 */
		editingWidget: NULLABLE_OBJECT_PROP,

		/**
		 * Initial content values — used when not editing.
		 *
		 * @type {object}
		 */
		value: objectProp(DEFAULT_CONTENT),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			...storedOrDefault(initial, SOURCE_FIELDS),
			...formatFields(initial.format),
			badge: { ...DEFAULT_CONTENT.badge, ...(initial.badge || {}) },
			...storedOrDefault(initial, LINK_FIELDS),
			showTitle: initial.showTitle !== false,
			colour: knownColour(initial.colour),
			border: initial.border === true,
			connections: [],
			reading: null,
			display: ['list', 'table', 'keyvalue'].includes(initial.display) ? initial.display : 'value',
			limit: Number.isInteger(Number(initial.limit)) && Number(initial.limit) > 0 ? Number(initial.limit) : DEFAULT_LIMIT,
			mapping: mappingRows(initial.mapping),
			MAX_FIELDS,
			connectorAvailable: false,
			urlAllowListError: '',
			MIN_REFRESH_SECONDS,
		}
	},

	computed: {
		/**
		 * The two choice cards for where the value comes from. Connection is
		 * disabled without integriq.
		 *
		 * @return {Array<{value: string, title: string, text: string, disabled: boolean}>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		sourceCards() {
			return [
				{
					value: 'connector',
					title: t('launchpad', 'Connection'),
					text: t('launchpad', 'A system the administrator has connected'),
					disabled: !this.connectorAvailable,
				},
				{
					value: 'url',
					title: t('launchpad', 'Web address'),
					text: t('launchpad', 'An address that returns JSON'),
					disabled: false,
				},
			]
		},

		/**
		 * The chosen connection, when it is one of the listed ones.
		 *
		 * @return {{value: string, label: string, lastSuccessAt: (string|null)}|null}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		selectedConnection() {
			return this.connections.find((option) => option.value === this.sourceId) || null
		},

		/**
		 * When the chosen connection last succeeded, in the reader's locale.
		 *
		 * @return {string}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		lastSuccessText() {
			const at = this.selectedConnection?.lastSuccessAt
			const date = at ? new Date(at) : null
			if (!date || Number.isNaN(date.getTime())) {
				return t('launchpad', 'Not yet')
			}
			return date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
		},

		/**
		 * The unit field: the stored suffix without its leading space.
		 *
		 * @return {string}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		unit() {
			return suffixToUnit(this.formatSuffix)
		},

		/**
		 * The refresh choices, keeping a stored interval that is not one of them.
		 *
		 * @return {Array<{value: number, label: string}>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		refreshOptions() {
			return refreshOptions(this.clampRefresh(this.refresh))
		},

		/**
		 * The theme colours a tile can take.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		colourOptions() {
			return [
				{ value: '', label: t('launchpad', 'Default') },
				{ value: 'primary', label: t('launchpad', 'Primary') },
				{ value: 'success', label: t('launchpad', 'Success') },
				{ value: 'warning', label: t('launchpad', 'Warning') },
				{ value: 'error', label: t('launchpad', 'Error') },
			]
		},

		/**
		 * The preview's colour and border classes, as the tile renders them.
		 *
		 * @return {object}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		previewClasses() {
			return {
				['live-tile-colour--' + this.colour]: this.colour !== '',
				'live-tile-widget-form__preview--bordered': this.border,
			}
		},

		/**
		 * The value in the preview: the tile's last reading when it has one.
		 *
		 * @return {string}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		previewValue() {
			return previewValue(this.reading, {
				prefix: this.formatPrefix,
				suffix: this.formatSuffix,
				thousands: this.formatThousands,
			})
		},

		/**
		 * "Refreshes every 5 minutes" under the preview.
		 *
		 * @return {string}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		refreshText() {
			return refreshLabel(this.clampRefresh(this.refresh), true)
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		sourceModeOptions() {
			const options = [{ value: 'url', label: t('launchpad', 'Direct URL') }]
			// REQ-LIVETILE-005: only offer `connector` mode when the
			// capability probe confirms OpenConnector is present.
			if (this.connectorAvailable) {
				options.push({
					value: 'connector',
					label: t('launchpad', 'Integriq source'),
				})
			}
			return options
		},

		/**
		 * The displays a tile can have.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		displayOptions() {
			return [
				{ value: 'value', label: t('launchpad', 'Single value') },
				{ value: 'list', label: t('launchpad', 'List') },
				{ value: 'table', label: t('launchpad', 'Table') },
				{ value: 'keyvalue', label: t('launchpad', 'Key-value pairs') },
			]
		},

		/**
		 * What a mapped field is for.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		roleOptions() {
			return [
				{ value: 'title', label: t('launchpad', 'Title') },
				{ value: 'link', label: t('launchpad', 'Link') },
				{ value: 'date', label: t('launchpad', 'Date') },
				{ value: 'field', label: t('launchpad', 'Other field') },
			]
		},

		/**
		 * The mapping presets.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-preset-shows-another-intranets-latest-documents-req-ltc-004
		 */
		presetOptions() {
			return [
				{ value: 'sharepoint', label: t('launchpad', 'Latest documents (SharePoint)') },
				{ value: 'confluence', label: t('launchpad', 'Latest documents (Confluence)') },
			]
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		linkTargetOptions() {
			return [
				{ value: 'same-tab', label: t('launchpad', 'Same tab') },
				{ value: 'new-tab', label: t('launchpad', 'New tab') },
			]
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		assembledContent() {
			return {
				label: this.label,
				sourceMode: this.sourceMode,
				url: this.url,
				sourceId: this.sourceId,
				valueExpr: this.valueExpr,
				refresh: this.clampRefresh(this.refresh),
				format: {
					prefix: this.formatPrefix,
					suffix: this.formatSuffix,
					thousands: this.formatThousands,
				},

				badge: this.badge,
				linkUrl: this.linkUrl,
				linkTarget: this.linkTarget,
				...this.styleContent,
				...this.displayContent,
			}
		},

		/**
		 * Title visibility, colour and border; only what differs from the
		 * default, so a tile saved before these existed is stored as it was.
		 *
		 * @return {object}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		styleContent() {
			const content = {}
			if (this.showTitle === false) {
				content.showTitle = false
			}
			if (this.colour !== '') {
				content.colour = this.colour
			}
			if (this.border === true) {
				content.border = true
			}
			return content
		},

		/**
		 * The display, limit and mapping of the content; nothing for a single-value tile,
		 * so a tile saved before lists existed is stored as it was.
		 *
		 * @return {object}
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		displayContent() {
			if (this.display === 'value') {
				return {}
			}
			const content = {
				display: this.display,
				mapping: this.mapping.map((field) => ({ ...field })),
			}
			if (this.display !== 'keyvalue') {
				content.limit = Number(this.limit)
			}
			return content
		},
	},

	created() {
		this.refreshConnectorAvailability()
		this.loadConnections()
		this.loadReading()
	},

	methods: {
		t,

		/**
		 * Clamp a refresh interval: values `<= 0` default to
		 * {@link DEFAULT_REFRESH_SECONDS}; any positive value below
		 * {@link MIN_REFRESH_SECONDS} is raised to that minimum
		 * (REQ-LIVETILE-002 "Refresh interval bounds").
		 *
		 * @param {number|string} raw the raw entered value.
		 * @return {number} the clamped refresh interval in seconds.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		clampRefresh(raw) {
			const num = Number(raw)
			if (!Number.isFinite(num) || num <= 0) {
				return DEFAULT_REFRESH_SECONDS
			}
			return Math.max(num, MIN_REFRESH_SECONDS)
		},

		/**
		 * Probe OpenConnector's `dashboard-http-datasource` capability. When
		 * absent (or already selected as `connector` with no availability),
		 * falls back to `url` mode so the form never offers a dead option
		 * (REQ-LIVETILE-005).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		async refreshConnectorAvailability() {
			this.connectorAvailable = await fetchConnectorAvailability()
			if (
				this.connectorAvailable === false
				&& this.sourceMode === 'connector'
			) {
				this.sourceMode = 'url'
				this.emitUpdate()
			}
		},

		/**
		 * Load the connections the editor can choose from.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		async loadConnections() {
			try {
				const list = await fetchLiveTileConnections()
				this.connections = Array.isArray(list) ? list : []
			} catch {
				this.connections = []
			}
		},

		/**
		 * Load the tile's current value for the preview when an existing tile
		 * is edited; a new tile previews a dash.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		async loadReading() {
			const id = this.editingWidget?.id
			if (id === undefined || id === null || id === '') {
				return
			}
			try {
				this.reading = await fetchLiveTileValue(id)
			} catch {
				this.reading = null
			}
		},

		/**
		 * Set the unit; stored as the number format's suffix.
		 *
		 * @param {string} val The unit as typed.
		 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
		 */
		onUnitChange(val) {
			this.formatSuffix = unitToSuffix(val)
			this.emitUpdate()
		},

		/**
		 * Switch where the tile reads its value from.
		 *
		 * @param {string} val Source mode; falsy falls back to `url`.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onSourceModeChange(val) {
			this.sourceMode = val || 'url'
			this.emitUpdate()
		},

		/**
		 * Set the data-source URL, clearing any stale allow-list error.
		 *
		 * @param {string} val The new URL.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onUrlChange(val) {
			this.url = val
			this.urlAllowListError = ''
			this.emitUpdate()
		},

		/**
		 * Set how often the tile re-polls its source.
		 *
		 * @param {number} val Refresh interval in seconds.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		onRefreshChange(val) {
			this.refresh = val
			this.emitUpdate()
		},

		/**
		 * Set one top-level form field.
		 *
		 * @param {string} field Name of the data property to write.
		 * @param {string|number|boolean|object} val New value for that field.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		updateField(field, val) {
			this[field] = val
			this.emitUpdate()
		},

		/**
		 * Set one of the number-formatting options.
		 *
		 * @param {string} field Which option to write — `prefix`, `suffix`
		 *   or `thousands`.
		 * @param {string|number|boolean} val New value for that option.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		updateFormat(field, val) {
			if (field === 'prefix') {
				this.formatPrefix = val
			} else if (field === 'suffix') {
				this.formatSuffix = val
			} else if (field === 'thousands') {
				this.formatThousands = val
			}
			this.emitUpdate()
		},

		/**
		 * Choose how the tile shows its data. A list, table or key-value tile
		 * starts with one title field.
		 *
		 * @param {string} val The display; falsy is a single value.
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		onDisplayChange(val) {
			this.display = val || 'value'
			if (this.display !== 'value' && this.mapping.length === 0) {
				this.mapping = [{ label: '', path: '', role: 'title' }]
			}
			this.emitUpdate()
		},

		/**
		 * Set how many rows a list or table shows.
		 *
		 * @param {string|number} val The limit.
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		onLimitChange(val) {
			this.limit = val
			this.emitUpdate()
		},

		/**
		 * Change one key of one mapped field.
		 *
		 * @param {number} index The row.
		 * @param {string} key label, path or role.
		 * @param {string} val The new value.
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		updateMapping(index, key, val) {
			this.mapping = this.mapping.map((field, at) => (at === index ? { ...field, [key]: val } : field))
			this.emitUpdate()
		},

		/**
		 * Add a mapped field, up to six.
		 *
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		addMapping() {
			if (this.mapping.length >= MAX_FIELDS) {
				return
			}
			this.mapping = [...this.mapping, { label: '', path: '', role: 'field' }]
			this.emitUpdate()
		},

		/**
		 * Remove a mapped field.
		 *
		 * @param {number} index The row.
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		removeMapping(index) {
			this.mapping = this.mapping.filter((_, at) => at !== index)
			this.emitUpdate()
		},

		/**
		 * Fill a list of the latest documents from a preset, for the author to confirm.
		 * The source stays the author's choice.
		 *
		 * @param {{value: string}|string|null} option The chosen preset.
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-preset-shows-another-intranets-latest-documents-req-ltc-004
		 */
		applyPreset(option) {
			const preset = DOCUMENT_PRESETS[option?.value ?? option]
			if (!preset) {
				return
			}
			const [title, link, modified, editor] = preset.paths
			this.display = 'list'
			this.limit = DEFAULT_LIMIT
			this.valueExpr = preset.valueExpr
			this.mapping = [
				{ label: t('launchpad', 'Document'), path: title, role: 'title' },
				{ label: t('launchpad', 'Open'), path: link, role: 'link' },
				{ label: t('launchpad', 'Last modified'), path: modified, role: 'date' },
				{ label: t('launchpad', 'Modified by'), path: editor, role: 'field' },
			]
			this.emitUpdate()
		},

		/** @spec openspec/specs/live-data-tile-widget/spec.md */
		emitUpdate() {
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * Best-effort async save-time check (REQ-LIVETILE-002 "rejected at
		 * save time") — the modal's submit gate calls `validate()`
		 * synchronously, so this only pre-populates `urlAllowListError` for
		 * the NEXT reactivity tick; the authoritative fail-closed
		 * enforcement always happens server-side at fetch time regardless.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		async checkUrlAllowed() {
			if (this.sourceMode !== 'url' || this.url.trim() === '') {
				this.urlAllowListError = ''
				return
			}
			const result = await validateLiveTileSource(this.assembledContent)
			this.urlAllowListError = allowListMessage(result)
		},

		/**
		 * Validate the form. `url` mode requires a syntactically valid URL;
		 * `connector` mode requires a source id and OpenConnector actually
		 * being available. Any async allow-list error already surfaced via
		 * `checkUrlAllowed()` also blocks submission.
		 *
		 * @return {string[]} the validation errors.
		 * @spec openspec/specs/live-data-tile-widget/spec.md
		 */
		validate() {
			const errors = this.sourceErrors()

			if (typeof this.valueExpr !== 'string' || this.valueExpr.trim() === '') {
				errors.push(t('launchpad', 'Value expression is required'))
			}

			return errors.concat(this.displayErrors())
		},

		/**
		 * The errors of a list, table or key-value display: at least one field,
		 * every field with a path, and a limit from 1 to 50.
		 *
		 * @return {string[]} the display errors.
		 * @spec openspec/changes/archive/2026-11-19-integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
		 */
		displayErrors() {
			if (!['list', 'table', 'keyvalue'].includes(this.display)) {
				return []
			}
			const errors = []
			const mapping = Array.isArray(this.mapping) ? this.mapping : []
			if (mapping.length === 0) {
				errors.push(t('launchpad', 'Add at least one field'))
			} else if (mapping.some((field) => typeof field.path !== 'string' || field.path.trim() === '')) {
				errors.push(t('launchpad', 'Every field needs a path'))
			}
			const limit = Number(this.limit)
			if (this.display !== 'keyvalue' && (!Number.isInteger(limit) || limit < 1 || limit > 50)) {
				errors.push(t('launchpad', 'The number of rows is from 1 to 50'))
			}
			return errors
		},

		/**
		 * The errors of the chosen source: the URL checks in `url` mode, the
		 * connector checks in `connector` mode.
		 *
		 * @return {string[]} the source errors.
		 */
		sourceErrors() {
			if (this.sourceMode === 'url') {
				return [...urlFieldErrors(this.url, this.urlAllowListError)]
			}
			if (this.sourceMode === 'connector') {
				return this.connectorErrors()
			}
			return []
		},

		/**
		 * The errors of `connector` mode: OpenConnector must be available and
		 * a source id chosen.
		 *
		 * @return {string[]} the connector errors.
		 */
		connectorErrors() {
			const errors = []
			if (!this.connectorAvailable) {
				errors.push(t('launchpad', 'Integriq is not available.'))
			}
			if (
				typeof this.sourceId !== 'string'
				|| this.sourceId.trim() === ''
			) {
				errors.push(t('launchpad', 'Source id is required'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.live-tile-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.live-tile-widget-form__hint {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.live-tile-widget-form__hint-small {
	margin: -8px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.live-tile-widget-form__warning {
	margin: 0;
	font-size: 13px;
	color: var(--color-warning-text, var(--color-text-maxcontrast));
}

.live-tile-widget-form__mapping {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 8px;
}

.live-tile-widget-form__mapping-row {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr auto;
	gap: 8px;
	align-items: end;
}

.live-tile-widget-form__row {
	display: flex;
	gap: 12px;
}

.live-tile-widget-form__row > * {
	flex: 1;
}

.live-tile-widget-form__choices,
.live-tile-widget-form__views,
.live-tile-widget-form__formatting {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	border: 0;
	margin: 0;
	padding: 0;
}

.live-tile-widget-form__formatting {
	flex-direction: column;
	flex-wrap: nowrap;
	gap: 12px;
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}

.live-tile-widget-form__legend {
	width: 100%;
	margin-bottom: 4px;
	font-weight: 600;
}

.live-tile-widget-form__choice {
	position: relative;
	display: flex;
	flex: 1 1 200px;
	flex-direction: column;
	gap: 2px;
	padding: 10px 12px 10px 36px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large);
	cursor: pointer;
}

.live-tile-widget-form__choice--selected {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.live-tile-widget-form__choice--disabled {
	cursor: not-allowed;
	opacity: 0.6;
}

.live-tile-widget-form__choice-input {
	position: absolute;
	top: 12px;
	left: 12px;
	margin: 0;
}

.live-tile-widget-form__choice-title {
	font-weight: 600;
}

.live-tile-widget-form__choice-text {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.live-tile-widget-form__view {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	padding: 4px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	cursor: pointer;
}

.live-tile-widget-form__view--selected {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.live-tile-widget-form__preview {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.live-tile-widget-form__preview--bordered {
	border: 1px solid var(--color-border-dark);
}

.live-tile-widget-form__preview-title {
	font-weight: 600;
}

.live-tile-widget-form__preview-value {
	font-size: 28px;
	font-weight: 700;
	color: var(--live-tile-accent, var(--color-main-text));
}

.live-tile-colour--primary {
	--live-tile-accent: var(--color-primary-element);
}

.live-tile-colour--success {
	--live-tile-accent: var(--color-success-text, var(--color-success));
}

.live-tile-colour--warning {
	--live-tile-accent: var(--color-warning-text, var(--color-warning));
}

.live-tile-colour--error {
	--live-tile-accent: var(--color-error-text, var(--color-error));
}
</style>
