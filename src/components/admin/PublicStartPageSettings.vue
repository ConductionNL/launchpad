<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="public-start-settings" data-test="public-start-settings">
		<h3>{{ t('launchpad', 'Public start page') }}</h3>
		<p class="public-start-settings__hint">
			{{
				t(
					'launchpad',
					'Visitors who are not signed in see this public dashboard instead of the login screen. Staff sign in from it.',
				)
			}}
		</p>

		<p
			v-if="loaded && active"
			class="public-start-settings__status"
			data-test="public-start-status">
			{{ t('launchpad', 'Public start page: {name}', { name: chosenName }) }}
		</p>
		<NcNoteCard
			v-else-if="loaded && offReason"
			type="warning"
			data-test="public-start-reason">
			{{ offReason }}
		</NcNoteCard>

		<div class="public-start-settings__field">
			<NcSelect
				v-model="selected"
				:inputLabel="t('launchpad', 'Public link')"
				:options="options"
				label="label"
				trackBy="token"
				:clearable="true"
				:disabled="!loaded || saving"
				:placeholder="t('launchpad', 'Choose a public link without a password')"
				data-test="public-start-link" />
			<p
				v-if="loaded && options.length === 0"
				class="public-start-settings__hint"
				data-test="public-start-no-links">
				{{
					t(
						'launchpad',
						'There is no public link without a password that has not expired. Share a dashboard on a public link first.',
					)
				}}
			</p>
		</div>

		<NcCheckboxRadioSwitch
			v-model="enabled"
			type="switch"
			:disabled="!loaded || saving"
			data-test="public-start-enabled">
			{{ t('launchpad', 'Show the public start page to visitors who are not signed in') }}
		</NcCheckboxRadioSwitch>

		<div class="public-start-settings__actions">
			<NcButton
				variant="primary"
				:disabled="!loaded || saving || (enabled && !selected)"
				data-test="public-start-save"
				@click="save">
				{{ t('launchpad', 'Save start page') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcNoteCard,
	NcSelect,
} from '@conduction/nextcloud-vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * PublicStartPageSettings — Beheer ▸ Sharing section that picks the public
 * link signed-out visitors see instead of the login form
 * (sharing-public-start-page REQ-PSP-001, design D1).
 *
 * Only links the server offers are listed: no password, not revoked, not
 * expired. When the chosen link later goes bad the start page is off, and
 * this section says why; the server keeps the choice so the administrator
 * sees what was chosen.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
 */
export default {
	name: 'PublicStartPageSettings',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcSelect,
	},

	data() {
		return {
			enabled: false,
			token: '',
			active: false,
			reason: null,
			candidates: [],
			selected: null,
			loaded: false,
			saving: false,
		}
	},

	computed: {
		/**
		 * The links that may be chosen, named by their dashboard.
		 *
		 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
		 * @return {Array<{token: string, label: string}>}
		 */
		options() {
			return this.candidates.map((candidate) => ({
				token: candidate.token,
				label: candidate.dashboardName || candidate.token,
			}))
		},

		/**
		 * The name of the dashboard the start page shows.
		 *
		 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
		 * @return {string}
		 */
		chosenName() {
			const chosen = this.options.find((option) => option.token === this.token)
			return chosen ? chosen.label : this.token
		},

		/**
		 * Why a switched-on start page is not shown, or '' when it is shown or off.
		 *
		 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
		 * @return {string}
		 */
		offReason() {
			if (!this.enabled || this.active) {
				return ''
			}
			switch (this.reason) {
			case 'no-link':
				return t('launchpad', 'The start page is off: no public link is chosen.')
			case 'not-found':
				return t('launchpad', 'The start page is off: the chosen public link no longer exists.')
			case 'revoked':
				return t('launchpad', 'The start page is off: the chosen public link has been revoked.')
			case 'password':
				return t('launchpad', 'The start page is off: the chosen public link now has a password.')
			case 'expired':
				return t('launchpad', 'The start page is off: the chosen public link has expired.')
			default:
				return ''
			}
		},
	},

	/** @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001 */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Read the status and the links that may be chosen.
		 *
		 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
		 * @return {Promise<void>}
		 */
		async load() {
			try {
				const { data } = await api.getPublicStart()
				this.candidates = Array.isArray(data?.candidates) ? data.candidates : []
				this.apply(data)
			} catch (error) {
				logger.error('Failed to load the public start page:', error)
			} finally {
				this.loaded = true
			}
		},

		/**
		 * Save the chosen link and the switch; the server's refusal is shown as it is.
		 *
		 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
		 * @return {Promise<void>}
		 */
		async save() {
			this.saving = true
			try {
				const { data } = await api.updatePublicStart({
					enabled: this.enabled,
					token: this.selected ? this.selected.token : '',
				})
				this.apply(data)
				showSuccess(t('launchpad', 'Start page saved.'))
			} catch (error) {
				logger.error('Failed to save the public start page:', error)
				showError(error?.response?.data?.message || t('launchpad', 'The start page could not be saved.'))
			} finally {
				this.saving = false
			}
		},

		/**
		 * Fill the section from a server status.
		 *
		 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-administrators-choose-a-public-link-as-the-start-page-req-psp-001
		 * @param {object} data The status.
		 */
		apply(data) {
			this.enabled = data?.enabled === true
			this.token = typeof data?.token === 'string' ? data.token : ''
			this.active = data?.active === true
			this.reason = typeof data?.reason === 'string' ? data.reason : null
			this.selected = this.options.find((option) => option.token === this.token) || null
		},
	},
}
</script>

<style scoped>
.public-start-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.public-start-settings__status {
	font-weight: bold;
	margin-bottom: 16px;
}

.public-start-settings__field {
	margin-bottom: 16px;
}

.public-start-settings__actions {
	margin-top: 16px;
}
</style>
