<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<!--
	ConditionalVisibilityEditor — the rule builder embedded in a widget
	placement's "Visibility" settings section
	(conditional-visibility-editor spec, REQ-CVUI-001..004).

	Lists a placement's conditional-visibility rules as editable
	`VisibilityRuleRow`s, grouped under legible "Show when…" (include/OR)
	and "Hide when…" (exclude/AND) headings, and hosts the "preview as
	audience/date" affordance. Reads and writes rules ONLY through the
	existing `/api/widgets/{placementId}/rules` and `/api/rules/{ruleId}`
	endpoints — no new persistence path (REQ-CVUI-001).
-->

<template>
	<div
		class="conditional-visibility-editor"
		data-test="conditional-visibility-editor">
		<div v-if="loading" class="conditional-visibility-editor__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else>
			<!-- REQ-CVUI-006: all or one rule must match, as two choice cards
			     with radio-group semantics (arrow keys move the choice). -->
			<div
				class="conditional-visibility-editor__modes"
				role="radiogroup"
				:aria-label="t('launchpad', 'Who sees this widget?')"
				data-test="rule-match">
				<button
					v-for="mode in matchModes"
					:key="mode.id"
					type="button"
					role="radio"
					class="conditional-visibility-editor__mode"
					:class="{ 'conditional-visibility-editor__mode--active': matchMode === mode.id }"
					:aria-checked="matchMode === mode.id ? 'true' : 'false'"
					:tabindex="matchMode === mode.id ? 0 : -1"
					:disabled="savingMatch"
					:data-test="'rule-match-' + mode.id"
					@click="setMatchMode(mode.id)"
					@keydown="onModeKeydown">
					<span class="conditional-visibility-editor__mode-dot" aria-hidden="true" />
					{{ mode.label }}
				</button>
			</div>

			<p
				v-if="isEmpty"
				class="conditional-visibility-editor__empty"
				data-test="visibility-empty-state">
				{{
					t(
						'launchpad',
						'No visibility rules yet — this widget is always shown.',
					)
				}}
			</p>

			<section
				v-for="section in sections"
				:key="section.id"
				class="conditional-visibility-editor__section"
				:data-test="section.id + '-section'">
				<h3>{{ section.heading }}</h3>
				<ul class="conditional-visibility-editor__rows">
					<li
						v-for="row in section.rules"
						:key="rowKey(row)"
						class="conditional-visibility-editor__card"
						:data-test="'visibility-rule-row-' + section.id">
						<div class="conditional-visibility-editor__card-head" data-test="visibility-rule-card">
							<span
								:id="'rule-kind-' + rowKey(row)"
								class="conditional-visibility-editor__kind"
								data-test="rule-card-kind">
								{{ kindLabel(row) }}
							</span>
							<span
								v-if="row.ruleType === 'group'"
								class="conditional-visibility-editor__chips"
								data-test="rule-card-value">
								<span
									v-for="name in groupNamesOf(row)"
									:key="name"
									class="conditional-visibility-editor__chip">{{ name }}</span>
							</span>
							<span
								v-else
								class="conditional-visibility-editor__value"
								data-test="rule-card-value">{{ valueOf(row) }}</span>
							<span class="conditional-visibility-editor__card-actions">
								<NcButton
									variant="tertiary"
									:aria-describedby="'rule-kind-' + rowKey(row)"
									:aria-expanded="isEditing(row) ? 'true' : 'false'"
									data-test="rule-card-edit"
									@click="toggleEdit(row)">
									{{ t('launchpad', 'Edit') }}
								</NcButton>
								<NcButton
									variant="tertiary"
									:aria-describedby="'rule-kind-' + rowKey(row)"
									:disabled="isRowBusy(row)"
									data-test="rule-card-delete"
									@click="onRowRemove(row)">
									{{ t('launchpad', 'Delete') }}
								</NcButton>
							</span>
						</div>
						<VisibilityRuleRow
							v-if="isEditing(row)"
							:rule="row"
							:availableGroups="availableGroups"
							:busy="isRowBusy(row)"
							:isNew="row.id === null"
							@update:rule="onRowUpdate(row, $event)"
							@save="onRowSave(row, $event)"
							@remove="onRowRemove(row)" />
					</li>
				</ul>
			</section>

			<NcButton variant="secondary" data-test="add-rule" @click="addRule">
				<template #icon>
					<Plus :size="18" />
				</template>
				{{ t('launchpad', 'Add rule') }}
			</NcButton>

			<!-- REQ-CVUI-006: View as a person at a moment, with the sentence. -->
			<section
				class="conditional-visibility-editor__preview"
				data-test="visibility-preview">
				<h3>{{ t('launchpad', 'View as') }}</h3>
				<div class="conditional-visibility-editor__preview-fields">
					<div class="conditional-visibility-editor__preview-field">
						<NcSelect
							v-model="previewPerson"
							:options="personOptions"
							:filterable="false"
							:loading="personLoading"
							:inputLabel="t('launchpad', 'Person')"
							:placeholder="t('launchpad', 'Search people')"
							label="displayName"
							trackBy="id"
							:clearable="false"
							data-test="preview-person"
							@search="onPersonSearch" />
					</div>
					<div class="conditional-visibility-editor__preview-field">
						<NcTextField
							v-model="previewDatetime"
							type="datetime-local"
							:label="t('launchpad', 'Moment')"
							data-test="preview-datetime" />
					</div>
					<NcButton
						variant="primary"
						:disabled="preview.state.loading"
						data-test="run-preview"
						@click="runPreview">
						{{ t('launchpad', 'Preview') }}
					</NcButton>
				</div>

				<div
					v-if="preview.state.loading"
					class="conditional-visibility-editor__preview-loading">
					<NcLoadingIcon :size="24" />
				</div>

				<div
					v-else-if="preview.state.error"
					class="conditional-visibility-editor__preview-error"
					data-test="preview-error">
					{{
						t(
							'launchpad',
							'Preview failed — check the rules above and try again.',
						)
					}}
				</div>

				<div
					v-else-if="preview.state.result"
					class="conditional-visibility-editor__preview-result"
					role="status"
					data-test="preview-result">
					<p class="conditional-visibility-editor__preview-verdict">
						<Eye v-if="preview.state.result.visible" :size="20" />
						<EyeOff v-else :size="20" />
						<strong data-test="preview-verdict-text">
							{{
								preview.state.result.visible
									? t('launchpad', 'Visible')
									: t('launchpad', 'Hidden')
							}}
						</strong>
					</p>
					<p
						class="conditional-visibility-editor__preview-sentence"
						data-test="visibility-sentence">
						{{ sentence }}
					</p>
					<p
						v-for="rule in matchedIncludeRules"
						:key="'inc-' + rowKey(rule)"
						class="conditional-visibility-editor__preview-reason"
						data-test="preview-matched-include">
						{{ t('launchpad', 'Matched include rule: {summary}', { summary: valueOf(rule) }) }}
					</p>
					<p
						v-for="rule in matchedExcludeRules"
						:key="'exc-' + rowKey(rule)"
						class="conditional-visibility-editor__preview-reason"
						data-test="preview-matched-exclude">
						{{ t('launchpad', 'Matched exclude rule: {summary}', { summary: valueOf(rule) }) }}
					</p>
				</div>
			</section>
		</template>
	</div>
