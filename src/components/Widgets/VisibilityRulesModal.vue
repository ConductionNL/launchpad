<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		v-if="open"
		size="normal"
		:name="t('launchpad', 'Visibility rules')"
		@close="$emit('close')">
		<div class="visibility-rules" data-test="visibility-rules-modal">
			<h2 class="visibility-rules__title">
				{{ t('launchpad', 'Visibility rules') }}
			</h2>
			<p class="visibility-rules__hint">
				{{ t('launchpad', 'Show or hide this widget based on group membership, time of day, date range, or a user attribute. Include rules use OR logic; exclude rules use AND logic.') }}
			</p>

			<div v-if="loading" class="visibility-rules__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<template v-else>
				<ul v-if="rules.length > 0" class="visibility-rules__list" data-test="visibility-rules-list">
					<li
						v-for="rule in rules"
						:key="rule.id"
						class="visibility-rules__item"
						data-test="visibility-rules-item">
						<div class="visibility-rules__item-info">
							<strong>{{ ruleTypeLabel(rule.ruleType) }}</strong>
							<span
								class="visibility-rules__flag"
								:class="rule.isInclude ? 'visibility-rules__flag--include' : 'visibility-rules__flag--exclude'">
								{{ rule.isInclude ? t('launchpad', 'Include') : t('launchpad', 'Exclude') }}
							</span>
							<code class="visibility-rules__config">{{ summariseConfig(rule) }}</code>
						</div>
						<NcButton
							type="tertiary"
							:aria-label="t('launchpad', 'Remove rule')"
							:disabled="busy"
							data-test="visibility-rule-remove"
							@click="removeRule(rule)">
							<template #icon>
								<Close :size="18" />
							</template>
						</NcButton>
					</li>
				</ul>
				<p v-else class="visibility-rules__empty">
					{{ t('launchpad', 'No visibility rules yet — this widget is always shown.') }}
				</p>

				<div class="visibility-rules__form" data-test="visibility-rules-form">
					<h3>{{ t('launchpad', 'Add a rule') }}</h3>

					<div class="visibility-rules__field">
						<NcSelect
							v-model="draft.type"
							:input-label="t('launchpad', 'Rule type')"
							:options="ruleTypeOptions"
							label="label"
							track-by="id"
							:clearable="false" />
					</div>

					<div class="visibility-rules__field">
						<NcSelect
							v-model="draft.mode"
							:input-label="t('launchpad', 'Effect')"
							:options="modeOptions"
							label="label"
							track-by="id"
							:clearable="false" />
					</div>

					<!-- group -->
					<div v-if="draft.type && draft.type.id === 'group'" class="visibility-rules__field">
						<label class="visibility-rules__label">{{ t('launchpad', 'Groups') }}</label>
						<NcSelectTags
							v-model="draft.groups"
							:options="availableGroups"
							:multiple="true"
							:aria-label-combobox="t('launchpad', 'Groups')"
							:placeholder="t('launchpad', 'Select groups')" />
					</div>

					<!-- time -->
					<template v-else-if="draft.type && draft.type.id === 'time'">
						<div class="visibility-rules__field">
							<NcTextField
								:value="draft.startTime"
								:label="t('launchpad', 'Start time (HH:MM)')"
								placeholder="09:00"
								@update:value="draft.startTime = $event" />
						</div>
						<div class="visibility-rules__field">
							<NcTextField
								:value="draft.endTime"
								:label="t('launchpad', 'End time (HH:MM)')"
								placeholder="17:00"
								@update:value="draft.endTime = $event" />
						</div>
					</template>

					<!-- date -->
					<template v-else-if="draft.type && draft.type.id === 'date'">
						<div class="visibility-rules__field">
							<NcTextField
								:value="draft.startDate"
								:label="t('launchpad', 'Start date (YYYY-MM-DD)')"
								placeholder="2026-12-01"
								@update:value="draft.startDate = $event" />
						</div>
						<div class="visibility-rules__field">
							<NcTextField
								:value="draft.endDate"
								:label="t('launchpad', 'End date (YYYY-MM-DD)')"
								placeholder="2026-12-31"
								@update:value="draft.endDate = $event" />
						</div>
					</template>

					<!-- attribute -->
					<template v-else-if="draft.type && draft.type.id === 'attribute'">
						<div class="visibility-rules__field">
							<NcTextField
								:value="draft.attribute"
								:label="t('launchpad', 'Attribute')"
								placeholder="language"
								@update:value="draft.attribute = $event" />
						</div>
						<div class="visibility-rules__field">
							<NcTextField
								:value="draft.value"
								:label="t('launchpad', 'Equals value')"
								placeholder="nl"
								@update:value="draft.value = $event" />
						</div>
					</template>

					<div class="visibility-rules__actions">
						<NcButton
							type="primary"
							:disabled="busy || !canAdd"
							data-test="visibility-rule-add"
							@click="addRule">
							{{ t('launchpad', 'Add rule') }}
						</NcButton>
					</div>
				</div>
			</template>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcSelect, NcSelectTags, NcTextField, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Close from 'vue-material-design-icons/Close.vue'
