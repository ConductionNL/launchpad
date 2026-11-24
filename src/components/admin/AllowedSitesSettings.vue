<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<!-- REQ-ASET-021 (board LpBeheerAlgemeen): one list of the outside
	     websites widgets may embed or fetch from. The server normalises every
	     host and refuses an entry that is not one; its message shows here. -->
	<CnSettingsSection
		:name="t('launchpad', 'Allowed websites')"
		data-testid="admin-allowed-sites">
		<p class="allowed-sites__hint">
			{{ t('launchpad', 'Widgets may only embed pages and fetch data from these sites.') }}
		</p>

		<ul v-if="rows.length > 0" class="allowed-sites__list" data-testid="admin-allowed-sites-list">
			<li
				v-for="row in rows"
				:key="row.host"
				class="allowed-sites__item"
				data-testid="admin-allowed-site">
				<code class="allowed-sites__host">{{ row.host }}</code>
				<NcSelect
					:modelValue="optionFor(row.purpose)"
					:options="purposeOptions"
					:clearable="false"
					:disabled="!loaded || saving"
					:aria-label-combobox="t('launchpad', 'Purpose of {host}', { host: row.host })"
					label="label"
					class="allowed-sites__purpose"
					@update:modelValue="(option) => setPurpose(row.host, option)" />
				<NcButton
					variant="tertiary"
					:aria-label="t('launchpad', 'Remove {host}', { host: row.host })"
					:disabled="saving"
					@click="removeRow(row.host)">
					{{ t('launchpad', 'Remove') }}
				</NcButton>
			</li>
		</ul>
		<p v-else-if="loaded" class="allowed-sites__hint" data-testid="admin-allowed-sites-empty">
			{{ t('launchpad', 'No websites on this list yet.') }}
		</p>

		<form class="allowed-sites__add" @submit.prevent="add">
			<NcTextField
				v-model="newHost"
				:label="t('launchpad', 'Website')"
				:helperText="t('launchpad', 'A host such as www.example.com, without https:// or a path.')"
				data-testid="admin-allowed-site-input" />
			<NcSelect
				v-model="newPurpose"
				:options="purposeOptions"
				:clearable="false"
				:inputLabel="t('launchpad', 'Purpose')"
				label="label"
				class="allowed-sites__purpose"
				data-testid="admin-allowed-site-purpose" />
			<NcButton
				type="submit"
				:disabled="newHost.trim() === '' || saving"
				data-testid="admin-allowed-site-add">
				{{ t('launchpad', 'Add website') }}
			</NcButton>
		</form>

		<p
			v-if="error"
			class="allowed-sites__error"
			role="alert"
			data-testid="admin-allowed-sites-error">
			{{ error }}
		</p>
		<div class="allowed-sites__actions">
			<NcButton
				variant="primary"
				:disabled="!loaded || saving"
				data-testid="admin-allowed-sites-save"
				@click="save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
		</div>
	</CnSettingsSection>
</template>

<script>
import { CnSettingsSection, NcButton, NcSelect, NcTextField } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { addRow, PURPOSES, rowsFrom, siteFor } from '../../utils/allowedSites.js'
import { logger } from '../../utils/logger.js'

/**
 * AllowedSitesSettings — the General tab's "Allowed websites" block
 * (REQ-ASET-021): hosts with their purpose, Add website and Save.
 *
 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
export default {
	name: 'AllowedSitesSettings',

	components: {
		CnSettingsSection,
		NcButton,
		NcSelect,
		NcTextField,
	},

	data() {
		return {
			rows: [],
			newHost: '',
			newPurpose: null,
			error: '',
			loaded: false,
			saving: false,
		}
	},

	computed: {
		/**
		 * @return {Array<{id: string, label: string}>} The purposes, translated.
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		purposeOptions() {
			const labels = {
				embed: t('launchpad', 'Embed'),
				fetch: t('launchpad', 'Fetch'),
				both: t('launchpad', 'Embed and fetch'),
			}
			return PURPOSES.map((id) => ({ id, label: labels[id] }))
		},
	},

	/** @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021 */
	created() {
		this.newPurpose = this.optionFor('embed')
		this.load()
	},

	methods: {
		t,

		/**
		 * @param {string} purpose The purpose id.
		 * @return {{id: string, label: string}} Its select option.
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		optionFor(purpose) {
			return this.purposeOptions.find((option) => option.id === purpose) ?? this.purposeOptions[0]
		},

		/**
		 * Read the stored list.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		async load() {
			try {
				const { data } = await api.getAllowedSites()
				this.rows = rowsFrom(data)
			} catch (error) {
				logger.error('Failed to load the allowed websites:', error)
			} finally {
				this.loaded = true
			}
		},

		/**
		 * Add the entered host with the chosen purpose (saved with Save).
		 *
		 * @return {void}
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		add() {
			this.rows = addRow(this.rows, this.newHost, this.newPurpose?.id ?? 'embed')
			this.newHost = ''
		},

		/**
		 * @param {string} host The row's host.
		 * @param {{id: string}|null} option The chosen purpose.
		 * @return {void}
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		setPurpose(host, option) {
			this.rows = addRow(this.rows, host, option?.id ?? 'embed')
		},

		/**
		 * @param {string} host The row's host.
		 * @return {void}
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		removeRow(host) {
			this.rows = this.rows.filter((row) => row.host !== host)
		},

		/**
		 * Save the list; the server's refusal is shown as it is.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
		 */
		async save() {
			this.saving = true
			this.error = ''
			try {
				const { data } = await api.updateAllowedSites(this.rows.map((row) => siteFor(row.host, row.purpose)))
				this.rows = rowsFrom(data)
				showSuccess(t('launchpad', 'Allowed websites saved.'))
			} catch (error) {
				logger.error('Failed to save the allowed websites:', error)
				this.error = error?.response?.data?.message || t('launchpad', 'The allowed websites could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.allowed-sites__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 12px;
}

.allowed-sites__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 16px;
}

.allowed-sites__item,
.allowed-sites__add {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.allowed-sites__host {
	flex: 1 1 200px;
	overflow-wrap: anywhere;
}

.allowed-sites__purpose {
	min-width: 180px;
}

.allowed-sites__error {
	color: var(--color-error-text);
	margin-top: 8px;
}

.allowed-sites__actions {
	margin-top: 16px;
}
</style>