</template>

<script>
import {
	NcButton,
	NcLoadingIcon,
	NcSelect,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { getLanguage, t } from '@nextcloud/l10n'
import Eye from 'vue-material-design-icons/Eye.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import VisibilityRuleRow from './VisibilityRuleRow.vue'
import { previewRuleId, useVisibilityPreview } from '../../composables/useVisibilityPreview.js'
import { LIST_PROP } from '../../constants/propDefinitions.js'
import { api } from '../../services/api.js'
import { toGroupOptions } from '../../utils/groupOptions.js'
import { logger } from '../../utils/logger.js'
import {
	formatMoment,
	ruleGroupNames,
	ruleKindLabel,
	ruleValue,
	visibilitySentence,
} from '../../utils/visibilitySentence.js'

/** How the include rules combine: one is enough, or all must match. */
const MATCH_MODES = ['all', 'any']

/**
 * The signed-in user as a person option, or null.
 *
 * @return {{id: string, displayName: string}|null} the option.
 */
function currentPerson() {
	const user = getCurrentUser()
	return user ? { id: user.uid, displayName: user.displayName || user.uid } : null
}

/**
 * A moment as the value of a `datetime-local` field, in local time.
 *
 * @param {Date} date The moment.
 * @return {string} `YYYY-MM-DDTHH:mm`.
 */
function localDatetime(date) {
	const pad = (n) => String(n).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/**
 * A response body's `data` envelope when present, else the body itself.
 *
 * @param {object|null} data The response body.
 * @return {object|null} the rule.
 */
function unwrapData(data) {
	return data && data.data ? data.data : data
}

/**
 * The rule list of a rules response: `{rules}`, `{data: {rules}}` or a bare
 * array; anything else reads as no rules.
 *
 * @param {object|Array|null} data The response body.
 * @return {Array<object>} the rules.
 */
function rulesFromResponse(data) {
	const payload = rulesPayload(data)
	if (Array.isArray(payload.rules)) {
		return payload.rules
	}
	return Array.isArray(data) ? data : []
}

/**
 * The object of a rules response that carries the `rules` key.
 *
 * @param {object|Array|null} data The response body.
 * @return {object} the payload, `{}` when there is none.
 */
function rulesPayload(data) {
	return data && data.rules ? data : data && data.data ? data.data : {}
}

/**
 * Rules as editor rows: persisted, so without a local draft key.
 *
 * @param {Array<object>} list The rules.
 * @return {Array<object>} the rows.
 */
function editableRules(list) {
	return list.map((r) => ({
		...r,
		id: r.id,
		_localKey: undefined,
	}))
}

export default {
	name: 'ConditionalVisibilityEditor',

	components: {
		NcButton,
		NcSelect,
		NcTextField,
		NcLoadingIcon,
		Plus,
		Eye,
		EyeOff,
		VisibilityRuleRow,
	},

	props: {
		/** Placement id whose rules are being edited. */
		placementId: {
			type: [Number, String],
			default: null,
		},

		/** Group-id options for the group-rule picker and the group chips. */
		availableGroups: LIST_PROP,

		/** How the include rules combine: `any` (one is enough) or `all`. */
		ruleMatch: {
			type: String,
			default: 'any',
		},
	},

	emits: ['ruleAdded', 'ruleUpdated', 'ruleRemoved', 'ruleMatchChanged'],

	/** @spec openspec/specs/conditional-visibility-editor/spec.md */
	setup() {
		return { preview: useVisibilityPreview() }
	},

	data() {
		return {
			rules: [],
			loading: false,
			busyKeys: {},
			nextLocalId: -1,
			matchMode: this.ruleMatch === 'all' ? 'all' : 'any',
			savingMatch: false,
			editingKeys: {},
			previewPerson: currentPerson(),
			personOptions: currentPerson() ? [currentPerson()] : [],
			personLoading: false,
			personSearchSeq: 0,
			previewDatetime: localDatetime(new Date()),
			previewSubject: null,
		}
	},

	computed: {
		/**
		 * The two choice cards: all rules or one rule must match.
		 *
		 * @return {Array<{id: string, label: string}>} the modes.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		matchModes() {
			return [
				{ id: 'all', label: t('launchpad', 'If all rules match') },
				{ id: 'any', label: t('launchpad', 'If one rule matches') },
			]
		},

		/**
		 * The include and exclude sections that have rules, with headings
		 * that follow the match mode (REQ-CVUI-003).
		 *
		 * @return {Array<{id: string, heading: string, rules: Array<object>}>} the sections.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		sections() {
			return [
				{
					id: 'include',
					heading: this.matchMode === 'all'
						? t('launchpad', 'Show when ALL of these match')
						: t('launchpad', 'Show when ANY of these match'),
					rules: this.includeRules,
				},
				{ id: 'exclude', heading: t('launchpad', 'Hide when ANY of these match'), rules: this.excludeRules },
			].filter((section) => section.rules.length > 0)
		},

		/**
		 * Display name per group id, for the chips and the sentence.
		 *
		 * @return {{[key: string]: string}} the names.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		groupNames() {
			return Object.fromEntries(toGroupOptions(this.availableGroups).map((group) => [group.id, group.displayName]))
		},

		/**
		 * The include rules the last preview matched (REQ-CVUI-004).
		 *
		 * @return {Array<object>} the rules.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date
		 */
		matchedIncludeRules() {
			const ids = this.preview.state.result?.matchedIncludeRuleIds || []
			return this.includeRules.filter((r) => ids.includes(previewRuleId(r)))
		},

		/**
		 * The exclude rules the last preview matched (REQ-CVUI-004).
		 *
		 * @return {Array<object>} the rules.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date
		 */
		matchedExcludeRules() {
			const ids = this.preview.state.result?.matchedExcludeRuleIds || []
			return this.excludeRules.filter((r) => ids.includes(previewRuleId(r)))
		},

		/**
		 * The View as sentence for the last preview: whether the person
		 * sees the widget at the moment, and why.
		 *
		 * @return {string} the sentence, empty before a preview.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		sentence() {
			if (!this.preview.state.result || !this.previewSubject) {
				return ''
			}
			return visibilitySentence({
				...this.previewSubject,
				rules: this.rules.map((rule) => ({ ...rule, id: previewRuleId(rule) })),
				result: this.preview.state.result,
				groupNames: this.groupNames,
			})
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-003-legible-include-exclude-semantics */
		includeRules() {
			return this.rules.filter((r) => r.isInclude !== false)
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-003-legible-include-exclude-semantics */
		excludeRules() {
			return this.rules.filter((r) => r.isInclude === false)
		},

		isEmpty() {
			return this.rules.length === 0
		},
	},

	watch: {
		/**
		 * Follow the placement's mode when it changes outside the dialog.
		 *
		 * @param {string} mode the stored mode.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		ruleMatch(mode) {
			this.matchMode = mode === 'all' ? 'all' : 'any'
		},

		placementId: {
			immediate: true,
			/** @spec openspec/specs/conditional-visibility-editor/spec.md */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/**
		 * Stable `v-for` key for a rule row. Persisted rules key on their
		 * server id; never-saved drafts key on a local counter so adding or
		 * removing a draft does not renumber its siblings.
		 *
		 * @param {object} row The rule row.
		 * @return {string} The render key.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 */
		rowKey(row) {
			return row.id !== null && row.id !== undefined
				? `id-${row.id}`
				: `local-${row._localKey}`
		},

		isRowBusy(row) {
			return !!this.busyKeys[this.rowKey(row)]
		},

		setRowBusy(row, value) {
			this.busyKeys[this.rowKey(row)] = value
		},

		/** @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings */
		async load() {
			if (this.placementId === null || this.placementId === undefined) {
				this.rules = []
				return
			}
			await this.loadRules()
		},

		/** Fetch the placement's rules, clearing them when the fetch fails. */
		async loadRules() {
			this.loading = true
			this.preview.reset()
			try {
				const { data } = await api.getWidgetRules(this.placementId)
				this.rules = editableRules(rulesFromResponse(data))
			} catch (error) {
				logger.error('Failed to load visibility rules:', error)
				this.rules = []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Append a fresh, unsaved draft row. Uses a decrementing negative
		 * local key so it can never collide with a real (positive) rule
		 * id — including when it is echoed back by the preview endpoint's
		 * `matchedIncludeRuleIds` / `matchedExcludeRuleIds` before the row
		 * is ever saved.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {void}
		 */
		addRule() {
			const localKey = this.nextLocalId--
			this.rules.push({
				id: null,
				_localKey: localKey,
				ruleType: 'group',
				ruleConfig: { groups: [] },
				isInclude: true,
			})
		},

		/**
		 * Live-sync a row's edits into the shared `rules` array so preview
		 * always reflects the in-editor (possibly unsaved) state
		 * (REQ-CVUI-004 "Preview evaluates unsaved edits").
		 *
		 * @param {object} row the rule object identity to update
		 * @param {object} payload `{ruleType, ruleConfig, isInclude}`
		 * @spec openspec/specs/conditional-visibility-editor/spec.md
		 * @return {void}
		 */
		onRowUpdate(row, payload) {
			const index = this.rules.indexOf(row)
			if (index === -1) {
				return
			}
			this.rules[index] = { ...row, ...payload }
		},

		/**
		 * Persist a rule row — updates an existing rule, or creates one for
		 * a draft that has never been saved.
		 *
		 * @param {object} row The rule row being saved.
		 * @param {object} payload Rule fields to write (`ruleType`,
		 *   `ruleConfig`, `isInclude`).
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings
		 */
		async onRowSave(row, payload) {
			this.setRowBusy(row, true)
			try {
				if (row.id !== null && row.id !== undefined) {
					await this.updateSavedRule(row, payload)
				} else {
					await this.createDraftRule(row, payload)
				}
			} catch (error) {
				logger.error('Failed to save visibility rule:', error)
			} finally {
				this.setRowBusy(row, false)
			}
		},

		/**
		 * Write a persisted rule, merge the server's copy into its row and
		 * close the card's editor.
		 *
		 * @param {object} row The persisted rule row.
		 * @param {object} payload Rule fields to write.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		async updateSavedRule(row, payload) {
			const { data } = await api.updateRule(row.id, payload)
			const updated = unwrapData(data)
			const index = this.rules.indexOf(row)
			if (index !== -1) {
				this.rules[index] = { ...row, ...updated }
			}
			this.editingKeys = { ...this.editingKeys, [this.rowKey(row)]: false }
			this.$emit('ruleUpdated')
		},

		/**
		 * Create a draft rule and swap the draft row for the created rule;
		 * reload the list when the response carries no id.
		 *
		 * @param {object} row The draft rule row.
		 * @param {object} payload Rule fields to write.
		 */
		async createDraftRule(row, payload) {
			const { data } = await api.addWidgetRule(
				this.placementId,
				payload,
			)
			const created = unwrapData(data)
			const index = this.rules.indexOf(row)
			if (index !== -1 && created && created.id) {
				this.rules[index] = { ...created, _localKey: undefined }
			} else {
				await this.load()
			}
			this.$emit('ruleAdded')
		},

		/**
		 * Remove a rule row. A never-persisted draft is discarded locally
		 * with no API call; a saved rule is deleted server-side.
		 *
		 * @param {object} row The rule row to remove.
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings
		 */
		async onRowRemove(row) {
			if (row.id === null || row.id === undefined) {
				// Never-persisted draft — discard locally, no API call.
				this.rules = this.rules.filter((r) => r !== row)
				return
			}

			this.setRowBusy(row, true)
			const snapshot = this.rules.slice()
			this.rules = this.rules.filter((r) => r !== row)
			try {
				await api.deleteRule(row.id)
				this.$emit('ruleRemoved')
			} catch (error) {
				this.rules = snapshot
				logger.error('Failed to remove visibility rule:', error)
			} finally {
				this.setRowBusy(row, false)
			}
		},

		/**
		 * Choose whether all rules or one rule must match, and save it on
		 * the placement; on a failed save the previous choice comes back.
		 *
		 * @param {string} mode `all` or `any`.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		async setMatchMode(mode) {
			if (mode === this.matchMode || !MATCH_MODES.includes(mode)) {
				return
			}
			const previous = this.matchMode
			this.matchMode = mode
			if (this.placementId === null || this.placementId === undefined) {
				return
			}
			this.savingMatch = true
			try {
				await api.updateWidgetPlacement(this.placementId, { ruleMatch: mode })
				this.$emit('ruleMatchChanged', mode)
			} catch (error) {
				logger.error('Failed to save how the visibility rules combine:', error)
				this.matchMode = previous
			} finally {
				this.savingMatch = false
			}
		},

		/**
		 * Arrow keys on a choice card choose the other card and move the
		 * focus there, as in a radio group.
		 *
		 * @param {KeyboardEvent} event the key press.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		async onModeKeydown(event) {
			if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
				return
			}
			event.preventDefault()
			const other = this.matchMode === 'all' ? 'any' : 'all'
			await this.setMatchMode(other)
			await this.$nextTick()
			this.$el.querySelector(`[data-test="rule-match-${this.matchMode}"]`)?.focus()
		},

		/**
		 * Whether a rule's card shows its editor: a draft always, a saved
		 * rule after Edit.
		 *
		 * @param {object} row The rule row.
		 * @return {boolean} true when the editor shows.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		isEditing(row) {
			return row.id === null || row.id === undefined || !!this.editingKeys[this.rowKey(row)]
		},

		/**
		 * Open or close a saved rule's editor.
		 *
		 * @param {object} row The rule row.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		toggleEdit(row) {
			const key = this.rowKey(row)
			this.editingKeys = { ...this.editingKeys, [key]: !this.editingKeys[key] }
		},

		/**
		 * The kind of a rule, as its card names it.
		 *
		 * @param {object} row The rule row.
		 * @return {string} Groups, Times, Period or Profile field.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		kindLabel(row) {
			return ruleKindLabel(row)
		},

		/**
		 * The groups a group rule names, as chips.
		 *
		 * @param {object} row The rule row.
		 * @return {string[]} the group names.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		groupNamesOf(row) {
			return ruleGroupNames(row, this.groupNames)
		},

		/**
		 * The value of a time, period or profile field rule.
		 *
		 * @param {object} row The rule row.
		 * @return {string} the value.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		valueOf(row) {
			return ruleValue(row)
		},

		/**
		 * Search people to view as; an empty search offers yourself again.
		 * The server ignores one-letter searches.
		 *
		 * @param {string} query The typed text.
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		async onPersonSearch(query) {
			const trimmed = (query || '').trim()
			if (trimmed.length < 2) {
				return
			}
			const seq = ++this.personSearchSeq
			this.personLoading = true
			try {
				const { data } = await api.searchSharees(trimmed)
				if (seq === this.personSearchSeq) {
					const self = currentPerson()
					this.personOptions = [...(self ? [self] : []), ...(data?.users || []).map((u) => ({ id: u.id, displayName: u.displayName || u.id }))]
				}
			} catch (error) {
				logger.error('Person search failed:', error)
			} finally {
				this.personLoading = false
			}
		},

		/**
		 * Preview the in-editor rules as the chosen person at the chosen
		 * moment, in the chosen match mode, and keep who and when for the
		 * sentence.
		 *
		 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date
		 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
		 */
		async runPreview() {
			const person = this.previewPerson
			const moment = this.previewDatetime ? new Date(this.previewDatetime) : new Date()
			try {
				await this.preview.runPreview(this.rules, {
					userId: person?.id || null,
					datetime: moment.toISOString(),
					match: this.matchMode,
					placementId: this.placementId,
				})
				this.previewSubject = {
					name: person?.displayName || person?.id || '',
					moment: formatMoment(moment, getLanguage()),
				}
			} catch {
				// Surfaced via preview.state.error in the template.
			}
		},
	},
}
</script>

<style scoped>
.conditional-visibility-editor {
	display: flex;
	flex-direction: column;
	gap: 16px;
}


.conditional-visibility-editor__loading {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.conditional-visibility-editor__empty {
	color: var(--color-text-maxcontrast);
}

.conditional-visibility-editor__section h3 {
	margin: 0 0 8px;
}

.conditional-visibility-editor__rows {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.conditional-visibility-editor__modes {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 8px;
}

.conditional-visibility-editor__mode {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 12px 16px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius-large, 10px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
	text-align: start;
	cursor: pointer;
}

.conditional-visibility-editor__mode--active {
	border-color: var(--color-primary-element);
	font-weight: bold;
}

.conditional-visibility-editor__mode:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.conditional-visibility-editor__mode-dot {
	flex: none;
	width: 14px;
	height: 14px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: 50%;
}

.conditional-visibility-editor__mode--active .conditional-visibility-editor__mode-dot {
	border: 4px solid var(--color-primary-element);
}

.conditional-visibility-editor__card {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 10px);
	padding: 8px 12px;
}

.conditional-visibility-editor__card-head {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.conditional-visibility-editor__kind {
	font-weight: bold;
	min-width: 96px;
}

.conditional-visibility-editor__chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.conditional-visibility-editor__chip {
	padding: 2px 10px;
	border-radius: var(--border-radius-pill, 16px);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.conditional-visibility-editor__value {
	color: var(--color-text-maxcontrast);
}

.conditional-visibility-editor__card-actions {
	display: flex;
	gap: 4px;
	margin-inline-start: auto;
}

.conditional-visibility-editor__preview-sentence {
	margin: 0;
}

.conditional-visibility-editor__preview-reason {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.conditional-visibility-editor__preview {
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}

.conditional-visibility-editor__preview h3 {
	margin: 0 0 8px;
}

.conditional-visibility-editor__preview-fields {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 12px;
	margin-bottom: 12px;
}

.conditional-visibility-editor__preview-field {
	min-width: 200px;
	flex: 1 1 200px;
}

.conditional-visibility-editor__preview-loading {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.conditional-visibility-editor__preview-error {
	color: var(--color-error-text, var(--color-error));
}

.conditional-visibility-editor__preview-verdict {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 4px;
}

</style>
