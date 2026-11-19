<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="tenant-theme-manager" data-test="tenant-theme-manager">
		<h3>{{ t('launchpad', 'Embed themes') }}</h3>

		<NcNoteCard v-if="error !== ''" type="error" data-test="tenant-theme-error">
			{{ error }}
		</NcNoteCard>

		<form
			v-if="editing"
			class="tenant-theme-manager__form"
			data-test="tenant-theme-form"
			@submit.prevent="save">
			<NcTextField v-model="form.name" :label="t('launchpad', 'Name')" required data-test="tenant-theme-name" />
			<div v-for="variable in variables" :key="variable.name" class="tenant-theme-manager__variable">
				<input
					:id="'tenant-theme-' + variable.name"
					v-model="form.cssVariables[variable.name]"
					type="color"
					:aria-label="variable.label"
					:data-test="'tenant-theme-color' + variable.name">
				<NcTextField
					v-model="form.cssVariables[variable.name]"
					:label="variable.label + ' (' + variable.name + ')'"
					:data-test="'tenant-theme-var' + variable.name" />
			</div>
			<NcTextField
				v-model="form.logoUrl"
				:label="t('launchpad', 'Logo URL (https)')"
				data-test="tenant-theme-logo" />
			<NcTextArea
				v-model="form.customCss"
				:label="t('launchpad', 'Custom CSS, :root rules only')"
				:error="cssProblem !== ''"
				:helperText="cssProblem"
				data-test="tenant-theme-css" />

			<div class="tenant-theme-manager__preview" :style="previewStyle" data-test="tenant-theme-preview">
				<img v-if="safeLogo" :src="safeLogo" alt="" class="tenant-theme-manager__preview-logo">
				<strong>{{ t('launchpad', 'Sample widget') }}</strong>
				<p>{{ t('launchpad', 'Text on the surface colour.') }}</p>
				<span class="tenant-theme-manager__preview-button">{{ t('launchpad', 'Primary action') }}</span>
			</div>

			<div class="tenant-theme-manager__row tenant-theme-manager__row--end">
				<NcButton data-test="tenant-theme-cancel" @click="editing = null">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton
					type="submit"
					variant="primary"
					:disabled="saving || cssProblem !== ''"
					data-test="tenant-theme-save">
					{{ t('launchpad', 'Save') }}
				</NcButton>
			</div>
		</form>

		<template v-else>
			<div class="tenant-theme-manager__row">
				<NcButton variant="primary" data-test="tenant-theme-new" @click="edit(null)">
					{{ t('launchpad', 'New theme') }}
				</NcButton>
			</div>
			<NcLoadingIcon v-if="loading" />
			<p v-else-if="themes.length === 0" class="tenant-theme-manager__empty" data-test="tenant-theme-empty">
				{{ t('launchpad', 'No themes yet. Embeds use the default theme.') }}
			</p>
			<table v-else class="tenant-theme-manager__table" data-test="tenant-theme-table">
				<thead>
					<tr>
						<th scope="col">{{ t('launchpad', 'Name') }}</th>
						<th scope="col">{{ t('launchpad', 'Primary colour') }}</th>
						<th scope="col">{{ t('launchpad', 'Logo') }}</th>
						<th scope="col">{{ t('launchpad', 'Created') }}</th>
						<th scope="col">{{ t('launchpad', 'Tokens') }}</th>
						<th scope="col"><span class="hidden-visually">{{ t('launchpad', 'Actions') }}</span></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="theme in themes" :key="theme.id" :data-test="'tenant-theme-row-' + theme.id">
						<td>{{ theme.name }}</td>
						<td>
							<span class="tenant-theme-manager__swatch" :style="{ background: (theme.cssVariables || {})['--c-primary'] }" />
							{{ (theme.cssVariables || {})['--c-primary'] || '' }}
						</td>
						<td>{{ theme.logoUrl ? t('launchpad', 'Yes') : t('launchpad', 'No') }}</td>
						<td>{{ theme.createdAt }}</td>
						<td>{{ theme.tokenCount || 0 }}</td>
						<td class="tenant-theme-manager__row">
							<NcButton variant="tertiary" :data-test="'tenant-theme-edit-' + theme.id" @click="edit(theme)">
								{{ t('launchpad', 'Edit') }}
							</NcButton>
							<NcButton variant="tertiary" :data-test="'tenant-theme-copy-' + theme.id" @click="copyJson(theme)">
								{{ t('launchpad', 'Copy JSON') }}
							</NcButton>
							<NcButton variant="tertiary" :data-test="'tenant-theme-delete-' + theme.id" @click="deleting = theme">
								{{ t('launchpad', 'Delete') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</template>

		<DeleteTenantThemeDialog
			v-if="deleting"
			:open="true"
			:name="deleting.name"
			:tokenCount="deleting.tokenCount || 0"
			@update:open="deleting = $event ? deleting : null"
			@confirm="remove" />
	</section>
</template>

<script>
import { NcButton, NcLoadingIcon, NcNoteCard, NcTextArea, NcTextField } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import DeleteTenantThemeDialog from '../../../dialogs/DeleteTenantThemeDialog.vue'
import { embedApi } from '../../../services/embedApi.js'
import { onlyRootRules } from '../../../utils/embedToken.js'
import { logger } from '../../../utils/logger.js'

/** The variables the form offers, with the platform defaults of a new theme. */
const VARIABLES = [
	{ name: '--c-primary', fallback: '#0b5394' },
	{ name: '--c-surface', fallback: '#ffffff' },
	{ name: '--c-text', fallback: '#222222' },
	{ name: '--c-border', fallback: '#dddddd' },
]

/**
 * TenantThemeManager — Beheer ▸ Embeds: the tenant themes embeds can use
 * (embedded-analytics task 16, REQ-EMB-005). Lists them with their primary
 * colour and how many tokens use them; creates and edits them with a live
 * preview; deletes them, warning first when tokens use one; copies a
 * theme's JSON. The server checks names, values, :root-only CSS, the https
 * logo and contrast; its refusal message is shown.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
 */
export default {
	name: 'TenantThemeManager',

	components: {
		DeleteTenantThemeDialog,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcTextArea,
		NcTextField,
	},

	data() {
		return {
			themes: [],
			loading: true,
			saving: false,
			editing: null,
			deleting: null,
			form: this.emptyForm(),
			error: '',
		}
	},

	computed: {
		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		variables() {
			const labels = {
				'--c-primary': t('launchpad', 'Primary colour'),
				'--c-surface': t('launchpad', 'Surface colour'),
				'--c-text': t('launchpad', 'Text colour'),
				'--c-border': t('launchpad', 'Border colour'),
			}
			return VARIABLES.map((variable) => ({ ...variable, label: labels[variable.name] }))
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		cssProblem() {
			return onlyRootRules(this.form.customCss) ? '' : t('launchpad', 'Custom CSS may only set :root rules.')
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		previewStyle() {
			const vars = this.form.cssVariables
			return {
				...vars,
				background: vars['--c-surface'],
				color: vars['--c-text'],
				borderColor: vars['--c-border'],
				'--preview-primary': vars['--c-primary'],
			}
		},

		/** @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md */
		safeLogo() {
			return /^https:\/\//.test(this.form.logoUrl) ? this.form.logoUrl : ''
		},
	},

	/** @spec openspec/changes/embedded-analytics/tasks.md#task-16 */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * @return {object} A new theme's form.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		emptyForm() {
			return {
				name: '',
				cssVariables: Object.fromEntries(VARIABLES.map((variable) => [variable.name, variable.fallback])),
				logoUrl: '',
				customCss: '',
			}
		},

		/** @spec openspec/changes/embedded-analytics/tasks.md#task-16 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				this.themes = await embedApi.listThemes()
			} catch (error) {
				this.error = t('launchpad', 'Themes could not be loaded.')
				logger.error('Failed to load tenant themes:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Open the form for a theme, or for a new one.
		 *
		 * @param {object|null} theme The theme.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		edit(theme) {
			const form = this.emptyForm()
			if (theme) {
				form.name = theme.name ?? ''
				form.cssVariables = { ...form.cssVariables, ...(theme.cssVariables ?? {}) }
				form.logoUrl = theme.logoUrl ?? ''
				form.customCss = theme.customCss ?? ''
			}
			this.form = form
			this.editing = theme ?? { id: '' }
			this.error = ''
		},

		/** @spec openspec/changes/embedded-analytics/tasks.md#task-16 */
		async save() {
			this.saving = true
			this.error = ''
			const theme = {
				name: this.form.name.trim(),
				cssVariables: Object.fromEntries(Object.entries(this.form.cssVariables).filter(([, value]) => String(value).trim() !== '')),
				logoUrl: this.form.logoUrl.trim(),
				customCss: this.form.customCss,
			}
			try {
				if (this.editing.id) {
					await embedApi.updateTheme(this.editing.id, theme)
				} else {
					await embedApi.createTheme(theme)
				}
				this.editing = null
				showSuccess(t('launchpad', 'Theme saved.'))
				await this.load()
			} catch (error) {
				this.error = error?.response?.data?.message || t('launchpad', 'The theme could not be saved.')
				logger.error('Failed to save tenant theme:', error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * Delete the theme in the dialog; one tokens use is deleted with force, the dialog having warned.
		 *
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
		 */
		async remove() {
			const theme = this.deleting
			this.deleting = null
			this.error = ''
			try {
				await embedApi.deleteTheme(theme.id, (theme.tokenCount ?? 0) > 0)
				this.themes = this.themes.filter((item) => item.id !== theme.id)
				showSuccess(t('launchpad', 'Theme deleted.'))
			} catch (error) {
				this.error = error?.response?.data?.message || t('launchpad', 'The theme could not be deleted.')
				logger.error('Failed to delete tenant theme:', error)
			}
		},

		/**
		 * Copy a theme's JSON (name, variables, logo, custom CSS) for documentation.
		 *
		 * @param {object} theme The theme.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		async copyJson(theme) {
			const { name, cssVariables, logoUrl, customCss } = theme
			try {
				await navigator.clipboard.writeText(JSON.stringify({ name, cssVariables, logoUrl, customCss }, null, 2))
				showSuccess(t('launchpad', 'Theme JSON copied.'))
			} catch (error) {
				logger.error('Failed to copy the theme JSON:', error)
			}
		},
	},
}
</script>

<style scoped>
.tenant-theme-manager {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.tenant-theme-manager__form {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 640px;
}

.tenant-theme-manager__row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.tenant-theme-manager__row--end {
	justify-content: flex-end;
}

.tenant-theme-manager__variable {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.tenant-theme-manager__variable input[type='color'] {
	width: 44px;
	height: 36px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.tenant-theme-manager__preview {
	border: 1px solid;
	border-radius: var(--border-radius-large);
	padding: 12px;
}

.tenant-theme-manager__preview-logo {
	display: block;
	max-height: 32px;
	margin-bottom: 8px;
}

.tenant-theme-manager__preview-button {
	display: inline-block;
	padding: 4px 12px;
	border-radius: var(--border-radius);
	background: var(--preview-primary);
	color: var(--color-primary-element-text);
}

.tenant-theme-manager__table {
	width: 100%;
	border-collapse: collapse;
}

.tenant-theme-manager__table th,
.tenant-theme-manager__table td {
	text-align: start;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.tenant-theme-manager__swatch {
	display: inline-block;
	width: 16px;
	height: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	vertical-align: middle;
}

.tenant-theme-manager__empty {
	color: var(--color-text-maxcontrast);
}
</style>
