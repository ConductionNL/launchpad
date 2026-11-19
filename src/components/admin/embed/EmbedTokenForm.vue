<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<form class="embed-token-form" data-test="embed-token-form" @submit.prevent="submit">
		<NcTextField
			v-model="form.name"
			:label="t('launchpad', 'Name')"
			:error="errors.name !== ''"
			:helperText="errors.name"
			required
			data-test="embed-token-name" />
		<NcTextArea
			v-model="form.description"
			:label="t('launchpad', 'Description')"
			data-test="embed-token-description" />

		<template v-if="!editing">
			<fieldset class="embed-token-form__group">
				<legend>{{ t('launchpad', 'What to embed') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="option in subjectTypes"
					:key="option.id"
					v-model="form.subjectType"
					type="radio"
					name="embed-subject-type"
					:value="option.id"
					:data-test="`embed-subject-type-${option.id}`">
					{{ option.label }}
				</NcCheckboxRadioSwitch>
				<NcSelect
					:modelValue="selectedDashboard"
					:inputLabel="t('launchpad', 'Dashboard')"
					:options="dashboards"
					label="name"
					trackBy="id"
					:clearable="false"
					data-test="embed-subject-dashboard"
					@update:modelValue="pickDashboard" />
				<NcSelect
					v-if="form.subjectType === 'widget'"
					:modelValue="selectedPlacement"
					:inputLabel="t('launchpad', 'Widget')"
					:options="placementOptions"
					:loading="loadingPlacements"
					:disabled="form.dashboardId === null"
					label="label"
					trackBy="id"
					:clearable="false"
					data-test="embed-subject-widget"
					@update:modelValue="form.subjectId = $event ? String($event.id) : ''" />
				<p v-if="errors.subject !== ''" class="embed-token-form__error" role="alert">
					{{ errors.subject }}
				</p>
				<NcTextArea
					v-if="form.subjectType === 'dashboard'"
					v-model="form.filterContext"
					:label="t('launchpad', 'Pinned filters, one dimension=value per line')"
					data-test="embed-token-filter-context" />
			</fieldset>

			<fieldset class="embed-token-form__group">
				<legend>{{ t('launchpad', 'Scope') }}</legend>
				<NcCheckboxRadioSwitch
					v-for="option in modes"
					:key="option.id"
					v-model="form.mode"
					type="radio"
					name="embed-scope-mode"
					:value="option.id"
					:data-test="`embed-scope-mode-${option.id}`">
					{{ option.label }}
				</NcCheckboxRadioSwitch>
				<template v-if="form.mode === 'read-with-interactions'">
					<NcCheckboxRadioSwitch
						v-for="option in actions"
						:key="option.id"
						v-model="form.allowedActions"
						type="checkbox"
						:value="option.id"
						:data-test="`embed-scope-action-${option.id}`">
						{{ option.label }}
					</NcCheckboxRadioSwitch>
					<NcTextField
						v-model="form.allowedFilters"
						:label="t('launchpad', 'Filters viewers may change, comma-separated')"
						data-test="embed-token-allowed-filters" />
				</template>
			</fieldset>

			<fieldset class="embed-token-form__group">
				<legend>{{ t('launchpad', 'Sites that may embed it') }}</legend>
				<ul class="embed-token-form__origins" data-test="embed-token-origins">
					<li v-for="origin in form.hostOrigins" :key="origin">
						<code>{{ origin }}</code>
						<NcButton
							variant="tertiary"
							:aria-label="t('launchpad', 'Remove {origin}', { origin })"
							@click="removeOrigin(origin)">
							{{ t('launchpad', 'Remove') }}
						</NcButton>
					</li>
				</ul>
				<div class="embed-token-form__add-origin">
					<NcTextField
						v-model="newOrigin"
						:label="t('launchpad', 'Origin, e.g. https://www.example.com')"
						:error="errors.hostOrigins !== ''"
						:helperText="errors.hostOrigins"
						data-test="embed-token-new-origin"
						@keydown.enter.prevent="addOrigin" />
					<NcButton data-test="embed-token-add-origin" @click="addOrigin">
						{{ t('launchpad', 'Add') }}
					</NcButton>
				</div>
				<NcButton variant="tertiary" data-test="embed-token-csp-toggle" @click="showCsp = !showCsp">
					{{ showCsp ? t('launchpad', 'Hide CSP header') : t('launchpad', 'Preview CSP header') }}
				</NcButton>
				<pre v-if="showCsp" class="embed-token-form__csp" data-test="embed-token-csp">Content-Security-Policy: {{ csp }}</pre>
			</fieldset>

			<NcSelect
				:modelValue="selectedTheme"
				:inputLabel="t('launchpad', 'Theme')"
				:options="themeOptions"
				label="name"
				trackBy="id"
				:clearable="false"
				data-test="embed-token-theme"
				@update:modelValue="form.tenantThemeId = $event && $event.id !== '' ? $event.id : null" />
			<NcTextField
				v-model="form.tokenExpiresAt"
				type="date"
				:label="t('launchpad', 'Expires on (optional)')"
				data-test="embed-token-expires" />
		</template>

		<fieldset class="embed-token-form__group">
			<legend>{{ t('launchpad', 'Rate limit') }}</legend>
			<p class="embed-token-form__hint">
				{{ t('launchpad', 'Leave empty to use the organisation default.') }}
			</p>
			<NcTextField
				v-model="form.requestsPerMinute"
				type="number"
				min="1"
				:label="t('launchpad', 'Requests per minute')"
				data-test="embed-token-rpm" />
			<NcTextField
				v-model="form.burstSize"
				type="number"
				min="1"
				:label="t('launchpad', 'Burst size')"
				data-test="embed-token-burst" />
		</fieldset>

		<div class="embed-token-form__actions">
			<NcButton data-test="embed-token-cancel" @click="$emit('cancel')">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				type="submit"
				variant="primary"
				:disabled="saving"
				data-test="embed-token-submit">
				{{ editing ? t('launchpad', 'Save') : t('launchpad', 'Create token') }}
			</NcButton>
		</div>
	</form>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextArea, NcTextField } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../../services/api.js'
import {
	createPayload,
	cspPreview,
	formFromToken,
	normaliseOrigin,
	originProblem,
	updatePayload,
} from '../../../utils/embedToken.js'
import { logger } from '../../../utils/logger.js'

/**
 * EmbedTokenForm — the create and edit form of an embed token
 * (embedded-analytics task 18). Creating asks for the subject, scope, host
 * origins, theme, expiry and rate limit; editing a stored token only offers
 * what may change after issue (name, description, rate limit). Emits the
 * payload; the manager sends it.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
 */
export default {
	name: 'EmbedTokenForm',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The stored token to edit; null to create one. */
		token: {
			type: Object,
			default: null,
		},

		/** The dashboards a token can embed: {id, uuid, name}. */
		dashboards: {
			type: Array,
			default: () => [],
		},

		/** The tenant themes: {id, name}. */
		themes: {
			type: Array,
			default: () => [],
		},

		/** Pre-selected subject of a new token: {subjectType, subjectId, dashboardId}. */
		defaults: {
			type: Object,
			default: () => ({}),
		},

		/** Whether the manager is saving. */
		saving: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['submit', 'cancel'],

	data() {
		return {
			form: formFromToken(this.token, this.defaults),
			placements: [],
			loadingPlacements: false,
			newOrigin: '',
			showCsp: false,
			errors: { name: '', subject: '', hostOrigins: '' },
		}
	},

	computed: {
		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		editing() {
			return this.token !== null
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		subjectTypes() {
			return [
				{ id: 'dashboard', label: t('launchpad', 'A dashboard') },
				{ id: 'widget', label: t('launchpad', 'One widget') },
			]
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		modes() {
			return [
				{ id: 'read', label: t('launchpad', 'Read only') },
				{ id: 'read-with-interactions', label: t('launchpad', 'Read with interactions') },
			]
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		actions() {
			return [
				{ id: 'interact', label: t('launchpad', 'Change filters') },
				{ id: 'drillDown', label: t('launchpad', 'Drill down') },
				{ id: 'export', label: t('launchpad', 'Export data') },
			]
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		selectedDashboard() {
			return this.dashboards.find((dashboard) => dashboard.id === this.form.dashboardId) ?? null
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		placementOptions() {
			return this.placements.map((placement) => ({
				id: placement.id,
				label: placement.customTitle || placement.tileTitle || `${placement.widgetId} #${placement.id}`,
			}))
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		selectedPlacement() {
			return this.placementOptions.find((option) => String(option.id) === String(this.form.subjectId)) ?? null
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		themeOptions() {
			return [{ id: '', name: t('launchpad', 'Default theme') }, ...this.themes]
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		selectedTheme() {
			return this.themeOptions.find((theme) => theme.id === (this.form.tenantThemeId ?? '')) ?? this.themeOptions[0]
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		csp() {
			return cspPreview(this.form.hostOrigins)
		},
	},

	/** @spec openspec/changes/embedded-analytics/tasks.md#task-18 */
	created() {
		if (this.form.subjectType === 'widget' && this.form.dashboardId !== null) {
			this.loadPlacements(this.form.dashboardId)
		}
	},

	methods: {
		t,

		/**
		 * Choose a dashboard: the subject of a dashboard token, the source of a widget token's placements.
		 *
		 * @param {object|null} dashboard The dashboard.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
		 */
		pickDashboard(dashboard) {
			this.form.dashboardId = dashboard?.id ?? null
			this.form.subjectId = this.form.subjectType === 'dashboard' ? (dashboard?.uuid ?? '') : ''
			if (this.form.subjectType === 'widget' && dashboard) {
				this.loadPlacements(dashboard.id)
			}
		},

		/**
		 * Load the widget placements of a dashboard.
		 *
		 * @param {number|string} dashboardId The dashboard's numeric id.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
		 */
		async loadPlacements(dashboardId) {
			this.loadingPlacements = true
			try {
				const { data } = await api.getDashboardById(dashboardId)
				this.placements = Array.isArray(data?.placements) ? data.placements : []
			} catch (error) {
				this.placements = []
				logger.error('Failed to load the widgets of the dashboard:', error)
			} finally {
				this.loadingPlacements = false
			}
		},

		/**
		 * Add the typed origin when the server would accept it.
		 *
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
		 */
		addOrigin() {
			const problem = originProblem(this.newOrigin)
			if (problem === 'wildcard') {
				this.errors.hostOrigins = t('launchpad', 'Wildcards are not allowed; list each site.')
				return
			}
			if (problem === 'invalid') {
				this.errors.hostOrigins = t('launchpad', 'An origin is a scheme and a host, e.g. https://www.example.com, without a path.')
				return
			}
			const origin = normaliseOrigin(this.newOrigin)
			if (!this.form.hostOrigins.includes(origin)) {
				this.form.hostOrigins.push(origin)
			}
			this.newOrigin = ''
			this.errors.hostOrigins = ''
		},

		/**
		 * Remove an origin.
		 *
		 * @param {string} origin The origin.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		removeOrigin(origin) {
			this.form.hostOrigins = this.form.hostOrigins.filter((item) => item !== origin)
		},

		/**
		 * Check the form and emit its payload.
		 *
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
		 */
		submit() {
			this.errors = {
				name: this.form.name.trim() === '' ? t('launchpad', 'Give the token a name.') : '',
				subject: !this.editing && this.form.subjectId === '' ? t('launchpad', 'Choose what to embed.') : '',
				hostOrigins: !this.editing && this.form.hostOrigins.length === 0 ? t('launchpad', 'Add at least one site that may embed it.') : '',
			}
			if (Object.values(this.errors).some((message) => message !== '')) {
				return
			}
			this.$emit('submit', this.editing ? updatePayload(this.form) : createPayload(this.form))
		},
	},
}
</script>

<style scoped>
.embed-token-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 640px;
}

.embed-token-form__group {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 12px;
}

.embed-token-form__group legend {
	font-weight: bold;
	padding: 0 4px;
}

.embed-token-form__origins {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.embed-token-form__origins li,
.embed-token-form__add-origin,
.embed-token-form__actions {
	display: flex;
	align-items: center;
	gap: 8px;
}

.embed-token-form__actions {
	justify-content: flex-end;
}

.embed-token-form__csp {
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
	padding: 8px;
	white-space: pre-wrap;
	word-break: break-all;
}

.embed-token-form__hint {
	color: var(--color-text-maxcontrast);
}

.embed-token-form__error {
	color: var(--color-error-text);
}
</style>
