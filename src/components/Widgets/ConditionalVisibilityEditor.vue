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
	<div class="conditional-visibility-editor" data-test="conditional-visibility-editor">
		<p class="conditional-visibility-editor__hint">
			{{ t('launchpad', 'Show or hide this widget based on group membership, time of day, date range, or a user attribute.') }}
		</p>

		<div v-if="loading" class="conditional-visibility-editor__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<template v-else>
			<p
				v-if="isEmpty"
				class="conditional-visibility-editor__empty"
				data-test="visibility-empty-state">
				{{ t('launchpad', 'No visibility rules yet — this widget is always shown.') }}
			</p>

			<section
				v-if="includeRules.length > 0"
				class="conditional-visibility-editor__section"
				data-test="include-section">
				<h3>{{ t('launchpad', 'Show when ANY of these match') }}</h3>
				<div class="conditional-visibility-editor__rows">
					<VisibilityRuleRow
						v-for="row in includeRules"
						:key="rowKey(row)"
						:rule="row"
						:available-groups="availableGroups"
						:busy="isRowBusy(row)"
						:is-new="row.id === null"
						data-test="visibility-rule-row-include"
						@update:rule="onRowUpdate(row, $event)"
						@save="onRowSave(row, $event)"
						@remove="onRowRemove(row)" />
				</div>
			</section>

			<section
				v-if="excludeRules.length > 0"
				class="conditional-visibility-editor__section"
				data-test="exclude-section">
				<h3>{{ t('launchpad', 'Hide when ANY of these match') }}</h3>
				<div class="conditional-visibility-editor__rows">
					<VisibilityRuleRow
						v-for="row in excludeRules"
						:key="rowKey(row)"
						:rule="row"
						:available-groups="availableGroups"
						:busy="isRowBusy(row)"
						:is-new="row.id === null"
						data-test="visibility-rule-row-exclude"
						@update:rule="onRowUpdate(row, $event)"
						@save="onRowSave(row, $event)"
						@remove="onRowRemove(row)" />
				</div>
			</section>

			<NcButton type="secondary" data-test="add-rule" @click="addRule">
				<template #icon>
					<Plus :size="18" />
				</template>
				{{ t('launchpad', 'Add rule') }}
			</NcButton>

			<!-- REQ-CVUI-004: preview as audience/date. -->
			<section class="conditional-visibility-editor__preview" data-test="visibility-preview">
				<h3>{{ t('launchpad', 'Preview as audience / date') }}</h3>
				<div class="conditional-visibility-editor__preview-fields">
					<div class="conditional-visibility-editor__preview-field">
						<NcSelectTags
							v-model="previewGroups"
							:options="availableGroups"
							:multiple="true"
							:aria-label-combobox="t('launchpad', 'Preview as groups')"
							:placeholder="t('launchpad', 'Select groups to preview as')"
							data-test="preview-groups" />
					</div>
					<div class="conditional-visibility-editor__preview-field">
						<NcTextField
							:value="previewDatetime"
							type="datetime-local"
							:label="t('launchpad', 'Preview at date/time')"
							data-test="preview-datetime"
							@update:value="previewDatetime = $event" />
					</div>
					<NcButton
						type="primary"
						:disabled="preview.state.loading"
						data-test="run-preview"
						@click="runPreview">
						{{ t('launchpad', 'Preview') }}
					</NcButton>
				</div>

				<div v-if="preview.state.loading" class="conditional-visibility-editor__preview-loading">
					<NcLoadingIcon :size="24" />
				</div>

				<div
					v-else-if="preview.state.error"
					class="conditional-visibility-editor__preview-error"
					data-test="preview-error">
					{{ t('launchpad', 'Preview failed — check the rules above and try again.') }}
				</div>

				<div
					v-else-if="preview.state.result"
					class="conditional-visibility-editor__preview-result"
					data-test="preview-result">
					<p class="conditional-visibility-editor__preview-verdict">
						<Eye v-if="preview.state.result.visible" :size="20" />
						<EyeOff v-else :size="20" />
						<strong data-test="preview-verdict-text">
							{{ preview.state.result.visible ? t('launchpad', 'Visible') : t('launchpad', 'Hidden') }}
						</strong>
					</p>
					<p
						v-for="rule in matchedIncludeRules"
						:key="'inc-' + rule.id"
						class="conditional-visibility-editor__preview-reason"
						data-test="preview-matched-include">
						{{ t('launchpad', 'Matched include rule: {summary}', { summary: describeRule(rule) }) }}
					</p>
					<p
						v-for="rule in matchedExcludeRules"
						:key="'exc-' + rule.id"
						class="conditional-visibility-editor__preview-reason"
						data-test="preview-matched-exclude">
						{{ t('launchpad', 'Matched exclude rule: {summary}', { summary: describeRule(rule) }) }}
					</p>
				</div>
			</section>
		</template>
	</div>
</template>