import { api } from '../../services/api.js'

/**
 * VisibilityRulesModal — per-widget conditional-visibility editor
 * (conditional-visibility spec, Per-Widget Visibility Rules Editor). Opens
 * from the widget context menu, fetches the placement's rules on open, and
 * lets the dashboard owner add / remove rules of the four supported types
 * (group / time / date / attribute) without leaving the canvas.
 */
export default {
	name: 'VisibilityRulesModal',

	components: {
		NcModal,
		NcButton,
		NcSelect,
		NcSelectTags,
		NcTextField,
		NcLoadingIcon,
		Close,
	},

	props: {
		/** Placement id whose rules are being edited. */
		placementId: {
			type: [Number, String],
			default: null,
		},
		/** Whether the modal is shown. */
		open: {
			type: Boolean,
			default: false,
		},
		/** Optional group-id list for the group-rule picker. */
		availableGroups: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close', 'rule-added', 'rule-removed'],

	data() {
		return {
			rules: [],
			loading: false,
			busy: false,
			draft: this.freshDraft(),
		}
	},

	computed: {
		/** @spec openspec/specs/conditional-visibility/spec.md */
		ruleTypeOptions() {
			return [
				{ id: 'group', label: t('launchpad', 'Group') },
				{ id: 'time', label: t('launchpad', 'Time of day') },
				{ id: 'date', label: t('launchpad', 'Date range') },
				{ id: 'attribute', label: t('launchpad', 'User attribute') },
			]
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		modeOptions() {
			return [
				{ id: 'include', label: t('launchpad', 'Include (show when matched)') },
				{ id: 'exclude', label: t('launchpad', 'Exclude (hide when matched)') },
			]
		},

		/**
		 * Whether the current draft has enough input to build a valid rule.
		 *
		 * @return {boolean}
		 */
		canAdd() {
			if (!this.draft.type) {
				return false
			}
			switch (this.draft.type.id) {
			case 'group':
				return Array.isArray(this.draft.groups) && this.draft.groups.length > 0
			case 'time':
				return this.draft.startTime !== '' && this.draft.endTime !== ''
			case 'date':
				return this.draft.startDate !== '' || this.draft.endDate !== ''
			case 'attribute':
				return this.draft.attribute !== '' && this.draft.value !== ''
			default:
				return false
			}
		},
	},

	watch: {
		open: {
			immediate: true,
			handler(value) {
				if (value) {
					this.load()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * A pristine draft-rule form state.
		 *
		 * @return {object}
		 */
		freshDraft() {
			return {
				type: { id: 'group', label: t('launchpad', 'Group') },
				mode: { id: 'include', label: t('launchpad', 'Include (show when matched)') },
				groups: [],
				startTime: '',
				endTime: '',
				startDate: '',
				endDate: '',
				attribute: '',
				value: '',
			}
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		async load() {
			if (this.placementId === null) {
				return
			}
			this.loading = true
			this.draft = this.freshDraft()
			try {
				const { data } = await api.getWidgetRules(this.placementId)
				const payload = data?.data ?? data ?? {}
				this.rules = Array.isArray(payload.rules) ? payload.rules : (Array.isArray(payload) ? payload : [])
			} catch (error) {
				console.error('Failed to load visibility rules:', error)
				this.rules = []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Build the `ruleConfig` blob for the active draft type.
		 *
		 * @return {object} The config blob matching the spec schema.
		 */
		buildRuleConfig() {
			switch (this.draft.type.id) {
			case 'group':
				return { groups: this.draft.groups }
			case 'time':
				return { startTime: this.draft.startTime, endTime: this.draft.endTime }
			case 'date':
				return { startDate: this.draft.startDate, endDate: this.draft.endDate }
			case 'attribute':
				return { attribute: this.draft.attribute, operator: 'equals', value: this.draft.value }
			default:
				return {}
			}
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		async addRule() {
			if (!this.canAdd || this.busy) {
				return
			}
			this.busy = true
			try {
				const { data } = await api.addWidgetRule(this.placementId, {
					ruleType: this.draft.type.id,
					ruleConfig: this.buildRuleConfig(),
					isInclude: this.draft.mode.id === 'include',
				})
				const created = data?.data ?? data
				if (created && created.id) {
					this.rules = [...this.rules, created]
				} else {
					// Fall back to a refetch if the envelope shape is unexpected.
					await this.load()
				}
				this.draft = this.freshDraft()
				this.$emit('rule-added')
			} catch (error) {
				console.error('Failed to add visibility rule:', error)
			} finally {
				this.busy = false
			}
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		async removeRule(rule) {
			if (this.busy) {
				return
			}
			this.busy = true
			const snapshot = this.rules.slice()
			// Optimistic removal so the list never lags the click.
			this.rules = this.rules.filter(r => r.id !== rule.id)
			try {
				await api.deleteRule(rule.id)
				this.$emit('rule-removed')
			} catch (error) {
				// Roll back on failure.
				this.rules = snapshot
				console.error('Failed to remove visibility rule:', error)
			} finally {
				this.busy = false
			}
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		ruleTypeLabel(type) {
			const found = this.ruleTypeOptions.find(o => o.id === type)
			return found ? found.label : type
		},

		/**
		 * Short human-readable summary of a rule's config for the list row.
		 *
		 * @param {object} rule The rule entity.
		 * @return {string}
		 */
		summariseConfig(rule) {
			const cfg = rule.ruleConfig || {}
			switch (rule.ruleType) {
			case 'group':
				return (cfg.groups || []).join(', ')
			case 'time':
				return `${cfg.startTime || ''}–${cfg.endTime || ''}`
			case 'date':
				return `${cfg.startDate || '…'} → ${cfg.endDate || '…'}`
			case 'attribute':
				return `${cfg.attribute || ''} = ${cfg.value || ''}`
			default:
				return ''
			}
		},
	},
}
</script>

<style scoped>
.visibility-rules {
	padding: 24px;
}

.visibility-rules__title {
	margin: 0 0 8px;
}

.visibility-rules__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.visibility-rules__loading {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.visibility-rules__list {
	list-style: none;
	margin: 0 0 24px;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.visibility-rules__item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 12px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.visibility-rules__item-info {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.visibility-rules__flag {
	padding: 1px 8px;
	border-radius: var(--border-radius-pill);
	font-size: 12px;
}

.visibility-rules__flag--include {
	background: var(--color-success, #2d7d46);
	color: #fff;
}

.visibility-rules__flag--exclude {
	background: var(--color-error, #d32f2f);
	color: #fff;
}

.visibility-rules__config {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.visibility-rules__empty {
	color: var(--color-text-maxcontrast);
	margin-bottom: 24px;
}

.visibility-rules__form h3 {
	margin: 0 0 12px;
}

.visibility-rules__field {
	margin-bottom: 12px;
}

.visibility-rules__label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
}

.visibility-rules__actions {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
}
</style>
