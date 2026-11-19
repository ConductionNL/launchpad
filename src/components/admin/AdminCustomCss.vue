<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="custom-css" data-testid="admin-custom-css">
		<p class="custom-css__hint">
			{{
				t(
					'launchpad',
					'Cleaned on save: imports, addresses to other sites and script-like constructs are removed, and every rule is limited to LaunchPad.',
				)
			}}
		</p>
		<label class="custom-css__label" for="launchpad-admin-custom-css">
			{{ t('launchpad', 'CSS for all of LaunchPad') }}
		</label>
		<textarea
			id="launchpad-admin-custom-css"
			v-model="css"
			class="custom-css__input"
			data-testid="admin-custom-css-input"
			rows="10"
			spellcheck="false"
			:disabled="loading || saving" />
		<div class="custom-css__actions">
			<NcButton
				variant="primary"
				data-testid="admin-custom-css-save"
				:disabled="loading || saving"
				@click="save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
		</div>
		<p
			v-if="removed.length > 0"
			class="custom-css__removed"
			role="status"
			data-testid="admin-custom-css-removed">
			{{ t('launchpad', 'Removed: {constructs}', { constructs: removed.join(', ') }) }}
		</p>
		<p
			v-else-if="saved"
			class="custom-css__saved"
			role="status"
			data-testid="admin-custom-css-saved">
			{{ t('launchpad', 'CSS saved') }}
		</p>
		<p
			v-if="error !== ''"
			class="custom-css__error"
			role="alert"
			data-testid="admin-custom-css-error">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { NcButton } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * AdminCustomCss — Beheer ▸ General section for the administrator CSS of all
 * of LaunchPad (platform-dashboard-theme-and-css REQ-THM-003). The server
 * stores only the cleaned and scoped CSS; after a save the field shows that
 * stored version and the section names what the cleaning removed.
 */
export default {
	name: 'AdminCustomCss',

	components: {
		NcButton,
	},

	data() {
		return {
			css: '',
			removed: [],
			saved: false,
			error: '',
			loading: true,
			saving: false,
		}
	},

	/** @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003 */
	created() {
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003 */
		async load() {
			this.loading = true
			try {
				const { data } = await api.getCustomCss()
				this.css = typeof data?.customCss === 'string' ? data.customCss : ''
			} catch (error) {
				logger.error('Failed to load the custom CSS:', error)
				this.error = t('launchpad', 'The custom CSS could not be loaded.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * Save the CSS, then show what the server stored and what it removed.
		 *
		 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
		 */
		async save() {
			this.saving = true
			this.saved = false
			this.error = ''
			this.removed = []
			try {
				const { data } = await api.updateCustomCss(this.css)
				this.showSaved(data)
			} catch (error) {
				logger.error('Failed to save the custom CSS:', error)
				this.error = saveErrorMessage(error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * Show the CSS the server stored and what its cleaning removed.
		 *
		 * @param {object|undefined} data The save response.
		 * @return {void}
		 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
		 */
		showSaved(data) {
			this.css = typeof data?.customCss === 'string' ? data.customCss : ''
			this.removed = Array.isArray(data?.removed) ? data.removed : []
			this.saved = true
		},
	},
}

/**
 * The server's refusal message, or a general one when it sent none.
 *
 * @param {Error} error The failed request.
 * @return {string}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */
function saveErrorMessage(error) {
	const message = error?.response?.data?.error
	return typeof message === 'string' && message !== ''
		? message
		: t('launchpad', 'The custom CSS could not be saved.')
}
</script>

<style scoped>
.custom-css__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 8px;
}

.custom-css__label {
	display: block;
	font-weight: bold;
	margin-bottom: 4px;
}

.custom-css__input {
	width: 100%;
	font-family: var(--font-face-monospace, monospace);
	color: var(--color-main-text);
	background-color: var(--color-main-background);
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	padding: 8px;
}

.custom-css__actions {
	margin-top: 8px;
}

.custom-css__removed,
.custom-css__saved {
	margin-top: 8px;
	color: var(--color-main-text);
}

.custom-css__error {
	margin-top: 8px;
	color: var(--color-error-text, var(--color-error));
}
</style>