<script>
import { NcButton, NcSelectTags, NcTextField, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Plus from 'vue-material-design-icons/Plus.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import { api } from '../../services/api.js'
import { useVisibilityPreview } from '../../composables/useVisibilityPreview.js'
import VisibilityRuleRow from './VisibilityRuleRow.vue'

export default {
	name: 'ConditionalVisibilityEditor',

	components: {
		NcButton,
		NcSelectTags,
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
		/** Group-id options for the group-rule / preview-audience pickers. */
		availableGroups: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['rule-added', 'rule-updated', 'rule-removed'],

	/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md */
	setup() {
		return { preview: useVisibilityPreview() }
	},

	data() {
		return {
			rules: [],
			loading: false,
			busyKeys: {},
			nextLocalId: -1,
			previewGroups: [],
			previewDatetime: '',
		}
	},

	computed: {
		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-003-legible-include-exclude-semantics */
		includeRules() {
			return this.rules.filter((r) => r.isInclude !== false)
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-003-legible-include-exclude-semantics */
		excludeRules() {
			return this.rules.filter((r) => r.isInclude === false)
		},

		isEmpty() {
			return this.rules.length === 0
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md */
		matchedIncludeRules() {
			const ids = (this.preview.state.result && this.preview.state.result.matchedIncludeRuleIds) || []
			return this.rules.filter((r) => ids.includes(r.id))
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md */
		matchedExcludeRules() {
			const ids = (this.preview.state.result && this.preview.state.result.matchedExcludeRuleIds) || []
			return this.rules.filter((r) => ids.includes(r.id))
		},
	},

	watch: {
		placementId: {
			immediate: true,
			/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md */
		rowKey(row) {
			return row.id !== null && row.id !== undefined ? `id-${row.id}` : `local-${row._localKey}`
		},

		isRowBusy(row) {
			return !!this.busyKeys[this.rowKey(row)]
		},

		setRowBusy(row, value) {
			this.$set(this.busyKeys, this.rowKey(row), value)
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings */
		async load() {
			if (this.placementId === null || this.placementId === undefined) {
				this.rules = []
				return
			}
			this.loading = true
			this.preview.reset()
			try {
				const { data } = await api.getWidgetRules(this.placementId)
				const payload = (data && data.rules) ? data : (data && data.data) ? data.data : {}
				const list = Array.isArray(payload.rules) ? payload.rules : (Array.isArray(data) ? data : [])
				this.rules = list.map((r) => ({ ...r, id: r.id, _localKey: undefined }))
			} catch (error) {
				console.error('Failed to load visibility rules:', error)
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
		 * @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md
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
		 * @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md
		 * @return {void}
		 */
		onRowUpdate(row, payload) {
			const index = this.rules.indexOf(row)
			if (index === -1) {
				return
			}
			this.$set(this.rules, index, { ...row, ...payload })
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings */
		async onRowSave(row, payload) {
			this.setRowBusy(row, true)
			try {
				if (row.id !== null && row.id !== undefined) {
					const { data } = await api.updateRule(row.id, payload)
					const updated = (data && data.data) ? data.data : data
					const index = this.rules.indexOf(row)
					if (index !== -1) {
						this.$set(this.rules, index, { ...row, ...updated })
					}
					this.$emit('rule-updated')
				} else {
					const { data } = await api.addWidgetRule(this.placementId, payload)
					const created = (data && data.data) ? data.data : data
					const index = this.rules.indexOf(row)
					if (index !== -1 && created && created.id) {
						this.$set(this.rules, index, { ...created, _localKey: undefined })
					} else {
						await this.load()
					}
					this.$emit('rule-added')
				}
			} catch (error) {
				console.error('Failed to save visibility rule:', error)
			} finally {
				this.setRowBusy(row, false)
			}
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-001-rule-builder-in-placement-settings */
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
				this.$emit('rule-removed')
			} catch (error) {
				this.rules = snapshot
				console.error('Failed to remove visibility rule:', error)
			} finally {
				this.setRowBusy(row, false)
			}
		},

		/** @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date */
		async runPreview() {
			try {
				await this.preview.runPreview(this.rules, {
					groups: this.previewGroups,
					datetime: this.previewDatetime ? new Date(this.previewDatetime).toISOString() : null,
				})
			} catch (error) {
				// Surfaced via preview.state.error in the template.
			}
		},

		/**
		 * Short human-readable summary of a rule's config, used to indicate
		 * which rule matched in the preview result.
		 *
		 * @param {object} rule the rule entity
		 * @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md
		 * @return {string}
		 */
		describeRule(rule) {
			const cfg = rule.ruleConfig || {}
			switch (rule.ruleType) {
			case 'group':
				return (cfg.groups || []).join(', ')
			case 'time':
				return `${cfg.startTime || ''}–${cfg.endTime || ''}`
			case 'date':
				return `${cfg.startDate || '…'} → ${cfg.endDate || '…'}`
			case 'attribute':
				return `${cfg.attribute || ''} ${cfg.operator || ''} ${cfg.value || ''}`
			default:
				return ''
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

.conditional-visibility-editor__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
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

.conditional-visibility-editor__preview-reason {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
