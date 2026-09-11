<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="registry-settings" data-test="dashboard-registry-settings">
		<h3>{{ t('launchpad', 'Dashboard registry') }}</h3>
		<p class="registry-settings__hint">
			{{
				t(
					'launchpad',
					'Connect a registry to install dashboards that other organisations published.',
				)
			}}
		</p>

		<NcNoteCard
			v-if="loaded && !available"
			type="warning"
			data-test="registry-engine-missing">
			{{ t('launchpad', 'Enable OpenRegister so you can reach a registry.') }}
		</NcNoteCard>

		<form class="registry-settings__form" @submit.prevent="save">
			<div class="registry-settings__field">
				<NcTextField
					v-model="registryUrl"
					:label="t('launchpad', 'Registry URL')"
					:helperText="
						t(
							'launchpad',
							'Use the address of the instance that publishes the templates.',
						)
					"
					type="url"
					autocomplete="off"
					:disabled="!loaded || saving"
					data-test="registry-url" />
			</div>

			<div class="registry-settings__field">
				<NcTextField
					v-model="registryRegister"
					:label="t('launchpad', 'Register')"
					:helperText="t('launchpad', 'Leave empty to use launchpad.')"
					autocomplete="off"
					:disabled="!loaded || saving"
					data-test="registry-register" />
			</div>

			<div class="registry-settings__field">
				<!--
				  The token field is WRITE-ONLY and always starts empty. The server
				  never returns the token, only whether one is set, so there is
				  nothing to pre-fill and nothing to leak. `new-password` stops
				  the browser offering to autofill the administrator's own
				  Nextcloud password into it.
				-->
				<NcPasswordField
					v-model="tokenInput"
					:label="t('launchpad', 'Access token')"
					autocomplete="new-password"
					:disabled="!loaded || saving"
					data-test="registry-token" />
				<p class="registry-settings__hint" data-test="registry-token-status">
					{{
						tokenConfigured
							? t(
									'launchpad',
									'A token is set. Enter a new one to replace it.',
								)
							: t(
									'launchpad',
									'No token is set. Add one if the registry asks for it.',
								)
					}}
				</p>
			</div>

			<div class="registry-settings__actions">
				<NcButton
					type="submit"
					variant="primary"
					:disabled="!loaded || saving"
					data-test="registry-save">
					{{ t('launchpad', 'Save registry') }}
				</NcButton>
				<NcButton
					v-if="tokenConfigured"
					variant="tertiary"
					:disabled="!loaded || saving"
					data-test="registry-remove-token"
					@click="removeToken">
					{{ t('launchpad', 'Remove token') }}
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script>
import {
	NcButton,
	NcNoteCard,
	NcPasswordField,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

/**
 * DashboardRegistrySettings — Beheer ▸ Sharing section that connects
 * LaunchPad to a dashboard registry (dashboard-store spec, REQ-STORE-009).
 *
 * The three values live in Nextcloud's app config under `launchpad`, which
 * is where OpenRegister's store client reads them. LaunchPad's other admin
 * settings live in a table of their own, so these have their own endpoint.
 *
 * 🔴 THE TOKEN IS WRITE-ONLY. The server answers `tokenConfigured` and never
 * the token itself, and this component never sends the token back unless
 * the administrator typed a new one. An empty token field on save means
 * "leave it alone"; removing the token is its own explicit button, so a save
 * that only changes the URL cannot wipe a token by accident.
 */
export default {
	name: 'DashboardRegistrySettings',

	components: {
		NcButton,
		NcNoteCard,
		NcPasswordField,
		NcTextField,
	},

	data() {
		return {
			registryUrl: '',
			registryRegister: '',
			tokenInput: '',
			tokenConfigured: false,
			available: true,
			loaded: false,
			saving: false,
		}
	},

	/** @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-009-an-administrator-must-be-able-to-connect-a-registry-without-a-shell */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Read the redacted connection and fill the form from it.
		 *
		 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-009-an-administrator-must-be-able-to-connect-a-registry-without-a-shell
		 * @return {Promise<void>}
		 */
		async load() {
			try {
				const { data } = await api.getStoreConfig()
				this.apply(data)
				this.available = data?.available !== false
			} catch (error) {
				logger.error('Failed to load the dashboard registry:', error)
			} finally {
				this.loaded = true
			}
		},

		/**
		 * Save the URL and register, and the token only when one was typed.
		 *
		 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-009-an-administrator-must-be-able-to-connect-a-registry-without-a-shell
		 * @return {Promise<void>}
		 */
		async save() {
			const payload = {
				registryUrl: this.registryUrl.trim(),
				registryRegister: this.registryRegister.trim(),
			}
			if (this.tokenInput !== '') {
				payload.registryToken = this.tokenInput
			}

			await this.write(payload, t('launchpad', 'Registry saved.'))
		},

		/**
		 * Clear the stored token, and nothing else.
		 *
		 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-009-an-administrator-must-be-able-to-connect-a-registry-without-a-shell
		 * @return {Promise<void>}
		 */
		async removeToken() {
			await this.write({ registryToken: '' }, t('launchpad', 'Token removed.'))
		},

		/**
		 * Send one update and apply the redacted answer.
		 *
		 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-009-an-administrator-must-be-able-to-connect-a-registry-without-a-shell
		 * @param {object} payload The keys to change.
		 * @param {string} message The toast shown on success.
		 * @return {Promise<void>}
		 */
		async write(payload, message) {
			this.saving = true
			try {
				const { data } = await api.updateStoreConfig(payload)
				this.apply(data)
				showSuccess(message)
			} catch (error) {
				logger.error('Failed to save the dashboard registry:', error)
				showError(t('launchpad', 'The registry could not be saved.'))
			} finally {
				this.saving = false
			}
		},

		/**
		 * Fill the form from a server answer. The token field is always
		 * emptied: whatever was typed has been sent, and the server has
		 * nothing to give back.
		 *
		 * @spec openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#requirement-req-store-009-an-administrator-must-be-able-to-connect-a-registry-without-a-shell
		 * @param {object} data The redacted connection.
		 */
		apply(data) {
			this.registryUrl =
				typeof data?.registryUrl === 'string' ? data.registryUrl : ''
			this.registryRegister =
				typeof data?.registryRegister === 'string'
					? data.registryRegister
					: ''
			this.tokenConfigured = data?.tokenConfigured === true
			this.tokenInput = ''
		},
	},
}
</script>

<style scoped>
.registry-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.registry-settings__field {
	margin-bottom: 16px;
	max-width: 480px;
}

.registry-settings__actions {
	display: flex;
	gap: 8px;
}
</style>
